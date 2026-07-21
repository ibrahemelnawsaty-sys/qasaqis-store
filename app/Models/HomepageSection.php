<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * قسم كتب في الرئيسية (كاروسيل). تلقائي بقاعدة (source_type) مع تعديل يدوي عبر
 * علاقة books() (المثبّتة/المختارة). يحلّه HomepageSectionResolver إلى كتب مرتّبة.
 *
 * @property string $source_type
 * @property int $item_limit
 */
class HomepageSection extends Model
{
    protected $fillable = [
        'eyebrow',
        'title',
        'subtitle',
        'source_type',
        'category_id',
        'item_limit',
        'cta_url',
        'cta_label',
        'background_pattern',
        'is_active',
        'sort_order',
    ];

    /**
     * أنواع المصدر + تسمياتها العربية (تُستعمل في الفورم والشارة).
     *
     * @var array<string, string>
     */
    public const SOURCE_TYPES = [
        'latest' => 'وصل حديثًا (الأحدث تلقائيًا)',
        'bestsellers' => 'الأكثر مبيعًا (تلقائيًا)',
        'featured' => 'مختارات (المميّزة تلقائيًا)',
        'popular' => 'الأكثر مشاهدة (تلقائيًا)',
        'on_sale' => 'عروض وتخفيضات (تلقائيًا)',
        'category' => 'قسم محدّد (تلقائيًا)',
        'manual' => 'يدوي (تختار الكتب بنفسك)',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'item_limit' => 'integer',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @return BelongsTo<Category, HomepageSection>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * الكتب المثبّتة/المختارة يدويًا، مرتّبة بعمود pivot (السحب في الأدمن يكتبه).
     *
     * @return BelongsToMany<Book>
     */
    public function books(): BelongsToMany
    {
        return $this->belongsToMany(Book::class, 'homepage_section_book')
            ->withPivot('position')
            ->orderByPivot('position');
    }
}
