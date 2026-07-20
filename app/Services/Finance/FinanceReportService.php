<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Models\Order;
use App\Models\OrderItem;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * مصدر الأرقام الوحيد للقسم المالي (المرحلة ١ — الإيراد فقط، بلا تكلفة).
 *
 * كل مقياس يمرّ عبر Order::scopeRealisedRevenue فتُطبَّق قاعدة «المحقّق» مرة
 * واحدة في مكان واحد (الدستور 2.3). الأيام تُجمَّع بتوقيت القاهرة لا UTC، لأن
 * المخزون UTC (config/app.php) بينما يوم العمل مصري — فطلب الساعة ١ صباحًا
 * بتوقيت القاهرة ينتمي لليوم الصحيح لا لليوم السابق.
 *
 * ملاحظة DST: مصر تتبع توقيتًا صيفيًا (+2/+3)، وجداول توقيت MySQL قد تغيب على
 * الاستضافة المشتركة، فنحسب حدود النطاق في PHP عبر Carbon (واعٍ بـ DST) ونُرشّح
 * على عمود created_at المفهرس بحدود UTC — بلا CONVERT_TZ في الاستعلام. أما وسم
 * اليوم فيُحوَّل للقاهرة في PHP بعد الجلب. هذا يتفادى إرجاع NULL الصامت من
 * CONVERT_TZ حين تنقص جداول التوقيت (الدستور 1.4 — لا نبني على مجهول).
 *
 * الأداء (5.4): استعلام تجميعي واحد لكل مقياس على أعمدة مفهرسة، ونتيجة كل نطاق
 * تُخزَّن مؤقتًا بمفتاح موسوم بإصدار يبطله OrderObserver عند أي تغيّر.
 */
class FinanceReportService
{
    private const TZ = 'Africa/Cairo';

    private const VERSION_KEY = 'finance.report.version';

    private const TTL_SECONDS = 300;

    /**
     * ملخّص المؤشرات لنطاق تاريخي (KPIs — المرحلة ١).
     *
     * @return array{
     *   gross_revenue:string, net_sales:string, discounts:string, shipping:string,
     *   orders:int, units:int, aov:?string, pipeline:string
     * }
     */
    public function summary(CarbonImmutable $from, CarbonImmutable $to): array
    {
        return $this->remember('summary', $from, $to, function () use ($from, $to): array {
            [$start, $end] = $this->utcBounds($from, $to);

            $realised = Order::query()
                ->realisedRevenue()
                ->whereBetween('created_at', [$start, $end])
                ->selectRaw('COALESCE(SUM(subtotal),0) AS gross')
                ->selectRaw('COALESCE(SUM(subtotal - discount_total),0) AS net')
                ->selectRaw('COALESCE(SUM(discount_total),0) AS discounts')
                ->selectRaw('COALESCE(SUM(shipping_total),0) AS shipping')
                ->selectRaw('COUNT(*) AS orders')
                ->first();

            // الوحدات المباعة: مجموع الكميات على سطور الطلبات المحقّقة. whereHas يعيد
            // استخدام نفس قاعدة «المحقّق» (استعلام فرعي واحد، بلا N+1 — الدستور 2.5)،
            // فلا تتكرّر القاعدة في مكانين. quantity عدد صحيح فالمجموع صحيح لا عشري.
            $units = (int) OrderItem::query()
                ->whereHas('order', function ($q) use ($start, $end): void {
                    $q->realisedRevenue()->whereBetween('created_at', [$start, $end]);
                })
                ->sum('quantity');

            // قيد التحصيل: طلبات حيّة لم تتحقّق بعد — تُعرض منفصلة لا ضمن الإيراد.
            // نستبعد المدفوع لأنه صار إيرادًا محقّقًا (فلا يُحتسب مرتين)، والحالات
            // النهائية (delivered/completed = محقّق، وcancelled/… = ميت) خارج القائمة.
            $pipeline = Order::query()
                ->whereIn('status', ['pending', 'confirmed', 'processing', 'shipped'])
                ->where('payment_status', '!=', 'paid')
                ->whereBetween('created_at', [$start, $end])
                ->sum('grand_total');

            $ordersCount = (int) ($realised->orders ?? 0);
            $net = (string) ($realised->net ?? '0');

            return [
                'gross_revenue' => $this->money($realised->gross ?? 0),
                'net_sales' => $this->money($net),
                'discounts' => $this->money($realised->discounts ?? 0),
                'shipping' => $this->money($realised->shipping ?? 0),
                'orders' => $ordersCount,
                'units' => $units,
                // متوسط الطلب: null حين لا طلبات (لا قسمة على صفر — يُعرض «غير محدد»).
                'aov' => $ordersCount > 0 ? $this->money(bcdiv($net, (string) $ordersCount, 2)) : null,
                'pipeline' => $this->money($pipeline),
            ];
        });
    }

    /**
     * السلسلة اليومية: صف لكل يوم في النطاق (أيام بلا طلبات تظهر بصفر لا بفجوة).
     * كل صف: {date:Y-m-d, orders:int, net_sales:string} بتوقيت القاهرة.
     *
     * @return Collection<int, array{date:string, orders:int, net_sales:string}>
     */
    public function dailySeries(CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        return $this->remember('daily', $from, $to, function () use ($from, $to): Collection {
            [$start, $end] = $this->utcBounds($from, $to);

            // نجلب الطلبات المحقّقة الخام (تاريخ UTC + صافي السطر) ونُجمّعها بيوم
            // القاهرة في PHP — يتفادى الاعتماد على CONVERT_TZ في القاعدة.
            $rows = Order::query()
                ->realisedRevenue()
                ->whereBetween('created_at', [$start, $end])
                ->get(['created_at', 'subtotal', 'discount_total']);

            $byDay = [];
            foreach ($rows as $row) {
                $day = Carbon::parse($row->created_at)->timezone(self::TZ)->format('Y-m-d');
                $byDay[$day] ??= ['orders' => 0, 'net' => '0'];
                $byDay[$day]['orders']++;
                $byDay[$day]['net'] = bcadd(
                    $byDay[$day]['net'],
                    bcsub((string) $row->subtotal, (string) $row->discount_total, 2),
                    2,
                );
            }

            // ملء كل يوم في النطاق بصفر إن غاب — لا فجوات في السلسلة الزمنية.
            //
            // نقارن بمفتاح التاريخ (Y-m-d) لا بكائن Carbon: في يوم انتقال التوقيت
            // الصيفي بمصر (نبيع منتصف الليل) لا وجود لـ 00:00 محليًا، فيُطبّعه
            // Carbon إلى 01:00 ويصبح cursor > last فيسقط أحدث يوم. المقارنة النصية
            // تتفادى ذلك، و startOfDay بعد addDay تعيد الإرساء لبداية اليوم المحلي.
            $series = collect();
            $cursor = $from->timezone(self::TZ)->startOfDay();
            $lastKey = $to->timezone(self::TZ)->format('Y-m-d');

            // سقف أمان: امتداد النطاق مقيّد أصلًا، وهذا يمنع أي حلقة لا نهائية.
            for ($guard = 0; $guard <= 400; $guard++) {
                $key = $cursor->format('Y-m-d');
                $series->push([
                    'date' => $key,
                    'orders' => $byDay[$key]['orders'] ?? 0,
                    'net_sales' => $this->money($byDay[$key]['net'] ?? 0),
                ]);

                if ($key === $lastKey) {
                    break;
                }

                $cursor = $cursor->addDay()->startOfDay();
            }

            return $series;
        });
    }

    /**
     * تقسيم الإيراد المحقّق حسب بُعد مفهرس (payment_method / status / governorate).
     * القيمة العمود؛ الوسم يُترجَم في الواجهة لا هنا (الدستور 6.4).
     *
     * @return Collection<int, array{key:?string, orders:int, net_sales:string}>
     */
    public function breakdownBy(string $column, CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        // قائمة بيضاء صارمة: لا نُركّب اسم عمود من مدخل مباشرة (الدستور 4.2).
        $allowed = ['payment_method', 'status', 'governorate'];
        if (! in_array($column, $allowed, true)) {
            throw new \InvalidArgumentException("عمود تقسيم غير مسموح: {$column}");
        }

        return $this->remember("breakdown.{$column}", $from, $to, function () use ($column, $from, $to): Collection {
            [$start, $end] = $this->utcBounds($from, $to);

            return Order::query()
                ->realisedRevenue()
                ->whereBetween('created_at', [$start, $end])
                ->groupBy($column)
                ->orderByRaw('SUM(subtotal - discount_total) DESC')
                ->get([
                    DB::raw("{$column} AS bucket"),
                    DB::raw('COUNT(*) AS orders'),
                    DB::raw('COALESCE(SUM(subtotal - discount_total),0) AS net'),
                ])
                ->map(fn ($r): array => [
                    'key' => $r->bucket,
                    'orders' => (int) $r->orders,
                    'net_sales' => $this->money($r->net),
                ]);
        });
    }

    /**
     * يبطل كل تقارير الكاش برفع رقم الإصدار — يستدعيه OrderObserver عند أي حفظ/حذف.
     * مخزن الكاش الافتراضي (database) لا يدعم الوسوم (Cache::tags)، فرفع إصدار
     * يُدمج في كل المفاتيح هو البديل المتوافق: تصير كل المفاتيح القديمة غير مقروءة
     * دفعة واحدة. تعارُض التزامن غير مؤثّر هنا (لوحة أدمن، متجر صغير) وأسوأه قراءة
     * قديمة عابرة تنتهي عند أول تحديث.
     */
    public function flush(): void
    {
        Cache::forever(self::VERSION_KEY, $this->version() + 1);
    }

    // ----- داخليّات -------------------------------------------------------

    /**
     * حدود UTC ليوم القاهرة: بداية اليوم المحلي ونهايته محوّلتان إلى UTC، حتى
     * يطابق الترشيح على created_at (UTC) حدود يوم العمل المصري بدقة DST.
     *
     * @return array{0:CarbonImmutable, 1:CarbonImmutable}
     */
    private function utcBounds(CarbonImmutable $from, CarbonImmutable $to): array
    {
        return [
            $from->timezone(self::TZ)->startOfDay()->utc(),
            $to->timezone(self::TZ)->endOfDay()->utc(),
        ];
    }

    private function money(string|int|float $value): string
    {
        return bcadd((string) $value, '0', 2);
    }

    /**
     * @template T
     *
     * @param  callable():T  $callback
     * @return T
     */
    private function remember(string $metric, CarbonImmutable $from, CarbonImmutable $to, callable $callback)
    {
        $key = sprintf(
            'finance.report.v%d.%s.%s.%s',
            $this->version(),
            $metric,
            $from->timezone(self::TZ)->format('Ymd'),
            $to->timezone(self::TZ)->format('Ymd'),
        );

        // rescue: التقرير يجب ألا يكسر لوحة الأدمن قبل تنفيذ الهجرات/البذور.
        // نمرّر $callback إغلاقًا لا $callback() — الوسيط الثاني يُقيَّم فور
        // استدعاء rescue، فتمرير النتيجة يُشغّل الاستعلام في كل طلب (يُبطل الكاش)
        // ويرمي قبل try/catch عند غياب الجداول (يُسقط اللوحة بدل التدرّج). كإغلاق
        // يُستدعى كسلًا داخل catch فقط، بلا كاش، عند الفشل الحقيقي.
        return rescue(
            fn () => Cache::remember($key, self::TTL_SECONDS, $callback),
            $callback,
            report: false,
        );
    }

    private function version(): int
    {
        return (int) (Cache::get(self::VERSION_KEY) ?? 1);
    }
}
