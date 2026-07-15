<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Order;
use App\Services\Analytics\Ga4MeasurementProtocol;
use App\Services\Analytics\MetaConversionsApi;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RuntimeException;

/**
 * إرسال حدث الشراء الخادمي إلى Meta CAPI + GA4 (M6). يُطلَق من OrderObserver عند
 * تأكيد الدفع (payment_status→paid). يحمل orderId (لا نموذجًا) فلا PII في الطابور.
 * idempotent: لا يُرسِل إن سبق الإرسال (purchase_sent_at)، ويُعيد المحاولة عند فشل
 * الشبكة (tries=5) حتى ينجح أحد القناتين على الأقل.
 */
class SendPurchaseServerEvent implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 5;

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [30, 120, 300, 600];
    }

    public function __construct(public int $orderId)
    {
    }

    public function handle(MetaConversionsApi $meta, Ga4MeasurementProtocol $ga4): void
    {
        $order = Order::query()->with('tracking')->find($this->orderId);

        // تحقّق مجدّد: الطلب مدفوع ولم يُرسَل الحدث بعد (idempotency).
        if ($order === null
            || $order->payment_status !== 'paid'
            || $order->tracking === null
            || $order->tracking->purchase_sent_at !== null) {
            return;
        }

        $tracking = $order->tracking;

        $sent = $meta->sendPurchase($order, $tracking);
        // GA4 كذلك؛ | لا || كي لا يُقصَر الثاني عند نجاح الأول.
        $sent = $ga4->sendPurchase($order, $tracking) || $sent;

        if ($sent) {
            $tracking->forceFill(['purchase_sent_at' => now()])->save();

            return;
        }

        // فشل القناتان (شبكة/مفاتيح غائبة) — ارمِ لإعادة المحاولة. لو كانت المفاتيح
        // غائبة كليًا فالبوابة analytics.enabled يفترض ألا تصل هنا؛ الفشل شبكي غالبًا.
        throw new RuntimeException("Purchase server event failed for order {$this->orderId}");
    }
}
