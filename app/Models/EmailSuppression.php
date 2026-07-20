<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * قائمة حظر الحملات. وجود البريد هنا يعني: لا حملة تسويقية بعد اليوم. تُملأ من نقرة
 * إلغاء الاشتراك أو يدويًا من الأدمن. لا تمسّ رسائل المعاملات.
 *
 * @property int $id
 * @property string $email
 * @property string $reason
 */
class EmailSuppression extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'email',
        'reason',
        'suppressed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'suppressed_at' => 'datetime',
        ];
    }
}
