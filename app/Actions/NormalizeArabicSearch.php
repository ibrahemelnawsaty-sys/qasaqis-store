<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Book;

/**
 * Arabic search normalization for the storefront search engine.
 *
 * Reuses the single verified normalizer on the Book model (unifies hamza/alef/
 * ta-marbuta/alef-maqsura, strips tashkeel & tatweel) so indexing and querying
 * always agree, and adds a search-only step that drops the leading definite
 * article «ال» from each word — matching how users spell queries vs. how titles
 * are stored. All done in PHP (never in SQL) per the DB conventions.
 */
final class NormalizeArabicSearch
{
    /**
     * Base normalization (kept identical to the stored search fields).
     */
    public function normalize(?string $text): string
    {
        return Book::normalizeArabic($text);
    }

    /**
     * User-query normalization: base normalization + dropping the «ال» prefix.
     * The stored fields are matched by FULLTEXT prefix (and a LIKE fallback for
     * short tokens), so dropping «ال» lets "الكتاب" and "كتاب" both match.
     */
    public function forSearch(?string $text): string
    {
        // Same «ال»-stripping the index uses (Book::stripDefiniteArticle), so the
        // query and the stored search_index always agree.
        return Book::stripDefiniteArticle(Book::normalizeArabic($text));
    }

    /**
     * Split a normalized query into individual, non-empty search words.
     *
     * @return array<int, string>
     */
    public function words(?string $text): array
    {
        $normalized = $this->forSearch($text);

        if ($normalized === '') {
            return [];
        }

        return array_values(array_filter(
            explode(' ', $normalized),
            static fn (string $w): bool => $w !== '',
        ));
    }

    public function __invoke(?string $text): string
    {
        return $this->forSearch($text);
    }
}
