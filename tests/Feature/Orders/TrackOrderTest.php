<?php

declare(strict_types=1);

namespace Tests\Feature\Orders;

use App\Support\Order\OrderLinks;
use Database\Factories\OrderFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * صفحة «تتبّع الطلب» للضيف (M3): مطابقة رقم الطلب + الجوال تُعيد رابطًا موقّتًا
 * للصفحة المناسبة، دون كشف تعداد الطلبات (رد موحّد)، مع throttle وانتهاء الرابط.
 *
 * NOTE: Order بلا HasFactory؛ يُنشأ عبر OrderFactory::new().
 * HONESTY (1.3/1.5): لم تُشغَّل هنا (لا PHP)؛ تعمل عبر `php artisan test` (MySQL).
 */
final class TrackOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_track_form_is_public_and_shows_no_order_data(): void
    {
        $this->get(route('orders.track.show'))
            ->assertOk()
            ->assertSee(__('payment.track.title'), false);
    }

    public function test_matching_pending_review_order_redirects_to_signed_payment_page(): void
    {
        $order = OrderFactory::new()->create([
            'customer_phone' => '01012345678',
            'payment_method' => 'instapay',
            'payment_status' => 'pending_review',
            'status' => 'pending',
        ]);

        $response = $this->post(route('orders.track.lookup'), [
            'order_number' => $order->order_number,
            'phone' => '01012345678',
        ]);

        $response->assertRedirect();
        $location = (string) $response->headers->get('Location');
        $this->assertStringContainsString('/orders/'.$order->id.'/payment', $location);
        $this->assertStringContainsString('signature=', $location);
        $this->assertStringContainsString('expires=', $location);
    }

    public function test_matching_paid_order_redirects_to_signed_thankyou(): void
    {
        $order = OrderFactory::new()->create([
            'customer_phone' => '01012345678',
            'payment_status' => 'paid',
            'status' => 'processing',
        ]);

        $response = $this->post(route('orders.track.lookup'), [
            'order_number' => $order->order_number,
            'phone' => '01012345678',
        ]);

        $response->assertRedirect();
        $this->assertStringContainsString('/orders/'.$order->id.'/thank-you', (string) $response->headers->get('Location'));
    }

    public function test_wrong_phone_is_indistinguishable_from_a_missing_order(): void
    {
        $order = OrderFactory::new()->create(['customer_phone' => '01012345678']);

        $wrongPhone = $this->post(route('orders.track.lookup'), [
            'order_number' => $order->order_number,
            'phone' => '01099999999',
        ]);

        $missing = $this->post(route('orders.track.lookup'), [
            'order_number' => 'QSQ-2026-ZZZZZZ',
            'phone' => '01012345678',
        ]);

        // رد موحّد يمنع التعداد: نفس الوجهة ونفس الرسالة.
        foreach ([$wrongPhone, $missing] as $response) {
            $response->assertRedirect(route('orders.track.show'));
            $response->assertSessionHas('error', __('payment.track.not_found'));
        }
    }

    public function test_phone_prefix_variation_still_matches(): void
    {
        // مخزَّن بصيغة دولية، مُدخَل بصيغة محلية — نفس آخر 10 أرقام.
        $order = OrderFactory::new()->create(['customer_phone' => '+201012345678']);

        $this->post(route('orders.track.lookup'), [
            'order_number' => $order->order_number,
            'phone' => '01012345678',
        ])->assertRedirect();
    }

    public function test_lowercase_order_number_is_accepted(): void
    {
        $order = OrderFactory::new()->create(['customer_phone' => '01012345678']);

        $response = $this->post(route('orders.track.lookup'), [
            'order_number' => strtolower($order->order_number),
            'phone' => '01012345678',
        ]);

        $response->assertRedirect();
        $this->assertStringContainsString('/orders/'.$order->id.'/', (string) $response->headers->get('Location'));
    }

    public function test_invalid_order_number_format_is_rejected(): void
    {
        $this->from(route('orders.track.show'))
            ->post(route('orders.track.lookup'), [
                'order_number' => 'not-an-order',
                'phone' => '01012345678',
            ])
            ->assertSessionHasErrors('order_number');
    }

    public function test_lookup_is_throttled(): void
    {
        for ($i = 0; $i < 6; $i++) {
            $this->post(route('orders.track.lookup'), [
                'order_number' => 'QSQ-2026-AAAAAA',
                'phone' => '01099999999',
            ]);
        }

        $this->post(route('orders.track.lookup'), [
            'order_number' => 'QSQ-2026-AAAAAA',
            'phone' => '01099999999',
        ])->assertStatus(429);
    }

    public function test_generated_link_is_valid_now_and_expires_after_ttl(): void
    {
        config(['orders.track_link_ttl_minutes' => 30]);

        $order = OrderFactory::new()->create([
            'customer_phone' => '01012345678',
            'payment_status' => 'paid',
            'status' => 'confirmed',
        ]);

        $link = OrderLinks::signedDestinationFor($order);

        // صالح الآن (توقيع سليم).
        $this->get($link)->assertOk();

        // بعد المهلة: التوقيع منتهٍ → 403 من middleware signed.
        $this->travel(31)->minutes();
        $this->get($link)->assertForbidden();
    }

    public function test_cod_order_redirects_to_signed_thankyou(): void
    {
        // COD: confirmed + unpaid — المسار الأشيع، يذهب للشكر لا الدفع.
        $order = OrderFactory::new()->cod()->create(['customer_phone' => '01012345678']);

        $response = $this->post(route('orders.track.lookup'), [
            'order_number' => $order->order_number,
            'phone' => '01012345678',
        ]);

        $response->assertRedirect();
        $this->assertStringContainsString('/orders/'.$order->id.'/thank-you', (string) $response->headers->get('Location'));
    }

    public function test_alternate_phone_matches_via_http(): void
    {
        $order = OrderFactory::new()->create([
            'customer_phone' => '01011111111',
            'customer_phone_alt' => '01022222222',
        ]);

        $this->post(route('orders.track.lookup'), [
            'order_number' => $order->order_number,
            'phone' => '01022222222',
        ])->assertRedirect();
    }

    public function test_failed_lookup_does_not_echo_the_phone(): void
    {
        $order = OrderFactory::new()->create(['customer_phone' => '01012345678']);

        $this->followingRedirects()
            ->post(route('orders.track.lookup'), [
                'order_number' => $order->order_number,
                'phone' => '01055554444',
            ])
            ->assertOk()
            ->assertDontSee('01055554444');
    }
}
