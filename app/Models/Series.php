<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * سلسلة كتب: تجمع عدة عناوين (كل عنوان كتاب مستقل). تُنشأ مرة واحدة من لوحة الأدمن
 * وتُسنَد إليها الكتب. الحذف الناعم يتوافق مع بقية كيانات المحتوى (دار النشر/القسم).
 */
class Series extends Model
{
    /** @use HasFactory<\Database\Factories\SeriesFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'series';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'is_active',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * عناوين السلسلة مرتّبة حسب series_position (والفارغ في النهاية) ثم الترتيب العام.
     */
    public function books(): HasMany
    {
        return $this->hasMany(Book::class)
            ->orderByRaw('series_position IS NULL')
            ->orderBy('series_position')
            ->orderBy('sort_order');
    }

    /**
     * بيانات SEO الاختيارية (تجاوز الأدمن). polymorphic على جدول seo_meta المشترك
     * فلا يلزم عمود ولا هجرة. تُملأ من لوحة الأدمن، وتُترك فارغة ليشتقّ الموقع
     * الميتا تلقائيًا من اسم السلسلة ووصفها (App\Support\Seo\SeoDefaults).
     */
    public function seo(): MorphOne
    {
        return $this->morphOne(SeoMeta::class, 'seoable');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
