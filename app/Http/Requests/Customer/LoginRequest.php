<?php

declare(strict_types=1);

namespace App\Http\Requests\Customer;

use App\Support\Phone\PhoneNormalizer;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * تحقّق «دخول العميلة». المعرّف هو **الجوال وحده** — لا بريد ولا «أيهما»: مسار فشل
 * واحد ورسالة واحدة، فلا تُستعمل الصفحة كقناة تعداد للأرقام أو البُرد المسجّلة.
 *
 * حدّ المعدّل (بند 4.6) بمفتاح **مركّب** `phone_normalized|IP`:
 * الاكتفاء بالـ IP وحده مرفوض على شبكات CGNAT المصرية (حيّ كامل خلف عنوان واحد
 * يُقفل بمحاولات مهاجم واحد)، والاكتفاء بالجوال وحده يتيح قفل حساب أي عميلة عمدًا.
 * هذه الطبقة الأولى؛ الثانية `throttle:` على المسار نفسه (يضيفها المنسّق) — ويجب
 * أن يكون حدّها **أوسع** من MAX_ATTEMPTS وإلا حجب 429 الرسالةَ العربية قبل ظهورها.
 */
final class LoginRequest extends FormRequest
{
    /** محاولات فاشلة مسموحة قبل القفل. */
    public const MAX_ATTEMPTS = 5;

    /** مدة القفل بالثواني. */
    public const DECAY_SECONDS = 60;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $phone = $this->input('phone');

        if (is_string($phone)) {
            $this->merge(['phone' => trim($phone)]);
        }
    }

    /**
     * بلا قاعدة regex على الجوال عمدًا: رفض الصيغة عند الدخول يفرّق بين «رقم غير
     * مصري» و«رقم مصري غير مسجَّل»، وهذا فرق يُستغَل. كل فشل يخرج برسالة واحدة.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'phone' => ['required', 'string', 'max:20'],
            'password' => ['required', 'string', 'max:255'],
            'remember' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'phone' => __('account.login.phone'),
            'password' => __('account.login.password'),
        ];
    }

    /**
     * يُستدعى تلقائيًا بعد نجاح التحقق — فيبقى فرض الحدّ داخل الـ Form Request
     * (بند 2.4) بلا سطر إضافي في المتحكم.
     */
    protected function passedValidation(): void
    {
        $this->ensureIsNotRateLimited();
    }

    /**
     * بيانات الاعتماد للحارس: `where('phone_normalized', …)` ثم Hash::check —
     * ينفّذهما EloquentUserProvider. النطاق الناعم للموديل يستبعد الحسابات
     * المحذوفة تلقائيًا، فلا تُستعاد بالدخول.
     *
     * @return array<string, string>
     */
    public function credentials(): array
    {
        return [
            'phone_normalized' => $this->normalizedPhone(),
            'password' => (string) $this->validated('password'),
        ];
    }

    /**
     * الرقم غير المصري يُطبَّع إلى '' فلا يطابق أي صفّ (العمود CHAR(10) يُكتب من
     * المطبِّع دائمًا) — يفشل الدخول بالرسالة الموحّدة نفسها بلا كشف السبب.
     */
    public function normalizedPhone(): string
    {
        return (string) PhoneNormalizer::normalize((string) $this->validated('phone'));
    }

    public function throttleKey(): string
    {
        return 'customer-login|'.$this->normalizedPhone().'|'.$this->ip();
    }

    /** يُستدعى من المتحكم عند فشل المطابقة فقط — لا تُحتسب المحاولة الناجحة. */
    public function hitRateLimiter(): void
    {
        RateLimiter::hit($this->throttleKey(), self::DECAY_SECONDS);
    }

    public function clearRateLimiter(): void
    {
        RateLimiter::clear($this->throttleKey());
    }

    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), self::MAX_ATTEMPTS)) {
            return;
        }

        // حدث إطار العمل القياسي — يلتقطه أي مستمع تدقيق لاحقًا (بند 4.7).
        event(new Lockout($this));

        // :seconds فقط — lang/ar/auth.php لا يعرّف :minutes، وتمرير متغيّر لا
        // تستعمله الرسالة يمرّ بصمت، أمّا العكس فيطبع الاسم حرفيًا للأم.
        throw ValidationException::withMessages([
            'phone' => __('auth.throttle', [
                'seconds' => RateLimiter::availableIn($this->throttleKey()),
            ]),
        ]);
    }
}
