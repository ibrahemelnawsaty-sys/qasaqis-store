<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Storefront\Concerns\FiltersBooks;
use App\Http\Requests\SearchRequest;
use App\Models\Book;
use App\Services\SearchSuggestService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;

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
     * يُخزَّن في المتصفح 5 دقائق عبر Cache-Control.
     */
    public function indexJson(): JsonResponse
    {
        $books = Book::query()
            ->where('is_published', true)
            ->with(['publisher:id,name'])
            ->orderBy('sort_order')
            ->orderByDesc('id')
            ->get(['id', 'title', 'slug', 'author', 'publisher_id', 'cover_image', 'price']);

        // دار النشر الافتراضية (اسم المتجر) لا تُدرَج في بيانات البحث حتى لا تُطابق
        // كل الكتب المرتبطة بها عند كتابة أي حرف من اسمها.
        $defaultPublishers = ['قصص أطفال', 'قصاقيص أطفال'];

        $currency = __('common.currency');

        return response()->json([
            'books' => $books->map(function (Book $b) use ($defaultPublishers, $currency) {
                $pub = $b->publisher?->name;

                // رابط الغلاف (خارجي كما هو، أو من التخزين العام) — null لو لا غلاف.
                $cover = $b->cover_image;
                $img = filled($cover)
                    ? (\Illuminate\Support\Str::startsWith($cover, ['http://', 'https://'])
                        ? $cover
                        : asset('storage/'.ltrim($cover, '/')))
                    : null;

                return [
                    't' => $b->title,
                    'a' => $b->author,
                    'p' => in_array($pub, $defaultPublishers, true) ? null : $pub,
                    'u' => route('books.show', $b),
                    'img' => $img,
                    // السعر منسّق للعرض؛ null لو لا سعر (لا نختلق قيمة — بند 0.4).
                    'pr' => $b->price !== null
                        ? number_format((float) $b->price, 0).' '.$currency
                        : null,
                ];
            })->values(),
        ])->header('Cache-Control', 'public, max-age=300');
    }
}
