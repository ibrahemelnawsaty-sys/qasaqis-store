<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Models\Order;
use App\Notifications\AdminOrderNotification;
use App\Notifications\CustomerOrderNotification;
use App\Support\Notifications\AdminRecipients;
use Illuminate\Support\Facades\Notification;

/**
 * نقطة موحّدة لإرسال إشعارات دورة حياة الطلب (M4). تُستدعى من نقاط الأحداث بعد
 * نجاح المعاملة (بعد الـ commit) كي لا يُرسَل بريد عن طلب مُرجَع. تُبقي المتحكمات
 * نحيفة (سطر استدعاء واحد). كل الإشعارات ShouldQueue.
 */
class OrderNotifier
{
    public function orderPlaced(Order $order): void
    {
        $this->toCustomer($order, CustomerOrderNotification::PLACED);
        $this->toAdmins($order, AdminOrderNotification::NEW_ORDER);
    }

    /**
     * $notifyCustomer=false للرفع المتكرّر: مسار الرفع يسمح بستّ محاولات في الدقيقة،
     * ورفع صورة ثقيلة على شبكة بطيئة هو بالضبط ما يدفع لتكرار النقر — فبلا حارس
     * تصل العميلة ستُّ رسائل متطابقة تقول لها «لا حاجة لإرسال الإثبات مرة أخرى».
     * تنبيه الأدمن يبقى في كل مرة: كل إثبات جديد يحتاج مراجعة فعلية.
     */
    public function paymentProofSubmitted(Order $order, bool $notifyCustomer = true): void
    {
        // إيصال للعميلة (M7): كانت تحوّل المال وترفع الصورة ثم لا يصلها شيء، فتبقى
        // في أعلى نقطة قلق في المسار كله وتتصل بالدعم أو تتراجع عن الشراء.
        if ($notifyCustomer) {
            $this->toCustomer($order, CustomerOrderNotification::PROOF_RECEIVED);
        }

        $this->toAdmins($order, AdminOrderNotification::PROOF_SUBMITTED);
    }

    public function paymentApproved(Order $order): void
    {
        $this->toCustomer($order, CustomerOrderNotification::APPROVED);
    }

    public function paymentRejected(Order $order, ?string $reason = null): void
    {
        $this->toCustomer($order, CustomerOrderNotification::REJECTED, $reason);
    }

    public function orderShipped(Order $order): void
    {
        $this->toCustomer($order, CustomerOrderNotification::SHIPPED);
    }

    private function toCustomer(Order $order, string $kind, ?string $reason = null): void
    {
        // ضيف بلا بريد — تخطٍّ آمن (يبقى تنبيه الأدمن).
        if (blank($order->customer_email)) {
            return;
        }

        Notification::route('mail', $order->customer_email)
            ->notify(new CustomerOrderNotification($order, $kind, $reason));
    }

    private function toAdmins(Order $order, string $kind): void
    {
        $notification = new AdminOrderNotification($order, $kind);
        $admins = AdminRecipients::forOrders();

        if ($admins->isNotEmpty()) {
            Notification::send($admins, $notification);

            return;
        }

        // احتياطي: لا يوجد مستخدم بصلاحية orders.view نشط — أرسل لبريد احتياطي.
        $fallback = config('orders.admin_fallback_email');

        if (filled($fallback)) {
            Notification::route('mail', $fallback)->notify($notification);
        }
    }
}
