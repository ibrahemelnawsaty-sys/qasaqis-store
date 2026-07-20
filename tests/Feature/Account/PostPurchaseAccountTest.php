<?php

declare(strict_types=1);

namespace Tests\Feature\Account;

use App\Models\Customer;
use App\Models\Order;
use App\Notifications\VerificationCodeNotification;
use Database\Factories\OrderFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * إنشاء حساب من صفحة الشكر بعد الشراء (M10) — نافذة منبثقة بكلمة مرور فقط، وكل
 * البيانات من الطلب. المسار موقّع، وله حُرّاس ضد الاستيلاء.
 *
 * HONESTY (1.3/1.5): لم تُشغَّل يدويًا؛ تعمل عبر php artisan test (MariaDB محليًا).
 */
final class PostPurchaseAccountTest extends TestCase
{
    use RefreshDatabase;

    private function guestOrder(array $overrides = []): Order
    {
        return OrderFactory::new()->create(array_merge([
            'customer_id' => null,
            'customer_name' => 'أم أحمد',
            'customer_phone' => '01012345678',
            'customer_email' => 'mom@example.com',
            'governorate' => 'القاهرة',
            'address_line' => 'شارع التجربة 5',
            'country_code' => 'EG',
        ], $overrides));
    }

    private function signedUrl(Order $order): string
    {
        return URL::signedRoute('orders.create-account', ['order' => $order->id]);
    }

    // ── المسار السعيد ────────────────────────────────────────────────────────

    public function test_a_guest_creates_an_account_from_the_thank_you_page(): void
    {
        Notification::fake();
        $order = $this->guestOrder();

        $this->post($this->signedUrl($order), [
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ])->assertRedirect(route('customer.verify.show'));

        $customer = Customer::firstOrFail();
        // كل البيانات من الطلب.
        $this->assertSame('أم أحمد', $customer->name);
        $this->assertSame('mom@example.com', $customer->email);
        $this->assertSame('1012345678', $customer->phone_normalized);
        $this->assertSame('القاهرة', $customer->last_governorate);
        $this->assertTrue(Hash::check('secret123', (string) $customer->password));
        $this->assertAuthenticatedAs($customer, 'customer');
    }

    public function test_the_order_is_linked_to_the_new_account(): void
    {
        Notification::fake();
        $order = $this->guestOrder();

        $this->post($this->signedUrl($order), [
            'password' => 'secret123', 'password_confirmation' => 'secret123',
        ]);

        $this->assertSame(Customer::firstOrFail()->id, $order->fresh()->customer_id);
    }

    public function test_a_verification_code_is_sent_to_the_order_email(): void
    {
        Notification::fake();
        $order = $this->guestOrder();

        $this->post($this->signedUrl($order), [
            'password' => 'secret123', 'password_confirmation' => 'secret123',
        ]);

        Notification::assertSentOnDemand(
            VerificationCodeNotification::class,
            fn ($n, $channels, $notifiable) => $notifiable->routes['mail'] === 'mom@example.com'
        );
    }

    // ── الأمان ───────────────────────────────────────────────────────────────

    public function test_an_unsigned_request_is_rejected(): void
    {
        $order = $this->guestOrder();

        $this->post(route('orders.create-account', ['order' => $order->id]), [
            'password' => 'secret123', 'password_confirmation' => 'secret123',
        ])->assertForbidden();

        $this->assertSame(0, Customer::count());
    }

    public function test_the_email_is_taken_from_the_order_not_the_form(): void
    {
        Notification::fake();
        $order = $this->guestOrder(['customer_email' => 'real@example.com']);

        // محاولة حقن بريد مختلف عبر النموذج — يجب أن يُتجاهَل ويُؤخذ بريد الطلب.
        $this->post($this->signedUrl($order), [
            'email' => 'attacker@example.com',
            'password' => 'secret123', 'password_confirmation' => 'secret123',
        ]);

        $this->assertSame('real@example.com', Customer::firstOrFail()->email);
    }

    public function test_it_refuses_to_create_over_an_existing_phone_account(): void
    {
        $order = $this->guestOrder(['customer_phone' => '01012345678']);
        // حساب قائم بنفس الجوال.
        Customer::factory()->create(['phone_normalized' => '1012345678', 'email' => 'existing@example.com']);

        $this->post($this->signedUrl($order), [
            'password' => 'secret123', 'password_confirmation' => 'secret123',
        ])->assertRedirect(route('customer.login.show'));

        // لم يُنشأ حساب ثانٍ، ولم يُربط الطلب فوق حساب الغير.
        $this->assertSame(1, Customer::count());
        $this->assertNull($order->fresh()->customer_id);
    }

    public function test_an_already_linked_order_does_not_create_a_second_account(): void
    {
        Notification::fake();
        $existing = Customer::factory()->create();
        $order = $this->guestOrder(['customer_id' => $existing->id, 'customer_phone' => '01099998888']);

        $this->post($this->signedUrl($order), [
            'password' => 'secret123', 'password_confirmation' => 'secret123',
        ])->assertRedirect();

        $this->assertSame(1, Customer::count());
    }

    public function test_a_weak_password_is_rejected(): void
    {
        $order = $this->guestOrder();

        $this->from(route('orders.thankyou', ['order' => $order->id]))
            ->post($this->signedUrl($order), ['password' => '123', 'password_confirmation' => '123'])
            ->assertSessionHasErrors('password');

        $this->assertSame(0, Customer::count());
    }

    // ── الطلبات القديمة بلا بريد (توافق خلفي) ────────────────────────────────

    public function test_a_legacy_order_without_email_requires_one_in_the_popup(): void
    {
        $order = $this->guestOrder(['customer_email' => null]);

        $this->from(route('orders.thankyou', ['order' => $order->id]))
            ->post($this->signedUrl($order), ['password' => 'secret123', 'password_confirmation' => 'secret123'])
            ->assertSessionHasErrors('email');

        $this->assertSame(0, Customer::count());
    }

    public function test_a_legacy_order_uses_the_form_email(): void
    {
        Notification::fake();
        $order = $this->guestOrder(['customer_email' => null]);

        $this->post($this->signedUrl($order), [
            'email' => 'chosen@example.com',
            'password' => 'secret123', 'password_confirmation' => 'secret123',
        ])->assertRedirect(route('customer.verify.show'));

        $this->assertSame('chosen@example.com', Customer::firstOrFail()->email);
    }
}
