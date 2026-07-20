<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * مصروف تشغيلي (المرحلة ٤ج): إعلانات، رواتب، تغليف… يُطرح من هامش المساهمة
 * لحساب صافي ربح النشاط. المبلغ decimal لا float (الدستور 3.5).
 */
class Expense extends Model
{
    use HasFactory;

    protected $fillable = [
        'category',
        'title',
        'amount',
        'incurred_on',
        'note',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'incurred_on' => 'date',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
