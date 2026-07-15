<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Article;
use Illuminate\Contracts\View\View;

/**
 * Public blog (المدونة) storefront pages.
 *
 * Depends on the shared Article schema (articles + article_book pivot) and the
 * App\Models\Article model owned by the data/model agent: published scope,
 * books() belongsToMany, route key = slug. Only columns from that agreed schema
 * are referenced here (constitution 1.1 / cross-agent contract 10.3–10.4).
 */
class BlogController extends Controller
{
    /**
     * Books-table columns needed to render an x-book-card for the books linked to
     * an article. Qualified with the `books.` prefix because they are selected
     * across the article_book pivot join (avoids any column ambiguity). Never
     * loads content/long text into a list — keeps the query light (constitution 5).
     *
     * @var array<int, string>
     */
    private array $linkedBookColumns = [
        'books.id', 'books.category_id', 'books.publisher_id', 'books.title', 'books.slug',
        'books.author', 'books.price', 'books.old_price', 'books.cover_image', 'books.age_label',
        'books.age_min', 'books.age_max', 'books.stock_status', 'books.is_featured',
        'books.avg_rating', 'books.reviews_count', 'books.published_at',
    ];

    /**
     * Blog index: published articles, featured first, paginated. Eager-loads a
     * light slice of each article's linked books so the "related books" hint on a
     * card never triggers an N+1 query (constitution 2.5).
     */
    public function index(): View
    {
        $articles = Article::query()
            ->published()
            ->with(['books' => fn ($q) => $q->select('books.id', 'books.slug', 'books.title')])
            ->orderByDesc('is_featured')
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->paginate(9);

        return view('blog.index', ['articles' => $articles]);
    }

    /**
     * Single article page. Increments the view counter atomically (race-safe,
     * one UPDATE, no read-modify-write), loads the linked published books for
     * x-book-card, and picks related articles from the same category.
     */
    public function show(Article $article): View
    {
        abort_unless($article->is_published, 404);

        // Atomic, race-safe counter bump that also skips touching updated_at.
        Article::whereKey($article->getKey())->increment('views_count');

        $article->load(['books' => fn ($q) => $q
            ->published()
            ->select($this->linkedBookColumns)
            ->with([
                'category:id,name,slug,color_hex,icon',
                'publisher:id,name,slug',
            ])
            ->orderByDesc('is_featured')
            ->orderByDesc('published_at'),
        ]);

        $related = Article::query()
            ->published()
            ->whereKeyNot($article->getKey())
            ->where('category', $article->category)
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->take(3)
            ->get(['id', 'title', 'slug', 'excerpt', 'cover_image', 'category', 'reading_minutes']);

        return view('blog.show', [
            'article' => $article,
            'related' => $related,
        ]);
    }
}
