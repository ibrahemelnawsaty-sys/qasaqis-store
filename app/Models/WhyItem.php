<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * بطاقة في قسم «ليه الأمهات بيحبونا» بالرئيسية. يحرّرها الأدمن.
 * icon = إيموجي؛ لون خلفية البطاقة يتناوب تلقائيًا حسب الترتيب في القالب.
 */
class WhyItem extends Model
{
    /** @use HasFactory<\Database\Factories\WhyItemFactory> */
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
