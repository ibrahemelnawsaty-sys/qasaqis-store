<?php

declare(strict_types=1);

namespace Tests\Feature\Account;

use App\Http\Requests\Customer\LoginRequest;
use App\Models\Customer;
use Database\Factories\OrderFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

/**
 * تدفّق حسابات العميلات: تسجيل، دخول، خروج، استعادة كلمة المرور.
 *
 * الاختبارات هنا تفحص **السلوك الأمني** لا وجود الشاشات: منع تعداد الأرقام
 * والبُرد، منع الاستيلاء على طلبات الغير، تجزئة الكلمة، حدّ المعدّل، وبقاء السلة
 * بعد الخروج.
 *
 * NOTE: الطلبات تُنشأ عبر OrderFactory::new() لأن موديل Order لا يستعمل HasFactory
 * (نفس نمط tests/Feature/Orders/TrackOrderTest)، بينما Customer يستعمله.
 */
final class AuthFlowTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'kalima-sirriya-8';

    private const NEW_PASSWORD = 'kalima-gedida-9';

    private const PHONE_LOCAL = '01012345678';

    private const PHONE_NORMALIZED = '1012345678';

    private const EMAIL = 'om.youssef@example.com';

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeCustomer(array $overrides = [], string $phone = self::PHONE_LOCAL): Customer
    {
        return Customer::factory()
            ->withPhone($phone)
            ->create(array_merge([
                'name' => 'أم يوسف',
                'email' => self::EMAIL,
                'password' => Hash::make(self::PASSWORD),
            ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function registrationPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'أم يوسف',
            'phone' => self::PHONE_LOCAL,
            'email' => self::EMAIL,
            'password' => self::PASSWORD,
            'password_confirmation' => self::PASSWORD,
        ], $overrides);
    }

    /*
    |--------------------------------------------------------------------------
    | التسجيل
    |--------------------------------------------------------------------------
    */

    public function test_a_mother_can_create_an_account_and_is_logged_in_immediately(): void
    {
        $response = $this->post(route('customer.register.store'), $this->registrationPayload());

        // بعد التسجيل تُوجَّه إلى تأكيد البريد (M9) — مسجّلة الدخول أصلًا.
        $response->assertRedirect(route('customer.verify.show'));
        $this->assertAuthenticated('customer');
        $this->assertDatabaseHas('customers', [
            'phone_normalized' => self::PHONE_NORMALIZED,
            'phone_e164' => '+20'.self::PHONE_NORMALIZED,
            'email' => self::EMAIL,
        ]);
    }

    public function test_the_password_is_stored_hashed_never_in_plain_text(): void
    {
        $this->post(route('customer.register.store'), $this->registrationPayload());

        $customer = Customer::firstWhere('phone_normalized', self::PHONE_NORMALIZED);

        $this->assertNotNull($customer);
        $this->assertNotSame(self::PASSWORD, $customer->password);
        $this->assertTrue(Hash::check(self::PASSWORD, (string) $customer->password));
    }

    public function test_the_phone_is_normalised_to_ten_digits_whatever_the_prefix(): void
    {
        $this->post(route('customer.register.store'), $this->registrationPayload([
            'phone' => '+20 101 234 5678',
        ]))->assertRedirect(route('customer.verify.show'));

        $this->assertDatabaseHas('customers', ['phone_normalized' => self::PHONE_NORMALIZED]);
    }

    /** بند 4.1: الحقل المشتقّ خادميًا لا يُقرأ من العميل مهما أرسل. */
    public function test_a_forged_phone_normalized_field_is_ignored(): void
    {
        $this->post(route('customer.register.store'), $this->registrationPayload([
            'phone' => self::PHONE_LOCAL,
            'phone_normalized' => '1099999999',
            'phone_e164' => '+201099999999',
            'is_claimed' => true,
            'orders_count' => 99,
        ]))->assertRedirect(route('customer.verify.show'));

        $this->assertDatabaseHas('customers', [
            'phone_normalized' => self::PHONE_NORMALIZED,
            'is_claimed' => false,
            'orders_count' => 0,
        ]);
        $this->assertDatabaseMissing('customers', ['phone_normalized' => '1099999999']);
    }

    public function test_registration_rejects_an_email_that_already_has_an_account(): void
    {
        $this->makeCustomer(phone: '01099999999');

        $this->from(route('customer.register.show'))
            ->post(route('customer.register.store'), $this->registrationPayload())
            ->assertSessionHasErrors('email');

        $this->assertGuest('customer');
        $this->assertSame(1, Customer::query()->count());
    }

    public function test_registration_rejects_the_same_phone_written_in_another_format(): void
    {
        $this->makeCustomer();

        // نفس الرقم بصيغة دولية + بريد مختلف: التصادم على الجوال وحده.
        $this->from(route('customer.register.show'))
            ->post(route('customer.register.store'), $this->registrationPayload([
                'phone' => '+201012345678',
                'email' => 'another@example.com',
            ]))
            ->assertSessionHasErrors(['phone' => __('account.register.phone_taken')]);

        $this->assertGuest('customer');
        $this->assertSame(1, Customer::query()->count());
    }

    public function test_registration_rejects_the_phone_of_a_soft_deleted_account_and_does_not_restore_it(): void
    {
        $customer = $this->makeCustomer();
        $customer->delete();

        $this->from(route('customer.register.show'))
            ->post(route('customer.register.store'), $this->registrationPayload([
                'email' => 'another@example.com',
            ]))
            ->assertSessionHasErrors(['phone' => __('account.register.phone_taken')]);

        // الصفّ المحذوف يبقى محذوفًا: لا استرجاع صامت يسلّم طلبات الأولى للتالي.
        $this->assertSoftDeleted('customers', ['id' => $customer->id]);
        $this->assertGuest('customer');
    }

    public function test_registration_rejects_a_non_egyptian_phone(): void
    {
        $this->from(route('customer.register.show'))
            ->post(route('customer.register.store'), $this->registrationPayload([
                'phone' => '+966512345678',
            ]))
            ->assertSessionHasErrors('phone');

        $this->assertSame(0, Customer::query()->count());
    }

    public function test_registration_rejects_a_mismatched_password_confirmation(): void
    {
        $this->from(route('customer.register.show'))
            ->post(route('customer.register.store'), $this->registrationPayload([
                'password_confirmation' => 'something-else-9',
            ]))
            ->assertSessionHasErrors('password');

        $this->assertSame(0, Customer::query()->count());
    }

    /**
     * ══ القاعدة الأمنية الحاكمة ══
     * `/checkout` لا يتحقق من ملكية رقم الجوال إطلاقًا، فمطابقة الرقم ليست دليل
     * ملكية. التسجيل يُنشئ حسابًا فارغًا ولا يرث أي طلب سابق — وإلا صار التسجيل
     * برقم الضحية استيلاءً على سجلّ طلباتها بتكلفة صفر.
     */
    public function test_registration_never_links_existing_guest_orders_that_share_the_phone(): void
    {
        $guestOrder = OrderFactory::new()->create(['customer_phone' => self::PHONE_LOCAL]);
        $altOrder = OrderFactory::new()->create([
            'customer_phone' => '01099999999',
            'customer_phone_alt' => self::PHONE_LOCAL,
        ]);

        $this->post(route('customer.register.store'), $this->registrationPayload())
            ->assertRedirect(route('customer.verify.show'));

        foreach ([$guestOrder, $altOrder] as $order) {
            $this->assertDatabaseHas('orders', [
                'id' => $order->id,
                'customer_id' => null,
            ]);
        }
    }

    public function test_the_register_page_sends_an_authenticated_customer_to_her_dashboard(): void
    {
        $this->actingAs($this->makeCustomer(), 'customer')
            ->get(route('customer.register.show'))
            ->assertRedirect(route('customer.dashboard'));
    }

    /*
    |--------------------------------------------------------------------------
    | الدخول
    |--------------------------------------------------------------------------
    */

    public function test_login_succeeds_with_any_egyptian_phone_prefix(): void
    {
        $customer = $this->makeCustomer();

        foreach ([self::PHONE_LOCAL, '+201012345678', '201012345678'] as $written) {
            $this->post(route('customer.login.store'), [
                'phone' => $written,
                'password' => self::PASSWORD,
            ])->assertRedirect(route('customer.dashboard'));

            $this->assertAuthenticatedAs($customer, 'customer');

            $this->post(route('customer.logout'));
        }
    }

    /** لا قناة تعداد: الرقم غير المسجَّل والكلمة الخاطئة يخرجان برسالة واحدة. */
    public function test_wrong_password_and_unknown_phone_fail_with_the_exact_same_message(): void
    {
        $this->makeCustomer();

        $wrongPassword = $this->from(route('customer.login.show'))
            ->post(route('customer.login.store'), [
                'phone' => self::PHONE_LOCAL,
                'password' => 'not-the-password',
            ]);

        $unknownPhone = $this->from(route('customer.login.show'))
            ->post(route('customer.login.store'), [
                'phone' => '01155554444',
                'password' => self::PASSWORD,
            ]);

        foreach ([$wrongPassword, $unknownPhone] as $response) {
            $response->assertRedirect(route('customer.login.show'));
            $response->assertSessionHasErrors(['phone' => __('auth.failed')]);
        }

        $this->assertGuest('customer');
    }

    public function test_a_soft_deleted_account_cannot_log_in(): void
    {
        $this->makeCustomer()->delete();

        $this->post(route('customer.login.store'), [
            'phone' => self::PHONE_LOCAL,
            'password' => self::PASSWORD,
        ])->assertSessionHasErrors(['phone' => __('auth.failed')]);

        $this->assertGuest('customer');
    }

    /** السكيمة تسمح بحساب بلا كلمة مرور — ويجب ألا يفتح بابًا ولا أن ينهار. */
    public function test_an_account_without_a_password_cannot_log_in(): void
    {
        $this->makeCustomer()->forceFill(['password' => null])->save();

        $this->post(route('customer.login.store'), [
            'phone' => self::PHONE_LOCAL,
            'password' => '',
        ])->assertSessionHasErrors('password');

        $this->post(route('customer.login.store'), [
            'phone' => self::PHONE_LOCAL,
            'password' => 'anything-at-all',
        ])->assertSessionHasErrors(['phone' => __('auth.failed')]);

        $this->assertGuest('customer');
    }

    /**
     * بند 4.6. الإثبات سلوكي لا نصّي: بعد استنفاد المحاولات تُرفض **الكلمة
     * الصحيحة** أيضًا — وإلا كان الحدّ رسالةً بلا أثر.
     */
    public function test_login_is_locked_after_five_failed_attempts(): void
    {
        $this->makeCustomer();

        for ($i = 0; $i < LoginRequest::MAX_ATTEMPTS; $i++) {
            $this->post(route('customer.login.store'), [
                'phone' => self::PHONE_LOCAL,
                'password' => 'wrong-'.$i,
            ]);
        }

        $this->from(route('customer.login.show'))
            ->post(route('customer.login.store'), [
                'phone' => self::PHONE_LOCAL,
                'password' => self::PASSWORD,
            ])
            ->assertSessionHasErrors('phone');

        $this->assertGuest('customer');
    }

    /** الدخول الناجح يصفّر العدّاد، فلا تتراكم محاولات قديمة على عميلة شرعية. */
    public function test_a_successful_login_clears_the_failed_attempt_budget(): void
    {
        $customer = $this->makeCustomer();

        for ($round = 0; $round < 2; $round++) {
            for ($i = 0; $i < LoginRequest::MAX_ATTEMPTS - 1; $i++) {
                $this->post(route('customer.login.store'), [
                    'phone' => self::PHONE_LOCAL,
                    'password' => "wrong-{$round}-{$i}",
                ]);
            }

            // 4 + 4 = 8 محاولة فاشلة إجمالًا؛ لولا التصفير لكان الحساب مقفولًا
            // قبل الجولة الثانية.
            $this->post(route('customer.login.store'), [
                'phone' => self::PHONE_LOCAL,
                'password' => self::PASSWORD,
            ])->assertRedirect(route('customer.dashboard'));

            $this->assertAuthenticatedAs($customer, 'customer');

            $this->post(route('customer.logout'));
        }
    }

    /*
    |--------------------------------------------------------------------------
    | الخروج
    |--------------------------------------------------------------------------
    */

    public function test_logout_ends_the_session_and_keeps_the_cart(): void
    {
        $cart = [7 => 2, 11 => 1];

        $response = $this->actingAs($this->makeCustomer(), 'customer')
            ->withSession(['cart' => $cart])
            ->post(route('customer.logout'));

        $response->assertRedirect(route('home'));
        $response->assertSessionHas('cart', $cart);

        $this->assertGuest('customer');
    }

    /** بند 4.2: الخروج فعل يغيّر الحالة — POST + CSRF، لا GET. */
    public function test_logout_is_not_reachable_by_get(): void
    {
        $this->actingAs($this->makeCustomer(), 'customer')
            ->get('/account/logout')
            ->assertStatus(405);
    }

    /*
    |--------------------------------------------------------------------------
    | عزل الطلبات
    |--------------------------------------------------------------------------
    */

    public function test_a_customer_cannot_open_another_customers_order(): void
    {
        $mine = $this->makeCustomer();
        $hers = $this->makeCustomer(['email' => 'om.sara@example.com'], phone: '01099999999');

        $myOrder = OrderFactory::new()->create(['customer_id' => $mine->id]);
        $herOrder = OrderFactory::new()->create(['customer_id' => $hers->id]);

        // إثبات أن الاختبار ليس صوريًا: طلبها هي يُفتح فعلًا.
        $this->actingAs($mine, 'customer')
            ->get(route('customer.orders.show', $myOrder))
            ->assertOk();

        // 404 لا 403: الرمز 403 يؤكد أن الطلب موجود.
        $this->actingAs($mine, 'customer')
            ->get(route('customer.orders.show', $herOrder))
            ->assertNotFound();
    }

    /** مطابقة رقم الجوال وحدها لا تمنح رؤية طلب ضيف — العرض بـ customer_id فقط. */
    public function test_a_guest_order_with_the_same_phone_stays_invisible_in_the_account(): void
    {
        $customer = $this->makeCustomer();
        $guestOrder = OrderFactory::new()->create(['customer_phone' => self::PHONE_LOCAL]);

        $this->actingAs($customer, 'customer')
            ->get(route('customer.orders.show', $guestOrder))
            ->assertNotFound();
    }

    /*
    |--------------------------------------------------------------------------
    | استعادة كلمة المرور
    |--------------------------------------------------------------------------
    */

    public function test_password_reset_link_is_emailed_and_the_new_password_works(): void
    {
        Notification::fake();

        $customer = $this->makeCustomer();

        $this->post(route('customer.password.email'), ['email' => $customer->email])
            ->assertSessionHas('status', __('account.password.status.sent'));

        Notification::assertCount(1);
        $this->assertDatabaseCount('customer_password_reset_tokens', 1);

        // الرمز يُخزَّن مجزّأً فلا يُقرأ من الجدول؛ نطلب رمزًا صالحًا من الوسيط نفسه.
        $token = Password::broker('customers')->createToken($customer);

        $this->post(route('customer.password.update'), [
            'token' => $token,
            'email' => $customer->email,
            'password' => self::NEW_PASSWORD,
            'password_confirmation' => self::NEW_PASSWORD,
        ])->assertRedirect(route('customer.login.show'));

        $this->assertTrue(Hash::check(self::NEW_PASSWORD, (string) $customer->fresh()?->password));

        // الرمز يُستهلَك مرة واحدة.
        $this->assertDatabaseCount('customer_password_reset_tokens', 0);

        // الكلمة القديمة ماتت، والجديدة تعمل.
        $this->post(route('customer.login.store'), [
            'phone' => self::PHONE_LOCAL,
            'password' => self::PASSWORD,
        ])->assertSessionHasErrors(['phone' => __('auth.failed')]);

        $this->post(route('customer.login.store'), [
            'phone' => self::PHONE_LOCAL,
            'password' => self::NEW_PASSWORD,
        ])->assertRedirect(route('customer.dashboard'));
    }

    /** منع تعداد البُرد: الرد نفسه حرفيًا لبريد غير مسجَّل، وبلا أي إرسال. */
    public function test_password_reset_request_looks_identical_for_an_unknown_email(): void
    {
        Notification::fake();

        $this->post(route('customer.password.email'), ['email' => 'nobody@example.com'])
            ->assertSessionHas('status', __('account.password.status.sent'));

        Notification::assertNothingSent();
        $this->assertDatabaseCount('customer_password_reset_tokens', 0);
    }

    public function test_password_reset_rejects_a_forged_token(): void
    {
        $customer = $this->makeCustomer();

        $this->from(route('customer.password.reset', ['token' => 'forged-token']))
            ->post(route('customer.password.update'), [
                'token' => 'forged-token',
                'email' => $customer->email,
                'password' => self::NEW_PASSWORD,
                'password_confirmation' => self::NEW_PASSWORD,
            ])
            ->assertSessionHasErrors(['email' => __('account.password.status.token')]);

        $this->assertTrue(Hash::check(self::PASSWORD, (string) $customer->fresh()?->password));
    }

    /** رمز عميلة أخرى لا يفتح حساب هذه — البريد والرمز يجب أن يتطابقا معًا. */
    public function test_a_token_issued_for_another_account_cannot_reset_this_one(): void
    {
        $victim = $this->makeCustomer();
        $attacker = $this->makeCustomer(['email' => 'attacker@example.com'], phone: '01099999999');

        $attackerToken = Password::broker('customers')->createToken($attacker);

        $this->from(route('customer.password.reset', ['token' => $attackerToken]))
            ->post(route('customer.password.update'), [
                'token' => $attackerToken,
                'email' => $victim->email,
                'password' => self::NEW_PASSWORD,
                'password_confirmation' => self::NEW_PASSWORD,
            ])
            ->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check(self::PASSWORD, (string) $victim->fresh()?->password));
    }

    public function test_the_reset_form_is_served_for_any_token_without_confirming_it(): void
    {
        // الصفحة لا تفصح عن صلاحية الرمز — الفصل يقع عند الإرسال فقط.
        $this->get(route('customer.password.reset', ['token' => 'whatever-token']))
            ->assertOk();
    }
}
