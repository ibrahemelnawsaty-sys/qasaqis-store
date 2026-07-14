<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Book;

/**
 * Keeps a book's Arabic search columns in sync on every write.
 *
 * Closes a documented gap: books created/edited from the Filament admin must be
 * indexed for the storefront search engine (constitution 0.9). Without this,
 * `title_normalized` (prefix autocomplete) and `search_index` (the unified
 * normalized FULLTEXT blob) would only be populated when the title mutator
 * happened to fire, missing publisher/category changes and admin-side edits.
 */
class BookObserver
{
    /**
     * Runs before both insert and update, so the normalized columns are written
     * inside the same query as the rest of the row — no extra save, no drift.
     */
    public function saving(Book $book): void
    {
        // Refresh the prefix-autocomplete column even when the title was set
        // without passing through the model's mutator (forceFill, copy, import).
        $book->title_normalized = Book::normalizeArabic((string) $book->title);

        // The unified blob needs publisher & category names; make sure both
        // relations are available before refreshSearchIndex() reads them. Only
        // queries the ones not already loaded (publisher has a withDefault()).
        $book->loadMissing(['publisher', 'category']);

        // Rebuild search_index from the current field + relation values.
        $book->refreshSearchIndex();
    }
}
