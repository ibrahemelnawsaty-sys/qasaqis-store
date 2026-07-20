<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * تسجيل الدخول الموحّد (/login): مُعرّف واحد يوجّه للحارس الصحيح — بريد → أدمن (web)،
 * جوال → عميل (customer) — ورسالة فشل موحّدة، وقائمة الهيدر الواعية بالحالة.
 */
class UnifiedLoginTest extends TestCase
{
    use RefreshDatabase;

    private const PW = 'password123';
    private const PHONE = '01012345678';
    private const ADMIN_EMAIL = 'admin@qasaqis.store';

    protected function setUp(): void
    {
        parent::setUp();
        // أدوار spatie لازمة لبوّابة canAccessPanel (نشط + دور إداري).
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
    }

    private function customer(): Customer
    {
        return Customer::factory()->withPhone(self::PHONE)->create([
            'name' => 'أم يوسف',
            'password' => Hash::make(self::PW),
        ]);
    }

    private function admin(): User
    {
        $admin = User::factory()->create([
            'name' => 'المدير',
            'email' => self::ADMIN_EMAIL,
            'password' => Hash::make(self::PW),
        ]);
        $admin->assignRole('super_admin');

        return $admin;
    }

    // ----- التوجيه حسب الحارس ------------------------------------------------

    public function test_customer_logs_in_by_phone_and_lands_on_dashboard(): void
    {
        $customer = $this->customer();

        $this->post(route('login.store'), ['identifier' => self::PHONE, 'password' => self::PW])
            ->assertRedirect(route('customer.dashboard'));

        $this->assertAuthenticatedAs($customer, 'customer');
        $this->assertGuest('web');
    }

    public function test_admin_logs_in_by_email_and_lands_on_admin_panel(): void
    {
        $admin = $this->admin();

        $this->post(route('login.store'), ['identifier' => self::ADMIN_EMAIL, 'password' => self::PW])
            ->assertRedirect('/admin');

        $this->assertAuthenticatedAs($admin, 'web');
        $this->assertGuest('customer');
    }

    // ----- الأمان: رسالة موحّدة، لا تعداد ------------------------------------

    public function test_wrong_password_yields_unified_error_and_no_auth(): void
    {
        $this->customer();

        $this->from(route('login'))
            ->post(route('login.store'), ['identifier' => self::PHONE, 'password' => 'wrong-pass'])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('identifier');

        $this->assertGuest('customer');
        $this->assertGuest('web');
    }

    public function test_unknown_email_and_unknown_phone_fail_identically(): void
    {
        // بريد غير مسجَّل
        $this->from(route('login'))
            ->post(route('login.store'), ['identifier' => 'nobody@example.com', 'password' => 'x'])
            ->assertSessionHasErrors('identifier');

        // جوال غير مسجَّل — نفس الحقل ونفس المسار (لا كشف أيّهما موجود)
        $this->from(route('login'))
            ->post(route('login.store'), ['identifier' => '01099999999', 'password' => 'x'])
            ->assertSessionHasErrors('identifier');

        $this->assertGuest('customer');
        $this->assertGuest('web');
    }

    public function test_a_customer_cannot_authenticate_the_admin_guard_and_vice_versa(): void
    {
        // بيانات العميل الصحيحة عبر فرع البريد (نضع بريدًا) يجب ألا تدخله كأدمن.
        $this->customer();
        $this->post(route('login.store'), ['identifier' => 'notanadmin@example.com', 'password' => self::PW])
            ->assertSessionHasErrors('identifier');
        $this->assertGuest('web');
    }

    public function test_user_without_admin_role_cannot_get_a_web_session(): void
    {
        // مستخدم صحيح البيانات لكن بلا دور إداري: يفشل بوّابة canAccessPanel،
        // فتُسقَط جلسته ويعامَل كفشل موحّد (لا جلسة web).
        User::factory()->create([
            'email' => 'roleless@example.com',
            'password' => Hash::make(self::PW),
        ]);

        $this->post(route('login.store'), ['identifier' => 'roleless@example.com', 'password' => self::PW])
            ->assertSessionHasErrors('identifier');

        $this->assertGuest('web');
    }

    public function test_deactivated_admin_cannot_get_a_web_session(): void
    {
        $admin = $this->admin();
        $admin->update(['is_active' => false]);

        $this->post(route('login.store'), ['identifier' => self::ADMIN_EMAIL, 'password' => self::PW])
            ->assertSessionHasErrors('identifier');

        $this->assertGuest('web');
    }

    // ----- الهيدر واعٍ بالحالة ----------------------------------------------

    public function test_guest_header_shows_the_login_button(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('تسجيل الدخول', $html);
        $this->assertStringContainsString(route('login'), $html);
    }

    public function test_customer_header_shows_avatar_menu_with_orders_and_logout(): void
    {
        $customer = $this->customer();

        $html = $this->actingAs($customer, 'customer')->get('/')->assertOk()->getContent();

        $this->assertStringContainsString($customer->name, $html);
        $this->assertStringContainsString(route('customer.orders.index'), $html);
        $this->assertStringContainsString(route('customer.dashboard'), $html);
        $this->assertStringContainsString(route('customer.logout'), $html);
    }

    public function test_admin_header_shows_admin_panel_and_logout(): void
    {
        $admin = $this->admin();

        $html = $this->actingAs($admin, 'web')->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('/admin', $html);
        $this->assertStringContainsString(route('filament.admin.auth.logout'), $html);
    }

    // ----- تحويل من هو داخلٌ أصلًا ------------------------------------------

    public function test_authenticated_customer_visiting_login_is_redirected_to_dashboard(): void
    {
        $customer = $this->customer();

        $this->actingAs($customer, 'customer')->get(route('login'))
            ->assertRedirect(route('customer.dashboard'));
    }

    public function test_authenticated_admin_visiting_login_is_redirected_to_admin(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'web')->get(route('login'))
            ->assertRedirect('/admin');
    }
}
