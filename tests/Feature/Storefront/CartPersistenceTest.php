<?php

declare(strict_types=1);

namespace Tests\Feature\Storefront;

use App\Http\Controllers\Storefront\CartController;
use App\Models\Book;
use App\Models\Category;
use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * حفظ سلة العميل المسجَّل على الخادم (متابعة السلات المتروكة). المزامنة خلفيّة عبر
 * POST /cart/sync: للمسجَّلين فقط، id + qty فقط (الأسعار خادمية — بند 4.1)، وسلة فارغة
 * تمسح الصفّ. الزائر لا يُخزَّن له شيء. HONESTY (1.3): تعمل عبر php artisan test.
 */
final class CartPersistenceTest extends TestCase
{
    use RefreshDatabase;

    private function book(): Book
    {
        return Book::factory()->create(['category_id' => Category::factory()]);
    }

    public function test_a_guest_sync_is_a_no_op_and_stores_nothing(): void
    {
        $book = $this->book();

        $this->postJson(route('cart.sync'), ['items' => [['book_id' => $book->id, 'qty' => 2]]])
            ->assertNoContent();

        $this->assertDatabaseCount('customer_carts', 0);
    }

    public function test_a_logged_in_customer_cart_is_persisted(): void
    {
        $customer = Customer::factory()->create();
        $a = $this->book();
        $b = $this->book();

        $this->actingAs($customer, 'customer')
            ->postJson(route('cart.sync'), ['items' => [
                ['book_id' => $a->id, 'qty' => 2],
                ['book_id' => $b->id, 'qty' => 1],
            ]])
            ->assertNoContent();

        $this->assertDatabaseHas('customer_carts', ['customer_id' => $customer->id]);

        $row = DB::table('customer_carts')->where('customer_id', $customer->id)->first();
        $this->assertEqualsCanonicalizing(
            [['id' => $a->id, 'qty' => 2], ['id' => $b->id, 'qty' => 1]],
            json_decode((string) $row->items, true),
        );
    }

    public function test_duplicate_book_ids_are_merged_and_qty_capped(): void
    {
        $customer = Customer::factory()->create();
        $book = $this->book();

        $this->actingAs($customer, 'customer')
            ->postJson(route('cart.sync'), ['items' => [
                ['book_id' => $book->id, 'qty' => 60],
                ['book_id' => $book->id, 'qty' => 60],
            ]])
            ->assertNoContent();

        $row = DB::table('customer_carts')->where('customer_id', $customer->id)->first();
        $this->assertSame([['id' => $book->id, 'qty' => 99]], json_decode((string) $row->items, true));
    }

    public function test_an_empty_sync_deletes_the_persisted_cart(): void
    {
        $customer = Customer::factory()->create();
        DB::table('customer_carts')->insert([
            'customer_id' => $customer->id,
            'items' => json_encode([['id' => 1, 'qty' => 1]]),
            'updated_at' => now(),
        ]);

        $this->actingAs($customer, 'customer')
            ->postJson(route('cart.sync'), ['items' => []])
            ->assertNoContent();

        $this->assertDatabaseCount('customer_carts', 0);
    }

    public function test_syncing_replaces_the_existing_cart(): void
    {
        $customer = Customer::factory()->create();
        $old = $this->book();
        $new = $this->book();
        DB::table('customer_carts')->insert([
            'customer_id' => $customer->id,
            'items' => json_encode([['id' => $old->id, 'qty' => 5]]),
            'updated_at' => now()->subDay(),
        ]);

        $this->actingAs($customer, 'customer')
            ->postJson(route('cart.sync'), ['items' => [['book_id' => $new->id, 'qty' => 1]]])
            ->assertNoContent();

        $this->assertDatabaseCount('customer_carts', 1);
        $row = DB::table('customer_carts')->where('customer_id', $customer->id)->first();
        $this->assertSame([['id' => $new->id, 'qty' => 1]], json_decode((string) $row->items, true));
    }

    public function test_forget_persisted_cart_clears_the_row(): void
    {
        $customer = Customer::factory()->create();
        DB::table('customer_carts')->insert([
            'customer_id' => $customer->id,
            'items' => json_encode([['id' => 1, 'qty' => 1]]),
            'updated_at' => now(),
        ]);

        CartController::forgetPersistedCart($customer->id);

        $this->assertDatabaseCount('customer_carts', 0);
    }

    public function test_mine_returns_empty_for_a_guest(): void
    {
        $this->getJson(route('cart.mine'))->assertOk()->assertExactJson(['items' => []]);
    }

    public function test_mine_is_empty_when_the_customer_has_no_saved_cart(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs($customer, 'customer')
            ->getJson(route('cart.mine'))
            ->assertOk()
            ->assertExactJson(['items' => []]);
    }

    public function test_mine_returns_the_saved_cart_resolved_from_the_db(): void
    {
        $customer = Customer::factory()->create();
        $book = Book::factory()->create([
            'category_id' => Category::factory(),
            'title' => 'كتاب المشاعر',
            'price' => '120.00',
        ]);
        DB::table('customer_carts')->insert([
            'customer_id' => $customer->id,
            'items' => json_encode([['id' => $book->id, 'qty' => 2]]),
            'updated_at' => now(),
        ]);

        $this->actingAs($customer, 'customer')
            ->getJson(route('cart.mine'))
            ->assertOk()
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.id', $book->id)
            ->assertJsonPath('items.0.title', 'كتاب المشاعر')
            ->assertJsonPath('items.0.qty', 2)
            ->assertJsonPath('items.0.price', '120 ج.م'); // السعر من قاعدة البيانات (4.1)
    }

    public function test_mine_skips_deleted_books(): void
    {
        $customer = Customer::factory()->create();
        $book = $this->book();
        DB::table('customer_carts')->insert([
            'customer_id' => $customer->id,
            'items' => json_encode([['id' => $book->id, 'qty' => 1]]),
            'updated_at' => now(),
        ]);
        $book->delete();

        $this->actingAs($customer, 'customer')
            ->getJson(route('cart.mine'))
            ->assertOk()
            ->assertExactJson(['items' => []]);
    }

    public function test_a_session_cart_owned_by_another_customer_is_not_shown(): void
    {
        // عزل الحسابات على الجهاز المشترك: سلة الجلسة المختومة بعميلٍ آخر لا تُعرَض.
        $a = Customer::factory()->create();
        $b = Customer::factory()->create();
        $book = Book::factory()->create(['category_id' => Category::factory(), 'title' => 'كتاب العميل أ']);

        $this->actingAs($b, 'customer')
            ->withSession(['cart' => [$book->id => 2], 'cart_owner' => (string) $a->id])
            ->get(route('cart.show'))
            ->assertOk()
            ->assertDontSee('كتاب العميل أ', false);
    }

    public function test_a_customer_sees_their_own_session_cart(): void
    {
        $b = Customer::factory()->create();
        $book = Book::factory()->create(['category_id' => Category::factory(), 'title' => 'كتاب العميل ب']);

        $this->actingAs($b, 'customer')
            ->withSession(['cart' => [$book->id => 1], 'cart_owner' => (string) $b->id])
            ->get(route('cart.show'))
            ->assertOk()
            ->assertSee('كتاب العميل ب', false);
    }

    public function test_mine_skips_unpublished_books(): void
    {
        // نفس رؤية الدفع (منشور وله سعر — 0.4/BOOK1): كتابٌ أُلغي نشره لا يظهر عبر الأجهزة.
        $customer = Customer::factory()->create();
        $book = Book::factory()->create([
            'category_id' => Category::factory(),
            'is_published' => false,
        ]);
        DB::table('customer_carts')->insert([
            'customer_id' => $customer->id,
            'items' => json_encode([['id' => $book->id, 'qty' => 1]]),
            'updated_at' => now(),
        ]);

        $this->actingAs($customer, 'customer')
            ->getJson(route('cart.mine'))
            ->assertOk()
            ->assertExactJson(['items' => []]);
    }
}
