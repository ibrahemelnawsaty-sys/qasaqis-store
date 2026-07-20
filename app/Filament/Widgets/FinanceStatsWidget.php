<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Services\Finance\FinanceReportService;
use Carbon\CarbonImmutable;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * مؤشرات القسم المالي (المرحلة ١ — الإيراد فقط). يقرأ نطاق التاريخ من فلاتر
 * الصفحة (InteractsWithPageFilters) ويعرض ملخّص FinanceReportService.
 *
 * الأمان (الدستور 4.4): الويدجت يفوّض إذنه مستقلًّا عبر canView — حماية الصفحة
 * وحدها لا تكفي، إذ يمكن استدعاء الويدجت في سياق آخر. أي ويدجت مالي بلا هذا
 * الفحص يسرّب الأرقام.
 */
class FinanceStatsWidget extends StatsOverviewWidget
{
    use InteractsWithPageFilters;

    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return (bool) auth()->user()?->can('orders.view_financials');
    }

    protected function getStats(): array
    {
        [$from, $to] = $this->resolveRange();
        $s = app(FinanceReportService::class)->summary($from, $to);

        $egp = static fn (string $v): string => number_format((float) $v, 2).' ج.م';

        return [
            Stat::make('صافي المبيعات', $egp($s['net_sales']))
                ->description('إجمالي '.$egp($s['gross_revenue']).' − خصومات '.$egp($s['discounts']))
                ->color('success'),

            Stat::make('عدد الطلبات', number_format($s['orders']))
                ->description(number_format($s['units']).' وحدة مباعة')
                ->color('primary'),

            Stat::make('متوسط قيمة الطلب', $s['aov'] === null ? 'غير محدد' : $egp($s['aov']))
                ->description('صافي المبيعات ÷ الطلبات')
                ->color('gray'),

            Stat::make('الشحن المُحصَّل', $egp($s['shipping']))
                ->description('من العملاء — تكلفة الشركة تُضاف لاحقًا')
                ->color('gray'),

            Stat::make('قيد التحصيل', $egp($s['pipeline']))
                ->description('طلبات مؤكّدة لم تُحقَّق بعد — خارج الإيراد')
                ->color('warning'),
        ];
    }

    /**
     * نطاق التاريخ من فلاتر الصفحة، مع افتراض آخر ٣٠ يومًا حين لا فلتر.
     *
     * @return array{0:CarbonImmutable, 1:CarbonImmutable}
     */
    private function resolveRange(): array
    {
        return FinanceRange::fromFilters($this->filters);
    }
}
