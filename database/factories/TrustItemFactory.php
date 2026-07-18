<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\TrustItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TrustItem>
 */
class TrustItemFactory extends Factory
{
    protected $model = TrustItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'icon' => fake()->randomElement(['globe', 'gift', 'badge-check', 'chat', 'truck', 'shield-check']),
            'title' => fake()->words(3, true),
            'description' => fake()->optional()->sentence(4),
            'is_active' => true,
            'sort_order' => fake()->numberBetween(0, 20),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }
}
