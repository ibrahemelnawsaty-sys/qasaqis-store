<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Page;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Factory for the Page model (CMS pages: من نحن، سياسة الشحن…). Fields mirror the
 * create_pages_table migration EXACTLY: title, slug(unique), content, template,
 * is_published, published_at, sort_order (+ softDeletes). Published by default;
 * use draft() for an unpublished page that must 404 via pages.show.
 *
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Page>
 */
class PageFactory extends Factory
{
    protected $model = Page::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->unique()->sentence(3);

        return [
            'title' => $title,
            'slug' => Str::slug($title).'-'.fake()->unique()->numberBetween(1, 999999),
            'content' => '<p>'.fake()->paragraph().'</p>',
            'template' => null,
            'is_published' => true,
            'published_at' => now(),
            'sort_order' => 0,
        ];
    }

    /** Unpublished draft — must not be publicly visible (pages.show → 404). */
    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_published' => false,
            'published_at' => null,
        ]);
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_published' => true,
            'published_at' => now(),
        ]);
    }
}
