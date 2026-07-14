<?php

declare(strict_types=1);

namespace Tests\Feature\Checkout;

use App\Models\Book;
use Database\Factories\CouponFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The AJAX coupon-preview endpoint (/coupon/apply) reads the cart from the SESSION
 * and re-prices it from the DB — the client sends only the code, never a total
 * (constitution 4.1). It returns a JSON verdict with a translated message.
 *
 * HONESTY (1.3/1.5): NOT executed here (no PHP); runs via `php artisan test`.
 */
final class CouponApplyEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_coupon_returns_a_discount_and_preview_total(): void
    {
        $book = Book::factory()->create(['price' => '200.00']);
        $coupon = CouponFactory::new()->percentage(10)->create();

        $response = $this->withSession(['cart' => [$book->id => 2]]) // subtotal 400
            ->postJson(route('coupon.apply'), ['coupon' => $coupon->code]);

        $response->assertOk()->assertJson([
            'valid' => true,
            'discount' => '40.00',       // 10% of 400
            'subtotal' => '400.00',
            'preview_total' => '360.00', // subtotal - discount (shipping excluded)
        ]);
    }

    public function test_expired_coupon_returns_invalid(): void
    {
        $book = Book::factory()->create(['price' => '200.00']);
        $coupon = CouponFactory::new()->expired()->create();

        $response = $this->withSession(['cart' => [$book->id => 1]])
            ->postJson(route('coupon.apply'), ['coupon' => $coupon->code]);

        $response->assertOk()->assertJson(['valid' => false]);
    }

    public function test_empty_cart_is_rejected(): void
    {
        $coupon = CouponFactory::new()->create();

        $response = $this->withSession(['cart' => []])
            ->postJson(route('coupon.apply'), ['coupon' => $coupon->code]);

        $response->assertOk()->assertJson(['valid' => false]);
    }

    public function test_a_code_is_required(): void
    {
        $book = Book::factory()->create(['price' => '200.00']);

        $this->withSession(['cart' => [$book->id => 1]])
            ->postJson(route('coupon.apply'), [])
            ->assertStatus(422); // CouponApplyRequest: coupon required.
    }
}
