<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\PaymentMethod;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Factory for PaymentMethod. Columns verified against create_payment_methods_table:
 * code(unique), name, type(cash_on_delivery|manual_transfer|online_gateway),
 * is_enabled, instructions, account_details(json), gateway_provider, config(json),
 * requires_proof, sort_order.
 *
 * NOTE: the PaymentMethod model does NOT use the HasFactory trait (app code is out
 * of scope for the tests task), so tests instantiate this via PaymentMethodFactory::new()
 * rather than PaymentMethod::factory().
 *
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PaymentMethod>
 */
class PaymentMethodFactory extends Factory
{
    protected $model = PaymentMethod::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Unique, non-enum code by default; use the states below for real codes
        // that match the orders.payment_method enum (cod/instapay/...).
        return [
            'code' => 'method_'.Str::lower(Str::random(8)),
            'name' => 'طريقة دفع',
            'type' => 'cash_on_delivery',
            'is_enabled' => true,
            'instructions' => null,
            'account_details' => null,
            'gateway_provider' => null,
            'config' => null,
            'requires_proof' => false,
            'sort_order' => 0,
        ];
    }

    /** Cash on delivery — the real seeded code. */
    public function cod(): static
    {
        return $this->state(fn (array $attributes) => [
            'code' => 'cod',
            'name' => 'الدفع عند الاستلام',
            'type' => 'cash_on_delivery',
            'requires_proof' => false,
            'sort_order' => 1,
        ]);
    }

    /** A manual-transfer method (requires an uploaded proof). */
    public function manualTransfer(string $code = 'instapay', string $name = 'إنستاباي'): static
    {
        return $this->state(fn (array $attributes) => [
            'code' => $code,
            'name' => $name,
            'type' => 'manual_transfer',
            'requires_proof' => true,
            'sort_order' => 2,
        ]);
    }

    /** The online gateway method (hidden unless online payment is enabled). */
    public function onlineGateway(): static
    {
        return $this->state(fn (array $attributes) => [
            'code' => 'online_gateway',
            'name' => 'الدفع أونلاين',
            'type' => 'online_gateway',
            'gateway_provider' => 'paymob',
            'requires_proof' => false,
            'sort_order' => 5,
        ]);
    }

    public function disabled(): static
    {
        return $this->state(fn (array $attributes) => ['is_enabled' => false]);
    }
}
