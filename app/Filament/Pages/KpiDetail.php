<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Models\Book;
use App\Models\Order;
use App\Models\PaymentProof;
use App\Support\Ops\OpsKpi;
use Filament\Pages\Page;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Locked;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

    /** سقف صفوف تصدير CSV — حماية من تصدير ضخم يستنزف الذاكرة. */
    public const EXPORT_CAP = 5000;

    /**
     * عمود واتجاه الفرز داخل الصفحة. غير مقفولين (المستخدم يفرز)، لكن يُتحقَّق منهما
     * بقائمة بيضاء (sortableColumns) قبل أي orderBy — فلا يُحقَن اسم عمود عشوائي.
     */
    public string $sortCol = '';

    public string $sortDir = 'desc';

    /**
     * مفتاح المؤشّر الحالي. #[Locked] حاسم أمنيًّا: يُضبط في mount() من الرابط بعد
     * التحقّق، ثم **يمنع Livewire أيّ تعديل من العميل** بعدها. بدونه كان مستخدمٌ بلا
     * صلاحية مالية يفتح مؤشّرًا غير ماليّ ثم يبدّل الخاصية إلى مؤشّر ماليّ عبر تحديث
     * Livewire (لا يُعيد mount) فيرى أرقامًا محجوبة (الممنوع 13 / بند 4.4).
     */
    #[Locked]
    public string $kpi = '';

    /** معامِل المؤشّرات المُعامَلة (اسم محافظة/معرّف كتاب/شهر…). مقفول كـ$kpi. */
    #[Locked]
    public string $value = '';

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
        $def = OpsKpi::get($this->kpi);

        if ($def === null) {
            return 'تفاصيل المؤشّر';
        }

        if (OpsKpi::isParam($def) && $this->value !== '') {
            return $def['label'].': '.OpsKpi::valueLabel($def, $this->value);
        }

        return $def['label'];
    }

    public function mount(): void
    {
        $this->kpi = (string) request()->query('kpi', '');
        $this->value = (string) request()->query('v', '');

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
        // مؤشّر مُعامَل بلا قيمة = طلب ناقص (404): لا نعرض «كل الطلبات» بلا قصد.
        abort_if(OpsKpi::isParam($def) && $this->value === '', 404);

        return $def;
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $def = $this->authorizedDef();

        return [
            'def' => $def,
            'metric' => OpsKpi::metricValue($def, $this->value),
            'valueLabel' => OpsKpi::isParam($def) ? OpsKpi::valueLabel($def, $this->value) : null,
            'rows' => $this->rows($def),
            'breakdown' => $this->breakdown($def),
        ];
    }

    /** صفوف المؤشّر مرقّمةً (بالفرز والتحميل المسبق). */
    private function rows(array $def): LengthAwarePaginator
    {
        return $this->baseQuery($def)->paginate(self::PER_PAGE);
    }

    /**
     * الاستعلام الأساسي مع التحميل المسبق (يمنع N+1، بند 2.5) والفرز المطبَّق —
     * مشترك بين العرض المرقّم والتصدير كي لا يختلفا.
     */
    private function baseQuery(array $def): Builder
    {
        $query = ($def['query'])($this->value);

        if ($def['model'] === PaymentProof::class) {
            $query->with('order:id,order_number,customer_name,grand_total');
        }

        return $this->applySort($query, $def);
    }

    /** يطبّق الفرز المختار (بعد التحقّق بالقائمة البيضاء) أو الافتراضي حسب النموذج. */
    private function applySort(Builder $query, array $def): Builder
    {
        if ($this->sortCol !== '' && in_array($this->sortCol, $this->sortableColumns($def), true)) {
            return $query->orderBy($this->sortCol, $this->sortDir === 'asc' ? 'asc' : 'desc');
        }

        // الافتراضي: الأقلّ مخزونًا أولًا للكتب (الأكثر إلحاحًا)، والأحدث لغيرها.
        return $def['model'] === Book::class
            ? $query->orderBy('stock_quantity')
            : $query->latest('created_at');
    }

    /**
     * أعمدة يُسمح بالفرز عليها — قائمة بيضاء صارمة تمنع حقن اسم عمود عشوائي في orderBy.
     *
     * @return list<string>
     */
    public function sortableColumns(array $def): array
    {
        return match ($def['model']) {
            Book::class => ['title', 'stock_quantity', 'stock_status'],
            PaymentProof::class => ['created_at', 'review_status'],
            default => ['order_number', 'customer_name', 'governorate', 'status', 'payment_status', 'grand_total', 'created_at'],
        };
    }

    /** نقر عنوان عمود: يبدّل الاتجاه إن تكرّر العمود، وإلا يفرز تصاعديًّا عليه. */
    public function sort(string $column): void
    {
        $def = $this->authorizedDef();

        if (! in_array($column, $this->sortableColumns($def), true)) {
            return; // عمود غير مسموح — يُتجاهَل بصمت (لا حقن)
        }

        if ($this->sortCol === $column) {
            $this->sortDir = $this->sortDir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortCol = $column;
            $this->sortDir = 'asc';
        }

        $this->resetPage();
    }

    /**
     * تصدير CSV لكل صفوف المؤشّر (حتى EXPORT_CAP). يعيد التفويض أولًا (الحجب المالي
     * يسري على التصدير كالعرض)، ويكتب BOM كي يقرأ Excel العربية صحيحًا.
     */
    public function export(): StreamedResponse
    {
        $def = $this->authorizedDef();

        $slug = $this->value === '' ? '' : '-'.preg_replace('/[^\p{Arabic}\w-]+/u', '_', $this->value);
        $filename = 'kpi-'.$this->kpi.$slug.'.csv';

        return response()->streamDownload(function () use ($def): void {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");

            [$headers, $mapper] = $this->exportShape($def);
            fputcsv($out, $headers);

            $this->baseQuery($def)->limit(self::EXPORT_CAP)->cursor()
                ->each(static function ($row) use ($out, $mapper): void {
                    fputcsv($out, $mapper($row));
                });

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * رؤوس CSV ودالّة تحويل الصفّ حسب النموذج (نفس أعمدة الجدول + إضافات مفيدة).
     *
     * @return array{0: list<string>, 1: callable}
     */
    private function exportShape(array $def): array
    {
        $ord = \App\Filament\Resources\OrderResource::class;

        if ($def['model'] === Book::class) {
            return [
                ['الكتاب', 'الكود', 'المخزون', 'الحالة'],
                static fn ($b): array => [$b->title, (string) $b->sku, (int) $b->stock_quantity, (string) $b->stock_status],
            ];
        }

        if ($def['model'] === PaymentProof::class) {
            return [
                ['رقم الطلب', 'العميل', 'الإجمالي', 'حالة المراجعة', 'التاريخ'],
                static fn ($p) => [
                    (string) $p->order?->order_number, (string) $p->order?->customer_name,
                    (string) $p->order?->grand_total,
                    $ord::REVIEW_STATUS_LABELS[$p->review_status] ?? (string) $p->review_status,
                    (string) $p->created_at?->format('Y-m-d H:i'),
                ],
            ];
        }

        return [
            ['رقم الطلب', 'العميل', 'الهاتف', 'المحافظة', 'الحالة', 'حالة الدفع', 'طريقة الدفع', 'الإجمالي', 'التاريخ'],
            static fn ($o): array => [
                (string) $o->order_number, (string) $o->customer_name, (string) $o->customer_phone,
                (string) $o->governorate,
                $ord::STATUS_LABELS[$o->status] ?? (string) $o->status,
                $ord::PAYMENT_STATUS_LABELS[$o->payment_status] ?? (string) $o->payment_status,
                $ord::PAYMENT_METHOD_LABELS[$o->payment_method] ?? (string) $o->payment_method,
                (string) $o->grand_total, (string) $o->created_at?->format('Y-m-d H:i'),
            ],
        ];
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
            $rows = ($def['query'])($this->value)
                ->selectRaw('status, COUNT(*) as n')->groupBy('status')
                ->orderByDesc('n')->pluck('n', 'status');

            return $rows->map(fn (int $n, string $status): array => [
                'label' => \App\Filament\Resources\OrderResource::STATUS_LABELS[$status] ?? $status,
                'value' => (int) $n,
            ])->values()->all();
        }

        if ($def['model'] === Book::class) {
            $out = ($def['query'])($this->value)->where(fn ($q) => $q->where('stock_status', 'out_of_stock')->orWhere('stock_quantity', '<=', 0))->count();
            $total = ($def['query'])($this->value)->count();

            return [
                ['label' => 'نافد تمامًا', 'value' => $out],
                ['label' => 'منخفض (متبقٍّ ولم ينفد)', 'value' => max(0, $total - $out)],
            ];
        }

        return [];
    }
}
