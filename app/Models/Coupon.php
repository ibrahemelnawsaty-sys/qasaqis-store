<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Coupon extends Model
{
    use \App\Support\Audit\RecordsAdminActivity;

    protected $fillable = [
        'code',
        'description',
        'type',
        'value',
        'min_order_total',
        'max_discount',
        'starts_at',
        'expires_at',
        'usage_limit',
        'usage_limit_per_user',
        'used_count',
        'applies_to',
        'is_active',
        'free_shipping',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'min_order_total' => 'decimal:2',
            'max_discount' => 'decimal:2',
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
            'is_active' => 'boolean',
            'free_shipping' => 'boolean',
        ];
    }

    public function books(): BelongsToMany
    {
        return $this->belongsToMany(Book::class, 'coupon_book');
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'coupon_category');
    }

    public function usages(): HasMany
    {
        return $this->hasMany(CouponUsage::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
