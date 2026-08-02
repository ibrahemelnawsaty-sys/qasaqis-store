<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Filament\Resources\CategoryResource\Pages\EditCategory;
use App\Filament\Resources\CategoryResource\RelationManagers\BooksOrderRelationManager;
use App\Models\Book;
use App\Models\Category;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * مدير ترتيب كتب القسم: يعرض عمود «الترتيب» القابل للكتابة + كتب القسم، والسحب يكتب
 * category_book_positions.position، والعرض مبوَّب بصلاحية categories.view.
 */
final class CategoryBooksOrderRelationManagerTest extends TestCase
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

    public function test_manager_renders_with_the_editable_position_column(): void
    {
        $this->asRole('super_admin');
        $cat = Category::factory()->create();
        $book = Book::factory()->create(['category_id' => $cat->id, 'is_published' => true]);
        DB::table('category_book_positions')->insert([
            'category_id' => $cat->id, 'book_id' => $book->id, 'position' => 1,
        ]);

        // التصيير يستدعي getStateUsing لعمود position (قراءة قيمة pivot) — يكشف أي خطأ فيه.
        Livewire::test(BooksOrderRelationManager::class, ['ownerRecord' => $cat, 'pageClass' => EditCategory::class])
            ->assertSuccessful()
            ->assertTableColumnExists('position')
            ->assertCanSeeTableRecords([$book]);
    }

    public function test_reorder_writes_pivot_position(): void
    {
        $this->asRole('super_admin');
        $cat = Category::factory()->create();
        $b1 = Book::factory()->create(['title' => 'One', 'category_id' => $cat->id, 'is_published' => true]);
        $b2 = Book::factory()->create(['title' => 'Two', 'category_id' => $cat->id, 'is_published' => true]);
        DB::table('category_book_positions')->insert([
            ['category_id' => $cat->id, 'book_id' => $b1->id, 'position' => 1],
            ['category_id' => $cat->id, 'book_id' => $b2->id, 'position' => 2],
        ]);

        // سحب: b2 قبل b1.
        Livewire::test(BooksOrderRelationManager::class, ['ownerRecord' => $cat, 'pageClass' => EditCategory::class])
            ->call('reorderTable', [$b2->id, $b1->id]);

        $pos = fn (int $id): int => (int) DB::table('category_book_positions')
            ->where('category_id', $cat->id)->where('book_id', $id)->value('position');

        $this->assertLessThan($pos($b1->id), $pos($b2->id));
    }

    public function test_view_requires_categories_view_permission(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $cat = Category::factory()->create();

        $admin = User::factory()->create();
        $admin->assignRole('super_admin');
        $this->actingAs($admin);
        $this->assertTrue(BooksOrderRelationManager::canViewForRecord($cat, EditCategory::class));

        $noPerms = User::factory()->create(); // بلا أدوار = بلا صلاحيات.
        $this->actingAs($noPerms);
        $this->assertFalse(BooksOrderRelationManager::canViewForRecord($cat, EditCategory::class));
    }
}
