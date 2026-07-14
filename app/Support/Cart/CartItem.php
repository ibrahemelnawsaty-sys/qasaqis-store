<?php

declare(strict_types=1);

namespace App\Support\Cart;

use App\Models\Book;

/**
 * A single priced cart line. Prices are always recomputed from the DB book
 * (constitution 4.1) — never taken from client input.
 */
final readonly class CartItem
{
    public function __construct(
        public Book $book,
        public int $quantity,
        public string $unitPrice,  // decimal string from books.price.
        public string $lineTotal,  // unitPrice * quantity (decimal string).
    ) {
    }
}
