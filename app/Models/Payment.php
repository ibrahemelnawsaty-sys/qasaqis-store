<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payment extends Model
{
    protected $fillable = [
        'order_id',
        'payment_method_code',
        'amount',
        // رسوم معالجة الدفع (م٤ب) — ما تقتطعه البوابة/COD.
        'fee_amount',
        'status',
        'transaction_ref',
        'gateway_response',
        'paid_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'fee_amount' => 'decimal:2',
            'gateway_response' => 'array',
            'paid_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function proofs(): HasMany
    {
        return $this->hasMany(PaymentProof::class);
    }
}
