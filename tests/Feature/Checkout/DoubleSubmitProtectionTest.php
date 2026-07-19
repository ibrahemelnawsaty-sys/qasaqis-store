<?php

declare(strict_types=1);

namespace Tests\Feature\Checkout;

use App\Models\Book;
use App\Models\Order;
use Database\Seeders\PaymentMethodSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * منع تكرار الطلب عند النقر المزدوج (M7 — رحلة العميل، المرحلة 5).
 *
 * الخلفية: زر «تأكيد الطلب» كان عنصر submit عاديًا بلا أي حماية. على الشبكات
 * المصرية البطيئة لا يستجيب الزر فورًا فتضغطه الأم مرة ثانية — فيُنشأ طلبان
 * ويُخصم المخزون مرتين.
 *
 * الحماية طبقتان: مفتاح في الجلسة (لا في حقل مخفي — بند 4.1) يُقارَن قبل الكتابة،
 * وفهرس فريد على orders.idempotency_key يلتقط السباق الحقيقي بين طلبين متزامنين.
 *
 * HONESTY (1.3/1.5): لم تُشغَّل هنا (لا MySQL محليًا)؛ تعمل عبر `php artisan test`.
 * مسار الـ catch (تصادم الفهرس الفريد) غير مغطّى هنا لأنه يتطلب محاكاة تزامن
 * حقيقي بين طلبين — يحميه القيد في قاعدة البيانات لا هذا الاختبار.
 */
final class DoubleSubmitProtectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PaymentMethodSeeder::class);
    }

    private function book(): Book
    {
        return Book::factory()->create([
            'price' => '200.00',
            'stock_status' => 'in_stock',
            'stock_quantity' => 10,
            'manage_stock' => true,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Book $book): array
    {
        return [
            'name' => 'أم أحمد',
            'phone' => '01012345678',
            'country_code' => 'EG',
            'governorate' => 'القاهرة',
            'address' => 'شارع التجربة رقم 5',
            'payment_method' => 'instapay',
            'items' => [['book_id' => $book->id, 'qty' => 1]],
        ];
    }

    /** يحاكي وصول العميلة لصفحة الدفع: سلة جلسة ثم عرض الصفحة (يُصدر المفتاح). */
    private function visitCheckout(Book $book): void
    {
        $this->withSession(['cart' => [$book->id => 1]])
            ->get(route('checkout.show'))
            ->assertOk();
    }

    public function test_checkout_page_issues_an_idempotency_key(): void
    {
        $book = $this->book();

        $this->visitCheckout($book);

        $this->assertNotNull(session('checkout.idempotency_key'));
    }

    public function test_double_submit_creates_only_one_order(): void
    {
        $book = $this->book();
        $this->visitCheckout($book);

        $this->post(route('checkout.place'), $this->payload($book))->assertStatus(302);
        $this->post(route('checkout.place'), $this->payload($book))->assertStatus(302);

        // الطلب الثاني رُدّ بنتيجة الأول، لا طلب جديد.
        $this->assertSame(1, Order::count());
    }

    public function test_double_submit_does_not_decrement_stock_twice(): void
    {
        $book = $this->book();
        $this->visitCheckout($book);

        $this->post(route('checkout.place'), $this->payload($book));
        $this->post(route('checkout.place'), $this->payload($book));

        // 10 − 1 = 9 وليس 8: أخطر أثر للتكرار هو خصم مخزون لم يُبع.
        $this->assertSame(9, $book->fresh()->stock_quantity);
    }

    public function test_replayed_submit_redirects_to_the_same_order(): void
    {
        $book = $this->book();
        $this->visitCheckout($book);

        $this->post(route('checkout.place'), $this->payload($book));
        $order = Order::firstOrFail();

        $second = $this->post(route('checkout.place'), $this->payload($book));

        // العميلة لا ترى شاشة خطأ — تصل لصفحة طلبها نفسه.
        $second->assertRedirectContains('/orders/'.$order->id.'/');
    }

    public function test_order_stores_the_session_idempotency_key(): void
    {
        $book = $this->book();
        $this->visitCheckout($book);
        $key = session('checkout.idempotency_key');

        $this->post(route('checkout.place'), $this->payload($book));

        $this->assertSame($key, Order::firstOrFail()->idempotency_key);
    }

    public function test_a_new_checkout_visit_allows_a_genuine_second_order(): void
    {
        $book = $this->book();

        $this->visitCheckout($book);
        $this->post(route('checkout.place'), $this->payload($book));

        // العميلة تشتري ثانيةً: تمرّ بصفحة الدفع من جديد ⇒ مفتاح جديد ⇒ طلب جديد.
        $this->visitCheckout($book);
        $this->post(route('checkout.place'), $this->payload($book));

        $this->assertSame(2, Order::count());
    }

    public function test_submit_without_a_session_key_still_places_the_order(): void
    {
        $book = $this->book();

        // إرسال لم يمرّ بصفحة الدفع (جلسة انتهت مثلًا): بلا حماية من التكرار،
        // لكن يجب ألا يُرفض الطلب — سلوك ما قبل هذه الميزة يبقى سليمًا.
        $this->post(route('checkout.place'), $this->payload($book))->assertStatus(302);

        $this->assertSame(1, Order::count());
        $this->assertNull(Order::firstOrFail()->idempotency_key);
    }

    public function test_a_different_payment_method_is_not_treated_as_a_replay(): void
    {
        $book = $this->book();
        $this->visitCheckout($book);

        // تبويبان مفتوحان يتقاسمان مفتاح الجلسة نفسه، لكن بطريقتَي دفع مختلفتين.
        $this->post(route('checkout.place'), $this->payload($book));
        $this->post(route('checkout.place'), array_merge($this->payload($book), [
            'payment_method' => 'cod',
        ]));

        // طلبان: لولا حارس طريقة الدفع لرُدّت العميلة التي اختارت «الدفع عند
        // الاستلام» إلى طلب إنستاباي يطالبها بتحويل مال إلى محفظة.
        $this->assertSame(2, Order::count());
        $this->assertSame(
            ['instapay', 'cod'],
            Order::orderBy('id')->pluck('payment_method')->all()
        );
    }

    public function test_replay_of_a_soft_deleted_order_does_not_explode(): void
    {
        $book = $this->book();
        $this->visitCheckout($book);

        $this->post(route('checkout.place'), $this->payload($book));
        Order::firstOrFail()->delete(); // حذف ناعم من لوحة الأدمن.

        // الفهرس الفريد يشمل المحذوف ناعمًا؛ بلا withTrashed كان INSERT ينفجر
        // بخطأ 1062 فتصل العميلة إلى صفحة 500 بدل صفحة طلبها.
        $this->post(route('checkout.place'), $this->payload($book))->assertStatus(302);

        $this->assertSame(0, Order::count());
        $this->assertSame(1, Order::withTrashed()->count());
    }

    public function test_successful_placement_flashes_the_cart_clear_signal(): void
    {
        $book = $this->book();
        $this->visitCheckout($book);

        $this->post(route('checkout.place'), $this->payload($book))
            ->assertSessionHas('cart_placed', true);
    }
}
