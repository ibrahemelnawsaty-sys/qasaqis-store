<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Actions\NormalizeArabicSearch;
use App\Models\Book;
use PHPUnit\Framework\TestCase;

/**
 * Arabic search normalization (constitution 0.9 / anti-pattern 28): a query must
 * match regardless of hamza/alef/ta-marbuta/alef-maqsura spelling or tashkeel.
 *
 * Pure logic — no database, no app container: Book::normalizeArabic() and
 * NormalizeArabicSearch are deterministic string transforms. This is the
 * foundation the FULLTEXT search relies on (both index and query use it), so
 * verifying it here proves the matching guarantee independently of MySQL.
 *
 * HONESTY (constitution 1.3/1.5): this environment has no PHP, so these tests were
 * NOT executed here. They are written to run with `php artisan test` on the
 * hosting / any PHP 8.2+ environment.
 */
final class ArabicSearchNormalizationTest extends TestCase
{
    private NormalizeArabicSearch $normalizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->normalizer = new NormalizeArabicSearch();
    }

    public function test_hamza_variants_of_alef_normalize_to_bare_alef(): void
    {
        // أ / إ / آ / ٱ all collapse to ا, so "أحمد" and "احمد" are equal.
        $this->assertSame(Book::normalizeArabic('احمد'), Book::normalizeArabic('أحمد'));
        $this->assertSame(Book::normalizeArabic('احمد'), Book::normalizeArabic('إحمد'));
        $this->assertSame(Book::normalizeArabic('احمد'), Book::normalizeArabic('آحمد'));
    }

    public function test_ta_marbuta_normalizes_to_ha(): void
    {
        // ة => ه, so "حكاية" and "حكايه" match.
        $this->assertSame('حكايه', Book::normalizeArabic('حكاية'));
        $this->assertSame(Book::normalizeArabic('حكايه'), Book::normalizeArabic('حكاية'));
    }

    public function test_alef_maqsura_normalizes_to_ya(): void
    {
        // ى => ي, so "مصطفى" and "مصطفي" match.
        $this->assertSame(Book::normalizeArabic('مصطفي'), Book::normalizeArabic('مصطفى'));
    }

    public function test_hamza_on_waw_and_ya_are_unified(): void
    {
        $this->assertSame('سوال', Book::normalizeArabic('سؤال'));   // ؤ => و
        $this->assertSame('قاري', Book::normalizeArabic('قارئ'));   // ئ => ي
    }

    public function test_tashkeel_and_tatweel_are_stripped(): void
    {
        // Harakat (fatha/damma/kasra/shadda…) and kashida vanish.
        $this->assertSame('كتاب', Book::normalizeArabic('كِتَاب'));
        $this->assertSame('كتاب', Book::normalizeArabic('كـــتـــاب')); // tatweel
        $this->assertSame(
            Book::normalizeArabic('مدرسة'),
            Book::normalizeArabic('مَدْرَسَةٌ'),
        );
    }

    public function test_whitespace_is_collapsed_and_latin_lowercased(): void
    {
        $this->assertSame('kitab moon', Book::normalizeArabic("  KITAB   MOON  "));
    }

    public function test_for_search_drops_the_definite_article(): void
    {
        // The query side drops «ال» so "الكتاب" and "كتاب" both hit the index.
        $this->assertSame('كتاب', $this->normalizer->forSearch('الكتاب'));
        $this->assertSame('كتاب جميل', $this->normalizer->forSearch('الكتاب الجميل'));
    }

    public function test_words_splits_normalized_query_into_tokens(): void
    {
        $this->assertSame(['كتاب', 'احمد'], $this->normalizer->words('الكتاب أحمد'));
        $this->assertSame([], $this->normalizer->words('   '));
        $this->assertSame([], $this->normalizer->words(null));
    }

    public function test_empty_and_null_input_returns_empty_string(): void
    {
        $this->assertSame('', Book::normalizeArabic(null));
        $this->assertSame('', Book::normalizeArabic(''));
    }

    public function test_strip_definite_article_only_touches_leading_al(): void
    {
        // «ال» is stripped as a word prefix, but a word that merely contains "ال"
        // mid-string (e.g. "جمال") must stay intact.
        $this->assertSame('جمال', Book::stripDefiniteArticle('جمال'));
        $this->assertSame('كتاب', Book::stripDefiniteArticle('الكتاب'));
    }
}
