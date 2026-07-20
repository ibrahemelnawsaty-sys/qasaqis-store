<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\Book;
use App\Models\Expense;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Finance\FinanceReportService;
use Carbon\CarbonImmutable;
use Database\Factories\OrderFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * صافي ربح النشاط (المرحلة ٤): المساهمة − مرتجعات − رسوم − مصروفات.
 *
 * كل خصم يُجمَّع من مصدره بشفافية، والقيم المجهولة (NULL) تُعامَل صفرًا لأنها
 * خصومات (غياب رسمٍ = لا رسم)، بخلاف التكلفة التي يُستبعد مجهولها من الربح.
 *
 * HONESTY (1.3/1.5): يُشغَّل على MySQL + bcmath عبر php artisan test.
 */
final class FinanceNetProfitTest extends TestCase
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
     * طلب محقّق مكتمل البيانات (تكلفة + تكلفة شحن) لينتج مساهمة معلومة.
     * الشحن المحصَّل 50 من الافتراض. يعيد الطلب لإضافة مرتجع/دفعة عليه.
     */
    private function completeOrder(string $subtotal, string $unitCost, string $carrier): Order
    {
        $order = OrderFactory::new()->create([
            'status' => 'delivered', 'payment_method' => 'cod', 'payment_status' => 'unpaid',
            'subtotal' => $subtotal, 'discount_total' => '0.00',
            'shipping_total' => '50.00', 'carrier_cost' => $carrier, 'grand_total' => $subtotal,
        ]);
        $book = Book::factory()->create(['price' => $subtotal]);
        $order->items()->create([
            'book_id' => $book->id, 'book_title' => $book->title,
            'unit_price' => $subtotal, 'unit_cost' => $unitCost,
            'quantity' => 1, 'line_total' => $subtotal, 'line_cost' => $unitCost,
        ]);

        return $order;
    }

    public function test_net_profit_subtracts_refunds_fees_and_expenses_from_contribution(): void
    {
        // مساهمة الطلب = (300 + 50) − 120 − 30 = 200.
        $order = $this->completeOrder(subtotal: '300.00', unitCost: '120.00', carrier: '30.00');
        $order->forceFill(['refunded_amount' => '40.00', 'refunded_at' => now()])->save();
        Payment::create([
            'order_id' => $order->id, 'payment_method_code' => 'cod',
            'amount' => '300.00', 'fee_amount' => '10.00', 'status' => 'completed',
        ]);
        Expense::factory()->create(['amount' => '25.00', 'incurred_on' => now()->toDateString()]);

        [$from, $to] = $this->range();
        $np = $this->service()->netProfit($from, $to);

        $this->assertSame('40.00', $np['refunds']);
        $this->assertSame('10.00', $np['fees']);
        $this->assertSame('25.00', $np['expenses']);
        $this->assertSame('200.00', $np['contribution']);
        // 200 − 40 − 10 − 25 = 125.
        $this->assertSame('125.00', $np['net_profit']);
    }

    public function test_refunds_of_incomplete_orders_are_not_deducted_from_a_complete_orders_profit(): void
    {
        // عيب أمسكته المراجعة: كان يُخصم مرتجع طلبٍ استُبعد ربحه (تكلفة شحن لم تصل)
        // من مساهمةٍ لا تشمله، فينقص صافي الربح. الآن المرتجعات على نفس مجموعة
        // التقاطع فقط.
        // طلب مكتمل: مساهمة = (300+50) − 120 − 30 = 200.
        $this->completeOrder(subtotal: '300.00', unitCost: '120.00', carrier: '30.00');
        // طلب محقّق ومعروف التكلفة لكن بلا تكلفة شحن (خارج التقاطع)، وعليه مرتجع.
        $incomplete = $this->completeOrder(subtotal: '500.00', unitCost: '200.00', carrier: '10.00');
        $incomplete->forceFill(['carrier_cost' => null, 'refunded_amount' => '80.00', 'refunded_at' => now()])->save();

        [$from, $to] = $this->range();
        $np = $this->service()->netProfit($from, $to);

        // مرتجع الطلب خارج التقاطع (80) لا يُخصم، فصافي الربح يساوي مساهمة المكتمل.
        $this->assertSame('0.00', $np['refunds'], 'مرتجع طلب خارج التقاطع مستبعد');
        $this->assertSame('200.00', $np['contribution']);
        $this->assertSame('200.00', $np['net_profit']);
    }

    public function test_net_profit_is_null_when_contribution_is_unknown(): void
    {
        // طلب بلا تكلفة شحن ⇒ لا مساهمة ⇒ لا صافي ربح (لا رقم مبنيّ على مجهول).
        $this->completeOrder(subtotal: '300.00', unitCost: '120.00', carrier: '0.00');
        $order = Order::query()->first();
        $order->forceFill(['carrier_cost' => null])->save();

        Expense::factory()->create(['amount' => '100.00', 'incurred_on' => now()->toDateString()]);

        [$from, $to] = $this->range();
        $np = $this->service()->netProfit($from, $to);

        $this->assertNull($np['contribution']);
        $this->assertNull($np['net_profit']);
        // المصروفات تظل مجمّعة ومعروضة حتى لو تعذّر صافي الربح.
        $this->assertSame('100.00', $np['expenses']);
    }

    public function test_expenses_are_bucketed_by_incurred_date_within_range(): void
    {
        Expense::factory()->create(['amount' => '100.00', 'incurred_on' => now()->toDateString()]);
        // مصروف خارج النطاق (قبل ٣٠ يومًا) — يجب ألا يُحتسب.
        Expense::factory()->create(['amount' => '999.00', 'incurred_on' => now()->subDays(30)->toDateString()]);

        [$from, $to] = $this->range();
        $np = $this->service()->netProfit($from, $to);

        $this->assertSame('100.00', $np['expenses'], 'المصروف خارج النطاق مستبعد');
    }

    public function test_fees_only_count_for_realised_orders(): void
    {
        $realised = $this->completeOrder(subtotal: '300.00', unitCost: '120.00', carrier: '30.00');
        Payment::create([
            'order_id' => $realised->id, 'payment_method_code' => 'cod',
            'amount' => '300.00', 'fee_amount' => '10.00', 'status' => 'completed',
        ]);

        // طلب غير محقّق (معلّق) بدفعة ذات رسوم — يجب ألا تُحتسب رسومه.
        $pending = OrderFactory::new()->create(['status' => 'pending', 'payment_status' => 'unpaid']);
        Payment::create([
            'order_id' => $pending->id, 'payment_method_code' => 'online_gateway',
            'amount' => '500.00', 'fee_amount' => '99.00', 'status' => 'pending',
        ]);

        [$from, $to] = $this->range();
        $np = $this->service()->netProfit($from, $to);

        $this->assertSame('10.00', $np['fees'], 'رسوم الطلب غير المحقّق مستبعدة');
    }

    public function test_refunds_only_count_for_realised_orders(): void
    {
        $realised = $this->completeOrder(subtotal: '300.00', unitCost: '120.00', carrier: '30.00');
        $realised->forceFill(['refunded_amount' => '40.00', 'refunded_at' => now()])->save();

        // طلب معلّق بمرتجع — غير محقّق، مستبعد.
        OrderFactory::new()->create([
            'status' => 'pending', 'payment_status' => 'unpaid',
            'refunded_amount' => '77.00', 'refunded_at' => now(),
        ]);

        [$from, $to] = $this->range();
        $np = $this->service()->netProfit($from, $to);

        $this->assertSame('40.00', $np['refunds']);
    }

    public function test_adding_an_expense_invalidates_the_cached_report(): void
    {
        // عيب أمسكته المراجعة: المصروف مصدر مستقل لا يمرّ بـ OrderObserver، فبلا
        // ExpenseObserver يبقى «المصروفات» قديمًا حتى انتهاء الكاش. نتحقق أن
        // الإضافة تُبطل الكاش فورًا.
        [$from, $to] = $this->range();
        $this->assertSame('0.00', $this->service()->netProfit($from, $to)['expenses']); // يملأ الكاش

        Expense::factory()->create(['amount' => '250.00', 'incurred_on' => now()->toDateString()]);

        // بلا إبطال لبقيت 0.00 من الكاش؛ المُراقب يُبطلها فتظهر القيمة الجديدة.
        $this->assertSame('250.00', $this->service()->netProfit($from, $to)['expenses']);
    }
}
