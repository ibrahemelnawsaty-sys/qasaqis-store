<?php

declare(strict_types=1);

namespace App\Http\Requests\Customer;

use App\Models\Customer;
use App\Support\Phone\PhoneNormalizer;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use LogicException;

/**
 * تحقّق «إنشاء حساب عميلة» — أربعة حقول ولا خامس (القرار المعماري §3).
 *
 * قائمة بيضاء صارمة (بند 4.1/2.4): لا يُقبل من العميل أي حقل غير المذكور هنا،
 * و`phone_normalized` **لا يُقرأ من الطلب إطلاقًا** بل يُشتق خادميًا من `phone`
 * ويُكتب بـ forceFill لأنه خارج $fillable في الموديل عمدًا (تحقّقت من Customer).
 *
 * الجوال هو الهوية: التصادم يُفحص بـ withTrashed() فيشمل الصفوف المحذوفة ناعمًا —
 * كما تنصّ هجرة create_customers_table صراحةً — فلا يرث القادمُ الجديد رقمَ أولى
 * ولا طلباتها، ويُردّ برسالة محايدة واحدة تُوجّه للدخول أو للدعم.
 *
 * البريد إلزامي عمدًا: تحقّقنا أن config/services.php بلا مزوّد SMS/WhatsApp API،
 * فالبريد هو قناة الاسترداد الوحيدة الممكنة، وحساب لا يُستعاد بنيويًا مرفوض.
 * التسجيل اختياري بالكامل فلا يمسّ هذا مسار الشراء كضيف.
 */
final class RegisterRequest extends FormRequest
{
    /** نُقل حرفيًا من CheckoutRequest::EGYPT_PHONE_REGEX (نفس صيغة الجوال المصري). */
    private const EGYPT_PHONE_REGEX = '/^(?:\+?20|0)?1[0125]\d{8}$/';

    public function authorize(): bool
    {
        return true;
    }

    /**
     * قصّ الفراغات فقط. حارس is_string يمنع «Array to string conversion» عند إرسال
     * name[]=x عمدًا؛ تُترك القيمة غير النصّية كما هي لترفضها قاعدة 'string'.
     */
    protected function prepareForValidation(): void
    {
        $trimmed = [];

        foreach (['name', 'phone'] as $field) {
            $value = $this->input($field);

            if (is_string($value)) {
                $trimmed[$field] = trim($value);
            }
        }

        $email = $this->input('email');

        if (is_string($email)) {
            $trimmed['email'] = mb_strtolower(trim($email));
        }

        if ($trimmed !== []) {
            $this->merge($trimmed);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:150'],

            // bail: لا نستعلم قاعدة البيانات بقيمة مرفوضة الصيغة أصلًا.
            'phone' => [
                'bail',
                'required',
                'string',
                'regex:'.self::EGYPT_PHONE_REGEX,
                $this->uniqueNormalizedPhoneRule(),
            ],

            // unique يستعلم الجدول مباشرةً (لا Eloquent) فيشمل الصفوف المحذوفة
            // ناعمًا — وهو المقصود: لا استيلاء صامت على بريد حساب مُغلق.
            'email' => ['required', 'string', 'email', 'max:191', Rule::unique('customers', 'email')],

            // بلا قواعد تعقيد وبلا uncompromised(): نداء شبكة متزامن في أحرج نموذج
            // على شبكة ضعيفة = نموذج معطّل. الطول هو الحماية العملية هنا.
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'phone.regex' => __('validation.egypt_phone'),
        ];
    }

    /**
     * أسماء الحقول من lang (بند 6.4). name/email يرثان تسميتهما من
     * validation.attributes الموجودة أصلًا؛ نغطّي هنا ما ليس فيها أو ما تسمّيه
     * صفحات الحساب تسمية أدقّ («رقم الجوال» لا «رقم الهاتف»).
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'phone' => __('account.register.phone'),
            'password' => __('account.register.password'),
            'password_confirmation' => __('account.register.password_confirmation'),
        ];
    }

    /**
     * الجوال المطبَّع (10 خانات) — مصدره الوحيد حقل phone بعد التحقق.
     *
     * PhoneNormalizer::normalize يعيد null لغير الجوال المصري؛ بعد اجتياز
     * EGYPT_PHONE_REGEX وقاعدة التفرّد أدناه لا يمكن أن يكون null، والاستثناء هنا
     * تأكيد لهذا الثبات لا معالجة لمدخل مستخدم (رسالته للمطوّر لا للأم).
     */
    public function normalizedPhone(): string
    {
        $normalized = PhoneNormalizer::normalize((string) $this->validated('phone'));

        if ($normalized === null) {
            throw new LogicException('Validated phone failed PhoneNormalizer; regex and normalizer have diverged.');
        }

        return $normalized;
    }

    /**
     * الحقول القابلة للتعبئة الجماعية فقط (Customer::$fillable). كلمة المرور
     * تُجزَّأ هنا بـ Hash::make (بند 4.3) فلا تخرج النسخة الخام من هذا الصنف؛
     * وcast «hashed» على الموديل لا يعيد تجزئتها لأنه يفحص Hash::isHashed أولًا.
     *
     * تُترك last_* والعدّادات وis_claimed لقيمها الافتراضية عمدًا: العدّادات لا
     * تُكتب في v1، والعنوان يُشتق من أول طلب مربوط لا من نموذج التسجيل.
     *
     * @return array<string, string>
     */
    public function toAttributes(): array
    {
        return [
            'name' => (string) $this->validated('name'),
            'email' => (string) $this->validated('email'),
            'password' => Hash::make((string) $this->validated('password')),
        ];
    }

    /**
     * أعمدة الهوية — خارج $fillable عمدًا في الموديل (الجوال معرّف الدخول وغير
     * قابل للتعديل الذاتي)، فتُكتب بـ forceFill من قيمة اشتقّها الخادم وحده.
     *
     * @return array<string, string|null>
     */
    public function identityColumns(): array
    {
        $phone = (string) $this->validated('phone');

        return [
            'phone_normalized' => $this->normalizedPhone(),
            'phone_e164' => PhoneNormalizer::toE164($phone),
        ];
    }

    /**
     * تفرّد الجوال المطبَّع شاملًا المحذوف ناعمًا. رسالة واحدة محايدة تُوجّه لتسجيل
     * الدخول: لا استرجاع تلقائي لصفّ محذوف (يسلّم طلبات الأولى للتالي)، والدعم هو
     * المسار الوحيد لاستعادته.
     */
    private function uniqueNormalizedPhoneRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (! is_string($value)) {
                return;
            }

            $normalized = PhoneNormalizer::normalize($value);

            // حارس تباعد: لو قبِل الـ regex رقمًا يرفضه المطبِّع، تُعرض رسالة
            // الصيغة العربية بدل انهيار لاحق بـ NULL في عمود NOT NULL.
            if ($normalized === null) {
                $fail(__('validation.egypt_phone'));

                return;
            }

            if (! Customer::withTrashed()->where('phone_normalized', $normalized)->exists()) {
                return;
            }

            // تسجيل إلزامي (بند 4.7): التصادم إمّا عميلة نسيت أن لها حسابًا وإمّا
            // محاولة استيلاء على رقم غيرها — والدعم يحتاج الأثر في الحالتين.
            // نُسجّل آخر أربع خانات فقط: السجلات أقل حمايةً من قاعدة البيانات،
            // وهذا القدر يكفي لمطابقة تذكرة دعم دون نقل رقم كامل إليها.
            Log::info('customer.register.phone_collision', [
                'phone_last4' => substr($normalized, -4),
                'ip' => $this->ip(),
            ]);

            $fail(__('account.register.phone_taken'));
        };
    }
}
