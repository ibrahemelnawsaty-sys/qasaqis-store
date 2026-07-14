<?php

declare(strict_types=1);

namespace Tests\Feature\Checkout;

use App\Models\Book;
use App\Models\CouponUsage;
use App\Models\User;
use App\Services\Cart\CartService;
use App\Services\Coupon\CouponService;
use App\Support\Cart\Cart;
use Database\Factories\CouponFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Coupon validation is entirely server-side against the DB (constitution 4.1):
 * active flag, date window (starts_at/expires_at), minimum order total, global
 * usage_limit and per-user usage_limit_per_user. All amounts are decimal.
 *
 * NOTE: Coupon has no HasFactory trait (app code out of scope), so CouponFactory
 * is used directly via ::new().
 *
 * HONESTY (1.3/1.5): NOT executed here (no PHP); runs via `php artisan test`.
 */
final class CouponValidationTest extends TestCase
{
    use RefreshDatabase;

    /** Build a DB-priced cart from a single book at the given price/qty. */
    private function cartAt(string $price, int $qty = 1): Cart
    {
        $book = Book::factory()->create(['price' => $price]);

        return app(CartService::class)->fromItems([
            ['book_id' => $book->id, 'qty' => $qty],
        ]);
    }

    private function service(): CouponService
    {
        return app(CouponService::class);
    }

    public function test_valid_percentage_coupon_applies_a_discount(): void
    {
        $coupon = CouponFactory::new()->percentage(10)->create();
        $cart = $this->cartAt('200.00');

        $result = $this->service()->validate($coupon, $cart);

        $this->assertTrue($result->valid);
        $this->assertSame('20.00', $result->discount); // 10% of 200.
        $this->assertSame('payment.coupon.applied', $result->messageKey);
    }

    public function test_fixed_coupon_never_exceeds_the_eligible_amount(): void
    {
        $coupon = CouponFactory::new()->fixed(500)->create();
        $cart = $this->cartAt('150.00');

        $result = $this->service()->validate($coupon, $cart);

        $this->assertTrue($result->valid);
        $this->assertSame('150.00', $result->discount); // capped at the subtotal.
    }

    public function test_percentage_discount_is_capped_by_max_discount(): void
    {
        $coupon = CouponFactory::new()->percentage(50)->create(['max_discount' => '30.00']);
        $cart = $this->cartAt('200.00'); // 50% = 100, capped to 30.

        $result = $this->service()->validate($coupon, $cart);

        $this->assertTrue($result->valid);
        $this->assertSame('30.00', $result->discount);
    }

    public function test_inactive_coupon_is_rejected(): void
    {
        $coupon = CouponFactory::new()->inactive()->create();

        $result = $this->service()->validate($coupon, $this->cartAt('200.00'));

        $this->assertFalse($result->valid);
        $this->assertSame('payment.coupon.inactive', $result->messageKey);
    }

    public function test_expired_coupon_is_rejected(): void
    {
        $coupon = CouponFactory::new()->expired()->create();

        $result = $this->service()->validate($coupon, $this->cartAt('200.00'));

        $this->assertFalse($result->valid);
        $this->assertSame('payment.coupon.expired', $result->messageKey);
    }

    public function test_not_yet_started_coupon_is_rejected(): void
    {
        $coupon = CouponFactory::new()->notStarted()->create();

        $result = $this->service()->validate($coupon, $this->cartAt('200.00'));

        $this->assertFalse($result->valid);
        $this->assertSame('payment.coupon.not_started', $result->messageKey);
    }

    public function test_minimum_order_total_is_enforced(): void
    {
        $coupon = CouponFactory::new()->minTotal('300.00')->create();

        // Subtotal 200 < min 300 => rejected.
        $result = $this->service()->validate($coupon, $this->cartAt('200.00'));

        $this->assertFalse($result->valid);
        $this->assertSame('payment.coupon.min_total', $result->messageKey);
    }

    public function test_global_usage_limit_is_enforced(): void
    {
        $coupon = CouponFactory::new()->usageLimit(2)->create(['used_count' => 2]);

        $result = $this->service()->validate($coupon, $this->cartAt('200.00'));

        $this->assertFalse($result->valid);
        $this->assertSame('payment.coupon.usage_limit', $result->messageKey);
    }

    public function test_per_user_limit_is_enforced_for_authenticated_users(): void
    {
        $user = User::factory()->create();
        $coupon = CouponFactory::new()->perUserLimit(1)->create();

        // The user already redeemed it once.
        CouponUsage::create([
            'coupon_id' => $coupon->id,
            'user_id' => $user->id,
            'discount_amount' => '20.00',
        ]);

        $result = $this->service()->validate($coupon, $this->cartAt('200.00'), $user->id);

        $this->assertFalse($result->valid);
        $this->assertSame('payment.coupon.user_limit', $result->messageKey);
    }

    public function test_per_user_limit_is_not_counted_for_guests(): void
    {
        $coupon = CouponFactory::new()->perUserLimit(1)->create();

        // Guest (userId null) is not blocked by the per-user limit (documented
        // limitation in CouponService: no reliable identity to count against).
        $result = $this->service()->validate($coupon, $this->cartAt('200.00'), null);

        $this->assertTrue($result->valid);
    }

    public function test_apply_by_unknown_code_returns_not_found(): void
    {
        $result = $this->service()->apply('DOES-NOT-EXIST', $this->cartAt('200.00'));

        $this->assertFalse($result->valid);
        $this->assertSame('payment.coupon.not_found', $result->messageKey);
    }
}
