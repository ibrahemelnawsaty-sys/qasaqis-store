<?php

declare(strict_types=1);

namespace App\Observers;

use App\Actions\Order\RestoreOrderStockAction;
use App\Filament\Pages\OpsDashboard;
use App\Jobs\SendPurchaseServerEvent;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Services\Finance\FinanceReportService;
use Illuminate\Support\Facades\Auth;

/**
 * مُراقب الطلب — نقطة واحدة، ثلاثة حُرّاس مستقلة:
 *  (M2) استرجاع المخزون عند تغيّر status إلى حالة نهائية غير منفّذة.
 *  (M6) إطلاق حدث الشراء الخادمي عند تغيّر payment_status إلى paid.
 *  (M8) تسجيل تاريخ الحالة عند تغيّر status — يغطّي كل المسارات (لوحة، نظام،
 *       الإلغاء التلقائي المجدول) لا صفحة Filament وحدها.
 * الحُرّاس لا يعيد أيّها إطلاق الآخر (كل يفحص عموده).
 *
 * قيد معروف (mass-update): يعتمد على حدث updated، فأي Order::where(...)->update
 * مستقبلي لن يُطلقها. كل المسارات الحالية تمرّ بـ save()، ولا bulkActions.
 */
class OrderObserver
{
    /** حالات نهائية غير منفّذة يُعاد عندها المخزون (enum orders.status). */
    private const RESTOCK_STATUSES = ['cancelled', 'refused', 'refunded'];

    /** حالات تعني أن البضاعة غادرت المخزن فعلًا. */
    private const FULFILLED_STATUSES = ['shipped', 'delivered', 'completed'];

    public function updated(Order $order): void
    {
        $this->maybeRecordStatusHistory($order);
        $this->maybeRestoreStock($order);
        $this->maybeDispatchPurchaseEvent($order);
    }

    /**
     * إبطال كاش القسم المالي **ولوحة العمليات** عند أي إنشاء/تعديل طلب — saved
     * يغطّي الحالتين. بدونه يظل الطلب الجديد أو تغيّر الحالة (كإرجاع طلب) غير مرئي
     * في اللوحة حتى انتهاء عمر الكاش (5 دقائق). الإبطال رفع إصدار واحد رخيص.
     */
    public function saved(Order $order): void
    {
        app(FinanceReportService::class)->flush();
        OpsDashboard::flushDashboardCache();
    }

    public function deleted(Order $order): void
    {
        app(FinanceReportService::class)->flush();
        OpsDashboard::flushDashboardCache();
    }

    /**
     * سجل انتقال الحالة (M8). الفاعل من حارس web الإداري إن وُجد (مستخدم لوحة)،
     * وإلا فالمصدر «نظام» (الإلغاء المجدول). لا يُسجَّل شيء إن لم تتغيّر الحالة.
     */
    private function maybeRecordStatusHistory(Order $order): void
    {
        if (! $order->wasChanged('status')) {
            return;
        }

        $actor = Auth::guard('web')->user();

        OrderStatusHistory::create([
            'order_id' => $order->id,
            'from_status' => $order->getOriginal('status'),
            'to_status' => $order->status,
            'actor_id' => $actor?->getKey(),
            'source' => $actor !== null ? 'admin' : 'system',
        ]);
    }

    private function maybeRestoreStock(Order $order): void
    {
        // المفتاح على status لا payment_status: رفض الإثبات يضبط
        // payment_status=failed ويُبقي status=pending (طلب حيّ) — يجب ألا يُسترجع.
        if (! $order->wasChanged('status')
            || ! in_array($order->status, self::RESTOCK_STATUSES, true)) {
            return;
        }

        // لا تسترجع تلقائيًا مخزون طلب غادرت بضاعته المخزن (شُحن/سُلّم): المرتجعات
        // الفعلية يعالجها الأدمن يدويًا عند استلام البضاعة، فلا يتضخّم المخزون عند
        // shipped/delivered/completed → refunded. getOriginal يعطي الحالة السابقة.
        $previous = (string) $order->getOriginal('status');

        if (in_array($previous, self::FULFILLED_STATUSES, true) || filled($order->tracking_number)) {
            return;
        }

        app(RestoreOrderStockAction::class)->execute($order);
    }

    private function maybeDispatchPurchaseEvent(Order $order): void
    {
        // نقطة تأكيد الشراء الوحيدة: انتقال payment_status إلى paid (قبول الإثبات
        // في ViewOrder، أو webhook مستقبلي). الإرسال الفعلي وidempotency في الـ Job.
        if (! (bool) config('analytics.enabled')) {
            return;
        }

        if ($order->wasChanged('payment_status') && $order->payment_status === 'paid') {
            SendPurchaseServerEvent::dispatch($order->id);
        }
    }
}
