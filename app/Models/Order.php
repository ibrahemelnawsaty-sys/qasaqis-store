<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use SoftDeletes;
    // سجل تدقيق «من غيّر ماذا ومتى» (M8) — يُسجّل تغييرات الحالة والدفع للأدمن.
    use \App\Support\Audit\RecordsAdminActivity;

    protected $fillable = [
        'order_number',
        // مفتاح منع التكرار (M7). يُشتق من الجلسة خادميًا لا من مدخلات العميل.
        'idempotency_key',
        'user_id',
        // حساب العميلة المالكة (M8). يُكتب خادميًا فقط: عند الإنشاء إن كانت مسجّلة
        // دخولًا، أو عبر ربط/تبنٍّ بمطابقة الجوال — لا يُقبل من مدخلات العميل.
        'customer_id',
        'status',
        'customer_name',
        'customer_phone',
        'customer_phone_alt',
        'customer_email',
        'governorate',
        'country_code',
        'state_province',
        'city',
        'address_line',
        'address_notes',
        'shipping_zone_code',
        'subtotal',
        'discount_total',
        'shipping_total',
        'grand_total',
        'coupon_id',
        'coupon_code',
        'payment_method',
        'payment_status',
        'shipping_company',
        'tracking_number',
        'whatsapp_confirmed_at',
        'confirmed_by',
        'customer_note',
        'admin_note',
        'ip_address',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'shipping_total' => 'decimal:2',
            'grand_total' => 'decimal:2',
            'whatsapp_confirmed_at' => 'datetime',
            // ختم استرجاع المخزون (M2). ليس في $fillable عمدًا — يُضبط خادميًا
            // عبر forceFill فقط، فلا يُقبل من مدخلات المستخدم.
            'stock_restored_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class);
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function paymentProofs(): HasMany
    {
        return $this->hasMany(PaymentProof::class);
    }

    public function tracking(): HasOne
    {
        return $this->hasOne(OrderTracking::class);
    }

    public function scopeStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }
}
