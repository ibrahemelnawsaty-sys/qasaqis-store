<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\Book;
use App\Services\Finance\FinanceReportService;
use Carbon\CarbonImmutable;
use Database\Factories\OrderFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * حساب الربح (م٢) وهامش الشحن والمساهمة (م٣).
 *
 * القاعدة الحرجة (سلامة السكان — أوجبتها المراجعة العدائية): الربح يُحسب فقط على
 * الطلبات معروفة التكلفة بالكامل؛ لا نطرح تكلفة جزئية من إيراد كامل. والمساهمة
 * على تقاطع «معروفة التكلفة + معروفة تكلفة الشحن» فقط.
 *
 * HONESTY (1.3/1.5): يُشغَّل على MySQL + bcmath عبر php artisan test.
 */
final class FinanceProfitTest extends TestCase
{
    use RefreshDatabase;

    private function service(): FinanceReportService
    {
        return app(FinanceReportService::class);
    }

    private function range(): array
    {
        $now = CarbonImmutable::now('Africa/Cairo');

        return [$now->subDays(7)->startOfDay(), $now->endOfDay()];
    }

    /**
     * طلب محقّق (COD مُسلَّم) بسطر واحد له سعر وتكلفة، وتكلفة شحن اختيارية.
     */
    private function realisedOrder(string $subtotal, ?string $unitCost, ?string $carrier = null): void
    {
        $order = OrderFactory::new()->create([
            'status' => 'delivered', 'payment_method' => 'cod', 'payment_status' => 'unpaid',
            'subtotal' => $subtotal, 'discount_total' => '0.00',
            'shipping_total' => '50.00', 'carrier_cost' => $carrier,
        ]);

        $book = Book::factory()->create(['price' => $subtotal]);
        $order->items()->create([
            'book_id' => $book->id, 'book_title' => $book->title,
            'unit_price' => $subtotal, 'unit_cost' => $unitCost,
            'quantity' => 1, 'line_total' => $subtotal,
            'line_cost' => $unitCost, // كمية 1
        ]);
    }

    public function test_gross_profit_is_net_minus_cogs_over_costed_orders(): void
    {
        $this->realisedOrder(subtotal: '300.00', unitCost: '120.00');
        $this->realisedOrder(subtotal: '200.00', unitCost: '80.00');

        [$from, $to] = $this->range();
        $p = $this->service()->profit($from, $to);

        $this->assertSame('200.00', $p['cogs']);          // 120 + 80
        $this->assertSame('500.00', $p['net_costed']);    // 300 + 200
        $this->assertSame('300.00', $p['gross_profit']);  // 500 − 200
        $this->assertSame('60.00', $p['margin_pct']);     // 300/500 = 60%
        $this->assertSame(2, $p['orders_costed']);
        $this->assertSame(0, $p['orders_unknown_cost']);
    }

    public function test_orders_without_cost_are_excluded_not_deflating_profit(): void
    {
        // العيب الذي منعته المراجعة: طلب بلا تكلفة يجب ألا يدخل الحساب بإيرادٍ
        // كامل وتكلفةٍ صفر، فينتفخ الربح. يُستبعد كليًّا ويُعدّ في التنبيه.
        $this->realisedOrder(subtotal: '300.00', unitCost: '120.00'); // معروف
        $this->realisedOrder(subtotal: '999.00', unitCost: null);     // مجهول التكلفة

        [$from, $to] = $this->range();
        $p = $this->service()->profit($from, $to);

        $this->assertSame('300.00', $p['net_costed'], 'الطلب المجهول لا يدخل الصافي');
        $this->assertSame('180.00', $p['gross_profit']); // 300 − 120 فقط
        $this->assertSame(1, $p['orders_costed']);
        $this->assertSame(1, $p['orders_unknown_cost']);
    }

    public function test_profit_is_null_when_no_order_has_known_cost(): void
    {
        $this->realisedOrder(subtotal: '300.00', unitCost: null);

        [$from, $to] = $this->range();
        $p = $this->service()->profit($from, $to);

        $this->assertNull($p['gross_profit'], 'بلا تكلفة معروفة: غير متاح لا صفر');
        $this->assertNull($p['margin_pct']);
        $this->assertSame(1, $p['orders_unknown_cost']);
    }

    public function test_shipping_margin_is_charged_minus_carrier_over_known_orders(): void
    {
        // شحن مُحصَّل 50 لكلٍّ، تكلفة شركة 30 و40.
        $this->realisedOrder(subtotal: '300.00', unitCost: '120.00', carrier: '30.00');
        $this->realisedOrder(subtotal: '200.00', unitCost: '80.00', carrier: '40.00');

        [$from, $to] = $this->range();
        $sh = $this->service()->shipping($from, $to);

        $this->assertSame('100.00', $sh['shipping_charged']); // 50 + 50
        $this->assertSame('70.00', $sh['carrier_cost']);      // 30 + 40
        $this->assertSame('30.00', $sh['shipping_margin']);   // 100 − 70
        $this->assertSame(2, $sh['orders_carrier_known']);
        $this->assertSame(0, $sh['orders_carrier_unknown']);
    }

    public function test_free_shipping_with_carrier_cost_shows_negative_margin(): void
    {
        // كوبون شحن مجاني: يُحصَّل 0 والشركة تتقاضى 45 ⇒ خسارة صريحة تظهر.
        $order = OrderFactory::new()->create([
            'status' => 'completed', 'payment_method' => 'cod', 'payment_status' => 'unpaid',
            'subtotal' => '300.00', 'discount_total' => '0.00',
            'shipping_total' => '0.00', 'carrier_cost' => '45.00',
        ]);
        $book = Book::factory()->create(['price' => '300.00']);
        $order->items()->create([
            'book_id' => $book->id, 'book_title' => $book->title,
            'unit_price' => '300.00', 'unit_cost' => '120.00',
            'quantity' => 1, 'line_total' => '300.00', 'line_cost' => '120.00',
        ]);

        [$from, $to] = $this->range();
        $sh = $this->service()->shipping($from, $to);

        $this->assertSame('-45.00', $sh['shipping_margin'], 'الشحن المجاني بتكلفة شركة = خسارة');
    }

    public function test_carrier_unknown_orders_are_excluded_and_counted(): void
    {
        $this->realisedOrder(subtotal: '300.00', unitCost: '120.00', carrier: '30.00'); // معروف
        $this->realisedOrder(subtotal: '200.00', unitCost: '80.00', carrier: null);     // مجهول

        [$from, $to] = $this->range();
        $sh = $this->service()->shipping($from, $to);

        $this->assertSame('30.00', $sh['carrier_cost'], 'المجهول لا يدخل');
        $this->assertSame(1, $sh['orders_carrier_known']);
        $this->assertSame(1, $sh['orders_carrier_unknown']);
    }

    public function test_contribution_is_over_the_strict_intersection(): void
    {
        // مكتمل البيانات: تكلفة + تكلفة شحن معروفتان (الشحن المحصَّل 50 من realisedOrder).
        $this->realisedOrder(subtotal: '300.00', unitCost: '120.00', carrier: '30.00');
        // ناقص تكلفة الشحن ⇒ خارج المساهمة رغم معرفة تكلفته.
        $this->realisedOrder(subtotal: '500.00', unitCost: '200.00', carrier: null);

        [$from, $to] = $this->range();
        $sh = $this->service()->shipping($from, $to);

        // المساهمة على الطلب الأول فقط: (صافي 300 + شحن محصَّل 50) − تكلفة 120 − شركة 30 = 200.
        $this->assertSame('200.00', $sh['contribution']);
        $this->assertSame(1, $sh['orders_contribution']);
    }

    public function test_contribution_equals_gross_profit_plus_shipping_margin(): void
    {
        // اتساق داخلي (إصلاح المراجعة العدائية): المساهمة يجب أن تساوي مجمل الربح
        // زائد هامش الشحن على نفس المجموعة — لا أن تُسقط إيراد الشحن بينما تطرح تكلفته.
        $this->realisedOrder(subtotal: '300.00', unitCost: '120.00', carrier: '30.00');

        [$from, $to] = $this->range();
        $p = $this->service()->profit($from, $to);
        $sh = $this->service()->shipping($from, $to);

        $expected = bcadd((string) $p['gross_profit'], (string) $sh['shipping_margin'], 2);
        $this->assertSame($expected, $sh['contribution'], 'المساهمة = مجمل الربح + هامش الشحن');
    }
}
