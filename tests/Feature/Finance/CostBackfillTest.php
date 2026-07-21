<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\Book;
use App\Models\OrderItem;
use App\Models\Publisher;
use App\Services\Finance\CostBackfillService;
use App\Support\Money;
use Database\Factories\OrderFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ترحيل تكلفة الطلبات القديمة: يملأ unit_cost/line_cost للسطور التي أُنشئت قبل
 * تفعيل تتبّع التكلفة (unit_cost=NULL) — عبر BookCostResolver: تكلفة مُدخَلة إن
 * وُجدت، وإلا تقدير من خصم دار النشر. لا يمسّ لقطة موجودة، ويُعلّم المقدَّر.
 *
 * ملاحظة: نُنشئ السطور بـ unit_cost=NULL يدويًا لمحاكاة بيانات ما قبل الميزة —
 * فالبيع الآن (PlaceOrderAction) لم يعُد يحفظ NULL أصلًا (يقدّر لحظتها).
 *
 * HONESTY (1.3/1.5): يُشغَّل على MySQL + bcmath عبر php artisan test.
 */
final class CostBackfillTest extends TestCase
{
    use RefreshDatabase;

    /** سطر طلبٍ قديم بتكلفة NULL (محاكاة بيانات ما قبل الميزة). */
    private function legacyItem(Book $book, int $qty): OrderItem
    {
        $order = OrderFactory::new()->create([
            'status' => 'delivered', 'payment_method' => 'cod', 'payment_status' => 'unpaid',
        ]);

        return $order->items()->create([
            'book_id' => $book->id, 'book_title' => $book->title,
            'unit_price' => $book->price, 'quantity' => $qty,
            'line_total' => Money::multiplyByQty(Money::normalize($book->price), $qty),
            'unit_cost' => null, 'line_cost' => null,
        ]);
    }

    public function test_backfill_estimates_cost_from_publisher_discount(): void
    {
        $publisher = Publisher::factory()->create(['cost_discount_percent' => '40.00']);
        $book = Book::factory()->create([
            'price' => '200.00', 'cost_price' => null, 'publisher_id' => $publisher->id,
        ]);
        $item = $this->legacyItem($book, 3);
        $this->assertNull($item->unit_cost);

        $r = app(CostBackfillService::class)->run();

        $item->refresh();
        $this->assertSame('120.00', $item->unit_cost);   // 200 × (1 − 0.40)
        $this->assertSame('360.00', $item->line_cost);   // 120 × 3
        $this->assertTrue((bool) $item->cost_is_estimated);
        $this->assertSame(1, $r['filled']);
        $this->assertSame(1, $r['estimated']);
        $this->assertSame(1, $r['orders']);
    }

    public function test_backfill_uses_manual_cost_when_admin_set_it_later(): void
    {
        // طلب قديم بلا تكلفة، ثم أدخل الأدمن سعر الشراء الحقيقي للكتاب.
        $book = Book::factory()->create(['price' => '200.00', 'cost_price' => null]);
        $item = $this->legacyItem($book, 1);
        $book->update(['cost_price' => '90.00']);

        $r = app(CostBackfillService::class)->run();

        $item->refresh();
        $this->assertSame('90.00', $item->unit_cost);
        $this->assertFalse((bool) $item->cost_is_estimated); // مؤكّدة لا تقديرية
        $this->assertSame(0, $r['estimated']);
    }

    public function test_backfill_never_overwrites_an_existing_snapshot(): void
    {
        $book = Book::factory()->create(['price' => '200.00', 'cost_price' => '120.00']);
        $order = OrderFactory::new()->create(['status' => 'delivered', 'payment_method' => 'cod']);
        $order->items()->create([
            'book_id' => $book->id, 'book_title' => $book->title,
            'unit_price' => '200.00', 'quantity' => 1, 'line_total' => '200.00',
            'unit_cost' => '50.00', 'line_cost' => '50.00', // لقطة أصلية محفوظة
        ]);
        $book->update(['cost_price' => '999.00']);

        $r = app(CostBackfillService::class)->run();

        // whereNull فقط — السطر ذو التكلفة لا يُلمس (idempotent).
        $this->assertSame('50.00', OrderItem::firstOrFail()->unit_cost);
        $this->assertSame(0, $r['filled']);
    }

    public function test_dry_run_reports_but_writes_nothing(): void
    {
        $publisher = Publisher::factory()->create(['cost_discount_percent' => '25.00']);
        $book = Book::factory()->create([
            'price' => '100.00', 'cost_price' => null, 'publisher_id' => $publisher->id,
        ]);
        $item = $this->legacyItem($book, 1);

        $r = app(CostBackfillService::class)->run(dryRun: true);

        $this->assertSame(1, $r['filled']);            // سيُملأ
        $item->refresh();
        $this->assertNull($item->unit_cost);           // لكن لم يُكتب فعلًا
    }
}
