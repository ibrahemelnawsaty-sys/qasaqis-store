<?php

declare(strict_types=1);

namespace Tests\Feature\Reviews;

use App\Models\Book;
use App\Models\Review;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * القطعة المضافة فوق نظام المراجعات: بعد تسجيل ReviewObserver (يحدّث
 * books.avg_rating/reviews_count)، صفحة الكتاب تُصدر aggregateRating في JSON-LD
 * — وهو ما يُنتج نجوم التقييم في نتائج بحث Google. يكمّل SubmitReviewTest الذي
 * يغطّي الإرسال والإشراف والعمودَين المشتقّين، لكنه لا يفحص وسم البحث.
 */
class AggregateRatingRenderTest extends TestCase
{
    use RefreshDatabase;

    private function publishReview(Book $book, int $rating): void
    {
        // Review::create يُطلق ReviewObserver المسجَّل → يحدّث عمودَي الكتاب.
        Review::create([
            'book_id' => $book->id,
            'author_name' => 'أم يوسف',
            'rating' => $rating,
            'body' => 'مراجعة منشورة لأغراض الاختبار — محتوى كافٍ.',
            'status' => 'published',
        ]);
    }

    public function test_book_page_emits_aggregate_rating_json_ld_when_reviews_exist(): void
    {
        $book = Book::factory()->create(['is_published' => true]);
        $this->publishReview($book, 5);
        $this->publishReview($book, 4);

        $book->refresh();
        $this->assertSame(2, (int) $book->reviews_count);
        $this->assertEquals(4.5, (float) $book->avg_rating);

        $html = $this->get(route('books.show', $book))->assertOk()->getContent();

        $this->assertStringContainsString('"aggregateRating"', $html);
        $this->assertStringContainsString('"ratingValue":"4.5"', $html);
        $this->assertStringContainsString('"reviewCount":2', $html);
        // الملخّص المرئي يطابق الوسم (شرط Google).
        $this->assertStringContainsString('4.5', $html);
    }

    public function test_book_page_has_no_aggregate_rating_without_reviews(): void
    {
        $book = Book::factory()->create(['is_published' => true]);

        $html = $this->get(route('books.show', $book))->assertOk()->getContent();

        $this->assertStringNotContainsString('"aggregateRating"', $html);
    }
}
