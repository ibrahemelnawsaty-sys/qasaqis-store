<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Models\Book;
use App\Models\Order;
use App\Models\PaymentProof;
use App\Support\Ops\OpsKpi;
use Filament\Pages\Page;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Locked;
use Livewire\WithPagination;

/**
 * صفحة تفاصيل مؤشّر (KPI) — تُفتح من نقر بطاقة على لوحة العمليات عبر ‎?kpi=<المفتاح>‎.
 *
 * لكل مؤشّر صفحته الخاصّة: الرقم الرئيسي (محسوب من نفس استعلام السِجل فيطابق البطاقة
 * تمامًا — بند 1.1)، تحليلٌ موجز (تفصيل حسب الحالة/النوع)، وجدولٌ بكل الصفوف الأساسية
 * خلف الرقم مع رابط فتح كل سجل بتفاصيله الكاملة في مورده.
 *
 * الصلاحية: orders.view (كبقية لوحة العمليات)، والمؤشّرات المالية محجوبة خلف
 * orders.view_financials — يُفحص خادميًّا في mount() لا واجهيًّا فقط (بند 4.4).
 * غير مسجَّلة في التنقّل: تُبلَغ عبر الروابط لا من القائمة الجانبية.
 */
class KpiDetail extends Page
{
    use WithPagination;

    protected static string $view = 'filament.pages.kpi-detail';

    /** صفوف لكل صفحة — لوائح تشغيلية، 50 كافية وخفيفة على الشبكة. */
    public const PER_PAGE = 50;

    /**
     * مفتاح المؤشّر الحالي. #[Locked] حاسم أمنيًّا: يُضبط في mount() من الرابط بعد
     * التحقّق، ثم **يمنع Livewire أيّ تعديل من العميل** بعدها. بدونه كان مستخدمٌ بلا
     * صلاحية مالية يفتح مؤشّرًا غير ماليّ ثم يبدّل الخاصية إلى مؤشّر ماليّ عبر تحديث
     * Livewire (لا يُعيد mount) فيرى أرقامًا محجوبة (الممنوع 13 / بند 4.4).
     */
    #[Locked]
    public string $kpi = '';

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('orders.view');
    }

    public function getTitle(): string
    {
        return OpsKpi::get($this->kpi)['label'] ?? 'تفاصيل المؤشّر';
    }

    public function mount(): void
    {
        $this->kpi = (string) request()->query('kpi', '');

        $this->authorizedDef();
    }

    /**
     * التفويض الحاكم — يُستدعى في mount() **وفي كل عرض** (getViewData) دفاعًا في
     * العمق (بند 4.4): مفتاح مجهول = 404 (لا نكشف قائمة المفاتيح)، ومؤشّر مالي بلا
     * صلاحية مالية = 403. لا يُكتفى بفحص mount مرّة (الممنوع 13).
     *
     * @return array<string, mixed>
     */
    private function authorizedDef(): array
    {
        abort_unless(static::canAccess(), 403);

        $def = OpsKpi::get($this->kpi);
        abort_if($def === null, 404);
        abort_if($def['financial'] && ! auth()->user()?->can('orders.view_financials'), 403);

        return $def;
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $def = $this->authorizedDef();

        return [
            'def' => $def,
            'metric' => OpsKpi::metricValue($def),
            'rows' => $this->rows($def),
            'breakdown' => $this->breakdown($def),
        ];
    }

    /**
     * صفوف المؤشّر مرقّمةً مع تحميل مسبق يمنع N+1 (بند 2.5). الترتيب حسب النموذج:
     * الأحدث للطلبات/الإثباتات، والأقلّ مخزونًا أولًا للكتب (الأكثر إلحاحًا).
     */
    private function rows(array $def): LengthAwarePaginator
    {
        $query = ($def['query'])();

        return match ($def['model']) {
            Book::class => $query->orderBy('stock_quantity')->paginate(self::PER_PAGE),
            PaymentProof::class => $query->with('order:id,order_number,customer_name,grand_total')
                ->latest('created_at')->paginate(self::PER_PAGE),
            default => $query->latest('created_at')->paginate(self::PER_PAGE),
        };
    }

    /**
     * تحليل موجز يعطي «لماذا هذا الرقم»: تفصيل الطلبات حسب الحالة، والكتب حسب
     * نافد/منخفض. يُحسب من نفس الاستعلام الأساسي فلا ينحرف عن الرقم الرئيسي.
     *
     * @return list<array{label:string, value:int}>
     */
    private function breakdown(array $def): array
    {
        if ($def['model'] === Order::class) {
            $rows = ($def['query'])()
                ->selectRaw('status, COUNT(*) as n')->groupBy('status')
                ->orderByDesc('n')->pluck('n', 'status');

            return $rows->map(fn (int $n, string $status): array => [
                'label' => \App\Filament\Resources\OrderResource::STATUS_LABELS[$status] ?? $status,
                'value' => (int) $n,
            ])->values()->all();
        }

        if ($def['model'] === Book::class) {
            $out = ($def['query'])()->where(fn ($q) => $q->where('stock_status', 'out_of_stock')->orWhere('stock_quantity', '<=', 0))->count();
            $total = ($def['query'])()->count();

            return [
                ['label' => 'نافد تمامًا', 'value' => $out],
                ['label' => 'منخفض (متبقٍّ ولم ينفد)', 'value' => max(0, $total - $out)],
            ];
        }

        return [];
    }
}
