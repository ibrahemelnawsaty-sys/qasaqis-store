<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront\Concerns;

use App\Services\Cart\CartService;
use App\Support\Cart\Cart;
use Illuminate\Http\Request;

/**
 * Shared helper for reading the session cart ({book_id: qty}) and rebuilding it,
 * DB-priced, via CartService. Keeps the price-from-DB rule (4.1) in one place.
 */
trait InteractsWithSessionCart
{
    public function sessionCartKey(): string
    {
        return 'cart';
    }

    protected function buildSessionCart(Request $request, CartService $cartService): Cart
    {
        /** @var array<int, int> $map */
        $map = (array) $request->session()->get($this->sessionCartKey(), []);

        $items = [];
        foreach ($map as $bookId => $qty) {
            $items[] = ['book_id' => (int) $bookId, 'qty' => (int) $qty];
        }

        return $cartService->fromItems($items);
    }

    protected function forgetSessionCart(Request $request): void
    {
        $request->session()->forget($this->sessionCartKey());
    }
}
