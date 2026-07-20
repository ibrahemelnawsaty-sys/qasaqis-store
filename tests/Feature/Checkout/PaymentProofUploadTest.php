<?php

declare(strict_types=1);

namespace Tests\Feature\Checkout;

use App\Models\Order;
use App\Models\PaymentProof;
use App\Notifications\CustomerOrderNotification;
use Database\Factories\OrderFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Payment-proof upload security (constitution 4.5):
 *  - only jpg/jpeg/png/pdf accepted, max 4 MB — everything else is rejected;
 *  - the file is stored on the PRIVATE `local` disk (storage/app/private), never
 *    a public path;
 *  - it is renamed to a random, safe name — the client's original name is never
 *    trusted.
 * Access is via a SIGNED link only (the route carries the `signed` middleware).
 *
 * NOTE: Order has no HasFactory trait; OrderFactory is used via ::new().
 *
 * HONESTY (1.3/1.5): NOT executed here (no PHP); runs via `php artisan test`.
 */
final class PaymentProofUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function manualOrder(): Order
    {
        return OrderFactory::new()->manualTransfer('instapay')->create([
            'grand_total' => '200.00',
        ]);
    }

    private function signedUrl(Order $order): string
    {
        return URL::signedRoute('orders.proof.store', ['order' => $order->id]);
    }

    public function test_customer_is_emailed_once_no_matter_how_many_times_the_proof_is_re_uploaded(): void
    {
        Notification::fake();
        // لا مستخدم بصلاحية orders.view في هذا الملف، ونُصفّر البريد الاحتياطي
        // صراحةً — فيصبح كل إشعار يُعدّ هنا إشعارَ عميلة، والعدّ حتميًا.
        config(['orders.admin_fallback_email' => null]);

        $order = OrderFactory::new()->manualTransfer('instapay')->create([
            'grand_total' => '200.00',
            'customer_email' => 'mom@example.com',
        ]);
        $url = $this->signedUrl($order);

        // ثلاث محاولات رفع — وهو سلوك متوقّع تمامًا: رفع صورة ثقيلة على شبكة
        // بطيئة يدفع للنقر مرارًا، ومسار الرفع يسمح بستّ محاولات في الدقيقة.
        for ($i = 0; $i < 3; $i++) {
            $this->post($url, ['proof' => UploadedFile::fake()->image("receipt-{$i}.jpg")]);
        }

        $this->assertSame(3, PaymentProof::where('order_id', $order->id)->count());

        // إيصال واحد فقط للعميلة — وإلا وصلتها ثلاث رسائل متطابقة تقول لها
        // «لا حاجة لإرسال الإثبات مرة أخرى».
        Notification::assertCount(1);
        Notification::assertSentOnDemand(
            CustomerOrderNotification::class,
            fn ($n) => $n->kind === CustomerOrderNotification::PROOF_RECEIVED
        );
    }

    public function test_valid_jpg_proof_is_accepted_and_stored_privately_with_a_random_name(): void
    {
        $order = $this->manualOrder();
        $url = $this->signedUrl($order);

        $response = $this->from($url)->post($url, [
            'proof' => UploadedFile::fake()->image('my-receipt.jpg'),
            'amount' => '200.00',
        ]);

        $response->assertStatus(302);

        $proof = PaymentProof::where('order_id', $order->id)->first();
        $this->assertNotNull($proof);

        // Stored on the private disk under the order folder...
        Storage::disk('local')->assertExists($proof->file_path);
        $this->assertStringStartsWith("payment-proofs/{$order->id}/", $proof->file_path);
        // ...with a random name — never the original "my-receipt".
        $this->assertStringNotContainsString('my-receipt', $proof->file_path);

        // The order is moved under review.
        $this->assertSame('pending_review', $order->fresh()->payment_status);
    }

    public function test_pdf_proof_is_accepted(): void
    {
        $order = $this->manualOrder();
        $url = $this->signedUrl($order);

        $this->from($url)->post($url, [
            'proof' => UploadedFile::fake()->create('receipt.pdf', 200, 'application/pdf'),
        ])->assertStatus(302);

        $this->assertSame(1, PaymentProof::where('order_id', $order->id)->count());
    }

    public function test_disallowed_file_type_is_rejected(): void
    {
        $order = $this->manualOrder();
        $url = $this->signedUrl($order);

        $response = $this->from($url)->post($url, [
            'proof' => UploadedFile::fake()->create('malware.exe', 100, 'application/octet-stream'),
        ]);

        $response->assertSessionHasErrors('proof');
        $this->assertSame(0, PaymentProof::where('order_id', $order->id)->count());
    }

    public function test_oversized_file_is_rejected(): void
    {
        $order = $this->manualOrder();
        $url = $this->signedUrl($order);

        // 9 MB > the 8 MB (8192 KB) cap, even though the type is allowed.
        $response = $this->from($url)->post($url, [
            'proof' => UploadedFile::fake()->create('big.jpg', 9000, 'image/jpeg'),
        ]);

        $response->assertSessionHasErrors('proof');
        $this->assertSame(0, PaymentProof::where('order_id', $order->id)->count());
    }

    public function test_a_typical_phone_photo_within_the_new_cap_is_accepted(): void
    {
        // 6 MB كان يُرفض قبل رفع الحدّ (صورة إيصال بهاتف حديث) — الآن يُقبل.
        $order = $this->manualOrder();
        $url = $this->signedUrl($order);

        $response = $this->from($url)->post($url, [
            'proof' => UploadedFile::fake()->create('receipt.jpg', 6000, 'image/jpeg'),
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertSame(1, PaymentProof::where('order_id', $order->id)->count());
    }

    public function test_missing_proof_is_rejected(): void
    {
        $order = $this->manualOrder();
        $url = $this->signedUrl($order);

        $this->from($url)->post($url, [])
            ->assertSessionHasErrors('proof');

        $this->assertSame(0, PaymentProof::where('order_id', $order->id)->count());
    }

    public function test_unsigned_request_is_forbidden(): void
    {
        $order = $this->manualOrder();

        // No valid signature -> the `signed` middleware aborts (403).
        $this->post(route('orders.proof.store', ['order' => $order->id]), [
            'proof' => UploadedFile::fake()->image('receipt.jpg'),
        ])->assertForbidden();

        $this->assertSame(0, PaymentProof::where('order_id', $order->id)->count());
    }
}
