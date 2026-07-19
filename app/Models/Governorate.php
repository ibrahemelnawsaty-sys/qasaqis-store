<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * محافظة مصرية بسعر شحن ومدة توصيل يضبطهما المالك من اللوحة (M8).
 *
 * `name_ar` هو مفتاح الربط مع `orders.governorate` (نصّ لا FK — اتساقًا مع لقطة
 * `shipping_zone_code` القائمة، وكي لا يتغيّر اسم محافظة على طلب قديم بأثر رجعي).
 *
 * `shipping_cost` و`delivery_days_*`: NULL = ورِّث من الدولة ثم المنطقة.
 * 0.00 في `shipping_cost` = مجاني عمدًا ⇒ توقّف الوراثة.
 */
class Governorate extends Model
{
    protected $fillable = [
        'name_ar',
        'name_en',
        'shipping_cost',
        'delivery_days_min',
        'delivery_days_max',
        'is_active',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            // decimal:2 يُبقي القيمة نصًّا لـ bcmath. لاحظ أن NULL يبقى NULL —
            // وهذا شرط بقاء دلالة «ورِّث» سليمة عبر الصبّ.
            'shipping_cost' => 'decimal:2',
            'delivery_days_min' => 'integer',
            'delivery_days_max' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** محافظات غير مسعَّرة — مصدر ودجت «تنبيه المحافظات بلا سعر» في اللوحة. */
    public function scopeUnpriced(Builder $query): Builder
    {
        return $query->whereNull('shipping_cost');
    }
}
