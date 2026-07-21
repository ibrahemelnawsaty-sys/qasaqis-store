<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Storefront\Concerns\FiltersBooks;
use App\Models\Article;
use App\Models\Book;
use App\Models\HomepageBlock;
use App\Models\Review;
use Illuminate\Contracts\View\View;

class HomeController extends Controller
{
    use FiltersBooks;

    public function __invoke(): View
    {
        $cardWith = [
            'category:id,name,slug,color_hex,icon',
            'publisher:id,name,slug',
        ];

        // Editable CMS blocks for the homepage (constitution 0.8). One query,
        // partitioned in memory (no N+1): banners/sliders feed the top carousel,
        // the rest render as ordered editable content sections.
        $homepageBlocks = HomepageBlock::query()
            ->active()
            ->where('area', 'homepage')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'key', 'type', 'title', 'content', 'sort_order']);

        $slides = $homepageBlocks->whereIn('type', ['slider', 'banner'])->values();
        $blocks = $homepageBlocks->whereIn('type', ['text', 'html', 'image', 'cta'])->values();

        $featured = Book::query()
            ->published()
            ->featured()
            ->select($this->cardColumns)
            ->with($cardWith)
            ->orderByDesc('sort_order')
            ->orderByDesc('published_at')
            ->take(8)
            ->get();

        // «الأكثر مبيعًا» — books the admin flagged via is_bestseller. If none are
        // flagged yet, fall back to the most-viewed then newest published books so
        // the homepage section is never empty for the user (constitution 1.6).
        $bestsellers = Book::query()
            ->published()
            ->where('is_bestseller', true)
            ->select($this->cardColumns)
            ->with($cardWith)
            ->orderByDesc('sort_order')
            ->orderByDesc('views_count')
            ->take(8)
            ->get();

        if ($bestsellers->isEmpty()) {
            $bestsellers = Book::query()
                ->published()
                ->select($this->cardColumns)
                ->with($cardWith)
                ->orderByDesc('views_count')
                ->orderByDesc('published_at')
                ->orderByDesc('id')
                ->take(8)
                ->get();
        }

        $latest = Book::query()
            ->published()
            ->select($this->cardColumns)
            ->with($cardWith)
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->take(8)
            ->get();

        // Real testimonials only — never fabricate reviews (constitution 0.4 / 11.4).
        $reviews = Review::query()
            ->published()
            ->whereNull('parent_id')
            ->with('book:id,title,slug')
            ->latest()
            ->take(3)
            ->get();

        // أحدث 3 مقالات منشورة للقسم «أحدث المقالات» بالرئيسية. لا علاقات مُحمّلة
        // (بطاقة المقال لا تعرض كتبًا) => بلا N+1. rescue تُبقي الرئيسية تعمل حتى
        // قبل تشغيل هجرة المدونة (مجموعة فارغة => القسم لا يظهر أصلًا).
        $articles = rescue(
            fn () => Article::query()
                ->published()
                ->orderByDesc('published_at')
                ->orderByDesc('id')
                ->take(3)
                ->get(['id', 'title', 'slug', 'excerpt', 'cover_image', 'category', 'reading_minutes']),
            collect(),
            report: false,
        );

        return view('home', [
            'slides' => $slides,
            'blocks' => $blocks,
            'categories' => $this->categoriesWithCounts(),
            'featured' => $featured,
            'bestsellers' => $bestsellers,
            'latest' => $latest,
            'reviews' => $reviews,
            'articles' => $articles,
        ]);
    }
}
