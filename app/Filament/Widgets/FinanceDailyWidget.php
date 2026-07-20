<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Services\Finance\FinanceReportService;
use Carbon\CarbonImmutable;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\Widget;

/**
 * جدول الأداء اليومي (المرحلة ١): صف لكل يوم في النطاق، بالتاريخ واسم اليوم
 * العربي وعدد الطلبات وصافي المبيعات. أيام بلا طلبات تظهر بصفر لا بفجوة.
 *
 * الأمان (4.4): canView مستقل عن الصفحة. البيانات تُجهَّز في PHP وتُمرَّر للقالب
 * جاهزة (بلا منطق مالي في Blade).
 */
class FinanceDailyWidget extends Widget
{
    use InteractsWithPageFilters;

    protected static string $view = 'filament.widgets.finance-daily';

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    /** أسماء أيام الأسبوع بالعربية، مفهرسة بـ Carbon::dayOfWeek (0=الأحد). */
    private const AR_DAYS = ['الأحد', 'الإثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت'];

    public static function canView(): bool
    {
        return (bool) auth()->user()?->can('orders.view_financials');
    }

    /**
     * @return array<int, array{date:string, day:string, orders:int, net:string, bar:float}>
     */
    public function getRows(): array
    {
        [$from, $to] = FinanceRange::fromFilters($this->filters);

        $series = app(FinanceReportService::class)->dailySeries($from, $to);

        // أعلى صافي في المدى مرجعًا لعرض شريط كل يوم (0.01 يمنع القسمة على صفر).
        $max = max(0.01, (float) $series->max(fn (array $row): float => (float) $row['net_sales']));

        return $series
            ->map(function (array $row) use ($max): array {
                $date = CarbonImmutable::createFromFormat('Y-m-d', $row['date']);
                $net = (float) $row['net_sales'];

                return [
                    'date' => $row['date'],
                    'day' => self::AR_DAYS[$date->dayOfWeek],
                    'orders' => $row['orders'],
                    'net' => number_format($net, 2),
                    'bar' => min(100.0, $net / $max * 100),
                ];
            })
            // الأحدث أولًا — يقرأ المالك اليوم الحالي في الأعلى.
            ->reverse()
            ->values()
            ->all();
    }
}
