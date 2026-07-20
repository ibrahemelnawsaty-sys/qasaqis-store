<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * قيد واحد في سجلّ تغيّر حالة الطلب (جدول order_status_histories).
 *
 * سجل ملحق-فقط (append-only): يُكتب مرة عند الانتقال ولا يُعدَّل بعدها، لذا
 * UPDATED_AT = null والجدول بلا عمود updated_at أصلًا.
 *
 * الصفوف تُكتب خادميًا فقط (من OrderObserver عند wasChanged('status'))، ولا يوجد
 * أي مسار HTTP ينشئها من مدخلات المستخدم — لذلك $fillable يشمل actor_id بأمان.
 * الـ RelationManager في اللوحة للقراءة فقط ولا يعرض نموذج إنشاء/تعديل.
 */
class OrderStatusHistory extends Model
{
    /** السجل ملحق-فقط: لا عمود updated_at في الجدول. */
    public const UPDATED_AT = null;

    /** قيم عمود source — enum مطابق للهجرة حرفيًا (بند 1.1). */
    public const SOURCE_ADMIN = 'admin';

    public const SOURCE_SYSTEM = 'system';

    public const SOURCE_CUSTOMER = 'customer';

    /** تسميات عربية لمصدر التغيير (النمط المتبع في موارد Filament القائمة). */
    public const SOURCE_LABELS = [
        self::SOURCE_ADMIN => 'لوحة التحكم',
        self::SOURCE_SYSTEM => 'النظام',
        self::SOURCE_CUSTOMER => 'العميل',
    ];

    protected $fillable = [
        'order_id',
        'from_status',
        'to_status',
        'note',
        'actor_id',
        'source',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * المستخدم الذي أجرى التغيير — null للتغييرات النظامية (مهمة مجدولة)
     * أو إذا حُذف حسابه لاحقًا (nullOnDelete).
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
