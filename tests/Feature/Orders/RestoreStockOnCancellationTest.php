<?php

declare(strict_types=1);

namespace Tests\Feature\Orders;

use App\Actions\Order\RestoreOrderStockAction;
use App\Models\Book;
use App\Models\Order;
use Database\Factories\OrderFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * استرجاع المخزون عند الانتقال إلى حالة نهائية غير منفّذة (M2)، عكس
 * assertStockAndReserve بالضبط، مع الحالات الحدّية: كتاب غير مُدار، كتاب محذوف،
 * عودة «نفد»→«متوفّر»، الحفاظ على preorder، والحماية من الاستعادة المزدوجة.
 *
 * NOTE: Order بلا HasFactory؛ يُنشأ عبر OrderFactory::new().
 * HONESTY (1.3/1.5): لم تُشغَّل هنا (لا PHP)؛ تعمل عبر `php artisan test` (MySQL).
 */
final class RestoreStockOnCancellationTest extends TestCase
{
    use RefreshDatabase;

    private function bookWithStock(int $qty, string $status = 'in_stock', bool $manage = true): Book
    {
        return Book::factory()->create([
            'stock_quantity' => $qty,
            'stock_status' => $status,
            'manage_stock' => $manage,
        ]);
    }

    private function orderFor(Book $book, int $qty = 2, array $overrides = []): Order
    {
        $order = OrderFactory::new()->create($overrides);

        $order->items()->create([
            'book_id' => $book->id,
            'book_title' => $book->title,
            'unit_price' => '100.00',
            'quantity' => $qty,
            'line_total' => '100.00',
        ]);

        return $order;
    }

    public function test_cancelling_a_managed_order_restores_stock_and_stamps(): void
    {
        $book = $this->bookWithStock(5);
        $order = $this->orderFor($book, 3);

        $order->forceFill(['status' => 'cancelled'])->save();

        $this->assertSame(8, $book->fresh()->stock_quantity);
        $this->assertNotNull($order->fresh()->stock_restored_at);
    }

    public function test_refused_and_refunded_also_restore(): void
    {
        foreach (['refused', 'refunded'] as $status) {
            $book = $this->bookWithStock(4);
            $order = $this->orderFor($book, 2);

            $order->forceFill(['status' => $status])->save();

            $this->assertSame(6, $book->fresh()->stock_quantity, "status={$status}");
        }
    }

    public function test_unmanaged_book_stock_is_not_touched(): void
    {
        $book = $this->bookWithStock(5, manage: false);
        $order = $this->orderFor($book, 3);

        $order->forceFill(['status' => 'cancelled'])->save();

        $this->assertSame(5, $book->fresh()->stock_quantity);
    }

    public function test_out_of_stock_book_returns_to_in_stock_when_positive(): void
    {
        $book = $this->bookWithStock(0, 'out_of_stock');
        $order = $this->orderFor($book, 3);

        $order->forceFill(['status' => 'cancelled'])->save();

        $fresh = $book->fresh();
        $this->assertSame(3, $fresh->stock_quantity);
        $this->assertSame('in_stock', $fresh->stock_status);
    }

    public function test_preorder_status_is_preserved(): void
    {
        $book = $this->bookWithStock(0, 'preorder');
        $order = $this->orderFor($book, 2);

        $order->forceFill(['status' => 'cancelled'])->save();

        $fresh = $book->fresh();
        $this->assertSame(2, $fresh->stock_quantity);
        $this->assertSame('preorder', $fresh->stock_status);
    }

    public function test_null_book_id_line_is_skipped_without_error(): void
    {
        $book = $this->bookWithStock(5);
        $order = $this->orderFor($book, 2);

        // سطر تاريخي بلا كتاب (nullOnDelete).
        $order->items()->update(['book_id' => null]);

        $order->forceFill(['status' => 'cancelled'])->save();

        $this->assertNotNull($order->fresh()->stock_restored_at);
    }

    public function test_double_restore_is_prevented(): void
    {
        $book = $this->bookWithStock(5);
        $order = $this->orderFor($book, 3);

        $order->forceFill(['status' => 'cancelled'])->save();
        $this->assertSame(8, $book->fresh()->stock_quantity);

        // استدعاء صريح ثانٍ: لا أثر (حارس stock_restored_at).
        app(RestoreOrderStockAction::class)->execute($order->fresh());
        $this->assertSame(8, $book->fresh()->stock_quantity);
    }

    public function test_payment_failure_without_status_change_does_not_restore(): void
    {
        // يحاكي رفض الإثبات: payment_status=failed مع بقاء status=pending (طلب حيّ).
        $book = $this->bookWithStock(5);
        $order = $this->orderFor($book, 3, ['status' => 'pending']);

        $order->forceFill(['payment_status' => 'failed'])->save();

        $this->assertSame(5, $book->fresh()->stock_quantity);
        $this->assertNull($order->fresh()->stock_restored_at);
    }

    public function test_non_final_status_change_does_not_restore(): void
    {
        $book = $this->bookWithStock(5);
        $order = $this->orderFor($book, 3, ['status' => 'pending']);

        $order->forceFill(['status' => 'processing'])->save();

        $this->assertSame(5, $book->fresh()->stock_quantity);
        $this->assertNull($order->fresh()->stock_restored_at);
    }

    public function test_shipped_order_with_tracking_is_not_restocked(): void
    {
        // بضاعة غادرت المخزن (لها رقم تتبّع) — لا استرجاع تلقائي عند الإلغاء.
        $book = $this->bookWithStock(5);
        $order = $this->orderFor($book, 3, ['tracking_number' => 'TRK-123']);

        $order->forceFill(['status' => 'cancelled'])->save();

        $this->assertSame(5, $book->fresh()->stock_quantity);
        $this->assertNull($order->fresh()->stock_restored_at);
    }

    public function test_previously_fulfilled_order_is_not_restocked_on_refund(): void
    {
        // shipped → refunded: البضاعة غادرت فعلًا؛ لا يتضخّم المخزون تلقائيًا.
        $book = $this->bookWithStock(5);
        $order = $this->orderFor($book, 3, ['status' => 'shipped']);

        $order->forceFill(['status' => 'refunded'])->save();

        $this->assertSame(5, $book->fresh()->stock_quantity);
        $this->assertNull($order->fresh()->stock_restored_at);
    }

    public function test_manual_transfer_admin_cancel_restores_stock(): void
    {
        // الإلغاء اليدوي لطلب تحويل (لم يُشحن) يُعيد مخزونه.
        $book = $this->bookWithStock(5);
        $order = $this->orderFor($book, 2, [
            'payment_method' => 'instapay',
            'payment_status' => 'pending_review',
        ]);

        $order->forceFill(['status' => 'cancelled'])->save();

        $this->assertSame(7, $book->fresh()->stock_quantity);
    }
}
