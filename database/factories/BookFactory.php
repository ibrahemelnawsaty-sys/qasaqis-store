<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Book;
use App\Models\Category;
use App\Models\Publisher;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Book>
 */
class BookFactory extends Factory
{
    protected $model = Book::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->unique()->sentence(3);
        // Price range mirrors the real catalogue (150–450 EGP). DECIMAL, never float.
        $price = fake()->numberBetween(150, 450);

        return [
            'category_id' => Category::factory(),
            'publisher_id' => Publisher::factory(),
            'title' => $title, // mutator also fills title_normalized.
            'slug' => Str::slug($title).'-'.fake()->unique()->numberBetween(1, 999999),
            'sku' => strtoupper(Str::random(8)),
            'author' => fake()->name(),
            'illustrator' => fake()->optional()->name(),
            'short_description' => fake()->sentence(),
            'long_description' => '<p>'.fake()->paragraph().'</p>',
            'price' => $price,
            'old_price' => fake()->optional()->numberBetween($price, 500),
            'cost_price' => fake()->numberBetween(50, 150),
            'stock_quantity' => fake()->numberBetween(0, 100),
            'stock_status' => 'in_stock',
            'manage_stock' => true,
            'age_min' => 3,
            'age_max' => 9,
            'age_label' => '3 - 9 سنوات',
            'pages_count' => fake()->numberBetween(16, 64),
            'isbn' => null,
            'weight_grams' => fake()->numberBetween(100, 500),
            'learning_outcomes' => [fake()->sentence(), fake()->sentence()],
            'cover_image' => 'books/'.Str::random(10).'.webp',
            'is_published' => true,
            'is_featured' => false,
            'published_at' => now(),
            'sort_order' => 0,
        ];
    }

    /**
     * Draft / unpublished book.
     */
    public function unpublished(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_published' => false,
            'published_at' => null,
        ]);
    }

    public function featured(): static
    {
        return $this->state(fn (array $attributes) => ['is_featured' => true]);
    }

    /**
     * Edge case: a book with no price yet (like BOOK1). Cannot be published.
     */
    public function withoutPrice(): static
    {
        return $this->state(fn (array $attributes) => [
            'price' => null,
            'old_price' => null,
            'is_published' => false,
            'published_at' => null,
        ]);
    }

    /**
     * Edge case: a book with no cover image (like BOOK10) — uses a placeholder.
     */
    public function withoutCover(): static
    {
        return $this->state(fn (array $attributes) => ['cover_image' => null]);
    }
}
