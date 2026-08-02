<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\NotifiesIndexNow;
use App\Models\Concerns\TracksSlugRedirects;
use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory, NotifiesIndexNow, TracksSlugRedirects;

    protected $fillable = [
        'parent_id',
        'name',
        'slug',
        'description',
        'icon',
        'image_path',
        'color_hex',
        'sort_order',
        'is_active',
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

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function books(): HasMany
    {
        return $this->hasMany(Book::class);
    }

    public function allBooks(): BelongsToMany
    {
        return $this->belongsToMany(Book::class, 'book_category');
    }

    /**
     * كتب القسم مرتّبةً بترتيب الظهور اليدويّ (عمود pivot `position` في
     * category_book_positions). طبقة ترتيب فوق العضويّة (الرئيسي + الإضافي)، تُملأ
     * عبر SyncCategoryBookOrder. السحب في الأدمن يكتب `position` (تصاعديّ = الأول).
     *
     * @return BelongsToMany<Book>
     */
    public function orderedBooks(): BelongsToMany
    {
        return $this->belongsToMany(Book::class, 'category_book_positions', 'category_id', 'book_id')
            ->withPivot('position')
            ->orderByPivot('position');
    }

    /**
     * ينقل كتابًا إلى رتبة محدّدة داخل القسم ثم يعيد ترقيم كل كتبه تسلسليًّا (1..N) بلا
     * تعارض: كتابة الأدمن رقمًا لكتابٍ تُدرِجه في تلك الرتبة وتُزيح الباقي تلقائيًّا
     * (كالسحب لكن بالكتابة). عمدًا دالّة على موديل موجود مسبقًا لا صنف Action جديد:
     * الخادم يستعمل classmap مُحسَّنًا والنشر بـgit pull فقط (بلا composer)، فالصنف
     * الجديد لا يُحمَّل حتى dump-autoload — بينما تعديل موديل قائم يعمل فورًا بالسحب.
     */
    public static function moveBookToPosition(int $categoryId, int $bookId, int $target): void
    {
        $ids = DB::table('category_book_positions')
            ->where('category_id', $categoryId)
            ->orderBy('position')
            ->orderBy('book_id')
            ->pluck('book_id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        // انزع الكتاب من موضعه ثم أدرِجه في الرتبة المطلوبة (محصورة ضمن [1، العدد]).
        $ids = array_values(array_filter($ids, static fn (int $id): bool => $id !== $bookId));
        $target = max(1, min($target, count($ids) + 1));
        array_splice($ids, $target - 1, 0, [$bookId]);

        // أعِد الترقيم تسلسليًّا 1..N في معاملة واحدة (أرقام فريدة بلا فجوات ولا تكرار).
        DB::transaction(function () use ($ids, $categoryId): void {
            foreach ($ids as $index => $id) {
                DB::table('category_book_positions')
                    ->where('category_id', $categoryId)
                    ->where('book_id', $id)
                    ->update(['position' => $index + 1]);
            }
        });
    }

    public function seo(): MorphOne
    {
        return $this->morphOne(SeoMeta::class, 'seoable');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * رابط صورة القسم المرفوعة من اللوحة (قرص public تحت categories/)، أو null إن لم
     * تُرفع. تُعرَض في بطاقة القسم قبل الأيقونة المرسومة/الاحتياطية (x-category-icon).
     */
    public function imageUrl(): ?string
    {
        return filled($this->image_path)
            ? Storage::disk('public')->url($this->image_path)
            : null;
    }

    /** الرابط العام للفهرسة الفورية (IndexNow) — القسم الفعّال فقط، وإلا null. */
    public function indexNowUrl(): ?string
    {
        return $this->is_active && filled($this->slug)
            ? rtrim((string) config('seo.site_url'), '/').'/category/'.$this->slug
            : null;
    }

    /** جذر مسار القسم لتوليد تحويل 301 تلقائي عند تغيّر الـslug. */
    public function seoUrlBase(): string
    {
        return '/category';
    }
}
