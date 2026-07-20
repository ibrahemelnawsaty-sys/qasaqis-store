<?php

declare(strict_types=1);

namespace Tests\Feature\Checkout;

use App\Models\Book;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\Order;
use Database\Factories\CouponFactory;
use Database\Seeders\PaymentMethodSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Coupon redemption counting must be exact and race-safe (constitution 3.5):
 *  - placing an order with a coupon increments used_count once and records a
 *    CouponUsage row inside the same transaction;
 *  - once the global usage_limit is reached the coupon can no longer be applied;
 *  - the counter is claimed with a single atomic conditional UPDATE, so it can
 *    never be pushed past the limit (the concurrency guard in PlaceOrderAction).
 *
 * HONESTY (1.3/1.5): NOT executed here (no PHP); runs via `php artisan test`.
 * Requires MySQL + bcmath.
 */
final class CouponCounterAtomicityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PaymentMethodSeeder::class); // cod enabled, no online gateway.

        // COD مبذور معطّلًا (3c40e04)؛ هذا الملف يحتاجه مفعّلًا. نُفعّله صراحةً
        // بدل الاعتماد على قيمة بذرة تتغيّر بقرار تجاري.
        \App\Models\PaymentMethod::query()->where('code', 'cod')->update(['is_enabled' => true]);
    }

    /** A published, in-stock, priced book. */
    private function book(string $price = '200.00'): Book
    {
        return Book::factory()->create([
            'price' => $price,
            'stock_status' => 'in_stock',
            'stock_quantity' => 50,
            'manage_stock' => true,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function checkoutPayload(Book $book, string $couponCode): array
    {
        return [
            'name' => 'أم أحمد',
            'phone' => '01012345678',
            'governorate' => 'القاهرة',
            'address' => 'شارع التجربة رقم 5',
            'payment_method' => 'cod',
            'coupon' => $couponCode,
            'items' => [
                ['book_id' => $book->id, 'qty' => 1],
            ],
        ];
    }

    public function test_placing_an_order_increments_used_count_and_records_usage(): void
    {
        $book = $this->book('200.00');
        $coupon = CouponFactory::new()->percentage(10)->usageLimit(5)->create(['used_count' => 0]);

        $response = $this->post(route('checkout.place'), $this->checkoutPayload($book, $coupon->code));

        $response->assertStatus(302); // redirected to the signed thank-you page.

        $this->assertSame(1, Order::count());
        $this->assertSame(1, (int) $coupon->fresh()->used_count);
        $this->assertSame(1, CouponUsage::where('coupon_id', $coupon->id)->count());

        // The order snapshot recorded the 10% discount off 200.
        $order = Order::firstOrFail();
        $this->assertSame($coupon->id, $order->coupon_id);
        $this->assertSame('20.00', $order->discount_total);
    }

    public function test_coupon_at_its_usage_limit_blocks_a_further_order(): void
    {
        $book = $this->book('200.00');
        // Already fully used up.
        $coupon = CouponFactory::new()->usageLimit(1)->create(['used_count' => 1]);

        $response = $this->post(route('checkout.place'), $this->checkoutPayload($book, $coupon->code));

        // Rejected back to the checkout form; no order, counter untouched.
        $response->assertRedirect(route('checkout.show'));
        $this->assertSame(0, Order::count());
        $this->assertSame(1, (int) $coupon->fresh()->used_count);
    }

    public function test_atomic_claim_never_pushes_used_count_past_the_limit(): void
    {
        // This mirrors the exact conditional UPDATE PlaceOrderAction uses to claim
        // one use. A second claim after the limit is reached must affect 0 rows —
        // the DB-level guarantee that closes the concurrent-checkout race.
        $coupon = CouponFactory::new()->usageLimit(1)->create(['used_count' => 0]);

        $claim = static fn (): int => Coupon::query()
            ->whereKey($coupon->id)
            ->where(function ($query): void {
                $query->whereNull('usage_limit')
                    ->orWhereColumn('used_count', '<', 'usage_limit');
            })
            ->increment('used_count');

        $this->assertSame(1, $claim()); // first claim succeeds
        $this->assertSame(0, $claim()); // second is refused by the WHERE guard
        $this->assertSame(1, (int) $coupon->fresh()->used_count);
    }
}
