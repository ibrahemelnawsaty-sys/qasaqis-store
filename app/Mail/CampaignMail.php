<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;
use Symfony\Component\Mime\Email;

/**
 * رسالة حملة واحدة لمستلم واحد. **ليست** ShouldQueue — يملك SendCampaignEmailJob
 * الطابور ويرسلها مزامنةً. body_html يصل معقَّمًا مسبقًا (CampaignHtml)؛ نستبدل هنا
 * {name} فقط ثم نغلّفه في القالب المؤسسي.
 *
 * ملاحظة تصميمية: الخصائص محميّة عمدًا لا عامّة — Mailable يدفع كل خاصية عامة إلى
 * بيانات العرض ويطغى بها على قيم with()، فكانت ستستبدل body_html المعالَج بالخام
 * الحامل {name}. كما أن تسمية خاصية عامة $subject تصطدم بخاصية Mailable غير المنمّطة.
 */
class CampaignMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        protected string $subjectLine,
        protected ?string $preheader,
        protected string $bodyHtml,
        protected ?string $name,
        protected string $unsubscribeToken,
    ) {}

    public function envelope(): Envelope
    {
        $url = URL::to('/email/unsubscribe/' . $this->unsubscribeToken);
        $replyTo = (string) (config('mail_campaigns.reply_to') ?: 'support@' . __('common.domain'));

        return new Envelope(
            subject: $this->subjectLine,
            replyTo: [new Address($replyTo, __('common.brand'))],
            using: [
                // ترويسات إلغاء الاشتراك القياسية — يعتمدها Gmail/Yahoo للنقرة بضغطة
                // واحدة، وتُحسّن سمعة المُرسِل. POST=One-Click يصل مسار store المُعفى من CSRF.
                function (Email $message) use ($url): void {
                    $message->getHeaders()
                        ->addTextHeader('List-Unsubscribe', "<{$url}>")
                        ->addTextHeader('List-Unsubscribe-Post', 'List-Unsubscribe=One-Click');
                },
            ],
        );
    }

    public function content(): Content
    {
        $body = str_replace('{name}', e($this->name ?? 'صديقتنا'), $this->bodyHtml);

        return new Content(
            view: 'emails.campaign',
            with: [
                'bodyHtml' => $body,
                'preheader' => $this->preheader,
                'unsubscribeUrl' => URL::to('/email/unsubscribe/' . $this->unsubscribeToken),
            ],
        );
    }
}
