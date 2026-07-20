<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\Book;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\Finance\FinanceReportService;
use Carbon\CarbonImmutable;
use Database\Factories\OrderFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * قلب القسم المالي: قاعدة «الإيراد المحقّق» وتجميعها (المرحلة ١).
 *
 * القاعدة المعتمدة: المدفوع مقدّمًا يُحتسب عند payment_status=paid؛ وCOD يُحتسب
 * عند status ∈ (delivered, completed)؛ ويُستبعد الملغى/المرفوض/المرتجع/المعلّق.
 * هذه الاختبارات تُثبت أن طلب COD مُسلَّم (يبقى unpaid) يُحتسب فعلًا — العيب الذي
 * كان سيُخفي كل مبيعات مصر.
 */
final class FinanceReportServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): FinanceReportService
    {
        return app(FinanceReportService::class);
    }

    private function range(): array
    {
        $now = CarbonImmutable::now('Africa/Cairo');

        return [$now->subDays(7)->startOfDay(), $now->endOfDay()];
    }

    public function test_cod_delivered_counts_as_revenue_even_though_it_stays_unpaid(): void
    {
        // طلب COD مُسلَّم — payment_status يبقى unpaid (لا شيء يحوّله)، لكنه محقّق.
        OrderFactory::new()->create([
            'status' => 'delivered',
            'payment_method' => 'cod',
            'payment_status' => 'unpaid',
            'subtotal' => '300.00',
            'discount_total' => '0.00',
        ]);

        [$from, $to] = $this->range();
        $summary = $this->service()->summary($from, $to);

        $this->assertSame(1, $summary['orders']);
        $this->assertSame('300.00', $summary['net_sales']);
    }

    public function test_prepaid_paid_counts_and_pending_cod_does_not(): void
    {
        OrderFactory::new()->create([
            'status' => 'processing', 'payment_method' => 'online_gateway',
            'payment_status' => 'paid', 'subtotal' => '200.00', 'discount_total' => '0.00',
        ]);
        // COD مؤكّد لكنه لم يُسلَّم بعد ⇒ قيد التحصيل، لا إيراد.
        OrderFactory::new()->create([
            'status' => 'confirmed', 'payment_method' => 'cod',
            'payment_status' => 'unpaid', 'subtotal' => '500.00', 'grand_total' => '500.00',
        ]);

        [$from, $to] = $this->range();
        $summary = $this->service()->summary($from, $to);

        $this->assertSame(1, $summary['orders']);
        $this->assertSame('200.00', $summary['net_sales']);
        // الطلب المؤكّد غير المحقّق يظهر في «قيد التحصيل» فقط.
        $this->assertSame('500.00', $summary['pipeline']);
    }

    public function test_cancelled_refused_refunded_are_excluded_even_when_paid(): void
    {
        // حالة حرجة: طلب دُفع ثم أُلغي/رُدّ يجب ألا يُحتسب إيرادًا — الاستبعاد يعلو على paid.
        foreach (['cancelled', 'refused', 'refunded'] as $status) {
            OrderFactory::new()->create([
                'status' => $status, 'payment_status' => 'paid', 'subtotal' => '999.00',
            ]);
        }

        [$from, $to] = $this->range();
        $summary = $this->service()->summary($from, $to);

        $this->assertSame(0, $summary['orders']);
        $this->assertSame('0.00', $summary['net_sales']);
    }

    public function test_prepaid_unpaid_but_marked_delivered_is_not_counted(): void
    {
        // عيب أمسكته المراجعة العدائية: طلب أونلاين غير مدفوع وُضع يدويًا على «مُسلَّم»
        // كان يُحتسب إيرادًا لم يُحصَّل. فرع delivered/completed مقصور على COD.
        OrderFactory::new()->create([
            'status' => 'delivered', 'payment_method' => 'online_gateway',
            'payment_status' => 'unpaid', 'subtotal' => '500.00',
        ]);
        // وطلب تحويل يدوي قيد المراجعة وُضع على «مكتمل» — أيضًا لا يُحتسب.
        OrderFactory::new()->create([
            'status' => 'completed', 'payment_method' => 'bank_transfer',
            'payment_status' => 'pending_review', 'subtotal' => '300.00',
        ]);

        [$from, $to] = $this->range();
        $summary = $this->service()->summary($from, $to);

        $this->assertSame(0, $summary['orders'], 'المدفوع مقدّمًا لا يُحتسب إلا عند paid');
        $this->assertSame('0.00', $summary['net_sales']);
    }

    public function test_daily_series_keeps_newest_day_across_dst_spring_forward(): void
    {
        // مصر تقدّم الساعة في 2026-04-24 (لا وجود لـ 00:00 محليًا). نطاق يعبر هذا
        // اليوم كان يُسقط أحدث يوم بصمت. نتحقق أن كل الأيام حاضرة والأخير موجود.
        $tz = 'Africa/Cairo';
        $from = CarbonImmutable::create(2026, 4, 20, 12, 0, 0, $tz);
        $to = CarbonImmutable::create(2026, 4, 28, 12, 0, 0, $tz);

        $series = $this->service()->dailySeries($from, $to);

        $this->assertCount(9, $series, 'من 20 إلى 28 أبريل = 9 أيام بلا إسقاط');
        $this->assertSame('2026-04-28', $series->last()['date']);
        $this->assertSame('2026-04-24', $series->get(4)['date'], 'يوم الانتقال حاضر وبالمفتاح الصحيح');
    }

    public function test_net_sales_subtracts_discount(): void
    {
        OrderFactory::new()->create([
            'status' => 'completed', 'payment_status' => 'unpaid', 'payment_method' => 'cod',
            'subtotal' => '400.00', 'discount_total' => '50.00',
        ]);

        [$from, $to] = $this->range();
        $summary = $this->service()->summary($from, $to);

        $this->assertSame('350.00', $summary['net_sales']);
        $this->assertSame('400.00', $summary['gross_revenue']);
        $this->assertSame('50.00', $summary['discounts']);
    }

    public function test_aov_is_null_with_zero_orders_not_a_division_error(): void
    {
        [$from, $to] = $this->range();
        $summary = $this->service()->summary($from, $to);

        $this->assertSame(0, $summary['orders']);
        $this->assertNull($summary['aov']);
    }

    public function test_units_sum_over_realised_order_lines_only(): void
    {
        $realised = OrderFactory::new()->create([
            'status' => 'delivered', 'payment_status' => 'unpaid', 'payment_method' => 'cod',
        ]);
        $pending = OrderFactory::new()->create(['status' => 'pending', 'payment_status' => 'unpaid']);
        $book = Book::factory()->create(['price' => '100.00']);

        $realised->items()->create(['book_id' => $book->id, 'book_title' => $book->title,
            'unit_price' => '100.00', 'quantity' => 3, 'line_total' => '300.00']);
        $pending->items()->create(['book_id' => $book->id, 'book_title' => $book->title,
            'unit_price' => '100.00', 'quantity' => 9, 'line_total' => '900.00']);

        [$from, $to] = $this->range();
        $summary = $this->service()->summary($from, $to);

        $this->assertSame(3, $summary['units']); // فقط سطور الطلب المحقّق.
    }

    public function test_daily_series_zero_fills_empty_days(): void
    {
        // بلا created_at صريح: يأخذ now() الحقيقي (UTC) فيقع في يوم القاهرة الحالي.
        OrderFactory::new()->create([
            'status' => 'completed', 'payment_status' => 'unpaid', 'payment_method' => 'cod',
            'subtotal' => '100.00',
        ]);

        $now = CarbonImmutable::now('Africa/Cairo');
        $series = $this->service()->dailySeries($now->subDays(4)->startOfDay(), $now->endOfDay());

        // ٥ أيام في النطاق ⇒ ٥ صفوف، بلا فجوات، والأيام الفارغة بصفر.
        $this->assertCount(5, $series);
        $this->assertSame(0, $series->first()['orders']);
        $this->assertSame(1, $series->last()['orders']);
    }

    public function test_cairo_timezone_buckets_late_night_order_into_correct_local_day(): void
    {
        // 22:30 UTC = 00:30 القاهرة (شتاءً +2) لليوم التالي. يجب أن يُنسب لليوم المحلي.
        $utcLateNight = CarbonImmutable::create(2026, 1, 15, 22, 30, 0, 'UTC');
        $order = OrderFactory::new()->create([
            'status' => 'completed', 'payment_status' => 'unpaid', 'payment_method' => 'cod',
            'subtotal' => '100.00',
        ]);
        // created_at ليس fillable، ونضبطه بدقة كـ UTC عبر تحديث خام (تمرير Carbon
        // بتوقيت القاهرة عبر create يخزّنه Eloquent خطأً فيخرج من النطاق).
        DB::table('orders')->where('id', $order->id)
            ->update(['created_at' => $utcLateNight->format('Y-m-d H:i:s')]);

        $cairo = $utcLateNight->timezone('Africa/Cairo'); // 2026-01-16 00:30
        $series = $this->service()->dailySeries(
            $cairo->subDay()->startOfDay(),
            $cairo->addDay()->endOfDay(),
        );

        $localDay = $series->firstWhere('date', '2026-01-16');
        $this->assertNotNull($localDay);
        $this->assertSame(1, $localDay['orders'], 'طلب منتصف الليل يجب أن يُنسب ليوم القاهرة لا UTC');
        // ولا يُنسب لليوم السابق (UTC).
        $this->assertSame(0, $series->firstWhere('date', '2026-01-15')['orders']);
    }

    public function test_breakdown_rejects_a_non_whitelisted_column(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        [$from, $to] = $this->range();
        // محاولة حقن اسم عمود خارج القائمة البيضاء تُرفض (الدستور 4.2).
        $this->service()->breakdownBy('subtotal); DROP TABLE orders; --', $from, $to);
    }

    public function test_breakdown_by_payment_method_groups_realised_only(): void
    {
        OrderFactory::new()->create([
            'status' => 'completed', 'payment_status' => 'unpaid', 'payment_method' => 'cod',
            'subtotal' => '100.00', 'discount_total' => '0.00',
        ]);
        OrderFactory::new()->create([
            'status' => 'processing', 'payment_status' => 'paid', 'payment_method' => 'online_gateway',
            'subtotal' => '250.00', 'discount_total' => '0.00',
        ]);
        OrderFactory::new()->create([ // معلّق ⇒ مستبعد
            'status' => 'pending', 'payment_status' => 'unpaid', 'payment_method' => 'cod',
            'subtotal' => '999.00',
        ]);

        [$from, $to] = $this->range();
        $rows = $this->service()->breakdownBy('payment_method', $from, $to);

        $this->assertCount(2, $rows); // cod + online_gateway، بلا المعلّق
        $this->assertSame('250.00', $rows->firstWhere('key', 'online_gateway')['net_sales']);
    }
}
