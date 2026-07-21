<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Models\Order;
use App\Services\Payment\Contracts\PaymentGateway;
use App\Support\Payment\PaymentInitiation;

/**
 * بوابة Kashier — الصفحة المستضافة (Hosted Payment Page) بتدفّق إعادة توجيه.
 *
 * المفاتيح تُقرأ من config('payment.gateways.kashier') ولا يُكتب أي سرّ في الكود
 * (بند 4.3). التوقيع (هاش الطلب والتحقّق من ردّ الدفع) يستعمل **مفتاح الدفع**
 * (Payment API Key = api_key)، لا secret_key — هذا ما يوثّقه كاشير رسميًّا وما
 * تصرّح به لوحته: «Payment API Key… لتوليد هاش الطلب». secret_key مخصّص لنداءات
 * REST على api.kashier.io (استرداد/استعلام) ولا يلزم لتدفّق الدفع هذا.
 *
 * الأمانة (بند 1.3/1.5): initiate يُرجِع فشلًا صريحًا حين تنقص المفاتيح، ولا يزعم
 * نجاحًا؛ verify يرفض أي ردّ توقيعه غير صحيح (المصدر الوحيد الموثوق).
 */
class KashierGateway implements PaymentGateway
{
    /** عملة التحصيل — مصر (بند 1.1: لا تُخترع قيم). */
    private const CURRENCY = 'EGP';

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(private readonly array $config)
    {
    }

    public function key(): string
    {
        return 'kashier';
    }

    public function isConfigured(): bool
    {
        return filled($this->config['api_key'] ?? null)
            && filled($this->config['merchant_id'] ?? null);
    }

    public function initiate(Order $order): PaymentInitiation
    {
        if (! $this->isConfigured()) {
            return PaymentInitiation::failed('payment.gateway.unavailable');
        }

        $merchantId = (string) $this->config['merchant_id'];
        $orderId = (string) $order->order_number;
        // المبلغ نصًّا بخانتين — يجب أن يطابق حرفيًّا قيمة الهاش وقيمة الرابط.
        $amount = number_format((float) $order->grand_total, 2, '.', '');
        $mode = $this->mode();

        // هاش الطلب: HMAC-SHA256 على المسار، بمفتاح الدفع (بالضبط كعرض كاشير الرسمي:
        // hash_hmac('sha256', "/?payment=mid.orderId.amount.currency", apiKey)).
        $path = "/?payment={$merchantId}.{$orderId}.{$amount}.".self::CURRENCY;
        $hash = $this->sign($path);

        $params = [
            'merchantId' => $merchantId,
            'orderId' => $orderId,
            'amount' => $amount,
            'currency' => self::CURRENCY,
            'hash' => $hash,
            'mode' => $mode,
            'merchantRedirect' => route('payments.kashier.callback'),
            'serverWebhook' => route('payments.kashier.webhook'),
            'allowedMethods' => (string) ($this->config['allowed_methods'] ?? 'card,wallet'),
            'display' => 'ar',
        ];

        $base = rtrim((string) ($this->config['hpp_url'] ?? 'https://checkout.kashier.io'), '/');
        $redirectUrl = $base.'/?'.http_build_query($params);

        return new PaymentInitiation(
            success: true,
            redirectUrl: $redirectUrl,
            reference: $orderId,
            // لا نخزّن السرّ ولا الهاش نفسه — فقط ما يفيد التتبّع.
            raw: ['gateway' => 'kashier', 'mode' => $mode],
        );
    }

    /**
     * التحقّق من توقيع ردّ **إعادة التوجيه** (عودة المتصفّح، merchantRedirect).
     *
     * يُبنى نصّ الاستعلام من كل المعطيات عدا signature و mode بترتيب ورودها، ثم
     * HMAC بمفتاح الدفع، ومقارنة زمنيًّا-ثابتة (hash_equals) — مطابقٌ حرفيًّا لعرض
     * كاشير الرسمي (hppCallback.php). ملاحظة: signatureKeys نفسها ضمن الحقول
     * الموقَّعة هنا (كالعرض)، فلا نميّز عليها.
     *
     * @param  array<string, mixed>  $payload
     */
    public function verify(array $payload): bool
    {
        $received = (string) ($payload['signature'] ?? '');

        if ($received === '' || ! filled($this->config['api_key'] ?? null)) {
            return false;
        }

        $parts = [];
        foreach ($payload as $key => $value) {
            if ($key === 'signature' || $key === 'mode' || ! is_scalar($value)) {
                continue;
            }
            $parts[] = $key.'='.$value;
        }

        return hash_equals($this->sign(implode('&', $parts)), $received);
    }

    /**
     * التحقّق من توقيع الـ **webhook** (خادم-لخادم). كاشير يوثّق: رتّب مفاتيح
     * signatureKeys أبجديًّا، ابنِ نصّ الاستعلام من قيمها في data، ثم HMAC بمفتاح
     * الدفع. صارم (fail-closed): يرفض عند أي عدم تطابق، فلا يعتمد ردًّا مزوّرًا أبدًا.
     *
     * @param  array<string, mixed>  $data       كائن data من جسم الـ webhook
     * @param  string                $signature  التوقيع المُستلَم (data أو الترويسة)
     */
    public function verifyWebhook(array $data, string $signature): bool
    {
        if ($signature === '' || ! filled($this->config['api_key'] ?? null)) {
            return false;
        }

        $keys = $data['signatureKeys'] ?? [];
        $keys = is_array($keys) ? $keys : explode(',', (string) $keys);

        // fail-closed: حقول ربط الطلب/المبلغ يجب أن تكون ضمن المُوقَّع، وإلّا أمكن
        // إعادة إرسال webhook صحيح بعد تبديل merchantOrderId إلى طلب آخر بنفس التوقيع.
        foreach (['merchantOrderId', 'amount'] as $required) {
            if (! in_array($required, $keys, true)) {
                return false;
            }
        }

        sort($keys);

        $parts = [];
        foreach ($keys as $key) {
            if (is_string($key) && array_key_exists($key, $data) && is_scalar($data[$key])) {
                $parts[] = $key.'='.$data[$key];
            }
        }

        if ($parts === []) {
            return false;
        }

        return hash_equals($this->sign(implode('&', $parts)), $signature);
    }

    /** HMAC-SHA256 بمفتاح الدفع (api_key). */
    private function sign(string $data): string
    {
        return hash_hmac('sha256', $data, (string) $this->config['api_key']);
    }

    /** وضع البوابة: test افتراضيًّا (لا نفترض live دون ضبط صريح). */
    private function mode(): string
    {
        $mode = (string) ($this->config['mode'] ?? 'test');

        return in_array($mode, ['test', 'live'], true) ? $mode : 'test';
    }
}
