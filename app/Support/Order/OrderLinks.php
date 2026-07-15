<?php

declare(strict_types=1);

namespace App\Support\Order;

use App\Models\Order;
use Illuminate\Support\Facades\URL;

/**
 * توليد روابط موقّتة موقّعة لطلب الضيف، موحّدة الوجهة والمهلة. تُستخدم من صفحة
 * «تتبّع الطلب» (M3) ومن بريد التأكيد لاحقًا (M4) كي لا يتكرّر منطق الوجهة.
 */
class OrderLinks
{
    /**
     * رابط موقّت للصفحة القابلة للتنفيذ في الطلب: صفحة الدفع/رفع الإثبات إن كان
     * الطلب بانتظار إثبات، وإلا صفحة الشكر/الملخّص. يطابق منطق
     * CheckoutController::redirectAfterPlacement. temporarySignedRoute (لا الدائم)
     * لأن الرابط المُعاد للاسترداد يجب أن يكون قصير العمر.
     *
     * $ttlMinutes: تخصيص المهلة للمستدعي — بريد التأكيد (M4) يمرّر مهلة أطول
     * (يُفتح بعد ساعات)؛ صفحة التتبّع (M3) تترك null فتُستخدم مهلة track القصيرة.
     *
     * ملاحظة: الطلب مرفوض الإثبات (payment_status=failed، status=pending) يُوجَّه
     * للشكر هنا؛ إعادة رفع الإثبات للعميل يوفّرها إشعار الرفض في M4 برابط مباشر.
     */
    public static function signedDestinationFor(Order $order, ?int $ttlMinutes = null): string
    {
        $minutes = $ttlMinutes ?? (int) config('orders.track_link_ttl_minutes', 60);
        $expiresAt = now()->addMinutes($minutes);

        $route = $order->payment_status === 'pending_review'
            ? 'orders.payment'
            : 'orders.thankyou';

        return URL::temporarySignedRoute($route, $expiresAt, ['order' => $order->id]);
    }

    /**
     * رابط موقّت مباشر لصفحة الدفع/رفع الإثبات بصرف النظر عن حالة الطلب. يُستخدم
     * لإشعار رفض الإثبات (M4): بعد الرفض تصبح payment_status='failed' فلا توجّهه
     * signedDestinationFor لصفحة الدفع؛ لكن OrderController::payment وقالبها
     * يعرضان نموذج الرفع بلا حارس حالة، فيعمل الرابط ويتيح إعادة الرفع.
     */
    public static function signedPaymentFor(Order $order, ?int $ttlMinutes = null): string
    {
        $minutes = $ttlMinutes ?? (int) config('orders.track_link_ttl_minutes', 60);

        return URL::temporarySignedRoute('orders.payment', now()->addMinutes($minutes), ['order' => $order->id]);
    }
}
