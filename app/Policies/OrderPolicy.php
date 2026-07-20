<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Customer;
use App\Models\Order;

/**
 * تفويض خادمي لطلبات العميلة (الدستور 4.4 / الممنوع 11.13): القرار يُتخذ هنا عند
 * كل نقطة فعل، ولا يُعتمد إطلاقًا على إخفاء رابط أو زر في الواجهة.
 *
 * ‏لماذا صنف عادي يُستدعى مباشرةً بدل Gate/Policy/authorize()؟ ليس تفضيلًا أسلوبيًا:
 * ‏Gate مكسور بنيويًا مع أي موديل غير App\Models\User في هذا المستودع، وهذا مُثبت
 * بالتشغيل الفعلي لا بالقياس (الدستور 1.3):
 *
 *   • ‏Gate::forUser($customer)->allows(...) و $customer->can(...) يرميان TypeError
 *     (‏خطأ 500) من AppServiceProvider.php:66 — المغلقة هناك
 *     ‏Gate::before(static fn (User $user): ?bool => ...) مقيّدة بالنوع App\Models\User،
 *     ‏و Gate::callBeforeCallbacks تناديها بلا أي فحص نوع (تحقّقت من مصدر الإطار).
 *   • ‏$this->authorize(...) يحلّ المستخدم من الحارس **الافتراضي** (web) لا من حارس
 *     customer، فيراه ضيفًا ويرفض — أي أن **صاحبة الطلب نفسها** كانت ستُمنع 403.
 *   • ‏زيادةً: App\Http\Controllers\Controller مجرّد ولا يستعمل AuthorizesRequests،
 *     ‏فالدالة $this->authorize() غير موجودة أصلًا في متحكمات هذا المشروع.
 *
 * ‏إصلاح ذلك يتطلب تعديل AppServiceProvider — خارج ملكية هذا الوكيل، ومُصعَّد للمنسّق.
 * ‏لذلك تُستدعى هذه السياسة مباشرةً من المتحكم، وهي أوضح وأسهل اختبارًا بالنتيجة.
 *
 * ‏ملاحظة للمنسّق: **يُمنع** تسجيل هذا الصنف في خريطة سياسات Gate — التسجيل يعيد
 * ‏المسار المكسور أعلاه إلى الحياة عبر can/@can في القوالب.
 */
final class OrderPolicy
{
    /**
     * هل تملك هذه العميلة حق عرض هذا الطلب؟
     *
     * ‏الملكية تُقرأ من orders.customer_id حصريًا. **يُمنع** إسناد الملكية بمطابقة
     * ‏رقم الجوال: أي شخص يستطيع تقديم طلب برقم غيره، فمطابقة الرقم ليست إثبات ملكية.
     */
    public function view(Customer $customer, Order $order): bool
    {
        $customerId = $customer->getKey();
        $ownerId = $order->getAttribute('customer_id');

        // ‏حرج: طلب ضيف غير مربوط (customer_id = null) أو موديل عميلة غير محفوظ
        // ‏(getKey() = null). بدون هذا الحارس تمرّ المقارنة null === null فتنفتح
        // ‏كل طلبات الضيوف لأي كائن عميلة غير محفوظ. نرفض صراحةً قبل أي مقارنة.
        if ($customerId === null || $ownerId === null) {
            return false;
        }

        // ‏الطلبات المحذوفة ناعمًا لا تُعرض. ربط المسار يستبعدها أصلًا (Order يستعمل
        // ‏SoftDeletes)، وهذا تحصين ثانٍ لو استُدعيت السياسة بطلب حُمّل بـ withTrashed.
        if ($order->trashed()) {
            return false;
        }

        // ‏مقارنة صارمة بعد التوحيد نصيًا: المفاتيح قد تعود int أو string حسب سائق
        // ‏قاعدة البيانات، و === على نوعين مختلفين كان سيرفض المالكة الحقيقية.
        return (string) $ownerId === (string) $customerId;
    }
}
