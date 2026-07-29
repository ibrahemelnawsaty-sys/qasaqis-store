<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\PaymentProof;
use App\Models\User;
use Database\Factories\OrderFactory;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * عرض إثبات الدفع للأدمن: يُبثّ من القرص الخاصّ عبر مسار مُصادَق مُصرَّح، بدل رابط
 * /storage الذي كان يُعطي 404 على الإنتاج (تعارض مع الرابط الرمزيّ العامّ). التفويض
 * خادميّ: orders.view (بند 4.4/4.5).
 */
final class PaymentProofViewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        Storage::fake('local');
    }

    private function proofWithFile(): PaymentProof
    {
        $order = OrderFactory::new()->create();
        $path = "payment-proofs/{$order->id}/proof.jpg";
        Storage::disk('local')->put($path, 'FAKE-IMAGE-BYTES');

        return PaymentProof::create([
            'order_id' => $order->id,
            'method_code' => 'instapay',
            'file_path' => $path,
            'amount' => '100.00',
            'review_status' => 'pending_review',
        ]);
    }

    public function test_an_authorized_admin_can_view_the_proof(): void
    {
        $proof = $this->proofWithFile();
        $admin = User::factory()->create();
        $admin->assignRole('admin');   // يملك orders.view

        $this->actingAs($admin)
            ->get(route('admin.payment-proofs.show', ['proof' => $proof->id]))
            ->assertOk();
    }

    public function test_a_user_without_orders_view_is_forbidden(): void
    {
        $proof = $this->proofWithFile();
        $nobody = User::factory()->create();   // بلا دور ولا صلاحية

        $this->actingAs($nobody)
            ->get(route('admin.payment-proofs.show', ['proof' => $proof->id]))
            ->assertForbidden();   // 403 — التفويض خادميّ لا مجرّد إخفاء رابط
    }

    public function test_a_guest_is_redirected_to_login(): void
    {
        $proof = $this->proofWithFile();

        $this->get(route('admin.payment-proofs.show', ['proof' => $proof->id]))
            ->assertRedirect();   // حارس auth
    }

    public function test_a_missing_file_returns_404_not_500(): void
    {
        $order = OrderFactory::new()->create();
        $proof = PaymentProof::create([
            'order_id' => $order->id, 'method_code' => 'instapay',
            'file_path' => 'payment-proofs/999/gone.jpg',   // لا ملف على القرص
            'amount' => '100.00', 'review_status' => 'pending_review',
        ]);
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin)
            ->get(route('admin.payment-proofs.show', ['proof' => $proof->id]))
            ->assertNotFound();
    }
}
