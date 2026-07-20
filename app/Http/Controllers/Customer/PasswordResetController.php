<?php

declare(strict_types=1);

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\PasswordEmailRequest;
use App\Http\Requests\Customer\PasswordResetRequest;
use App\Models\Customer;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification as BaseNotification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

/**
 * استعادة كلمة مرور العميلة عبر **البريد** — الوسيط القياسي `passwords.customers`
 * بجدول `customer_password_reset_tokens` مفتاحه `email`.
 *
 * لماذا البريد لا الجوال: تحقّقنا أن config/services.php بلا أي مزوّد SMS/WhatsApp
 * API (الموجود store.whatsapp رقم نصّي لروابط wa.me فقط)، فالبريد هو قناة
 * الاسترداد الوحيدة الممكنة اليوم. ولهذا البريد إلزامي عند التسجيل.
 *
 * ══ منع التعداد ══
 * `email()` يعيد **نفس الرد ونفس الرسالة** سواء وُجد البريد أم لا. من يجرّب بريدًا
 * لا يعرف أبدًا إن كان له حساب.
 *
 * ══ لا ابتلاع صامت ══
 * إن كان المُرسِل log/array فلا رسالة تصل أبدًا؛ عندها تُعرض رسالة صريحة ومسار
 * الدعم بدل إيهام الأم أن رابطًا في طريقه إليها، ويُسجَّل تحذير للتشغيل.
 */
final class PasswordResetController extends Controller
{
    private const GUARD = 'customer';

    /** مفتاح الوسيط في config/auth.php ('passwords.customers'). */
    private const BROKER = 'customers';

    /** مُرسِلات لا تُوصل شيئًا لصندوق بريد حقيقي. */
    private const NON_DELIVERING_MAILERS = ['log', 'array'];

    public function request(): View|RedirectResponse
    {
        if (Auth::guard(self::GUARD)->check()) {
            return redirect()->route('customer.dashboard');
        }

        return view('account.passwords.request');
    }

    public function email(PasswordEmailRequest $request): RedirectResponse
    {
        if ($this->mailerCannotDeliver()) {
            Log::warning('customer.password.mailer_not_deliverable', [
                'mailer' => (string) config('mail.default'),
            ]);

            // لا نُنشئ رمزًا لن يصل أحدًا، ولا ندّعي إرسالًا لم يحدث.
            return back()->with('error', __('account.password.mail_unavailable'));
        }

        $email = (string) $request->validated('email');

        // النتيجة تُتجاهل عمدًا: أي تمييز بين INVALID_USER وRESET_LINK_SENT
        // وRESET_THROTTLED يكشف من له حساب. فشل الإرسال الحقيقي يُرصَد من السجل
        // لا من رد المستخدمة.
        Password::broker(self::BROKER)->sendResetLink(
            ['email' => $email],
            fn (Customer $customer, string $token) => $this->sendResetLinkMail($customer, $token),
        );

        return back()->with('status', __('account.password.status.sent'));
    }

    public function reset(Request $request, string $token): View
    {
        $email = $request->query('email');

        // الصفحة تُعرض لأي رمز بلا فحص: التفريق بين رمز صالح ومنتهٍ قبل الإرسال
        // يحوّلها إلى أداة فحص رموز.
        return view('account.passwords.reset', [
            'token' => $token,
            'email' => is_string($email) ? $email : '',
        ]);
    }

    public function update(PasswordResetRequest $request): RedirectResponse
    {
        $status = Password::broker(self::BROKER)->reset(
            $request->credentials(),
            static function (Customer $customer, string $password): void {
                // Hash::make صراحةً (بند 4.3)؛ cast «hashed» على الموديل لا يعيد
                // التجزئة لأنه يفحص Hash::isHashed أولًا.
                // تدوير remember_token يُبطل «تذكّرني» على كل الأجهزة الأخرى.
                $customer->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($customer));
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => __('account.password.status.token')]);
        }

        Log::info('customer.password.reset', ['ip' => $request->ip()]);

        // إلى صفحة الدخول لا إلى دخول تلقائي: من يملك الرابط ليس بالضرورة صاحبة
        // الحساب حتى تُثبت معرفتها بالكلمة الجديدة على الجهاز الذي تستعمله.
        return redirect()
            ->route('customer.login.show')
            ->with('status', __('account.password.status.reset'));
    }

    /**
     * رسالة الرابط. تُبنى هنا لا في صنف إشعار مستقل لأن app/Notifications خارج
     * نطاق هذه المهمة؛ كل نصّها من lang (بند 6.4).
     *
     * تُرسَل على الطلب (Notification::route) لا عبر ‎$customer->notify()‎ حتى لا
     * يعتمد المسار على سمة Notifiable في الموديل، ولأن إشعار Laravel الافتراضي
     * `ResetPassword` يبني رابطه من مسار اسمه `password.reset` — غير موجود في هذا
     * المشروع — فيرفع RouteNotFoundException. المسار هنا هو `customer.password.reset`.
     *
     * NOTE: بلا ShouldQueue عمدًا — الصنف المجهول غير قابل للتسلسل، والإرسال
     * الفوري هو المطلوب أصلًا (المُجدول يشغّل الطابور كل دقيقة، فالتأخير محسوس).
     */
    private function sendResetLinkMail(Customer $customer, string $token): void
    {
        $email = (string) $customer->getEmailForPasswordReset();

        $url = route('customer.password.reset', ['token' => $token, 'email' => $email]);
        $expire = (int) config('auth.passwords.'.self::BROKER.'.expire', 60);

        Notification::route('mail', $email)->notify(
            new class((string) $customer->name, $url, $expire) extends BaseNotification
            {
                public function __construct(
                    private readonly string $name,
                    private readonly string $url,
                    private readonly int $expire,
                ) {
                }

                /**
                 * @return array<int, string>
                 */
                public function via(object $notifiable): array
                {
                    return ['mail'];
                }

                public function toMail(object $notifiable): MailMessage
                {
                    // ‎->view()‎ لا سلسلة ‎->line()/->action()‎: القالب الافتراضي عامّ
                    // (ترويسة نصّية وزر أسود و«If you're having trouble…»). العرض هنا
                    // يرث emails.layout فيأخذ الترويسة والتذييل المؤسسيين.
                    return (new MailMessage)
                        ->subject(__('account.password.mail.subject'))
                        ->view('emails.reset-password', [
                            'name' => $this->name,
                            'url' => $this->url,
                            'expire' => $this->expire,
                        ]);
                }
            }
        );
    }

    /**
     * لا نبتلع فشل الإرسال بصمت. في بيئة الاختبار المُرسِل array عمدًا (phpunit.xml)
     * فيُستثنى، وإلا لاختبرنا مسار العطل بدل المسار الحقيقي.
     */
    private function mailerCannotDeliver(): bool
    {
        if (app()->environment('testing')) {
            return false;
        }

        return in_array((string) config('mail.default'), self::NON_DELIVERING_MAILERS, true);
    }
}
