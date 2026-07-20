<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Mail\CampaignMail;
use App\Models\EmailRecipient;
use App\Models\EmailSuppression;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * إرسال رسالة حملة واحدة لمستلم واحد — الوحدة الذرّية للطابور. لا BCC ولا تجميع
 * عناوين. حارس idempotency: يتخطّى إن أُلغيت الدفعة أو لم يعد المستلم queued.
 * يُعيد فحص الحظر وقت الإرسال (قد يُلغي المستلم اشتراكه بعد الجدولة قبل دوره).
 */
class SendCampaignEmailJob implements ShouldQueue
{
    use Batchable;
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    /**
     * @var array<int, int>
     */
    public array $backoff = [60, 300];

    public function __construct(public int $recipientId) {}

    public function handle(): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $rec = EmailRecipient::with('campaign')->find($this->recipientId);

        if ($rec === null || $rec->status !== 'queued') {
            return;
        }

        // ألغى اشتراكه بين الجدولة والإرسال؟ لا نرسل، ونُعلّمه.
        if (EmailSuppression::query()->where('email', $rec->email)->exists()) {
            $rec->update(['status' => 'unsubscribed']);

            return;
        }

        Mail::to($rec->email)->send(new CampaignMail(
            $rec->campaign->subject,
            $rec->campaign->preheader,
            $rec->campaign->body_html,
            $rec->name,
            $rec->token,
        ));

        $rec->update(['status' => 'sent', 'sent_at' => now()]);
        $rec->campaign->increment('sent_count');
    }

    public function failed(?Throwable $e): void
    {
        $rec = EmailRecipient::with('campaign')->find($this->recipientId);
        $rec?->update(['status' => 'failed', 'error' => $e?->getMessage()]);
        $rec?->campaign?->increment('failed_count');
    }
}
