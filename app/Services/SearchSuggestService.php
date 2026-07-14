<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\NormalizeArabicSearch;
use App\Models\Book;
use App\Models\Category;
use App\Models\Publisher;

/**
 * Lightweight autocomplete for the storefront search bar (constitution 0.9).
 *
 * Prefix-matches the Arabic-normalized columns so results appear as the user
 * types, capped to a handful per group to stay within the performance budget.
 * Categories have no normalized column, so the fixed six are normalized and
 * filtered in PHP (cheap) rather than inventing a schema change.
 */
final class SearchSuggestService
{
    /** Minimum normalized length before we bother querying. */
    private const MIN_TERM = 2;

    private const MAX_BOOKS = 6;

    private const MAX_PUBLISHERS = 4;

    private const MAX_CATEGORIES = 4;

    public function __construct(private readonly NormalizeArabicSearch $normalizer) {}

    /**
     * @return array{
     *     books: array<int, array{label: string, url: string}>,
     *     publishers: array<int, array{label: string, url: string}>,
     *     categories: array<int, array{label: string, url: string}>
     * }
     */
    public function suggest(?string $rawTerm): array
    {
        $term = $this->normalizer->forSearch($rawTerm);

        if (mb_strlen($term) < self::MIN_TERM) {
            return ['books' => [], 'publishers' => [], 'categories' => []];
        }

        return [
            'books' => $this->books($term),
            'publishers' => $this->publishers($term),
            'categories' => $this->categories($term),
        ];
    }

    /**
     * @return array<int, array{label: string, url: string}>
     */
    private function books(string $term): array
    {
        return Book::query()
            ->published()
            ->where('title_normalized', 'like', $term.'%')
            ->orderByDesc('is_featured')
            ->orderByDesc('published_at')
            ->limit(self::MAX_BOOKS)
            ->get(['id', 'title', 'slug'])
            ->map(fn (Book $book): array => [
                'label' => (string) $book->title,
                'url' => route('books.show', $book),
            ])
            ->all();
    }

    /**
     * @return array<int, array{label: string, url: string}>
     */
    private function publishers(string $term): array
    {
        return Publisher::query()
            ->active()
            ->where('name_normalized', 'like', $term.'%')
            ->orderBy('sort_order')
            ->limit(self::MAX_PUBLISHERS)
            ->get(['id', 'name', 'slug'])
            ->map(fn (Publisher $publisher): array => [
                'label' => (string) $publisher->name,
                // Publishers have no dedicated page — link to the filtered catalogue.
                'url' => route('books.index', ['pub' => [$publisher->id]]),
            ])
            ->all();
    }

    /**
     * @return array<int, array{label: string, url: string}>
     */
    private function categories(string $term): array
    {
        return Category::query()
            ->active()
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->get(['id', 'name', 'slug'])
            ->filter(fn (Category $category): bool => str_starts_with(
                Book::normalizeArabic($category->name),
                $term
            ))
            ->take(self::MAX_CATEGORIES)
            ->map(fn (Category $category): array => [
                'label' => (string) $category->name,
                'url' => route('categories.show', $category),
            ])
            ->values()
            ->all();
    }
}
