<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\Book;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PaymentMethod;
use Database\Seeders\PaymentMethodSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * لقطة التكلفة وقت البيع (المرحلة ٢): سطر الطلب يجمّد unit_cost/line_cost من
 * books.cost_price لحظة الطلب، فيصير الربح ثابتًا لا يتأثر بتعديل التكلفة لاحقًا
 * ولا بحذف الكتاب. كتاب بلا تكلفة يُلتقط سطره بتكلفة NULL — لا صفر مخترع (0.4).
 *
 * HONESTY (1.3/1.5): يُشغَّل على MySQL + bcmath عبر php artisan test.
 */
final class CostSnapshotTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PaymentMethodSeeder::class);
        PaymentMethod::query()->where('code', 'cod')->update(['is_enabled' => true]);
    }

    private function book(string $price, ?string $cost): Book
    {
        return Book::factory()->create([
            'price' => $price, 'cost_price' => $cost,
            'stock_status' => 'in_stock', 'stock_quantity' => 50, 'manage_stock' => true,
        ]);
    }

    private function placeOrder(Book $book, int $qty): void
    {
        $this->post(route('checkout.place'), [
            'name' => 'أم أحمد', 'phone' => '01012345678', 'email' => 'buyer@example.com',
            'governorate' => 'القاهرة', 'address' => 'شارع التجربة رقم 5', 'payment_method' => 'cod',
            'items' => [['book_id' => $book->id, 'qty' => $qty]],
        ])->assertStatus(302);
    }

    public function test_cost_is_snapshotted_from_the_book_at_order_time(): void
    {
        $book = $this->book(price: '200.00', cost: '120.00');
        $this->placeOrder($book, 3);

        $item = OrderItem::firstOrFail();
        $this->assertSame('120.00', $item->unit_cost);
        $this->assertSame('360.00', $item->line_cost); // 120 × 3
    }

    public function test_editing_the_book_cost_later_does_not_change_the_snapshot(): void
    {
        $book = $this->book(price: '200.00', cost: '120.00');
        $this->placeOrder($book, 1);

        // تغيّر تكلفة الكتاب بعد الطلب — يجب ألا يمسّ لقطة الطلب.
        $book->update(['cost_price' => '999.00']);

        $this->assertSame('120.00', OrderItem::firstOrFail()->unit_cost);
    }

    public function test_a_book_without_cost_snapshots_null_not_zero(): void
    {
        $book = $this->book(price: '200.00', cost: null);
        $this->placeOrder($book, 2);

        $item = OrderItem::firstOrFail();
        $this->assertNull($item->unit_cost, 'كتاب بلا تكلفة: NULL لا صفر مخترع');
        $this->assertNull($item->line_cost);
    }

    public function test_snapshot_survives_hard_book_deletion(): void
    {
        $book = $this->book(price: '200.00', cost: '120.00');
        $this->placeOrder($book, 1);

        // Book يستخدم SoftDeletes فالحذف العادي يبقي الصف؛ نُجبر الحذف الصلب
        // لنُثبت أن اللقطة تصمد حتى مع تفعيل books.book_id = nullOnDelete.
        $book->forceDelete();

        $item = OrderItem::firstOrFail();
        $this->assertNull($item->book_id, 'FK صار NULL بعد الحذف الصلب');
        $this->assertSame('120.00', $item->unit_cost, 'اللقطة تبقى بعد حذف الكتاب');
        $this->assertNotEmpty($item->book_title, 'عنوان الكتاب محفوظ في اللقطة');
    }
}
