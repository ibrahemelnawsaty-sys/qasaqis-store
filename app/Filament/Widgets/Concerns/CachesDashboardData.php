<?php

declare(strict_types=1);

namespace App\Filament\Widgets\Concerns;

use Closure;
use Illuminate\Support\Facades\Cache;

/**
 * Five-minute read-through cache shared by every /admin dashboard widget
 * (constitution 5.4).
 *
 * Why: the dashboard renders four widgets at once. Without a cache, every panel
 * load would re-run every aggregate — the panel would open slower than the shop
 * it manages. Five minutes is the agreed staleness budget: an owner refreshing
 * to watch an order land still sees it within one cycle, and the aggregates are
 * rolling time windows, not CMS lists, so they expire on TTL rather than on
 * write (there is no admin action that must invalidate them synchronously).
 *
 * Why rescue() wraps Cache::remember and not the other way round: the configured
 * store is `database` (config/cache.php → CACHE_STORE=database), so a DB outage
 * takes the cache down together with the data. Wrapping the whole call means a
 * broken connection renders an empty widget and logs the throwable, instead of
 * 500-ing the entire panel. Only successful reads are cached — the fallback is
 * never written, so recovery is immediate.
 *
 * NOT a general-purpose helper: keys are namespaced under a single prefix and
 * carry no per-user component, so nothing user-scoped may be cached here.
 */
trait CachesDashboardData
{
    /** بند 5.4 — خمس دقائق لكل استعلام لوحة. */
    protected const DASHBOARD_CACHE_TTL = 300;

    protected const DASHBOARD_CACHE_PREFIX = 'admin.dashboard.';

    /**
     * Remember a dashboard aggregate, degrading to $default on any failure.
     *
     * @template TValue
     *
     * @param  Closure(): TValue  $callback
     * @param  TValue  $default
     * @return TValue
     */
    protected static function rememberDashboard(string $key, Closure $callback, mixed $default): mixed
    {
        return rescue(
            fn (): mixed => Cache::remember(
                static::DASHBOARD_CACHE_PREFIX.$key,
                static::DASHBOARD_CACHE_TTL,
                $callback,
            ),
            $default,
        );
    }
}
