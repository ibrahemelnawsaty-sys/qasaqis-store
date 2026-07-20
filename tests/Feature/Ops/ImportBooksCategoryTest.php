<?php

declare(strict_types=1);

namespace Tests\Feature\Ops;

use App\Models\Book;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * أمر books:import — إسناد القسم لكل كتاب من الملف (لا قسم واحد للجميع).
 *
 * القسم الرئيسي من حقل «category» في المنتج (وإلا --category)، والأقسام الشكلية
 * من «extra_categories» عبر العلاقة المتعدّدة. الأقسام الجديدة (financial-literacy
 * وأخواتها) تُنشئها هجرة 2026_07_20_000001 التي يشغّلها RefreshDatabase.
 *
 * أمانة (1.3/1.5): لم تُنفَّذ هنا — لا PHP في بيئة التطوير. تُشغَّل على الاستضافة
 * بـ `php artisan test --filter=ImportBooksCategoryTest`.
 */
final class ImportBooksCategoryTest extends TestCase
{
    use RefreshDatabase;

    private function stories(): Category
    {
        // القسم الافتراضي (--category). القسم من CategorySeeder لا يُبذر تلقائيًّا
        // مع RefreshDatabase، فننشئه صراحةً.
        return Category::query()->firstOrCreate(
            ['slug' => 'stories'],
            ['name' => 'قصص', 'sort_order' => 4, 'is_active' => true],
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $products
     */
    private function feedFile(array $products): string
    {
        $path = storage_path('app/test-feed-'.uniqid().'.json');
        File::put($path, json_encode(['products' => $products], JSON_UNESCAPED_UNICODE));

        return $path;
    }

    public function test_it_assigns_primary_and_extra_categories_from_the_feed(): void
    {
        $this->stories();

        $feed = $this->feedFile([[
            'handle' => 'money-elephant',
            'title' => 'الفيل يتعلم الإدخار',
            'category' => 'financial-literacy',          // موجود بالهجرة
            'extra_categories' => ['activity-books'],    // موجود بالهجرة
            'variants' => [['price' => '200.00']],
            'images' => [],
        ]]);

        $this->artisan('books:import', [
            'file' => $feed, '--category' => 'stories', '--stock' => 5,
        ])->assertSuccessful();

        $book = Book::query()->where('title', 'الفيل يتعلم الإدخار')->firstOrFail();

        // القسم الرئيسي = قسم الكتاب لا الافتراضي.
        $this->assertSame(
            Category::query()->where('slug', 'financial-literacy')->value('id'),
            $book->category_id,
        );
        // القسم الشكلي مربوط عبر العلاقة المتعدّدة.
        $this->assertTrue($book->categories()->where('slug', 'activity-books')->exists());
        // المخزون طُبّق.
        $this->assertSame(5, $book->stock_quantity);
    }

    public function test_it_uses_the_default_category_when_the_feed_has_none(): void
    {
        $stories = $this->stories();

        $feed = $this->feedFile([[
            'handle' => 'plain-book',
            'title' => 'كتاب بلا قسم',
            'variants' => [['price' => '150.00']],
            'images' => [],
        ]]);

        $this->artisan('books:import', [
            'file' => $feed, '--category' => 'stories', '--stock' => 5,
        ])->assertSuccessful();

        $this->assertSame(
            $stories->id,
            Book::query()->where('title', 'كتاب بلا قسم')->value('category_id'),
        );
    }

    public function test_it_aborts_when_the_feed_references_a_missing_category(): void
    {
        $this->stories();

        $feed = $this->feedFile([[
            'handle' => 'ghost',
            'title' => 'كتاب بقسم غير موجود',
            'category' => 'does-not-exist',
            'variants' => [['price' => '100.00']],
            'images' => [],
        ]]);

        // يوقف قبل إنشاء أي كتاب بدل الفشل صفًّا صفًّا.
        $this->artisan('books:import', [
            'file' => $feed, '--category' => 'stories', '--stock' => 5,
        ])->assertFailed();

        $this->assertDatabaseMissing('books', ['title' => 'كتاب بقسم غير موجود']);
    }

    public function test_extra_category_sync_does_not_detach_curated_ones(): void
    {
        $this->stories();
        // كتاب موجود سبق أن أضاف له الأدمن قسمًا يدويًّا.
        $science = Category::query()->firstOrCreate(
            ['slug' => 'science'], ['name' => 'كتب علمية', 'sort_order' => 2, 'is_active' => true],
        );
        $book = Book::query()->create([
            'title' => 'كتاب موجود', 'slug' => 'existing-book',
            'category_id' => $this->stories()->id, 'stock_quantity' => 3,
            'stock_status' => 'in_stock', 'manage_stock' => true,
        ]);
        $book->categories()->attach($science->id);

        $feed = $this->feedFile([[
            'handle' => 'existing-book',   // نفس slug → تحديث
            'title' => 'كتاب موجود',
            'category' => 'programming',
            'extra_categories' => ['activity-books'],
            'variants' => [['price' => '175.00']],
            'images' => [],
        ]]);

        $this->artisan('books:import', [
            'file' => $feed, '--category' => 'stories', '--stock' => 5,
        ])->assertSuccessful();

        $book->refresh();
        // القسم اليدوي القديم باقٍ، والجديد أُضيف — بلا حذف.
        $this->assertTrue($book->categories()->where('slug', 'science')->exists());
        $this->assertTrue($book->categories()->where('slug', 'activity-books')->exists());
    }
}
