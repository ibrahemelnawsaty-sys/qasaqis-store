<?php

declare(strict_types=1);

namespace Tests\Feature\Storefront;

use App\Models\Book;
use App\Services\Cart\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The cart NEVER trusts a client-supplied price — every line is re-priced from
 * books.price in the DB (constitution 4.1). A book with no price (BOOK1) or that
 * is unpublished is silently dropped, never given an invented default (0.4 / 21).
 *
 * HONESTY (1.3/1.5): NOT executed here (no PHP); runs via `php artisan test`.
 * Requires the bcmath extension (Money uses bcmath).
 */
final class CartRepricingTest extends TestCase
{
    use RefreshDatabase;

    private function cart(): CartService
    {
        return app(CartService::class);
    }

    public function test_line_and_subtotal_come_from_the_db_price(): void
    {
        $book = Book::factory()->create(['price' => '200.00']);

        // The client "claims" a price of 1.00 — it must be ignored entirely.
        $cart = $this->cart()->fromItems([
            ['book_id' => $book->id, 'qty' => 3, 'price' => '1.00'],
        ]);

        $this->assertCount(1, $cart->items);
        $this->assertSame('200.00', $cart->items[0]->unitPrice); // from DB, not client.
        $this->assertSame('600.00', $cart->items[0]->lineTotal);
        $this->assertSame('600.00', $cart->subtotal);
        $this->assertSame(3, $cart->count);
    }

    public function test_book_without_a_price_is_excluded(): void
    {
        $priced = Book::factory()->create(['price' => '150.00']);
        // BOOK1-style: no price, and (per the model rules) not published.
        $noPrice = Book::factory()->withoutPrice()->create();

        $cart = $this->cart()->fromItems([
            ['book_id' => $priced->id, 'qty' => 1],
            ['book_id' => $noPrice->id, 'qty' => 1],
        ]);

        $this->assertCount(1, $cart->items);
        $this->assertSame($priced->id, $cart->items[0]->book->id);
        $this->assertContains($noPrice->id, $cart->ignoredBookIds);
        $this->assertSame('150.00', $cart->subtotal);
    }

    public function test_unpublished_book_is_excluded(): void
    {
        $draft = Book::factory()->unpublished()->create(['price' => '150.00']);

        $cart = $this->cart()->fromItems([
            ['book_id' => $draft->id, 'qty' => 2],
        ]);

        $this->assertTrue($cart->isEmpty());
        $this->assertContains($draft->id, $cart->ignoredBookIds);
        $this->assertSame('0.00', $cart->subtotal);
    }

    public function test_quantity_is_clamped_to_the_max_per_line(): void
    {
        $book = Book::factory()->create(['price' => '10.00']);

        $cart = $this->cart()->fromItems([
            ['book_id' => $book->id, 'qty' => 9999],
        ]);

        $this->assertSame(CartService::MAX_QTY_PER_LINE, $cart->items[0]->quantity);
    }

    public function test_cart_update_stores_only_book_id_and_qty_in_session(): void
    {
        $book = Book::factory()->create(['price' => '120.00']);

        $response = $this->post(route('cart.update'), [
            'items' => [
                ['book_id' => $book->id, 'qty' => 2],
            ],
        ]);

        $response->assertRedirect(route('cart.show'));
        // Session holds a plain {book_id: qty} map — no prices client-side.
        $this->assertSame([$book->id => 2], session('cart'));

        $this->get(route('cart.show'))->assertOk();
    }

    public function test_cart_update_with_zero_qty_removes_the_line(): void
    {
        $book = Book::factory()->create(['price' => '120.00']);

        $this->post(route('cart.update'), [
            'items' => [['book_id' => $book->id, 'qty' => 0]],
        ])->assertRedirect(route('cart.show'));

        $this->assertSame([], session('cart'));
    }
}
