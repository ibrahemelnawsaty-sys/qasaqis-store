<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * حساب عميلة المتجر — كيان منفصل تمامًا عن App\Models\User (الإداريين).
 *
 * يرث Illuminate\Foundation\Auth\User لأنه يجمع السمات الأربع اللازمة للحارس
 * customer وقد تُحقّق منها في المصدر: Authenticatable (getAuthPassword،
 * remember_token) و Authorizable و CanResetPassword و MustVerifyEmail.
 *
 * ⚠️ يُمنع منعًا باتًا إضافة Spatie\Permission\Traits\HasRoles أو
 * Filament\Models\Contracts\FilamentUser إلى هذا الصنف. الأول يمنح العميلة أدوارًا
 * قابلة للفحص، والثاني هو حرفيًا بوابة الدخول إلى /admin. غيابهما هو ما يجعل
 * تسريب حساب عميلة حادثًا محدودًا لا اختراقًا للوحة التحكم.
 *
 * ⚠️ يُمنع استخدام Gate / Policy / authorize() / can() مع هذا الموديل: مُسجَّل في
 * AppServiceProvider (السطر 66) Gate::before بتوقيع مقيّد بالنوع
 * `static fn (User $user)` — تمرير Customer إليه يرفع TypeError. التفويض في نطاق
 * الحساب يكون بشرط صريح في الاستعلام: ->where('customer_id', $id)->firstOrFail().
 *
 * ⚠️ Notifiable مطلوبة لا زينة: CanResetPassword::sendPasswordResetNotification()
 * تستدعي $this->notify()، وهي غير موجودة في Foundation\Auth\User. بدونها ينكسر
 * مسار استعادة كلمة المرور عند أول إرسال.
 *
 * ملاحظة على $fillable (الباب 4.1): phone_normalized و phone_e164 **خارجها عمدًا**.
 * الجوال هو الهوية ومعرّف الدخول، وغير قابل للتعديل الذاتي بقرار معماري؛ إبقاؤه
 * غير قابل للتعبئة الجماعية يمنع أي مسار مستقبلي من تغييره عبر
 * $customer->update($request->validated()). نفس سابقة Order::$stock_restored_at في
 * هذا المستودع. مسار التسجيل يضبطهما خادميًا:
 *
 *     $customer = new Customer($validatedSubset);
 *     $customer->forceFill([
 *         'phone_normalized' => PhoneNormalizer::normalize($request->validated('phone')),
 *         'phone_e164' => PhoneNormalizer::toE164($request->validated('phone')),
 *     ])->save();
 *
 * وكذلك is_claimed و orders_count و total_spent و phone_verified_at و
 * email_verified_at خارج $fillable: كلها حالة يحكمها الخادم لا العميل.
 */
class Customer extends Authenticatable
{
    /** @use HasFactory<CustomerFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'last_governorate',
        'last_city',
        'last_address_line',
        'last_country_code',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            // 'hashed' يجزّئ عند الإسناد (الباب 4.3) ولا يعيد التجزئة لقيمة مجزّأة.
            'password' => 'hashed',
            'phone_verified_at' => 'datetime',
            'email_verified_at' => 'datetime',
            'is_claimed' => 'boolean',
            'orders_count' => 'integer',
            // مبلغ لا float (الباب 3.5) — يعيد سلسلة "0.00" كباقي أعمدة المال.
            'total_spent' => 'decimal:2',
        ];
    }

    /**
     * طلبات هذه العميلة — الطلبات المربوطة صراحةً فقط.
     *
     * الربط لا يحدث أبدًا بمطابقة رقم الجوال: عمود customer_id يُكتب إما عند
     * إنشاء الطلب من جلسة مصادَقة، أو بربط صريح لطلب واحد بدليل خاص به.
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
