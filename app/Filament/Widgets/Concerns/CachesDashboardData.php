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
    /** بند 5.4 — خمس دقائق لكل استعلام لوحة (سقف التقادم؛ الإبطال الفوري أدناه). */
    protected const DASHBOARD_CACHE_TTL = 300;

    protected const DASHBOARD_CACHE_PREFIX = 'admin.dashboard.';

    /** مفتاح إصدار الكاش — رفعُه يُبطِل كلَّ مفاتيح اللوحة فورًا بلا وسوم. */
    protected const DASHBOARD_CACHE_VERSION_KEY = 'admin.dashboard.version';

    /** إصدار محفوظ لكل طلب — يُقرأ مرّة واحدة لا لكل استعلام لوحة. */
    private static ?int $dashboardVersionMemo = null;

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
                static::dashboardCacheKey($key),
                static::DASHBOARD_CACHE_TTL,
                $callback,
            ),
            $default,
        );
    }

    /**
     * إبطال كل كاش اللوحة فورًا برفع الإصدار — يُستدعى عند تغيّر مصدر البيانات
     * (حفظ/حذف طلب) كي تظهر التغييرات فور تحديث الصفحة بدل انتظار انتهاء المهلة.
     * متجر كاش قاعدة البيانات لا يدعم الوسوم، فبصمةُ إصدارٍ في المفتاح هي البديل؛
     * المفاتيح القديمة تنتهي بمهلتها. rescue: عطل الكاش لا يُسقِط عملية الحفظ.
     */
    public static function flushDashboardCache(): void
    {
        static::$dashboardVersionMemo = null;

        rescue(function (): void {
            if (Cache::increment(static::DASHBOARD_CACHE_VERSION_KEY) === false) {
                Cache::forever(static::DASHBOARD_CACHE_VERSION_KEY, 2);
            }
        });
    }

    /** المفتاح مضمومًا إليه بصمة الإصدار الحالية. */
    protected static function dashboardCacheKey(string $key): string
    {
        return static::DASHBOARD_CACHE_PREFIX.static::dashboardCacheVersion().'.'.$key;
    }

    protected static function dashboardCacheVersion(): int
    {
        return static::$dashboardVersionMemo ??= rescue(fn (): int => (int) Cache::get(static::DASHBOARD_CACHE_VERSION_KEY, 1), 1);
    }
}
