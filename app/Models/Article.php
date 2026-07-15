<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * مقال المدونة. المحتوى (content) HTML قادم من محرر نصي؛ يُعرض في الواجهة
 * عبر مطهّر HTML موثوق (App\Support\HtmlSanitizer) لا عبر {!! !!} مباشرة
 * (بند 4.2 / ممنوع 8). التوجيه بالـ slug (getRouteKeyName).
 */
class Article extends Model
{
    /** @use HasFactory<\Database\Factories\ArticleFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'title',
        'slug',
        'excerpt',
        'content',
        'cover_image',
        'author_name',
        'category',
        'reading_minutes',
        'seo_title',
        'seo_description',
        'is_published',
        'is_featured',
        'published_at',
        'views_count',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'is_featured' => 'boolean',
            'published_at' => 'datetime',
            'views_count' => 'integer',
            'reading_minutes' => 'integer',
        ];
    }

    // ----- Relationships -------------------------------------------------

    /**
     * الكتب ذات الصلة (روابط داخلية + ترويج) عبر جدول article_book.
     */
    public function books(): BelongsToMany
    {
        return $this->belongsToMany(Book::class, 'article_book');
    }

    // ----- Scopes --------------------------------------------------------

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    // ----- Routing -------------------------------------------------------

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
