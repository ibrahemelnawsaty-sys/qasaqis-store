<?php

declare(strict_types=1);

namespace Tests\Feature\Checkout;

use App\Models\Book;
use App\Models\Country;
use App\Models\Customer;
use App\Models\ShippingZone;
use Database\Seeders\CountrySeeder;
use Database\Seeders\PaymentMethodSeeder;
use Database\Seeders\ShippingZoneSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * ملء صفحة الدفع تلقائيًّا للعميلة المسجّلة من حسابها وآخر عنوان، وحفظ العنوان عند
 * كل طلب — كي لا تُعيد إدخال كل شيء في كل مرة (M12).
 *
 * HONESTY (1.3/1.5): تعمل عبر php artisan test (MariaDB محليًا).
 */
final class CheckoutPrefillTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PaymentMethodSeeder::class);
    }

    private function book(): Book
    {
        return Book::factory()->create([
            'price' => '150.00', 'stock_status' => 'in_stock',
            'stock_quantity' => 10, 'manage_stock' => true,
        ]);
    }

    public function test_it_prefills_a_logged_in_customers_saved_details(): void
    {
        $customer = Customer::factory()->withPhone('01012345678')->create([
            'name' => 'أم خالد', 'email' => 'umkhaled@example.com',
            'last_governorate' => 'القاهرة', 'last_city' => 'مدينة نصر',
            'last_address_line' => 'شارع الحرية 10',
        ]);
        $book = $this->book();

        $response = $this->actingAs($customer, 'customer')
            ->withSession(['cart' => [$book->id => 1]])
            ->get(route('checkout.show'));

        $response->assertOk();
        $response->assertSee('أم خالد', false);            // الاسم
        $response->assertSee('01012345678', false);         // الجوال (0 + المطبّع)
        $response->assertSee('umkhaled@example.com', false); // الإيميل
        $response->assertSee('القاهرة', false);             // المحافظة المحفوظة
        $response->assertSee('مدينة نصر', false);           // المدينة
        $response->assertSee('شارع الحرية 10', false);      // العنوان
    }

    public function test_it_does_not_prefill_for_a_guest(): void
    {
        $book = $this->book();

        $response = $this->withSession(['cart' => [$book->id => 1]])->get(route('checkout.show'));

        $response->assertOk();
        // حقل الاسم فارغ للزائرة (لا تسرّب بيانات).
        $response->assertSee('name="name" value=""', false);
    }

    public function test_placing_an_order_saves_the_address_for_a_logged_in_customer(): void
    {
        $customer = Customer::factory()->withPhone('01099887766')->create([
            'name' => 'أم سارة', 'last_governorate' => null, 'last_city' => null, 'last_address_line' => null,
        ]);
        $book = $this->book();

        // العميلة مصادَقة على حارس customer، والحارس الافتراضي يبقى web (null) كما في
        // الإنتاج — فلا يُدرَج معرّف العميلة في orders.user_id (المرتبط بجدول users).
        $this->be($customer, 'customer');
        \Illuminate\Support\Facades\Auth::shouldUse('web');

        $this->withSession(['cart' => [$book->id => 1]])
            ->get(route('checkout.show'))->assertOk();

        $this->post(route('checkout.place'), [
            'name' => 'أم سارة', 'phone' => '01099887766', 'email' => 'sara@example.com',
            'country_code' => 'EG', 'governorate' => 'الجيزة', 'city' => 'الدقي',
            'address' => 'شارع النصر رقم 20', 'payment_method' => 'instapay',
            'items' => [['book_id' => $book->id, 'qty' => 1]],
        ])->assertStatus(302);

        $customer->refresh();
        $this->assertSame('الجيزة', $customer->last_governorate);
        $this->assertSame('الدقي', $customer->last_city);
        $this->assertSame('شارع النصر رقم 20', $customer->last_address_line);
    }

    public function test_a_long_governorate_does_not_break_checkout_completion(): void
    {
        // نطاق دوليّ يقبل governorate حرًّا (max:100) بينما عمود last_governorate(50):
        // القصّ + try/catch يضمنان وصول العميلة لبوابة الدفع (302) لا خطأ 500.
        $this->seed(ShippingZoneSeeder::class);
        $this->seed(CountrySeeder::class);
        ShippingZone::query()->where('code', 'GULF')->update(['flat_cost' => '100.00', 'is_active' => true]);
        Country::query()->where('iso_code', 'SA')->update(['is_active' => true]);

        $customer = Customer::factory()->withPhone('01044556677')->create();
        $book = $this->book();
        $longGovernorate = str_repeat('ولاية طويلة جدًّا ', 6); // أطول من 50 حرفًا

        $this->be($customer, 'customer');
        Auth::shouldUse('web');
        $this->withSession(['cart' => [$book->id => 1]])->get(route('checkout.show'))->assertOk();

        $this->post(route('checkout.place'), [
            'name' => 'أم ليان', 'phone' => '01044556677', 'email' => 'layan@example.com',
            'country_code' => 'SA', 'governorate' => $longGovernorate, 'state_province' => 'الرياض',
            'address' => 'حي النخيل', 'payment_method' => 'instapay',
            'items' => [['book_id' => $book->id, 'qty' => 1]],
        ])->assertStatus(302); // اكتمل الطلب ووصلت للدفع، لا 500

        // العنوان حُفظ مقصوصًا على طول العمود (لا فيض).
        $this->assertLessThanOrEqual(50, mb_strlen((string) $customer->refresh()->last_governorate));
    }
}
