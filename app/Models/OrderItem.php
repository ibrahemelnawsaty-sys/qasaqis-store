<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id',
        'book_id',
        'book_title',
        'unit_price',
        'unit_cost',
        'quantity',
        'line_total',
        'line_cost',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            // التكلفة تبقى nullable-safe: الصبّ لا يحوّل null إلى 0 (الدستور 0.4).
            'unit_cost' => 'decimal:2',
            'line_total' => 'decimal:2',
            'line_cost' => 'decimal:2',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }
}
