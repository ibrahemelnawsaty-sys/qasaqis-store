<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Filament\Pages\FinanceDashboard;
use App\Filament\Resources\BookResource;
use App\Filament\Resources\BookResource\Pages\EditBook;
use App\Filament\Resources\ExpenseResource;
use App\Filament\Resources\OrderResource\Pages\ViewOrder;
use App\Filament\Widgets\FinanceDailyWidget;
use App\Filament\Widgets\FinanceStatsWidget;
use App\Models\Book;
use App\Models\User;
use Database\Factories\OrderFactory;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * سرّية المال (الدستور 4.4 / 0.7): لوحة القسم المالي وكل ويدجت فيها محميّة
 * بـ orders.view_financials خادميًا، وتكلفة الشراء مخفيّة عمّن لا يملك
 * products.cost.view — بما فيهم محرّر المحتوى الذي يحرّر الكتب.
 */
final class FinanceSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_finance_dashboard_and_every_widget_require_the_financial_permission(): void
    {
        $support = User::factory()->create();
        $support->assignRole('support'); // has orders.view but NOT orders.view_financials.

        $this->actingAs($support);
        $this->assertFalse(FinanceDashboard::canAccess(), 'الدعم يجب ألا يصل للوحة المالية');
        $this->assertFalse(FinanceStatsWidget::canView(), 'ويدجت المؤشرات يجب أن يحمي نفسه');
        $this->assertFalse(FinanceDailyWidget::canView(), 'ويدجت الجدول اليومي يجب أن يحمي نفسه');
    }

    public function test_orders_manager_can_access_the_finance_dashboard(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole('orders_manager'); // has orders.view_financials via the orders prefix.

        $this->actingAs($manager);
        $this->assertTrue(FinanceDashboard::canAccess());
        $this->assertTrue(FinanceStatsWidget::canView());
        $this->assertTrue(FinanceDailyWidget::canView());
    }

    public function test_cost_price_is_never_serialised_to_array_or_json(): void
    {
        $book = Book::factory()->create(['cost_price' => '75.50', 'price' => '150.00']);

        $this->assertArrayNotHasKey('cost_price', $book->fresh()->toArray());
        $this->assertStringNotContainsString('cost_price', $book->fresh()->toJson());
        $this->assertStringNotContainsString('75.50', $book->fresh()->toJson());
        // السعر العام يبقى مرئيًا — لم نُخفِ الحقل الخطأ.
        $this->assertArrayHasKey('price', $book->fresh()->toArray());
    }

    public function test_content_editor_can_edit_books_but_not_see_cost(): void
    {
        $editor = User::factory()->create();
        $editor->assignRole('content_editor'); // products.update + products.price.update.

        $this->actingAs($editor);
        // يحرّر الكتب (البادئة products) لكن بلا صلاحية التكلفة الجديدة.
        $this->assertTrue($editor->can('products.update'));
        $this->assertFalse($editor->can('products.cost.view'), 'محرّر المحتوى يجب ألا يرى التكلفة');
        $this->assertFalse($editor->can('products.cost.update'));
    }

    public function test_admin_retains_cost_visibility(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin'); // products prefix ⇒ includes products.cost.*

        $this->actingAs($admin);
        $this->assertTrue($admin->can('products.cost.view'));
        $this->assertTrue($admin->can('products.cost.update'));
    }

    public function test_book_resource_uses_products_prefix(): void
    {
        // يضمن أن userCan('cost.view') يُترجم إلى products.cost.view.
        $this->assertSame('products', BookResource::permissionPrefix());
    }

    public function test_expenses_resource_is_gated_to_financial_admins_only(): void
    {
        // دفتر المصروفات (م٤ج): بيانات مالية، ممنوح لـ admin لا للأدوار التشغيلية.
        $editor = User::factory()->create();
        $editor->assignRole('content_editor');
        $this->actingAs($editor);
        $this->assertFalse(ExpenseResource::canViewAny(), 'محرّر المحتوى لا يرى المصروفات');

        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin);
        $this->assertTrue(ExpenseResource::canViewAny());
        $this->assertTrue($admin->can('expenses.create'));
    }

    public function test_orders_manager_cannot_see_expenses(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole('orders_manager'); // مالي للطلبات لكن ليس للمصروفات.
        $this->actingAs($manager);

        $this->assertFalse($manager->can('expenses.view'), 'المصروفات صلاحية مستقلة عن الطلبات');
    }

    public function test_carrier_cost_is_not_seeded_into_form_state_for_non_financial_users(): void
    {
        // عيب أمسكته المراجعة العدائية: fillForm كان يبثّ carrier_cost في حالة
        // Livewire (تُرسَل للمتصفح) حتى لمن لا يملك الصلاحية المالية — رغم إخفاء
        // الحقل. البوابة على المصدر تمنع البثّ. مسؤول الطلبات يملك orders.ship.
        // صلاحيات محدّدة بلا دور: يرى الطلبات ويشحن، لكن لا يرى المال — عزل
        // نظيف لحالة «يشحن لكن لا يملك orders.view_financials».
        $shipper = User::factory()->create();
        $shipper->givePermissionTo(['orders.view', 'orders.ship']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->assertTrue($shipper->can('orders.ship'));
        $this->assertFalse($shipper->can('orders.view_financials'));

        $order = OrderFactory::new()->create([
            'status' => 'confirmed', 'payment_method' => 'cod', 'payment_status' => 'unpaid',
            'carrier_cost' => '45.00',
        ]);

        $this->actingAs($shipper);

        Livewire::test(ViewOrder::class, ['record' => $order->getKey()])
            ->mountAction('updateShipping')
            // القيمة السرّية يجب ألا تدخل حالة النموذج المُرسَلة للمتصفح
            // (mountedActionsData خاصية Livewire عامة).
            ->assertSet('mountedActionsData.0.carrier_cost', null);
    }

    public function test_editing_a_book_without_touching_cost_preserves_it(): void
    {
        // عيب أمسكته المراجعة العدائية: $hidden يحذف cost_price من ملء النموذج،
        // فيُكتب NULL عند الحفظ ويُتلف التكلفة. afterStateHydrated يعيد ترطيبه من
        // القيمة الخام. هنا نحاكي دورة التحرير: أدمن يعدّل المخزون فقط.
        $admin = User::factory()->create();
        $admin->assignRole('admin'); // يملك products.cost.view + update
        $this->actingAs($admin);

        $book = Book::factory()->create(['cost_price' => '75.50', 'stock_quantity' => 10]);

        Livewire::test(
            EditBook::class,
            ['record' => $book->getKey()],
        )
            ->assertFormSet(['cost_price' => '75.50']) // يُعرض سليمًا لا فارغًا
            ->fillForm(['stock_quantity' => 25])       // تعديل غير متعلّق بالتكلفة
            ->call('save')
            ->assertHasNoFormErrors();

        $book->refresh();
        $this->assertSame('75.50', $book->getRawOriginal('cost_price'), 'التكلفة يجب ألا تُمسح');
        $this->assertSame(25, $book->stock_quantity);
    }
}
