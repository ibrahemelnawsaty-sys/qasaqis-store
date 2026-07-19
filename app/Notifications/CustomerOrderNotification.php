<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Order;
use App\Support\Order\OrderLinks;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;
use InvalidArgumentException;

/**
 * إشعار العميل عبر البريد لأحداث دورة حياة الطلب (M4): استلام الطلب، تأكيد الدفع،
 * رفض الإثبات، الشحن. ShouldQueue (لا يحجب الطلب/إجراء الأدمن على SMTP). يُرسَل
 * على الطلب (on-demand) لبريد العميل الضيف، وكل النصوص من ملفات الترجمة.
 */
class CustomerOrderNotification extends Notification implements ShouldQueue
{
    use Queueable;
    // يُسلسِل الطلب كمُعرِّف (لا كل الحقول) فلا تبقى PII العميل نصًّا في jobs.
    use SerializesModels;

    public const PLACED = 'placed';

    /**
     * استلام إثبات التحويل (M7 — رحلة العميل، المرحلة 7). أعلى نقطة قلق في المسار:
     * العميلة حوّلت المال ورفعت الصورة، وكان الأدمن وحده يُنبَّه فتبقى هي بلا خبر.
     */
    public const PROOF_RECEIVED = 'proof_received';

    public const APPROVED = 'approved';

    public const REJECTED = 'rejected';

    public const SHIPPED = 'shipped';

    public function __construct(
        public Order $order,
        public string $kind,
        public ?string $reason = null,
    ) {
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $this->order->loadMissing('items');

        return (new MailMessage)
            ->subject(__('mail.'.$this->kind.'.subject', ['number' => $this->order->order_number]))
            ->view('emails.customer-order', $this->viewData());
    }

    /**
     * @return array<string, mixed>
     */
    private function viewData(): array
    {
        $order = $this->order;
        $link = OrderLinks::signedDestinationFor($order, (int) config('orders.email_link_ttl_minutes', 20160));

        return match ($this->kind) {
            self::PLACED => [
                'order' => $order,
                'heading' => __('mail.placed.heading'),
                'intro' => __('mail.placed.intro', ['name' => $order->customer_name]),
                'body' => __('mail.placed.body'),
                'highlight' => null,
                'showSummary' => true,
                'ctaUrl' => $link,
                'ctaLabel' => $order->payment_status === 'pending_review'
                    ? __('mail.placed.cta_pay')
                    : __('mail.placed.cta_view'),
                'note' => $this->placedNote(),
            ],
            self::PROOF_RECEIVED => [
                'order' => $order,
                'heading' => __('mail.proof_received.heading'),
                'intro' => __('mail.proof_received.intro', ['name' => $order->customer_name]),
                'body' => __('mail.proof_received.body'),
                'highlight' => null,
                // بلا ملخّص بنود: الرسالة إيصال طمأنة قصير، والملخّص وصلها مع
                // رسالة استلام الطلب.
                'showSummary' => false,
                'ctaUrl' => $link,
                'ctaLabel' => __('mail.proof_received.cta'),
                'note' => __('mail.proof_received.note'),
            ],
            self::APPROVED => [
                'order' => $order,
                'heading' => __('mail.approved.heading'),
                'intro' => __('mail.approved.intro', ['name' => $order->customer_name]),
                'body' => __('mail.approved.body'),
                'highlight' => null,
                'showSummary' => false,
                'ctaUrl' => $link,
                'ctaLabel' => __('mail.approved.cta'),
                'note' => null,
            ],
            self::REJECTED => [
                'order' => $order,
                'heading' => __('mail.rejected.heading'),
                'intro' => __('mail.rejected.intro', ['name' => $order->customer_name]),
                'body' => __('mail.rejected.body'),
                'highlight' => filled($this->reason) ? __('mail.rejected.reason', ['reason' => $this->reason]) : null,
                'showSummary' => false,
                // رابط مباشر لصفحة رفع الإثبات: بعد الرفض تصبح الحالة failed فلا
                // يوجّهها signedDestinationFor للدفع — لذا نفرض مسار الدفع.
                'ctaUrl' => OrderLinks::signedPaymentFor($order, (int) config('orders.email_link_ttl_minutes', 20160)),
                'ctaLabel' => __('mail.rejected.cta'),
                'note' => __('mail.rejected.note'),
            ],
            self::SHIPPED => [
                'order' => $order,
                'heading' => __('mail.shipped.heading'),
                'intro' => __('mail.shipped.intro', ['name' => $order->customer_name]),
                'body' => __('mail.shipped.body'),
                'highlight' => $this->shippedHighlight(),
                'showSummary' => false,
                'ctaUrl' => $link,
                'ctaLabel' => __('mail.shipped.cta'),
                'note' => null,
            ],
            default => throw new InvalidArgumentException("Unknown notification kind: {$this->kind}"),
        };
    }

    private function placedNote(): string
    {
        return match ($this->order->payment_method) {
            'instapay', 'vodafone_cash', 'bank_transfer' => __('mail.placed.note_manual'),
            'cod' => __('mail.placed.note_cod'),
            default => __('mail.placed.note_generic'),
        };
    }

    private function shippedHighlight(): ?string
    {
        if (blank($this->order->tracking_number)) {
            return null;
        }

        return __('mail.shipped.tracking', [
            'company' => $this->order->shipping_company ?: __('mail.shipped.company_fallback'),
            'number' => $this->order->tracking_number,
        ]);
    }
}
