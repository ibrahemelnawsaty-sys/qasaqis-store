<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Filament\Widgets\FinanceDailyWidget;
use App\Filament\Widgets\FinanceStatsWidget;
use App\Filament\Widgets\FinanceTrendWidget;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * تصيير ودجت المالية كمكوّنات Livewire مستقلة (طلب update:1 هو ما يُسقط الإنتاج
 * بـ500، لا تحميل الصفحة الأول). يحاكي دورة تحديث Livewire التي تُصيّر الودجت،
 * ويغطّي الحالة التي أسقطت الإنتاج فعلًا: filters = null (لا تُمرَّر الفلاتر).
 */
final class FinanceWidgetRenderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin);
    }

    public function test_overview_widget_renders_via_livewire(): void
    {
        Livewire::test(FinanceStatsWidget::class, ['filters' => ['preset' => '30d']])
            ->assertOk();
    }

    public function test_trend_widget_renders_via_livewire(): void
    {
        Livewire::test(FinanceTrendWidget::class, ['filters' => ['preset' => '30d']])
            ->assertOk();
    }

    public function test_daily_widget_renders_via_livewire(): void
    {
        Livewire::test(FinanceDailyWidget::class, ['filters' => ['preset' => '30d']])
            ->assertOk();
    }

    public function test_all_widgets_render_when_filters_is_null(): void
    {
        // الحالة الحقيقية التي أسقطت الإنتاج بـ500: InteractsWithPageFilters يبدأ
        // $filters = null، ويبقى null عند تصيير الودجت مستقلًّا — فكان
        // FinanceRange::fromFilters(null) يرمي TypeError. لا تُمرَّر filters هنا.
        Livewire::test(FinanceStatsWidget::class)->assertOk();
        Livewire::test(FinanceTrendWidget::class)->assertOk();
        Livewire::test(FinanceDailyWidget::class)->assertOk();
    }
}
