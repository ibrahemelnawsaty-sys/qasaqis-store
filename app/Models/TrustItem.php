<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * عنصر في شريط المزايا/الثقة بالرئيسية (شحن، تغليف، جودة، دعم…). يحرّره الأدمن.
 * icon = اسم أيقونة من مكوّن ui-icon (globe/gift/badge-check/chat/truck/…).
 */
class TrustItem extends Model
{
    /** @use HasFactory<\Database\Factories\TrustItemFactory> */
    use HasFactory;

    protected $fillable = [
        'icon',
        'title',
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

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
