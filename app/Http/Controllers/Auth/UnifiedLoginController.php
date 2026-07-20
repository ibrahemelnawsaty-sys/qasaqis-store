<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Phone\PhoneNormalizer;
use Filament\Facades\Filament;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * تسجيل دخول موحّد لكل المنصة: نقطة دخول واحدة يدخل منها العميل أو الأدمن.
 *
 * المُعرّف يحدّد الحارس دون كشفه للمستخدم:
 *  - يحوي بريدًا صالحًا → حارس `web` (User/الأدمن، جلسة Filament) → لوحة الأدمن.
 *  - غير ذلك → جوال → يُطبَّع بـ PhoneNormalizer → حارس `customer` → لوحة العميل.
 *
 * الحارسان منفصلان تمامًا (config/auth.php): دخول أحدهما لا يمنح وصول الآخر. رسالة
 * الفشل **واحدة** لكل الحالات فلا تصلح الصفحة لتعداد الحسابات.
 *
 * أمان (بعد مراجعة عدائية):
 *  - حدّ معدّل لكل مُعرّف+IP، ونشارك مفتاح `customer-login|…` نفسه الذي يستعمله
 *    مسار /account/login كي لا يصير هذا المسار طريقًا أضعف يلتفّ على قفل الحساب.
 *  - بعد نجاح حارس web نفرض بوّابة Filament نفسها (canAccessPanel: نشط + دور إداري)
 *    فلا يحصل موظّف مُعطَّل أو بلا دور على جلسة web رغم صحّة كلمة مروره.
 */
final class UnifiedLoginController extends Controller
{
    private const MAX_ATTEMPTS = 5;

    private const DECAY_SECONDS = 60;

    public function show(Request $request): View|RedirectResponse
    {
        if (Auth::guard('customer')->check()) {
            return redirect()->route('customer.dashboard');
        }

        if (Auth::guard('web')->check()) {
            return redirect()->to('/admin');
        }

        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'identifier' => ['required', 'string', 'max:191'],
            'password' => ['required', 'string', 'max:255'],
            'remember' => ['nullable', 'boolean'],
        ]);

        $identifier = trim((string) $data['identifier']);
        $password = (string) $data['password'];
        $remember = $request->boolean('remember');
        $isEmail = filter_var($identifier, FILTER_VALIDATE_EMAIL) !== false;
        $normalizedPhone = $isEmail ? '' : (string) PhoneNormalizer::normalize($identifier);

        // مفتاح مركّب لكل مُعرّف+IP. للعميل نستعمل مفتاح مسار العميل نفسه فيتّسق القفل
        // بين /login و /account/login ولا يصير أحدهما ثغرة تُبطل قفل الآخر.
        $key = $isEmail
            ? 'unified-login|'.mb_strtolower($identifier).'|'.$request->ip()
            : 'customer-login|'.$normalizedPhone.'|'.$request->ip();

        $this->ensureNotRateLimited($request, $key);

        if ($isEmail) {
            // 1) الأدمن (web) بالبريد.
            if (Auth::guard('web')->attempt(['email' => $identifier, 'password' => $password], $remember)) {
                $user = Auth::guard('web')->user();

                if ($user instanceof User && $user->canAccessPanel(Filament::getPanel('admin'))) {
                    RateLimiter::clear($key);
                    $request->session()->regenerate();

                    return redirect()->intended('/admin');
                }

                // مصادقة صحيحة لكن بلا صلاحية لوحة — نُسقط الجلسة ونعامله كفشل موحّد.
                Auth::guard('web')->logout();
            }

            // 2) العميل بالبريد: عمود email فريد على customers، والاستعادة تتم بالبريد،
            //    فمن الطبيعي أن تدخل العميلة ببريدها أو بجوالها.
            if (Auth::guard('customer')->attempt(['email' => $identifier, 'password' => $password], $remember)) {
                RateLimiter::clear($key);
                $request->session()->regenerate();

                return redirect()->intended(route('customer.dashboard'));
            }
        } elseif ($normalizedPhone !== ''
            && Auth::guard('customer')->attempt(['phone_normalized' => $normalizedPhone, 'password' => $password], $remember)) {
            RateLimiter::clear($key);
            $request->session()->regenerate();

            return redirect()->intended(route('customer.dashboard'));
        }

        RateLimiter::hit($key, self::DECAY_SECONDS);

        throw ValidationException::withMessages([
            'identifier' => __('auth.failed'),
        ]);
    }

    private function ensureNotRateLimited(Request $request, string $key): void
    {
        if (! RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            return;
        }

        event(new Lockout($request));

        throw ValidationException::withMessages([
            'identifier' => __('auth.throttle', [
                'seconds' => RateLimiter::availableIn($key),
            ]),
        ]);
    }
}
