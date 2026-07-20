<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\CachesDashboardData;
use App\Filament\Widgets\Concerns\ScopesRevenue;
use App\Models\Order;
use Filament\Widgets\ChartWidget;

/**
 * الطلب الشهري — يكشف الموسمية من بيانات المتجر نفسها (لا من التوجيه العام في
 * SeasonalGuideWidget). آخر 12 شهرًا: عدد الطلبات الصالحة لكل شهر. يعرض ما هو متاح
 * الآن (قد يكون شهرًا أو اثنين) ويزداد وضوحًا مع تراكم الأشهر حتى تظهر الأنماط
 * الموسمية الفعلية. عدد الطلبات (لا الإيراد) فالمؤشّر غير مالي — مرئيّ لمن يملك
 * orders.view. مخزَّن 5 دقائق كبقية اللوحة.
 */
class MonthlyDemandChartWidget extends ChartWidget
{
    use CachesDashboardData;
    use ScopesRevenue;

    protected static ?int $sort = 7;

    protected static ?string $heading = 'الطلب الشهري — تكشف الموسمية مع تراكم الأشهر';

    protected static ?string $maxHeight = '260px';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return (bool) auth()->user()?->can('orders.view');
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $rows = collect(static::rememberDashboard('monthly_demand', function (): array {
            return static::scopeRevenueOrders(Order::query())
                ->where('created_at', '>=', now()->subMonths(11)->startOfMonth())
                ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as ym, COUNT(*) as orders")
                ->groupBy('ym')
                ->orderBy('ym')
                ->get()
                ->all();
        }, []));

        return [
            'datasets' => [[
                'label' => 'عدد الطلبات',
                'data' => $rows->pluck('orders')->map(static fn ($v): int => (int) $v)->all(),
                'backgroundColor' => '#8B5CF6',
                'borderRadius' => 6,
            ]],
            'labels' => $rows->pluck('ym')->map(static fn ($ym): string => static::monthLabel((string) $ym))->all(),
        ];
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => ['legend' => ['display' => false]],
            'scales' => ['y' => ['beginAtZero' => true, 'ticks' => ['precision' => 0]]],
        ];
    }

    /** «2026-07» → «يوليو 26». */
    private static function monthLabel(string $ym): string
    {
        $names = ['', 'يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو',
            'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر'];
        [$year, $month] = array_pad(explode('-', $ym), 2, '1');

        return ($names[(int) $month] ?? $ym).' '.substr($year, -2);
    }
}
