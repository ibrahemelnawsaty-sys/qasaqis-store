<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Filament\Widgets\FinanceDailyWidget;
use App\Filament\Widgets\FinanceStatsWidget;
use App\Providers\Filament\AdminPanelProvider;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Pages\Page;
use Filament\Widgets\Widget;

/**
 * لوحة القسم المالي (المرحلة ١ — الإيراد فقط). صفحة تجمع ويدجت المؤشرات
 * وجدول الأداء اليومي تحت فلتر تاريخ واحد يمرّره HasFiltersForm لكل ويدجت.
 *
 * الأمان (الدستور 4.4 / ممنوع 13): الدخول محمي بـ orders.view_financials عبر
 * canAccess (يخفي الرابط) + mount (يمنع الوصول المباشر بـ 403). ولأن ويدجت
 * Filament تُصرّح مستقلة، يكرّر كل ويدجت الفحص نفسه في canView — فلا يكفي حجب
 * الصفحة وحدها.
 */
class FinanceDashboard extends Page
{
    use HasFiltersForm;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = AdminPanelProvider::GROUP_ORDERS_PAYMENTS;

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'القسم المالي';

    protected static ?string $title = 'القسم المالي';

    protected static string $view = 'filament.pages.finance-dashboard';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('orders.view_financials');
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
    }

    public function filtersForm(Form $form): Form
    {
        return $form->schema([
            Select::make('preset')
                ->label('النطاق الزمني')
                ->options([
                    'today' => 'اليوم',
                    '7d' => 'آخر ٧ أيام',
                    '30d' => 'آخر ٣٠ يومًا',
                    'month' => 'هذا الشهر',
                    'custom' => 'مخصّص',
                ])
                ->default('30d')
                ->native(false)
                ->live(),

            // حقلا التاريخ المخصّص يظهران فقط مع «مخصّص». التحقق النهائي خادميّ
            // في FinanceRange (from ≤ to، امتداد مقيّد) لا واجهيّ فقط (4.1).
            DatePicker::make('from')
                ->label('من')
                ->visible(fn (callable $get): bool => $get('preset') === 'custom'),

            DatePicker::make('to')
                ->label('إلى')
                ->visible(fn (callable $get): bool => $get('preset') === 'custom'),
        ])->columns(4);
    }

    /**
     * @return array<int, class-string<Widget>>
     */
    public function getWidgets(): array
    {
        return [
            FinanceStatsWidget::class,
            FinanceDailyWidget::class,
        ];
    }

    /**
     * @return array<int, class-string<Widget>>
     */
    protected function getHeaderWidgets(): array
    {
        return $this->getWidgets();
    }
}
