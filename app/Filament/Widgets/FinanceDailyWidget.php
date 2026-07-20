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

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    /** أسماء أيام الأسبوع بالعربية، مفهرسة بـ Carbon::dayOfWeek (0=الأحد). */
    private const AR_DAYS = ['الأحد', 'الإثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت'];

    public static function canView(): bool
    {
        return (bool) auth()->user()?->can('orders.view_financials');
    }

    /**
     * @return array<int, array{date:string, day:string, orders:int, net:string}>
     */
    public function getRows(): array
    {
        [$from, $to] = FinanceRange::fromFilters($this->filters);

        return app(FinanceReportService::class)
            ->dailySeries($from, $to)
            ->map(function (array $row): array {
                $date = CarbonImmutable::createFromFormat('Y-m-d', $row['date']);

                return [
                    'date' => $row['date'],
                    'day' => self::AR_DAYS[$date->dayOfWeek],
                    'orders' => $row['orders'],
                    'net' => number_format((float) $row['net_sales'], 2),
                ];
            })
            // الأحدث أولًا — يقرأ المالك اليوم الحالي في الأعلى.
            ->reverse()
            ->values()
            ->all();
    }
}
