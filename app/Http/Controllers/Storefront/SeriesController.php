<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Storefront\Concerns\FiltersBooks;
use App\Models\Book;
use App\Models\Series;
use Illuminate\Contracts\View\View;

class SeriesController extends Controller
{
    use FiltersBooks;

    /**
     * صفحة السلسلة: كل عناوينها المنشورة بترتيب series_position ثم الأحدث. تعيد استخدام
     * قالب الكتالوج نفسه (بلا فلاتر القسم لأن السلسلة تعبر الأقسام).
     */
    public function show(Series $series): View
    {
        abort_unless($series->is_active, 404);

        $books = Book::query()
            ->published()
            ->where('series_id', $series->id)
            ->select($this->cardColumns)
            ->with([
                'category:id,name,slug,color_hex,icon',
                'publisher:id,name,slug',
            ])
            ->orderByRaw('series_position IS NULL')
            ->orderBy('series_position')
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->paginate(12)
            ->withQueryString();

        return view('catalog.index', [
            'books' => $books,
            'category' => null,
            'series' => $series,
            'heading' => $series->name,
            'searchTerm' => null,
            'categories' => $this->categoriesWithCounts(),
            'publishers' => $this->publishersWithCounts(),
            'ageOptions' => $this->ageOptions(),
        ]);
    }
}
