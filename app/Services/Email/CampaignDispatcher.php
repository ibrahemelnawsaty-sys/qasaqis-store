<?php

declare(strict_types=1);

namespace App\Services\Email;

use App\Jobs\SendCampaignEmailJob;
use App\Models\Customer;
use App\Models\EmailCampaign;
use App\Models\EmailRecipient;
use App\Models\EmailSuppression;
use App\Models\User;
use App\Support\Email\CampaignAudience;
use App\Support\Email\CampaignHtml;
use Illuminate\Bus\Batch;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;
use Throwable;

/**
 * يبني حملة ويجدولها على الطابور: يحلّ العناوين من الجماهير المختارة، يزيل
 * المحظورين والمكرّرين، يخزّن المحتوى **معقَّمًا**، ثم يبعث دفعة (Bus::batch) مهمّة
 * لكل مستلم — لا BCC ولا كشف عناوين.
 *
 * التحكّم بالمعدّل عبر ->delay() متدرّج (دفعة كل دقيقة بحجم per_minute) بلا اعتماد
 * على cache؛ يتناغم مع queue:work المُجدول كل دقيقة على الاستضافة المشتركة.
 */
final class CampaignDispatcher
{
    public function dispatch(
        int $createdBy,
        string $subject,
        ?string $preheader,
        string $bodyHtml,
        ?string $templateKey,
        array $audiences,
        ?string $externalRaw,
    ): EmailCampaign {
        $recipients = $this->resolveEmails($audiences, $externalRaw);

        $campaign = EmailCampaign::create([
            'created_by' => $createdBy,
            'subject' => $subject,
            'preheader' => $preheader,
            'template_key' => $templateKey,
            'body_html' => CampaignHtml::sanitize($bodyHtml),
            'audiences' => array_values($audiences),
            'external_emails' => $externalRaw ? $this->parseExternal($externalRaw)->pluck('email')->all() : null,
            'status' => 'queued',
            'total_recipients' => $recipients->count(),
        ]);

        // لا مستلمين بعد التنقية: لا دفعة (Bus::batch الفارغ حالة حافّة) — نُنهيها فورًا.
        if ($recipients->isEmpty()) {
            $campaign->update(['status' => 'sent', 'sent_at' => now()]);

            return $campaign;
        }

        $rows = $recipients->values()->map(fn (array $r): EmailRecipient => EmailRecipient::create([
            'email_campaign_id' => $campaign->id,
            'email' => $r['email'],
            'name' => $r['name'],
            'source' => $r['source'],
            'token' => Str::random(48),
            'status' => 'queued',
        ]));

        $perMin = max((int) config('mail_campaigns.per_minute', 30), 1);
        $queue = (string) config('mail_campaigns.queue', 'campaigns');

        $jobs = $rows->values()->map(fn (EmailRecipient $rec, int $i) => (new SendCampaignEmailJob($rec->id))
            ->onQueue($queue)
            ->delay(now()->addSeconds(intdiv($i, $perMin) * 60)))->all();

        $campaignId = $campaign->id;

        // نضبط 'sending' **قبل** الإرسال لا بعده: على طابور sync يُنفّذ dispatch()
        // كل المهام وردّ finally (الذي يضبط 'sent') قبل أن يعود، فلو ضبطناها بعده
        // لأعادها 'sending' وطمس النتيجة. على طابور غير متزامن يبقى الترتيب صحيحًا أيضًا.
        $campaign->update(['status' => 'sending']);

        $batch = Bus::batch($jobs)
            ->name("campaign:{$campaign->id}")
            ->allowFailures()
            ->onQueue($queue)
            ->finally(function (Batch $b) use ($campaignId): void {
                EmailCampaign::whereKey($campaignId)->update([
                    'status' => $b->hasFailures() ? 'failed' : 'sent',
                    'sent_at' => now(),
                ]);
            })
            ->dispatch();

        // نكتب batch_id فقط (لا نمسّ status) كي لا نطمس ما كتبه finally على sync.
        $campaign->update(['batch_id' => $batch->id]);

        return $campaign;
    }

    /**
     * عدد متوقّع بعد التنقية — يظهر في الفورم قبل الإرسال.
     */
    public function estimate(array $audiences, ?string $externalRaw): string
    {
        if (empty($audiences)) {
            return 'اختر جمهورًا لعرض العدد المتوقّع.';
        }

        $count = $this->resolveCount($audiences, $externalRaw);

        return $count > 0
            ? number_format($count) . ' مستلمًا (بعد إزالة المكرّر والمحظور)'
            : 'لا مستلمين مطابقين بعد التنقية.';
    }

    /**
     * عدد المستلمين الفعلي بعد التنقية — يستعمله الفورم للحارس قبل الإرسال.
     */
    public function resolveCount(array $audiences, ?string $externalRaw): int
    {
        return $this->resolveEmails($audiences, $externalRaw)->count();
    }

    /**
     * @return Collection<int, array{email: string, name: ?string, source: string}>
     */
    private function resolveEmails(array $audiences, ?string $externalRaw): Collection
    {
        /** @var Collection<int, array{email: string, name: ?string, source: string}> $out */
        $out = collect();

        // ترتيب الدمج يُقدّم المسجّل على الخارجي، فيفوز مصدره عند unique('email').
        if (in_array(CampaignAudience::ALL_CUSTOMERS, $audiences, true)) {
            $out = $out->merge($this->customers(verifiedOnly: false));
        }

        if (in_array(CampaignAudience::VERIFIED_CUSTOMERS, $audiences, true)) {
            $out = $out->merge($this->customers(verifiedOnly: true));
        }

        if (in_array(CampaignAudience::PANEL_USERS, $audiences, true)) {
            $out = $out->merge(
                User::query()
                    ->where('is_active', true)
                    ->whereNotNull('email')
                    ->get(['name', 'email'])
                    ->map(fn (User $u): array => [
                        'email' => mb_strtolower((string) $u->email),
                        'name' => $u->name,
                        'source' => 'panel_user',
                    ]),
            );
        }

        if (in_array(CampaignAudience::EXTERNAL, $audiences, true) && filled($externalRaw)) {
            $out = $out->merge($this->parseExternal($externalRaw));
        }

        $suppressed = EmailSuppression::query()->pluck('email')
            ->map(static fn ($e): string => mb_strtolower((string) $e))
            ->flip();

        return $out
            ->filter(static fn (array $r): bool => filter_var($r['email'], FILTER_VALIDATE_EMAIL) !== false)
            ->reject(static fn (array $r): bool => $suppressed->has($r['email']))
            ->unique('email')
            ->values();
    }

    /**
     * @return Collection<int, array{email: string, name: ?string, source: string}>
     */
    private function customers(bool $verifiedOnly): Collection
    {
        return Customer::query()
            ->whereNotNull('email')
            ->when($verifiedOnly, fn ($q) => $q->whereNotNull('email_verified_at'))
            ->get(['name', 'email'])
            ->map(fn (Customer $c): array => [
                'email' => mb_strtolower((string) $c->email),
                'name' => $c->name,
                'source' => 'customer',
            ]);
    }

    /**
     * @return Collection<int, array{email: string, name: ?string, source: string}>
     */
    private function parseExternal(string $raw): Collection
    {
        return collect(preg_split('/[\s,;]+/', trim($raw)) ?: [])
            ->filter()
            ->map(static fn ($e): array => [
                'email' => mb_strtolower(trim((string) $e)),
                'name' => null,
                'source' => 'external',
            ]);
    }
}
