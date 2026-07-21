<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Filament\Resources\BookResource;
use App\Filament\Resources\ExpenseResource;
use App\Filament\Resources\OrderResource;
use App\Filament\Widgets\FinanceDailyWidget;
use App\Filament\Widgets\FinanceRange;
use App\Filament\Widgets\FinanceStatsWidget;
use App\Filament\Widgets\FinanceTrendWidget;
use App\Providers\Filament\AdminPanelProvider;
use App\Services\Finance\CostBackfillService;
use App\Services\Finance\FinanceReportService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Pages\Page;
use Filament\Widgets\Widget;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

    protected static ?string $navigationGroup = AdminPanelProvider::GROUP_FINANCE;

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'لوحة المالية';

    protected static ?string $title = 'الإدارة المالية';

    protected static string $view = 'filament.pages.finance-dashboard';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('orders.view_financials');
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
    }

    /**
     * ملخّص الفترة المختارة لشريط الترويسة — يتفاعل مع الفلتر ويعرض المدى الفعلي
     * بيوم القاهرة وعدد أيامه، فيعرف المالك بالضبط ما تُغطّيه الأرقام.
     *
     * @return array{label:string, from:string, to:string, days:int}
     */
    public function periodSummary(): array
    {
        [$from, $to] = FinanceRange::fromFilters($this->filters ?? []);

        $labels = [
            'today' => 'اليوم', '7d' => 'آخر ٧ أيام', '30d' => 'آخر ٣٠ يومًا',
            'month' => 'هذا الشهر', 'custom' => 'نطاق مخصّص',
        ];
        $preset = $this->filters['preset'] ?? '30d';

        return [
            'label' => $labels[$preset] ?? 'آخر ٣٠ يومًا',
            'from' => $from->format('Y-m-d'),
            'to' => $to->format('Y-m-d'),
            // فرق الأيام شامل الطرفين (يوم واحد = فرق صفر + ١). cast صريح لصحيح:
            // Carbon 3 يعيد diffInDays كـ float فيظهر «٣٠٫٩٩ يوم» بلا هذا.
            'days' => (int) $from->diffInDays($to) + 1,
        ];
    }

    /**
     * إجراءات الترويسة: تصدير السلسلة اليومية CSV (م٤د)، محمي بـ orders.export.
     *
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            // أزرار إدخال/تعديل صريحة — كلٌّ مُقيّد بإذن الموردة نفسها (canViewAny)،
            // فلا يظهر زرّ لا يملك المستخدم صلاحية وجهته (4.4). اللوحة تقرير يعكس
            // المصدر: نُدخل المدخلات (تكلفة/شحن/مصروف) فتُعاد الأرقام، لا العكس.
            Action::make('addExpense')
                ->label('إضافة مصروف')
                ->icon('heroicon-o-plus')
                ->color('primary')
                ->url(fn (): string => ExpenseResource::getUrl('create'))
                ->visible(fn (): bool => ExpenseResource::canViewAny()),

            Action::make('bookCosts')
                ->label('أسعار شراء الكتب')
                ->icon('heroicon-o-book-open')
                ->color('gray')
                ->url(fn (): string => BookResource::getUrl('index'))
                ->visible(fn (): bool => BookResource::canViewAny()),

            Action::make('manageOrders')
                ->label('إدارة الطلبات')
                ->icon('heroicon-o-shopping-bag')
                ->color('gray')
                ->url(fn (): string => OrderResource::getUrl('index'))
                ->visible(fn (): bool => OrderResource::canViewAny()),

            // ترحيل تكلفة الطلبات القديمة — مؤثّر في البيانات، فبتأكيد + إذن
            // products.cost.update (أضيق من الوصول للوحة) + تحقّق خادميّ عند الفعل.
            Action::make('backfillCosts')
                ->label('ترحيل تكاليف الطلبات القديمة')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->visible(fn (): bool => auth()->user()?->can('products.cost.update') === true)
                ->requiresConfirmation()
                ->modalHeading('ترحيل تكاليف الطلبات القديمة')
                ->modalDescription('يملأ تكلفة الطلبات التي أُنشئت قبل إدخال أسعار الشراء، مستعملًا سعر الشراء الحالي لكل كتاب. لا يلمس الطلبات التي لها تكلفة محفوظة. تنبيه: يستخدم السعر الحالي لا سعر وقت البيع.')
                ->modalSubmitActionLabel('نفّذ الترحيل')
                ->action(function (): void {
                    abort_unless(auth()->user()?->can('products.cost.update') === true, 403);

                    $r = app(CostBackfillService::class)->run();

                    Notification::make()
                        ->title('اكتمل ترحيل التكاليف')
                        ->body("مُلئت {$r['filled']} سطرًا في {$r['orders']} طلب. سطور بلا سعر شراء: {$r['skipped_no_cost']}.")
                        ->success()
                        ->send();
                }),

            Action::make('exportCsv')
                ->label('تصدير CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->visible(fn (): bool => auth()->user()?->can('orders.export') === true)
                ->action(fn (): StreamedResponse => $this->exportDailyCsv()),
        ];
    }

    /**
     * يبثّ السلسلة اليومية للنطاق الحالي كـ CSV (UTF-8 مع BOM ليقرأه Excel عربيًا).
     */
    private function exportDailyCsv(): StreamedResponse
    {
        // تحقّق خادميّ عند الفعل، لا الاكتفاء بإخفاء الزر (4.4 / ممنوع 13).
        abort_unless(auth()->user()?->can('orders.export') === true, 403);

        [$from, $to] = FinanceRange::fromFilters($this->filters ?? []);
        $rows = app(FinanceReportService::class)->dailySeries($from, $to);

        $filename = 'finance-daily-'.$from->format('Ymd').'-'.$to->format('Ymd').'.csv';

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'wb');
            fwrite($out, "\xEF\xBB\xBF"); // BOM لعرض العربية في Excel.
            fputcsv($out, ['التاريخ', 'عدد الطلبات', 'صافي المبيعات']);
            foreach ($rows as $row) {
                fputcsv($out, [$row['date'], $row['orders'], $row['net_sales']]);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
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
     * ودجت اللوحة — يُصيّرها القالب يدويًا داخل الـslot عبر getWidgets() لتمرير
     * الفلاتر الصحيحة. لا نسمّيها getHeaderWidgets: ذلك الاسم يجعل مكوّن صفحة
     * Filament يرسمها تلقائيًا فوق الـslot بفلاتر فارغة (نطاق ٣٠ يومًا ثابت)،
     * فتتكرّر كل ويدجت مرّتين بمفاتيح Livewire متضاربة. المصدر واحد هنا.
     *
     * @return array<int, class-string<Widget>>
     */
    public function getWidgets(): array
    {
        return [
            FinanceStatsWidget::class,
            FinanceTrendWidget::class,
            FinanceDailyWidget::class,
        ];
    }
}
