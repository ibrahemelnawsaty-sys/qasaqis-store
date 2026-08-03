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

    /**
     * خَتم مالك سلة الجلسة: معرّف العميل صاحبها (يوازي خَتم localStorage). يُمكّن عزل
     * الحسابات على الجهاز المشترك — سلة عميلٍ لا تُعرَض لعميلٍ آخر يدخل بعده.
     */
    public function sessionCartOwnerKey(): string
    {
        return 'cart_owner';
    }

    protected function buildSessionCart(Request $request, CartService $cartService): Cart
    {
        // عزل الحسابات: إن كانت سلة الجلسة مختومة بعميلٍ آخر (غير الحاليّ) نُسقطها فلا
        // تُعرَض ولا تُشترى باسم من دخل بعده على جهاز مشترك. سلة الزائر (بلا خَتم) تُحمَل
        // عاديًّا وتنتقل معه عند الدخول.
        $this->forgetForeignSessionCart($request);

        /** @var array<int, int> $map */
        $map = (array) $request->session()->get($this->sessionCartKey(), []);

        $items = [];
        foreach ($map as $bookId => $qty) {
            $items[] = ['book_id' => (int) $bookId, 'qty' => (int) $qty];
        }

        return $cartService->fromItems($items);
    }

    /** يكتب سلة الجلسة ويختمها بمالكها الحاليّ (معرّف العميل، أو '' للزائر). */
    protected function putSessionCart(Request $request, array $map): void
    {
        $request->session()->put($this->sessionCartKey(), $map);
        $request->session()->put($this->sessionCartOwnerKey(), $this->currentCartOwner());
    }

    protected function forgetSessionCart(Request $request): void
    {
        $request->session()->forget([$this->sessionCartKey(), $this->sessionCartOwnerKey()]);
    }

    /** يُسقط سلة الجلسة إن كانت مختومة بعميلٍ غير الحاليّ (عزل على الجهاز المشترك). */
    protected function forgetForeignSessionCart(Request $request): void
    {
        $owner = (string) $request->session()->get($this->sessionCartOwnerKey(), '');

        if ($owner !== '' && $owner !== $this->currentCartOwner()) {
            $this->forgetSessionCart($request);
        }
    }

    /** معرّف العميل المسجَّل الحاليّ كنصّ، أو '' للزائر. */
    private function currentCartOwner(): string
    {
        return (string) (auth('customer')->id() ?? '');
    }
}
