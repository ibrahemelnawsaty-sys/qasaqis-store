<?php

declare(strict_types=1);

namespace App\Filament\Widgets\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * One definition of «revenue» for every money widget on the dashboard.
 *
 * Three widgets report earnings (today's total, the 30-day trend, per-book
 * takings). If any of them counted orders differently, their numbers would
 * contradict each other on the same screen and the owner would stop trusting
 * all three — so the rule lives here once.
 *
 * The rule: «cancelled» and «refused» never happened financially and are
 * excluded. «refunded» IS counted — the sale occurred, the money moved, and
 * hiding it would silently rewrite a day that has already been reported. A
 * refund is a separate event, not a reason to erase the order.
 */
trait ScopesRevenue
{
    /**
     * Statuses that never produced money. Copied verbatim from the orders.status
     * enum in create_orders_table — never guessed (constitution 1.1).
     */
    protected const NON_REVENUE_STATUSES = ['cancelled', 'refused'];

    /** طول نافذة الاتجاه/الأكثر مبيعًا بالأيام (شاملة اليوم الحالي). */
    protected const TREND_DAYS = 30;

    /**
     * Restrict a query to orders that count as revenue.
     *
     * $statusColumn is qualified by callers that join orders to another table,
     * where a bare «status» would be ambiguous the moment a joined table grows a
     * column of that name.
     */
    protected static function scopeRevenueOrders(Builder $query, string $statusColumn = 'status'): Builder
    {
        return $query->whereNotIn($statusColumn, self::NON_REVENUE_STATUSES);
    }

    /**
     * Start of the trend window: midnight, TREND_DAYS-1 days back, so the window
     * is inclusive of today. Resolved in the app timezone — see the timezone
     * caveat documented on TodayOverviewWidget.
     */
    protected static function trendWindowStart(): Carbon
    {
        return Carbon::today()->subDays(self::TREND_DAYS - 1);
    }
}
