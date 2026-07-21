<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Filament\Pages\KpiDetail;
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

    // ── العرض + التفويض ────────────────────────────────────────────────────

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
