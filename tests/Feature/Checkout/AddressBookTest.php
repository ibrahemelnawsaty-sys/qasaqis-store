<?php

declare(strict_types=1);

namespace Tests\Feature\Checkout;

use App\Models\Book;
use App\Models\Customer;
use Database\Seeders\PaymentMethodSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * دفتر العناوين المُسمّى (M12): يُحفظ عنوان الدفع تلقائيًّا (افتراضيًّا)، لا يتكرّر
 * لنفس العنوان، ويظهر محدِّد «عناويني المحفوظة» للعائدة.
 *
 * HONESTY (1.3/1.5): تعمل عبر php artisan test (MariaDB محليًا).
 */
final class AddressBookTest extends TestCase
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
            'price' => '100.00', 'stock_status' => 'in_stock',
            'stock_quantity' => 20, 'manage_stock' => true,
        ]);
    }

    /** @return array<string, mixed> */
    private function payload(Book $book, array $overrides = []): array
    {
        return array_merge([
            'name' => 'أم منى', 'phone' => '01011224488', 'email' => 'mona@example.com',
            'country_code' => 'EG', 'governorate' => 'الإسكندرية', 'city' => 'سموحة',
            'address' => 'شارع فوزي معاذ 12', 'payment_method' => 'instapay',
            'items' => [['book_id' => $book->id, 'qty' => 1]],
        ], $overrides);
    }

    private function placeAs(Customer $customer, Book $book, array $overrides = []): void
    {
        $this->be($customer, 'customer');
        Auth::shouldUse('web');
        $this->withSession(['cart' => [$book->id => 1]])->get(route('checkout.show'))->assertOk();
        $this->post(route('checkout.place'), $this->payload($book, $overrides))->assertStatus(302);
    }

    public function test_placing_an_order_saves_a_default_address_to_the_book(): void
    {
        $customer = Customer::factory()->withPhone('01011224488')->create();

        $this->placeAs($customer, $this->book());

        $address = $customer->addresses()->firstOrFail();
        $this->assertTrue($address->is_default);
        $this->assertSame('الإسكندرية', $address->governorate);
        $this->assertSame('شارع فوزي معاذ 12', $address->address_line);
        $this->assertSame('الإسكندرية · سموحة', $address->label);   // تسمية تلقائية: المحافظة · المدينة
        $this->assertSame(1, $customer->addresses()->count());
    }

    public function test_the_same_location_for_a_different_recipient_is_a_new_entry(): void
    {
        // توصيل لقريب في نفس المبنى: نفس السطر/المحافظة/المدينة لكن مستلِم مختلف.
        // يجب ألّا يُطمَس المستلِم الأول (لا update فوق سجلّه) بل يُنشأ عنوان ثانٍ.
        $customer = Customer::factory()->withPhone('01011224488')->create();

        $this->placeAs($customer, $book = $this->book(), ['name' => 'أم منى', 'phone' => '01011224488']);
        $this->placeAs($customer, $book, ['name' => 'خالتي هدى', 'phone' => '01099887766']);

        $this->assertSame(2, $customer->addresses()->count());
        // المستلِم الأول باقٍ لم يُكتب فوقه.
        $this->assertTrue($customer->addresses()->where('name', 'أم منى')->exists());
        $this->assertTrue($customer->addresses()->where('name', 'خالتي هدى')->exists());
    }

    public function test_a_second_order_with_the_same_address_does_not_duplicate(): void
    {
        $customer = Customer::factory()->withPhone('01011224488')->create();

        $this->placeAs($customer, $book = $this->book());
        $this->placeAs($customer, $book); // نفس العنوان تمامًا

        $this->assertSame(1, $customer->addresses()->count()); // لا تكرار
    }

    public function test_a_different_address_adds_a_second_entry_and_moves_the_default(): void
    {
        $customer = Customer::factory()->withPhone('01011224488')->create();

        $this->placeAs($customer, $book = $this->book());
        $this->placeAs($customer, $book, ['governorate' => 'القاهرة', 'city' => 'المعادي', 'address' => 'شارع 9']);

        $this->assertSame(2, $customer->addresses()->count());
        // الافتراضيّ انتقل للعنوان الأحدث، والقديم لم يعد افتراضيًّا.
        $this->assertSame('القاهرة', $customer->addresses()->where('is_default', true)->value('governorate'));
        $this->assertSame(1, $customer->addresses()->where('is_default', true)->count());
    }

    public function test_checkout_shows_the_saved_address_picker_for_a_returning_customer(): void
    {
        $customer = Customer::factory()->withPhone('01011224488')->create();
        $customer->addresses()->create([
            'label' => 'المنزل', 'name' => 'أم منى', 'phone' => '01011224488',
            'country_code' => 'EG', 'governorate' => 'الجيزة', 'city' => 'الدقي',
            'address_line' => 'شارع النيل 5', 'is_default' => true,
        ]);
        $book = $this->book();

        $response = $this->actingAs($customer, 'customer')
            ->withSession(['cart' => [$book->id => 1]])
            ->get(route('checkout.show'));

        $response->assertOk();
        $response->assertSee('عناويني المحفوظة', false);   // المحدِّد يظهر
        $response->assertSee('المنزل', false);             // اسم العنوان
        $response->assertSee('شارع النيل 5', false);       // ملخّص العنوان
    }
}
