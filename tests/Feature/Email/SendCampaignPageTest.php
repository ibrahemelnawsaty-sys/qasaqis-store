<?php

declare(strict_types=1);

namespace Tests\Feature\Email;

use App\Filament\Pages\SendEmailCampaign;
use App\Models\Customer;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * يعيد إنتاج تدفّق المتصفّح لصفحة إرسال الحملة (Livewire) — يمسك أي خطأ في
 * getState()/الفورم لا يظهر باستدعاء المُرسِل مباشرة.
 */
class SendCampaignPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $admin = User::factory()->create(['email' => 'boss@qasaqis.store', 'password' => Hash::make('x')]);
        $admin->assignRole('super_admin');
        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_send_campaign_via_livewire(): void
    {
        Customer::factory()->withPhone('01055667788')->create([
            'email' => 'c1@x.test', 'email_verified_at' => now(),
        ]);

        Livewire::test(SendEmailCampaign::class)
            ->fillForm([
                'audiences' => ['all_customers'],
                'subject' => 'عرض تجريبي',
                'body_html' => '<p>مرحبا {name}</p>',
            ])
            ->call('send')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('email_campaigns', ['subject' => 'عرض تجريبي']);
    }
}
