<?php

declare(strict_types=1);

namespace App\Support\Ops;

use App\Models\Book;
use App\Models\Category;
use App\Models\Order;
use App\Models\PaymentProof;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

/**
 * سِجل مؤشّرات لوحة العمليات (KPIs) — **مصدر الحقيقة الوحيد** لتعريف كل مؤشّر:
 * تسميته، أيقونته، نموذجه، وأهمّ شيء **استعلامه الأساسي** (Builder للصفوف التي
 * يمثّلها الرقم). تستعمله صفحة التفاصيل KpiDetail لعرض الرقم نفسه المعروض على
 * اللوحة + الصفوف الكاملة خلفه، فلا ينحرف رقم التفاصيل عن رقم البطاقة (بند 1.1).
 *
 * قيود النافذة الزمنية تُطابق OpsDashboard/ScopesRevenue حرفيًّا (TREND_DAYS=30).
 * اختبارات KpiDetailTest تؤكّد أن metricValue واستعلام كل مؤشّر يطابقان العدّ/المجموع
 * المباشر المطابق لتعريف اللوحة (حارس ضدّ الانحراف).
 */
final class OpsKpi
{
    /** نافذة الاتجاه — تطابق ScopesRevenue::TREND_DAYS (30 يومًا شاملة اليوم). */
    public const TREND_DAYS = 30;

    /** حالات الطلب المُحقِّقة للإيراد (مسلَّم/مكتمل) — نفس تعريف overview. */
    public const REALIZED_STATUSES = ['delivered', 'completed'];

    /** بداية نافذة الاتجاه: منتصف ليل قبل 29 يومًا (يطابق trendWindowStart). */
    public static function windowStart(): Carbon
    {
        return Carbon::today()->subDays(self::TREND_DAYS - 1);
    }

    /**
     * تعريفات كل المؤشّرات. المفتاح يُستعمل في الرابط (?kpi=…).
     *
     * @return array<string, array{
     *   label:string, icon:string, hint:string, model:class-string,
     *   financial:bool, metric:'count'|'sum'|'avg', column?:string,
     *   query:callable():Builder
     * }>
     */
    public static function all(): array
    {
        $since = self::windowStart();

        return [
            // ── نظرة عامة ──────────────────────────────────────────────
            'orders_today' => [
                'label' => 'طلبات اليوم',
                'icon' => '📅',
                'hint' => 'كل الطلبات المُنشأة اليوم — بكل حالاتها.',
                'model' => Order::class,
                'financial' => false,
                'metric' => 'count',
                'query' => static fn (): Builder => Order::query()->whereDate('created_at', Carbon::today()),
            ],
            'orders_month' => [
                'label' => 'طلبات آخر 30 يومًا',
                'icon' => '📆',
                'hint' => 'الطلبات المُنشأة خلال نافذة الاتجاه (30 يومًا).',
                'model' => Order::class,
                'financial' => false,
                'metric' => 'count',
                'query' => static fn (): Builder => Order::query()->where('created_at', '>=', $since),
            ],
            'revenue_realized' => [
                'label' => 'الإيراد المحقَّق (30ي)',
                'icon' => '💰',
                'hint' => 'مجموع إجمالي الطلبات المسلَّمة/المكتملة خلال 30 يومًا.',
                'model' => Order::class,
                'financial' => true,
                'metric' => 'sum',
                'column' => 'grand_total',
                'query' => static fn (): Builder => Order::query()
                    ->whereIn('status', self::REALIZED_STATUSES)
                    ->where('created_at', '>=', $since),
            ],
            'aov' => [
                'label' => 'متوسّط قيمة الطلب',
                'icon' => '🧮',
                'hint' => 'متوسّط إجمالي الطلب على الطلبات المحقَّقة خلال 30 يومًا.',
                'model' => Order::class,
                'financial' => true,
                'metric' => 'avg',
                'column' => 'grand_total',
                'query' => static fn (): Builder => Order::query()
                    ->whereIn('status', self::REALIZED_STATUSES)
                    ->where('created_at', '>=', $since),
            ],

            // ── طوابير العمل ───────────────────────────────────────────
            'confirm' => [
                'label' => 'تنتظر تأكيد واتساب',
                'icon' => '📞',
                'hint' => 'طلبات قيد الانتظار (pending) لم تُؤكَّد عبر واتساب بعد.',
                'model' => Order::class,
                'financial' => false,
                'metric' => 'count',
                'query' => static fn (): Builder => Order::query()
                    ->where('status', 'pending')->whereNull('whatsapp_confirmed_at'),
            ],
            'proofs' => [
                'label' => 'إثباتات تنتظر المراجعة',
                'icon' => '🧾',
                'hint' => 'إثباتات تحويل رفعها العملاء وتنتظر مراجعتك.',
                'model' => PaymentProof::class,
                'financial' => false,
                'metric' => 'count',
                'query' => static fn (): Builder => PaymentProof::query()->where('review_status', 'pending_review'),
            ],
            'no_tracking' => [
                'label' => 'مؤكّد بلا رقم تتبّع',
                'icon' => '📦',
                'hint' => 'طلبات مؤكّدة/قيد التجهيز لم يُدخَل لها رقم تتبّع بعد.',
                'model' => Order::class,
                'financial' => false,
                'metric' => 'count',
                'query' => static fn (): Builder => Order::query()
                    ->whereIn('status', ['confirmed', 'processing'])->whereNull('tracking_number'),
            ],
            'shipped' => [
                'label' => 'مشحون ولم يُسلَّم',
                'icon' => '🚚',
                'hint' => 'شحنات جارية بحالة «شُحن» بانتظار التسليم.',
                'model' => Order::class,
                'financial' => false,
                'metric' => 'count',
                'query' => static fn (): Builder => Order::query()->where('status', 'shipped'),
            ],
            'manual_pending' => [
                'label' => 'دفع يدوي معلّق',
                'icon' => '💵',
                'hint' => 'تحويلات يدوية (غير COD) لم تُؤكَّد بعد — بانتظار الدفع أو الإثبات.',
                'model' => Order::class,
                'financial' => false,
                'metric' => 'sum',
                'column' => 'grand_total',
                'query' => static fn (): Builder => Order::query()
                    ->where('payment_method', '!=', 'cod')
                    ->whereIn('payment_status', ['unpaid', 'pending_review']),
            ],
            'low_stock' => [
                'label' => 'مخزون منخفض/نافد',
                'icon' => '📉',
                'hint' => 'كتب منشورة يُدار مخزونها: نافدة أو المتبقّي ≤ 5.',
                'model' => Book::class,
                'financial' => false,
                'metric' => 'count',
                'query' => static fn (): Builder => Book::query()
                    ->where('is_published', true)->where('manage_stock', true)
                    ->where(fn (Builder $q) => $q->where('stock_status', 'out_of_stock')->orWhere('stock_quantity', '<=', 5)),
            ],

            // ── مراحل القُمع (ثابتة، نافذة 30ي) — مجموعات حالات تراكمية كما في funnel() ──
            'funnel_confirmed' => self::funnelStage('طلبات وصلت مرحلة التأكيد فأكثر', '✅', ['confirmed', 'processing', 'shipped', 'delivered', 'completed'], $since),
            'funnel_processing' => self::funnelStage('طلبات وصلت التجهيز فأكثر', '📦', ['processing', 'shipped', 'delivered', 'completed'], $since),
            'funnel_shipped' => self::funnelStage('طلبات وصلت الشحن فأكثر', '🚚', ['shipped', 'delivered', 'completed'], $since),
            'funnel_delivered' => self::funnelStage('طلبات سُلّمت', '🎉', ['delivered', 'completed'], $since),
            'funnel_lost' => self::funnelStage('طلبات ملغاة/مرفوضة', '⚠️', ['cancelled', 'refused'], $since),

            // ── الأزمنة (ثابتة) — عيّنة الطلبات/الإثباتات خلف متوسّط الزمن ──
            'timing_confirm' => [
                'label' => 'طلبات مؤكّدة عبر واتساب (30ي)',
                'icon' => '⏱️',
                'hint' => 'العيّنة التي يُحسب منها متوسّط زمن التأكيد: طلبات لها ختم تأكيد واتساب خلال النافذة.',
                'model' => Order::class, 'financial' => false, 'metric' => 'count',
                'query' => static fn (): Builder => Order::query()
                    ->whereNotNull('whatsapp_confirmed_at')->where('created_at', '>=', $since),
            ],
            'timing_proof' => [
                'label' => 'إثباتات روجعت (30ي)',
                'icon' => '⏱️',
                'hint' => 'العيّنة التي يُحسب منها متوسّط زمن مراجعة الإثبات.',
                'model' => PaymentProof::class, 'financial' => false, 'metric' => 'count',
                'query' => static fn (): Builder => PaymentProof::query()
                    ->whereNotNull('reviewed_at')->where('created_at', '>=', $since),
            ],

            // ── مُعامَلة بقيمة (v=…) ─────────────────────────────────────────
            'governorate' => [
                'label' => 'طلبات المحافظة',
                'icon' => '🗺️',
                'hint' => 'كل طلبات هذه المحافظة خلال 30 يومًا (بكل حالاتها).',
                'model' => Order::class, 'financial' => false, 'metric' => 'count', 'param' => true,
                'valueLabel' => static fn (?string $v): string => (string) $v,
                'query' => static fn (?string $v): Builder => Order::query()
                    ->where('governorate', (string) $v)->where('created_at', '>=', $since),
            ],
            'month' => [
                'label' => 'طلبات شهر',
                'icon' => '📆',
                'hint' => 'كل الطلبات المُنشأة في هذا الشهر.',
                'model' => Order::class, 'financial' => false, 'metric' => 'count', 'param' => true,
                'valueLabel' => static fn (?string $v): string => (string) $v,
                'query' => static function (?string $v): Builder {
                    // معامِل موثّق بقائمة بيضاء YYYY-MM ثم مدى تواريخ (لا سلسلة خام في SQL).
                    $month = self::parseMonth($v);

                    return $month === null
                        ? Order::query()->whereRaw('1 = 0')  // قيمة غير صالحة → لا نتائج
                        : Order::query()->whereBetween('created_at', [$month, (clone $month)->endOfMonth()]);
                },
            ],
            'book' => [
                'label' => 'طلبات تحتوي الكتاب',
                'icon' => '📚',
                'hint' => 'الطلبات التي تضمّنت هذا الكتاب خلال 30 يومًا.',
                'model' => Order::class, 'financial' => false, 'metric' => 'count', 'param' => true,
                'valueLabel' => static fn (?string $v): string => (string) (Book::query()->whereKey((int) $v)->value('title') ?? ('#'.$v)),
                'query' => static fn (?string $v): Builder => Order::query()
                    ->whereHas('items', fn (Builder $q) => $q->where('book_id', (int) $v))
                    ->where('created_at', '>=', $since),
            ],
            'category' => [
                'label' => 'طلبات القسم',
                'icon' => '🏷️',
                'hint' => 'الطلبات التي تضمّنت كتبًا قسمُها الرئيسي هذا القسم، خلال 30 يومًا.',
                'model' => Order::class, 'financial' => false, 'metric' => 'count', 'param' => true,
                'valueLabel' => static fn (?string $v): string => (string) (Category::query()->whereKey((int) $v)->value('name') ?? ('#'.$v)),
                'query' => static fn (?string $v): Builder => Order::query()
                    ->whereHas('items', fn (Builder $q) => $q->whereHas('book', fn (Builder $b) => $b->where('category_id', (int) $v)))
                    ->where('created_at', '>=', $since),
            ],
            'payment' => [
                'label' => 'طلبات طريقة الدفع',
                'icon' => '💳',
                'hint' => 'الطلبات بهذه الطريقة خلال 30 يومًا.',
                'model' => Order::class, 'financial' => false, 'metric' => 'count', 'param' => true,
                'valueLabel' => static fn (?string $v): string => \App\Filament\Resources\OrderResource::PAYMENT_METHOD_LABELS[$v] ?? (string) $v,
                'query' => static fn (?string $v): Builder => Order::query()
                    ->where('payment_method', (string) $v)->where('created_at', '>=', $since),
            ],
        ];
    }

    /** بنية موحّدة لمرحلة قُمع (مجموعة حالات تراكمية ضمن النافذة). */
    private static function funnelStage(string $label, string $icon, array $statuses, Carbon $since): array
    {
        return [
            'label' => $label,
            'icon' => $icon,
            'hint' => 'ضمن نافذة الاتجاه (30 يومًا).',
            'model' => Order::class,
            'financial' => false,
            'metric' => 'count',
            'query' => static fn (): Builder => Order::query()
                ->whereIn('status', $statuses)->where('created_at', '>=', $since),
        ];
    }

    /** تحقّق بقائمة بيضاء من قيمة الشهر YYYY-MM → بداية الشهر، أو null إن كانت غير صالحة. */
    public static function parseMonth(?string $value): ?Carbon
    {
        if ($value === null || preg_match('/^\d{4}-\d{2}$/', $value) !== 1) {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $value.'-01')->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * تعريف مؤشّر واحد أو null إن كان المفتاح مجهولًا.
     *
     * @return array<string, mixed>|null
     */
    public static function get(string $key): ?array
    {
        return self::all()[$key] ?? null;
    }

    /**
     * الرقم الرئيسي للمؤشّر — يُحسب من نفس الاستعلام الأساسي فيطابق اللوحة تمامًا.
     * $value: معامِل المؤشّرات المُعامَلة (اسم محافظة/معرّف كتاب…)؛ تتجاهله المؤشّرات
     * الثابتة (إغلاقاتها بلا وسيط، وPHP يتجاهل الوسيط الزائد بأمان).
     */
    public static function metricValue(array $def, ?string $value = null): float
    {
        $query = ($def['query'])($value);

        return match ($def['metric']) {
            'sum' => (float) $query->sum($def['column']),
            'avg' => (float) ($query->avg($def['column']) ?? 0),
            default => (float) $query->count(),
        };
    }

    /** هل المؤشّر يحتاج معامِلًا (v=…)؟ */
    public static function isParam(array $def): bool
    {
        return ! empty($def['param']);
    }

    /** تسمية القيمة الودّية لعنوان الصفحة (اسم الكتاب/القسم بدل معرّفه). */
    public static function valueLabel(array $def, ?string $value): string
    {
        $resolver = $def['valueLabel'] ?? null;

        return $resolver ? (string) $resolver($value) : (string) $value;
    }
}
