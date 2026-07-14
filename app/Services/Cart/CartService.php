<?php

declare(strict_types=1);

namespace App\Services\Cart;

use App\Models\Book;
use App\Support\Cart\Cart;
use App\Support\Cart\CartItem;
use App\Support\Money;

/**
 * Builds a priced cart from a raw list of {book_id, qty} entries.
 *
 * Constitution guarantees:
 *  - 4.1: every price is recomputed from the DB (books.price); client-supplied
 *    prices/totals are never trusted.
 *  - 0.4 / BOOK1: books that are unpublished OR have no price are silently
 *    dropped (ignoredBookIds) — no invented default price.
 *  - 3.5 / 27: money uses decimal strings via Money (bcmath), never float.
 */
class CartService
{
    /** Hard ceiling on a single line quantity to bound abuse / typos. */
    public const MAX_QTY_PER_LINE = 99;

    /**
     * @param  array<int, array{book_id?: mixed, qty?: mixed}>  $rawItems
     * @param  bool  $lock  wrap the book fetch in lockForUpdate (checkout path)
     */
    public function fromItems(array $rawItems, bool $lock = false): Cart
    {
        $quantities = $this->normalizeQuantities($rawItems);

        if ($quantities === []) {
            return new Cart([], Money::ZERO, 0, []);
        }

        $query = Book::query()
            ->published()
            ->whereNotNull('price')
            ->whereIn('id', array_keys($quantities))
            ->with('categories:id'); // needed for category-scoped coupons.

        if ($lock) {
            $query->lockForUpdate();
        }

        /** @var array<int, Book> $books */
        $books = $query->get()->keyBy('id')->all();

        $items = [];
        $subtotal = Money::ZERO;
        $ignored = [];

        foreach ($quantities as $bookId => $qty) {
            $book = $books[$bookId] ?? null;

            // Dropped: unpublished, no price (BOOK1), or unknown id.
            if ($book === null) {
                $ignored[] = $bookId;

                continue;
            }

            $unitPrice = Money::normalize($book->price);
            $lineTotal = Money::multiplyByQty($unitPrice, $qty);

            $items[] = new CartItem($book, $qty, $unitPrice, $lineTotal);
            $subtotal = Money::add($subtotal, $lineTotal);
        }

        $count = array_sum(array_map(static fn (CartItem $i): int => $i->quantity, $items));

        return new Cart($items, $subtotal, $count, $ignored);
    }

    /**
     * Collapse the raw list into book_id => qty, merging duplicates and clamping
     * quantity to [1, MAX_QTY_PER_LINE]. Invalid / non-positive rows are dropped.
     *
     * @param  array<int, array{book_id?: mixed, qty?: mixed}>  $rawItems
     * @return array<int, int>
     */
    private function normalizeQuantities(array $rawItems): array
    {
        $quantities = [];

        foreach ($rawItems as $row) {
            $bookId = (int) ($row['book_id'] ?? 0);
            $qty = (int) ($row['qty'] ?? 0);

            if ($bookId <= 0 || $qty <= 0) {
                continue;
            }

            $quantities[$bookId] = ($quantities[$bookId] ?? 0) + $qty;
        }

        foreach ($quantities as $bookId => $qty) {
            $quantities[$bookId] = min($qty, self::MAX_QTY_PER_LINE);
        }

        return $quantities;
    }
}
