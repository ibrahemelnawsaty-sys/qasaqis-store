<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Order;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * يُلغي الطلبات المهجورة التي تجاوزت المهلة (config payment.pending_expiry_hours)
 * ويحرّر مخزونها، فلا يبقى مخزون محجوزًا للأبد لطلب لن يُدفع. يُجدوَل في
 * bootstrap/app.php ويُعيد المحاولة على تعارض القفل. آمن لإعادة التشغيل.
 *
 * مساران يحجزان المخزون (assertStockAndReserve يحجز لكل الطرق) ويُهجَران:
 *  1) online_gateway/unpaid — لم يُكمل الدفع الأونلاين (معطّل حاليًا لكن مُغطّى).
 *  2) تحويل يدوي (instapay/vodafone_cash/bank_transfer)/pending_review بلا أي
 *     إثبات مرفوع — العميل اختار التحويل ثم لم يرفع إثباتًا. الطلبات التي رُفع
 *     لها إثبات (قيد مراجعة الأدمن) أو رُفض إثباتها (payment_status=failed) لا
 *     تُمَسّ — قرارها للأدمن. فحص «بلا إثبات» يجري تحت القفل في cancel().
 *
 * تحرير المخزون يتم عبر OrderObserver الذي يُطلقه تغيير الحالة إلى cancelled —
 * نفس مسار الإلغاء اليدوي، بلا تكرار للمنطق.
 */
class CancelExpiredPendingOrders extends Command
{
    protected $signature = 'orders:cancel-expired';

    protected $description = 'إلغاء الطلبات المهجورة (أونلاين غير مدفوع أو تحويل يدوي بلا إثبات) بعد المهلة وتحرير مخزونها';

    /** طرق الدفع اليدوي التي تُنشئ طلبًا بانتظار إثبات. */
    private const MANUAL_METHODS = ['instapay', 'vodafone_cash', 'bank_transfer'];

    public function handle(): int
    {
        $hours = (int) config('payment.pending_expiry_hours', 48);
        $cutoff = now()->subHours($hours);
        $cancelled = 0;

        // مرشّحون: طلبات pending قديمة أونلاين-غير-مدفوعة أو تحويل-يدوي بانتظار
        // إثبات. فحص «بلا إثبات» لليدوي يجري تحت القفل في cancel() (تفادي سباق).
        Order::query()
            ->where('status', 'pending')
            ->where('created_at', '<=', $cutoff)
            ->where(function ($query): void {
                $query->where(function ($online): void {
                    $online->where('payment_method', 'online_gateway')
                        ->where('payment_status', 'unpaid');
                })->orWhere(function ($manual): void {
                    $manual->whereIn('payment_method', self::MANUAL_METHODS)
                        ->where('payment_status', 'pending_review');
                });
            })
            ->orderBy('id')
            ->chunkById(100, function ($orders) use (&$cancelled): void {
                foreach ($orders as $order) {
                    if ($this->cancel((int) $order->id)) {
                        $cancelled++;
                    }
                }
            });

        $this->info("ألغيت {$cancelled} طلبًا منتهي المهلة.");
        Log::info('orders.cancel_expired', ['count' => $cancelled, 'hours' => $hours]);

        return self::SUCCESS;
    }

    /**
     * إعادة تحقق تحت القفل ثم الإلغاء. يُعيد المحاولة 3 مرات على تعارض القفل
     * (deadlock) كي لا يُجهض deadlock واحد بقية الدفعة.
     */
    private function cancel(int $orderId): bool
    {
        return (bool) DB::transaction(function () use ($orderId): bool {
            $order = Order::query()->whereKey($orderId)->lockForUpdate()->first();

            if ($order === null || $order->status !== 'pending') {
                return false;
            }

            $isAbandonedOnline = $order->payment_method === 'online_gateway'
                && $order->payment_status === 'unpaid';

            $isAbandonedManual = in_array($order->payment_method, self::MANUAL_METHODS, true)
                && $order->payment_status === 'pending_review'
                && ! $order->paymentProofs()->exists();

            // إعادة التحقق تحت القفل: تفادي سباق مع دفع/رفع إثبات متأخر.
            if (! $isAbandonedOnline && ! $isAbandonedManual) {
                return false;
            }

            // تغيير الحالة يُطلق OrderObserver الذي يُعيد المخزون (نفس المسار).
            $order->forceFill([
                'status' => 'cancelled',
                'payment_status' => 'failed',
            ])->save();

            // إفشال صفوف الدفع المعلّقة (pending للأونلاين، pending_review لليدوي).
            $order->payments()
                ->whereIn('status', ['pending', 'pending_review'])
                ->update(['status' => 'failed']);

            return true;
        }, 3);
    }
}
