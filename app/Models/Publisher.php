<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Publisher extends Model
{
    /** @use HasFactory<\Database\Factories\PublisherFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'name_normalized',
        'description',
        'website',
        'logo_path',
        'is_active',
        'sort_order',
        'cost_discount_percent',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'cost_discount_percent' => 'decimal:2',
        ];
    }

    public function books(): HasMany
    {
        return $this->hasMany(Book::class);
    }

    public function activeBooks(): HasMany
    {
        return $this->hasMany(Book::class)->where('is_published', true);
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
     * Mutator: keep the Arabic-normalized name in sync for publisher search.
     */
    public function setNameAttribute(?string $value): void
    {
        $this->attributes['name'] = $value;
        $this->attributes['name_normalized'] = Book::normalizeArabic((string) $value);
    }
}
