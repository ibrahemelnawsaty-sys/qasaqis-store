<?php

declare(strict_types=1);

namespace Tests\Feature\Checkout;

use App\Models\Book;
use App\Models\Country;
use App\Models\Order;
use App\Models\ShippingZone;
use Database\Seeders\CountrySeeder;
use Database\Seeders\PaymentMethodSeeder;
use Database\Seeders\ShippingZoneSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * الشحن الدولي (M5): الطلب المصري يبقى كما هو (محافظة + هاتف مصري + تسعير
 * config/egypt)، والطلب الدولي يعمل (دولة + ولاية + هاتف E.164 + شحن المنطقة)،
 * مع تحقّق شرطي يفصل الحالتين.
 *
 * NOTE: Order بلا HasFactory؛ الطلب يُنشأ عبر مسار checkout الحقيقي.
 * HONESTY (1.3/1.5): لم تُشغَّل هنا (لا PHP)؛ تعمل عبر `php artisan test` (MySQL).
 */
final class InternationalCheckoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ShippingZoneSeeder::class);
        $this->seed(CountrySeeder::class);
        $this->seed(PaymentMethodSeeder::class);
    }

    private function book(string $price = '200.00'): Book
    {
        return Book::factory()->create([
            'price' => $price,
            'stock_status' => 'in_stock',
            'stock_quantity' => 50,
            'manage_stock' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(Book $book, array $overrides = []): array
    {
        return array_merge([
            'name' => 'أم أحمد',
            'phone' => '01012345678',
            'country_code' => 'EG',
            'governorate' => 'القاهرة',
            'address' => 'شارع التجربة رقم 5',
            'payment_method' => 'instapay',
            'items' => [['book_id' => $book->id, 'qty' => 1]],
        ], $overrides);
    }

    public function test_egyptian_order_still_works_unchanged(): void
    {
        $book = $this->book();

        $this->post(route('checkout.place'), $this->payload($book))->assertStatus(302);

        $order = Order::firstOrFail();
        $this->assertSame('EG', $order->country_code);
        $this->assertSame('القاهرة', $order->governorate);
        $this->assertNull($order->state_province);
        $this->assertSame('EG', $order->shipping_zone_code);
    }

    public function test_international_order_places_with_zone_shipping(): void
    {
        ShippingZone::query()->where('code', 'GULF')->update(['flat_cost' => '300.00']);
        $book = $this->book('200.00');

        $this->post(route('checkout.place'), $this->payload($book, [
            'country_code' => 'SA',
            'governorate' => null,
            'state_province' => 'الرياض',
            'phone' => '+966512345678',
        ]))->assertStatus(302);

        $order = Order::firstOrFail();
        $this->assertSame('SA', $order->country_code);
        $this->assertNull($order->governorate);
        $this->assertSame('الرياض', $order->state_province);
        $this->assertSame('GULF', $order->shipping_zone_code);
        $this->assertSame('300.00', $order->shipping_total);
        $this->assertSame('500.00', $order->grand_total);
    }

    public function test_international_order_requires_state_and_e164_phone(): void
    {
        $book = $this->book();

        $this->from(route('checkout.show'))->post(route('checkout.place'), $this->payload($book, [
            'country_code' => 'SA', 'governorate' => null, 'state_province' => null, 'phone' => '+966512345678',
        ]))->assertSessionHasErrors('state_province');

        $this->from(route('checkout.show'))->post(route('checkout.place'), $this->payload($book, [
            'country_code' => 'SA', 'governorate' => null, 'state_province' => 'الرياض', 'phone' => '01012345678',
        ]))->assertSessionHasErrors('phone');
    }

    public function test_egypt_requires_governorate_and_egyptian_phone(): void
    {
        $book = $this->book();

        $this->from(route('checkout.show'))->post(route('checkout.place'), $this->payload($book, [
            'governorate' => null,
        ]))->assertSessionHasErrors('governorate');

        $this->from(route('checkout.show'))->post(route('checkout.place'), $this->payload($book, [
            'phone' => '+966512345678',
        ]))->assertSessionHasErrors('phone');
    }

    public function test_unknown_or_inactive_country_is_rejected(): void
    {
        $book = $this->book();

        $this->from(route('checkout.show'))->post(route('checkout.place'), $this->payload($book, [
            'country_code' => 'ZZ', 'governorate' => null, 'state_province' => 'X', 'phone' => '+441234567890',
        ]))->assertSessionHasErrors('country_code');

        Country::query()->where('iso_code', 'SA')->update(['is_active' => false]);

        $this->from(route('checkout.show'))->post(route('checkout.place'), $this->payload($book, [
            'country_code' => 'SA', 'governorate' => null, 'state_province' => 'الرياض', 'phone' => '+966512345678',
        ]))->assertSessionHasErrors('country_code');
    }

    public function test_free_shipping_coupon_zeroes_international_shipping(): void
    {
        ShippingZone::query()->where('code', 'GULF')->update(['flat_cost' => '300.00']);
        $book = $this->book('200.00');
        \Database\Factories\CouponFactory::new()->freeShipping()->create(['code' => 'FREESHIP']);

        $this->post(route('checkout.place'), $this->payload($book, [
            'country_code' => 'SA', 'governorate' => null, 'state_province' => 'الرياض',
            'phone' => '+966512345678', 'coupon' => 'FREESHIP',
        ]))->assertStatus(302);

        $order = Order::firstOrFail();
        $this->assertSame('0.00', $order->shipping_total);
        $this->assertNull($order->shipping_zone_code);
    }
}
