<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

/**
 * «كم شخصًا يفتح المنصّة الآن» — مؤشّر حيّ للحضور اللحظي.
 *
 * الجلسات النشِطة خلال آخر 5 دقائق من جدول sessions (SESSION_DRIVER=database،
 * و last_activity طابع UNIX يُحدَّث في كل طلب) — بلا أيّ أداة تحليلات خارجية.
 * يشمل زوّار المتجر ولوحة الأدمن؛ user_id فارغ = زائر غير مسجَّل. تحديث دوري
 * كل 30 ثانية ليبقى حيًّا.
 *
 * مقصور على مؤشّر الحضور فقط: عدّادات الطلبات والماليات يوفّرها TodayOverviewWidget،
 * فلا نكرّرها. مرئيّ لأيّ مستخدم بلوحة (حضور تشغيلي غير حسّاس، لا مالي).
 */
class LiveVisitorsWidget extends BaseWidget
{
    protected static ?int $sort = 0;

    protected static ?string $pollingInterval = '30s';

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $activeSince = now()->subMinutes(5)->getTimestamp();

        $base = DB::table('sessions')->where('last_activity', '>=', $activeSince);
        $total = (clone $base)->count();
        $guests = (clone $base)->whereNull('user_id')->count();
        $members = $total - $guests;

        return [
            Stat::make('المتصفّحون الآن', $total)
                ->description($guests.' زائر · '.$members.' مسجَّل · آخر 5 دقائق')
                ->descriptionIcon('heroicon-m-signal')
                ->color($total > 0 ? 'success' : 'gray'),
        ];
    }
}
