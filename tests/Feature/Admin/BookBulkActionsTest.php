<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Filament\Resources\BookResource\Pages\ListBooks;
use App\Models\Book;
use App\Models\Category;
use App\Models\Publisher;
use App\Models\Series;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * إجراءات الكتب الجماعية (سلسلة/دار/قسم إضافيّ) + البحث المُطبَّع عربيًّا.
 * كلها محميّة بصلاحية products.update؛ super_admin يملكها.
 */
final class BookBulkActionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_set_series_assigns_series_and_auto_increments_position(): void
    {
        $series = Series::factory()->create();
        // كتاب قائم في السلسلة عند الموضع 3 — المحدَّد يُلحَق بعده.
        Book::factory()->create(['series_id' => $series->id, 'series_position' => 3]);
        $a = Book::factory()->create(['series_id' => null, 'series_position' => null]);
        $b = Book::factory()->create(['series_id' => null, 'series_position' => null]);

        Livewire::test(ListBooks::class)
            ->callTableBulkAction('setSeries', [$a->getKey(), $b->getKey()], data: ['series_id' => $series->id])
            ->assertHasNoTableBulkActionErrors();

        $this->assertSame($series->id, $a->fresh()->series_id);
        $this->assertSame(4, $a->fresh()->series_position);
        $this->assertSame(5, $b->fresh()->series_position);
    }

    public function test_set_publisher_assigns_the_publisher(): void
    {
        $publisher = Publisher::factory()->create();
        $book = Book::factory()->create(['publisher_id' => null]);

        Livewire::test(ListBooks::class)
            ->callTableBulkAction('setPublisher', [$book->getKey()], data: ['publisher_id' => $publisher->id])
            ->assertHasNoTableBulkActionErrors();

        $this->assertSame($publisher->id, $book->fresh()->publisher_id);
    }

    public function test_add_category_attaches_an_extra_without_changing_the_main(): void
    {
        $main = Category::factory()->create();
        $extra = Category::factory()->create();
        $book = Book::factory()->create(['category_id' => $main->id]);

        Livewire::test(ListBooks::class)
            ->callTableBulkAction('addCategory', [$book->getKey()], data: ['category_id' => $extra->id])
            ->assertHasNoTableBulkActionErrors();

        $book->refresh();
        $this->assertSame($main->id, $book->category_id);                          // الرئيسيّ لم يتغيّر
        $this->assertTrue($book->categories()->whereKey($extra->id)->exists());    // أُضيف كإضافيّ
    }

    public function test_normalized_search_finds_books_across_hamza_and_relations(): void
    {
        $publisher = Publisher::factory()->create(['name' => 'دار المعرفة']);
        $series = Series::factory()->create(['name' => 'سلسلة النجوم']);
        $hamza = Book::factory()->create(['title' => 'مغامرات الأطفال']);
        $byPub = Book::factory()->create(['title' => 'كتاب مختلف', 'publisher_id' => $publisher->id]);
        $bySeries = Book::factory()->create(['title' => 'كتاب ثالث', 'series_id' => $series->id]);

        // «الاطفال» (ألف بلا همزة) تجد «الأطفال» عبر التطبيع.
        Livewire::test(ListBooks::class)
            ->searchTable('الاطفال')
            ->assertCanSeeTableRecords([$hamza])
            ->assertCanNotSeeTableRecords([$byPub, $bySeries]);

        // البحث باسم الدار.
        Livewire::test(ListBooks::class)
            ->searchTable('المعرفة')
            ->assertCanSeeTableRecords([$byPub]);

        // البحث باسم السلسلة.
        Livewire::test(ListBooks::class)
            ->searchTable('النجوم')
            ->assertCanSeeTableRecords([$bySeries]);
    }
}
