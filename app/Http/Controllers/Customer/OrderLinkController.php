<?php

declare(strict_types=1);

namespace App\Http\Controllers\Customer;

use App\Actions\Order\FindGuestOrderAction;
use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;

/**
 * ربط/فكّ الطلبات السابقة بحساب العميلة (M8).
 *
 * القاعدة الأمنية الملزمة (القرار المعماري): يُربط الطلب بالحساب حصريًا بإثبات
 * حيازة دليل خاص بذلك الطلب — رقم الطلب + الجوال معًا (عبر FindGuestOrderAction
 * المتحقَّق منه)، أو توقيع صفحة الشكر. **يُمنع الربط بمطابقة الجوال وحده، ويُمنع
 * الربط الجماعي، ويُمنع نقل ملكية طلب مربوط.**
 */
class OrderLinkController extends Controller
{
    /**
     * ربط طلب ضيف سابق من داخل الحساب: رقم الطلب + الجوال.
     */
    public function attach(Request $request, FindGuestOrderAction $finder): RedirectResponse
    {
        $data = $request->validate([
            'order_number' => ['required', 'string', 'max:20'],
            'phone' => ['required', 'string', 'max:20'],
        ]);

        $customer = Auth::guard('customer')->user();
        $order = $finder->execute((string) $data['order_number'], (string) $data['phone']);

        // رسالة موحّدة للفشل تمنع تعداد الطلبات (طلب غير موجود أو جوال لا يطابق أو
        // طلب مربوط بالفعل — كلها نتيجة واحدة للخارج).
        if ($order === null) {
            return back()->with('link_error', __('account.orders.link_failed'));
        }

        // تحديث ذرّي شرطي: يُربط فقط ما دام customer_id فارغًا. عدد الصفوف المتأثرة
        // هو الحكم — لا قراءة ثم كتابة (يمنع سباق ربط طلب واحد لحسابين).
        $linked = Order::query()
            ->whereKey($order->getKey())
            ->whereNull('customer_id')
            ->update(['customer_id' => $customer->getKey()]);

        if ($linked === 0) {
            // مربوط بالفعل. متماثل: إن كان مربوطًا لهذه العميلة فنجاح صامت، وإلا فشل عام.
            $alreadyMine = Order::query()
                ->whereKey($order->getKey())
                ->where('customer_id', $customer->getKey())
                ->exists();

            return back()->with(
                $alreadyMine ? 'link_success' : 'link_error',
                $alreadyMine ? __('account.orders.link_already') : __('account.orders.link_failed'),
            );
        }

        Log::info('customer.order.attached', [
            'customer_id' => $customer->getKey(),
            'order_id' => $order->getKey(),
            'ip' => $request->ip(),
        ]);

        return back()->with('link_success', __('account.orders.link_success'));
    }

    /**
     * فكّ ربط طلب: الربط الخاطئ لا يحرم صاحبته من طلبها للأبد.
     */
    public function detach(Request $request, Order $order): RedirectResponse
    {
        $customer = Auth::guard('customer')->user();

        // تحديث ذرّي شرطي: تفكّ العميلة ما تملكه هي فقط.
        $detached = Order::query()
            ->whereKey($order->getKey())
            ->where('customer_id', $customer->getKey())
            ->update(['customer_id' => null]);

        if ($detached === 0) {
            abort(404);
        }

        Log::info('customer.order.detached', [
            'customer_id' => $customer->getKey(),
            'order_id' => $order->getKey(),
            'ip' => $request->ip(),
        ]);

        return redirect()
            ->route('customer.orders.index')
            ->with('link_success', __('account.orders.detach_success'));
    }

    /**
     * تبنّي الطلب من صفحة الشكر الموقّعة بعد التسجيل/الدخول.
     *
     * الأمن طبقتان: التوقيع (حيازة رابط الشراء) + مطابقة الجوال (لأن الرابط دائم
     * وقابل للتسريب، فحيازته وحدها لا تثبت الملكية).
     */
    public function claim(Request $request, Order $order): RedirectResponse
    {
        $customer = Auth::guard('customer')->user();

        if ($customer === null) {
            // غير مسجّلة الدخول: وجّهها للتسجيل، والرابط الموقّع يبقى صالحًا للعودة.
            return redirect()->route('customer.register.show');
        }

        // مطابقة جوال العميلة بجوال الطلب (نفس منطق FindGuestOrderAction).
        $orderPhone = substr((string) preg_replace('/\D/', '', (string) $order->customer_phone), -10);
        $altPhone = substr((string) preg_replace('/\D/', '', (string) $order->customer_phone_alt), -10);
        $mine = substr((string) preg_replace('/\D/', '', (string) $customer->phone_normalized), -10);

        $phoneMatches = $mine !== '' && ($mine === $orderPhone || $mine === $altPhone);

        $linked = $phoneMatches
            ? Order::query()->whereKey($order->getKey())->whereNull('customer_id')
                ->update(['customer_id' => $customer->getKey()])
            : 0;

        if ($linked > 0) {
            Log::info('customer.order.claimed', [
                'customer_id' => $customer->getKey(),
                'order_id' => $order->getKey(),
                'ip' => $request->ip(),
            ]);
        }

        return redirect()
            ->to(URL::signedRoute('orders.thankyou', ['order' => $order->getKey()]))
            ->with('status', $linked > 0 ? __('account.orders.claim_success') : null);
    }
}
