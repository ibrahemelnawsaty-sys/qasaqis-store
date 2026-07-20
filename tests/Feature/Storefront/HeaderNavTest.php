<?php

declare(strict_types=1);

namespace Tests\Feature\Storefront;

use App\Models\Customer;
use App\Models\Menu;
use App\Models\MenuItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * شريط تنقّل الهيدر يتبدّل حسب حالة الدخول (M12): الزائرة ترى قائمة الترويسة العامة،
 * والعميلة المسجّلة ترى شريط حسابها (المتجر/طلباتي/سلتي/بياناتي/حسابي). كلاهما
 * يحرّره الأدمن من «القوائم» عبر الموقع (header / header_customer)، ومع غياب القائمة
 * تظهر روابط افتراضية فلا تختفي الملاحة (rescue + fallback).
 *
 * HONESTY (1.3/1.5): تعمل عبر php artisan test (MariaDB محليًا).
 */
final class HeaderNavTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_sees_the_public_nav_strip(): void
    {
        $response = $this->get(route('home'));

        $response->assertOk();
        // افتراضي الزائرة (لا قائمة header في الأدمن): كل الكتب/العروض/المدونة.
        $response->assertSee('العروض', false);
        $response->assertSee('المدونة', false);
        // لا يظهر شريط الحساب للزائرة.
        $response->assertDontSee('سلتي', false);
        $response->assertDontSee('طلباتي', false);
    }

    public function test_a_logged_in_customer_sees_the_account_nav_strip(): void
    {
        $customer = Customer::factory()->create();

        $response = $this->actingAs($customer, 'customer')->get(route('home'));

        $response->assertOk();
        // شريط العميلة: المتجر/طلباتي/سلتي/بياناتي/حسابي (نبرة شخصية). فرع
        // if/elseif/else حصريّ فيستبدل شريط الزائرة لا يُضيف إليه؛ ونؤكّد الغياب
        // للزائرة في الاختبار السابق (assertDontSee سلتي) لإثبات التبعية للحالة.
        $response->assertSee('سلتي', false);
        $response->assertSee('طلباتي', false);
        $response->assertSee('بياناتي', false);
    }

    public function test_the_admin_can_override_the_customer_strip_via_a_menu(): void
    {
        $customer = Customer::factory()->create();

        // قائمة يحرّرها الأدمن للعميلة: تحلّ محلّ الافتراضي.
        $menu = Menu::create([
            'name' => 'قائمة العميلة',
            'location' => 'header_customer',
            'is_active' => true,
            'show_categories' => false,
        ]);
        MenuItem::create([
            'menu_id' => $menu->id,
            'label' => 'دعم قصاقيص',
            'url' => 'https://qasaqis.store/support',
            'link_type' => 'url',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $response = $this->actingAs($customer, 'customer')->get(route('home'));

        $response->assertOk();
        $response->assertSee('دعم قصاقيص', false);   // رابط الأدمن ظهر
        $response->assertDontSee('سلتي', false);      // والافتراضي استُبدل
    }

    public function test_the_customer_strip_falls_back_when_its_menu_is_inactive(): void
    {
        $customer = Customer::factory()->create();

        // قائمة معطّلة يجب أن تُتجاهَل فيعود الافتراضي (لا ملاحة فارغة).
        Menu::create([
            'name' => 'قائمة معطّلة',
            'location' => 'header_customer',
            'is_active' => false,
            'show_categories' => false,
        ]);

        $response = $this->actingAs($customer, 'customer')->get(route('home'));

        $response->assertOk();
        $response->assertSee('سلتي', false);   // عاد الشريط الافتراضي للعميلة
    }
}
