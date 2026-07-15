<?php

declare(strict_types=1);

namespace App\Services\Analytics;

use App\Models\Order;
use App\Models\OrderTracking;
use App\Support\Analytics\UserDataHasher;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * إرسال حدث الشراء إلى Meta Conversions API (خادميًا) — M6. المفاتيح من config
 * (env). user_data مجزّأة SHA-256. يعيد true عند النجاح، false إن غابت المفاتيح
 * أو فشل الإرسال (يقرّر الـ Job إعادة المحاولة).
 */
class MetaConversionsApi
{
    public function sendPurchase(Order $order, OrderTracking $tracking): bool
    {
        $pixelId = config('analytics.meta.pixel_id');
        $token = config('analytics.meta.capi_token');

        if (blank($pixelId) || blank($token)) {
            return false;
        }

        $userData = array_filter([
            'em' => UserDataHasher::hashEmail($order->customer_email),
            'ph' => UserDataHasher::hashPhone($order->customer_phone),
            'fbp' => $tracking->fbp,
            'fbc' => $tracking->fbc,
            'client_ip_address' => $order->ip_address,
            'client_user_agent' => $tracking->user_agent,
        ]);

        $payload = [
            'data' => [[
                'event_name' => 'Purchase',
                'event_time' => now()->timestamp,
                'event_id' => $tracking->purchase_event_id,
                'action_source' => 'website',
                'event_source_url' => $tracking->event_source_url,
                'user_data' => $userData,
                'custom_data' => [
                    'currency' => config('analytics.currency', 'EGP'),
                    'value' => (float) $order->grand_total,
                    'order_id' => $order->order_number,
                ],
            ]],
        ];

        if (filled(config('analytics.meta.test_event_code'))) {
            $payload['test_event_code'] = config('analytics.meta.test_event_code');
        }

        // access_token في جسم الطلب لا في الـ URL كي لا يتسرّب إلى السجلّات عند
        // خطأ اتصال. ConnectionException تُبتلَع (رسالتها قد تحمل الـ URL) ويقرّر
        // الـ Job إعادة المحاولة عبر استثنائه المُنقّى.
        $payload['access_token'] = $token;

        try {
            return Http::timeout(10)
                ->post("https://graph.facebook.com/v20.0/{$pixelId}/events", $payload)
                ->successful();
        } catch (ConnectionException) {
            return false;
        }
    }
}
