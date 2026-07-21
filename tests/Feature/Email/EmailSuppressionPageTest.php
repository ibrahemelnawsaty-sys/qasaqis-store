<?php

declare(strict_types=1);

namespace Tests\Feature\Email;

use App\Filament\Resources\EmailSuppressionResource;
use App\Filament\Resources\EmailSuppressionResource\Pages\ListEmailSuppressions;
use App\Models\EmailSuppression;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * قسم «من ألغوا الاشتراك»: بوّابة الصلاحية + إعادة التفعيل (حذف من قائمة الحظر) +
 * الحظر اليدوي.
 */
class EmailSuppressionPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function actingAsManager(): User
    {
        // marketing يحمل campaigns.suppressions.manage (byPrefix 'campaigns').
        $user = User::factory()->create(['email' => 'mkt@qasaqis.store']);
        $user->assignRole('marketing');
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        return $user;
    }

    public function test_access_requires_the_manage_permission(): void
    {
        // بلا مصادقة.
        $this->assertFalse(EmailSuppressionResource::canViewAny());

        // دور بلا الصلاحية.
        $support = User::factory()->create();
        $support->assignRole('support');
        $this->actingAs($support);
        $this->assertFalse(EmailSuppressionResource::canViewAny());

        // دور يملكها.
        $this->actingAsManager();
        $this->assertTrue(EmailSuppressionResource::canViewAny());
    }

    public function test_reactivate_removes_suppression_so_the_email_receives_again(): void
    {
        $this->actingAsManager();
        $suppression = EmailSuppression::create(['email' => 'back@x.test', 'reason' => 'unsubscribe']);

        Livewire::test(ListEmailSuppressions::class)
            ->callTableAction('reactivate', $suppression);

        $this->assertDatabaseMissing('email_suppressions', ['email' => 'back@x.test']);
    }

    public function test_manual_block_adds_a_suppression(): void
    {
        $this->actingAsManager();

        Livewire::test(ListEmailSuppressions::class)
            ->callAction('create', ['email' => 'blocked@x.test', 'reason' => 'manual']);

        $this->assertDatabaseHas('email_suppressions', ['email' => 'blocked@x.test', 'reason' => 'manual']);
    }
}
