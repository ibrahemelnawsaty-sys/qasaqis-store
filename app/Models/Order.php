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

    protected $fillable = [
        'order_number',
        // مفتاح منع التكرار (M7). يُشتق من الجلسة خادميًا لا من مدخلات العميل.
        'idempotency_key',
        'user_id',
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

    /**
     * الإيراد المحقّق — القاعدة الوحيدة التي تحكم كل رقم في القسم المالي
     * (قرار المالك المعتمد 2026-07-20):
     *   • المدفوع مقدّمًا (أونلاين/تحويل يدوي) يُحتسب عند payment_status = paid.
     *   • الدفع عند الاستلام (COD) يُحتسب عند status ∈ (delivered, completed)،
     *     لأنه يُنشأ unpaid ولا يتحوّل إلى paid أبدًا (النقطة الوحيدة التي تضبط
     *     paid هي قبول إثبات التحويل اليدوي) — فاعتماد paid وحده يُخفي كل مبيعات
     *     COD، وهي الأغلب في مصر.
     *   • يُستبعد ضمنيًا كل ما عدا ذلك: pending / cancelled / refused / refunded.
     *
     * القيم مطابقة لـ enum('status') و enum('payment_status') في هجرتَي الطلبات
     * (تُحقّق فعليًا، لا تُخمَّن — الدستور 1.1).
     */
    public function scopeRealisedRevenue(Builder $query): Builder
    {
        // الاستبعاد أولًا ويعلو على أي شيء: طلب ملغى/مرفوض/مرتجع لا يُحتسب إيرادًا
        // ولو كان مدفوعًا (paid ثم أُلغي/رُدّ) — وإلا حُسبت مبيعات لم تكتمل.
        //
        // فرع delivered/completed مقصور على COD عمدًا: هو وحده يُنشأ unpaid ولا
        // يتحوّل إلى paid، فيُحتسب عند التسليم. أما المدفوع مقدّمًا (أونلاين/تحويل)
        // فيُحتسب حصريًا عبر paid — وإلا لو وُضع طلب أونلاين غير مدفوع على «مُسلَّم»
        // يدويًا لاحتُسب إيرادًا لم يُحصَّل (قيمة الـ enum هي 'cod' لا 'cash_on_delivery').
        return $query
            ->whereNotIn('status', ['cancelled', 'refused', 'refunded'])
            ->where(function (Builder $q): void {
                $q->where('payment_status', 'paid')
                    ->orWhere(function (Builder $cod): void {
                        $cod->where('payment_method', 'cod')
                            ->whereIn('status', ['delivered', 'completed']);
                    });
            });
    }
}
