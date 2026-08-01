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
