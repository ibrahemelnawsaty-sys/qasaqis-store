<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Storefront\Concerns\FiltersBooks;
use App\Http\Requests\SearchRequest;
use App\Models\Book;
use App\Services\Pricing\PricingService;
use App\Services\SearchSuggestService;
use App\Support\Pricing\CurrencyContext;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class SearchController extends Controller
{
    use FiltersBooks;

    public function index(SearchRequest $request): View
    {
        $term = trim((string) $request->input('q', ''));
        $books = $this->filteredBooks($request);

        // On empty results show a few real books as a fallback (never fabricated).
        $fallback = collect();
        if ($books->isEmpty()) {
            $fallback = Book::query()
                ->published()
                ->select($this->cardColumns)
                ->with([
                    'category:id,name,slug,color_hex,icon',
                    'publisher:id,name,slug',
                ])
                ->orderByDesc('is_featured')
                ->orderByDesc('published_at')
                ->take(4)
                ->get();
        }

        return view('catalog.index', [
            'books' => $books,
            'category' => null,
            'heading' => $term !== ''
                ? __('search.results_for', ['term' => $term])
                : __('search.title'),
            'searchTerm' => $term,
            'fallbackBooks' => $fallback,
            'categories' => $this->categoriesWithCounts(),
            'publishers' => $this->publishersWithCounts(),
            'ageOptions' => $this->ageOptions(),
        ]);
    }

    /**
     * Lightweight instant-suggest JSON (books + publishers + categories).
     * Rate-limited at the route; logic lives in the service (thin controller).
     */
    public function suggest(SearchRequest $request, SearchSuggestService $service): JsonResponse
    {
        return response()->json(
            $service->suggest((string) $request->input('q', ''))
        );
    }

    /**
     * فهرس بحث خفيف: كل الكتب المنشورة مرة واحدة، ليُفلترها المتصفح لحظيًا
     * (بحث فوري بلا طلب لكل ضغطة — مناسب للشبكة الضعيفة، وكتالوج صغير 23 كتابًا).
     * يُخزَّن في المتصفح 5 دقائق عبر Cache-Control. السعر بعملة الزائر (متعدّد العملات)
     * فالكاش private كي لا تُشارَك عملةُ زائرٍ مع آخرَ عبر أيّ كاشٍ وسيط/CDN.
     */
    public function indexJson(PricingService $pricing, CurrencyContext $currencyContext): JsonResponse
    {
        // مخزّن خادميًّا لكلّ عملة (السعر يختلف بالعملة): بناءُ الفهرس الكامل (استعلام
        // الكتالوج + فحص الأغلفة + حلّ الأسعار) يقع مرّةً كلّ 300ث لكلّ عملة بدل كلّ طلب،
        // فلا يُثقِل الكتالوجُ الكبير الطلبَ ولا يعيد كلُّ زائرٍ بناءَه. المفتاح بالعملة
        // لأنّ المحتوى يختلف بها؛ والاستجابة private كي لا يشاركها كاشٌ وسيط بين العملات.
        $items = Cache::remember(
            'shop.search_index.'.$currencyContext->get()->code,
            300,
            static function () use ($pricing): array {
                $books = Book::query()
                    ->where('is_published', true)
                    ->with(['publisher:id,name'])
                    // نموذج التثبيت: المثبَّت (sort_order>0) أولًا، ثم غير المثبَّت (0) بالأحدث —
                    // يطابق ترتيب المتجر فلا تغرق الكتب المثبَّتة أسفل فهرس الاقتراح الفوري.
                    ->orderByRaw('sort_order = 0')
                    ->orderBy('sort_order')
                    ->orderByDesc('id')
                    ->get(['id', 'title', 'slug', 'author', 'publisher_id', 'cover_image', 'price', 'old_price', 'stock_status']);

                // دار النشر الافتراضية (اسم المتجر) لا تُدرَج في بيانات البحث حتى لا تُطابق
                // كل الكتب المرتبطة بها عند كتابة أي حرف من اسمها.
                $defaultPublishers = ['قصص أطفال', 'قصاقيص أطفال'];

                return $books->map(function (Book $b) use ($defaultPublishers, $pricing): array {
                    $pub = $b->publisher?->name;
                    // السعر بعملة الزائر النشطة (خادميًّا). null ⇒ لا سعر (قاعدة الصدق).
                    $price = $pricing->resolve($b);

                    return [
                        // المعرّف مطلوب للإضافة المباشرة للسلة من نتائج البحث (cart.add يحتاج id).
                        'id' => $b->id,
                        't' => $b->title,
                        'a' => $b->author,
                        'p' => in_array($pub, $defaultPublishers, true) ? null : $pub,
                        'u' => route('books.show', $b),
                        // متوفّر للشراء المباشر (سعر + مخزون) — يطابق $canBuy في البطاقة/الصفحة،
                        // فلا يظهر زرّ الإضافة لِما هو نافد فيقع العميل في طريق مسدود عند الدفع.
                        'in' => $b->stock_status === 'in_stock',
                        // الغلاف موسومًا بالعلامة المائية عبر coverUrl (يطابق البطاقات والسلة —
                        // حماية الصور) بدل asset('storage') المكشوف؛ null لو لا غلاف.
                        'img' => $b->coverUrl(),
                        // السعر منسّق بعملة الزائر؛ null لو لا سعر (لا نختلق قيمة — بند 0.4).
                        'pr' => $price?->formattedAmount(),
                    ];
                })->values()->all();
            },
        );

        return response()->json(['books' => $items])
            ->header('Cache-Control', 'private, max-age=300');
    }
}
