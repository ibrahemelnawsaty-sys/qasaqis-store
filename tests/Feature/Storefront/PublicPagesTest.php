<?php

declare(strict_types=1);

namespace Tests\Feature\Storefront;

use App\Models\Book;
use App\Models\Category;
use Database\Seeders\CategorySeeder;
use Database\Seeders\PublisherSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The critical public storefront pages must boot with HTTP 200, and an
 * unpublished book must 404 (constitution: only published books are visible).
 * All six sections stay reachable even when empty (constitution 0.3).
 *
 * HONESTY (1.3/1.5): NOT executed in this PHP-less environment; runs on the
 * hosting via `php artisan test`. Requires MySQL 8 (the search page/filters use
 * the InnoDB FULLTEXT index defined in the books migration).
 */
final class PublicPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Six fixed sections + real publishers (idempotent seeders).
        $this->seed([CategorySeeder::class, PublisherSeeder::class]);
    }

    public function test_home_page_loads(): void
    {
        Book::factory()->count(3)->create();

        $this->get(route('home'))->assertOk();
    }

    public function test_books_index_loads(): void
    {
        Book::factory()->count(5)->create();

        $this->get(route('books.index'))->assertOk();
    }

    public function test_published_book_page_loads(): void
    {
        $book = Book::factory()->create(['is_published' => true]);

        $this->get(route('books.show', $book))->assertOk();
    }

    public function test_unpublished_book_page_returns_404(): void
    {
        $book = Book::factory()->unpublished()->create();

        // The book row exists (slug binding resolves) but the controller aborts.
        $this->get(route('books.show', $book))->assertNotFound();
    }

    public function test_search_page_loads(): void
    {
        Book::factory()->count(3)->create();

        $this->get(route('search', ['q' => 'كتاب']))->assertOk();
    }

    public function test_search_page_loads_without_a_query(): void
    {
        $this->get(route('search'))->assertOk();
    }

    public function test_category_page_with_books_loads(): void
    {
        $category = Category::query()->where('slug', 'stories')->firstOrFail();
        Book::factory()->count(2)->create(['category_id' => $category->id]);

        $this->get(route('categories.show', $category))->assertOk();
    }

    public function test_empty_section_still_loads(): void
    {
        // «روايات» (novels) currently holds 0 books but MUST remain reachable.
        $empty = Category::query()->where('slug', 'novels')->firstOrFail();

        $this->assertSame(0, $empty->books()->count());
        $this->get(route('categories.show', $empty))->assertOk();
    }

    public function test_inactive_category_returns_404(): void
    {
        $category = Category::factory()->inactive()->create();

        $this->get(route('categories.show', $category))->assertNotFound();
    }
}
