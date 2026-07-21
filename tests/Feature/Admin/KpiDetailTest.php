<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Filament\Pages\KpiDetail;
use App\Models\Book;
use App\Models\Order;
use App\Models\User;
use App\Support\Ops\OpsKpi;
use Database\Factories\OrderFactory;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * صفحة تفاصيل المؤشّر (KPI) + سِجل OpsKpi.
 *
 * الأهمّ: **دقّة الرقم** — الرقم الرئيسي محسوب من نفس استعلام السِجل، والاستعلام
 * يختار الصفوف الصحيحة بالضبط. + الحجب المالي خادميًّا، والمفتاح المجهول 404.
 *
 * HONESTY (1.3/1.5): تعمل عبر php artisan test (MariaDB محليًا).
 */
final class KpiDetailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function admin(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user->fresh();
    }

    // ── دقّة الرقم والاستعلام ──────────────────────────────────────────────

    public function test_the_confirm_kpi_counts_exactly_pending_unconfirmed_orders(): void
    {
        // مطابق: pending بلا تأكيد واتساب.
        OrderFactory::new()->create(['status' => 'pending', 'whatsapp_confirmed_at' => null]);
        OrderFactory::new()->create(['status' => 'pending', 'whatsapp_confirmed_at' => null]);
        // غير مطابق: pending لكنه مؤكّد، وحالة أخرى.
        OrderFactory::new()->create(['status' => 'pending', 'whatsapp_confirmed_at' => now()]);
        OrderFactory::new()->create(['status' => 'shipped']);

        $def = OpsKpi::get('confirm');

        // الرقم الرئيسي = العدّ الحقيقي المباشر (لا انحراف).
        $this->assertSame(2.0, OpsKpi::metricValue($def));
        $direct = Order::where('status', 'pending')->whereNull('whatsapp_confirmed_at')->count();
        $this->assertSame(2, $direct);
        $this->assertSame(2, ($def['query'])()->count());
    }

    public function test_the_shipped_kpi_selects_only_shipped_orders(): void
    {
        $shipped = OrderFactory::new()->create(['status' => 'shipped']);
        OrderFactory::new()->create(['status' => 'delivered']);

        $ids = (OpsKpi::get('shipped')['query'])()->pluck('id')->all();

        $this->assertSame([$shipped->id], $ids);
    }

    public function test_the_realized_revenue_kpi_sums_delivered_and_completed_totals(): void
    {
        OrderFactory::new()->create(['status' => 'delivered', 'grand_total' => '100.00']);
        OrderFactory::new()->create(['status' => 'completed', 'grand_total' => '250.00']);
        OrderFactory::new()->create(['status' => 'shipped', 'grand_total' => '999.00']); // غير محقَّق

        $this->assertSame(350.0, OpsKpi::metricValue(OpsKpi::get('revenue_realized')));
    }

    public function test_the_aov_kpi_averages_realized_order_totals(): void
    {
        OrderFactory::new()->create(['status' => 'delivered', 'grand_total' => '100.00']);
        OrderFactory::new()->create(['status' => 'completed', 'grand_total' => '300.00']);
        OrderFactory::new()->create(['status' => 'cancelled', 'grand_total' => '999.00']); // غير محقَّق

        $this->assertSame(200.0, OpsKpi::metricValue(OpsKpi::get('aov')));
    }

    // ── المؤشّرات المُعامَلة بقيمة (محافظة/كتاب/شهر) ────────────────────────

    public function test_the_governorate_kpi_filters_orders_by_value(): void
    {
        OrderFactory::new()->create(['governorate' => 'القاهرة']);
        OrderFactory::new()->create(['governorate' => 'القاهرة']);
        OrderFactory::new()->create(['governorate' => 'الجيزة']);

        $this->assertSame(2.0, OpsKpi::metricValue(OpsKpi::get('governorate'), 'القاهرة'));
    }

    public function test_the_book_kpi_finds_orders_that_contain_the_book(): void
    {
        $bookA = Book::factory()->create();
        $bookB = Book::factory()->create();

        $orderA = OrderFactory::new()->create();
        $orderA->items()->create(['book_id' => $bookA->id, 'book_title' => $bookA->title, 'unit_price' => '50.00', 'quantity' => 1, 'line_total' => '50.00']);
        $orderB = OrderFactory::new()->create();
        $orderB->items()->create(['book_id' => $bookB->id, 'book_title' => $bookB->title, 'unit_price' => '50.00', 'quantity' => 1, 'line_total' => '50.00']);

        $ids = (OpsKpi::get('book')['query'])((string) $bookA->id)->pluck('id')->all();

        $this->assertSame([$orderA->id], $ids);
    }

    public function test_the_month_value_is_whitelisted_and_invalid_yields_no_rows(): void
    {
        $this->assertNotNull(OpsKpi::parseMonth('2026-07'));
        $this->assertNull(OpsKpi::parseMonth('garbage'));
        $this->assertNull(OpsKpi::parseMonth("2026-07'; DROP TABLE orders; --"));

        OrderFactory::new()->create(); // طلب هذا الشهر

        $thisMonth = now()->format('Y-m');
        $this->assertGreaterThanOrEqual(1.0, OpsKpi::metricValue(OpsKpi::get('month'), $thisMonth));
        // قيمة غير صالحة → لا صفوف (whereRaw 1=0)، لا كشف كل الطلبات.
        $this->assertSame(0.0, OpsKpi::metricValue(OpsKpi::get('month'), 'garbage'));
    }

    public function test_a_parametrized_kpi_without_a_value_is_not_found(): void
    {
        $this->actingAs($this->admin('super_admin'))
            ->get(KpiDetail::getUrl(['kpi' => 'governorate']))   // بلا v
            ->assertNotFound();
    }

    public function test_the_value_property_is_locked_against_client_tampering(): void
    {
        $property = new \ReflectionProperty(KpiDetail::class, 'value');

        $this->assertNotEmpty(
            $property->getAttributes(\Livewire\Attributes\Locked::class),
            'خاصية value يجب أن تحمل #[Locked] كبقية معاملات المؤشّر.'
        );
    }

    // ── العرض + التفويض ────────────────────────────────────────────────────

    public function test_the_kpi_property_is_locked_against_client_tampering(): void
    {
        // #[Locked] يمنع تبديل المؤشّر عبر تحديث Livewire — مسار تسريب الأرقام المالية
        // (مستخدم غير ماليّ يفتح مؤشّرًا عاديًّا ثم يبدّله إلى ماليّ). حارس ضدّ التراجع.
        $property = new \ReflectionProperty(KpiDetail::class, 'kpi');

        $this->assertNotEmpty(
            $property->getAttributes(\Livewire\Attributes\Locked::class),
            'خاصية kpi يجب أن تحمل #[Locked] كي لا تُبدَّل من العميل بعد mount.'
        );
    }

    public function test_the_detail_page_renders_the_kpi_and_its_underlying_rows(): void
    {
        $order = OrderFactory::new()->create(['status' => 'pending', 'whatsapp_confirmed_at' => null]);

        $response = $this->actingAs($this->admin('super_admin'))
            ->get(KpiDetail::getUrl(['kpi' => 'confirm']));

        $response->assertOk();
        $response->assertSee('تنتظر تأكيد واتساب', false);   // عنوان المؤشّر
        $response->assertSee($order->order_number, false);    // الصفّ الأساسي خلف الرقم
    }

    public function test_the_ops_dashboard_renders_kpi_cards_as_links(): void
    {
        $response = $this->actingAs($this->admin('super_admin'))
            ->get(\App\Filament\Pages\OpsDashboard::getUrl());

        $response->assertOk();
        $response->assertSee('طلبات اليوم', false);                 // اللوحة تُرسَم
        // البطاقة صارت رابطًا إلى صفحة تفاصيل المؤشّر (لا div ساكن).
        $response->assertSee(KpiDetail::getUrl(['kpi' => 'confirm']), false);
        $response->assertSee(KpiDetail::getUrl(['kpi' => 'orders_today']), false);
    }

    public function test_a_parametrized_detail_page_renders_with_its_value(): void
    {
        $order = OrderFactory::new()->create(['governorate' => 'أسوان']);

        $response = $this->actingAs($this->admin('super_admin'))
            ->get(KpiDetail::getUrl(['kpi' => 'governorate', 'v' => 'أسوان']));

        $response->assertOk();
        $response->assertSee('أسوان', false);                 // القيمة في العنوان
        $response->assertSee($order->order_number, false);     // الصفّ الأساسي
    }

    public function test_an_unknown_kpi_key_is_not_found(): void
    {
        $response = $this->actingAs($this->admin('super_admin'))
            ->get(KpiDetail::getUrl(['kpi' => 'made-up-key']));

        $response->assertNotFound();
    }

    public function test_a_financial_kpi_is_blocked_without_the_financial_permission(): void
    {
        // support يملك orders.view لا orders.view_financials.
        $this->actingAs($this->admin('support'))
            ->get(KpiDetail::getUrl(['kpi' => 'revenue_realized']))
            ->assertForbidden();

        // super_admin يملك المالية → يُسمح.
        $this->actingAs($this->admin('super_admin'))
            ->get(KpiDetail::getUrl(['kpi' => 'revenue_realized']))
            ->assertOk();
    }
}
