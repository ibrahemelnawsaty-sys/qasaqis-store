<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\CachesDashboardData;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

/**
 * توقيت التنفيذ — متوسّط زمن الطلب من الإنشاء إلى كل مرحلة (تأكيد/شحن/تسليم)، من
 * order_status_histories (يسجّل OrderObserver كل انتقال حالة بطابع زمني). يقيس
 * انضباط العملية.
 *
 * زمن الوصول لكل مرحلة = أوّل انتقال إليها ناقص orders.created_at (MIN CASE WHEN —
 * SQL قياسي بلا دوال نوافذ). يعرض عدد العيّنة n بجوار كل رقم بشفافية: يظهر ما هو
 * متاح الآن ويصير أدقّ مع تراكم الطلبات. مرئيّ لمن يملك orders.view، مخزَّن 5 دقائق.
 */
class FulfillmentTimingWidget extends BaseWidget
{
    use CachesDashboardData;

    protected static ?int $sort = 8;

    public static function canView(): bool
    {
        return (bool) auth()->user()?->can('orders.view');
    }

    protected function getStats(): array
    {
        $t = static::rememberDashboard('fulfillment_timing', function (): array {
            $milestones = DB::table('order_status_histories')
                ->groupBy('order_id')
                ->selectRaw(
                    'order_id,'
                    ." MIN(CASE WHEN to_status = 'confirmed' THEN created_at END) as confirmed_at,"
                    ." MIN(CASE WHEN to_status = 'shipped' THEN created_at END) as shipped_at,"
                    ." MIN(CASE WHEN to_status IN ('delivered', 'completed') THEN created_at END) as delivered_at"
                );

            $row = DB::query()
                ->fromSub($milestones, 'm')
                ->join('orders as o', 'o.id', '=', 'm.order_id')
                ->selectRaw(
                    'AVG(TIMESTAMPDIFF(MINUTE, o.created_at, m.confirmed_at)) as min_confirmed,'
                    .' COUNT(m.confirmed_at) as n_confirmed,'
                    .' AVG(TIMESTAMPDIFF(MINUTE, o.created_at, m.shipped_at)) as min_shipped,'
                    .' COUNT(m.shipped_at) as n_shipped,'
                    .' AVG(TIMESTAMPDIFF(MINUTE, o.created_at, m.delivered_at)) as min_delivered,'
                    .' COUNT(m.delivered_at) as n_delivered'
                )
                ->first();

            return (array) ($row ?? []);
        }, []);

        return [
            $this->timingStat('من الطلب إلى التأكيد', $t['min_confirmed'] ?? null, (int) ($t['n_confirmed'] ?? 0), 'heroicon-m-phone'),
            $this->timingStat('من الطلب إلى الشحن', $t['min_shipped'] ?? null, (int) ($t['n_shipped'] ?? 0), 'heroicon-m-truck'),
            $this->timingStat('من الطلب إلى التسليم', $t['min_delivered'] ?? null, (int) ($t['n_delivered'] ?? 0), 'heroicon-m-check-badge'),
        ];
    }

    private function timingStat(string $label, mixed $minutes, int $n, string $icon): Stat
    {
        if ($n < 1 || $minutes === null) {
            return Stat::make($label, '—')
                ->description('لا بيانات كافية بعد')
                ->descriptionIcon($icon)
                ->color('gray');
        }

        $hours = (float) $minutes / 60;
        $value = $hours < 1
            ? round((float) $minutes).' دقيقة'
            : round($hours, 1).' ساعة';

        return Stat::make($label, $value)
            ->description('متوسّط '.$n.' طلب')
            ->descriptionIcon($icon)
            ->color('primary');
    }
}
