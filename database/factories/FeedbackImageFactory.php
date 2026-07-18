<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\FeedbackImage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\FeedbackImage>
 */
class FeedbackImageFactory extends Factory
{
    protected $model = FeedbackImage::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'image_path' => 'images/reviews/review-'.fake()->numberBetween(1, 9).'.webp',
            'alt' => fake()->optional()->sentence(3),
            'is_active' => true,
            'sort_order' => fake()->numberBetween(0, 20),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }
}
