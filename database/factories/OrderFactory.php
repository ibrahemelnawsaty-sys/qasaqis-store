<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Factory for Order. Columns verified against create_orders_table. Money is
 * DECIMAL(10,2) — never float. payment_method / payment_status / status values
 * are taken ONLY from the actual DB enums:
 *   status:         pending|confirmed|processing|shipped|delivered|completed|cancelled|refused|refunded
 *   payment_method: cod|instapay|vodafone_cash|bank_transfer|online_gateway
 *   payment_status: unpaid|pending_review|partially_paid|paid|refunded|failed
 *
 * NOTE: the Order model does NOT use HasFactory (app code is out of scope for the
 * tests task), so tests instantiate this via OrderFactory::new() rather than
 * Order::factory().
 *
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_number' => 'QSQ-'.date('Y').'-'.Str::upper(Str::random(6)),
            'user_id' => null, // guest checkout by default.
            'status' => 'pending',
            'customer_name' => fake()->name(),
            'customer_phone' => '0101'.fake()->numberBetween(1000000, 9999999),
            'customer_phone_alt' => null,
            'customer_email' => null,
            'governorate' => 'القاهرة',
            'city' => null,
            'address_line' => 'شارع تجريبي رقم '.fake()->numberBetween(1, 200),
            'address_notes' => null,
            'subtotal' => '200.00',
            'discount_total' => '0.00',
            'shipping_total' => '0.00',
            'grand_total' => '200.00',
            'coupon_id' => null,
            'coupon_code' => null,
            'payment_method' => 'cod',
            'payment_status' => 'unpaid',
            'customer_note' => null,
            'admin_note' => null,
            'ip_address' => fake()->ipv4(),
        ];
    }

    /** COD: confirmed + unpaid (collected on delivery). */
    public function cod(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'confirmed',
            'payment_method' => 'cod',
            'payment_status' => 'unpaid',
        ]);
    }

    /** Manual transfer awaiting a proof upload / review. */
    public function manualTransfer(string $code = 'instapay'): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'payment_method' => $code,
            'payment_status' => 'pending_review',
        ]);
    }
}
