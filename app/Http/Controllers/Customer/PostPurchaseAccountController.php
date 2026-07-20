<?php

declare(strict_types=1);

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\PostPurchaseAccountRequest;
use App\Models\Customer;
use App\Models\Order;
use App\Support\Phone\PhoneNormalizer;
use App\Support\Verification\VerificationCodeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;

/**
 * إنشاء حساب من صفحة الشكر بعد الشراء (M10) — بخطوة واحدة: كلمة مرور فقط، فالاسم
 * والجوال والبريد معروفة من الطلب. يربط هذا الطلب وحده، ويرسل كود تأكيد البريد.
 *
 * ══ الأمان ══
 * المسار موقّع (signed) فحيازته إثبات المرور بمسار الشراء. ومع ذلك، لأن الرابط
 * دائم وقابل للتسريب، نضيف حُرّاسًا:
 *  1) الطلب غير مربوط بحساب بعد (customer_id فارغ).
 *  2) لا حساب قائم بهذا الجوال — إن وُجد، نوجّه للدخول لا لإنشاء حساب فوقه (منع
 *     الاستيلاء على حساب قائم).
 *  3) البريد إلزامي (قناة تأكيد الحساب): من الطلب إن وُجد، وإلا من النموذج.
 *  4) تأكيد البريد بكود لاحقًا يثبت ملكيته فعليًا.
 */
final class PostPurchaseAccountController extends Controller
{
    private const GUARD = 'customer';

    public function store(
        PostPurchaseAccountRequest $request,
        Order $order,
        VerificationCodeService $codes,
    ): RedirectResponse {
        // مسجّلة الدخول أصلًا، أو الطلب مربوط: لا حاجة لإنشاء حساب.
        if (Auth::guard(self::GUARD)->check() || $order->customer_id !== null) {
            return $this->backToThankYou($order);
        }

        $phoneNormalized = PhoneNormalizer::normalize((string) $order->customer_phone);

        // جوال الطلب غير مصري/غير صالح: لا يصلح مفتاح هوية — نكتفي بالضيف.
        if ($phoneNormalized === null) {
            return $this->backToThankYou($order)
                ->with('warning', __('account.post_purchase.phone_unsupported'));
        }

        // حساب قائم بهذا الجوال: لا نُنشئ فوقه — نوجّه للدخول (منع الاستيلاء).
        if (Customer::where('phone_normalized', $phoneNormalized)->withTrashed()->exists()) {
            return redirect()
                ->route('customer.login.show')
                ->with('status', __('account.post_purchase.already_registered'));
        }

        // البريد: من الطلب إن وُجد (لا يُقبل من النموذج حينها)، وإلا من النموذج.
        $email = filled($order->customer_email)
            ? (string) $order->customer_email
            : (string) $request->validated('email');

        $customer = DB::transaction(function () use ($order, $request, $phoneNormalized, $email): Customer {
            $customer = new Customer([
                'name' => (string) $order->customer_name,
                'email' => $email,
                'password' => Hash::make((string) $request->validated('password')),
            ]);

            // أعمدة الهوية والعنوان خارج $fillable (يحكمها الخادم) — forceFill.
            // كل البيانات تُؤخذ من الطلب (M10): الاسم، الجوال، البريد، والعنوان
            // الافتراضي — فلا تُعيد العميلة إدخال شيء سوى كلمة المرور.
            $customer->forceFill([
                'phone_normalized' => $phoneNormalized,
                'phone_e164' => PhoneNormalizer::toE164((string) $order->customer_phone),
                'last_governorate' => $order->governorate,
                'last_city' => $order->city,
                'last_address_line' => $order->address_line,
                'last_country_code' => $order->country_code,
                'is_claimed' => true,
            ])->save();

            // ربط هذا الطلب وحده (تحديث ذرّي شرطي: ما دام غير مربوط).
            Order::query()
                ->whereKey($order->getKey())
                ->whereNull('customer_id')
                ->update(['customer_id' => $customer->getKey()]);

            return $customer;
        });

        Auth::guard(self::GUARD)->login($customer);
        $request->session()->regenerate();

        Log::info('customer.post_purchase_account', [
            'customer_id' => $customer->id,
            'order_id' => $order->id,
            'ip' => $request->ip(),
        ]);

        // كود تأكيد البريد (M9). فشل الإرسال لا يُسقِط إنشاء الحساب.
        $codes->issueAndSend($email, 'email_verification');

        return redirect()
            ->route('customer.verify.show')
            ->with('status', __('account.post_purchase.created'));
    }

    private function backToThankYou(Order $order): RedirectResponse
    {
        return redirect()->to(URL::signedRoute('orders.thankyou', ['order' => $order->id]));
    }
}
