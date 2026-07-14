<?php

declare(strict_types=1);

namespace App\Services\Coupon;

use App\Models\Coupon;
use App\Support\Cart\Cart;
use App\Support\Cart\CartItem;
use App\Support\Coupon\CouponResult;
use App\Support\Money;
use Illuminate\Support\Carbon;

/**
 * Validates a coupon against a priced cart and computes the discount.
 *
 * Checks (all server-side, against the DB): active flag, date window
 * (starts_at/expires_at), minimum order total, global usage_limit,
 * per-user usage_limit_per_user, and applies_to scope (all / categories /
 * products). Handles free_shipping. Money is decimal via Money (no float).
 */
class CouponService
{
    /**
     * Look up a coupon by code and validate it. Returns an invalid result with a
     * translation key when it cannot be applied.
     */
    public function apply(?string $code, Cart $cart, ?int $userId = null): CouponResult
    {
        $code = trim((string) $code);

        if ($code === '') {
            return CouponResult::invalid('payment.coupon.required');
        }

        $coupon = Coupon::query()
            ->with(['books:id', 'categories:id'])
            ->where('code', $code)
            ->first();

        if ($coupon === null) {
            return CouponResult::invalid('payment.coupon.not_found');
        }

        return $this->validate($coupon, $cart, $userId);
    }

    /**
     * Validate an already-loaded coupon against the cart.
     */
    public function validate(Coupon $coupon, Cart $cart, ?int $userId = null): CouponResult
    {
        if (! $coupon->is_active) {
            return CouponResult::invalid('payment.coupon.inactive');
        }

        $now = Carbon::now();

        if ($coupon->starts_at !== null && $coupon->starts_at->greaterThan($now)) {
            return CouponResult::invalid('payment.coupon.not_started');
        }

        if ($coupon->expires_at !== null && $coupon->expires_at->lessThan($now)) {
            return CouponResult::invalid('payment.coupon.expired');
        }

        if ($coupon->usage_limit !== null && $coupon->used_count >= $coupon->usage_limit) {
            return CouponResult::invalid('payment.coupon.usage_limit');
        }

        // NOTE (constitution 1.5): the per-user limit is only enforceable for
        // authenticated users. For GUESTS (user_id === null) usage_limit_per_user
        // is deliberately NOT enforced — there is no reliable identity to count
        // prior uses against, so we skip this check. The GLOBAL usage_limit above
        // still applies to everyone (guests included) and remains the effective
        // cap for guest checkouts. This is a documented limitation, not a bug.
        if ($userId !== null && $coupon->usage_limit_per_user !== null) {
            $userUses = $coupon->usages()->where('user_id', $userId)->count();

            if ($userUses >= $coupon->usage_limit_per_user) {
                return CouponResult::invalid('payment.coupon.user_limit');
            }
        }

        // Minimum order total is checked against the whole cart subtotal.
        if ($coupon->min_order_total !== null
            && ! Money::gte($cart->subtotal, Money::normalize($coupon->min_order_total))) {
            return CouponResult::invalid('payment.coupon.min_total');
        }

        // Eligible amount depends on the applies_to scope.
        $eligible = $this->eligibleAmount($coupon, $cart);

        if (! Money::isPositive($eligible)) {
            return CouponResult::invalid('payment.coupon.not_applicable');
        }

        $discount = $this->computeDiscount($coupon, $eligible);

        if (! Money::isPositive($discount)) {
            return CouponResult::invalid('payment.coupon.not_applicable');
        }

        return CouponResult::valid($coupon, $discount, $coupon->free_shipping);
    }

    /**
     * Sum of line totals the coupon applies to.
     *  - all        => full subtotal.
     *  - products   => lines whose book is in coupon->books.
     *  - categories => lines whose book's main category OR any pivot category is
     *                  in coupon->categories.
     */
    private function eligibleAmount(Coupon $coupon, Cart $cart): string
    {
        if ($coupon->applies_to === 'all') {
            return $cart->subtotal;
        }

        if ($coupon->applies_to === 'products') {
            $allowed = $coupon->books->pluck('id')->all();

            return $this->sumLines(
                $cart,
                static fn (CartItem $item): bool => in_array($item->book->id, $allowed, true)
            );
        }

        // categories.
        $allowedCategories = $coupon->categories->pluck('id')->all();

        return $this->sumLines($cart, function (CartItem $item) use ($allowedCategories): bool {
            $bookCategoryIds = array_merge(
                [$item->book->category_id],
                $item->book->relationLoaded('categories')
                    ? $item->book->categories->pluck('id')->all()
                    : []
            );

            return array_intersect($bookCategoryIds, $allowedCategories) !== [];
        });
    }

    /**
     * @param  callable(CartItem): bool  $matches
     */
    private function sumLines(Cart $cart, callable $matches): string
    {
        $sum = Money::ZERO;

        foreach ($cart->items as $item) {
            if ($matches($item)) {
                $sum = Money::add($sum, $item->lineTotal);
            }
        }

        return $sum;
    }

    /**
     * Discount for the eligible amount: percentage (capped by max_discount) or
     * fixed (never more than the eligible amount).
     */
    private function computeDiscount(Coupon $coupon, string $eligible): string
    {
        if ($coupon->type === 'percentage') {
            $discount = Money::percentOf($eligible, Money::normalize($coupon->value));

            if ($coupon->max_discount !== null) {
                $discount = Money::min($discount, Money::normalize($coupon->max_discount));
            }

            return Money::min($discount, $eligible);
        }

        // fixed: cannot exceed the eligible amount.
        return Money::min(Money::normalize($coupon->value), $eligible);
    }
}
