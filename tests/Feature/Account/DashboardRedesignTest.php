<?php

declare(strict_types=1);

namespace Tests\Feature\Account;

use App\Models\Book;
use App\Models\Customer;
use App\Models\Order;
use Database\Factories\OrderFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * إعادة تصميم لوحة الحساب (M12) — إحساس «هذا حسابي»: مونوغرام + تحية بالاسم +
 * إحصائيات (طلباتك · مكتبتك · آخر حالة) + حالة فارغة مبهجة.
 *
 * HONESTY (1.3/1.5): تعمل عبر php artisan test (MariaDB محليًا).
 */
final class DashboardRedesignTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_dashboard_greets_the_customer_by_name_with_a_monogram(): void
    {
        $customer = Customer::factory()->create(['name' => 'أم أحمد']);

        $response = $this->actingAs($customer, 'customer')->get(route('customer.dashboard'));

        $response->assertOk();
        $response->assertSee('أم أحمد', false);           // التحية بالاسم
        $response->assertSee('acc-mono', false);           // المونوغرام
        $response->assertSee('acc-stats', false);          // شريط الإحصائيات
        $response->assertSee('مكتبتك', false);             // إعادة تأطير الإنفاق
    }

    public function test_the_library_count_sums_book_quantities(): void
    {
        $customer = Customer::factory()->create();
        $book = Book::factory()->create(['price' => '100.00']);
        $order = OrderFactory::new()->create(['customer_id' => $customer->id, 'status' => 'delivered']);
        $order->items()->create([
            'book_id' => $book->id, 'book_title' => $book->title,
            'unit_price' => '100.00', 'quantity' => 3, 'line_total' => '300.00',
        ]);

        $response = $this->actingAs($customer, 'customer')->get(route('customer.dashboard'));

        $response->assertOk();
        // «مكتبتك ٣» — مجموع الكميات لا عدد الطلبات.
        $response->assertSeeInOrder(['3', 'مكتبتك'], false);
    }

    public function test_a_customer_with_no_orders_sees_a_warm_empty_state(): void
    {
        $customer = Customer::factory()->create();

        $response = $this->actingAs($customer, 'customer')->get(route('customer.dashboard'));

        $response->assertOk();
        $response->assertSee('acc-empty', false);
        $response->assertSee('مكتبة طفلك تبدأ من هنا', false);
    }

    public function test_the_login_page_has_a_password_eye_toggle(): void
    {
        $response = $this->get(route('customer.login.show'));

        $response->assertOk();
        $response->assertSee('acc-eye', false);
        $response->assertSee('acc-passwrap', false);
    }
}
