<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Models\Order;
use App\Services\Payment\Contracts\PaymentGateway;
use App\Support\Payment\PaymentInitiation;
use Illuminate\Support\Facades\URL;

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

        // افتراضيًّا (embed): نرسل العميلة إلى صفحة دفع **داخل المتجر** تعرض نموذج كاشير
        // مدمجًا (iframe) بتصميمنا فلا تغادر الموقع. عند تعطيل التضمين: توجيه مباشر
        // للصفحة المستضافة الخارجية (رابط مبنيّ من نفس المعطيات).
        $redirectUrl = $this->embedEnabled()
            ? URL::signedRoute('orders.pay', ['order' => $order->id])
            : rtrim((string) ($this->config['hpp_url'] ?? 'https://checkout.kashier.io'), '/')
                .'/?'.http_build_query($this->hostedPaymentParams($order));

        return new PaymentInitiation(
            success: true,
            redirectUrl: $redirectUrl,
            reference: (string) $order->order_number,
            // لا نخزّن السرّ ولا الهاش — فقط ما يفيد التتبّع.
            raw: ['gateway' => 'kashier', 'mode' => $this->mode()],
        );
    }

    /**
     * معطيات الدفع لصفحة كاشير — تُرسَل كسمات data-* لسكربت التضمين، أو معاملاتِ
     * رابطِ الصفحة المستضافة. الهاش محسوب على نفس القيم الحرفية المُرسَلة (المبلغ
     * بخانتين) بمفتاح الدفع. أسماء المفاتيح مطابقة حرفيًّا لعرض كاشير الرسمي.
     *
     * @return array<string, string>
     */
    public function hostedPaymentParams(Order $order): array
    {
        $merchantId = (string) $this->config['merchant_id'];
        $orderId = (string) $order->order_number;
        $amount = number_format((float) $order->grand_total, 2, '.', '');

        $path = "/?payment={$merchantId}.{$orderId}.{$amount}.".self::CURRENCY;

        return [
            'merchantId' => $merchantId,
            'orderId' => $orderId,
            'amount' => $amount,
            'currency' => self::CURRENCY,
            'hash' => $this->sign($path),
            'mode' => $this->mode(),
            'merchantRedirect' => route('payments.kashier.callback'),
            'serverWebhook' => route('payments.kashier.webhook'),
            'allowedMethods' => (string) ($this->config['allowed_methods'] ?? 'card,wallet'),
            'display' => 'ar',
        ];
    }

    /** مصدر سكربت التضمين kashier-checkout.js (على أصل الصفحة المستضافة). */
    public function scriptUrl(): string
    {
        return rtrim((string) ($this->config['hpp_url'] ?? 'https://checkout.kashier.io'), '/').'/kashier-checkout.js';
    }

    /** هل نعرض نموذج الدفع مدمجًا داخل المتجر (افتراضي) أم نوجّه خارجيًّا؟ */
    private function embedEnabled(): bool
    {
        return (bool) ($this->config['embed'] ?? true);
    }

    /**
     * التحقّق من توقيع ردّ **إعادة التوجيه** (عودة المتصفّح، merchantRedirect).
     *
     * يُقارَن التوقيع المُستلَم بعدّة صيغ محتملة (انظر redirectSignatureCandidates)
     * لأن صيغة كاشير الفعلية قد تختلف عن العرض الرسمي؛ كلّها HMAC بمفتاح الدفع
     * ومقارنة زمنيًّا-ثابتة (hash_equals). لا تُضعف تجربةُ الصيغ الأمانَ (لا تزوير
     * دون معرفة المفتاح).
     *
     * @param  array<string, mixed>  $payload
     */
    public function verify(array $payload): bool
    {
        $received = (string) ($payload['signature'] ?? '');

        if ($received === '' || ! filled($this->config['api_key'] ?? null)) {
            return false;
        }

        foreach ($this->redirectSignatureCandidates($payload) as $candidate) {
            if (hash_equals($this->sign($candidate), $received)) {
                return true;
            }
        }

        return false;
    }

    /**
     * مرشّحات نصّ توقيع ردّ إعادة التوجيه — تغطّي اختلاف صيغة كاشير الفعلية عن العرض
     * الرسمي: (1) كلّ المعطيات عدا signature/mode بترتيب ورودها (hpp/iframe الرسمي)؛
     * (2) حقول signatureKeys فقط بترتيب ورودها؛ (3) حقول signatureKeys مرتّبة أبجديًّا
     * (صيغة الـwebhook). جميعها تتطلّب api_key، فتجربة أكثر من صيغة لا تُضعف الأمان.
     *
     * @param  array<string, mixed>  $payload
     * @return array<int, string>
     */
    private function redirectSignatureCandidates(array $payload): array
    {
        $candidates = [];

        // (1) كل المعطيات عدا signature و mode، بترتيب ورودها.
        $all = [];
        foreach ($payload as $key => $value) {
            if ($key === 'signature' || $key === 'mode' || ! is_scalar($value)) {
                continue;
            }
            $all[] = $key.'='.$value;
        }
        if ($all !== []) {
            $candidates[] = implode('&', $all);
        }

        // (2)+(3) مجموعة signatureKeys فقط — بترتيب ورودها، ثم مرتّبة أبجديًّا.
        // fail-closed (كالـwebhook): لا نقبل مجموعةً جزئية إلّا إذا كانت تُوقّع
        // merchantOrderId و amount معًا، وإلّا أمكن إعادة توجيه توقيع صحيح لطلب آخر
        // بنفس المبلغ. حين لا تربطهما، نسقط لمرشّح (1) كامل-المعطيات الذي يوقّع كلّ شيء.
        if (isset($payload['signatureKeys'])) {
            $keys = is_array($payload['signatureKeys'])
                ? $payload['signatureKeys']
                : explode(',', (string) $payload['signatureKeys']);
            $keys = array_values(array_filter($keys, 'is_string'));

            $bindsOrder = in_array('merchantOrderId', $keys, true) && in_array('amount', $keys, true);

            if ($bindsOrder) {
                $build = static function (array $ks) use ($payload): ?string {
                    $parts = [];
                    foreach ($ks as $k) {
                        if (array_key_exists($k, $payload) && is_scalar($payload[$k])) {
                            $parts[] = $k.'='.$payload[$k];
                        }
                    }

                    return $parts === [] ? null : implode('&', $parts);
                };

                $listed = $build($keys);
                if ($listed !== null) {
                    $candidates[] = $listed;
                }

                $sorted = $keys;
                sort($sorted);
                $alpha = $build($sorted);
                if ($alpha !== null && $alpha !== $listed) {
                    $candidates[] = $alpha;
                }
            }
        }

        return $candidates;
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
