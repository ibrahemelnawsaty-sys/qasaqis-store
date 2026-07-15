<?php

declare(strict_types=1);

namespace App\Services\Analytics;

use App\Models\Order;
use App\Models\OrderTracking;
use Illuminate\Support\Facades\Http;

/**
 * إرسال حدث الشراء إلى GA4 عبر Measurement Protocol (خادميًا) — M6. يتطلب
 * ga_client_id من كوكي المتصفح الملتقط وقت الدفع؛ إن غاب (رفض الكوكيز) يسقط
 * حدث GA4 (Meta CAPI يبقى يعمل). المفاتيح من config (env).
 */
class Ga4MeasurementProtocol
{
    public function sendPurchase(Order $order, OrderTracking $tracking): bool
    {
        $measurementId = config('analytics.ga4.measurement_id');
        $apiSecret = config('analytics.ga4.api_secret');

        if (blank($measurementId) || blank($apiSecret) || blank($tracking->ga_client_id)) {
            return false;
        }

        $payload = [
            'client_id' => $tracking->ga_client_id,
            'events' => [[
                'name' => 'purchase',
                'params' => array_filter([
                    'transaction_id' => $order->order_number,
                    'currency' => config('analytics.currency', 'EGP'),
                    'value' => (float) $order->grand_total,
                    'session_id' => $tracking->ga_session_id,
                ], static fn ($v): bool => $v !== null && $v !== ''),
            ]],
        ];

        return Http::timeout(10)
            ->post("https://www.google-analytics.com/mp/collect?measurement_id={$measurementId}&api_secret={$apiSecret}", $payload)
            ->successful();
    }
}
