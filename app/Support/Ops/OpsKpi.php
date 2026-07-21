<?php

declare(strict_types=1);

namespace App\Support\Ops;

use App\Models\Book;
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
 * اختبار OpsKpiParityTest يحرس هذا التطابق ضدّ الانحراف مستقبلًا.
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
            'orders_week' => [
                'label' => 'طلبات آخر 7 أيام',
                'icon' => '🗓️',
                'hint' => 'الطلبات المُنشأة خلال آخر سبعة أيام.',
                'model' => Order::class,
                'financial' => false,
                'metric' => 'count',
                'query' => static fn (): Builder => Order::query()->where('created_at', '>=', Carbon::now()->subDays(7)),
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
        ];
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
     */
    public static function metricValue(array $def): float
    {
        $query = ($def['query'])();

        return match ($def['metric']) {
            'sum' => (float) $query->sum($def['column']),
            'avg' => (float) ($query->avg($def['column']) ?? 0),
            default => (float) $query->count(),
        };
    }
}
