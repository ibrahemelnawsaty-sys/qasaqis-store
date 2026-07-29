<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Filament\Pages\OpsDashboard;
use App\Models\User;
use Database\Factories\OrderFactory;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * شريط «أين طلباتك الآن؟»: توزيع الطلبات حسب المرحلة الحاليّة — كل طلب مرّة واحدة
 * فقط (لا تراكم كالقُمع)، فمجموع الأرقام = الإجمالي بلا فجوة (يغطّي حالات
 * STATUS_LABELS التسع). حارس ضدّ الازدواج الذي أربك المالك (طلب واحد يظهر في كل مرحلة).
 */
final class OpsDashboardCurrentStatusTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');

        return $user->fresh();
    }

    public function test_current_status_strip_counts_each_order_once_and_sums_to_total(): void
    {
        // طلب واحد من كل حالة من حالات STATUS_LABELS التسع.
        foreach (['pending', 'confirmed', 'processing', 'shipped', 'delivered', 'completed', 'cancelled', 'refused', 'refunded'] as $status) {
            OrderFactory::new()->create(['status' => $status]);
        }

        $this->actingAs($this->admin());
        $funnel = Livewire::test(OpsDashboard::class)->viewData('funnel');

        $this->assertSame(9, $funnel['total']);

        // الحاليّة: كل طلب مرّة واحدة. delivered يضمّ completed؛ lost يضمّ الثلاثة.
        $this->assertSame([
            'pending' => 1,
            'confirmed' => 1,
            'processing' => 1,
            'shipped' => 1,
            'delivered' => 2,
            'lost' => 3,
        ], $funnel['current']);

        // لا ازدواج ولا فجوة: مجموع الحاليّة = الإجمالي بالضبط.
        $this->assertSame($funnel['total'], array_sum($funnel['current']));

        // القُمع تراكميّ فيختلف: «مؤكّد فأكثر» = 5 (كل ما بعد الوارد عدا المفقود)،
        // بينما «مؤكّد» الحاليّ = 1 فقط. هذا هو الفرق الذي يزيل إرباك المالك.
        $this->assertSame(5, $funnel['confirmed']);
        $this->assertNotSame($funnel['current']['confirmed'], $funnel['confirmed']);
    }
}
