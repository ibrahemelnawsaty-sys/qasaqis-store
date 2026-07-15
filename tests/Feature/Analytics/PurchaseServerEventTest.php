<?php

declare(strict_types=1);

namespace Tests\Feature\Analytics;

use App\Jobs\SendPurchaseServerEvent;
use App\Models\Order;
use App\Services\Analytics\Ga4MeasurementProtocol;
use App\Services\Analytics\MetaConversionsApi;
use Database\Factories\OrderFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * حدث الشراء الخادمي (M6): يُطلَق عند تأكيد الدفع فقط وحين التفعيل، ويُرسَل إلى
 * Meta CAPI + GA4 مرة واحدة (idempotent).
 *
 * HONESTY (1.3/1.5): لم تُشغَّل هنا (لا PHP)؛ تعمل عبر `php artisan test` (MySQL).
 */
final class PurchaseServerEventTest extends TestCase
{
    use RefreshDatabase;

    private function paidTransition(Order $order): void
    {
        $order->forceFill(['payment_status' => 'paid', 'status' => 'processing'])->save();
    }

    private function orderWithTracking(array $overrides = []): Order
    {
        $order = OrderFactory::new()->create(array_merge([
            'payment_status' => 'pending_review',
            'status' => 'pending',
        ], $overrides));

        $order->tracking()->create([
            'purchase_event_id' => (string) Str::uuid(),
            'ga_client_id' => '111.222',
        ]);

        return $order;
    }

    private function enableKeys(): void
    {
        config([
            'analytics.enabled' => true,
            'analytics.meta.pixel_id' => '123',
            'analytics.meta.capi_token' => 'tok',
            'analytics.ga4.measurement_id' => 'G-XXX',
            'analytics.ga4.api_secret' => 'sec',
        ]);
    }

    public function test_event_dispatched_on_payment_paid_when_enabled(): void
    {
        config(['analytics.enabled' => true]);
        Queue::fake();
        $order = $this->orderWithTracking();

        $this->paidTransition($order);

        Queue::assertPushed(
            SendPurchaseServerEvent::class,
            fn (SendPurchaseServerEvent $job): bool => $job->orderId === $order->id
        );
    }

    public function test_event_not_dispatched_when_disabled(): void
    {
        config(['analytics.enabled' => false]);
        Queue::fake();
        $order = $this->orderWithTracking();

        $this->paidTransition($order);

        Queue::assertNotPushed(SendPurchaseServerEvent::class);
    }

    public function test_event_not_dispatched_on_non_paid_change(): void
    {
        config(['analytics.enabled' => true]);
        Queue::fake();
        $order = $this->orderWithTracking();

        // رفض الإثبات: payment_status → failed (لا paid).
        $order->forceFill(['payment_status' => 'failed'])->save();

        Queue::assertNotPushed(SendPurchaseServerEvent::class);
    }

    public function test_job_sends_to_meta_and_ga4_then_stamps(): void
    {
        $this->enableKeys();
        Http::fake(['*' => Http::response('', 200)]);
        $order = $this->orderWithTracking(['payment_status' => 'paid', 'customer_email' => 'mom@example.com']);

        (new SendPurchaseServerEvent($order->id))
            ->handle(app(MetaConversionsApi::class), app(Ga4MeasurementProtocol::class));

        Http::assertSentCount(2); // Meta CAPI + GA4 MP.
        $this->assertNotNull($order->tracking()->first()->purchase_sent_at);
    }

    public function test_job_is_idempotent_when_already_sent(): void
    {
        $this->enableKeys();
        Http::fake(['*' => Http::response('', 200)]);
        $order = $this->orderWithTracking(['payment_status' => 'paid']);
        $order->tracking()->update(['purchase_sent_at' => now()]);

        (new SendPurchaseServerEvent($order->id))
            ->handle(app(MetaConversionsApi::class), app(Ga4MeasurementProtocol::class));

        Http::assertNothingSent();
    }

    public function test_analytics_not_injected_when_disabled_by_default(): void
    {
        // ANALYTICS_ENABLED غير مضبوط في الاختبار → false → لا حقن.
        $this->get('/')
            ->assertOk()
            ->assertDontSee('googletagmanager.com/gtag', false)
            ->assertDontSee('connect.facebook.net', false);
    }
}
