<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\Order;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

/**
 * القسم ١ من لوحة العمليات — نظرة عامة (المرحلة ١ MVP).
 *
 * كل رقم مُشتقّ من عمود حقيقي، ويعمل من أوّل طلب (لقطات لحظية لا متوسّطات تحتاج تراكمًا).
 * الماليات (إيراد/متوسّط الطلب) محجوبة خلف صلاحية orders.view_financials فلا يراها
 * موظّف الشحن/الدعم (الدستور 4.4). التحديث الدوري 30 ثانية ليبقى «المتصفّحون الآن» حيًّا.
 */
class OverviewStatsWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected static ?string $pollingInterval = '30s';

    protected function getStats(): array
    {
        // «المتصفّحون الآن»: جلسات نشِطة خلال آخر 5 دقائق. SESSION_DRIVER=database،
        // و sessions.last_activity طابع UNIX صحيح (يُحدَّث في كل طلب). يشمل زوّار
        // المتجر ولوحة الأدمن معًا؛ user_id فارغ = زائر غير مسجَّل.
        $activeSince = now()->subMinutes(5)->getTimestamp();
        $liveTotal = DB::table('sessions')->where('last_activity', '>=', $activeSince)->count();
        $liveGuests = DB::table('sessions')
            ->where('last_activity', '>=', $activeSince)
            ->whereNull('user_id')
            ->count();

        $today = Order::whereDate('created_at', today())->count();
        $week = Order::where('created_at', '>=', now()->subDays(7))->count();
        $month = Order::where('created_at', '>=', now()->subDays(30))->count();

        $stats = [
            Stat::make('المتصفّحون الآن', $liveTotal)
                ->description($liveGuests.' زائر غير مسجَّل · آخر 5 دقائق')
                ->descriptionIcon('heroicon-m-signal')
                ->color($liveTotal > 0 ? 'success' : 'gray'),

            Stat::make('طلبات اليوم', $today)
                ->description('هذا الأسبوع: '.$week)
                ->descriptionIcon('heroicon-m-shopping-bag')
                ->color('primary'),

            Stat::make('طلبات آخر 30 يومًا', $month)
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color('primary'),
        ];

        // الماليات: للمالك ومن يملك orders.view_financials فقط (super_admin يمرّ عبر
        // Gate::before). الإيراد «المحقَّق» = الطلبات المسلَّمة/المكتملة فقط، لا المعلّقة.
        if (auth()->user()?->can('orders.view_financials')) {
            $realized = Order::query()
                ->whereIn('status', ['delivered', 'completed'])
                ->where('created_at', '>=', now()->subDays(30));

            $count = (clone $realized)->count();
            $revenue = (float) (clone $realized)->sum('grand_total');
            $aov = $count > 0 ? $revenue / $count : 0.0;

            $stats[] = Stat::make('الإيراد المحقَّق (30 يومًا)', number_format($revenue).' ج.م')
                ->description('من الطلبات المسلَّمة/المكتملة')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success');

            $stats[] = Stat::make('متوسّط قيمة الطلب', number_format($aov).' ج.م')
                ->description($count.' طلبًا محقَّقًا')
                ->descriptionIcon('heroicon-m-calculator')
                ->color('success');
        }

        return $stats;
    }
}
