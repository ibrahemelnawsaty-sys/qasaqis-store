<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\BookFactory;
use Illuminate\Database\Eloquent\Builder;
use App\Models\Concerns\NotifiesIndexNow;
use App\Models\Concerns\TracksSlugRedirects;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Book extends Model
{
    /** @use HasFactory<BookFactory> */
    use HasFactory, NotifiesIndexNow, SoftDeletes, TracksSlugRedirects;
    use \App\Support\Audit\RecordsAdminActivity;

    protected $fillable = [
        'category_id',
        'publisher_id',
        'series_id',
        'series_position',
        'title',
        'slug',
        'focus_keyword',
        'sku',
        'author',
        'illustrator',
        'short_description',
        'long_description',
        'price',
        'old_price',
        'cost_price',
        'stock_quantity',
        'stock_status',
        'manage_stock',
        'age_min',
        'age_max',
        'age_label',
        'pages_count',
        'isbn',
        'weight_grams',
        'learning_outcomes',
        'cover_image',
        'is_published',
        'is_featured',
        'is_bestseller',
        'published_at',
        'sort_order',
        'views_count',
        'avg_rating',
        'reviews_count',
        'title_normalized',
        'search_index',
    ];

    /**
     * تكلفة الشراء سرّية (الدستور 0.7): تُستبعد من أي تسلسل تلقائي (toArray/toJson)
     * حتى لو حُمِّل الصف كاملًا في الواجهة، فلا تتسرّب في JSON-LD أو dataLayer أو API.
     * دفاع في العمق يكمّل حماية الحقل في لوحة الأدمن بصلاحية products.cost.view.
     *
     * @var array<int, string>
     */
    protected $hidden = ['cost_price'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            // Money is DECIMAL — cast keeps it a string-safe 2-decimal value, never float maths.
            'price' => 'decimal:2',
            'old_price' => 'decimal:2',
            'cost_price' => 'decimal:2',
            'avg_rating' => 'decimal:1',
            'learning_outcomes' => 'array',
            'manage_stock' => 'boolean',
            'is_published' => 'boolean',
            'is_featured' => 'boolean',
            'is_bestseller' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    // ----- Relationships -------------------------------------------------

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function publisher(): BelongsTo
    {
        // Books with no visible publisher fall back to the default label.
        return $this->belongsTo(Publisher::class)->withDefault(['name' => 'قصاقيص أطفال']);
    }

    public function series(): BelongsTo
    {
        return $this->belongsTo(Series::class);
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'book_category');
    }

    /**
     * أقسام كتب الرئيسية التي ثُبّت فيها هذا الكتاب (عكس HomepageSection::books()).
     * لازمة لعمل زر «إضافة كتاب» (AttachAction) في مدير الكتب المثبّتة.
     *
     * @return BelongsToMany<HomepageSection>
     */
    public function homepageSections(): BelongsToMany
    {
        return $this->belongsToMany(HomepageSection::class, 'homepage_section_book')
            ->withPivot('position');
    }

    public function indexNowUrl(): ?string
    {
        return $this->is_published && filled($this->slug)
            ? rtrim((string) config('seo.site_url'), '/') . '/books/' . $this->slug
            : null;
    }

    public function seoUrlBase(): string
    {
        return '/books';
    }

    public function images(): HasMany
    {
        return $this->hasMany(BookImage::class)->orderBy('sort_order');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function coupons(): BelongsToMany
    {
        return $this->belongsToMany(Coupon::class, 'coupon_book');
    }

    public function seo(): MorphOne
    {
        return $this->morphOne(SeoMeta::class, 'seoable');
    }

    // ----- Scopes --------------------------------------------------------

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('is_featured', true);
    }

    public function scopeInStock(Builder $query): Builder
    {
        return $query->where('stock_status', 'in_stock');
    }

    // ----- Search normalization (Arabic) ---------------------------------

    /**
     * Mutator: keep the normalized title in sync for prefix autocomplete.
     */
    public function setTitleAttribute(?string $value): void
    {
        $this->attributes['title'] = $value;
        $this->attributes['title_normalized'] = static::normalizeArabic((string) $value);
    }

    /**
     * Rebuild the unified normalized FULLTEXT blob from the book's fields and
     * loaded relations. Intended to be called from a BookObserver on save.
     *
     * The definite-article-stripped title is appended too: the query side drops
     * «ال» (NormalizeArabicSearch::forSearch), so a FULLTEXT prefix search for
     * "كتاب" must also be able to match a title stored as "الكتاب". Storing both
     * variants keeps indexing and querying in agreement (see docs/06 §1.1–1.3).
     */
    public function refreshSearchIndex(): void
    {
        $parts = [
            $this->title,
            $this->author,
            $this->relationLoaded('publisher') && $this->publisher ? $this->publisher->name : null,
            $this->relationLoaded('category') && $this->category ? $this->category->name : null,
        ];

        $normalized = array_map(
            static fn ($p) => static::normalizeArabic((string) $p),
            array_filter($parts, static fn ($p) => filled($p))
        );

        $normalized[] = static::stripDefiniteArticle(static::normalizeArabic((string) $this->title));

        // De-duplicate and drop empties to keep the blob compact.
        $normalized = array_values(array_unique(array_filter(
            $normalized,
            static fn (string $p): bool => $p !== ''
        )));

        $this->attributes['search_index'] = trim(implode(' ', $normalized));
    }

    /**
     * Drop the leading definite article «ال» from every word of an ALREADY
     * normalized string. Kept in one place so index-building and query-building
     * strip it identically.
     */
    public static function stripDefiniteArticle(string $normalized): string
    {
        if ($normalized === '') {
            return '';
        }

        return trim((string) preg_replace('/(^|\s)ال(\S)/u', '$1$2', $normalized));
    }

    /**
     * Normalize Arabic text: strip diacritics/tatweel and unify hamza/alef/
     * ta-marbuta/alef-maqsura so search matches regardless of spelling. Done in
     * PHP (not MySQL) per the DB conventions. Latin text is lower-cased.
     */
    public static function normalizeArabic(?string $text): string
    {
        if ($text === null || $text === '') {
            return '';
        }

        // Remove harakat (tashkeel), superscript alef and Quranic marks.
        $text = preg_replace('/[\x{0610}-\x{061A}\x{064B}-\x{065F}\x{0670}\x{06D6}-\x{06ED}]/u', '', $text);
        // Remove tatweel (kashida).
        $text = preg_replace('/\x{0640}/u', '', $text);

        $map = [
            'أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ٱ' => 'ا',
            'ة' => 'ه',
            'ى' => 'ي',
            'ؤ' => 'و',
            'ئ' => 'ي',
        ];
        $text = strtr($text, $map);

        // Collapse whitespace and lowercase latin characters.
        $text = preg_replace('/\s+/u', ' ', $text);

        return trim(mb_strtolower($text));
    }
}
