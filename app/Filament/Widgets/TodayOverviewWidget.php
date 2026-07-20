<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Filament\Resources\OrderResource;
use App\Filament\Widgets\Concerns\ResolvesPendingActions;
use App\Filament\Widgets\Concerns\ScopesRevenue;
use App\Models\Order;
use App\Support\Money;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;

/**
 * The four numbers a store owner opens /admin to see: today's orders, today's
 * revenue, average order value, and how much is waiting on them.
 *
 * Gated on «orders.view_financials» (RolePermissionSeeder §3.4), not the weaker
 * «orders.view»: three of the four stats are money. Enforcement is server-side
 * twice over — Filament filters the dashboard by canView()
 * (vendor/filament/filament/src/Pages/Page.php:252) AND aborts 403 on every
 * Livewire hydration (Widgets\Concerns\CanAuthorizeAccess) — so this is a real
 * gate, not a hidden button (constitution 4.4 / anti-pattern 13).
 *
 * Revenue excludes «cancelled» and «refused»: money that was never collected is
 * not revenue. It deliberately still includes «refunded» — the sale happened and
 * the day's takings should show it — which is why refunded is only filtered out
 * of the pending-actions definition, not this one.
 *
 * TIMEZONE CAVEAT: «today» is resolved with Carbon::today(), i.e. the app
 * timezone, which config/app.php:68 hardcodes to UTC even though .env.example
 * sets APP_TIMEZONE=Africa/Cairo. Every other date in the panel (OrderResource's
 * ->dateTime() columns) renders in that same UTC, so these stats reconcile with
 * the order list. They do NOT align with the owner's Cairo midnight — the day
 * rolls over 2-3 hours late. Fixing that belongs in config/app.php, out of this
 * widget's scope; see the handoff note.
 */
class TodayOverviewWidget extends StatsOverviewWidget
{
    use ResolvesPendingActions;
    use ScopesRevenue;

    /**
     * Deferred: the dashboard paints immediately and each widget fetches after,
     * so one slow aggregate never blocks the whole panel (constitution 1.6).
     */
    protected static bool $isLazy = true;

    protected static ?int $sort = 1;

    public static function canView(): bool
    {
        return auth()->user()?->can('orders.view_financials') ?? false;
    }

    /**
     * @return array<int, Stat>
     */
    protected function getStats(): array
    {
        $totals = static::dailyTotals();
        $today = $totals['today'];
        $yesterday = $totals['yesterday'];

        $todayAov = static::averageOrderValue($today['revenue'], $today['revenue_orders']);
        $yesterdayAov = static::averageOrderValue($yesterday['revenue'], $yesterday['revenue_orders']);

        $ordersChange = static::comparison((string) $today['orders'], (string) $yesterday['orders']);
        $revenueChange = static::comparison($today['revenue'], $yesterday['revenue']);
        $aovChange = static::comparison($todayAov, $yesterdayAov);

        $pending = static::pendingActionsCount();
        $proofs = static::pendingProofsCount();

        return [
            Stat::make('طلبات اليوم', (string) $today['orders'])
                ->description($ordersChange['description'])
                ->descriptionIcon($ordersChange['icon'])
                ->descriptionColor($ordersChange['color'])
                ->icon('heroicon-m-shopping-bag')
                ->color('primary'),

            Stat::make('إيراد اليوم', static::formatMoney($today['revenue']))
                ->description($revenueChange['description'])
                ->descriptionIcon($revenueChange['icon'])
                ->descriptionColor($revenueChange['color'])
                ->icon('heroicon-m-banknotes')
                ->color('success'),

            Stat::make('متوسط قيمة الطلب', static::formatMoney($todayAov))
                ->description(
                    $today['revenue_orders'] > 0
                        ? $aovChange['description']
                        : 'لا طلبات محتسبة اليوم'
                )
                ->descriptionIcon($today['revenue_orders'] > 0 ? $aovChange['icon'] : null)
                ->descriptionColor($today['revenue_orders'] > 0 ? $aovChange['color'] : 'gray')
                ->icon('heroicon-m-calculator')
                ->color('info'),

            Stat::make('طلبات تنتظر إجراءً', (string) $pending)
                // A backlog is not a daily metric, so it gets a breakdown instead
                // of a yesterday comparison: the owner needs to know WHAT to do,
                // not whether the queue grew.
                ->description(static::backlogDescription($pending, $proofs))
                ->descriptionIcon($pending > 0 ? 'heroicon-m-bell-alert' : 'heroicon-m-check-circle')
                ->descriptionColor($pending > 0 ? 'danger' : 'success')
                ->icon('heroicon-m-bell-alert')
                ->color($pending > 0 ? 'danger' : 'gray')
                ->url(OrderResource::canViewAny() ? OrderResource::getUrl('index') : null),
        ];
    }

    /**
     * Today's and yesterday's totals in one grouped query over the indexed
     * created_at range — two rows at most, so DATE() in the GROUP BY costs
     * nothing while the WHERE still uses the index.
     *
     * SUM over DECIMAL(10,2) returns DECIMAL, which PDO hands back as a string;
     * it is kept as a string all the way to formatMoney() so no money arithmetic
     * ever touches a float (constitution 3.5 / anti-pattern 27).
     *
     * @return array{
     *     today: array{orders: int, revenue: string, revenue_orders: int},
     *     yesterday: array{orders: int, revenue: string, revenue_orders: int}
     * }
     */
    protected static function dailyTotals(): array
    {
        $empty = ['orders' => 0, 'revenue' => Money::ZERO, 'revenue_orders' => 0];

        return static::rememberDashboard(
            'daily.totals',
            static function () use ($empty): array {
                $todayStart = Carbon::today();
                $windowStart = $todayStart->copy()->subDay();
                $windowEnd = $todayStart->copy()->addDay();

                // Raw is unavoidable for a conditional aggregate; every value is
                // bound, never interpolated (constitution 2.5 / anti-pattern 8).
                $rows = Order::query()
                    ->selectRaw(
                        'DATE(created_at) as bucket,'
                        .' COUNT(*) as orders_count,'
                        .' SUM(CASE WHEN status IN (?, ?) THEN 0 ELSE grand_total END) as revenue,'
                        .' SUM(CASE WHEN status IN (?, ?) THEN 0 ELSE 1 END) as revenue_orders',
                        [...self::NON_REVENUE_STATUSES, ...self::NON_REVENUE_STATUSES],
                    )
                    ->where('created_at', '>=', $windowStart)
                    ->where('created_at', '<', $windowEnd)
                    ->groupByRaw('DATE(created_at)')
                    ->get();

                $buckets = [
                    'today' => $todayStart->toDateString(),
                    'yesterday' => $windowStart->toDateString(),
                ];

                $totals = ['today' => $empty, 'yesterday' => $empty];

                foreach ($buckets as $slot => $date) {
                    $row = $rows->firstWhere('bucket', $date);

                    if ($row === null) {
                        continue;
                    }

                    $totals[$slot] = [
                        'orders' => (int) $row->orders_count,
                        'revenue' => Money::normalize($row->revenue),
                        'revenue_orders' => (int) $row->revenue_orders,
                    ];
                }

                return $totals;
            },
            ['today' => $empty, 'yesterday' => $empty],
        );
    }

    /** المتوسط بحساب عشري (bcmath) لا float؛ صفر عند غياب طلبات محتسبة. */
    private static function averageOrderValue(string $revenue, int $orders): string
    {
        if ($orders < 1) {
            return Money::ZERO;
        }

        return bcdiv(Money::normalize($revenue), (string) $orders, Money::SCALE);
    }

    /**
     * Yesterday-over-today comparison. Both inputs are decimal strings so counts
     * and money share one code path without a float ever appearing.
     *
     * A zero baseline yields no percentage — «+∞%» would be a fabricated number
     * (constitution 1.1), so the copy says plainly that there is nothing to
     * compare against.
     *
     * @return array{description: string, icon: string|null, color: string}
     */
    private static function comparison(string $today, string $yesterday): array
    {
        $today = Money::normalize($today);
        $yesterday = Money::normalize($yesterday);

        if (bccomp($yesterday, Money::ZERO, Money::SCALE) === 0) {
            return bccomp($today, Money::ZERO, Money::SCALE) === 0
                ? ['description' => 'مثل أمس: لا نشاط', 'icon' => null, 'color' => 'gray']
                : ['description' => 'أمس بلا نشاط — لا نسبة للمقارنة', 'icon' => 'heroicon-m-arrow-trending-up', 'color' => 'success'];
        }

        $delta = bcsub($today, $yesterday, Money::SCALE);
        $direction = bccomp($delta, Money::ZERO, Money::SCALE);

        if ($direction === 0) {
            return ['description' => 'بلا تغيير عن أمس', 'icon' => 'heroicon-m-minus-small', 'color' => 'gray'];
        }

        // Truncated (not rounded) toward zero — an understated change is safer
        // to act on than an overstated one.
        $percent = ltrim(bcdiv(bcmul($delta, '100', Money::SCALE), $yesterday, 0), '-');

        return $direction > 0
            ? ['description' => "أعلى من أمس بـ {$percent}٪", 'icon' => 'heroicon-m-arrow-trending-up', 'color' => 'success']
            : ['description' => "أقل من أمس بـ {$percent}٪", 'icon' => 'heroicon-m-arrow-trending-down', 'color' => 'danger'];
    }

    /**
     * Arabic number-noun agreement: «1 إثباتات» and «3 إثبات» are both wrong, so
     * the singular is spelled out and the plural puts the numeral last — a form
     * that stays correct at any count without a pluralisation table.
     */
    private static function backlogDescription(int $pending, int $proofs): string
    {
        if ($pending === 0) {
            return 'لا شيء ينتظر إجراءً';
        }

        if ($proofs === 0) {
            return 'طلبات معلّقة بلا تأكيد';
        }

        $shown = min($pending, self::PENDING_PREVIEW_LIMIT);
        $suffix = $pending > $shown ? " — تُعرض أقدم {$shown}" : '';

        return ($proofs === 1
            ? 'منها إثبات دفع واحد للمراجعة'
            : "منها إثباتات دفع للمراجعة: {$proofs}").$suffix;
    }

    /**
     * Presentation boundary only: the amount arrives as an exact DECIMAL string
     * and every calculation above it used bcmath. The float cast happens after
     * all arithmetic, purely to group thousands for display.
     */
    private static function formatMoney(string $amount): string
    {
        return number_format((float) Money::normalize($amount), Money::SCALE).' '.__('common.currency');
    }
}
