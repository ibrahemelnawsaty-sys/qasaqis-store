<?php

declare(strict_types=1);

namespace Tests\Feature\Orders;

use App\Models\Book;
use App\Models\Order;
use Database\Factories\OrderFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * الأمر المجدول orders:cancel-expired (M2): يلغي طلبات الدفع الأونلاين غير
 * المدفوعة بعد المهلة ويحرّر مخزونها، ولا يمسّ غيرها، وآمن لإعادة التشغيل.
 *
 * NOTE: Order بلا HasFactory؛ يُنشأ عبر OrderFactory::new(). created_at ليس في
 * $fillable فيُقدَّم عمره عبر تحديث Query Builder (بلا أحداث).
 * HONESTY (1.3/1.5): لم تُشغَّل هنا (لا PHP)؛ تعمل عبر `php artisan test` (MySQL).
 */
final class CancelExpiredPendingOrdersTest extends TestCase
{
    use RefreshDatabase;

    private function expiredOnlineOrder(Book $book, int $qty = 2): Order
    {
        $order = OrderFactory::new()->create([
            'payment_method' => 'online_gateway',
            'status' => 'pending',
            'payment_status' => 'unpaid',
        ]);

        $order->items()->create([
            'book_id' => $book->id,
            'book_title' => $book->title,
            'unit_price' => '100.00',
            'quantity' => $qty,
            'line_total' => '100.00',
        ]);

        $order->payments()->create([
            'payment_method_code' => 'online_gateway',
            'amount' => '200.00',
            'status' => 'pending',
        ]);

        // تقديم العمر خلف المهلة (48 ساعة) — created_at ليس fillable.
        Order::whereKey($order->id)->update(['created_at' => now()->subHours(50)]);

        return $order;
    }

    public function test_expired_unpaid_online_order_is_cancelled_and_stock_restored(): void
    {
        $book = Book::factory()->create([
            'stock_quantity' => 5,
            'stock_status' => 'in_stock',
            'manage_stock' => true,
        ]);
        $order = $this->expiredOnlineOrder($book, 2);

        $this->artisan('orders:cancel-expired')->assertSuccessful();

        $fresh = $order->fresh();
        $this->assertSame('cancelled', $fresh->status);
        $this->assertSame('failed', $fresh->payment_status);
        $this->assertNotNull($fresh->stock_restored_at);
        $this->assertSame(7, $book->fresh()->stock_quantity);
        $this->assertSame('failed', $order->payments()->first()->status);
    }

    public function test_recent_order_is_not_cancelled(): void
    {
        $order = OrderFactory::new()->create([
            'payment_method' => 'online_gateway',
            'status' => 'pending',
            'payment_status' => 'unpaid',
        ]);
        // created_at = now (داخل النافذة).

        $this->artisan('orders:cancel-expired')->assertSuccessful();

        $this->assertSame('pending', $order->fresh()->status);
    }

    public function test_abandoned_manual_transfer_without_proof_is_cancelled_and_stock_restored(): void
    {
        $book = Book::factory()->create([
            'stock_quantity' => 5,
            'stock_status' => 'in_stock',
            'manage_stock' => true,
        ]);

        $order = OrderFactory::new()->manualTransfer('instapay')->create();
        $order->items()->create([
            'book_id' => $book->id,
            'book_title' => $book->title,
            'unit_price' => '100.00',
            'quantity' => 2,
            'line_total' => '100.00',
        ]);
        Order::whereKey($order->id)->update(['created_at' => now()->subHours(50)]);

        $this->artisan('orders:cancel-expired')->assertSuccessful();

        $fresh = $order->fresh();
        $this->assertSame('cancelled', $fresh->status);
        $this->assertSame('failed', $fresh->payment_status);
        $this->assertSame(7, $book->fresh()->stock_quantity);
    }

    public function test_manual_transfer_with_uploaded_proof_is_not_cancelled(): void
    {
        // رُفع إثبات → بانتظار مراجعة الأدمن؛ لا يلمسه الأمر (قراره للأدمن).
        $order = OrderFactory::new()->manualTransfer('instapay')->create();
        $order->paymentProofs()->create([
            'method_code' => 'instapay',
            'file_path' => 'payment-proofs/1/'.str_repeat('a', 20).'.jpg',
        ]);
        Order::whereKey($order->id)->update(['created_at' => now()->subHours(50)]);

        $this->artisan('orders:cancel-expired')->assertSuccessful();

        $fresh = $order->fresh();
        $this->assertSame('pending', $fresh->status);
        $this->assertSame('pending_review', $fresh->payment_status);
    }

    public function test_paid_online_order_is_not_touched(): void
    {
        $order = OrderFactory::new()->create([
            'payment_method' => 'online_gateway',
            'status' => 'processing',
            'payment_status' => 'paid',
        ]);
        Order::whereKey($order->id)->update(['created_at' => now()->subHours(50)]);

        $this->artisan('orders:cancel-expired')->assertSuccessful();

        $this->assertSame('processing', $order->fresh()->status);
    }

    public function test_command_is_idempotent(): void
    {
        $book = Book::factory()->create([
            'stock_quantity' => 5,
            'stock_status' => 'in_stock',
            'manage_stock' => true,
        ]);
        $this->expiredOnlineOrder($book, 2);

        $this->artisan('orders:cancel-expired')->assertSuccessful();
        $this->artisan('orders:cancel-expired')->assertSuccessful();

        // استُرجع مرة واحدة فقط.
        $this->assertSame(7, $book->fresh()->stock_quantity);
    }
}
