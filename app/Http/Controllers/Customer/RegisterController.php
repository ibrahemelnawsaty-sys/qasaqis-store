<?php

declare(strict_types=1);

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\RegisterRequest;
use App\Models\Customer;
use App\Support\Verification\VerificationCodeService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * إنشاء حساب عميلة. متحكم نحيف (بند 2.2): التحقق والتطبيع والتجزئة كلها في
 * RegisterRequest، وهنا التنسيق فقط.
 *
 * ══ قاعدة أمنية ملزمة: التسجيل لا يربط أي طلب. إطلاقًا. ══
 * الحساب يُنشأ فارغًا. ربط الطلبات السابقة بمطابقة رقم الجوال **محظور** لأن
 * `/checkout` لا يتحقق من ملكية الرقم إطلاقًا: أي شخص يقدّم طلبًا برقم أي شخص آخر.
 * فلو ربطنا هنا بالجوال لصار التسجيل بوابة استيلاء بتكلفة صفر على سجلّ طلبات
 * الضحية. الربط يجري حصريًا بإثبات حيازة دليل خاص بطلب **واحد بعينه**:
 * `orders.claim` (توقيع + مطابقة جوال) أو `customer.orders.attach` (رقم الطلب +
 * الجوال). أي إضافة «ربط جماعي» هنا لاحقًا تُعدّ ثغرة لا ميزة.
 */
final class RegisterController extends Controller
{
    /** مفتاح الحارس في config/auth.php — منفصل تمامًا عن حارس الإداريين `web`. */
    private const GUARD = 'customer';

    public function show(): View|RedirectResponse
    {
        if (Auth::guard(self::GUARD)->check()) {
            return redirect()->route('customer.dashboard');
        }

        return view('account.register');
    }

    public function store(RegisterRequest $request, VerificationCodeService $codes): RedirectResponse
    {
        // النمط الذي يفرضه الموديل نفسه: phone_normalized وphone_e164 خارج
        // $fillable عمدًا (الجوال هوية غير قابلة للتعديل الذاتي) فيُكتبان بـ
        // forceFill من قيمة اشتقّها الخادم — لا من أي حقل نموذج (بند 4.1).
        $customer = new Customer($request->toAttributes());
        $customer->forceFill($request->identityColumns())->save();

        Auth::guard(self::GUARD)->login($customer);

        // تثبيت الجلسة: معرّف جديد بعد المصادقة مع الاحتفاظ ببيانات الجلسة —
        // فتبقى سلة العميلة (مفتاح 'cart') كما هي ولا يضيع شراؤها بالتسجيل.
        $request->session()->regenerate();

        Log::info('customer.registered', [
            'customer_id' => $customer->id,
            'ip' => $request->ip(),
        ]);

        // كود تأكيد البريد (M9). فشل الإرسال لا يُسقِط التسجيل — الحساب أُنشئ
        // والعميلة تُعيد الإرسال من صفحة التأكيد.
        $codes->issueAndSend((string) $customer->email, 'email_verification');

        return redirect()
            ->route('customer.verify.show')
            ->with('status', __('account.verify.sent'));
    }
}
