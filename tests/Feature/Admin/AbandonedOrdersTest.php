<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Filament\Pages\AbandonedOrders;
use App\Models\Coupon;
use App\Models\User;
use Database\Factories\OrderFactory;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * قسم «متابعة الطلبات المتروكة» — عملاء بدؤوا طلبًا (pending) ولم يُكملوا الدفع
 * (unpaid/failed). عرض + تواصل + توليد كود خصم (محروس بـ coupons.manage).
 *
 * HONESTY (1.3/1.5): تعمل عبر php artisan test (MariaDB محليًا).
 */
final class AbandonedOrdersTest extends TestCase
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

    private function abandonedOrder(array $overrides = [])
    {
        $order = OrderFactory::new()->create(array_merge([
            'status' => 'pending', 'payment_status' => 'unpaid',
            'customer_name' => 'أم خالد', 'customer_phone' => '01012345678',
            'customer_email' => 'umkhaled@example.com',
        ], $overrides));

        $order->items()->create([
            'book_id' => null, 'book_title' => 'كتاب المشاعر',
            'unit_price' => '120.00', 'quantity' => 1, 'line_total' => '120.00',
        ]);

        return $order;
    }

    public function test_it_lists_pending_unpaid_orders_with_contact_details(): void
    {
        $order = $this->abandonedOrder();

        $response = $this->actingAs($this->admin('super_admin'))->get(AbandonedOrders::getUrl());

        $response->assertOk();
        $response->assertSee('أم خالد', false);                 // اسم العميل
        $response->assertSee($order->order_number, false);       // رقم الطلب
        $response->assertSee('كتاب المشاعر', false);             // الكتب
        $response->assertSee('umkhaled@example.com', false);     // الإيميل
        $response->assertSee('wa.me/201012345678', false);       // رابط واتساب مطبّع
    }

    public function test_it_excludes_paid_or_progressed_orders(): void
    {
        $this->abandonedOrder(['customer_name' => 'عميلة متروكة']);
        OrderFactory::new()->create(['status' => 'pending', 'payment_status' => 'paid', 'customer_name' => 'دفعت بالفعل']);
        OrderFactory::new()->create(['status' => 'confirmed', 'payment_status' => 'unpaid', 'customer_name' => 'طلبها مؤكّد']);

        $response = $this->actingAs($this->admin('super_admin'))->get(AbandonedOrders::getUrl());

        $response->assertOk();
        $response->assertSee('عميلة متروكة', false);
        $response->assertDontSee('دفعت بالفعل', false);   // paid → ليست متروكة
        $response->assertDontSee('طلبها مؤكّد', false);    // confirmed → تقدّمت
    }

    public function test_generating_a_coupon_creates_a_percentage_discount(): void
    {
        $order = $this->abandonedOrder();

        $this->actingAs($this->admin('super_admin'));
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(AbandonedOrders::class)
            ->call('generateCoupon', $order->id)
            ->assertSet('coupons.'.$order->id, fn ($code): bool => is_string($code) && str_starts_with($code, 'BACK'));

        $this->assertDatabaseCount('coupons', 1);
        $this->assertDatabaseHas('coupons', ['type' => 'percentage', 'value' => '10.00', 'is_active' => 1, 'usage_limit_per_user' => 1]);
    }

    public function test_generating_a_coupon_requires_the_coupons_manage_permission(): void
    {
        // «الدعم» يملك orders.view (يرى الصفحة) لا coupons.manage.
        $order = $this->abandonedOrder();

        $this->actingAs($this->admin('support'));
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(AbandonedOrders::class)
            ->call('generateCoupon', $order->id)
            ->assertForbidden();

        $this->assertDatabaseCount('coupons', 0);
    }

    public function test_it_will_not_generate_a_coupon_for_an_order_outside_the_list(): void
    {
        // طلب مكتمل (ليس متروكًا) — لا يجوز توليد كوبون له عبر تمرير معرّفه.
        $done = OrderFactory::new()->create(['status' => 'delivered', 'payment_status' => 'paid']);

        $this->actingAs($this->admin('super_admin'));
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(AbandonedOrders::class)->call('generateCoupon', $done->id);

        $this->assertDatabaseCount('coupons', 0);
    }
}
