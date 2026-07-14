<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Storefront\Concerns\InteractsWithSessionCart;
use App\Http\Requests\CouponApplyRequest;
use App\Services\Cart\CartService;
use App\Services\Coupon\CouponService;
use App\Support\Money;
use Illuminate\Http\JsonResponse;

/**
 * AJAX coupon preview. The cart is read from the session server-side and priced
 * from the DB; the client only sends the code (never a total) — 4.1.
 */
class CouponController extends Controller
{
    use InteractsWithSessionCart;

    public function apply(
        CouponApplyRequest $request,
        CartService $cartService,
        CouponService $couponService,
    ): JsonResponse {
        $cart = $this->buildSessionCart($request, $cartService);

        if ($cart->isEmpty()) {
            return response()->json([
                'valid' => false,
                'message' => __('payment.errors.empty_cart'),
            ]);
        }

        $result = $couponService->apply(
            (string) $request->validated('coupon'),
            $cart,
            $request->user()?->id,
        );

        if (! $result->valid) {
            return response()->json([
                'valid' => false,
                'message' => __($result->messageKey),
                'subtotal' => $cart->subtotal,
            ]);
        }

        // Preview total excludes shipping (governorate is chosen on the form).
        $previewTotal = Money::clampNonNegative(Money::sub($cart->subtotal, $result->discount));

        return response()->json([
            'valid' => true,
            'message' => __($result->messageKey),
            'code' => $result->coupon?->code,
            'discount' => $result->discount,
            'free_shipping' => $result->freeShipping,
            'subtotal' => $cart->subtotal,
            'preview_total' => $previewTotal,
        ]);
    }
}
