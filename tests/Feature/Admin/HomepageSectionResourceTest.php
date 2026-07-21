<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Filament\Resources\BookResource\Pages\ListBooks;
use App\Filament\Resources\HomepageSectionResource;
use App\Filament\Resources\HomepageSectionResource\Pages\EditHomepageSection;
use App\Filament\Resources\HomepageSectionResource\RelationManagers\BooksRelationManager;
use App\Models\Book;
use App\Models\Category;
use App\Models\HomepageSection;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * لوحة أقسام كتب الرئيسية: بوّابة الصلاحيات (sections.view/manage) + سحب ترتيب الكتب
 * في مورد الكتب يكتب sort_order.
 */
class HomepageSectionResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function asRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        return $user;
    }

    public function test_view_and_manage_require_sections_permissions(): void
    {
        $editor = User::factory()->create();
        $editor->assignRole('content_editor'); // يملك sections.* (byPrefix)
        $support = User::factory()->create();
        $support->assignRole('support'); // لا يملكها

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->actingAs($editor);
        $this->assertTrue(HomepageSectionResource::canViewAny());
        $this->assertTrue(HomepageSectionResource::canCreate());

        $this->actingAs($support);
        $this->assertFalse(HomepageSectionResource::canViewAny());
        $this->assertFalse(HomepageSectionResource::canCreate());
    }

    public function test_admin_can_reorder_books_which_writes_sort_order(): void
    {
        $this->asRole('super_admin');
        $cat = Category::factory()->create();
        $a = Book::factory()->create(['title' => 'A', 'category_id' => $cat->id, 'sort_order' => 10]);
        $b = Book::factory()->create(['title' => 'B', 'category_id' => $cat->id, 'sort_order' => 20]);
        $c = Book::factory()->create(['title' => 'C', 'category_id' => $cat->id, 'sort_order' => 30]);

        Livewire::test(ListBooks::class)
            ->call('reorderTable', [$c->id, $a->id, $b->id]);

        $this->assertTrue($c->fresh()->sort_order < $a->fresh()->sort_order);
        $this->assertTrue($a->fresh()->sort_order < $b->fresh()->sort_order);
    }

    public function test_attach_action_pins_a_book_to_the_section(): void
    {
        $this->asRole('super_admin');
        $cat = Category::factory()->create();
        $section = HomepageSection::create(['title' => 'x', 'source_type' => 'manual', 'item_limit' => 8, 'is_active' => true]);
        $book = Book::factory()->create(['title' => 'Attachable', 'category_id' => $cat->id]);

        // يغطّي كامل الدورة (mount + إرسال) — كان mount يسقط بـ500 لغياب العلاقة العكسية.
        Livewire::test(BooksRelationManager::class, ['ownerRecord' => $section, 'pageClass' => EditHomepageSection::class])
            ->callTableAction('attach', data: ['recordId' => $book->id])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('homepage_section_book', [
            'homepage_section_id' => $section->id,
            'book_id' => $book->id,
        ]);
    }

    public function test_relation_manager_reorder_writes_pivot_position(): void
    {
        $this->asRole('super_admin');
        $cat = Category::factory()->create();
        $section = HomepageSection::create(['title' => 'يدوي', 'source_type' => 'manual', 'item_limit' => 8, 'is_active' => true]);
        $b1 = Book::factory()->create(['title' => 'One', 'category_id' => $cat->id]);
        $b2 = Book::factory()->create(['title' => 'Two', 'category_id' => $cat->id]);
        $section->books()->attach([$b1->id => ['position' => 1], $b2->id => ['position' => 2]]);

        // سحب: b2 قبل b1.
        Livewire::test(BooksRelationManager::class, ['ownerRecord' => $section, 'pageClass' => EditHomepageSection::class])
            ->call('reorderTable', [$b2->id, $b1->id]);

        $pos = fn (int $bookId): int => (int) $section->books()->where('books.id', $bookId)->first()->pivot->position;
        $this->assertLessThan($pos($b1->id), $pos($b2->id));
    }
}
