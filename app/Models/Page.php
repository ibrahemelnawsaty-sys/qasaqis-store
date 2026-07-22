<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\NotifiesIndexNow;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Page extends Model
{
    use NotifiesIndexNow;
    use SoftDeletes;

    protected $fillable = [
        'title',
        'slug',
        'content',
        'template',
        'background_pattern',
        'is_published',
        'published_at',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    public function seo(): MorphOne
    {
        return $this->morphOne(SeoMeta::class, 'seoable');
    }

    /**
     * فئة نقش الخلفية لهذه الصفحة: اختيار الصفحة نفسها إن وُجد، وإلا النقش
     * المضبوط لـ«الصفحات الثابتة» في شاشة نقوش الخلفية. قيمة فارغة = لا نقش.
     */
    public function patternClass(): string
    {
        if (filled($this->background_pattern)) {
            return \App\Enums\BackgroundPattern::fromValue($this->background_pattern)->cssClass() ?? '';
        }

        return app(\App\Services\Cms\BackgroundPatternService::class)
            ->cssClass(\App\Enums\PatternSurface::PageStatic);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    public function indexNowUrl(): ?string
    {
        return $this->is_published && filled($this->slug)
            ? rtrim((string) config('seo.site_url'), '/') . '/pages/' . $this->slug
            : null;
    }
}
