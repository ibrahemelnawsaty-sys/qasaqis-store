<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Storefront\Concerns\FiltersBooks;
use App\Http\Requests\CatalogFilterRequest;
use App\Models\Book;
use Illuminate\Contracts\View\View;

class BookController extends Controller
{
    use FiltersBooks;

    /**
     * Browse-all catalogue page with filters, sort and pagination.
     */
    public function index(CatalogFilterRequest $request): View
    {
        $heading = $request->boolean('sale')
            ? __('catalog.offers_heading')
            : __('catalog.all_heading');

        return view('catalog.index', [
            'books' => $this->filteredBooks($request),
            'category' => null,
            'heading' => $heading,
            'searchTerm' => null,
            'categories' => $this->categoriesWithCounts(),
            'publishers' => $this->publishersWithCounts(),
            'ageOptions' => $this->ageOptions(),
        ]);
    }

    /**
     * Single book page (PDP).
     */
    public function show(Book $book): View
    {
        abort_unless($book->is_published, 404);

        $book->load([
            'category:id,name,slug,color_hex,icon',
            'publisher:id,name,slug,website',
            'images',
            'seo',
        ]);

        $reviews = $book->reviews()
            ->where('status', 'published')
            ->whereNull('parent_id')
            ->latest()
            ->take(6)
            ->get();

        // Similar books from the same category (never the book itself).
        $related = Book::query()
            ->published()
            ->where('category_id', $book->category_id)
            ->whereKeyNot($book->id)
            ->select($this->cardColumns)
            ->with([
                'category:id,name,slug,color_hex,icon',
                'publisher:id,name,slug',
            ])
            ->orderByDesc('is_featured')
            ->orderByDesc('published_at')
            ->take(4)
            ->get();

        return view('books.show', [
            'book' => $book,
            'reviews' => $reviews,
            'related' => $related,
        ]);
    }
}
