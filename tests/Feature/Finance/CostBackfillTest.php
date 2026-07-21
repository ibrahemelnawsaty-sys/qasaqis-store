<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\Book;
use App\Models\OrderItem;
use App\Models\PaymentMethod;
use App\Services\Finance\CostBackfillService;
use Database\Seeders\PaymentMethodSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ترحيل تكلفة الطلبات القديمة: يملأ unit_cost/line_cost للسطور التي أُنشئت قبل
 * إدخال سعر الشراء، من السعر الحالي للكتاب — دون المساس بلقطة أصلية، ودون اختراع
 * صفر لكتاب بلا تكلفة. يطابق منطق PlaceOrderAction (Money bcmath).
 *
 * HONESTY (1.3/1.5): يُشغَّل على MySQL + bcmath عبر php artisan test.
 */
final class CostBackfillTest extends TestCase
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

    public function test_backfill_fills_null_unit_cost_from_current_book_cost(): void
    {
        // طلب أُنشئ والكتاب بلا تكلفة → لقطة NULL.
        $book = $this->book(price: '200.00', cost: null);
        $this->placeOrder($book, 3);
        $this->assertNull(OrderItem::firstOrFail()->unit_cost);

        // المالك يُدخل سعر الشراء لاحقًا، ثم يُرحّل.
        $book->update(['cost_price' => '120.00']);
        $r = app(CostBackfillService::class)->run();

        $item = OrderItem::firstOrFail();
        $this->assertSame('120.00', $item->unit_cost);
        $this->assertSame('360.00', $item->line_cost); // 120 × 3 بحساب Money
        $this->assertSame(1, $r['filled']);
        $this->assertSame(1, $r['orders']);
    }

    public function test_backfill_never_overwrites_an_existing_snapshot(): void
    {
        // له تكلفة وقت الطلب، ثم تغيّرت تكلفة الكتاب.
        $book = $this->book(price: '200.00', cost: '120.00');
        $this->placeOrder($book, 1);
        $book->update(['cost_price' => '999.00']);

        $r = app(CostBackfillService::class)->run();

        // اللقطة الأصلية تصمد (idempotent) — لا تُستبدل بالسعر الجديد.
        $this->assertSame('120.00', OrderItem::firstOrFail()->unit_cost);
        $this->assertSame(0, $r['filled']);
    }

    public function test_backfill_skips_items_whose_book_still_has_no_cost(): void
    {
        $book = $this->book(price: '200.00', cost: null);
        $this->placeOrder($book, 2);

        $r = app(CostBackfillService::class)->run();

        // لا صفر مخترع: يبقى NULL ويُعدّ في skipped_no_cost.
        $this->assertNull(OrderItem::firstOrFail()->unit_cost);
        $this->assertSame(0, $r['filled']);
        $this->assertSame(1, $r['skipped_no_cost']);
    }

    public function test_dry_run_reports_but_writes_nothing(): void
    {
        $book = $this->book(price: '200.00', cost: null);
        $this->placeOrder($book, 1);
        $book->update(['cost_price' => '50.00']);

        $r = app(CostBackfillService::class)->run(dryRun: true);

        $this->assertSame(1, $r['filled']);                     // سيُملأ
        $this->assertNull(OrderItem::firstOrFail()->unit_cost); // لكن لم يُكتب فعلًا
    }
}
