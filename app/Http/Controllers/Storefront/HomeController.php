<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Storefront\Concerns\FiltersBooks;
use App\Models\Article;
use App\Models\HomepageBlock;
use App\Models\HomepageSection;
use App\Models\Review;
use App\Services\Cms\HomepageSectionResolver;
use Illuminate\Contracts\View\View;

class HomeController extends Controller
{
    use FiltersBooks;

    public function __invoke(): View
    {
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

        // أقسام كتب الرئيسية (كاروسيلات) يديرها الأدمن: يضيف/يحذف/يرتّب، وكل قسم
        // تلقائي بقاعدة مع تعديل يدوي. rescue تُبقي الرئيسية تعمل قبل الهجرة/الزرع،
        // والأقسام الفارغة (بلا كتب) لا تظهر. كل قسم = استعلام واحد خفيف (بلا N+1).
        $resolver = app(HomepageSectionResolver::class);
        $bookSections = rescue(
            fn () => HomepageSection::query()
                ->active()
                ->with('category:id,name,slug')
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()
                ->map(fn (HomepageSection $section): array => [
                    'section' => $section,
                    'books' => $resolver->resolve($section),
                ])
                ->filter(fn (array $row): bool => $row['books']->isNotEmpty())
                ->values(),
            collect(),
            report: false,
        );

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
            'bookSections' => $bookSections,
            'reviews' => $reviews,
            'articles' => $articles,
        ]);
    }
}
