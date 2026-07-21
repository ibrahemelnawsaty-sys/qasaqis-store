<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Actions\Checkout\PlaceOrderAction;
use App\Actions\Customer\RememberCheckoutAddress;
use App\Exceptions\CheckoutException;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Customer\PostPurchaseAccountController;
use App\Http\Controllers\Storefront\Concerns\InteractsWithSessionCart;
use App\Http\Requests\CheckoutRequest;
use App\Models\Country;
use App\Models\Order;
use App\Services\Cart\CartService;
use App\Services\Payment\PaymentMethodResolver;
use App\Support\Checkout\CheckoutSession;
use App\Support\Payment\PaymentInitiation;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

/**
 * Shows the checkout form and places the order. The controller stays thin (2.2):
 * validation is in CheckoutRequest, the write is in PlaceOrderAction.
 */
class CheckoutController extends Controller
{
    use InteractsWithSessionCart;

    public function show(
        Request $request,
        CartService $cartService,
        PaymentMethodResolver $resolver,
    ): View|RedirectResponse {
        $cart = $this->buildSessionCart($request, $cartService);

        if ($cart->isEmpty()) {
            return redirect()
                ->route('cart.show')
                ->with('error', __('payment.errors.empty_cart'));
        }

        // بداية محاولة دفع جديدة بمفتاح منع تكرار جديد (M7). كل عرض للصفحة محاولة
        // مستقلة؛ وكل إرسال من العرض نفسه (نقرة مزدوجة أو F5) هو المحاولة ذاتها.
        CheckoutSession::beginAttempt($request->session());

        return view('checkout.show', [
            'cart' => $cart,
            'methods' => $resolver->available(),
            'onlineEnabled' => $resolver->isOnlineEnabled(),
            'onlineDisabledMessageKey' => $resolver->onlineDisabledMessageKey(),
            'governorates' => config('egypt.governorates'),
            'countries' => Country::shippable()->orderBy('sort_order')->get(['iso_code', 'name_ar']),
            // ملء مسبق للعميلة المسجّلة من عنوانها الافتراضيّ (أو آخر عنوان)، كي لا
            // تُعيد إدخال كل شيء. القالب يفضّل old() عند ارتداد خطأ تحقّق.
            'prefill' => $this->prefill(),
            // دفتر عناوينها المحفوظة (لمحدِّد «اختر عنوانًا/أضف جديدًا»). فارغ للزائرة.
            'addresses' => auth('customer')->user()?->addresses ?? collect(),
        ]);
    }

    /**
     * بيانات الملء المسبق للعميلة المسجّلة فقط: عنوانها الافتراضيّ من الدفتر، وإلا
     * آخر عنوان محفوظ على الحساب. الجوال بصيغة محلية. للزائرة مصفوفة فارغة.
     *
     * @return array<string, string>
     */
    private function prefill(): array
    {
        $customer = auth('customer')->user();

        if ($customer === null) {
            return [];
        }

        $default = $customer->addresses()->where('is_default', true)->first();

        return [
            'name' => (string) ($default->name ?? $customer->name),
            'phone' => (string) ($default->phone ?? (filled($customer->phone_normalized) ? '0'.$customer->phone_normalized : '')),
            'email' => (string) $customer->email,
            'governorate' => (string) ($default->governorate ?? $customer->last_governorate),
            'city' => (string) ($default->city ?? $customer->last_city),
            'address' => (string) ($default->address_line ?? $customer->last_address_line),
            'country_code' => (string) ($default->country_code ?? ($customer->last_country_code ?: 'EG')),
        ];
    }

    /**
     * يحفظ آخر عنوان استخدمته العميلة المسجّلة على حسابها (للملء التلقائي لاحقًا).
     *
     * **حفظ العنوان رفاهيّة best-effort ويجب ألّا يكسر استجابة طلبٍ اكتمل**: الطلب
     * أُنشئ والسلة أُفرِغت قبل هذا السطر، فأيّ خطأ DB (توقّف مؤقّت/تزاحم/طول زائد) هنا
     * يُسجَّل ويُبتلَع كي تصل العميلة لبوابة الدفع لا لخطأ 500. + قصّ دفاعيّ لأطوال
     * الأعمدة (تحقّق governorate الدولي يسمح بـ100 بينما last_governorate عموده 50).
     */
    private function rememberAddressFor(CheckoutRequest $request): void
    {
        $customer = auth('customer')->user();

        if ($customer === null) {
            return;
        }

        try {
            // دفتر العناوين المُسمّى (M12): يُحفظ العنوان المُستخدَم ويصير الافتراضيّ.
            app(RememberCheckoutAddress::class)->handle($customer, [
                'name' => $request->input('name'),
                'phone' => $request->input('phone'),
                'phone_alt' => $request->input('phone_alt'),
                'country_code' => $request->input('country_code'),
                'governorate' => $request->input('governorate'),
                'state_province' => $request->input('state_province'),
                'city' => $request->input('city'),
                'address_line' => $request->input('address'),
                'address_notes' => $request->input('address_notes'),
            ]);

            // last_* يبقى كـ fallback للملء السريع قبل ظهور الدفتر.
            $customer->update([
                'last_governorate' => $this->clip($request->input('governorate'), 50),
                'last_city' => $this->clip($request->input('city'), 80),
                'last_address_line' => $this->clip($request->input('address'), 300),
                'last_country_code' => $request->input('country_code') ?: 'EG',
            ]);
        } catch (\Throwable $e) {
            report($e); // لا يُسقط استجابة طلبٍ اكتمل
        }
    }

    /** قصّ آمن على طول العمود (متعدد البايت)، مع تمرير null كما هو. */
    private function clip(?string $value, int $max): ?string
    {
        return $value === null ? null : mb_substr($value, 0, $max);
    }

    public function place(CheckoutRequest $request, PlaceOrderAction $action): RedirectResponse
    {
        try {
            $result = $action->execute($request->toData());
        } catch (CheckoutException $e) {
            return redirect()
                ->route('checkout.show')
                ->withInput()
                ->with('error', $e->localizedMessage());
        }

        $this->forgetSessionCart($request);

        // حفظ آخر عنوان للعميلة المسجّلة كي يُملأ تلقائيًّا في الطلب القادم (لا تُعيد
        // إدخاله). لا يمسّ الطلب نفسه، وللزائرة لا شيء.
        $this->rememberAddressFor($request);

        // المفتاح لا يُنسى هنا عمدًا — انظر CheckoutSession: نسيانه يفتح ثغرة
        // إعادة إرسال النموذج بعد اكتمال الطلب. يُستبدل عند العرض التالي للصفحة.

        // ربط قدرة «إنشاء حساب بعد الشراء» بجلسة المشتري نفسه (M10): نافذة الشكر
        // لا تظهر ولا تُقبل إلا لهذه الجلسة، فرابط شكر مُسرَّب في متصفح آخر لا يُنشئ
        // حسابًا على هوية العميلة (دفاع في العمق مع التوقيع).
        $request->session()->put(PostPurchaseAccountController::SESSION_KEY, $result->order->id);

        return $this->redirectAfterPlacement($request, $result->order, $result->initiation);
    }

    /**
     * Route the customer to the next step based on the payment path.
     */
    private function redirectAfterPlacement(
        Request $request,
        Order $order,
        ?PaymentInitiation $initiation,
    ): RedirectResponse {
        // Online gateway path.
        if ($initiation !== null) {
            if ($initiation->success && $initiation->redirectUrl !== null) {
                // بلا إشارة تفريغ سلة: الوجهة موقع خارجي، فالإشارة (flash لطلب
                // واحد) تُستهلَك في أول صفحة تعود إليها العميلة أيًّا كانت.
                return redirect()->away($initiation->redirectUrl);
            }

            // Gateway could not start (e.g. not configured) — order stays pending.
            // ولا نُفرِّغ سلتها هنا تحديدًا: خرجت بطلب غير مدفوع ولا سبيل لدفعه،
            // فسلة المتصفح هي كل ما تملكه لإعادة المحاولة بطريقة دفع أخرى.
            return redirect()
                ->to(URL::signedRoute('orders.thankyou', ['order' => $order->id]))
                ->with('warning', __($initiation->messageKey ?? 'payment.gateway.unavailable'));
        }

        // إشارة لمرة واحدة تُفرِّغ سلة localStorage في الوجهة (M7 — المرحلة 6):
        // سلة الجلسة تُمسح في place()، لكن السلة التي تراها العميلة تعيش في
        // المتصفح فكانت الشارة تُظهر ما اشترته للتوّ. تُضبط هنا فقط حيث الطلب
        // مكتمل ووجهته صفحة داخلية مباشرة.
        $request->session()->flash('cart_placed', true);

        // Manual transfer path -> instructions + proof upload.
        if ($order->payment_status === 'pending_review') {
            return redirect()->to(URL::signedRoute('orders.payment', ['order' => $order->id]));
        }

        // COD (and anything else) -> thank-you.
        return redirect()->to(URL::signedRoute('orders.thankyou', ['order' => $order->id]));
    }
}
