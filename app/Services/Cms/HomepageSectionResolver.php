<?php

declare(strict_types=1);

namespace App\Services\Cms;

use App\Models\Book;
use App\Models\HomepageSection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * يحوّل قسم رئيسية إلى كتب مرتّبة جاهزة للعرض، N+1-safe (نفس أعمدة البطاقة والتحميل
 * المسبق في FiltersBooks/HomeController). كل قاعدة (source_type) تعيد استعمال منطق
 * الترتيب الموجود أصلًا. الترتيب اليدوي تصاعدي (sort_order ASC) ليطابق السحب في الأدمن.
 *
 * هجين: القسم اليدوي = كتبه المثبّتة فقط؛ القسم التلقائي = الكتب المثبّتة (إن وُجدت)
 * أولًا ثم تُكمِل القاعدة بحد item_limit — فيصير «تلقائي مع تعديل يدوي».
 */
final class HomepageSectionResolver
{
    /**
     * @var array<int, string>
     */
    private array $cardColumns = [
        'id', 'category_id', 'publisher_id', 'title', 'slug', 'author',
        'price', 'old_price', 'cover_image', 'age_label', 'age_min', 'age_max',
        'stock_status', 'is_featured', 'avg_rating', 'reviews_count', 'published_at',
    ];

    /**
     * @return Collection<int, Book>
     */
    public function resolve(HomepageSection $section): Collection
    {
        $limit = max(1, (int) $section->item_limit);

        // الكتب المثبّتة/المختارة (المنشورة فقط)، بترتيب pivot.
        $pinned = $section->books()->published()->with($this->cardWith())->limit($limit)->get();

        if ($section->source_type === 'manual') {
            return $pinned;
        }

        $remaining = $limit - $pinned->count();
        if ($remaining <= 0) {
            return $pinned;
        }

        $ruleBooks = $this->ruleQuery($section)
            ->whereKeyNot($pinned->modelKeys())
            ->limit($remaining)
            ->get();

        return $pinned->concat($ruleBooks);
    }

    private function ruleQuery(HomepageSection $section): Builder
    {
        $base = Book::query()->published()->select($this->cardColumns)->with($this->cardWith());

        return match ($section->source_type) {
            'featured' => $base->featured()->orderBy('sort_order')->orderByDesc('published_at'),
            'bestsellers' => $this->bestsellersQuery($base),
            'popular' => $base->orderByDesc('views_count')->orderByDesc('published_at'),
            'on_sale' => $base->whereNotNull('old_price')->orderBy('sort_order')->orderByDesc('published_at'),
            'category' => $this->categoryQuery($base, $section),
            // latest والافتراضي.
            default => $base->orderByDesc('published_at')->orderByDesc('id'),
        };
    }

    /**
     * الأكثر مبيعًا: المُعلَّمة يدويًا؛ فإن لم تُعلَّم أي كتب، رجوع للأكثر مشاهدة كي لا
     * يكون القسم فارغًا (نفس سلوك HomeController الأصلي).
     */
    private function bestsellersQuery(Builder $base): Builder
    {
        $hasFlagged = Book::query()->published()->where('is_bestseller', true)->exists();

        return $hasFlagged
            ? $base->where('is_bestseller', true)->orderByDesc('views_count')->orderByDesc('id')
            : $base->orderByDesc('views_count')->orderByDesc('published_at')->orderByDesc('id');
    }

    private function categoryQuery(Builder $base, HomepageSection $section): Builder
    {
        $categoryId = $section->category_id;

        if ($categoryId === null) {
            return $base->whereRaw('1 = 0'); // لا قسم مختار → فارغ.
        }

        // كتاب ينتمي للقسم إن كان قسمه الرئيسي أو أحد أقسامه الإضافية (نفس FiltersBooks).
        return $base->where(function (Builder $q) use ($categoryId): void {
            $q->where('category_id', $categoryId)
                ->orWhereHas('categories', fn (Builder $c) => $c->whereKey($categoryId));
        })->orderBy('sort_order')->orderByDesc('published_at');
    }

    /**
     * @return array<int, string>
     */
    private function cardWith(): array
    {
        return [
            'category:id,name,slug,color_hex,icon',
            'publisher:id,name,slug',
        ];
    }
}
