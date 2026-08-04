<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Filament\Pages\AbandonedCarts;
use App\Mail\AbandonedCartRecovery;
use App\Models\Book;
use App\Models\Category;
use App\Models\Customer;
use App\Models\EmailSuppression;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * قسم «متابعة السلات المتروكة» — عملاء مسجَّلون أضافوا كتبًا إلى السلة ولم يُكملوا
 * الطلب (customer_carts سكنت أكثر من المهلة). عرض + تواصل + توليد كود خصم (CART،
 * محروس بـ coupons.manage). HONESTY (1.3): تعمل عبر php artisan test (MariaDB محليًا).
 */
final class AbandonedCartsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function admin(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user->fresh();
    }

    private function book(string $title, string $price): Book
    {
        return Book::factory()->create([
            'category_id' => Category::factory(),
            'title' => $title,
            'price' => $price,
        ]);
    }

    private function persistCart(Customer $customer, array $items, ?Carbon $updatedAt = null): void
    {
        DB::table('customer_carts')->insert([
            'customer_id' => $customer->id,
            'items' => json_encode($items),
            'updated_at' => $updatedAt ?? now()->subHours(2),
        ]);
    }

    public function test_it_lists_a_stale_registered_cart_with_contact_and_total(): void
    {
        $customer = Customer::factory()->create(['name' => 'أم يوسف']);
        $a = $this->book('كتاب المشاعر', '120.00');
        $b = $this->book('حكايات الغابة', '80.00');
        $this->persistCart($customer, [['id' => $a->id, 'qty' => 2], ['id' => $b->id, 'qty' => 1]]);

        $response = $this->actingAs($this->admin('super_admin'))->get(AbandonedCarts::getUrl());

        $response->assertOk();
        $response->assertSee('أم يوسف', false);                          // اسم العميل
        $response->assertSee('كتاب المشاعر', false);                     // الكتب
        $response->assertSee('حكايات الغابة', false);
        $response->assertSee('320 ج.م', false);                          // 120×2 + 80 = 320 من قاعدة البيانات
        $response->assertSee('wa.me/20'.$customer->phone_normalized, false); // رابط واتساب مطبّع
    }

    public function test_it_excludes_fresh_carts_within_the_threshold(): void
    {
        $stale = Customer::factory()->create(['name' => 'سلة قديمة']);
        $fresh = Customer::factory()->create(['name' => 'سلة جديدة']);
        $book = $this->book('كتاب', '50.00');
        $this->persistCart($stale, [['id' => $book->id, 'qty' => 1]], now()->subHours(3));
        $this->persistCart($fresh, [['id' => $book->id, 'qty' => 1]], now()); // قيد التعديل الآن

        $response = $this->actingAs($this->admin('super_admin'))->get(AbandonedCarts::getUrl());

        $response->assertOk();
        $response->assertSee('سلة قديمة', false);
        $response->assertDontSee('سلة جديدة', false);
    }

    public function test_it_excludes_soft_deleted_customers(): void
    {
        $customer = Customer::factory()->create(['name' => 'عميلة محذوفة']);
        $book = $this->book('كتاب', '50.00');
        $this->persistCart($customer, [['id' => $book->id, 'qty' => 1]]);
        $customer->delete(); // حذف ناعم

        $response = $this->actingAs($this->admin('super_admin'))->get(AbandonedCarts::getUrl());

        $response->assertOk();
        $response->assertDontSee('عميلة محذوفة', false);
    }

    public function test_it_excludes_a_cart_whose_books_were_all_deleted(): void
    {
        $customer = Customer::factory()->create(['name' => 'سلة كتبها محذوفة']);
        $book = $this->book('كتاب محذوف', '90.00');
        $this->persistCart($customer, [['id' => $book->id, 'qty' => 1]]);
        $book->delete(); // لم يبقَ في السلة كتاب متاح

        $response = $this->actingAs($this->admin('super_admin'))->get(AbandonedCarts::getUrl());

        $response->assertOk();
        $response->assertDontSee('سلة كتبها محذوفة', false); // لا شيء للاستعادة → لا بطاقة
    }

    public function test_it_will_not_generate_a_coupon_for_a_cart_with_no_available_books(): void
    {
        $customer = Customer::factory()->create();
        $book = $this->book('كتاب محذوف', '90.00');
        $this->persistCart($customer, [['id' => $book->id, 'qty' => 1]]);
        $book->delete();

        $this->actingAs($this->admin('super_admin'));
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(AbandonedCarts::class)->call('generateCoupon', $customer->id);

        $this->assertDatabaseCount('coupons', 0); // لا كوبون لسلة بلا كتب متاحة
    }

    public function test_generating_a_coupon_creates_a_cart_recovery_discount(): void
    {
        $customer = Customer::factory()->create();
        $book = $this->book('كتاب', '50.00');
        $this->persistCart($customer, [['id' => $book->id, 'qty' => 1]]);

        $this->actingAs($this->admin('super_admin'));
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(AbandonedCarts::class)
            ->call('generateCoupon', $customer->id)
            ->assertSet('coupons.'.$customer->id, fn ($code): bool => is_string($code) && str_starts_with($code, 'CART'));

        $this->assertDatabaseCount('coupons', 1);
        $this->assertDatabaseHas('coupons', ['type' => 'percentage', 'value' => '10.00', 'is_active' => 1, 'usage_limit_per_user' => 1]);
    }

    public function test_generating_a_coupon_twice_reuses_the_same_code(): void
    {
        $customer = Customer::factory()->create();
        $book = $this->book('كتاب', '50.00');
        $this->persistCart($customer, [['id' => $book->id, 'qty' => 1]]);

        $this->actingAs($this->admin('super_admin'));
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $component = Livewire::test(AbandonedCarts::class)->call('generateCoupon', $customer->id);
        $first = $component->get('coupons')[$customer->id];

        $component->call('generateCoupon', $customer->id);
        $second = $component->get('coupons')[$customer->id];

        $this->assertSame($first, $second);       // نفس الكود
        $this->assertDatabaseCount('coupons', 1);  // لم يُنشأ كوبون ثانٍ
    }

    public function test_it_will_not_generate_a_coupon_for_a_customer_outside_the_list(): void
    {
        // سلة حديثة (خارج المهلة) → العميل ليس ضمن قائمة المتروكة، فلا كوبون بتمرير معرّفه.
        $customer = Customer::factory()->create();
        $book = $this->book('كتاب', '50.00');
        $this->persistCart($customer, [['id' => $book->id, 'qty' => 1]], now());

        $this->actingAs($this->admin('super_admin'));
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(AbandonedCarts::class)->call('generateCoupon', $customer->id);

        $this->assertDatabaseCount('coupons', 0);
    }

    public function test_a_user_without_orders_view_is_forbidden(): void
    {
        // «التسويق» يملك coupons.manage لا orders.view → يُمنع عند بوابة الصفحة.
        $this->actingAs($this->admin('marketing'))
            ->get(AbandonedCarts::getUrl())
            ->assertForbidden();
    }

    public function test_compose_email_prefills_the_recipient_subject_and_body_with_the_db_total(): void
    {
        $customer = Customer::factory()->create(['name' => 'أم يوسف']);
        $book = $this->book('كتاب المشاعر', '120.00');
        $this->persistCart($customer, [['id' => $book->id, 'qty' => 2]]);

        $this->actingAs($this->admin('super_admin'));
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(AbandonedCarts::class)
            ->call('composeEmail', $customer->id)
            ->assertSet('composeCustomerId', $customer->id)
            ->assertSet('composeTo', $customer->email)
            ->assertSet('composeSubject', fn ($v): bool => is_string($v) && $v !== '')
            ->assertSet('composeBody', fn ($v): bool => is_string($v) && str_contains($v, '240')); // 120×2 من قاعدة البيانات
    }

    public function test_sending_a_recovery_email_dispatches_the_branded_mailable_and_closes_the_modal(): void
    {
        Mail::fake();
        $customer = Customer::factory()->create();
        $book = $this->book('كتاب', '50.00');
        $this->persistCart($customer, [['id' => $book->id, 'qty' => 1]]);

        $this->actingAs($this->admin('super_admin'));
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(AbandonedCarts::class)
            ->call('composeEmail', $customer->id)
            ->call('sendRecoveryEmail')
            ->assertSet('composeCustomerId', null);

        Mail::assertSent(AbandonedCartRecovery::class, fn (AbandonedCartRecovery $mail): bool => $mail->hasTo($customer->email));
    }

    public function test_the_compose_modal_can_be_closed_without_sending(): void
    {
        // closeCompose يُعيد ضبط الخاصية المقفولة خادميًّا (لا يمكن عبر ->set لأنها #[Locked]).
        $customer = Customer::factory()->create();
        $book = $this->book('كتاب', '50.00');
        $this->persistCart($customer, [['id' => $book->id, 'qty' => 1]]);

        $this->actingAs($this->admin('super_admin'));
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(AbandonedCarts::class)
            ->call('composeEmail', $customer->id)
            ->assertSet('composeCustomerId', $customer->id)
            ->call('closeCompose')
            ->assertSet('composeCustomerId', null);
    }

    public function test_it_never_emails_an_unsubscribed_customer(): void
    {
        Mail::fake();
        $customer = Customer::factory()->create();
        $book = $this->book('كتاب', '50.00');
        $this->persistCart($customer, [['id' => $book->id, 'qty' => 1]]);
        EmailSuppression::firstOrCreate(['email' => $customer->email], ['reason' => 'unsubscribe']);

        $this->actingAs($this->admin('super_admin'));
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(AbandonedCarts::class)
            ->call('composeEmail', $customer->id)
            ->call('sendRecoveryEmail');

        Mail::assertNothingSent(); // احترام إلغاء الاشتراك
    }

    public function test_composing_an_email_requires_the_campaigns_send_permission(): void
    {
        // «الدعم» يملك orders.view (يرى الصفحة) لا campaigns.send.
        $customer = Customer::factory()->create();
        $book = $this->book('كتاب', '50.00');
        $this->persistCart($customer, [['id' => $book->id, 'qty' => 1]]);

        $this->actingAs($this->admin('support'));
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(AbandonedCarts::class)
            ->call('composeEmail', $customer->id)
            ->assertForbidden();
    }

    public function test_generating_a_coupon_requires_the_coupons_manage_permission(): void
    {
        // «الدعم» يملك orders.view (يرى الصفحة) لا coupons.manage.
        $customer = Customer::factory()->create();
        $book = $this->book('كتاب', '50.00');
        $this->persistCart($customer, [['id' => $book->id, 'qty' => 1]]);

        $this->actingAs($this->admin('support'));
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(AbandonedCarts::class)
            ->call('generateCoupon', $customer->id)
            ->assertForbidden();

        $this->assertDatabaseCount('coupons', 0);
    }
}
