<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Storefront\Concerns\FiltersBooks;
use App\Models\Book;
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
            'categories' => $this->categoriesWithCounts(),
            'featured' => $featured,
            'latest' => $latest,
            'reviews' => $reviews,
        ]);
    }
}
