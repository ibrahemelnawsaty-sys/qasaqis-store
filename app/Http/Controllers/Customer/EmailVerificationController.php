<?php

declare(strict_types=1);

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\VerifyCodeRequest;
use App\Support\Verification\VerificationCodeService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * تأكيد بريد العميلة بكود (M9). القناة بريد اليوم، وتتبدّل إلى OTP جوال لاحقًا
 * عبر إعداد واحد بلا تغيير هنا. غير حاجب: العميلة مسجّلة الدخول وتستطيع تخطّي
 * التأكيد إلى حسابها، لكن يبقى بريدها «غير مؤكّد» حتى تُدخل الكود.
 */
final class EmailVerificationController extends Controller
{
    private const GUARD = 'customer';

    private const PURPOSE = 'email_verification';

    public function __construct(private readonly VerificationCodeService $codes)
    {
    }

    /** صفحة إدخال الكود. البريد مؤكَّد أصلًا؟ إلى اللوحة مباشرة. */
    public function show(Request $request): View|RedirectResponse
    {
        $customer = Auth::guard(self::GUARD)->user();

        if ($customer->email_verified_at !== null) {
            return redirect()->route('customer.dashboard');
        }

        return view('account.verify-email', [
            'email' => $customer->email,
        ]);
    }

    /** يتحقق من الكود ويضع email_verified_at عند النجاح. */
    public function verify(VerifyCodeRequest $request): RedirectResponse
    {
        $customer = Auth::guard(self::GUARD)->user();

        if ($customer->email_verified_at !== null) {
            return redirect()->route('customer.dashboard');
        }

        // استهلاك الكود ووضع الختم معًا في معاملة واحدة (بند 3.5): لا يبقى الكود
        // مُستهلَكًا بينما فشل حفظ الختم.
        $ok = DB::transaction(function () use ($customer, $request): bool {
            $verified = $this->codes->verify(
                (string) $customer->email,
                self::PURPOSE,
                (string) $request->validated('code'),
            );

            if ($verified) {
                // email_verified_at خارج $fillable عمدًا (حالة يحكمها الخادم).
                $customer->forceFill(['email_verified_at' => now()])->save();
            }

            return $verified;
        });

        if (! $ok) {
            return back()->with('verify_error', __('account.verify.invalid'));
        }

        return redirect()
            ->route('customer.dashboard')
            ->with('status', __('account.verify.success'));
    }

    /** إعادة إرسال كود جديد (throttle على المسار). */
    public function resend(Request $request): RedirectResponse
    {
        $customer = Auth::guard(self::GUARD)->user();

        if ($customer->email_verified_at !== null) {
            return redirect()->route('customer.dashboard');
        }

        $sent = $this->codes->issueAndSend((string) $customer->email, self::PURPOSE);

        return back()->with(
            $sent ? 'status' : 'verify_error',
            $sent ? __('account.verify.resent') : __('account.verify.send_failed'),
        );
    }
}
