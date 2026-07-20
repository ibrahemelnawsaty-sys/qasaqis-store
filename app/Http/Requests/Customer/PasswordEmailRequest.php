<?php

declare(strict_types=1);

namespace App\Http\Requests\Customer;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * تحقّق «طلب رابط استعادة كلمة المرور».
 *
 * **بلا قاعدة `exists`** عمدًا: التحقق من وجود البريد هنا يحوّل النموذج إلى قناة
 * تعداد لبُرد العميلات. الوجود يُفحص داخل وسيط كلمات المرور، والرد للمستخدمة موحّد
 * في كل الحالات (انظر PasswordResetController::email).
 *
 * حدّ المعدّل (بند 4.6) بمفتاح مركّب `email|IP` وأشدّ من الدخول: كل إرسال يستهلك
 * حصة SMTP حقيقية ويصل بريدًا لصندوق شخص لم يطلبه.
 */
final class PasswordEmailRequest extends FormRequest
{
    /** طلبات مسموحة قبل القفل. */
    public const MAX_ATTEMPTS = 3;

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
            'email' => ['required', 'string', 'email', 'max:191'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'email' => __('account.password.email'),
        ];
    }

    /**
     * كل طلب يستهلك حصة — سواء وُجد البريد أم لا. لو حُوسبت المحاولات الفاشلة فقط
     * لصار زمن القفل نفسه إشارةً تُميّز بريدًا مسجّلًا عن غيره.
     */
    protected function passedValidation(): void
    {
        $this->ensureIsNotRateLimited();

        RateLimiter::hit($this->throttleKey(), self::DECAY_SECONDS);
    }

    public function throttleKey(): string
    {
        return 'customer-password-email|'.(string) $this->validated('email').'|'.$this->ip();
    }

    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), self::MAX_ATTEMPTS)) {
            return;
        }

        event(new Lockout($this));

        // رسالة محايدة بلا مدّة: ذكر ثوانٍ دقيقة يكشف متى بدأت النافذة، والمفتاح
        // مركّب على البريد فتصير المدّة إشارةً غير مباشرة على نشاط ذلك البريد.
        throw ValidationException::withMessages([
            'email' => __('account.password.status.throttled'),
        ]);
    }
}
