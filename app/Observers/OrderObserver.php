<?php

declare(strict_types=1);

namespace App\Observers;

use App\Actions\Order\RestoreOrderStockAction;
use App\Models\Order;

/**
 * مُراقب الطلب. يُعيد المخزون عند الانتقال إلى حالة نهائية غير منفّذة —
 * يلتقط تلقائيًا التغيير اليدوي (ViewOrder) والأمر المجدول معًا بنقطة واحدة.
 *
 * قيد معروف (mass-update): يعتمد على حدث updated لنموذج Eloquent، فأي مسار
 * مستقبلي يستخدم Order::where(...)->update(['status'=>...]) لن يُطلق الاسترجاع.
 * كل مسارات M2 الحالية تمرّ بـ save()، ولا bulkActions على OrderResource.
 *
 * ملاحظة (M6): سيُوسَّع بحدث الشراء الخادمي عند تغيّر payment_status إلى paid —
 * حارسان منفصلان (status مقابل payment_status) لا يعيد أيّهما إطلاق الآخر.
 */
class OrderObserver
{
    /** حالات نهائية غير منفّذة يُعاد عندها المخزون (enum orders.status). */
    private const RESTOCK_STATUSES = ['cancelled', 'refused', 'refunded'];

    /** حالات تعني أن البضاعة غادرت المخزن فعلًا. */
    private const FULFILLED_STATUSES = ['shipped', 'delivered', 'completed'];

    public function updated(Order $order): void
    {
        // المفتاح على status لا payment_status: رفض الإثبات يضبط
        // payment_status=failed ويُبقي status=pending (طلب حيّ) — يجب ألا يُسترجع.
        if (! $order->wasChanged('status')
            || ! in_array($order->status, self::RESTOCK_STATUSES, true)) {
            return;
        }

        // لا تسترجع تلقائيًا مخزون طلب غادرت بضاعته المخزن (شُحن/سُلّم): المرتجعات
        // الفعلية يعالجها الأدمن يدويًا عند استلام البضاعة، فلا يتضخّم المخزون عند
        // shipped/delivered/completed → refunded. getOriginal يعطي الحالة السابقة
        // قبل هذا الحفظ، وtracking_number يكشف الشحن حتى لو لم تُضبط الحالة.
        $previous = (string) $order->getOriginal('status');

        if (in_array($previous, self::FULFILLED_STATUSES, true) || filled($order->tracking_number)) {
            return;
        }

        app(RestoreOrderStockAction::class)->execute($order);
    }
}
