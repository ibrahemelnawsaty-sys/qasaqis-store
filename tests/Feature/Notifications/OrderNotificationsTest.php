<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Models\Order;
use App\Models\User;
use App\Notifications\AdminOrderNotification;
use App\Notifications\CustomerOrderNotification;
use App\Services\Notifications\OrderNotifier;
use App\Support\Notifications\AdminRecipients;
use Database\Factories\OrderFactory;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * إشعارات دورة حياة الطلب (M4): تأكيد للعميل، تنبيه للأدمن، إشعارات الدفع/الشحن.
 * كلها ShouldQueue، وتخطٍّ آمن للعميل بلا بريد، ومستقبِلو الأدمن خادميًا.
 *
 * HONESTY (1.3/1.5): لم تُشغَّل هنا (لا PHP)؛ تعمل عبر `php artisan test` (MySQL).
 */
final class OrderNotificationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function notifier(): OrderNotifier
    {
        return app(OrderNotifier::class);
    }

    private function admin(): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->givePermissionTo('orders.view');

        return $user;
    }

    public function test_order_placed_notifies_customer_and_admin(): void
    {
        Notification::fake();
        $admin = $this->admin();
        $order = OrderFactory::new()->create(['customer_email' => 'mom@example.com']);

        $this->notifier()->orderPlaced($order);

        Notification::assertSentOnDemand(
            CustomerOrderNotification::class,
            fn ($n, $channels, $notifiable) => $n->kind === CustomerOrderNotification::PLACED
                && $notifiable->routes['mail'] === 'mom@example.com'
        );
        Notification::assertSentTo(
            $admin,
            AdminOrderNotification::class,
            fn ($n) => $n->kind === AdminOrderNotification::NEW_ORDER
        );
    }

    public function test_guest_without_email_skips_customer_but_still_notifies_admin(): void
    {
        Notification::fake();
        $admin = $this->admin();
        $order = OrderFactory::new()->create(['customer_email' => null]);

        $this->notifier()->orderPlaced($order);

        // إشعار واحد فقط (الأدمن) — لا إشعار عميل بلا بريد.
        Notification::assertCount(1);
        Notification::assertSentTo($admin, AdminOrderNotification::class);
    }

    public function test_proof_submitted_notifies_customer_and_admin(): void
    {
        Notification::fake();
        $admin = $this->admin();
        $order = OrderFactory::new()->create(['customer_email' => 'mom@example.com']);

        $this->notifier()->paymentProofSubmitted($order);

        // تغيّر السلوك في M7: كان الأدمن وحده يُنبَّه، فتبقى العميلة التي حوّلت
        // المال بلا خبر — أعلى نقطة قلق في المسار. صارت تستلم إيصالًا فوريًا.
        Notification::assertSentOnDemand(
            CustomerOrderNotification::class,
            fn ($n, $channels, $notifiable) => $n->kind === CustomerOrderNotification::PROOF_RECEIVED
                && $notifiable->routes['mail'] === 'mom@example.com'
        );
        Notification::assertSentTo(
            $admin,
            AdminOrderNotification::class,
            fn ($n) => $n->kind === AdminOrderNotification::PROOF_SUBMITTED
        );
    }

    public function test_proof_submitted_without_customer_email_still_notifies_admin(): void
    {
        Notification::fake();
        $admin = $this->admin();
        $order = OrderFactory::new()->create(['customer_email' => null]);

        $this->notifier()->paymentProofSubmitted($order);

        // البريد اختياري في نموذج الدفع: غيابه يتخطّى العميلة بصمت ولا يُسقِط
        // تنبيه الأدمن (وإلا ضاع الإثبات بلا مراجعة).
        Notification::assertCount(1);
        Notification::assertSentTo($admin, AdminOrderNotification::class);
    }

    public function test_proof_received_email_builds_with_subject_and_cta(): void
    {
        $order = OrderFactory::new()->create([
            'customer_email' => 'mom@example.com',
            'payment_method' => 'instapay',
            'payment_status' => 'pending_review',
            'status' => 'pending',
        ]);

        $mail = (new CustomerOrderNotification($order, CustomerOrderNotification::PROOF_RECEIVED))
            ->toMail(new AnonymousNotifiable);

        $this->assertSame('emails.customer-order', $mail->view);
        $this->assertStringContainsString($order->order_number, $mail->subject);
        $this->assertStringContainsString('signature=', $mail->viewData['ctaUrl']);
        // إيصال طمأنة قصير — لا يُعيد سرد بنود الطلب (وصلت مع رسالة الاستلام).
        $this->assertFalse($mail->viewData['showSummary']);
    }

    public function test_payment_approved_notifies_customer(): void
    {
        Notification::fake();
        $order = OrderFactory::new()->create(['customer_email' => 'mom@example.com']);

        $this->notifier()->paymentApproved($order);

        Notification::assertSentOnDemand(
            CustomerOrderNotification::class,
            fn ($n) => $n->kind === CustomerOrderNotification::APPROVED
        );
    }

    public function test_payment_rejected_carries_reason(): void
    {
        Notification::fake();
        $order = OrderFactory::new()->create(['customer_email' => 'mom@example.com']);

        $this->notifier()->paymentRejected($order, 'الصورة غير واضحة');

        Notification::assertSentOnDemand(
            CustomerOrderNotification::class,
            fn ($n) => $n->kind === CustomerOrderNotification::REJECTED
                && $n->reason === 'الصورة غير واضحة'
        );
    }

    public function test_order_shipped_notifies_customer(): void
    {
        Notification::fake();
        $order = OrderFactory::new()->create([
            'customer_email' => 'mom@example.com',
            'tracking_number' => 'TRK-777',
        ]);

        $this->notifier()->orderShipped($order);

        Notification::assertSentOnDemand(
            CustomerOrderNotification::class,
            fn ($n) => $n->kind === CustomerOrderNotification::SHIPPED
        );
    }

    public function test_admin_fallback_email_used_when_no_permitted_user(): void
    {
        Notification::fake();
        config(['orders.admin_fallback_email' => 'ops@example.com']);
        // لا مستخدم بصلاحية orders.view.
        $order = OrderFactory::new()->create(['customer_email' => null]);

        $this->notifier()->orderPlaced($order);

        Notification::assertSentOnDemand(
            AdminOrderNotification::class,
            fn ($n, $channels, $notifiable) => $notifiable->routes['mail'] === 'ops@example.com'
        );
    }

    public function test_inactive_or_unpermitted_users_are_not_recipients(): void
    {
        $permitted = $this->admin();

        $inactive = User::factory()->create(['is_active' => false]);
        $inactive->givePermissionTo('orders.view');

        User::factory()->create(['is_active' => true]); // active but no permission.

        $recipients = AdminRecipients::forOrders()->pluck('id');

        $this->assertTrue($recipients->contains($permitted->id));
        $this->assertFalse($recipients->contains($inactive->id));
        $this->assertCount(1, $recipients);
    }

    public function test_notifications_are_queueable(): void
    {
        $order = OrderFactory::new()->create();

        $this->assertInstanceOf(ShouldQueue::class, new CustomerOrderNotification($order, CustomerOrderNotification::PLACED));
        $this->assertInstanceOf(ShouldQueue::class, new AdminOrderNotification($order, AdminOrderNotification::NEW_ORDER));
    }

    public function test_customer_mail_builds_with_subject_and_view(): void
    {
        $order = OrderFactory::new()->create(['customer_email' => 'mom@example.com']);

        $mail = (new CustomerOrderNotification($order, CustomerOrderNotification::PLACED))
            ->toMail(new AnonymousNotifiable);

        $this->assertSame('emails.customer-order', $mail->view);
        $this->assertStringContainsString($order->order_number, $mail->subject);
    }

    public function test_rejected_email_links_directly_to_the_upload_page(): void
    {
        // بعد الرفض تصبح الحالة failed — الرابط يجب أن يقود لصفحة الرفع لا الشكر.
        $order = OrderFactory::new()->create([
            'customer_email' => 'mom@example.com',
            'payment_method' => 'instapay',
            'payment_status' => 'failed',
            'status' => 'pending',
        ]);

        $mail = (new CustomerOrderNotification($order, CustomerOrderNotification::REJECTED, 'الصورة غير واضحة'))
            ->toMail(new AnonymousNotifiable);

        $this->assertStringContainsString('/orders/'.$order->id.'/payment', $mail->viewData['ctaUrl']);
        $this->assertStringContainsString('signature=', $mail->viewData['ctaUrl']);
    }
}
