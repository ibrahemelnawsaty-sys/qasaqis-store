<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * تجاوز تسعيرٍ يدويّ لكتابٍ بعملةٍ محدّدة (بعملة الوجهة). صفٌّ = سعرٌ ثابتٌ وضعه
 * المالك يدويًّا؛ غيابه = تحويلٌ آليّ من الأساس. انظر book_country_prices migration.
 *
 * @property int $book_id
 * @property string $currency_code
 * @property float $price
 * @property float|null $old_price
 * @property bool $is_active
 */
class BookCountryPrice extends Model
{
    protected $fillable = [
        'book_id', 'currency_code', 'price', 'old_price', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:3',
            'old_price' => 'decimal:3',
            'is_active' => 'boolean',
        ];
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_code', 'code');
    }
}
