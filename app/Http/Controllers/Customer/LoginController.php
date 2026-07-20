<?php

declare(strict_types=1);

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\LoginRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * دخول العميلة بالجوال + كلمة المرور. متحكم نحيف (بند 2.2): التحقق وحدّ المعدّل
 * في LoginRequest، والمطابقة يتولاها حارس `customer`
 * (`where('phone_normalized', …)` ثم Hash::check داخل EloquentUserProvider).
 *
 * رسالة الفشل **واحدة** لكل الحالات — رقم غير مسجَّل، كلمة مرور خاطئة، حساب
 * محذوف ناعمًا — فلا تصلح الصفحة لتعداد أرقام العميلات.
 */
final class LoginController extends Controller
{
    private const GUARD = 'customer';

    public function show(): View|RedirectResponse
    {
        if (Auth::guard(self::GUARD)->check()) {
            return redirect()->route('customer.dashboard');
        }

        return view('account.login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        if (! Auth::guard(self::GUARD)->attempt($request->credentials(), $request->boolean('remember'))) {
            $request->hitRateLimiter();

            // auth.failed — المفتاح الذي يبحث عنه إطار Laravel بالاسم، ونصّه عربي
            // موحّد لكل أسباب الفشل (رقم غير مسجّل، كلمة خاطئة، حساب محذوف).
            throw ValidationException::withMessages([
                'phone' => __('auth.failed'),
            ]);
        }

        $request->clearRateLimiter();
        $request->session()->regenerate();

        return redirect()->intended(route('customer.dashboard'));
    }
}
