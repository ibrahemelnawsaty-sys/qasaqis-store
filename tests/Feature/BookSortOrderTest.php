<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * الترتيب العامّ للكتب (books.sort_order): كتابة رقم في عمود «الترتيب» بجدول الكتب
 * تنقل الكتاب لتلك الرتبة وتعيد ترقيم الباقي تلقائيًّا (1..N بلا تعارض) — عبر
 * Book::moveToSortPosition. يؤثّر على ترتيب كتب أقسام الرئيسية «مختارات/عروض/قسم».
 */
final class BookSortOrderTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<int, Book> كتب بترتيب sort_order 1..n. */
    private function seedBooks(int $n): array
    {
        $cat = Category::factory()->create();
        $books = [];
        for ($i = 1; $i <= $n; $i++) {
            $books[] = Book::factory()->create(['category_id' => $cat->id, 'sort_order' => $i]);
        }

        return $books;
    }

    /** @return array<int, int> معرّفات الكتب بترتيب sort_order الحاليّ. */
    private function order(): array
    {
        return DB::table('books')->orderBy('sort_order')->orderBy('id')
            ->pluck('id')->map(static fn ($id): int => (int) $id)->all();
    }

    public function test_typing_a_number_moves_the_book_and_renumbers_without_conflict(): void
    {
        [$a, $b, $c, $d] = $this->seedBooks(4); // 1..4

        // كتابة «2» للكتاب d (والرقم 2 يملكه b): يُدرَج d في الرتبة 2 ويُزاح الباقي.
        Book::moveToSortPosition($d->id, 2);

        $this->assertSame([$a->id, $d->id, $b->id, $c->id], $this->order());

        // مواضع فريدة متسلسلة 1..4 (لا تعارض).
        $positions = Book::query()->orderBy('sort_order')->pluck('sort_order')
            ->map(static fn ($p): int => (int) $p)->all();
        $this->assertSame([1, 2, 3, 4], $positions);
    }

    public function test_typing_one_moves_the_book_to_the_top(): void
    {
        [$a, $b, $c] = $this->seedBooks(3);

        Book::moveToSortPosition($c->id, 1);

        $this->assertSame([$c->id, $a->id, $b->id], $this->order());
    }

    public function test_typing_beyond_count_sends_the_book_last(): void
    {
        [$a, $b, $c] = $this->seedBooks(3);

        Book::moveToSortPosition($a->id, 99);

        $this->assertSame([$b->id, $c->id, $a->id], $this->order());
    }
}
