<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Audit\RecordsAdminActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;

class Coupon extends Model
{
    use RecordsAdminActivity;

    /** جمهور الكوبون: من يستحقّ استخدامه (يُفحَص خادميًّا في CouponService). */
    public const AUDIENCES = [
        'all' => 'كل العملاء',
        'specific' => 'عميل محدّد',
        'new' => 'عملاء جدد (أوّل طلب)',
        'returning' => 'عملاء عائدون',
        'lapsed' => 'عملاء متوقّفون (منذ مدّة)',
        'vip' => 'عملاء مميّزون',
    ];

    protected $fillable = [
        'code',
        'description',
        'type',
        'value',
        'min_order_total',
        'max_discount',
        'starts_at',
        'expires_at',
        'usage_limit',
        'usage_limit_per_user',
        'used_count',
        'applies_to',
        'audience',
        'customer_id',
        'lapsed_days',
        'min_orders',
        'min_spent',
        'is_active',
        'free_shipping',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'min_order_total' => 'decimal:2',
            'max_discount' => 'decimal:2',
            'min_spent' => 'decimal:2',
            'lapsed_days' => 'integer',
            'min_orders' => 'integer',
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
            'is_active' => 'boolean',
            'free_shipping' => 'boolean',
        ];
    }

    /** العميل المستهدَف حين audience=specific (وإلا null). */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function books(): BelongsToMany
    {
        return $this->belongsToMany(Book::class, 'coupon_book');
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'coupon_category');
    }

    public function usages(): HasMany
    {
        return $this->hasMany(CouponUsage::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * ينشئ (أو يُعيد) كود خصم استعادة idempotent موصوفًا بـ$description: إن وُجد كوبونٌ
     * نشطٌ غير مستهلَك وغير منتهٍ بالوصف نفسه أعاد كوده (فلا يتكرّر عند تحديث الصفحة أو
     * تكرار الضغط)، وإلا أنشأ كودًا فريدًا — حلقة تلتقط تصادم القيد الفريد النادر
     * (23000) فلا تُسقط الصفحة، وغيره يُرمى. يُعيد الكود أو null إن تعذّر توليد فريد.
     *
     * منطق مشترك لمتابعة «الطلبات المتروكة» و«السلات المتروكة» (خصمٌ نسبيّ لمرّة واحدة).
     * حين يُمرَّر customerId يصير الكوبون مقصورًا على ذلك العميل (audience=specific) فلا
     * يستطيع غيره استخدامه ولو تسرّب الكود (يُفحَص خادميًّا في CouponService).
     */
    public static function createRecovery(string $description, int $percent, int $days, string $prefix = 'BACK', ?int $customerId = null): ?string
    {
        $existing = static::query()
            ->where('description', $description)
            ->where('is_active', true)
            ->where('used_count', 0)
            ->where(fn (Builder $query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->value('code');

        if ($existing !== null) {
            return (string) $existing;
        }

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $code = $prefix.strtoupper(Str::random(5));

            try {
                static::query()->create([
                    'code' => $code,
                    'description' => $description,
                    'type' => 'percentage',
                    'value' => $percent,
                    'starts_at' => now(),
                    'expires_at' => now()->addDays($days),
                    'usage_limit' => 1,
                    'usage_limit_per_user' => 1,
                    'applies_to' => 'all',
                    'audience' => $customerId !== null ? 'specific' : 'all',
                    'customer_id' => $customerId,
                    'is_active' => true,
                    'free_shipping' => false,
                ]);

                return $code;
            } catch (QueryException $e) {
                // 23000 = انتهاك قيد سلامة (تصادم unique) → جرّب كودًا آخر. غيره يُرمى.
                if ((string) $e->getCode() !== '23000') {
                    throw $e;
                }
            }
        }

        return null;
    }
}
