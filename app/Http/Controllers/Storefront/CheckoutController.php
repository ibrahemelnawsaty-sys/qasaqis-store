<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Actions\Checkout\PlaceOrderAction;
use App\Exceptions\CheckoutException;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Storefront\Concerns\InteractsWithSessionCart;
use App\Http\Requests\CheckoutRequest;
use App\Models\Country;
use App\Models\Order;
use App\Services\Cart\CartService;
use App\Services\Payment\PaymentMethodResolver;
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

        return view('checkout.show', [
            'cart' => $cart,
            'methods' => $resolver->available(),
            'onlineEnabled' => $resolver->isOnlineEnabled(),
            'onlineDisabledMessageKey' => $resolver->onlineDisabledMessageKey(),
            'governorates' => config('egypt.governorates'),
            'countries' => Country::active()->orderBy('sort_order')->get(['iso_code', 'name_ar']),
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

        return $this->redirectAfterPlacement($result->order, $result->initiation);
    }

    /**
     * Route the customer to the next step based on the payment path.
     */
    private function redirectAfterPlacement(Order $order, ?PaymentInitiation $initiation): RedirectResponse
    {
        // Online gateway path.
        if ($initiation !== null) {
            if ($initiation->success && $initiation->redirectUrl !== null) {
                return redirect()->away($initiation->redirectUrl);
            }

            // Gateway could not start (e.g. not configured) — order stays pending.
            return redirect()
                ->to(URL::signedRoute('orders.thankyou', ['order' => $order->id]))
                ->with('warning', __($initiation->messageKey ?? 'payment.gateway.unavailable'));
        }

        // Manual transfer path -> instructions + proof upload.
        if ($order->payment_status === 'pending_review') {
            return redirect()->to(URL::signedRoute('orders.payment', ['order' => $order->id]));
        }

        // COD (and anything else) -> thank-you.
        return redirect()->to(URL::signedRoute('orders.thankyou', ['order' => $order->id]));
    }
}
