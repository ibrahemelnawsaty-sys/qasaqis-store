<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Models\Expense;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
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
        }, [
            'gross_revenue' => '0.00', 'net_sales' => '0.00', 'discounts' => '0.00',
            'shipping' => '0.00', 'orders' => 0, 'units' => 0, 'aov' => null, 'pipeline' => '0.00',
        ]);
    }

    /**
     * الربح (المرحلة ٢): تكلفة البضاعة المباعة ومجمل الربح والهامش.
     *
     * قاعدة السلامة (إصلاح المراجعة العدائية): الربح والهامش يُحسبان حصريًا على
     * الطلبات المحقّقة التي **كل** سطورها معروفة التكلفة — فلا نطرح تكلفة جزئية
     * من إيراد كامل فينتفخ الربح. الطلبات ذات سطر بلا تكلفة (تاريخية أو كتاب بلا
     * تكلفة مُدخلة) تُعدّ منفصلة في «orders_unknown_cost» وتُستبعد كليًّا من الحساب.
     * حين لا طلب معروف التكلفة، الربح/الهامش = null (يُعرض «غير متاح» لا صفرًا).
     *
     * @return array{
     *   cogs:string, gross_profit:?string, margin_pct:?string, net_costed:string,
     *   orders_costed:int, orders_unknown_cost:int, orders_estimated:int
     * }
     */
    public function profit(CarbonImmutable $from, CarbonImmutable $to): array
    {
        return $this->remember('profit', $from, $to, function () use ($from, $to): array {
            [$start, $end] = $this->utcBounds($from, $to);

            // مجموعة «معروفة التكلفة بالكامل»: محقّقة، لها سطور، ولا سطر بلا تكلفة.
            $costedOrders = fn () => Order::query()
                ->realisedRevenue()
                ->whereBetween('created_at', [$start, $end])
                ->whereHas('items')
                ->whereDoesntHave('items', fn ($q) => $q->whereNull('unit_cost'));

            $agg = $costedOrders()
                ->selectRaw('COALESCE(SUM(subtotal - discount_total),0) AS net')
                ->selectRaw('COUNT(*) AS orders')
                ->first();

            // COGS على سطور تلك المجموعة فقط (استعلام فرعي واحد، بلا N+1).
            $cogs = (string) OrderItem::query()
                ->whereHas('order', function ($q) use ($start, $end): void {
                    $q->realisedRevenue()
                        ->whereBetween('created_at', [$start, $end])
                        ->whereHas('items')
                        ->whereDoesntHave('items', fn ($i) => $i->whereNull('unit_cost'));
                })
                ->sum('line_cost');

            // طلبات محقّقة فيها سطر واحد على الأقل بلا تكلفة — تُعرض كتنبيه صدق.
            $unknown = Order::query()
                ->realisedRevenue()
                ->whereBetween('created_at', [$start, $end])
                ->whereHas('items', fn ($q) => $q->whereNull('unit_cost'))
                ->count();

            // طلبات مُدرَجة في الحساب (معروفة التكلفة بالكامل) فيها سطر تقديري —
            // تنبيه أمانة أن جزءًا من الربح مبنيّ على تقدير (1.4). نقيّده بنفس سكان
            // costedOrders كي يبقى orders_estimated ⊆ orders_costed دائمًا، فلا
            // يُحتسب تقديرُ طلبٍ مستبعدٍ أصلًا من الربح (سطرٌ آخر فيه بلا تكلفة).
            $estimatedOrders = $costedOrders()
                ->whereHas('items', fn ($q) => $q->where('cost_is_estimated', true))
                ->count();

            $net = (string) ($agg->net ?? '0');
            $orders = (int) ($agg->orders ?? 0);
            $cogs = $this->money($cogs);

            $grossProfit = $orders > 0 ? $this->money(bcsub($net, $cogs, 2)) : null;
            $marginPct = ($orders > 0 && bccomp($net, '0', 2) === 1)
                ? bcmul(bcdiv($grossProfit, $net, 6), '100', 2)
                : null;

            return [
                'cogs' => $cogs,
                'gross_profit' => $grossProfit,
                'margin_pct' => $marginPct,
                'net_costed' => $this->money($net),
                'orders_costed' => $orders,
                'orders_unknown_cost' => $unknown,
                'orders_estimated' => $estimatedOrders,
            ];
        }, [
            'cogs' => '0.00', 'gross_profit' => null, 'margin_pct' => null,
            'net_costed' => '0.00', 'orders_costed' => 0, 'orders_unknown_cost' => 0,
            'orders_estimated' => 0,
        ]);
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
        }, collect());
    }

    /**
     * الشحن وربح المساهمة (المرحلة ٣).
     *
     * هامش الشحن يُحسب فقط على الطلبات المحقّقة معروفة تكلفة الشركة (carrier_cost
     * غير NULL): المحصَّل من العميل ناقص المدفوع للشركة. الطلبات بلا تكلفة شركة
     * (تاريخية أو لم تصل فاتورتها) تُعدّ في orders_carrier_unknown وتُستبعد.
     *
     * ربح المساهمة يُحسب على التقاطع الصارم: طلبات معروفة تكلفة بضاعتها بالكامل
     * **و** تكلفة شركتها = (صافي المبيعات + الشحن المحصَّل) − COGS − تكلفة الشركة.
     * نُدرج الشحن المحصَّل عمدًا: هو إيراد يقابل تكلفة الشركة، فطرح التكلفة دون
     * إيرادها يُنقص الرقم بمقدار الشحن (يجعل المساهمة = مجمل الربح + هامش الشحن).
     * لا نجمع سكانًا مختلفة (سلامة السكان التي أوجبتها المراجعة العدائية).
     *
     * @return array{
     *   carrier_cost:string, shipping_charged:string, shipping_margin:?string,
     *   orders_carrier_known:int, orders_carrier_unknown:int,
     *   contribution:?string, orders_contribution:int
     * }
     */
    public function shipping(CarbonImmutable $from, CarbonImmutable $to): array
    {
        return $this->remember('shipping', $from, $to, function () use ($from, $to): array {
            [$start, $end] = $this->utcBounds($from, $to);

            $carrierKnown = fn () => Order::query()
                ->realisedRevenue()
                ->whereBetween('created_at', [$start, $end])
                ->whereNotNull('carrier_cost');

            $ship = $carrierKnown()
                ->selectRaw('COALESCE(SUM(shipping_total),0) AS charged')
                ->selectRaw('COALESCE(SUM(carrier_cost),0) AS carrier')
                ->selectRaw('COUNT(*) AS orders')
                ->first();

            $charged = $this->money($ship->charged ?? 0);
            $carrier = $this->money($ship->carrier ?? 0);
            $knownCount = (int) ($ship->orders ?? 0);

            $unknownCarrier = Order::query()
                ->realisedRevenue()
                ->whereBetween('created_at', [$start, $end])
                ->whereNull('carrier_cost')
                ->count();

            $shippingMargin = $knownCount > 0 ? $this->money(bcsub($charged, $carrier, 2)) : null;

            // التقاطع «مكتمل البيانات»: معروفة التكلفة بالكامل + معروفة تكلفة الشركة.
            $contribAgg = $this->completeOrders($start, $end)
                ->selectRaw('COALESCE(SUM(subtotal - discount_total),0) AS net')
                ->selectRaw('COALESCE(SUM(shipping_total),0) AS shipping')
                ->selectRaw('COALESCE(SUM(carrier_cost),0) AS carrier')
                ->selectRaw('COUNT(*) AS orders')
                ->first();

            $contribCount = (int) ($contribAgg->orders ?? 0);
            $contribution = null;

            if ($contribCount > 0) {
                $contribCogs = (string) OrderItem::query()
                    ->whereHas('order', fn (Builder $q) => $this->applyComplete($q, $start, $end))
                    ->sum('line_cost');

                // (صافي + شحن محصَّل) − COGS − تكلفة الشركة، على مجموعة التقاطع.
                $revenue = bcadd((string) ($contribAgg->net ?? '0'), (string) ($contribAgg->shipping ?? '0'), 2);
                $contribution = $this->money(bcsub(
                    bcsub($revenue, $this->money($contribCogs), 2),
                    $this->money($contribAgg->carrier ?? 0),
                    2,
                ));
            }

            return [
                'carrier_cost' => $carrier,
                'shipping_charged' => $charged,
                'shipping_margin' => $shippingMargin,
                'orders_carrier_known' => $knownCount,
                'orders_carrier_unknown' => $unknownCarrier,
                'contribution' => $contribution,
                'orders_contribution' => $contribCount,
            ];
        }, [
            'carrier_cost' => '0.00', 'shipping_charged' => '0.00', 'shipping_margin' => null,
            'orders_carrier_known' => 0, 'orders_carrier_unknown' => 0,
            'contribution' => null, 'orders_contribution' => 0,
        ]);
    }

    /**
     * صافي ربح النشاط (المرحلة ٤): يبني على ربح المساهمة (م٢-٣) بخصم ما يستنزفه
     * النشاط فعليًا في النطاق: المرتجعات الجزئية + رسوم المعالجة + المصروفات.
     *
     * سلامة السكان (إصلاح المراجعة العدائية): المرتجعات والرسوم تُجمَّعان على **نفس**
     * مجموعة التقاطع «مكتملة البيانات» التي أنتجت المساهمة — لا على كل الطلبات
     * المحقّقة. وإلا خُصمت مرتجعات/رسوم طلبٍ استُبعد ربحه (تكلفة شحن لم تصل بعد)،
     * فينقص صافي الربح دون مقابله من المساهمة. المصروفات وحدها على مستوى الفترة
     * لأنها غير مرتبطة بطلب. القيم المجهولة (NULL) تُعامَل صفرًا (غياب خصمٍ = لا خصم).
     *
     * @return array{
     *   refunds:string, fees:string, expenses:string,
     *   net_profit:?string, contribution:?string
     * }
     */
    public function netProfit(CarbonImmutable $from, CarbonImmutable $to): array
    {
        return $this->remember('net_profit', $from, $to, function () use ($from, $to): array {
            [$start, $end] = $this->utcBounds($from, $to);

            // المرتجعات الجزئية على مجموعة التقاطع نفسها (المرتجع الكلّي = status
            // refunded مستبعد أصلًا من التقاطع). نفس سكان المساهمة → اتساق تام.
            $refunds = (string) $this->completeOrders($start, $end)
                ->whereNotNull('refunded_amount')
                ->sum('refunded_amount');

            // رسوم المعالجة على مدفوعات طلبات التقاطع نفسها.
            $fees = (string) Payment::query()
                ->whereNotNull('fee_amount')
                ->whereHas('order', fn (Builder $q) => $this->applyComplete($q, $start, $end))
                ->sum('fee_amount');

            // المصروفات بتاريخ صرفها (بيوم القاهرة) داخل النطاق.
            $expenses = (string) Expense::query()
                ->whereBetween('incurred_on', [
                    $from->timezone(self::TZ)->toDateString(),
                    $to->timezone(self::TZ)->toDateString(),
                ])
                ->sum('amount');

            // نبني على ربح المساهمة (م٣). قد يكون null حين لا طلب مكتمل البيانات؛
            // عندها صافي الربح null أيضًا (لا نعرض رقمًا مبنيًّا على مجهول).
            $contribution = $this->shipping($from, $to)['contribution'];

            $netProfit = $contribution === null ? null : $this->money(bcsub(bcsub(bcsub(
                $contribution,
                $this->money($refunds), 2),
                $this->money($fees), 2),
                $this->money($expenses), 2));

            return [
                'refunds' => $this->money($refunds),
                'fees' => $this->money($fees),
                'expenses' => $this->money($expenses),
                'contribution' => $contribution,
                'net_profit' => $netProfit,
            ];
        }, [
            'refunds' => '0.00', 'fees' => '0.00', 'expenses' => '0.00',
            'contribution' => null, 'net_profit' => null,
        ]);
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
        }, collect());
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
     * يطبّق قيود مجموعة «مكتملة البيانات» على استعلام طلبات: محقّقة، وتكلفة شحنها
     * معروفة، ولها سطور، ولا سطر بلا تكلفة. مصدر واحد يضمن أن المساهمة والمرتجعات
     * والرسوم تُحسب على نفس السكان (سلامة السكان، الدستور 2.3).
     */
    private function applyComplete(Builder $query, CarbonImmutable $start, CarbonImmutable $end): void
    {
        $query->realisedRevenue()
            ->whereBetween('created_at', [$start, $end])
            ->whereNotNull('carrier_cost')
            ->whereHas('items')
            ->whereDoesntHave('items', fn (Builder $q) => $q->whereNull('unit_cost'));
    }

    /**
     * استعلام جديد لمجموعة «مكتملة البيانات».
     */
    private function completeOrders(CarbonImmutable $start, CarbonImmutable $end): Builder
    {
        $query = Order::query();
        $this->applyComplete($query, $start, $end);

        return $query;
    }

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
    /**
     * @template T
     *
     * @param  callable():T  $callback
     * @param  T  $default  قيمة آمنة تُعاد إن فشل الاستعلام (شكل المقياس فارغًا)
     * @return T
     */
    private function remember(string $metric, CarbonImmutable $from, CarbonImmutable $to, callable $callback, mixed $default)
    {
        $key = sprintf(
            'finance.report.v%d.%s.%s.%s',
            $this->version(),
            $metric,
            $from->timezone(self::TZ)->format('Ymd'),
            $to->timezone(self::TZ)->format('Ymd'),
        );

        // rescue بقيمة آمنة ثابتة (لا إعادة تشغيل الاستعلام): لو غاب جزء من المخطط
        // قبل تنفيذ الهجرات على الإنتاج، تتدرّج اللوحة لأرقام فارغة بدل 500. تمرير
        // $callback نفسه كبديل كان يُعيد الاستعلام داخل catch فيرمي ثانيةً بلا
        // التقاط — وهو سبب انهيار اللوحة قبل الهجرات. القيمة الثابتة تُصلحه.
        return rescue(
            fn () => Cache::remember($key, self::TTL_SECONDS, $callback),
            $default,
            report: false,
        );
    }

    private function version(): int
    {
        return (int) (Cache::get(self::VERSION_KEY) ?? 1);
    }
}
