<?php

declare(strict_types=1);

namespace App\Http\Requests\Customer;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * تحقّق «تعيين كلمة مرور جديدة» بعد فتح رابط الاستعادة.
 *
 * الرمز يُقبل نصًّا فقط؛ صلاحيته وربطه بالبريد يفحصهما وسيط كلمات المرور مقابل
 * النسخة المجزّأة في `customer_password_reset_tokens` — **يُمنع** الاستدلال على
 * صلاحيته هنا. وقواعد كلمة المرور هي نفسها في التسجيل: طول 8 + تأكيد، بلا تعقيد
 * وبلا uncompromised() (نداء شبكة متزامن على شبكة ضعيفة).
 */
final class PasswordResetRequest extends FormRequest
{
    /** محاولات مسموحة قبل القفل. */
    public const MAX_ATTEMPTS = 5;

    /** مدة القفل بالثواني (ربع ساعة). */
    public const DECAY_SECONDS = 900;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $email = $this->input('email');

        if (is_string($email)) {
            $this->merge(['email' => mb_strtolower(trim($email))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'token' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:191'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'email' => __('account.password.email'),
            'password' => __('account.password.new_password'),
            'password_confirmation' => __('account.password.new_password_confirmation'),
        ];
    }

    protected function passedValidation(): void
    {
        $this->ensureIsNotRateLimited();

        RateLimiter::hit($this->throttleKey(), self::DECAY_SECONDS);
    }

    /**
     * بيانات الوسيط كما يتوقعها PasswordBroker::reset (بند 1.1: التوقيع مقروء من
     * vendor لا مفترضًا) — password_confirmation يتجاهله المزوّد ولا يُخزَّن.
     *
     * @return array<string, string>
     */
    public function credentials(): array
    {
        return [
            'token' => (string) $this->validated('token'),
            'email' => (string) $this->validated('email'),
            'password' => (string) $this->validated('password'),
            'password_confirmation' => (string) $this->input('password_confirmation'),
        ];
    }

    public function throttleKey(): string
    {
        return 'customer-password-reset|'.(string) $this->validated('email').'|'.$this->ip();
    }

    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), self::MAX_ATTEMPTS)) {
            return;
        }

        event(new Lockout($this));

        throw ValidationException::withMessages([
            'email' => __('account.password.status.throttled'),
        ]);
    }
}
