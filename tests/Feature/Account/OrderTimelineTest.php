<?php

declare(strict_types=1);

namespace Tests\Feature\Account;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use Database\Factories\OrderFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * خط رحلة الطلب (M12) — قلب صفحة الطلب. يعرض المعالم الخمسة بحالة (مكتمل/نشط/قادم)
 * مشتقّة من حالة الطلب، بأوقاتٍ حقيقية من سجل التاريخ فقط — لا وقت مُختلق لخطوة لم
 * تقع، ولا خطوات سعيدة لاحقة حين يتوقّف الطلب (ملغى/مرفوض/مُسترد).
 *
 * HONESTY (1.3/1.5): تعمل عبر php artisan test (MariaDB محليًا).
 */
final class OrderTimelineTest extends TestCase
{
    use RefreshDatabase;

    private function order(Customer $customer, string $status): Order
    {
        return OrderFactory::new()->create([
            'customer_id' => $customer->id,
            'status' => $status,
        ]);
    }

    public function test_the_timeline_marks_the_current_status_active_and_later_steps_upcoming(): void
    {
        $customer = Customer::factory()->create();
        $order = $this->order($customer, 'shipped');

        // انتقالات حقيقية سُجّلت في الطريق إلى «شُحن».
        foreach (['confirmed', 'processing', 'shipped'] as $to) {
            OrderStatusHistory::create([
                'order_id' => $order->id, 'from_status' => 'pending',
                'to_status' => $to, 'source' => 'admin',
            ]);
        }

        $response = $this->actingAs($customer, 'customer')->get(route('customer.orders.show', ['order' => $order->id]));

        $response->assertOk();
        $response->assertSee('acc-tl', false);                              // الخط الزمني مرسوم
        $response->assertSee('رحلة طلبك', false);
        // المعلم النشط (shipped) قبل معلم قادم (delivered) في ترتيب DOM.
        $response->assertSeeInOrder(['طلبك في الطريق إليك', 'وصل إلى بيتك'], false);
        // «وصل» خطوة قادمة: تظهر بصنف upcoming لا active.
        $this->assertMatchesRegularExpression(
            '/class="upcoming"[^>]*>\s*<span[^>]*>🎉/u',
            $response->getContent(),
        );
    }

    public function test_a_delivered_order_marks_the_final_step_done_not_active(): void
    {
        $customer = Customer::factory()->create();
        $order = $this->order($customer, 'delivered');
        OrderStatusHistory::create([
            'order_id' => $order->id, 'from_status' => 'shipped',
            'to_status' => 'delivered', 'source' => 'admin',
        ]);

        $response = $this->actingAs($customer, 'customer')->get(route('customer.orders.show', ['order' => $order->id]));

        $response->assertOk();
        // طلبٌ منتهٍ لا يبدو جاريًا: لا عقدة «نشطة» تنبض في خطّه الزمني.
        $this->assertStringNotContainsString('aria-current="step"', $response->getContent());
        // ونصّ الحالة لقارئ الشاشة موجود (المعلومة ليست باللون وحده — WCAG 1.4.1).
        $response->assertSee('تمّت', false);
    }

    public function test_a_cancelled_order_shows_a_negative_terminal_and_no_future_happy_steps(): void
    {
        $customer = Customer::factory()->create();
        $order = $this->order($customer, 'cancelled');
        OrderStatusHistory::create([
            'order_id' => $order->id, 'from_status' => 'pending',
            'to_status' => 'cancelled', 'source' => 'admin',
        ]);

        $response = $this->actingAs($customer, 'customer')->get(route('customer.orders.show', ['order' => $order->id]));

        $response->assertOk();
        $response->assertSee('acc-tl', false);
        $response->assertSee('ملغى', false);                     // الحالة النهائية السلبية
        $response->assertDontSee('وصل إلى بيتك', false);         // بلا نهاية سعيدة وهمية
        $response->assertDontSee('طلبك في الطريق إليك', false);  // ولا خطوات مسار سعيد لاحقة
    }

    public function test_upcoming_steps_carry_no_fabricated_time(): void
    {
        $customer = Customer::factory()->create();
        // طلب جديد (pending): لا انتقالات بعد، فكل ما بعد الاستلام «قادم» بلا وقت.
        $order = $this->order($customer, 'pending');

        $response = $this->actingAs($customer, 'customer')->get(route('customer.orders.show', ['order' => $order->id]));

        $response->assertOk();
        // عنصر الوقت المرسوم يظهر مرة واحدة فقط: للاستلام (created_at). لا وقت للقادم.
        // (نعدّ class="tl-time" لا "tl-time" كي لا يُحسب محدِّد CSS في نمط الصفحة.)
        $this->assertSame(1, substr_count($response->getContent(), 'class="tl-time"'));
    }
}
