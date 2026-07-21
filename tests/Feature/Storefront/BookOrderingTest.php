<?php

declare(strict_types=1);

namespace Tests\Feature\Storefront;

use App\Filament\Resources\BookResource\Pages\ListBooks;
use App\Models\Book;
use App\Models\Category;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * ترتيب المتجر اليدوي (sort_order): يحكم شبكات التصفّح افتراضيًا في كل مكان، ويضبطه
 * الأدمن بالسحب في لوحة الكتب. الفرز الصريح (الأحدث/السعر…) يبقى تجاوزًا اختياريًّا.
 */
class BookOrderingTest extends TestCase
{
    use RefreshDatabase;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->category = Category::factory()->create(['is_active' => true]);
    }

    private function book(string $title, int $sort, int $publishedDaysAgo): Book
    {
        return Book::factory()->create([
            'title' => $title,
            'category_id' => $this->category->id,
            'sort_order' => $sort,
            'is_published' => true,
            'published_at' => now()->subDays($publishedDaysAgo),
        ]);
    }

    public function test_catalog_default_order_is_the_manual_store_order(): void
    {
        // ترتيب المتجر: Alpha(10) ثم Beta(20) ثم Gamma(30) — مخالف لترتيب النشر.
        $this->book('ZzzGamma', 30, publishedDaysAgo: 1);
        $this->book('ZzzAlpha', 10, publishedDaysAgo: 30);
        $this->book('ZzzBeta', 20, publishedDaysAgo: 15);

        $this->get('/books')->assertOk()
            ->assertSeeInOrder(['ZzzAlpha', 'ZzzBeta', 'ZzzGamma']);
    }

    public function test_explicit_newest_sort_overrides_store_order(): void
    {
        $this->book('ZzzAlpha', 10, publishedDaysAgo: 30); // أقدم
        $this->book('ZzzGamma', 30, publishedDaysAgo: 1);  // أحدث

        $this->get('/books?sort=newest')->assertOk()
            ->assertSeeInOrder(['ZzzGamma', 'ZzzAlpha']);
    }

    public function test_category_page_uses_store_order_too(): void
    {
        $this->book('ZzzGamma', 30, publishedDaysAgo: 1);
        $this->book('ZzzAlpha', 10, publishedDaysAgo: 30);

        $this->get('/category/'.$this->category->slug)->assertOk()
            ->assertSeeInOrder(['ZzzAlpha', 'ZzzGamma']);
    }

    public function test_admin_can_reorder_books_by_drag(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('super_admin');
        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $a = $this->book('A', 10, 1);
        $b = $this->book('B', 20, 1);
        $c = $this->book('C', 30, 1);

        // ترتيب جديد بالسحب: C ثم A ثم B. (reorderTable هو إجراء Livewire الذي
        // يستدعيه Filament عند الإفلات، فيعيد كتابة sort_order وفق الترتيب الجديد.)
        Livewire::test(ListBooks::class)
            ->call('reorderTable', [$c->id, $a->id, $b->id]);

        $this->assertTrue($c->fresh()->sort_order < $a->fresh()->sort_order);
        $this->assertTrue($a->fresh()->sort_order < $b->fresh()->sort_order);
    }
}
