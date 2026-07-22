<?php

declare(strict_types=1);

namespace Tests\Feature\Checkout;

use App\Models\Book;
use App\Models\Customer;
use Database\Seeders\PaymentMethodSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * الدفع أهمّ مسار للإيراد، فيجب ألّا يُسقطه دفتر العناوين (رفاهية) حتى لو غاب جدوله —
 * كأن يُنشر الكود قبل تشغيل الهجرة على الإنتاج (كود جديد على مخطّط قديم، DEPLOYMENT
 * §12). يوازي FinanceResilienceTest: يتدرّج بدل 500 (الدستور 1.4/1.6).
 *
 * يُعيد الجدول في finally كي لا يُلوّث قاعدة الاختبار المشتركة لبقيّة التشغيل
 * (إسقاط الجدول DDL يُثبَّت فلا يُلغى بتراجع معاملة RefreshDatabase).
 */
final class CheckoutAddressResilienceTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_survives_when_the_address_book_table_is_missing(): void
    {
        $this->seed(PaymentMethodSeeder::class);
        // جوال عشوائيّ من المصنع (لا withPhone): أيّ صفّ يتسرّب من DDL يبقى بلا تصادم.
        $customer = Customer::factory()->create();
        $book = Book::factory()->create([
            'price' => '100.00', 'stock_status' => 'in_stock',
            'stock_quantity' => 20, 'manage_stock' => true,
        ]);

        $migration = require database_path('migrations/2026_07_22_000010_create_customer_addresses_table.php');
        Schema::dropIfExists('customer_addresses');

        try {
            $response = $this->actingAs($customer, 'customer')
                ->withSession(['cart' => [$book->id => 1]])
                ->get(route('checkout.show'));

            $response->assertOk();                              // لا 500 — الدفع صامد
            $response->assertDontSee('عناويني المحفوظة', false);   // تدهور نظيف: بلا محدِّد
        } finally {
            $migration->up();   // أعِد الجدول كي لا يتلوّث بقيّة التشغيل
        }
    }
}
