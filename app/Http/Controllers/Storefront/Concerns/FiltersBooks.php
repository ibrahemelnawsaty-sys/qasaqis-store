<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront\Concerns;

use App\Actions\NormalizeArabicSearch;
use App\Models\Book;
use App\Models\Category;
use App\Models\Publisher;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Reusable storefront listing logic (filters + sort + eager loading) shared by
 * the catalog, category and search pages. Keeps controllers thin and guarantees
 * a single, N+1-safe query path. Only real columns from the books schema are
 * referenced (price/old_price, stock_status, age_min/age_max, avg_rating…).
 */
trait FiltersBooks
{
    /**
     * Age buckets (param value => [min, max]) used by the age facet.
     *
     * @var array<string, array{int, int}>
     */
    protected array $ageBuckets = [
        '0-3' => [0, 3],
        '3-6' => [3, 6],
        '6-9' => [6, 9],
        '9-99' => [9, 99],
    ];

    /**
     * Columns needed to render a book card — never load long_description or the
     * search blob into a list (keeps the query and response light).
     *
     * @var array<int, string>
     */
    protected array $cardColumns = [
        'id', 'category_id', 'publisher_id', 'title', 'slug', 'author',
        'price', 'old_price', 'cover_image', 'age_label', 'age_min', 'age_max',
        'stock_status', 'is_featured', 'avg_rating', 'reviews_count', 'published_at',
    ];

    protected function filteredBooks(Request $request, ?Category $category = null): LengthAwarePaginator
    {
        $query = Book::query()
            ->published()
            ->select($this->cardColumns)
            ->with([
                'category:id,name,slug,color_hex,icon',
                'publisher:id,name,slug',
            ]);

        // Category context: a category page pins one category. A book belongs to a
        // category if it is its MAIN category (books.category_id) OR one of its EXTRA
        // categories (book_category pivot) — so multi-category books list under each.
        if ($category !== null) {
            $query->where(function (Builder $q) use ($category): void {
                $q->where('category_id', $category->id)
                    ->orWhereHas('categories', fn (Builder $c) => $c->whereKey($category->id));
            });
        } elseif (filled($request->input('cat'))) {
            $ids = array_values(array_filter(array_map('intval', (array) $request->input('cat'))));
            $query->where(function (Builder $q) use ($ids): void {
                $q->whereIn('category_id', $ids)
                    ->orWhereHas('categories', fn (Builder $c) => $c->whereIn('categories.id', $ids));
            });
        }

        // Free-text search (Arabic-normalized).
        if (filled($request->input('q'))) {
            $this->applySearch($query, (string) $request->input('q'));
        }

        // Publisher facet.
        if (filled($request->input('pub'))) {
            $query->whereIn('publisher_id', (array) $request->input('pub'));
        }

        // Age facet (overlap against age_min/age_max ranges).
        if (filled($request->input('age'))) {
            $this->applyAgeFilter($query, (array) $request->input('age'));
        }

        // Price range (on the effective selling price = price column).
        if ($request->filled('min')) {
            $query->where('price', '>=', (int) $request->input('min'));
        }
        if ($request->filled('max')) {
            $query->where('price', '<=', (int) $request->input('max'));
        }

        // Toggles.
        if ($request->boolean('sale')) {
            $query->whereNotNull('old_price'); // has a struck-through price.
        }
        if ($request->boolean('featured')) {
            $query->where('is_featured', true);
        }
        if ($request->boolean('stock')) {
            $query->where('stock_status', 'in_stock');
        }

        // الافتراضي «ترتيب المتجر» (sort_order اليدوي) لا الأحدث — كي يتحكّم الأدمن
        // بترتيب الظهور في الأقسام وصفحة كل الكتب والبحث من مكان واحد بالسحب.
        $this->applySort($query, (string) $request->input('sort', 'curated'));

        return $query->paginate(12)->withQueryString();
    }

    protected function applyAgeFilter(Builder $query, array $ages): void
    {
        $query->where(function (Builder $outer) use ($ages): void {
            foreach ($ages as $age) {
                if (! isset($this->ageBuckets[$age])) {
                    continue;
                }
                [$lo, $hi] = $this->ageBuckets[$age];
                // A book overlaps the bucket if its range intersects [lo, hi].
                $outer->orWhere(function (Builder $b) use ($lo, $hi): void {
                    $b->where(function (Builder $x) use ($hi): void {
                        $x->whereNull('age_min')->orWhere('age_min', '<=', $hi);
                    })->where(function (Builder $x) use ($lo): void {
                        $x->whereNull('age_max')->orWhere('age_max', '>=', $lo);
                    });
                });
            }
        });
    }

    /**
     * MySQL InnoDB FULLTEXT default minimum indexed token length. Words shorter
     * than this are not in the FULLTEXT index, so a boolean prefix match would
     * miss them; they fall back to a normalized LIKE instead.
     */
    protected int $fullTextMinToken = 3;

    /**
     * Free-text search over the Arabic-normalized `search_index` FULLTEXT column
     * (which already contains the normalized title, author, publisher & category).
     *
     * The main path uses the FULLTEXT index in BOOLEAN MODE: every word is required
     * ("+word") and prefix-matched ("word*"), so "كتاب" finds "كتابي". Tokens too
     * short to be indexed keep a plain LIKE fallback so nothing silently drops.
     * The query string is bound as a parameter by whereFullText(), and boolean
     * operator characters are stripped from each word so user input can't alter
     * the FULLTEXT syntax.
     */
    protected function applySearch(Builder $query, string $term): void
    {
        $words = app(NormalizeArabicSearch::class)->words($term);

        // Strip FULLTEXT boolean operators so the normalized user input can only
        // ever be treated as search terms, never as syntax.
        $words = array_values(array_filter(array_map(
            static fn (string $w): string => trim((string) preg_replace('/[+\-<>()~*"@]+/u', '', $w)),
            $words
        ), static fn (string $w): bool => $w !== ''));

        if ($words === []) {
            return;
        }

        $longWords = array_values(array_filter(
            $words,
            fn (string $w): bool => mb_strlen($w) >= $this->fullTextMinToken
        ));
        $shortWords = array_values(array_filter(
            $words,
            fn (string $w): bool => mb_strlen($w) < $this->fullTextMinToken
        ));

        // FULLTEXT (BOOLEAN MODE) for indexable words: required + prefix match.
        if ($longWords !== []) {
            $boolean = implode(' ', array_map(
                static fn (string $w): string => '+'.$w.'*',
                $longWords
            ));

            $query->whereFullText('search_index', $boolean, ['mode' => 'boolean']);
        }

        // Short tokens aren't indexed by FULLTEXT — keep them matchable via LIKE.
        foreach ($shortWords as $word) {
            $query->where('search_index', 'like', '%'.$word.'%');
        }
    }

    protected function applySort(Builder $query, string $sort): void
    {
        match ($sort) {
            'newest' => $query->orderByDesc('published_at')->orderByDesc('id'),
            'price_asc' => $query->orderByRaw('price IS NULL')->orderBy('price'),
            'price_desc' => $query->orderByDesc('price'),
            'rating' => $query->orderByDesc('avg_rating'),
            'popular' => $query->orderByDesc('views_count'),
            // «ترتيب المتجر» والافتراضي: الترتيب اليدوي (sort_order تصاعدي) الذي يضبطه
            // الأدمن بالسحب في لوحة الكتب، فيظهر نفسه في كل القوائم.
            default => $query->orderBy('sort_order')->orderBy('id'),
        };
    }

    /**
     * The six categories (all kept, even the empty ones) with published counts.
     */
    protected function categoriesWithCounts()
    {
        // العدّ يشمل الكتب التي هذا قسمها الرئيسي (books.category_id) أو أحد أقسامها
        // الإضافية (book_category) — كتاب واحد يُحسب مرة واحدة عبر استعلام فرعي مترابط.
        return Category::query()
            ->active()
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->select('categories.*')
            ->selectSub(
                Book::query()
                    ->where('is_published', true)
                    ->where(function (Builder $q): void {
                        $q->whereColumn('books.category_id', 'categories.id')
                            ->orWhereExists(function ($e): void {
                                $e->selectRaw('1')
                                    ->from('book_category')
                                    ->whereColumn('book_category.book_id', 'books.id')
                                    ->whereColumn('book_category.category_id', 'categories.id');
                            });
                    })
                    ->selectRaw('count(*)'),
                'books_count'
            )
            ->get();
    }

    /**
     * Active publishers that actually have published books, for the facet list.
     */
    protected function publishersWithCounts()
    {
        return Publisher::query()
            ->active()
            ->orderBy('sort_order')
            ->withCount(['activeBooks as books_count'])
            ->having('books_count', '>', 0)
            ->get();
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    protected function ageOptions(): array
    {
        $options = [];
        foreach (array_keys($this->ageBuckets) as $value) {
            $options[] = ['value' => $value, 'label' => __('catalog.ages.'.$value)];
        }

        return $options;
    }
}
