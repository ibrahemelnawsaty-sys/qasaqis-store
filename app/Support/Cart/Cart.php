<?php

declare(strict_types=1);

namespace App\Support\Cart;

/**
 * Immutable snapshot of a priced cart, built by CartService from a list of
 * {book_id, qty} entries. All money fields are decimal strings.
 */
final readonly class Cart
{
    /**
     * @param  array<int, CartItem>  $items
     * @param  array<int, int>  $ignoredBookIds  book ids dropped (unpublished / no price)
     */
    public function __construct(
        public array $items,
        public string $subtotal,
        public int $count,
        public array $ignoredBookIds = [],
    ) {
    }

    public function isEmpty(): bool
    {
        return $this->items === [];
    }

    /**
     * @return array<int, int>
     */
    public function bookIds(): array
    {
        return array_map(static fn (CartItem $i): int => $i->book->id, $this->items);
    }
}
