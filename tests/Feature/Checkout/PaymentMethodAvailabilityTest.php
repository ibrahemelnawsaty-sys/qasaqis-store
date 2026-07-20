<?php

declare(strict_types=1);

namespace Tests\Feature\Checkout;

use App\Models\Book;
use App\Models\Order;
use App\Services\Payment\PaymentMethodResolver;
use Database\Factories\PaymentMethodFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Online payment is OFFERED only when ONLINE_PAYMENT_ENABLED=true AND the default
 * gateway holds an API key (docs/04 §5.1 / PaymentMethodResolver). Otherwise the
 * online method is hidden — and, critically, rejected server-side at checkout, not
 * merely hidden in the UI (constitution 4.4 / anti-pattern 13). COD + manual
 * transfer keep working regardless.
 *
 * NOTE: PaymentMethod has no HasFactory trait; PaymentMethodFactory is used via ::new().
 *
 * HONESTY (1.3/1.5): NOT executed here (no PHP); runs via `php artisan test`.
 */
final class PaymentMethodAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    private function resolver(): PaymentMethodResolver
    {
        return app(PaymentMethodResolver::class);
    }

    private function seedRealMethods(): void
    {
        // COD + one manual transfer + the online gateway row (all enabled so the
        // ONLY thing gating the online option is the master switch + API key).
        PaymentMethodFactory::new()->cod()->create();
        PaymentMethodFactory::new()->manualTransfer('instapay', 'إنستاباي')->create();
        PaymentMethodFactory::new()->onlineGateway()->create();
    }

    public function test_online_is_hidden_when_the_master_switch_is_off(): void
    {
        config(['payment.online_enabled' => false]);
        $this->seedRealMethods();

        $codes = $this->resolver()->availableCodes();

        $this->assertFalse($this->resolver()->isOnlineEnabled());
        $this->assertNotContains('online_gateway', $codes);
        // Manual + COD still offered.
        $this->assertContains('cod', $codes);
        $this->assertContains('instapay', $codes);
    }

    public function test_online_is_hidden_when_enabled_but_no_api_key_configured(): void
    {
        config([
            'payment.online_enabled' => true,
            'payment.default' => 'paymob',
            'payment.gateways.paymob.api_key' => null, // no credentials.
        ]);
        $this->seedRealMethods();

        $this->assertFalse($this->resolver()->isOnlineEnabled());
        $this->assertNotContains('online_gateway', $this->resolver()->availableCodes());
    }

    public function test_online_is_offered_when_enabled_and_configured(): void
    {
        config([
            'payment.online_enabled' => true,
            'payment.default' => 'paymob',
            'payment.gateways.paymob.api_key' => 'test-key',
        ]);
        $this->seedRealMethods();

        $this->assertTrue($this->resolver()->isOnlineEnabled());
        $this->assertContains('online_gateway', $this->resolver()->availableCodes());
    }

    public function test_checkout_rejects_online_gateway_server_side_when_hidden(): void
    {
        config(['payment.online_enabled' => false]);
        $this->seedRealMethods();

        $book = Book::factory()->create([
            'price' => '200.00',
            'stock_status' => 'in_stock',
            'stock_quantity' => 10,
        ]);

        $response = $this->from(route('checkout.show'))->post(route('checkout.place'), [
            'name' => 'أم أحمد',
            'phone' => '01012345678',
            'email' => 'buyer@example.com',
            'governorate' => 'القاهرة',
            'address' => 'شارع التجربة رقم 5',
            'payment_method' => 'online_gateway', // hidden -> not in the whitelist.
            'items' => [['book_id' => $book->id, 'qty' => 1]],
        ]);

        // Validation rejects the disallowed method; no order is created.
        $response->assertSessionHasErrors('payment_method');
        $this->assertSame(0, Order::count());
    }
}
