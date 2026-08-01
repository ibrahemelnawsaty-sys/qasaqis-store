<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Category\SyncCategoryBookOrder;
use App\Models\Book;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * ترتيب ظهور كتب القسم (مستقلّ لكل قسم عبر category_book_positions):
 * - المزامنة تملأ كل أعضاء القسم (الرئيسي + الإضافي) وتحذف اليتيم.
 * - صفحة القسم تفتح على الترتيب اليدويّ افتراضيًّا، ويتجاوزه فرز العميل الصريح.
 * - غير المرتّب يظهر بعد المرتّب. وعلاقة orderedBooks تُرجِع بترتيب position.
 */
final class CategoryBookOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_backfills_main_and_extra_members_and_drops_stale(): void
    {
        $cat = Category::factory()->create(['is_active' => true]);
        $main = Book::factory()->create(['is_published' => true, 'category_id' => $cat->id]);
        $extra = Book::factory()->create(['is_published' => true]);
        $extra->categories()->attach($cat->id); // عضويّة إضافيّة عبر book_category.
        $other = Book::factory()->create(['is_published' => true]); // ليس في القسم.

        // صفّ يتيم لكتاب ليس في القسم — يجب أن تحذفه المزامنة.
        DB::table('category_book_positions')->insert([
            'category_id' => $cat->id, 'book_id' => $other->id, 'position' => 5,
        ]);

        app(SyncCategoryBookOrder::class)->execute($cat);

        $this->assertDatabaseHas('category_book_positions', ['category_id' => $cat->id, 'book_id' => $main->id]);
        $this->assertDatabaseHas('category_book_positions', ['category_id' => $cat->id, 'book_id' => $extra->id]);
        $this->assertDatabaseMissing('category_book_positions', ['category_id' => $cat->id, 'book_id' => $other->id]);
    }

    public function test_sync_is_idempotent_and_appends_new_books_after_existing(): void
    {
        $cat = Category::factory()->create();
        $a = Book::factory()->create(['is_published' => true, 'category_id' => $cat->id]);
        app(SyncCategoryBookOrder::class)->execute($cat);
        $posA = (int) DB::table('category_book_positions')->where('book_id', $a->id)->value('position');

        // كتاب جديد يُلحَق بعد القديم دون تغيير موضع القديم.
        $b = Book::factory()->create(['is_published' => true, 'category_id' => $cat->id]);
        app(SyncCategoryBookOrder::class)->execute($cat);

        $this->assertSame($posA, (int) DB::table('category_book_positions')->where('book_id', $a->id)->value('position'));
        $this->assertGreaterThan($posA, (int) DB::table('category_book_positions')->where('book_id', $b->id)->value('position'));
    }

    public function test_category_page_uses_manual_order_by_default(): void
    {
        $cat = Category::factory()->create(['is_active' => true]);
        $newest = Book::factory()->create(['is_published' => true, 'category_id' => $cat->id, 'title' => 'كتاب ألف', 'published_at' => now()->subDay()]);
        $mid = Book::factory()->create(['is_published' => true, 'category_id' => $cat->id, 'title' => 'كتاب باء', 'published_at' => now()->subDays(10)]);
        $oldest = Book::factory()->create(['is_published' => true, 'category_id' => $cat->id, 'title' => 'كتاب جيم', 'published_at' => now()->subDays(20)]);

        // ترتيب يدويّ مقصود يخالف الأحدث: جيم ← ألف ← باء.
        DB::table('category_book_positions')->insert([
            ['category_id' => $cat->id, 'book_id' => $oldest->id, 'position' => 1],
            ['category_id' => $cat->id, 'book_id' => $newest->id, 'position' => 2],
            ['category_id' => $cat->id, 'book_id' => $mid->id, 'position' => 3],
        ]);

        $this->get(route('categories.show', $cat))->assertOk()
            ->assertSeeInOrder(['كتاب جيم', 'كتاب ألف', 'كتاب باء']);
    }

    public function test_customer_sort_overrides_manual_order(): void
    {
        $cat = Category::factory()->create(['is_active' => true]);
        $newest = Book::factory()->create(['is_published' => true, 'category_id' => $cat->id, 'title' => 'عنوان ألف', 'published_at' => now()->subDay()]);
        $oldest = Book::factory()->create(['is_published' => true, 'category_id' => $cat->id, 'title' => 'عنوان جيم', 'published_at' => now()->subDays(20)]);

        // اليدويّ يضع الأقدم أولًا؛ فرز «الأحدث» الصريح يجب أن يتجاوزه.
        DB::table('category_book_positions')->insert([
            ['category_id' => $cat->id, 'book_id' => $oldest->id, 'position' => 1],
            ['category_id' => $cat->id, 'book_id' => $newest->id, 'position' => 2],
        ]);

        $this->get(route('categories.show', ['category' => $cat, 'sort' => 'newest']))->assertOk()
            ->assertSeeInOrder(['عنوان ألف', 'عنوان جيم']);
    }

    public function test_manual_order_survives_the_curated_sort_the_form_submits(): void
    {
        // نموذج القسم يرسل sort=curated افتراضيًّا مع أي فلترة — يجب أن يبقى الترتيب
        // اليدويّ (لا ينقلب إلى الأحدث)، وألّا يُرفض التحقّق (422).
        $cat = Category::factory()->create(['is_active' => true]);
        $newest = Book::factory()->create(['is_published' => true, 'category_id' => $cat->id, 'title' => 'واو ألف', 'published_at' => now()->subDay()]);
        $oldest = Book::factory()->create(['is_published' => true, 'category_id' => $cat->id, 'title' => 'واو جيم', 'published_at' => now()->subDays(20)]);

        DB::table('category_book_positions')->insert([
            ['category_id' => $cat->id, 'book_id' => $oldest->id, 'position' => 1],
            ['category_id' => $cat->id, 'book_id' => $newest->id, 'position' => 2],
        ]);

        $this->get(route('categories.show', ['category' => $cat, 'sort' => 'curated']))->assertOk()
            ->assertSeeInOrder(['واو جيم', 'واو ألف']);
    }

    public function test_unpositioned_books_appear_after_positioned_ones(): void
    {
        $cat = Category::factory()->create(['is_active' => true]);
        $positioned = Book::factory()->create(['is_published' => true, 'category_id' => $cat->id, 'title' => 'مرتّب زاي', 'published_at' => now()->subDays(30)]);
        Book::factory()->create(['is_published' => true, 'category_id' => $cat->id, 'title' => 'غير مرتب حاء', 'published_at' => now()->subDay()]);

        // المرتّب (position=1) يسبق غير المرتّب رغم أن الأخير أحدث.
        DB::table('category_book_positions')->insert([
            ['category_id' => $cat->id, 'book_id' => $positioned->id, 'position' => 1],
        ]);

        $this->get(route('categories.show', $cat))->assertOk()
            ->assertSeeInOrder(['مرتّب زاي', 'غير مرتب حاء']);
    }

    public function test_ordered_books_relation_returns_by_position(): void
    {
        $cat = Category::factory()->create();
        $a = Book::factory()->create(['is_published' => true, 'category_id' => $cat->id]);
        $b = Book::factory()->create(['is_published' => true, 'category_id' => $cat->id]);

        DB::table('category_book_positions')->insert([
            ['category_id' => $cat->id, 'book_id' => $b->id, 'position' => 1],
            ['category_id' => $cat->id, 'book_id' => $a->id, 'position' => 2],
        ]);

        $this->assertSame([$b->id, $a->id], $cat->orderedBooks->pluck('id')->all());
    }
}
