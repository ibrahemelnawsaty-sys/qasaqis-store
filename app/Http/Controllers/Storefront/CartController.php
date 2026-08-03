<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Storefront\Concerns\InteractsWithSessionCart;
use App\Http\Requests\CartUpdateRequest;
use App\Models\Book;
use App\Services\Cart\CartService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

/**
 * The cart lives in the session as a plain map {book_id: qty}. Prices are always
 * (re)resolved from the DB via CartService — never stored client-side (4.1).
 */
class CartController extends Controller
{
    use InteractsWithSessionCart;

    public function __construct(private readonly CartService $cartService) {}

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

        $this->putSessionCart($request, $map); // يختمها بمالكها (عزل الحسابات)

        return redirect()
            ->route('cart.show')
            ->with('status', __('payment.cart.updated'));
    }

    /**
     * يحفظ سلة العميل المسجَّل على الخادم لمتابعة «السلات المتروكة». يُستدعى خلفيًّا
     * من متجر السلة (localStorage) عند كل تغيير — معرّف الكتاب والكمية فقط، فالأسعار
     * تُحسَب من قاعدة البيانات عند العرض (بند 4.1). الزائر لا يُخزَّن له شيء (السلة
     * جلسة/متصفّح فقط). سلة فارغة تمسح الصفّ. صفٌّ واحد لكل عميل (customer_id فريد).
     *
     * best-effort خالص: منفصل تمامًا عن مسار الشراء، وأيّ فشل هنا لا يمسّ العميل —
     * السلة الحقيقية تبقى في localStorage والدفع يعمل عبر cart.update المستقلّ.
     */
    public function persist(Request $request): Response
    {
        $data = $request->validate([
            'items' => ['present', 'array', 'max:200'],
            'items.*.book_id' => ['required', 'integer', 'min:1'],
            'items.*.qty' => ['required', 'integer', 'min:1', 'max:99'],
        ]);

        $customer = auth('customer')->user();

        if ($customer === null) {
            return response()->noContent(); // الزائر: لا سجلّ خادميّ للسلة
        }

        // تطبيع: دمج تكرار المعرّف وحدّ الكمية، ونُخزّن id + qty فقط (بند 4.1).
        $merged = [];
        foreach ($data['items'] as $row) {
            $id = (int) $row['book_id'];
            $merged[$id] = min(($merged[$id] ?? 0) + (int) $row['qty'], 99);
        }

        $items = [];
        foreach ($merged as $id => $qty) {
            $items[] = ['id' => $id, 'qty' => $qty];
        }

        if ($items === []) {
            DB::table('customer_carts')->where('customer_id', $customer->getKey())->delete();

            return response()->noContent();
        }

        DB::table('customer_carts')->updateOrInsert(
            ['customer_id' => $customer->getKey()],
            ['items' => json_encode($items), 'updated_at' => now()],
        );

        return response()->noContent();
    }

    /**
     * يُعيد سلة العميل المسجَّل المحفوظة على الخادم جاهزةً للعرض (بنفس شكل بطاقة الكتاب:
     * id/title/price/url + qty) — بيانات العرض والأسعار من قاعدة البيانات (بند 4.1). تُستعمَل
     * لتحميل السلة عبر الأجهزة عند الدخول (متجر السلة يدمجها في localStorage). الزائر أو من
     * لا سلة له → قائمة فارغة. تُتجاوَز الكتب غير المتاحة (محذوفة/غير منشورة/بلا سعر) بنفس
     * رؤية CartService::fromItems و BookController::show (بند 0.4/BOOK1)، فلا يظهر عبر
     * الأجهزة كتابٌ يُسقطه الدفع أو يُعطي رابطه 404.
     */
    public function mine(Request $request): JsonResponse
    {
        $customer = auth('customer')->user();

        if ($customer === null) {
            return response()->json(['items' => []]);
        }

        $row = DB::table('customer_carts')->where('customer_id', $customer->getKey())->first();

        if ($row === null) {
            return response()->json(['items' => []]);
        }

        $decoded = json_decode((string) $row->items, true);
        $qtyById = [];
        foreach (is_array($decoded) ? $decoded : [] as $item) {
            $qtyById[(int) ($item['id'] ?? 0)] = max(1, (int) ($item['qty'] ?? 1));
        }
        unset($qtyById[0]);

        if ($qtyById === []) {
            return response()->json(['items' => []]);
        }

        // بيانات العرض (والأسعار) من قاعدة البيانات — بند 4.1، بنفس شكل بطاقة الكتاب،
        // وبنفس رؤية الدفع (منشور وله سعر — بند 0.4/BOOK1) فلا يُحمَّل ما يُسقطه الدفع.
        $items = Book::query()
            ->published()
            ->whereNotNull('price')
            ->whereKey(array_keys($qtyById))
            ->get(['id', 'slug', 'title', 'price'])
            ->map(static fn (Book $book): array => [
                'id' => (int) $book->id,
                'title' => (string) $book->title,
                'price' => $book->price !== null
                    ? number_format((float) $book->price, 0).' '.__('common.currency')
                    : null,
                'url' => route('books.show', $book),
                'qty' => $qtyById[(int) $book->id],
            ])
            ->values()
            ->all();

        return response()->json(['items' => $items]);
    }

    /**
     * يمسح سلة العميل المسجَّل المحفوظة على الخادم (عند إتمام الطلب أو تفريغها).
     * ثابتة لإعادة الاستخدام من CheckoutController بلا تكرار منطق الجدول.
     */
    public static function forgetPersistedCart(int $customerId): void
    {
        DB::table('customer_carts')->where('customer_id', $customerId)->delete();
    }
}
