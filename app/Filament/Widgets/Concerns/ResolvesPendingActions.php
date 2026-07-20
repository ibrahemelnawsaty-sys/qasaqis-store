<?php

declare(strict_types=1);

namespace App\Filament\Widgets\Concerns;

use App\Models\Order;
use Illuminate\Database\Eloquent\Builder;

/**
 * The single definition of «what still needs the owner's attention», shared by
 * TodayOverviewWidget (the counter) and PendingActionsWidget (the list).
 *
 * Why one place: the stat card and the table sit on the same screen. If the
 * counter said 5 and the table showed 3, the dashboard would be worse than no
 * dashboard. Both read this trait, so the definitions cannot drift apart.
 *
 * Every column touched here is indexed by create_orders_table (status,
 * payment_status, created_at), so the scan behind the cache is cheap even before
 * the five-minute TTL absorbs it.
 *
 * Low stock is the third thing needing attention; it lives in ResolvesLowStock
 * because it queries books, not orders, and TopBooksWidget needs it too.
 */
trait ResolvesPendingActions
{
    use CachesDashboardData;

    /** ساعات مرور طلب «pending» بلا تأكيد قبل اعتباره متأخرًا ويحتاج تدخّلًا. */
    public const STALE_PENDING_HOURS = 24;

    /**
     * How many rows the pending-actions table previews. Shared so the stat card
     * can honestly say «تُعرض أقدم N» with the number the table actually shows.
     * A dashboard list is a call to action, not a work queue — past ~8 rows the
     * owner should be in the filtered orders list instead.
     */
    public const PENDING_PREVIEW_LIMIT = 8;

    /**
     * Statuses in which a manual-payment proof no longer needs reviewing: the
     * order is already closed, so approving its transfer would be meaningless.
     * Values copied verbatim from orders.status enum (create_orders_table).
     */
    protected const CLOSED_STATUSES = ['cancelled', 'refused', 'refunded'];

    /**
     * Orders needing a human decision right now.
     *
     * (أ) payment_status = pending_review — a manual transfer proof is waiting.
     *     Same predicate as OrderResource::getNavigationBadge() so the sidebar
     *     badge and this dashboard never disagree, minus orders already closed.
     * (ب) status = pending older than STALE_PENDING_HOURS — placed but never
     *     confirmed, i.e. a customer left hanging.
     *
     * SoftDeletes is applied automatically by the Order model's global scope.
     */
    protected static function pendingActionsQuery(): Builder
    {
        $staleBefore = now()->subHours(static::STALE_PENDING_HOURS);

        return Order::query()->where(
            static function (Builder $query) use ($staleBefore): void {
                $query
                    ->where(static function (Builder $awaitingProof): void {
                        $awaitingProof
                            ->where('payment_status', 'pending_review')
                            ->whereNotIn('status', static::CLOSED_STATUSES);
                    })
                    ->orWhere(static function (Builder $stale) use ($staleBefore): void {
                        $stale
                            ->where('status', 'pending')
                            ->where('created_at', '<=', $staleBefore);
                    });
            }
        );
    }

    /** إجمالي ما ينتظر إجراءً — الرقم المعروض في بطاقة الإحصاء. */
    protected static function pendingActionsCount(): int
    {
        return static::rememberDashboard(
            'pending.count',
            static fn (): int => static::pendingActionsQuery()->count(),
            0,
        );
    }

    /** كم منها إثبات دفع بانتظار المراجعة — تفصيل يوضّح نوع التدخّل المطلوب. */
    protected static function pendingProofsCount(): int
    {
        return static::rememberDashboard(
            'pending.proofs.count',
            static fn (): int => Order::query()
                ->where('payment_status', 'pending_review')
                ->whereNotIn('status', static::CLOSED_STATUSES)
                ->count(),
            0,
        );
    }

    /**
     * The most urgent (oldest) pending-action order ids.
     *
     * Only ids are cached: the widget re-reads the rows through a primary-key
     * lookup so Filament gets the live Eloquent Builder its table layer requires
     * (vendor/filament/tables/src/Concerns/HasRecords.php::getTableRecords), while
     * the scan that finds them stays behind the five-minute cache.
     *
     * @return array<int, int>
     */
    protected static function pendingActionIds(int $limit): array
    {
        return static::rememberDashboard(
            'pending.ids.'.$limit,
            static fn (): array => static::pendingActionsQuery()
                ->orderBy('created_at')
                ->limit($limit)
                ->pluck('id')
                ->all(),
            [],
        );
    }

    /**
     * Why this order is on the list — derived in PHP from columns already loaded,
     * so it costs no extra query. Proof review wins when an order matches both
     * rules: money waiting on a decision outranks an unconfirmed order.
     *
     * @return array{label: string, color: string}
     */
    protected static function pendingActionReason(Order $order): array
    {
        if ($order->payment_status === 'pending_review') {
            return ['label' => 'إثبات دفع بانتظار المراجعة', 'color' => 'warning'];
        }

        return ['label' => 'طلب معلّق بلا تأكيد', 'color' => 'danger'];
    }
}
