<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Storefront\Concerns\InteractsWithSessionCart;
use App\Http\Requests\CartUpdateRequest;
use App\Services\Cart\CartService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The cart lives in the session as a plain map {book_id: qty}. Prices are always
 * (re)resolved from the DB via CartService — never stored client-side (4.1).
 */
class CartController extends Controller
{
    use InteractsWithSessionCart;

    public function __construct(private readonly CartService $cartService)
    {
    }

    public function show(Request $request): View
    {
        return view('cart.index', [
            'cart' => $this->buildSessionCart($request, $this->cartService),
        ]);
    }

    public function update(CartUpdateRequest $request): RedirectResponse
    {
        $map = [];

        foreach ($request->validated('items') as $row) {
            $bookId = (int) $row['book_id'];
            $qty = (int) $row['qty'];

            // qty 0 removes the line.
            if ($qty > 0) {
                $map[$bookId] = $qty;
            }
        }

        $request->session()->put($this->sessionCartKey(), $map);

        return redirect()
            ->route('cart.show')
            ->with('status', __('payment.cart.updated'));
    }
}
