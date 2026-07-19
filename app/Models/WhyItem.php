<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * بطاقة في قسم «ليه الأمهات بيحبونا» بالرئيسية. يحرّرها الأدمن.
 * icon = مفتاح من مكتبة resources/views/components/why-icon.blade.php
 * (قيمة قديمة بإيموجي تظلّ تعمل: المكوّن يطبعها نصًّا إن لم تطابق مفتاحًا).
 * لون البطاقة يتناوب تلقائيًا حسب الترتيب عبر nth-child في app.css.
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
