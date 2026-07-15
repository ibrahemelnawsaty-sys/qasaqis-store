<?php

declare(strict_types=1);

namespace App\Actions\Order;

use App\Models\Book;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * يُعيد كميات order_items إلى مخزون الكتب عند صيرورة الطلب في حالة نهائية غير
 * منفّذة (cancelled/refused/refunded). عكس assertStockAndReserve في
 * PlaceOrderAction بالضبط: يتخطّى الكتب غير المُدارة، ويعيد «نفد» إلى «متوفّر»
 * عند تجاوز الصفر، ولا يلمس preorder.
 *
 * الذرّية والتزامن (الدستور 3.5): كل شيء داخل DB::transaction مع قفل صف الطلب
 * والكتب (lockForUpdate)، ومُطالبة حصرية على stock_restored_at IS NULL تمنع
 * الاستعادة المزدوجة عند تسابق مسارين (تغيير يدوي + الأمر المجدول). ضبط
 * stock_restored_at لا يغيّر status فلا يُعيد إطلاق OrderObserver (لا تكرار).
 */
class RestoreOrderStockAction
{
    public function execute(Order $order): void
    {
        DB::transaction(function () use ($order): void {
            $locked = Order::query()->whereKey($order->getKey())->lockForUpdate()->first();

            // مُطالبة حصرية: أول مسار يفوز، والبقية تخرج بلا أثر.
            if ($locked === null || $locked->stock_restored_at !== null) {
                return;
            }

            // ترتيب القفل بـ book_id يثبّت ترتيب اكتساب الأقفال عالميًا فيقلّل
            // احتمال deadlock بين استرجاعين متزامنين على كتب مشتركة.
            foreach ($locked->items()->orderBy('book_id')->get() as $item) {
                if ($item->book_id === null) {
                    continue; // الكتاب حُذف (nullOnDelete) — لا مخزون لاستعادته.
                }

                $book = Book::query()->whereKey($item->book_id)->lockForUpdate()->first();

                if ($book === null || ! $book->manage_stock) {
                    continue; // كتاب محذوف (soft delete) أو غير مُدار المخزون.
                }

                $newQuantity = $book->stock_quantity + $item->quantity;

                // يعود «متوفّر» فقط إن كان «نفد» وأصبح موجبًا — لا نلمس preorder.
                $newStatus = ($book->stock_status === 'out_of_stock' && $newQuantity > 0)
                    ? 'in_stock'
                    : $book->stock_status;

                // تحديث عبر Query Builder: تغيير مخزون بحت لا يمسّ العنوان/الوصف،
                // فنتجنّب إطلاق BookObserver (إعادة بناء فهرس البحث) الذي يُطيل
                // نافذة القفل بلا داعٍ. الصف مقفول أصلًا بـ lockForUpdate أعلاه.
                Book::query()->whereKey($book->id)->update([
                    'stock_quantity' => $newQuantity,
                    'stock_status' => $newStatus,
                ]);
            }

            $locked->forceFill(['stock_restored_at' => now()])->save();

            Log::info('orders.stock_restored', ['order_id' => $locked->id]);
        });
    }
}
