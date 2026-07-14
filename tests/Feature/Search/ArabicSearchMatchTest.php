<?php

declare(strict_types=1);

namespace Tests\Feature\Search;

use App\Models\Book;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * End-to-end Arabic search: a query typed with a DIFFERENT hamza/ta-marbuta
 * spelling than the stored title still finds the book, because both the stored
 * `search_index` (filled by BookObserver) and the query are Arabic-normalized
 * the same way (constitution 0.9 / anti-pattern 28).
 *
 * REQUIRES MySQL 8: the storefront search uses the InnoDB FULLTEXT index on
 * `search_index` (books migration). On MySQL the default innodb_ft_min_token_size
 * is 3, so the test words are >= 3 normalized characters.
 *
 * HONESTY (1.3/1.5): NOT executed in this PHP-less environment; runs via
 * `php artisan test` against MySQL on the hosting.
 */
final class ArabicSearchMatchTest extends TestCase
{
    use RefreshDatabase;

    private function makeBook(string $title, string $slug): Book
    {
        // Explicit slug: Str::slug() strips pure-Arabic titles to empty, and the
        // slug is what the card link renders — a reliable assertion target.
        return Book::factory()->create([
            'title' => $title,
            'slug' => $slug,
            'is_published' => true,
        ]);
    }

    public function test_query_with_ta_marbuta_matches_title_stored_with_ta_marbuta(): void
    {
        $target = $this->makeBook('حكاية أحمد الصغير', 'hikaya-ahmad');
        $other = $this->makeBook('قصة الفيل الطيّب', 'qissat-al-fil');

        // Query uses «ه» where the title has «ة» — must still match.
        $response = $this->get(route('search', ['q' => 'حكايه']));

        $response->assertOk();
        $response->assertSee($target->slug, false);
        $response->assertDontSee($other->slug, false);
    }

    public function test_query_with_bare_alef_matches_title_written_with_hamza(): void
    {
        $target = $this->makeBook('مكتبة أحمد', 'maktabat-ahmad');
        $this->makeBook('حديقة الحيوان', 'hadiqat-alhayawan');

        // Title has «أحمد» (hamza-alef); query types «احمد» (bare alef).
        $response = $this->get(route('search', ['q' => 'احمد']));

        $response->assertOk();
        $response->assertSee($target->slug, false);
    }

    public function test_definite_article_prefix_is_ignored_in_the_query(): void
    {
        $target = $this->makeBook('مغامرات كتاب صغير', 'mughamarat-kitab');

        // "الكتاب" (with «ال») should still find a title storing "كتاب".
        $response = $this->get(route('search', ['q' => 'الكتاب']));

        $response->assertOk();
        $response->assertSee($target->slug, false);
    }

    public function test_unrelated_query_does_not_match(): void
    {
        $this->makeBook('حكاية أحمد الصغير', 'hikaya-ahmad');

        $response = $this->get(route('search', ['q' => 'ديناصور']));

        $response->assertOk();
        $response->assertDontSee('hikaya-ahmad', false);
    }
}
