<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Coupon;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Factory for the Coupon model. Fields mirror the coupons migration EXACTLY
 * (verified against create_coupons_table): code, type(percentage|fixed), value,
 * min_order_total, max_discount, starts_at, expires_at, usage_limit,
 * usage_limit_per_user, used_count, applies_to(all|categories|products),
 * is_active, free_shipping. Money stays DECIMAL — never float (constitution 27).
 *
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Coupon>
 */
class CouponFactory extends Factory
{
    protected $model = Coupon::class;

    /**
     * A valid, active, all-scope percentage coupon by default.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => strtoupper(Str::random(8)),
            'description' => null,
            'type' => 'percentage',
            'value' => 10, // 10% — DECIMAL(10,2) via the model cast.
            'min_order_total' => null,
            'max_discount' => null,
            'starts_at' => null,
            'expires_at' => null,
            'usage_limit' => null,
            'usage_limit_per_user' => null,
            'used_count' => 0,
            'applies_to' => 'all',
            'is_active' => true,
            'free_shipping' => false,
        ];
    }

    /** Fixed-amount coupon (value is EGP, not a percentage). */
    public function fixed(int|string $amount = 50): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'fixed',
            'value' => $amount,
        ]);
    }

    public function percentage(int|string $percent = 10): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'percentage',
            'value' => $percent,
        ]);
    }

    /** Already expired (yesterday). */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'starts_at' => now()->subDays(10),
            'expires_at' => now()->subDay(),
        ]);
    }

    /** Not started yet (starts tomorrow). */
    public function notStarted(): static
    {
        return $this->state(fn (array $attributes) => [
            'starts_at' => now()->addDay(),
            'expires_at' => now()->addDays(10),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }

    public function minTotal(int|string $min): static
    {
        return $this->state(fn (array $attributes) => ['min_order_total' => $min]);
    }

    /** Global usage cap. */
    public function usageLimit(int $limit): static
    {
        return $this->state(fn (array $attributes) => ['usage_limit' => $limit]);
    }

    public function perUserLimit(int $limit): static
    {
        return $this->state(fn (array $attributes) => ['usage_limit_per_user' => $limit]);
    }

    public function freeShipping(): static
    {
        return $this->state(fn (array $attributes) => ['free_shipping' => true]);
    }
}
