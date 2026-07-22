<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Filament\Pages\SeoAudit;
use App\Models\Book;
use App\Models\Category;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * لوحة «تدقيق SEO»: بوّابة الصلاحية (seo.view) + التصيير يعرض نواقص المحتوى المنشور
 * مع رابط الإصلاح داخل سياق لوحة الأدمن.
 */
class SeoAuditPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        Http::fake();
    }

    public function test_access_requires_seo_view_permission(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $admin = User::factory()->create();
        $admin->assignRole('admin'); // يملك seo.view
        $this->actingAs($admin);
        $this->assertTrue(SeoAudit::canAccess());

        $support = User::factory()->create();
        $support->assignRole('support'); // لا يملك seo.view
        $this->actingAs($support);
        $this->assertFalse(SeoAudit::canAccess());
    }

    public function test_page_renders_findings_with_fix_link(): void
    {
        $book = Book::factory()->create([
            'category_id' => Category::factory()->create(['description' => 'x'])->id,
            'is_published' => true,
            'title' => 'كتاب بلا غلاف للاختبار',
            'short_description' => 'وصف قصير كافٍ.',
            'cover_image' => '',
        ]);

        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(SeoAudit::class)
            ->assertOk()
            ->assertSee('كتاب بلا غلاف للاختبار')
            ->assertSee('صورة غلاف')
            ->assertSee($book->getKey() . '/edit'); // رابط الإصلاح لمورد الكتاب
    }
}
