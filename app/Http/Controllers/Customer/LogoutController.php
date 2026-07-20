<?php

declare(strict_types=1);

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Storefront\Concerns\InteractsWithSessionCart;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * خروج العميلة. POST + CSRF فقط (بند 4.2): زر خروج على GET يُشغَّل بصورة مموّهة
 * أو بجلب مسبق من المتصفح فيُخرج العميلة دون قصدها.
 *
 * ══ السلة تُحفظ حول invalidate() ══
 * `invalidate()` يُفرِّغ الجلسة كاملةً — ومنها مفتاح 'cart'. إفراغ سلة أمٍّ بلا
 * إنذار لمجرد أنها سجّلت الخروج هو خسارة إيراد مباشرة، لا مجرد إزعاج. نلتقط
 * السلة قبل الإبطال ونعيدها بعده. مفتاح الجلسة يأتي من
 * InteractsWithSessionCart::sessionCartKey() — مصدر واحد للحقيقة بلا نص مكرّر.
 */
final class LogoutController extends Controller
{
    use InteractsWithSessionCart;

    private const GUARD = 'customer';

    public function __invoke(Request $request): RedirectResponse
    {
        $cart = $request->session()->get($this->sessionCartKey());

        Auth::guard(self::GUARD)->logout();

        // إبطال الجلسة + تدوير رمز CSRF: يمنع تثبيت الجلسة وإعادة استعمال الرمز
        // القديم بعد الخروج على جهاز مشترك.
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if (is_array($cart) && $cart !== []) {
            $request->session()->put($this->sessionCartKey(), $cart);
        }

        return redirect()
            ->route('home')
            ->with('status', __('account.logout.done'));
    }
}
