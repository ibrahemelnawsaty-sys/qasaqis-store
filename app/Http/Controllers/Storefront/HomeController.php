<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Storefront\Concerns\FiltersBooks;
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

        return view('home', [
            'slides' => $slides,
            'blocks' => $blocks,
            'categories' => $this->categoriesWithCounts(),
            'featured' => $featured,
            'latest' => $latest,
            'reviews' => $reviews,
        ]);
    }
}
