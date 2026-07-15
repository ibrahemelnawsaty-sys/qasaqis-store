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
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use RuntimeException;

/**
 * إرسال حدث الشراء الخادمي إلى Meta CAPI + GA4 (M6). يُطلَق من OrderObserver عند
 * تأكيد الدفع (payment_status→paid). يحمل orderId فقط (لا PII في الطابور).
 *
 * لكل قناة ختمها المستقل (meta_sent_at / ga4_sent_at): نجاح إحداهما لا يُفقد
 * الأخرى — تُعاد الناقصة وحدها (event_id/transaction_id الثابتان يمنعان الازدواج).
 * Meta CAPI (يحمل PII مجزّأة) يُرسَل فقط بموافقة الزائر الإعلانية (ads_consent).
 * قفل WithoutOverlapping يمنع الإرسال المزدوج من عاملين متزامنين.
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

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [(new WithoutOverlapping((string) $this->orderId))->dontRelease()->expireAfter(180)];
    }

    public function handle(MetaConversionsApi $meta, Ga4MeasurementProtocol $ga4): void
    {
        $order = Order::query()->with('tracking')->find($this->orderId);

        if ($order === null || $order->payment_status !== 'paid' || $order->tracking === null) {
            return;
        }

        $tracking = $order->tracking;

        $metaConfigured = filled(config('analytics.meta.pixel_id')) && filled(config('analytics.meta.capi_token'));
        $ga4Configured = filled(config('analytics.ga4.measurement_id'))
            && filled(config('analytics.ga4.api_secret'))
            && filled($tracking->ga_client_id);

        // Meta CAPI يرسل بيانات المستخدم المجزّأة — فقط بموافقة إعلانية صريحة.
        $metaAllowed = $metaConfigured && $tracking->ads_consent === true;

        $pending = false;

        if ($metaAllowed && $tracking->meta_sent_at === null) {
            if ($meta->sendPurchase($order, $tracking)) {
                $tracking->meta_sent_at = now();
            } else {
                $pending = true;
            }
        }

        if ($ga4Configured && $tracking->ga4_sent_at === null) {
            if ($ga4->sendPurchase($order, $tracking)) {
                $tracking->ga4_sent_at = now();
            } else {
                $pending = true;
            }
        }

        if ($tracking->isDirty()) {
            $tracking->save();
        }

        // قناة مُهيّأة لم تنجح بعد → أعِد المحاولة (تُعاد الناقصة وحدها).
        if ($pending) {
            throw new RuntimeException("Purchase server event pending for order {$this->orderId}");
        }
    }
}
