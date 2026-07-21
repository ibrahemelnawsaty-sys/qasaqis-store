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
        // /offers هو نفس صفحة التصفّح بفلتر الخصم مفروضًا. نفرضه هنا لا عبر
        // ->defaults() لأن الأخير يضبط معامل مسار لا يقرأه $request->boolean().
        //
        // يجب الدمج في الطلبين معًا: الحاوية تُنشئ الـ FormRequest عبر
        // FormRequest::createFrom() الذي يبني InputBag جديدة، فيبقى الطلب المربوط في
        // الحاوية منفصلًا. الاستعلام يقرأ $request (هنا)، بينما القوالب — خانة
        // «العروض فقط» في partials/filters.blade.php — تقرأ request() المساعدة.
        // لولا الثانية لظهرت الخانة غير مؤشَّرة وسقط الفلتر عند أول إرسال للنموذج.
        if ($request->routeIs('books.offers')) {
            $request->merge(['sale' => 1]);
            request()->merge(['sale' => 1]);
        }

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

        // عدّاد مشاهدات: مرّة واحدة لكل زائر لكل كتاب في الجلسة (لا يتضخّم بالتحديث/
        // إعادة التحميل). incrementQuietly لا يلمس updated_at ولا يُطلق أحداثًا. يُغذّي
        // تحليلات «الاهتمام دون بيع» في لوحة العمليات (العمود كان موجودًا وغير مأهول).
        $seen = (array) session()->get('viewed_book_ids', []);
        if (! in_array($book->id, $seen, true)) {
            $book->incrementQuietly('views_count');
            session()->put('viewed_book_ids', array_slice([...$seen, $book->id], -300));
        }

        $book->load([
            'category:id,name,slug,color_hex,icon',
            'categories:id,name,slug',
            'publisher:id,name,slug,website',
            'series:id,name,slug,is_active',
            'images',
            'seo',
        ]);

        // عناوين نفس السلسلة (بما فيها الحالي) للمبدّل — فقط لسلسلة مُفعّلة وغير محذوفة.
        $seriesBooks = collect();
        if ($book->series && $book->series->is_active) {
            $seriesBooks = Book::query()
                ->published()
                ->where('series_id', $book->series_id)
                ->select(['id', 'title', 'slug', 'cover_image', 'price', 'series_position'])
                ->orderByRaw('series_position IS NULL')
                ->orderBy('series_position')
                ->orderBy('id')
                ->get();
        }

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
            // ترتيب المتجر اليدوي (sort_order) — نفس ترتيب الظهور في باقي القوائم.
            ->orderBy('sort_order')
            ->orderBy('id')
            ->take(4)
            ->get();

        return view('books.show', [
            'book' => $book,
            'reviews' => $reviews,
            'related' => $related,
            'seriesBooks' => $seriesBooks,
        ]);
    }
}
