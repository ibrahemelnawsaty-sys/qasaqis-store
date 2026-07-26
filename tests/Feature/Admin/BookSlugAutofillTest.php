<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Filament\Resources\BookResource\Pages\CreateBook;
use App\Filament\Resources\BookResource\Pages\EditBook;
use App\Models\Book;
use App\Models\User;
use App\Support\Text\Slug;
use Database\Seeders\CategorySeeder;
use Database\Seeders\PublisherSeeder;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * توليد الـslug تلقائيًا في محرّر الكتاب: يُملأ من العنوان العربي عند الإنشاء،
 * ولا يتغيّر عند تعديل عنوان كتاب قائم (حماية روابط الكتب المنشورة).
 */
class BookSlugAutofillTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolePermissionSeeder::class, CategorySeeder::class, PublisherSeeder::class]);
        Http::fake();
    }

    private function actAsAdmin(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_create_page_autofills_latin_slug_from_arabic_title(): void
    {
        $this->actAsAdmin();

        Livewire::test(CreateBook::class)
            ->fillForm(['title' => 'قصة الأسد والفأر'])
            ->assertFormSet(['slug' => 'qsa-alasd-walfar']);
    }

    public function test_editing_title_does_not_overwrite_existing_slug(): void
    {
        $book = Book::factory()->create(['slug' => 'my-fixed-slug', 'is_published' => true]);

        $this->actAsAdmin();

        Livewire::test(EditBook::class, ['record' => $book->getKey()])
            ->fillForm(['title' => 'عنوان مختلف تمامًا بعد النشر'])
            ->assertFormSet(['slug' => 'my-fixed-slug']);
    }

    public function test_manual_slug_edit_is_not_clobbered_by_a_later_title_change(): void
    {
        $this->actAsAdmin();

        Livewire::test(CreateBook::class)
            ->fillForm(['title' => 'قصة الأرنب'])   // يُملأ الـslug تلقائيًا
            ->fillForm(['slug' => 'arnab'])          // تعديل يدويّ للـslug
            ->fillForm(['title' => 'قصة الأرنب الشجاع']) // تغيير العنوان مجددًا
            ->assertFormSet(['slug' => 'arnab']);    // التعديل اليدويّ يبقى (لا يُدهَس)
    }

    public function test_slug_keeps_following_title_until_manually_edited(): void
    {
        $this->actAsAdmin();

        Livewire::test(CreateBook::class)
            ->fillForm(['title' => 'قصة الأرنب'])
            ->fillForm(['title' => 'قصة الأرنب الشجاع']) // لم يُعدَّل الـslug يدويًا بعد
            ->assertFormSet(['slug' => Slug::fromTitle('قصة الأرنب الشجاع')]);
    }
}
