<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * عنوان مُسمّى في دفتر عناوين العميلة (M12). يُملأ في الدفع أو يُدار من الملف.
 * كل الحقول من إدخال العميلة لعنوانها هي (بلا حالة يحكمها الخادم)، فكلها fillable
 * عدا customer_id الذي يُضبَط خادميًّا من الحساب المصادَق لا من مدخلات النموذج.
 */
class CustomerAddress extends Model
{
    protected $fillable = [
        'label',
        'name',
        'phone',
        'phone_alt',
        'country_code',
        'governorate',
        'state_province',
        'city',
        'address_line',
        'address_notes',
        'is_default',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
