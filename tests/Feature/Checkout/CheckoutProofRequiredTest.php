<?php

declare(strict_types=1);

namespace Tests\Feature\Checkout;

use App\Models\Book;
use App\Models\Order;
use App\Models\PaymentProof;
use Database\Seeders\PaymentMethodSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * إثبات التحويل إجباريّ في الـcheckout لطرق requires_proof: **لا يُنشأ أيّ طلب تحويل
 * يدويّ بلا إثبات مرفق** (طلب المالك). الطلب والإثبات يُنشآن ذرّيًّا في معاملة واحدة.
 * لا يمسّ COD/الأونلاين (لا إثبات لهما).
 */
final class CheckoutProofRequiredTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PaymentMethodSeeder::class);
        Storage::fake('local');
        // COD مبذور معطّلًا أحيانًا؛ نفعّله صراحةً لاختبار مساره.
        \App\Models\PaymentMethod::query()->where('code', 'cod')->update(['is_enabled' => true]);
    }

    private function book(): Book
    {
        return Book::factory()->create([
            'price' => '200.00', 'stock_status' => 'in_stock',
            'stock_quantity' => 10, 'manage_stock' => true,
        ]);
    }

    /** @return array<string, mixed> */
    private function payload(Book $book, array $overrides = []): array
    {
        return array_merge([
            'name' => 'أم أحمد',
            'phone' => '01012345678',
            'email' => 'buyer@example.com',
            'country_code' => 'EG',
            'governorate' => 'القاهرة',
            'address' => 'شارع التجربة رقم 5',
            'payment_method' => 'instapay',
            'items' => [['book_id' => $book->id, 'qty' => 1]],
        ], $overrides);
    }

    private function visitCheckout(Book $book): void
    {
        $this->withSession(['cart' => [$book->id => 1]])->get(route('checkout.show'))->assertOk();
    }

    public function test_manual_transfer_without_a_proof_is_rejected_and_no_order_is_created(): void
    {
        $book = $this->book();
        $this->visitCheckout($book);

        $this->from(route('checkout.show'))
            ->post(route('checkout.place'), $this->payload($book)) // بلا proof
            ->assertSessionHasErrors('proof');

        // الجوهر: لم يُنشأ أيّ طلب، ولم يُحجَز مخزون.
        $this->assertSame(0, Order::count());
        $this->assertSame(10, $book->fresh()->stock_quantity);
    }

    public function test_manual_transfer_with_a_proof_creates_the_order_and_the_proof_atomically(): void
    {
        $book = $this->book();
        $this->visitCheckout($book);

        $this->post(route('checkout.place'), $this->payload($book, [
            'proof' => UploadedFile::fake()->image('receipt.jpg'),
            'sender_reference' => 'REF-123',
        ]))->assertStatus(302);

        $this->assertSame(1, Order::count());
        $order = Order::firstOrFail();

        // إثبات مرفق فعليّ + ملفّ مخزَّن على القرص الخاصّ + حالة «قيد مراجعة الإثبات».
        $proof = PaymentProof::where('order_id', $order->id)->first();
        $this->assertNotNull($proof);
        $this->assertStringStartsWith("payment-proofs/{$order->id}/", $proof->file_path);
        Storage::disk('local')->assertExists($proof->file_path);
        $this->assertSame('REF-123', $proof->sender_reference);
        $this->assertSame('pending_review', $order->payment_status);
        $this->assertSame('pending', $order->status);
    }

    public function test_manual_transfer_with_a_proof_redirects_to_thank_you(): void
    {
        $book = $this->book();
        $this->visitCheckout($book);

        $this->post(route('checkout.place'), $this->payload($book, [
            'proof' => UploadedFile::fake()->image('receipt.jpg'),
        ]))->assertRedirectContains('/thank-you');
    }

    public function test_checkout_renders_the_two_step_structure(): void
    {
        $book = $this->book();

        // الخطوة ١ (co-step1) + الخطوة ٢ (proofBlock مع زرّ الرجوع) + زرّ «متابعة» +
        // علم requires_proof على الراديو. يؤكّد أن إعادة الهيكلة تُصيَّر بعناصرها.
        $this->withSession(['cart' => [$book->id => 1]])
            ->get(route('checkout.show'))
            ->assertOk()
            ->assertSee('id="co-step1"', false)
            ->assertSee('id="proofBlock"', false)
            ->assertSee('id="continueBtn"', false)
            ->assertSee('id="backToStep1"', false)
            ->assertSee('data-requires-proof', false);
    }

    public function test_cash_on_delivery_places_the_order_without_a_proof(): void
    {
        $book = $this->book();
        $this->visitCheckout($book);

        $this->post(route('checkout.place'), $this->payload($book, ['payment_method' => 'cod']))
            ->assertStatus(302);

        $this->assertSame(1, Order::count());
        $this->assertSame(0, PaymentProof::count());
        $this->assertSame('confirmed', Order::firstOrFail()->status);
    }

    public function test_a_disallowed_proof_type_is_rejected_and_no_order_is_created(): void
    {
        $book = $this->book();
        $this->visitCheckout($book);

        $this->from(route('checkout.show'))->post(route('checkout.place'), $this->payload($book, [
            'proof' => UploadedFile::fake()->create('malware.exe', 100, 'application/octet-stream'),
        ]))->assertSessionHasErrors('proof');

        $this->assertSame(0, Order::count());
    }
}
