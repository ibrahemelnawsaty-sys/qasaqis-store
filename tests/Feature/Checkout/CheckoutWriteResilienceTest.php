<?php

declare(strict_types=1);

namespace Tests\Feature\Checkout;

use App\Models\Book;
use App\Models\Customer;
use App\Models\Order;
use Database\Seeders\PaymentMethodSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * إنشاء الطلب (أهمّ مسار للإيراد) يجب ألّا يُسقطه انزلاق المخطّط: لو نُشر كودُ موجةٍ
 * قبل تشغيل هجراتها على الإنتاج (كود جديد على مخطّط قديم، DEPLOYMENT §12) يجب أن يكتمل
 * البيع بإسقاط الأعمدة الغائبة من الـINSERT (تصفية onlyExisting)، لا أن يرمي 500.
 *
 * يُسقط أعمدة موجة الحساب/المالية الحديثة عبر down() لكل هجرة، ثم يؤكّد نجاح الطلب،
 * ثم يستعيدها عبر up() في finally كي لا تُلوّث قاعدة الاختبار المشتركة.
 */
final class CheckoutWriteResilienceTest extends TestCase
{
    use RefreshDatabase;

    /** بترتيب زمنيّ (الأقدم أولًا): up() آمن بهذا الترتيب (cost_is_estimated بعد unit_cost). */
    private const RECENT_MIGRATIONS = [
        '2026_07_19_000005_add_idempotency_key_to_orders_table',
        '2026_07_20_000001_add_cost_snapshot_to_order_items_table',
        '2026_07_20_000002_add_customer_id_to_orders_table',
        '2026_07_21_000002_add_cost_is_estimated_to_order_items_table',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PaymentMethodSeeder::class);
    }

    public function test_an_order_is_placed_even_when_the_recent_column_wave_is_missing(): void
    {
        // الإشعار مؤجَّل (ShouldQueue) في الإنتاج فلا يمسّ استجابة الدفع — نُثبّته هنا.
        Mail::fake();
        Notification::fake();

        $book = Book::factory()->create([
            'price' => '100.00', 'stock_status' => 'in_stock',
            'stock_quantity' => 20, 'manage_stock' => true,
        ]);
        $customer = Customer::factory()->withPhone('01011224488')->create();

        // كل require (لا require_once) يُعيد تنفيذ الملف فيُنتج كائن هجرة طازجًا.
        $migrations = array_map(
            fn (string $f) => require database_path("migrations/{$f}.php"),
            self::RECENT_MIGRATIONS,
        );

        // إسقاط بترتيب معكوس (الأحدث أولًا) لسلامة التبعيات.
        foreach (array_reverse($migrations) as $migration) {
            $migration->down();
        }

        try {
            // العميلة مصادَقة على حارس customer، والحارس الافتراضي web = null (فلا
            // يُكتب user_id لمستخدم إداريّ غير موجود) — نفس نمط AddressBookTest.
            $this->be($customer, 'customer');
            Auth::shouldUse('web');

            // GET أولًا كي يُثبَّت مفتاح منع التكرار في الجلسة، فنُجرّب findReplay
            // بمفتاح غير null بينما عموده مُسقَط (يجب أن يتخطّاه الحارس بلا 500).
            $this->withSession(['cart' => [$book->id => 1]])
                ->get(route('checkout.show'))
                ->assertOk();

            $this->post(route('checkout.place'), [
                'name' => 'أم منى', 'phone' => '01011224488', 'email' => 'mona@example.com',
                'country_code' => 'EG', 'governorate' => 'الإسكندرية', 'city' => 'سموحة',
                'address' => 'شارع فوزي معاذ 12', 'payment_method' => 'instapay',
                'items' => [['book_id' => $book->id, 'qty' => 1]],
            ])->assertStatus(302);   // إعادة توجيه لصفحة الدفع/الشكر — لا 500

            // البيع لم يُفقَد: الطلب أُنشئ (بلا الأعمدة الغائبة)، وسطره كذلك.
            $this->assertSame(1, Order::count());
            $order = Order::firstOrFail();
            $this->assertSame('01011224488', $order->customer_phone);
            $this->assertSame(1, $order->items()->count());
        } finally {
            // استعادة بترتيب زمنيّ (الأقدم أولًا).
            foreach ($migrations as $migration) {
                $migration->up();
            }
        }
    }
}
