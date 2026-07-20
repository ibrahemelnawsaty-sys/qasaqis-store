<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Actions\Checkout\PlaceOrderAction;
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
        ]);
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
