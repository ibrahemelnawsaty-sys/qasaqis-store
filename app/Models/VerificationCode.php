<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * كود تحقق مُخزَّن مُجزّأً (M9). كل الكتابة تمرّ عبر VerificationCodeService؛
 * $fillable هنا لخدمة الخدمة فقط، والكود الخام لا يُخزَّن إطلاقًا.
 */
class VerificationCode extends Model
{
    protected $fillable = [
        'identifier',
        'channel',
        'purpose',
        'code_hash',
        'attempts',
        'expires_at',
        'consumed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
            'attempts' => 'integer',
        ];
    }

    /** أكواد حيّة: غير مُستهلَكة ولم تنتهِ صلاحيتها. */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('consumed_at')->where('expires_at', '>', now());
    }
}
