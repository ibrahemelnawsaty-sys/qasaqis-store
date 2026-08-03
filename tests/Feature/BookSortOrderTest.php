<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * الترتيب العامّ «المثبَّت أولًا ثم الأحدث» على /books (نموذج التثبيت):
 * books.sort_order = 0 يعني غير مثبَّت (يُرتَّب بالأحدث)، و> 0 يعني مثبَّت. كتابة رقم في
 * عمود «الترتيب» تُثبّت الكتاب وتعيد ترقيم المثبَّتة بلا تعارض (Book::moveToSortPosition)،
 * و0 تُلغي التثبيت. صفحة /books تفتح على المثبَّت أولًا ثم الأحدث.
 */
final class BookSortOrderTest extends TestCase
{
    use RefreshDatabase;

    private function book(string $title, int $daysOld = 0): Book
    {
        return Book::factory()->create([
            'category_id' => Category::factory(),
            'is_published' => true,
            'sort_order' => 0,
            'title' => $title,
            'published_at' => now()->subDays($daysOld),
        ]);
    }

    public function test_typing_a_number_pins_the_book_and_leaves_others_unpinned(): void
    {
        $a = $this->book('أ');
        $b = $this->book('ب');

        Book::moveToSortPosition($a->id, 1);

        $this->assertSame(1, (int) $a->fresh()->sort_order); // مثبَّت
        $this->assertSame(0, (int) $b->fresh()->sort_order); // يبقى غير مثبَّت
    }

    public function test_pinning_to_an_occupied_rank_shifts_existing_pins_without_conflict(): void
    {
        $a = $this->book('أ');
        $b = $this->book('ب');

        Book::moveToSortPosition($a->id, 1); // أ = 1
        Book::moveToSortPosition($b->id, 1); // ب = 1، وأ تُزاح إلى 2

        $this->assertSame(1, (int) $b->fresh()->sort_order);
        $this->assertSame(2, (int) $a->fresh()->sort_order);
    }

    public function test_typing_zero_unpins_and_renumbers_remaining(): void
    {
        $a = $this->book('أ');
        $b = $this->book('ب');
        Book::moveToSortPosition($a->id, 1); // أ = 1
        Book::moveToSortPosition($b->id, 2); // ب = 2

        Book::moveToSortPosition($a->id, 0); // إلغاء تثبيت أ

        $this->assertSame(0, (int) $a->fresh()->sort_order); // عاد غير مثبَّت
        $this->assertSame(1, (int) $b->fresh()->sort_order); // ب أُعيد ترقيمه إلى 1
    }

    public function test_books_page_shows_pinned_first_then_newest(): void
    {
        $newest = $this->book('كتاب حديث', 1);
        $old = $this->book('كتاب قديم', 30);

        Book::moveToSortPosition($old->id, 1); // ثبّت القديم في القمّة

        // المثبَّت (القديم) أولًا رغم أنه أقدم، ثم غير المثبَّت بالأحدث.
        $this->get(route('books.index'))->assertOk()
            ->assertSeeInOrder(['كتاب قديم', 'كتاب حديث']);
    }

    public function test_books_page_defaults_to_newest_when_nothing_pinned(): void
    {
        $this->book('ألف حديث', 1);
        $this->book('ياء قديم', 30);

        // لا تثبيت → الأحدث أولًا (السلوك الافتراضي محفوظ).
        $this->get(route('books.index'))->assertOk()
            ->assertSeeInOrder(['ألف حديث', 'ياء قديم']);
    }
}
