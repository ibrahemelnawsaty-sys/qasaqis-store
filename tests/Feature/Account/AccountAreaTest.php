<?php

declare(strict_types=1);

namespace Tests\Feature\Account;

use App\Models\Customer;
use App\Models\Order;
use App\Policies\OrderPolicy;
use Database\Factories\OrderFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * ‏منطقة الحساب: اللوحة، سجل الطلبات، صفحة الطلب الواحد، «بياناتي».
 *
 * ‏الاختبار الحاكم هو test_viewing_another_customers_order_is_forbidden: منع خادمي
 * ‏لا إخفاء رابط (الدستور 4.4 / الممنوع 11.13).
 *
 * ‏تبعيات خارجية (الدستور 1.5/10.3):
 * ‏تحقّقت بنفسي من وجود App\Models\Customer وجدول customers وعمود orders.customer_id
 * ‏و CustomerFactory (وكيل آخر، هجرتا 2026_07_20_000001/000002).
 * ‏**لم يوجد بعد** وقت الكتابة: حارس customer في config/auth.php، مسارات customer.*،
 * ‏وقوالب resources/views/customer/*. لذلك تعمل مجموعة «السياسة» أدناه الآن، بينما
 * ‏تفشل مجموعات HTTP بفشل تكاملي صادق («حارس/مسار/قالب غير موجود») حتى تُربط.
 */
final class AccountAreaTest extends TestCase
{
    use RefreshDatabase;

    /** ‏كلمة المرور الافتراضية في CustomerFactory (متحقَّق منها في الملف). */
    private const FACTORY_PASSWORD = 'password';

    // ================== السياسة (بلا HTTP ولا قوالب ولا حارس) ==================
    // ‏جوهر الأمان. تعمل بمجرد وجود Customer + orders.customer_id.

    public function test_policy_allows_a_customer_to_view_her_own_order(): void
    {
        $customer = $this->makeCustomer();
        $order = $this->makeOrder($customer);

        $this->assertTrue(app(OrderPolicy::class)->view($customer, $order));
    }

    public function test_policy_denies_viewing_another_customers_order(): void
    {
        $owner = $this->makeCustomer();
        $intruder = $this->makeCustomer();
        $order = $this->makeOrder($owner);

        $this->assertFalse(app(OrderPolicy::class)->view($intruder, $order));
    }

    public function test_policy_denies_an_unlinked_guest_order(): void
    {
        // ‏طلب ضيف: customer_id = null. لا أحد يملكه عبر الحساب.
        $customer = $this->makeCustomer();
        $order = OrderFactory::new()->create(['customer_id' => null]);

        $this->assertFalse(app(OrderPolicy::class)->view($customer, $order));
    }

    public function test_policy_denies_a_guest_order_for_an_unsaved_customer(): void
    {
        // ‏الفخّ الذي تحرسه السياسة: getKey() = null و customer_id = null، فمقارنة
        // ‏null === null كانت ستفتح كل طلبات الضيوف لأي كائن عميلة غير محفوظ.
        $order = OrderFactory::new()->create(['customer_id' => null]);

        $this->assertFalse(app(OrderPolicy::class)->view(new Customer, $order));
    }

    public function test_policy_denies_a_soft_deleted_order(): void
    {
        $customer = $this->makeCustomer();
        $order = $this->makeOrder($customer);
        $order->delete();

        $this->assertTrue($order->trashed());
        $this->assertFalse(app(OrderPolicy::class)->view($customer, $order));
    }

    public function test_policy_matches_when_keys_differ_in_php_type(): void
    {
        // ‏سائق قاعدة البيانات قد يعيد المفتاح نصًا. المالكة الحقيقية يجب ألا تُمنع.
        $customer = $this->makeCustomer();
        $order = $this->makeOrder($customer);
        $order->setAttribute('customer_id', (string) $customer->getKey());

        $this->assertTrue(app(OrderPolicy::class)->view($customer, $order));
    }

    public function test_policy_denies_a_customer_whose_key_matches_nothing(): void
    {
        $owner = $this->makeCustomer();
        $order = $this->makeOrder($owner);
        $order->setAttribute('customer_id', 0);

        $this->assertFalse(app(OrderPolicy::class)->view($owner, $order));
    }

    // ================== صفحة الطلب الواحد ==================

    public function test_viewing_another_customers_order_is_forbidden(): void
    {
        $owner = $this->makeCustomer();
        $intruder = $this->makeCustomer();
        $order = $this->makeOrder($owner);

        $this->actingAs($intruder, 'customer')
            ->get(route('customer.orders.show', ['order' => $order->id]))
            // 404 لا 403: قاعدة الانكشاف الصفري — لا نؤكّد حتى وجود الطلب (يطابق
            // القرار المعماري و AuthFlowTest؛ المتحكم يستعمل abort_unless(..., 404)).
            ->assertNotFound();
    }

    public function test_a_customer_can_view_her_own_order(): void
    {
        $customer = $this->makeCustomer();
        $order = $this->makeOrder($customer);

        $this->actingAs($customer, 'customer')
            ->get(route('customer.orders.show', ['order' => $order->id]))
            ->assertOk()
            ->assertSee($order->order_number, false);
    }

    public function test_an_unlinked_guest_order_is_forbidden_even_with_a_matching_phone(): void
    {
        // ‏قاعدة الانكشاف الصفري: مطابقة رقم الجوال ليست إثبات ملكية — أي شخص
        // ‏يستطيع تقديم طلب برقم غيره. الملكية من customer_id حصريًا.
        $customer = $this->makeCustomer(['phone_normalized' => '1012345678']);
        $order = OrderFactory::new()->create([
            'customer_id' => null,
            'customer_phone' => '01012345678',
        ]);

        $this->actingAs($customer, 'customer')
            ->get(route('customer.orders.show', ['order' => $order->id]))
            // 404 لا 403: قاعدة الانكشاف الصفري — لا نؤكّد حتى وجود الطلب (يطابق
            // القرار المعماري و AuthFlowTest؛ المتحكم يستعمل abort_unless(..., 404)).
            ->assertNotFound();
    }

    public function test_a_soft_deleted_order_is_not_reachable(): void
    {
        $customer = $this->makeCustomer();
        $order = $this->makeOrder($customer);
        $order->delete();

        $this->actingAs($customer, 'customer')
            ->get(route('customer.orders.show', ['order' => $order->id]))
            ->assertNotFound();
    }

    public function test_a_missing_order_returns_404(): void
    {
        $customer = $this->makeCustomer();

        $this->actingAs($customer, 'customer')
            ->get(route('customer.orders.show', ['order' => 999999]))
            ->assertNotFound();
    }

    public function test_a_guest_cannot_reach_the_account_area(): void
    {
        foreach (['customer.dashboard', 'customer.orders.index', 'customer.profile.edit'] as $name) {
            $response = $this->get(route($name));

            // ‏المهم: لا 200 لضيف. الوجهة (تحويل للدخول أو 403) يحكمها middleware
            // ‏الحارس المملوك لوكيل آخر، فلا نثبّتها هنا.
            $this->assertNotSame(200, $response->getStatusCode(), "Guest reached [{$name}].");
        }
    }

    // ================== سجل الطلبات ==================

    public function test_the_orders_list_shows_only_her_own_orders(): void
    {
        $customer = $this->makeCustomer();
        $other = $this->makeCustomer();

        $mine = $this->makeOrder($customer);
        $hers = $this->makeOrder($other);
        $guest = OrderFactory::new()->create(['customer_id' => null]);

        $response = $this->actingAs($customer, 'customer')
            ->get(route('customer.orders.index'))
            ->assertOk();

        $ids = $response->viewData('orders')->pluck('id')->all();

        $this->assertSame([$mine->id], $ids);
        $this->assertNotContains($hers->id, $ids);
        $this->assertNotContains($guest->id, $ids);
    }

    public function test_the_orders_list_is_paginated_at_fifteen(): void
    {
        $customer = $this->makeCustomer();

        for ($i = 0; $i < 16; $i++) {
            $this->makeOrder($customer);
        }

        $orders = $this->actingAs($customer, 'customer')
            ->get(route('customer.orders.index'))
            ->assertOk()
            ->viewData('orders');

        $this->assertSame(15, $orders->perPage());
        $this->assertCount(15, $orders->items());
        $this->assertSame(16, $orders->total());
    }

    public function test_the_orders_list_counts_items_without_n_plus_one(): void
    {
        $customer = $this->makeCustomer();
        $order = $this->makeOrder($customer);
        $order->items()->create([
            'book_id' => null,
            'book_title' => 'كتاب تجريبي',
            'unit_price' => '100.00',
            'quantity' => 2,
            'line_total' => '200.00',
        ]);

        $listed = $this->actingAs($customer, 'customer')
            ->get(route('customer.orders.index'))
            ->assertOk()
            ->viewData('orders')
            ->first();

        // ‏العدّ جاء من استعلام فرعي واحد، لا من تحميل العلاقة داخل حلقة.
        $this->assertSame(1, (int) $listed->items_count);
        $this->assertFalse($listed->relationLoaded('items'));
    }

    public function test_the_orders_list_does_not_carry_address_or_phone_columns(): void
    {
        // ‏تقليل البيانات: ما لا يُحمَّل لا يُسرَّب في القالب سهوًا.
        $customer = $this->makeCustomer();
        $this->makeOrder($customer);

        $attributes = $this->actingAs($customer, 'customer')
            ->get(route('customer.orders.index'))
            ->assertOk()
            ->viewData('orders')
            ->first()
            ->getAttributes();

        foreach (['customer_phone', 'customer_phone_alt', 'address_line', 'address_notes', 'ip_address'] as $column) {
            $this->assertArrayNotHasKey($column, $attributes);
        }
    }

    // ================== اللوحة ==================

    public function test_the_dashboard_counts_orders_and_spending_from_the_orders_table(): void
    {
        $customer = $this->makeCustomer();
        $this->makeOrder($customer, ['grand_total' => '150.00', 'status' => 'delivered']);
        $this->makeOrder($customer, ['grand_total' => '250.50', 'status' => 'confirmed']);

        $response = $this->actingAs($customer, 'customer')
            ->get(route('customer.dashboard'))
            ->assertOk();

        $this->assertSame(2, $response->viewData('ordersCount'));
        $this->assertSame('400.50', $response->viewData('totalSpent'));
    }

    public function test_spending_excludes_cancelled_refused_and_refunded_orders(): void
    {
        $customer = $this->makeCustomer();
        $this->makeOrder($customer, ['grand_total' => '100.00', 'status' => 'delivered']);

        foreach (['cancelled', 'refused', 'refunded'] as $status) {
            $this->makeOrder($customer, ['grand_total' => '999.00', 'status' => $status]);
        }

        $response = $this->actingAs($customer, 'customer')
            ->get(route('customer.dashboard'))
            ->assertOk();

        // ‏العدّ يشمل كل الطلبات؛ الإنفاق يشمل المقبوض فقط.
        $this->assertSame(4, $response->viewData('ordersCount'));
        $this->assertSame('100.00', $response->viewData('totalSpent'));
    }

    public function test_the_dashboard_of_a_customer_with_no_orders_is_zeroed_not_broken(): void
    {
        $customer = $this->makeCustomer();

        $response = $this->actingAs($customer, 'customer')
            ->get(route('customer.dashboard'))
            ->assertOk();

        $this->assertSame(0, $response->viewData('ordersCount'));
        $this->assertSame('0.00', $response->viewData('totalSpent'));
        $this->assertNull($response->viewData('lastOrder'));
    }

    public function test_the_dashboard_last_order_is_the_most_recent_one(): void
    {
        $customer = $this->makeCustomer();
        $this->makeOrder($customer, ['created_at' => now()->subDays(3)]);
        $newest = $this->makeOrder($customer, ['created_at' => now()]);

        $response = $this->actingAs($customer, 'customer')
            ->get(route('customer.dashboard'))
            ->assertOk();

        $this->assertSame($newest->id, $response->viewData('lastOrder')->id);
    }

    public function test_the_dashboard_ignores_another_customers_orders(): void
    {
        $customer = $this->makeCustomer();
        $other = $this->makeCustomer();
        $this->makeOrder($other, ['grand_total' => '900.00', 'status' => 'delivered']);

        $response = $this->actingAs($customer, 'customer')
            ->get(route('customer.dashboard'))
            ->assertOk();

        $this->assertSame(0, $response->viewData('ordersCount'));
        $this->assertSame('0.00', $response->viewData('totalSpent'));
    }

    // ================== بياناتي ==================

    public function test_a_customer_can_update_her_name_email_and_default_address(): void
    {
        $customer = $this->makeCustomer();

        $this->actingAs($customer, 'customer')
            ->put(route('customer.profile.update'), [
                'name' => 'أم يوسف',
                'email' => 'new@example.test',
                'last_country_code' => 'EG',
                'last_governorate' => 'الجيزة',
                'last_city' => 'الدقي',
                'last_address_line' => 'شارع التحرير 12',
            ])
            ->assertRedirect(route('customer.profile.edit'));

        $fresh = $customer->fresh();

        $this->assertSame('أم يوسف', $fresh->name);
        $this->assertSame('new@example.test', $fresh->email);
        $this->assertSame('الجيزة', $fresh->last_governorate);
        $this->assertSame('الدقي', $fresh->last_city);
        $this->assertSame('شارع التحرير 12', $fresh->last_address_line);
    }

    public function test_the_login_phone_cannot_be_changed_from_the_profile_form(): void
    {
        // ‏الجوال هوية الدخول ولا توجد قناة تحقق من ملكيته. الحقول المهرَّبة تُتجاهل.
        $customer = $this->makeCustomer(['phone_normalized' => '1012345678']);

        $this->actingAs($customer, 'customer')
            ->put(route('customer.profile.update'), [
                'name' => 'أم يوسف',
                'email' => 'keep@example.test',
                'phone' => '01099999999',
                'phone_normalized' => '1099999999',
                'phone_e164' => '+201099999999',
            ]);

        $fresh = $customer->fresh();

        $this->assertSame('1012345678', $fresh->phone_normalized);
        $this->assertSame('أم يوسف', $fresh->name);
    }

    public function test_server_controlled_flags_cannot_be_smuggled_through_the_profile_form(): void
    {
        $customer = $this->makeCustomer();

        $this->actingAs($customer, 'customer')
            ->put(route('customer.profile.update'), [
                'name' => 'أم يوسف',
                'email' => 'flags@example.test',
                'is_claimed' => true,
                'phone_verified_at' => now()->toDateTimeString(),
                'orders_count' => 999,
                'total_spent' => '99999.00',
            ]);

        $fresh = $customer->fresh();

        $this->assertNull($fresh->phone_verified_at);
        $this->assertFalse((bool) $fresh->is_claimed);
        $this->assertSame(0, (int) $fresh->orders_count);
        $this->assertSame('0.00', (string) $fresh->total_spent);
    }

    public function test_the_email_must_stay_unique_across_customers(): void
    {
        $this->makeCustomer(['email' => 'taken@example.test']);
        $customer = $this->makeCustomer();

        $this->actingAs($customer, 'customer')
            ->from(route('customer.profile.edit'))
            ->put(route('customer.profile.update'), [
                'name' => 'أم يوسف',
                'email' => 'taken@example.test',
            ])
            ->assertSessionHasErrors('email');

        $this->assertNotSame('taken@example.test', $customer->fresh()->email);
    }

    public function test_keeping_the_same_email_is_not_rejected_as_a_duplicate(): void
    {
        $customer = $this->makeCustomer(['email' => 'mine@example.test']);

        $this->actingAs($customer, 'customer')
            ->put(route('customer.profile.update'), [
                'name' => 'اسم جديد',
                'email' => 'mine@example.test',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('اسم جديد', $customer->fresh()->name);
    }

    public function test_an_unknown_governorate_is_rejected(): void
    {
        $customer = $this->makeCustomer();

        $this->actingAs($customer, 'customer')
            ->from(route('customer.profile.edit'))
            ->put(route('customer.profile.update'), [
                'name' => 'أم يوسف',
                'email' => 'gov@example.test',
                'last_country_code' => 'EG',
                'last_governorate' => 'محافظة لا وجود لها',
            ])
            ->assertSessionHasErrors('last_governorate');
    }

    public function test_changing_the_password_requires_the_current_one(): void
    {
        $customer = $this->makeCustomer();

        $this->actingAs($customer, 'customer')
            ->from(route('customer.profile.edit'))
            ->put(route('customer.profile.update'), [
                'name' => 'أم يوسف',
                'email' => 'pw@example.test',
                'password' => 'new-secret-123',
                'password_confirmation' => 'new-secret-123',
                'current_password' => 'wrong-password',
            ])
            ->assertSessionHasErrors('current_password');

        $this->assertTrue(Hash::check(self::FACTORY_PASSWORD, $customer->fresh()->password));
    }

    public function test_a_password_change_without_the_current_password_is_rejected(): void
    {
        // ‏بدون هذه القاعدة تكفي جلسة مسروقة لقفل صاحبة الحساب خارج حسابها.
        $customer = $this->makeCustomer();

        $this->actingAs($customer, 'customer')
            ->from(route('customer.profile.edit'))
            ->put(route('customer.profile.update'), [
                'name' => 'أم يوسف',
                'email' => 'pw0@example.test',
                'password' => 'new-secret-123',
                'password_confirmation' => 'new-secret-123',
            ])
            ->assertSessionHasErrors('current_password');

        $this->assertTrue(Hash::check(self::FACTORY_PASSWORD, $customer->fresh()->password));
    }

    public function test_a_soft_deleted_customers_email_stays_reserved(): void
    {
        // ‏سلوك مقصود: القيد الفريد لا يعرف الحذف الناعم، و Rule::unique يستعلم كل
        // ‏الصفوف. حساب محذوف ناعمًا يحجز بريده فلا يرثه شخص آخر.
        $gone = $this->makeCustomer(['email' => 'gone@example.test']);
        $gone->delete();

        $customer = $this->makeCustomer();

        $this->actingAs($customer, 'customer')
            ->from(route('customer.profile.edit'))
            ->put(route('customer.profile.update'), [
                'name' => 'أم يوسف',
                'email' => 'gone@example.test',
            ])
            ->assertSessionHasErrors('email');
    }

    public function test_a_country_the_store_cannot_ship_to_is_rejected(): void
    {
        // ‏جدول countries فارغ في هذا الاختبار ⇒ المسموح «EG» فقط.
        $customer = $this->makeCustomer();

        $this->actingAs($customer, 'customer')
            ->from(route('customer.profile.edit'))
            ->put(route('customer.profile.update'), [
                'name' => 'أم يوسف',
                'email' => 'intl@example.test',
                'last_country_code' => 'SA',
            ])
            ->assertSessionHasErrors('last_country_code');
    }

    public function test_the_second_page_of_the_orders_list_works(): void
    {
        $customer = $this->makeCustomer();

        for ($i = 0; $i < 16; $i++) {
            $this->makeOrder($customer);
        }

        $orders = $this->actingAs($customer, 'customer')
            ->get(route('customer.orders.index').'?page=2')
            ->assertOk()
            ->viewData('orders');

        $this->assertCount(1, $orders->items());
        $this->assertSame(2, $orders->currentPage());
    }

    public function test_the_password_change_must_be_confirmed(): void
    {
        $customer = $this->makeCustomer();

        $this->actingAs($customer, 'customer')
            ->from(route('customer.profile.edit'))
            ->put(route('customer.profile.update'), [
                'name' => 'أم يوسف',
                'email' => 'pw2@example.test',
                'password' => 'new-secret-123',
                'password_confirmation' => 'different-123',
                'current_password' => self::FACTORY_PASSWORD,
            ])
            ->assertSessionHasErrors('password');
    }

    public function test_a_correct_current_password_changes_the_password_and_stores_it_hashed(): void
    {
        $customer = $this->makeCustomer();

        $this->actingAs($customer, 'customer')
            ->put(route('customer.profile.update'), [
                'name' => 'أم يوسف',
                'email' => 'pw3@example.test',
                'password' => 'new-secret-123',
                'password_confirmation' => 'new-secret-123',
                'current_password' => self::FACTORY_PASSWORD,
            ])
            ->assertSessionHasNoErrors();

        $stored = $customer->fresh()->password;

        // ‏مجزّأة لا مخزَّنة كنص صريح (الدستور 4.3)، وبلا تجزئة مزدوجة.
        $this->assertTrue(Hash::check('new-secret-123', $stored));
        $this->assertNotSame('new-secret-123', $stored);
    }

    public function test_the_name_is_required(): void
    {
        $customer = $this->makeCustomer();

        $this->actingAs($customer, 'customer')
            ->from(route('customer.profile.edit'))
            ->put(route('customer.profile.update'), ['name' => '', 'email' => 'x@example.test'])
            ->assertSessionHasErrors('name');
    }

    // ================== مساعدات ==================

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function makeCustomer(array $attributes = []): Customer
    {
        return Customer::factory()->create($attributes);
    }

    /**
     * ‏Order بلا HasFactory — يُنشأ عبر OrderFactory::new() (نفس نمط TrackOrderTest).
     *
     * @param  array<string, mixed>  $attributes
     */
    private function makeOrder(Customer $customer, array $attributes = []): Order
    {
        return OrderFactory::new()->create(array_merge([
            'customer_id' => $customer->getKey(),
        ], $attributes));
    }
}
