<?php

declare(strict_types=1);

namespace Tests\Feature\Account;

use App\Models\Customer;
use App\Models\VerificationCode;
use App\Notifications\VerificationCodeNotification;
use App\Support\Verification\VerificationCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * تأكيد بريد العميلة بكود (M9). القناة بريد اليوم، قابلة للتبديل إلى OTP جوال.
 *
 * محاور: الكود يُخزَّن مُجزّأً، والتحقق يستهلكه، والخطأ يزيد المحاولات ويُبطِل عند
 * الحدّ، وإصدار جديد يُبطِل السابق، والتسجيل يُصدر كودًا ويوجّه لصفحة التأكيد،
 * وفشل الإرسال لا يُسقِط التسجيل.
 *
 * HONESTY (1.3/1.5): لم تُشغَّل يدويًا؛ تعمل عبر php artisan test (MariaDB محليًا).
 */
final class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    private function customer(array $overrides = []): Customer
    {
        return Customer::factory()->create(array_merge([
            'email' => 'mom@example.com',
            'email_verified_at' => null,
        ], $overrides));
    }

    private function service(): VerificationCodeService
    {
        return app(VerificationCodeService::class);
    }

    // ── التخزين والتحقق ──────────────────────────────────────────────────────

    public function test_the_code_is_stored_hashed_never_plaintext(): void
    {
        Notification::fake();
        $this->service()->issueAndSend('mom@example.com', 'email_verification');

        $record = VerificationCode::firstOrFail();
        $this->assertNotEmpty($record->code_hash);
        // لا كود خام مخزّن: عمود code_hash يبدأ بصيغة تجزئة bcrypt/argon.
        $this->assertMatchesRegularExpression('/^\$2y\$|^\$argon/', (string) $record->code_hash);
    }

    public function test_issuing_sends_via_the_email_channel(): void
    {
        Notification::fake();
        $this->service()->issueAndSend('mom@example.com', 'email_verification');

        // الإرسال مؤجَّل لِما بعد الاستجابة (M11 — لتسريع الصفحة). في استدعاء مباشر
        // للخدمة لا يوجد terminate يُشغّله، فنُشغّل المؤجَّلات يدويًا قبل التوكيد.
        app(\Illuminate\Support\Defer\DeferredCallbackCollection::class)->invoke();

        Notification::assertSentOnDemand(
            VerificationCodeNotification::class,
            fn ($n, $channels, $notifiable) => $notifiable->routes['mail'] === 'mom@example.com'
        );
    }

    public function test_the_correct_code_verifies_and_is_consumed(): void
    {
        // نُنشئ الكود يدويًا بقيمة معروفة (الخدمة تولّده عشوائيًا).
        VerificationCode::create([
            'identifier' => 'mom@example.com',
            'channel' => 'email',
            'purpose' => 'email_verification',
            'code_hash' => Hash::make('123456'),
            'attempts' => 0,
            'expires_at' => now()->addMinutes(15),
        ]);

        $ok = $this->service()->verify('mom@example.com', 'email_verification', '123456');

        $this->assertTrue($ok);
        $this->assertNotNull(VerificationCode::firstOrFail()->consumed_at);
    }

    public function test_a_wrong_code_fails_and_increments_attempts(): void
    {
        VerificationCode::create([
            'identifier' => 'mom@example.com', 'channel' => 'email', 'purpose' => 'email_verification',
            'code_hash' => Hash::make('123456'), 'attempts' => 0, 'expires_at' => now()->addMinutes(15),
        ]);

        $this->assertFalse($this->service()->verify('mom@example.com', 'email_verification', '000000'));
        $this->assertSame(1, VerificationCode::firstOrFail()->attempts);
    }

    public function test_the_code_is_invalidated_after_five_wrong_attempts(): void
    {
        VerificationCode::create([
            'identifier' => 'mom@example.com', 'channel' => 'email', 'purpose' => 'email_verification',
            'code_hash' => Hash::make('123456'), 'attempts' => 5, 'expires_at' => now()->addMinutes(15),
        ]);

        // حتى بالكود الصحيح: تجاوز الحدّ يُبطِله (منع التخمين المستمر).
        $this->assertFalse($this->service()->verify('mom@example.com', 'email_verification', '123456'));
        $this->assertNotNull(VerificationCode::firstOrFail()->consumed_at);
    }

    public function test_an_expired_code_does_not_verify(): void
    {
        VerificationCode::create([
            'identifier' => 'mom@example.com', 'channel' => 'email', 'purpose' => 'email_verification',
            'code_hash' => Hash::make('123456'), 'attempts' => 0, 'expires_at' => now()->subMinute(),
        ]);

        $this->assertFalse($this->service()->verify('mom@example.com', 'email_verification', '123456'));
    }

    public function test_issuing_a_new_code_invalidates_the_previous_one(): void
    {
        Notification::fake();
        $this->service()->issueAndSend('mom@example.com', 'email_verification');
        $first = VerificationCode::firstOrFail();

        $this->service()->issueAndSend('mom@example.com', 'email_verification');

        // الكود الأول استُهلك (أُبطِل)، والفعّال واحد فقط.
        $this->assertNotNull($first->fresh()->consumed_at);
        $this->assertSame(1, VerificationCode::query()->whereNull('consumed_at')->count());
    }

    // ── تدفّق HTTP ───────────────────────────────────────────────────────────

    public function test_registration_issues_a_code_and_redirects_to_verify(): void
    {
        Notification::fake();

        $this->post(route('customer.register.store'), [
            'name' => 'أم أحمد',
            'phone' => '01012345678',
            'email' => 'mom@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ])->assertRedirect(route('customer.verify.show'));

        $this->assertDatabaseHas('verification_codes', [
            'identifier' => 'mom@example.com',
            'purpose' => 'email_verification',
        ]);
        Notification::assertSentOnDemand(VerificationCodeNotification::class);
    }

    public function test_verifying_from_the_page_marks_the_email_verified(): void
    {
        $customer = $this->customer();
        VerificationCode::create([
            'identifier' => $customer->email, 'channel' => 'email', 'purpose' => 'email_verification',
            'code_hash' => Hash::make('654321'), 'attempts' => 0, 'expires_at' => now()->addMinutes(15),
        ]);

        $this->actingAs($customer, 'customer')
            ->post(route('customer.verify.store'), ['code' => '654321'])
            ->assertRedirect(route('customer.dashboard'));

        $this->assertNotNull($customer->fresh()->email_verified_at);
    }

    public function test_a_wrong_code_on_the_page_keeps_the_email_unverified(): void
    {
        $customer = $this->customer();
        VerificationCode::create([
            'identifier' => $customer->email, 'channel' => 'email', 'purpose' => 'email_verification',
            'code_hash' => Hash::make('654321'), 'attempts' => 0, 'expires_at' => now()->addMinutes(15),
        ]);

        $this->actingAs($customer, 'customer')
            ->post(route('customer.verify.store'), ['code' => '111111'])
            ->assertSessionHas('verify_error');

        $this->assertNull($customer->fresh()->email_verified_at);
    }

    public function test_an_already_verified_customer_is_sent_to_the_dashboard(): void
    {
        $customer = $this->customer(['email_verified_at' => now()]);

        $this->actingAs($customer, 'customer')
            ->get(route('customer.verify.show'))
            ->assertRedirect(route('customer.dashboard'));
    }

    public function test_the_verify_page_requires_a_logged_in_customer(): void
    {
        $this->get(route('customer.verify.show'))->assertRedirect(route('customer.login.show'));
    }

    public function test_a_send_failure_does_not_break_registration(): void
    {
        // نُجبر قناة تفشل عند الإرسال: التسجيل يجب أن ينجح رغم ذلك.
        $this->app->bind(\App\Support\Verification\VerificationChannel::class, fn () => new class implements \App\Support\Verification\VerificationChannel {
            public function send(string $identifier, string $code): void
            {
                throw new \RuntimeException('SMTP down');
            }

            public function name(): string
            {
                return 'email';
            }
        });

        $this->post(route('customer.register.store'), [
            'name' => 'أم سارة',
            'phone' => '01112345678',
            'email' => 'sara@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ])->assertRedirect(route('customer.verify.show'));

        // الحساب أُنشئ رغم فشل الإرسال.
        $this->assertDatabaseHas('customers', ['email' => 'sara@example.com']);
    }
}
