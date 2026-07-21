<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Notifications\OrderNotifier;
use App\Services\Payment\KashierGateway;
use App\Services\Payment\PaymentGatewayFactory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;

/**
 * ردّ بوابة الدفع الأونلاين (Kashier). مسربان:
 *  - callback: عودة المتصفّح (merchantRedirect) لعرض النتيجة للعميلة.
 *  - webhook: إخطار خادم-لخادم (serverWebhook) — المصدر الموثوق للتأكيد النهائي.
 *
 * الأمان (بند 4.x): لا نثق بأي ردّ قبل التحقّق من توقيعه (HMAC بمفتاح الدفع). حتى
 * بعد التحقّق نتأكّد أنّ المبلغ يطابق إجمالي الطلب (دفاع في العمق ضدّ التلاعب بالقيمة)
 * قبل اعتماد الدفع. التأكيد مُتماثِل (idempotent): أوّل مصدرٍ صحيحٍ يعتمد الدفع،
 * والبقية لا تُكرّره. لا نعتمد الدفع إلا لطلبات online_gateway (بند 1.3/1.5).
 */
class PaymentCallbackController extends Controller
{
    public function __construct(
        private readonly PaymentGatewayFactory $gateways,
        private readonly OrderNotifier $notifier,
    ) {
    }

    /** عودة المتصفّح من صفحة كاشير المستضافة (GET، موقَّعة من كاشير). */
    public function kashierCallback(Request $request): RedirectResponse
    {
        $params = $request->query();
        $gateway = $this->gateways->make('kashier');

        if (! $gateway->verify($params)) {
            // تشخيص: بنية الردّ وحقوله الآمنة فقط لمطابقة صيغة التوقيع عند فشل كل
            // المرشّحات. نستبعد التوقيع و cardDataToken (رمز بطاقة قابل لإعادة الاستخدام)
            // فلا أسرار في اللوقات (الدستور). تُزال لاحقًا بعد ثبات التكامل.
            Log::warning('kashier.callback.bad_signature', [
                'ip' => $request->ip(),
                'method' => $request->method(),
                'keys' => array_keys($params),
                'signatureKeys' => is_scalar($params['signatureKeys'] ?? null) ? $params['signatureKeys'] : null,
                'safe' => \Illuminate\Support\Arr::only($params, [
                    'paymentStatus', 'status', 'merchantOrderId', 'orderId',
                    'amount', 'currency', 'transactionId', 'mode',
                ]),
            ]);

            // صفحة تتبّع الطلب تعرض رسالة error وتتيح للعميلة العثور على طلبها.
            return redirect()->route('orders.track.show')->with('error', __('payment.gateway.verify_failed'));
        }

        $order = $this->findOrder((string) $request->query('merchantOrderId'));

        if ($order === null) {
            return redirect()->route('orders.track.show')->with('error', __('payment.gateway.order_not_found'));
        }

        if ($this->isSuccess($params) && $this->amountMatches($order, $params)) {
            $this->markPaid($order, $params, 'callback');

            return redirect()
                ->to(URL::signedRoute('orders.thankyou', ['order' => $order->id]))
                ->with('status', __('payment.gateway.success'))
                // تُفرِّغ سلة localStorage في صفحة الشكر بعد اكتمال الدفع فعلًا (شارة
                // السلة في الترويسة كانت تبقى تعرض الكتب المشتراة طوال رحلة الدفع).
                ->with('cart_placed', true);
        }

        // فشل/إلغاء الدفع: الطلب يبقى pending (يلغيه orders:cancel-expired لاحقًا)،
        // ونوجّه العميلة لصفحة الشكر مع رسالة (المفتاح warning هو ما تعرضه الصفحة).
        return redirect()
            ->to(URL::signedRoute('orders.thankyou', ['order' => $order->id]))
            ->with('warning', __('payment.gateway.failed'));
    }

    /** إخطار كاشير خادم-لخادم (POST JSON). يعيد 200 دائمًا بعد المعالجة الآمنة. */
    public function kashierWebhook(Request $request): Response
    {
        // بنية كاشير: { event, data: { ...الحقول..., signatureKeys, signature } }.
        // التوقيع قد يأتي داخل data أو في ترويسة؛ نجرّب كليهما.
        $data = (array) $request->input('data', $request->all());
        $signature = (string) ($data['signature'] ?? $request->header('x-kashier-signature', ''));

        $gateway = $this->gateways->make('kashier');

        if (! $gateway instanceof KashierGateway || ! $gateway->verifyWebhook($data, $signature)) {
            Log::warning('kashier.webhook.bad_signature', ['ip' => $request->ip()]);

            return response('invalid signature', 400);
        }

        $order = $this->findOrder((string) ($data['merchantOrderId'] ?? ''));

        if ($order !== null && $this->isSuccess($data) && $this->amountMatches($order, $data)) {
            $this->markPaid($order, $data, 'webhook');
        }

        // 200 دائمًا بعد تحقّق التوقيع كي لا يعيد كاشير الإرسال بلا داعٍ؛ الحالات
        // غير المعتمَدة مُسجَّلة، والتأكيد المتماثل يمنع الازدواج.
        return response('ok', 200);
    }

    private function findOrder(string $orderNumber): ?Order
    {
        if ($orderNumber === '') {
            return null;
        }

        return Order::where('order_number', $orderNumber)->first();
    }

    /** كاشير يرسل paymentStatus (إعادة التوجيه) أو status (webhook) بقيمة SUCCESS. */
    private function isSuccess(array $payload): bool
    {
        $status = strtoupper((string) ($payload['paymentStatus'] ?? $payload['status'] ?? ''));

        return $status === 'SUCCESS';
    }

    /** دفاع في العمق: المبلغ **والعملة** يطابقان إجمالي الطلب (مقارنة قروش صحيحة). */
    private function amountMatches(Order $order, array $payload): bool
    {
        if (! isset($payload['amount'])) {
            return false;
        }

        // العملة يجب أن تكون EGP (نطلق الدفع بـEGP دومًا). أيّ عملة أخرى مرفوضة.
        $currency = strtoupper((string) ($payload['currency'] ?? 'EGP'));

        $paid = (int) round(((float) $payload['amount']) * 100);
        $due = (int) round(((float) $order->grand_total) * 100);

        if ($paid !== $due || $currency !== 'EGP') {
            Log::warning('kashier.amount_mismatch', [
                'order_id' => $order->id,
                'paid' => $payload['amount'],
                'currency' => $currency,
                'due' => $order->grand_total,
            ]);

            return false;
        }

        return true;
    }

    /**
     * اعتماد الدفع بشكل متماثِل: يطابق انتقال قبول الإثبات اليدوي (payment=completed،
     * order paid+processing). يفعّل مراقب الطلب حدث الشراء وإشعار العميلة.
     *
     * @param  array<string, mixed>  $payload
     */
    private function markPaid(Order $order, array $payload, string $source): void
    {
        // حرّاس مبكّرة قبل المعاملة: البوابة تعتمد فقط طلب أونلاين ما زال pending.
        if ($order->payment_method !== 'online_gateway' || $order->payment_status === 'paid') {
            return;
        }

        // حالة نهائية (ملغاة/مرفوضة/مستردّة أو تجاوزت pending): لا نُحييها برد متأخّر —
        // المخزون أُعيد للرفّ وقد تُحتسب إيرادًا وهميًّا (يطابق حارس ViewOrder للحالات
        // النهائية). المال قد يكون ورد فعلًا فنُسجّل لمراجعة يدوية (استرداد).
        if ($order->status !== 'pending') {
            Log::warning('kashier.paid_on_non_pending_order', [
                'order_id' => $order->id,
                'status' => $order->status,
                'source' => $source,
            ]);

            return;
        }

        $changed = DB::transaction(function () use ($order, $payload): bool {
            // قفل الصفّ ثم إعادة الفحص داخل المعاملة (تزامن callback + webhook + إلغاء).
            $fresh = Order::whereKey($order->getKey())->lockForUpdate()->first();

            if ($fresh === null
                || $fresh->payment_method !== 'online_gateway'
                || $fresh->payment_status === 'paid'
                || $fresh->status !== 'pending') {
                return false;
            }

            $fresh->payments()->latest('id')->first()?->forceFill([
                'status' => 'completed',
                'paid_at' => now(),
                'transaction_ref' => (string) ($payload['transactionId'] ?? $payload['kashierOrderId'] ?? $payload['orderId'] ?? ''),
                'gateway_response' => $payload,
            ])->save();

            $fresh->forceFill([
                'payment_status' => 'paid',
                'status' => 'processing',
            ])->save();

            return true;
        });

        if (! $changed) {
            return;
        }

        // بعد الـ commit: إشعار العميلة (ShouldQueue فلا يحجب). حدث الشراء يُطلقه
        // مراقب الطلب عند انتقال payment_status إلى paid.
        $this->notifier->paymentApproved($order->refresh());

        Log::info('kashier.payment.paid', [
            'order_id' => $order->id,
            'source' => $source,
            'transaction_ref' => $payload['transactionId'] ?? null,
        ]);
    }
}
