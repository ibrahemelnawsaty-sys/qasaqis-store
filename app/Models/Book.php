<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\NotifiesIndexNow;
use App\Models\Concerns\TracksSlugRedirects;
use App\Services\Media\MediaCache;
use App\Support\Audit\RecordsAdminActivity;
use Database\Factories\BookFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Book extends Model
{
    /** @use HasFactory<BookFactory> */
    use HasFactory, NotifiesIndexNow, SoftDeletes, TracksSlugRedirects;

    use RecordsAdminActivity;

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
        'is_new',
        'published_at',
        'sort_order',
        'offer_sort_order',
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
            'is_new' => 'boolean',
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
            ? rtrim((string) config('seo.site_url'), '/').'/books/'.$this->slug
            : null;
    }

    public function seoUrlBase(): string
    {
        return '/books';
    }

    /**
     * رابط الغلاف الموسوم بالعلامة المائية (عبر مسار بالمعرّف، لا يكشف /storage). يعيد
     * الرابط الخارجي كما هو، وnull حين لا غلاف (فيُعرض البديل). لا يُستعمَل في لوحة الإدارة.
     */
    public function coverUrl(): ?string
    {
        if (blank($this->cover_image)) {
            return null;
        }

        if (str_starts_with($this->cover_image, 'http://') || str_starts_with($this->cover_image, 'https://')) {
            return $this->cover_image;
        }

        // أصل ثابت مشحون (public/images) لا يمرّ بالتخزين — يُخدَم مباشرةً بلا علامة.
        if (str_starts_with($this->cover_image, 'images/')) {
            return asset($this->cover_image);
        }

        // المشتقّ الموسوم الثابت إن جهز (يخدمه خادم الويب بلا PHP)، وإلا مسار /media
        // الذي يولّده عند أوّل طلب فيصير الطلب التالي static.
        return MediaCache::publicUrlIfReady($this->cover_image) ?? route('media.book', $this);
    }

    /**
     * رابط مصغّر صغير للغلاف للوحة الإدارة (قائمة الكتب): بايتاته وفكّ ترميزه أخفّ بكثير
     * من نسخة العرض حين تُصفّ ٢٥+ صورة. المصغّر الثابت إن جهز (static بلا PHP)، وإلا مسار
     * media.book-thumb الذي يولّده عند أوّل طلب فيصير static. null/خارجيّ/ثابت كما coverUrl.
     */
    public function adminThumbUrl(): ?string
    {
        if (blank($this->cover_image)) {
            return null;
        }

        if (str_starts_with($this->cover_image, 'http://') || str_starts_with($this->cover_image, 'https://')) {
            return $this->cover_image;
        }

        if (str_starts_with($this->cover_image, 'images/')) {
            return asset($this->cover_image);
        }

        return MediaCache::thumbUrlIfReady($this->cover_image) ?? route('media.book-thumb', $this);
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

    /**
     * «الكتب الجديدة» — منطق هجين: العلم اليدوي `is_new` (إظهار قسريّ) أو أي كتاب
     * نُشر خلال آخر N يوم من تاريخ نشره. تُستعمَل في فلتر/صفحة «وصل حديثًا».
     * N = عدد أيام شارة «جديد» (إعداد قابل للتعديل من الأدمن، افتراضه 30) — يُمرَّر
     * صراحةً ليبقى النطاق نقيًّا/قابلًا للاختبار، ويُشتقّ من الإعداد عند غيابه.
     */
    public function scopeNewArrivals(Builder $query, ?int $days = null): Builder
    {
        $cutoff = now()->subDays($days ?? static::newBadgeDays());
        $now = now();

        return $query->where(function (Builder $q) use ($cutoff, $now): void {
            // فرع التاريخ محصور بين [الآن − N يوم، الآن]: كتاب بتاريخ نشر مستقبليّ ليس
            // «وصل حديثًا» (يبقى الفرع اليدوي is_new مستقلًّا عن التاريخ).
            $q->where('is_new', true)
                ->orWhere(function (Builder $inner) use ($cutoff, $now): void {
                    $inner->where('published_at', '>=', $cutoff)
                        ->where('published_at', '<=', $now);
                });
        });
    }

    /**
     * هل تظهر شارة «جديد» على غلاف هذا الكتاب؟ نفس منطق scopeNewArrivals لكن على
     * مستوى الصف (بلا استعلام) — يحتاج is_new و published_at المحمَّلين في القائمة.
     */
    public function newBadgeVisible(): bool
    {
        if ($this->is_new) {
            return true;
        }

        // فرع التاريخ: نُشر خلال آخر N يوم وليس تاريخًا مستقبليًّا (يطابق scopeNewArrivals).
        return $this->published_at !== null
            && $this->published_at->gte(now()->subDays(static::newBadgeDays()))
            && $this->published_at->lte(now());
    }

    /**
     * عدد الأيام التي يبقى فيها الكتاب «جديدًا» تلقائيًّا بعد نشره. يُقرأ من إعداد
     * المتجر `new_badge_days` (مربوط singleton مرّة لكل طلب في AppServiceProvider)،
     * مع رجوع آمن إلى 30 إن لم يُربَط أو كانت قيمته غير صالحة.
     */
    public static function newBadgeDays(): int
    {
        $days = (int) (app()->bound('shop.new_badge_days') ? app('shop.new_badge_days') : 30);

        return $days > 0 ? $days : 30;
    }

    /**
     * تثبيت الكتاب في رتبة على /books («الأحدث إلا لو ثُبّت»). نموذج «تثبيت»:
     * sort_order = 0 يعني غير مثبَّت (يُرتَّب بالأحدث)، و> 0 يعني مثبَّت في تلك الرتبة.
     * كتابة رقم تُدرِج الكتاب في المجموعة المثبَّتة وتُعيد ترقيمها تسلسليًّا (1..M) بلا
     * تعارض، وتبقى بقيّة الكتب على 0 (بالأحدث). كتابة 0 (أو أقل) تُلغي التثبيت.
     *
     * لا يُرقِّم إلا المثبَّتة (مجموعة صغيرة) فيبقى خفيفًا على الكتالوج الكبير. يؤثّر أيضًا
     * على ترتيب كتب أقسام الرئيسية «مختارات/عروض/قسم» (المثبَّت أولًا). دالّة على موديل
     * قائم (لا صنف Action جديد) لتعمل بـgit pull بلا composer dump-autoload.
     */
    public static function moveToSortPosition(int $bookId, int $target): void
    {
        DB::transaction(function () use ($bookId, $target): void {
            // الكتب المثبَّتة حاليًّا فقط (sort_order > 0) بترتيبها، مع قفلها لتسلسل
            // التعديلات المتزامنة (يمنع تكرار المواضع)، بعد نزع الكتاب المستهدف.
            $pinned = DB::table('books')
                ->whereNull('deleted_at')
                ->where('sort_order', '>', 0)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->lockForUpdate()
                ->pluck('id')
                ->map(static fn ($id): int => (int) $id)
                ->reject(static fn (int $id): bool => $id === $bookId)
                ->values()
                ->all();

            if ($target < 1) {
                // إلغاء التثبيت: يعود الكتاب إلى 0 (الأحدث)، ونعيد ترقيم بقيّة المثبَّتة.
                DB::table('books')->where('id', $bookId)->update(['sort_order' => 0]);
            } else {
                // أدرِج الكتاب في الرتبة المطلوبة ضمن المجموعة المثبَّتة (محصورة).
                array_splice($pinned, min($target, count($pinned) + 1) - 1, 0, [$bookId]);
            }

            foreach ($pinned as $index => $id) {
                DB::table('books')->where('id', $id)->update(['sort_order' => $index + 1]);
            }
        });
    }

    /**
     * ترتيب صفحة العروض (/offers) — مستقلّ عن sort_order. يعيد ترقيم مجموعة الكتب المخصومة
     * (old_price غير فارغ) 1..N بإدراج الكتاب في الرتبة المطلوبة (محصورة ضمن [1، العدد])،
     * فلا تتعارض الأرقام. يُستدعى من عمود «الترتيب» في صفحة أدمن ترتيب العروض. مقفول ذرّيًّا.
     */
    public static function moveToOfferPosition(int $bookId, int $target): void
    {
        DB::transaction(function () use ($bookId, $target): void {
            $ids = DB::table('books')
                ->whereNull('deleted_at')
                ->whereNotNull('old_price')
                ->where('is_published', true) // نفس مجموعة المتجر وصفحة الأدمن (منشور + مخصوم)
                ->orderByRaw('offer_sort_order = 0') // غير المرتَّب (0) في النهاية
                ->orderBy('offer_sort_order')
                ->orderByDesc('published_at')
                ->orderByDesc('id') // كسر تعادل «الأحدث» يطابق المتجر (applyOffersManualOrder)
                ->lockForUpdate()
                ->pluck('id')
                ->map(static fn ($id): int => (int) $id)
                ->reject(static fn (int $id): bool => $id === $bookId)
                ->values()
                ->all();

            $target = max(1, min($target, count($ids) + 1));
            array_splice($ids, $target - 1, 0, [$bookId]);

            foreach ($ids as $index => $id) {
                DB::table('books')->where('id', $id)->update(['offer_sort_order' => $index + 1]);
            }
        });
    }

    /**
     * يعيد ترقيم مجموعة العروض (منشور + مخصوم) 1..N بالترتيب المعياريّ: المرتَّب أوّلًا
     * (offer_sort_order تصاعديًّا) ثم غير المرتَّب (0) بالأحدث — يطابق moveToOfferPosition
     * والمتجر (applyOffersManualOrder). فيُلحَق الجديد، وتُغلَق الفجوات (خصمٌ أُزيل) وتُحلّ أي
     * أرقام مكرّرة. يُستدعى عند فتح صفحة ترتيب العروض. لا يمسّ إلا العروض المنشورة (لا يلمس
     * غير المنشور أو غير المخصوم). idempotent (كتابة فقط عند اختلاف القيمة) وآمن للتزامن (قفل).
     */
    public static function syncOfferPositions(): void
    {
        DB::transaction(function (): void {
            $rows = DB::table('books')
                ->whereNull('deleted_at')
                ->whereNotNull('old_price')
                ->where('is_published', true) // نفس مجموعة المتجر وصفحة الأدمن (منشور + مخصوم)
                ->orderByRaw('offer_sort_order = 0') // غير المرتَّب (0) في النهاية
                ->orderBy('offer_sort_order')
                ->orderByDesc('published_at')
                ->orderByDesc('id') // كسر تعادل «الأحدث» يطابق المتجر (applyOffersManualOrder)
                ->lockForUpdate()
                ->pluck('offer_sort_order', 'id');

            $rank = 0;
            foreach ($rows as $id => $current) {
                $rank++;
                if ((int) $current !== $rank) {
                    DB::table('books')->where('id', $id)->update(['offer_sort_order' => $rank]);
                }
            }
        });
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
