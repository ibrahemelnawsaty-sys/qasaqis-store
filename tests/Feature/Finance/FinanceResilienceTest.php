<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Services\Finance\FinanceReportService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * القسم المالي يجب ألا يُسقط اللوحة (500) إن غاب جزء من المخطط — قبل تنفيذ
 * الهجرات على الإنتاج مثلًا. يتدرّج لأرقام فارغة بدل الانهيار (الدستور 1.4/1.6).
 */
final class FinanceResilienceTest extends TestCase
{
    use RefreshDatabase;

    private function range(): array
    {
        $now = CarbonImmutable::now('Africa/Cairo');

        return [$now->subDays(30), $now];
    }

    public function test_missing_expenses_table_degrades_instead_of_500(): void
    {
        Schema::dropIfExists('expenses');

        [$from, $to] = $this->range();
        $np = app(FinanceReportService::class)->netProfit($from, $to);

        // يعود بأرقام آمنة لا يرمي — اللوحة تظل تُصيَّر.
        $this->assertSame('0.00', $np['expenses']);
    }

    public function test_missing_carrier_cost_column_degrades_instead_of_500(): void
    {
        Schema::table('orders', function ($t): void {
            $t->dropColumn('carrier_cost');
        });

        [$from, $to] = $this->range();
        $sh = app(FinanceReportService::class)->shipping($from, $to);

        $this->assertSame('0.00', $sh['carrier_cost']);
        $this->assertNull($sh['contribution']);
    }

    public function test_all_metrics_degrade_when_finance_schema_is_absent(): void
    {
        // محاكاة الإنتاج قبل الهجرات: نُسقط كل ما أضافته المراحل ٢-٤.
        Schema::dropIfExists('expenses');
        Schema::table('orders', fn ($t) => $t->dropColumn(['carrier_cost', 'refunded_amount', 'refunded_at']));
        Schema::table('order_items', fn ($t) => $t->dropColumn(['unit_cost', 'line_cost']));
        Schema::table('payments', fn ($t) => $t->dropColumn('fee_amount'));

        [$from, $to] = $this->range();
        $svc = app(FinanceReportService::class);

        // لا يرمي أيّها — كلها تتدرّج لأرقام آمنة فتظل اللوحة تُصيَّر.
        $this->assertSame('0.00', $svc->summary($from, $to)['net_sales']);
        $this->assertNull($svc->profit($from, $to)['gross_profit']);
        $this->assertNull($svc->shipping($from, $to)['contribution']);
        $this->assertNull($svc->netProfit($from, $to)['net_profit']);
        // السلسلة اليومية تعتمد على أعمدة المرحلة ١ فقط (subtotal/created_at)، فتبقى
        // تعمل وتُرجع أيامًا صفرية — لا تتأثر بغياب أعمدة المراحل ٢-٤.
        $daily = $svc->dailySeries($from, $to);
        $this->assertGreaterThan(0, $daily->count());
        $this->assertSame(0, $daily->first()['orders']);
    }
}
