<?php

declare(strict_types=1);

namespace Tests\Feature\Checkout;

use App\Models\Book;
use App\Models\Customer;
use App\Models\Order;
use Database\Factories\OrderFactory;
use Database\Seeders\PaymentMethodSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * ربط الطلب بحساب العميلة المسجّلة تلقائيًّا عند الشراء (M12) — فيظهر في «طلباتي»
 * بلا مطالبة يدوية، وتُحسب مُجمّعاتها (عدد الطلبات/الإنفاق) حيًّا من customer_id.
 * الزائرة تبقى غير مربوطة (customer_id = null، تربط لاحقًا يدويًّا).
 *
 * HONESTY (1.3/1.5): تعمل عبر php artisan test (MariaDB محليًا).
 */
final class OrderCustomerLinkTest extends TestCase
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
            'price' => '120.00', 'stock_status' => 'in_stock',
            'stock_quantity' => 10, 'manage_stock' => true,
        ]);
    }

    /** @return array<string, mixed> */
    private function payload(Book $book): array
    {
        return [
            'name' => 'أم يوسف', 'phone' => '01055443322', 'email' => 'yousef@example.com',
            'country_code' => 'EG', 'governorate' => 'القاهرة', 'address' => 'شارع ٩',
            'payment_method' => 'instapay', 'items' => [['book_id' => $book->id, 'qty' => 1]],
        ];
    }

    public function test_a_logged_in_customers_order_links_to_their_account(): void
    {
        $customer = Customer::factory()->withPhone('01055443322')->create();
        $book = $this->book();

        // حارس customer مصادَق، والافتراضي web (كالإنتاج) فلا يلتبس user_id.
        $this->be($customer, 'customer');
        Auth::shouldUse('web');
        $this->withSession(['cart' => [$book->id => 1]])->get(route('checkout.show'))->assertOk();

        $this->post(route('checkout.place'), $this->payload($book))->assertStatus(302);

        $order = Order::query()->latest('id')->firstOrFail();
        $this->assertSame($customer->id, $order->customer_id);   // مربوط بحسابها

        // ويظهر في «طلباتي» مباشرةً بلا مطالبة يدوية.
        $this->actingAs($customer, 'customer')
            ->get(route('customer.orders.index'))
            ->assertOk()
            ->assertSee($order->order_number, false);
    }

    public function test_the_account_link_survives_a_conflicting_idempotency_key(): void
    {
        // مفتاح منع التكرار نفسه بطريقة دفع أخرى يُفعّل إعادة بناء الـDTO
        // (withoutConflictingKey). الربط بحساب العميلة يجب أن ينجو (لا يُنسى الحقل).
        $customer = Customer::factory()->withPhone('01066554433')->create();
        $book = $this->book();

        $this->be($customer, 'customer');
        Auth::shouldUse('web');
        $this->withSession(['cart' => [$book->id => 1]])->get(route('checkout.show'))->assertOk();

        // طلب سابق بنفس المفتاح لكن طريقة دفع مختلفة (cod) — يخلق التعارض.
        OrderFactory::new()->create([
            'idempotency_key' => session('checkout.idempotency_key'),
            'payment_method' => 'cod',
        ]);

        $this->post(route('checkout.place'), $this->payload($book))->assertStatus(302); // instapay

        $order = Order::query()->where('payment_method', 'instapay')->latest('id')->firstOrFail();
        $this->assertSame($customer->id, $order->customer_id); // الربط نجا رغم التعارض
    }

    public function test_a_guest_order_is_not_linked_to_any_account(): void
    {
        $book = $this->book();

        $this->withSession(['cart' => [$book->id => 1]])->get(route('checkout.show'))->assertOk();
        $this->post(route('checkout.place'), $this->payload($book))->assertStatus(302);

        $this->assertNull(Order::query()->latest('id')->firstOrFail()->customer_id);
    }
}
