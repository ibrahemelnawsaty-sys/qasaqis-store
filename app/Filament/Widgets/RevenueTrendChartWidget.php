<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\CachesDashboardData;
use App\Filament\Widgets\Concerns\ScopesRevenue;
use App\Models\Order;
use App\Support\Money;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

/**
 * Thirty days of revenue as one line — the shape of the business, not a table
 * of numbers. Answers «هل نصعد أم نهبط؟» at a glance, which is the only
 * question a trend chart should have to answer.
 *
 * Gated on «orders.view_financials» like the other money widgets, enforced by
 * Filament server-side (Pages\Page::filterVisibleWidgets + the 403 abort in
 * Widgets\Concerns\CanAuthorizeAccess) — constitution 4.4.
 *
 * Revenue is defined once in ScopesRevenue so this line and the «إيراد اليوم»
 * stat can never disagree.
 */
class RevenueTrendChartWidget extends ChartWidget
{
    use CachesDashboardData;
    use ScopesRevenue;

    /** Deferred so the chart never delays the first paint of the dashboard. */
    protected static bool $isLazy = true;

    protected static ?int $sort = 2;

    protected static ?string $heading = 'اتجاه الإيراد — آخر 30 يومًا';

    protected static ?string $description = 'إجمالي الطلبات يوميًا بالجنيه، دون الملغاة والمرفوضة.';

    /** Brand purple (AdminPanelProvider::panel → primary #6E2FB0). */
    protected static string $color = 'primary';

    /** Keeps the dashboard scannable: a trend line does not need half a screen. */
    protected static ?string $maxHeight = '260px';

    public static function canView(): bool
    {
        return auth()->user()?->can('orders.view_financials') ?? false;
    }

    protected function getType(): string
    {
        return 'line';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        $series = static::dailyRevenueSeries();

        return [
            'datasets' => [
                [
                    'label' => 'الإيراد ('.__('common.currency').')',
                    // Chart.js consumes JSON numbers, so the exact DECIMAL string
                    // is cast at this presentation boundary only — every sum above
                    // it was computed by MySQL in DECIMAL (constitution 3.5).
                    'data' => array_map(static fn (string $amount): float => (float) $amount, array_values($series)),
                    'fill' => 'start',
                ],
            ],
            'labels' => array_map(
                static fn (string $date): string => Carbon::parse($date)->format('d/m'),
                array_keys($series),
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getOptions(): array
    {
        return [
            // One dataset — a legend would only repeat the heading.
            'plugins' => ['legend' => ['display' => false]],
            'scales' => ['y' => ['beginAtZero' => true]],
            // A day with no orders is a real zero, not a gap to interpolate over.
            'elements' => ['line' => ['tension' => 0.3]],
        ];
    }

    /**
     * Revenue per day for the trend window, zero-filled.
     *
     * One grouped query, not thirty: the WHERE uses the created_at index and the
     * GROUP BY collapses at most 30 buckets. Days with no orders are absent from
     * the result set, so PHP fills them with 0.00 — otherwise the line would skip
     * quiet days and imply a shorter, busier month than actually happened.
     *
     * @return array<string, string> date (Y-m-d) => exact decimal amount
     */
    protected static function dailyRevenueSeries(): array
    {
        $skeleton = static::zeroFilledWindow();

        return static::rememberDashboard(
            'revenue.trend.'.self::TREND_DAYS,
            static function () use ($skeleton): array {
                $rows = static::scopeRevenueOrders(Order::query())
                    ->selectRaw('DATE(created_at) as bucket, SUM(grand_total) as revenue')
                    ->where('created_at', '>=', static::trendWindowStart())
                    ->where('created_at', '<', Carbon::today()->addDay())
                    ->groupByRaw('DATE(created_at)')
                    ->get();

                $series = $skeleton;

                foreach ($rows as $row) {
                    $bucket = (string) $row->bucket;

                    if (array_key_exists($bucket, $series)) {
                        $series[$bucket] = Money::normalize($row->revenue);
                    }
                }

                return $series;
            },
            $skeleton,
        );
    }

    /**
     * @return array<string, string>
     */
    private static function zeroFilledWindow(): array
    {
        $day = static::trendWindowStart();
        $window = [];

        for ($i = 0; $i < self::TREND_DAYS; $i++) {
            $window[$day->toDateString()] = Money::ZERO;
            $day = $day->copy()->addDay();
        }

        return $window;
    }
}
