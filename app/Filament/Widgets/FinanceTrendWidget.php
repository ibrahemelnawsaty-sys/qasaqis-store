<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Services\Finance\FinanceReportService;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

/**
 * منحنى المبيعات اليومي — يقرأ صافي المبيعات المحقّقة لكل يوم (بتوقيت القاهرة)
 * من نفس FinanceReportService، فيتّسق رقمُه مع بقية اللوحة. يتفاعل مع فلتر
 * النطاق تلقائيًا: $filters مُعلَّمة #[Reactive]، وChartWidget::rendering يعيد
 * دفع البيانات عند كل تصيير.
 *
 * الأمان (4.4): canView يفوّض الإذن مستقلًّا كبقية ودجت المالية.
 */
class FinanceTrendWidget extends ChartWidget
{
    use InteractsWithPageFilters;

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $maxHeight = '260px';

    public static function canView(): bool
    {
        return (bool) auth()->user()?->can('orders.view_financials');
    }

    public function getHeading(): string
    {
        return 'منحنى المبيعات اليومي';
    }

    public function getDescription(): ?string
    {
        return 'صافي المبيعات المحقّقة لكل يوم — بتوقيت القاهرة.';
    }

    protected function getType(): string
    {
        return 'line';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        [$from, $to] = FinanceRange::fromFilters($this->filters);
        $series = app(FinanceReportService::class)->dailySeries($from, $to);

        // نقاط بلا حشو حين تكثر الأيام (٣٠+) كي لا يزدحم المنحنى.
        $dense = $series->count() > 45;

        return [
            'datasets' => [
                [
                    'label' => 'صافي المبيعات (ج.م)',
                    'data' => $series->map(fn (array $r): float => (float) $r['net_sales'])->all(),
                    'borderColor' => '#6E2FB0',
                    'backgroundColor' => 'rgba(110, 47, 176, 0.12)',
                    'fill' => true,
                    'tension' => 0.35,
                    'borderWidth' => 2,
                    'pointRadius' => $dense ? 0 : 3,
                    'pointHoverRadius' => 5,
                    'pointBackgroundColor' => '#6E2FB0',
                ],
            ],
            'labels' => $series->map(fn (array $r): string => $r['date'])->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getOptions(): array
    {
        return [
            'maintainAspectRatio' => false,
            'plugins' => [
                'legend' => ['display' => false],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'ticks' => ['maxTicksLimit' => 5],
                ],
                'x' => [
                    'ticks' => ['maxTicksLimit' => 8, 'autoSkip' => true, 'maxRotation' => 0],
                ],
            ],
        ];
    }
}
