<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Filament\Pages\FinanceDashboard;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * تصيير لوحة القسم المالي فعليًا (كشف أخطاء 500 التي لا يلتقطها canAccess وحده).
 */
final class FinanceDashboardRenderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_dashboard_renders_for_admin(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin);

        Livewire::test(FinanceDashboard::class)
            ->assertOk();
    }
}
