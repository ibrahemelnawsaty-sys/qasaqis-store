<?php

declare(strict_types=1);

namespace App\Http\Requests\Customer;

use App\Models\Country;
use App\Models\Customer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * ‏تحقق «بياناتي» (customer.profile.update → PUT /account/profile).
 *
 * ‏قائمة بيضاء صارمة (الدستور 4.1/2.4). ثلاثة حقول **غائبة عمدًا** لا سهوًا:
 *
 *  1. ‏phone / phone_normalized — هو **هوية الدخول** (المعرّف الوحيد في نموذج
 *     ‏الدخول، وعليه فهرس فريد). تعديله ذاتيًا يعني تغيير من يستطيع الدخول للحساب،
 *     ‏وحجز رقم عميلة أخرى قبل أن تسجّل، وكسر الفهرس الفريد. ولا توجد اليوم أي قناة
 *     ‏تحقق من ملكية الرقم (لا مزوّد SMS/WhatsApp API في config/services.php)،
 *     ‏فالتعديل سيكون **بلا إثبات ملكية**. يبقى للقراءة فقط ويُغيَّر عبر الدعم.
 *  2. ‏is_claimed / phone_verified_at / email_verified_at — أعلام خادمية.
 *  3. ‏orders_count / total_spent — لا تُكتب ولا تُقرأ (تُحسب عند العرض).
 *
 * ‏كل الرسائل من lang/ar/validation.php (الدستور 6.4) — لا نص مثبّت هنا.
 */
final class ProfileUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        // ‏فحص هوية بسيط لا يمرّ بـ Gate إطلاقًا (Gate يرمي TypeError مع Customer —
        // ‏انظر التوثيق في App\Policies\OrderPolicy).
        return $this->user('customer') instanceof Customer;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $customerId = $this->user('customer')?->getKey();

        // ‏«مصر أو بلا دولة» تُعامَل كمصر: العميلة قد تحفظ محافظة بلا اختيار دولة.
        $isEgypt = in_array($this->input('last_country_code'), ['EG', null, ''], true);

        return [
            'name' => ['required', 'string', 'min:2', 'max:150'],

            // ‏إلزامي: البريد هو قناة الاسترداد الوحيدة الممكنة اليوم؛ إفراغه يحوّل
            // ‏الحساب إلى حساب غير قابل للاسترداد بنيويًا. unique يتجاهل صفّ العميلة
            // ‏نفسها كي لا يفشل حفظ الاسم وحده.
            'email' => [
                'required',
                'email',
                'max:191',
                Rule::unique('customers', 'email')->ignore($customerId),
            ],

            // ‏العنوان الافتراضي: مجرد قيم ملء مسبق لصفحة الدفع، كلها اختيارية،
            // ‏ولا تمسّ تسعير الشحن (مرجعه الطلب نفسه لا هذه الأعمدة).
            'last_country_code' => [
                'nullable',
                'string',
                'size:2',
                Rule::in($this->allowedCountryCodes()),
            ],
            'last_governorate' => $isEgypt
                ? ['nullable', 'string', Rule::in(config('egypt.governorates'))]
                : ['nullable', 'string', 'max:50'],
            'last_city' => ['nullable', 'string', 'max:80'],
            'last_address_line' => ['nullable', 'string', 'max:300'],

            // ‏تغيير كلمة المرور اختياري. إثبات كلمة المرور الحالية إلزامي متى طُلب
            // ‏التغيير — وإلا كانت جلسة مسروقة تكفي لقفل صاحبة الحساب خارج حسابها.
            // ‏اللاحقة :customer توجّه القاعدة لحارس العميلة لا للحارس الافتراضي web
            // ‏(تحقّقت من مصدر الإطار: القاعدة تقرأ أول معامل كاسم حارس).
            'current_password' => ['nullable', 'required_with:password', 'current_password:customer'],
            // ‏بلا قواعد تعقيد وبلا uncompromised(): الأخيرة نداء شبكة متزامن على
            // ‏جمهور بإنترنت ضعيف (الدستور 1.6/5.1).
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ];
    }

    /**
     * ‏الدول المسموح حفظها كافتراضي: مصر دائمًا + كل دولة قابلة للشحن فعليًا.
     * ‏نفس مصدر CheckoutRequest كي لا يُحفظ افتراضي لا يمكن الشحن إليه.
     *
     * @return list<string>
     */
    private function allowedCountryCodes(): array
    {
        return array_values(array_unique(array_merge(
            ['EG'],
            Country::query()->shippable()->pluck('iso_code')->all(),
        )));
    }

    /**
     * ‏الأعمدة القابلة للتحديث من هذا النموذج — قائمة مغلقة تُبنى خادميًا من
     * ‏validated() فقط. كلمة المرور **ليست** هنا: يتولّاها المتحكم بـ Hash::make.
     *
     * @return array<string, string|null>
     */
    public function profileAttributes(): array
    {
        return [
            'name' => (string) $this->validated('name'),
            'email' => (string) $this->validated('email'),
            'last_country_code' => $this->validated('last_country_code'),
            'last_governorate' => $this->validated('last_governorate'),
            'last_city' => $this->validated('last_city'),
            'last_address_line' => $this->validated('last_address_line'),
        ];
    }
}
