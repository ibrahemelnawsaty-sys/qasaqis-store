<?php

declare(strict_types=1);

namespace App\Services\Coupon;

use App\Models\Coupon;
use App\Models\Customer;
use App\Models\Order;
use App\Support\Cart\Cart;
use App\Support\Cart\CartItem;
use App\Support\Coupon\CouponResult;
use App\Support\Money;
use App\Support\Phone\PhoneNormalizer;
use Illuminate\Support\Carbon;

/**
 * Validates a coupon against a priced cart and computes the discount.
 *
 * Checks (all server-side, against the DB): active flag, date window, minimum
 * order total, global usage_limit, per-CUSTOMER usage_limit_per_user, applies_to
 * product scope (all/categories/products), and the AUDIENCE scope (all/specific/
 * new/returning/lapsed/vip). Handles free_shipping. Money is decimal (no float).
 *
 * Customer identity: the shopper is identified by their registered customer id
 * (logged in) or, for a guest, by matching the checkout phone/email against the
 * customers table. History (new/returning/lapsed/vip) is computed from the ORDERS
 * table via Order::realisedRevenue() — NOT the dead customers.orders_count/total_spent
 * counters — counting orders linked to the account (customer_id) OR bearing the
 * customer's email. It does NOT count a guest's phone-only past orders (orders store
 * a raw, un-normalised phone that can't be matched in SQL); so history-audiences are
 * fully accurate for customers who order while logged in or provide their email, and
 * a phone-only guest may register as "new" — a documented limitation.
 * When the shopper is unidentifiable (guest preview before entering a phone) the
 * audience + per-customer checks are DEFERRED to order placement (which always has
 * the phone), so eligible guests are never wrongly blocked at preview.
 */
class CouponService
{
    /**
     * Look up a coupon by code and validate it. Returns an invalid result with a
     * translation key when it cannot be applied.
     */
    public function apply(?string $code, Cart $cart, ?int $customerId = null, ?string $phone = null, ?string $email = null): CouponResult
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

        return $this->validate($coupon, $cart, $customerId, $phone, $email);
    }

    /**
     * Validate an already-loaded coupon against the cart for a given shopper.
     */
    public function validate(Coupon $coupon, Cart $cart, ?int $customerId = null, ?string $phone = null, ?string $email = null): CouponResult
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

        // هويّة المتسوّق: للجمهور والحدّ-لكل-عميل. قد تتعذّر عند معاينة الزائر قبل إدخال
        // جوّاله؛ حينها نؤجّل هذين الفحصين إلى إتمام الطلب (حيث الجوّال متاح دائمًا)، فلا
        // يُحجب عميلٌ مؤهَّل خطأً في المعاينة. الحدّ العامّ (أعلاه) يسري على الجميع دومًا.
        $identified = $customerId !== null || filled($phone) || filled($email);
        $customer = $this->resolveCustomer($customerId, $phone, $email);

        if ($identified) {
            $audienceRejection = $this->audienceRejection($coupon, $customer);

            if ($audienceRejection !== null) {
                return CouponResult::invalid($audienceRejection);
            }
        }

        // حدّ «مرّة لكل عميل» بالعميل الحقيقيّ (لا مستخدم اللوحة) — يُفحَص عند التعرّف عليه.
        if ($customer !== null && $coupon->usage_limit_per_user !== null) {
            $uses = $coupon->usages()->where('customer_id', $customer->id)->count();

            if ($uses >= $coupon->usage_limit_per_user) {
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

        return CouponResult::valid($coupon, $discount, $coupon->free_shipping, $customer?->id);
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

    /**
     * يُحدّد العميل المتسوّق: بمُعرّفه (مسجّل الدخول) وإلا بمطابقة جوّاله المطبَّع ثم بريده
     * (زائر عند الدفع). null إن تعذّر (زائر بلا هويّة، أو جوّال/بريد لا يطابق أي عميل =
     * عميل جديد). لا يُستعمَل Gate/can مع Customer (الدستور) — استعلامات صريحة فقط.
     */
    private function resolveCustomer(?int $customerId, ?string $phone, ?string $email): ?Customer
    {
        if ($customerId !== null) {
            return Customer::find($customerId);
        }

        $normalized = PhoneNormalizer::normalize($phone);
        if ($normalized !== null && $normalized !== '') {
            $byPhone = Customer::where('phone_normalized', $normalized)->first();

            if ($byPhone !== null) {
                return $byPhone;
            }
        }

        if (filled($email)) {
            return Customer::where('email', $email)->first();
        }

        return null;
    }

    /**
     * مفتاح رسالة الرفض إن كان المتسوّق لا يستحقّ جمهور الكوبون، وإلا null. سجلّ العميل
     * (جديد/عائد/متوقّف/مميّز) يُحسَب من جدول الطلبات (الإيراد المحقَّق) لا من عدّادات ميّتة.
     */
    private function audienceRejection(Coupon $coupon, ?Customer $customer): ?string
    {
        $audience = $coupon->audience ?: 'all';

        if ($audience === 'all') {
            return null;
        }

        if ($audience === 'specific') {
            return ($customer !== null && $coupon->customer_id !== null && (int) $customer->id === (int) $coupon->customer_id)
                ? null
                : 'payment.coupon.audience_specific';
        }

        $stats = $this->customerStats($customer);

        return match ($audience) {
            'new' => $stats['count'] === 0 ? null : 'payment.coupon.audience_new',
            'returning' => $stats['count'] >= 1 ? null : 'payment.coupon.audience_returning',
            'lapsed' => ($stats['count'] >= 1
                && $stats['lastOrderAt'] !== null
                && $stats['lastOrderAt']->lessThan(Carbon::now()->subDays(max(1, (int) ($coupon->lapsed_days ?? 60)))))
                ? null
                : 'payment.coupon.audience_lapsed',
            'vip' => $this->meetsVip($coupon, $stats) ? null : 'payment.coupon.audience_vip',
            default => null,
        };
    }

    /**
     * إحصاء طلبات العميل المحقَّقة (استعلام تجميعيّ واحد): العدد، وقت آخر طلب، مجموع الصرف
     * (grand_total إجماليّ — تقدير كافٍ للاستهداف). المصدر Order::realisedRevenue لا العدّادات.
     *
     * نطابق الطلبات المربوطة بحسابه (customer_id) وكذلك طلباته كزائرٍ ببريده (customer_email)
     * — مطابقة دقيقة موثوقة. الجوّال لا يُطابَق هنا: orders.customer_phone خام غير مطبَّع
     * (يتعذّر مطابقته في SQL)، فطلبات زائرٍ بلا بريد لا تُحتسَب (حدّ معروف؛ يزول بالدخول).
     *
     * @return array{count: int, lastOrderAt: ?Carbon, spent: string}
     */
    private function customerStats(?Customer $customer): array
    {
        if ($customer === null) {
            return ['count' => 0, 'lastOrderAt' => null, 'spent' => Money::ZERO];
        }

        $row = Order::query()
            ->where(function ($query) use ($customer): void {
                $query->where('customer_id', $customer->id);

                if (filled($customer->email)) {
                    $query->orWhere('customer_email', $customer->email);
                }
            })
            ->realisedRevenue()
            ->selectRaw('COUNT(*) as cnt, MAX(created_at) as last_at, COALESCE(SUM(grand_total), 0) as spent')
            ->first();

        return [
            'count' => (int) ($row->cnt ?? 0),
            'lastOrderAt' => filled($row?->last_at) ? Carbon::parse($row->last_at) : null,
            'spent' => Money::normalize((string) ($row->spent ?? '0')),
        ];
    }

    /**
     * @param  array{count: int, lastOrderAt: ?Carbon, spent: string}  $stats
     */
    private function meetsVip(Coupon $coupon, array $stats): bool
    {
        // بلا حدّ مضبوط → أي عميل له طلب محقَّق (احتياط كي لا يصير الكوبون مستحيلًا).
        if ($coupon->min_orders === null && $coupon->min_spent === null) {
            return $stats['count'] >= 1;
        }

        $byOrders = $coupon->min_orders !== null && $stats['count'] >= (int) $coupon->min_orders;
        $bySpent = $coupon->min_spent !== null
            && Money::gte($stats['spent'], Money::normalize($coupon->min_spent));

        return $byOrders || $bySpent;
    }
}
