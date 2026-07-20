<?php

declare(strict_types=1);

namespace Tests\Feature\Account;

use App\Models\Customer;
use App\Models\Order;
use App\Support\Phone\PhoneNormalizer;
use Database\Factories\CustomerFactory;
use Database\Factories\OrderFactory;
use Filament\Models\Contracts\FilamentUser;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * أساس نظام حسابات العملاء: المطبِّع، الموديل، والسكيمة (customers + orders.customer_id).
 *
 * يحتاج قاعدة بيانات: يفحص قيودًا حقيقية (UNIQUE، المفتاح الخارجي، الفهارس) لا
 * يمكن إثباتها بقراءة الكود.
 */
final class CustomerModelTest extends TestCase
{
    use RefreshDatabase;

    // ---------------------------------------------------------------------
    // PhoneNormalizer — الحالات الحدّية
    // ---------------------------------------------------------------------

    public function test_normalize_accepts_every_egyptian_prefix_form(): void
    {
        $cases = [
            'بلا بادئة' => '1012345678',
            'صفر محلي' => '01012345678',
            'دولي بعلامة زائد' => '+201012345678',
            'دولي بلا زائد' => '201012345678',
            'دولي بصفرين' => '00201012345678',
            'زائد وصفر معًا' => '+2001012345678',
        ];

        foreach ($cases as $label => $input) {
            $this->assertSame('1012345678', PhoneNormalizer::normalize($input), $label);
        }
    }

    public function test_normalize_strips_formatting_characters(): void
    {
        $cases = [
            'مسافات' => '+20 101 234 5678',
            'شرطات' => '010-1234-5678',
            'أقواس ومسافات' => '(+20) 010 1234 5678',
            'مسافات طرفية' => '   01012345678   ',
            'نقاط' => '010.1234.5678',
        ];

        foreach ($cases as $label => $input) {
            $this->assertSame('1012345678', PhoneNormalizer::normalize($input), $label);
        }
    }

    public function test_normalize_accepts_the_four_egyptian_operator_prefixes(): void
    {
        foreach (['10', '11', '12', '15'] as $operator) {
            $raw = '0'.$operator.'12345678';

            $this->assertSame($operator.'12345678', PhoneNormalizer::normalize($raw), $raw);
        }
    }

    public function test_normalize_rejects_unknown_operator_prefixes(): void
    {
        // 13/14/16/17/18/19 ليست بادئات مشغّلين مصريين في EGYPT_PHONE_REGEX.
        foreach (['13', '14', '16', '17', '18', '19'] as $operator) {
            $raw = '0'.$operator.'12345678';

            $this->assertNull(PhoneNormalizer::normalize($raw), $raw);
        }
    }

    public function test_normalize_rejects_non_egyptian_and_malformed_input(): void
    {
        $cases = [
            'أمريكي' => '+13105551234',
            'سعودي' => '+966501234567',
            'بريطاني' => '+447911123456',
            'أرضي مصري' => '0227354321',
            'فارغ' => '',
            'مسافات فقط' => '   ',
            'null' => null,
            'قصير جدًا' => '0101234',
            'خانة ناقصة' => '0101234567',
            'حروف فقط' => 'not-a-phone',
            // preg_replace('/\D/') بلا معدّل /u يجرّد الأرقام العربية-الهندية
            // بايتًا بايتًا. سلوك مطابق لـ FindGuestOrderAction ولـ CheckoutRequest
            // الذي يرفضها أصلًا — موثّق في PhoneNormalizer كقيد معلَن.
            'أرقام عربية-هندية' => '٠١٠١٢٣٤٥٦٧٨',
        ];

        foreach ($cases as $label => $input) {
            $this->assertNull(PhoneNormalizer::normalize($input), $label);
        }
    }

    public function test_normalize_ignores_extra_leading_digits_like_the_guest_lookup_does(): void
    {
        // منطق «آخر 10 خانات» الموروث من FindGuestOrderAction: أي بادئة إضافية
        // تُتجاهَل ما دامت الخانات العشر الأخيرة جوالًا مصريًا صحيحًا.
        $this->assertSame('1012345678', PhoneNormalizer::normalize('99900201012345678'));
    }

    public function test_normalize_output_always_fits_the_identity_column(): void
    {
        $normalized = PhoneNormalizer::normalize('+201512345678');

        $this->assertNotNull($normalized);
        $this->assertSame(10, strlen($normalized), 'عمود phone_normalized هو CHAR(10)');
        $this->assertStringStartsWith('1', $normalized);
    }

    public function test_to_e164_prefixes_egypt_calling_code(): void
    {
        $this->assertSame('+201012345678', PhoneNormalizer::toE164('01012345678'));
        $this->assertSame('+201012345678', PhoneNormalizer::toE164('+20 101 234 5678'));
        $this->assertSame('+201512345678', PhoneNormalizer::toE164('00201512345678'));
    }

    public function test_to_e164_returns_null_for_anything_normalize_rejects(): void
    {
        foreach (['+13105551234', '', null, '0101234', 'garbage'] as $input) {
            $this->assertNull(PhoneNormalizer::toE164($input), var_export($input, true));
        }
    }

    public function test_to_e164_stays_within_the_column_width(): void
    {
        $e164 = PhoneNormalizer::toE164('01012345678');

        $this->assertNotNull($e164);
        $this->assertLessThanOrEqual(20, strlen($e164), 'عمود phone_e164 هو VARCHAR(20)');
    }

    // ---------------------------------------------------------------------
    // موديل Customer
    // ---------------------------------------------------------------------

    public function test_it_uses_the_customers_table_and_is_not_an_admin(): void
    {
        $customer = CustomerFactory::new()->create();

        $this->assertSame('customers', $customer->getTable());

        // العزل عن لوحة التحكم: لا FilamentUser ولا أدوار spatie.
        $this->assertNotInstanceOf(FilamentUser::class, $customer);
        $this->assertFalse(method_exists($customer, 'canAccessPanel'));
        $this->assertFalse(method_exists($customer, 'hasRole'));
        $this->assertFalse(method_exists($customer, 'assignRole'));
    }

    public function test_it_satisfies_the_guard_and_password_broker_contracts(): void
    {
        $customer = CustomerFactory::new()->create(['email' => 'mom@example.test']);

        $this->assertInstanceOf(AuthenticatableContract::class, $customer);
        $this->assertInstanceOf(CanResetPasswordContract::class, $customer);
        $this->assertSame('id', $customer->getAuthIdentifierName());
        $this->assertSame($customer->id, $customer->getAuthIdentifier());
        $this->assertSame($customer->getAttributes()['password'], $customer->getAuthPassword());
        $this->assertSame('mom@example.test', $customer->getEmailForPasswordReset());
        // Notifiable لازمة لإرسال إشعار استعادة كلمة المرور.
        $this->assertTrue(method_exists($customer, 'notify'));
    }

    public function test_password_is_hashed_on_assignment(): void
    {
        $customer = CustomerFactory::new()->create();
        $customer->password = 'secret-password';
        $customer->save();

        $stored = $customer->fresh()->getAttributes()['password'];

        $this->assertNotSame('secret-password', $stored);
        $this->assertTrue(Hash::check('secret-password', $stored));
    }

    public function test_password_and_remember_token_are_hidden_from_serialization(): void
    {
        $customer = CustomerFactory::new()->create();
        $customer->setRememberToken('a-remember-token');
        $customer->save();

        $array = $customer->fresh()->toArray();

        $this->assertArrayNotHasKey('password', $array);
        $this->assertArrayNotHasKey('remember_token', $array);
        $this->assertArrayHasKey('phone_normalized', $array);
    }

    public function test_attribute_casts_match_the_column_types(): void
    {
        $customer = CustomerFactory::new()->create();
        $fresh = $customer->fresh();

        $this->assertIsBool($fresh->is_claimed);
        $this->assertFalse($fresh->is_claimed);
        $this->assertIsInt($fresh->orders_count);
        $this->assertSame(0, $fresh->orders_count);
        // decimal:2 يعيد سلسلة لا float (الباب 3.5).
        $this->assertSame('0.00', $fresh->total_spent);
        $this->assertNull($fresh->phone_verified_at);
        $this->assertNull($fresh->email_verified_at);
        $this->assertNull($fresh->deleted_at);
    }

    public function test_verified_at_columns_cast_to_dates_when_present(): void
    {
        $customer = CustomerFactory::new()->create();
        $customer->forceFill([
            'phone_verified_at' => now(),
            'email_verified_at' => now(),
        ])->save();

        $fresh = $customer->fresh();

        $this->assertInstanceOf(Carbon::class, $fresh->phone_verified_at);
        $this->assertInstanceOf(Carbon::class, $fresh->email_verified_at);
    }

    public function test_server_controlled_columns_are_not_mass_assignable(): void
    {
        // الجوال هوية، والعدّادات وعلامة الربط حالة يحكمها الخادم (الباب 4.1).
        $customer = new Customer([
            'name' => 'أم محمد',
            'phone_normalized' => '1099999999',
            'phone_e164' => '+201099999999',
            'is_claimed' => true,
            'orders_count' => 99,
            'total_spent' => '5000.00',
            'phone_verified_at' => now(),
            'email_verified_at' => now(),
        ]);

        $this->assertSame('أم محمد', $customer->name);

        foreach (['phone_normalized', 'phone_e164', 'is_claimed', 'orders_count', 'total_spent', 'phone_verified_at', 'email_verified_at'] as $guarded) {
            $this->assertArrayNotHasKey($guarded, $customer->getAttributes(), $guarded);
        }
    }

    public function test_phone_normalized_is_unique(): void
    {
        CustomerFactory::new()->create(['phone_normalized' => '1012345678']);

        $this->expectException(QueryException::class);

        CustomerFactory::new()->create(['phone_normalized' => '1012345678']);
    }

    public function test_phone_normalized_stays_reserved_after_a_soft_delete(): void
    {
        // قرار معماري: الحساب المحذوف ناعمًا يحجز رقمه فلا يرث شخصٌ لاحق طلبات
        // صاحبته. القيد الفريد على مستوى قاعدة البيانات هو ما يفرض ذلك.
        $customer = CustomerFactory::new()->create(['phone_normalized' => '1112345678']);
        $customer->delete();

        $this->assertSoftDeleted('customers', ['id' => $customer->id]);

        $this->expectException(QueryException::class);

        CustomerFactory::new()->create(['phone_normalized' => '1112345678']);
    }

    public function test_email_is_unique_but_optional_at_schema_level(): void
    {
        CustomerFactory::new()->create(['email' => 'same@example.test']);

        // السكيمة تسمح بـ NULL (التسجيل هو من يفرض البريد) وتسمح بتكرار NULL.
        CustomerFactory::new()->create(['email' => null]);
        CustomerFactory::new()->create(['email' => null]);
        $this->assertSame(2, Customer::query()->whereNull('email')->count());

        $this->expectException(QueryException::class);

        CustomerFactory::new()->create(['email' => 'same@example.test']);
    }

    public function test_password_may_be_null_at_schema_level(): void
    {
        $customer = CustomerFactory::new()->withoutPassword()->create();

        $this->assertNull($customer->fresh()->getAttributes()['password']);
    }

    public function test_soft_deleted_customers_leave_the_default_scope(): void
    {
        $customer = CustomerFactory::new()->create();
        $customer->delete();

        $this->assertNull(Customer::query()->find($customer->id));
        $this->assertNotNull(Customer::withTrashed()->find($customer->id));
    }

    public function test_factory_with_phone_state_uses_the_normalizer(): void
    {
        $customer = CustomerFactory::new()->withPhone('+20 101 234 5678')->create();

        $this->assertSame('1012345678', $customer->phone_normalized);
        $this->assertSame('+201012345678', $customer->phone_e164);
    }

    // ---------------------------------------------------------------------
    // السكيمة: orders.customer_id
    // ---------------------------------------------------------------------

    public function test_orders_table_gained_customer_columns_without_touching_user_id(): void
    {
        $this->assertTrue(Schema::hasColumn('orders', 'customer_id'));
        $this->assertTrue(Schema::hasColumn('orders', 'claimed_at'));
        // user_id القائم (يشير إلى الإداريين) لم يُمسّ.
        $this->assertTrue(Schema::hasColumn('orders', 'user_id'));
    }

    public function test_guest_orders_stay_unlinked(): void
    {
        $order = OrderFactory::new()->create();

        $this->assertNull($order->customer_id);
        $this->assertNull($order->claimed_at);
        $this->assertNull($order->user_id);
    }

    public function test_customer_orders_relation_returns_only_linked_orders(): void
    {
        $customer = CustomerFactory::new()->create();
        $other = CustomerFactory::new()->create();

        $mine = OrderFactory::new()->create(['customer_id' => $customer->id]);
        OrderFactory::new()->create(['customer_id' => $other->id]);
        // طلب ضيف بنفس رقم جوال العميلة — يجب ألا يظهر: الربط لا يكون بالجوال.
        OrderFactory::new()->create([
            'customer_id' => null,
            'customer_phone' => '0'.$customer->phone_normalized,
        ]);

        $ids = $customer->orders()->pluck('id')->all();

        $this->assertSame([$mine->id], $ids);
    }

    public function test_force_deleting_a_customer_nulls_the_link_and_keeps_the_order(): void
    {
        $customer = CustomerFactory::new()->create();
        $order = OrderFactory::new()->create(['customer_id' => $customer->id]);

        // الحذف الناعم لا يُفعّل nullOnDelete (لا حذف فعلي في قاعدة البيانات).
        $customer->delete();
        $this->assertSame($customer->id, $order->fresh()->customer_id);

        // الحذف النهائي يُفعّل القيد: الطلب يبقى ويصير بلا حساب.
        $customer->forceDelete();

        $reloaded = Order::query()->find($order->id);
        $this->assertNotNull($reloaded, 'حذف الحساب يجب ألا يمحو الطلب');
        $this->assertNull($reloaded->customer_id);
    }

    public function test_customer_id_is_indexed_exactly_once_as_a_composite_index(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('SHOW INDEX خاص بـ MySQL/MariaDB.');
        }

        $leading = [];

        foreach (DB::select('SHOW INDEX FROM orders') as $row) {
            if ((int) $row->Seq_in_index === 1 && $row->Column_name === 'customer_id') {
                $leading[] = $row->Key_name;
            }
        }

        // فهرس واحد فقط يبدأ بـ customer_id: المركّب الذي تبنّاه المفتاح الخارجي.
        // وجود اثنين يعني أن InnoDB أنشأ فهرسًا تلقائيًا مكرّرًا للقيد.
        $this->assertCount(1, $leading, 'فهارس تبدأ بـ customer_id: '.implode(', ', $leading));

        $columns = [];

        foreach (DB::select('SHOW INDEX FROM orders') as $row) {
            if ($row->Key_name === $leading[0]) {
                $columns[(int) $row->Seq_in_index] = $row->Column_name;
            }
        }

        ksort($columns);
        $this->assertSame(['customer_id', 'created_at'], array_values($columns));
    }
}
