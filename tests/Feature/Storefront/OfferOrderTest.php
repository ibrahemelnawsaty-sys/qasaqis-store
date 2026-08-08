<?php

declare(strict_types=1);

namespace Tests\Feature\Storefront;

use App\Models\Book;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ترتيب صفحة العروض (/offers) المستقلّ: books.offer_sort_order. المرتَّب (>0) أوّلًا، ثم غير
 * المرتَّب (0 — خصمٌ جديد) بالأحدث. الظهور مقرون بالخصم (old_price). Book::moveToOfferPosition
 * يعيد الترقيم 1..N، وsyncOfferPositions يُلحِق غير المرتَّب.
 *
 * HONESTY (1.3): NOT executed here (MySQL down locally); runs via `php artisan test`.
 */
final class OfferOrderTest extends TestCase
{
    use RefreshDatabase;

    private function offer(string $title, int $daysOld = 0, int $pos = 0, bool $published = true): Book
    {
        return Book::factory()->create([
            'category_id' => Category::factory(),
            'is_published' => $published,
            'title' => $title,
            'price' => '100.00',
            'old_price' => '150.00', // مخصوم → يظهر في العروض
            'offer_sort_order' => $pos,
            'published_at' => now()->subDays($daysOld),
        ]);
    }

    public function test_offers_page_orders_by_manual_order_then_newest(): void
    {
        $this->offer('حديث غير مرتَّب', 1);
        $this->offer('قديم غير مرتَّب', 30);
        $this->offer('مرتَّب أولًا', 60, 1); // أقدم لكنه مرتَّب #1 → يظهر أولًا

        $this->get(route('books.offers'))->assertOk()
            ->assertSeeInOrder(['مرتَّب أولًا', 'حديث غير مرتَّب', 'قديم غير مرتَّب'], false);
    }

    public function test_only_discounted_books_appear_in_offers(): void
    {
        $this->offer('كتاب مخصوم');
        Book::factory()->create([
            'category_id' => Category::factory(),
            'is_published' => true,
            'title' => 'كتاب بلا خصم',
            'old_price' => null,
        ]);

        $this->get(route('books.offers'))->assertOk()
            ->assertSee('كتاب مخصوم', false)
            ->assertDontSee('كتاب بلا خصم', false);
    }

    public function test_move_to_offer_position_renumbers_the_discounted_set(): void
    {
        $a = $this->offer('أ', 0, 1);
        $b = $this->offer('ب', 0, 2);
        $c = $this->offer('ج', 0, 3);

        Book::moveToOfferPosition($c->id, 1); // ج → الأول

        $this->assertSame(1, (int) $c->fresh()->offer_sort_order);
        $this->assertSame(2, (int) $a->fresh()->offer_sort_order);
        $this->assertSame(3, (int) $b->fresh()->offer_sort_order);
    }

    public function test_sync_offer_positions_appends_unranked_discounted_books(): void
    {
        $ranked = $this->offer('مرتَّب', 0, 1);
        $unranked = $this->offer('غير مرتَّب', 0, 0);

        Book::syncOfferPositions();

        $this->assertSame(1, (int) $ranked->fresh()->offer_sort_order); // كما هو
        $this->assertSame(2, (int) $unranked->fresh()->offer_sort_order); // أُلحِق بالنهاية
    }

    public function test_sync_renumbers_full_set_and_closes_gaps(): void
    {
        // فجوة (2 مفقود، خصمٌ أُزيل سابقًا) + تكرار (رتبتان 3) → يُعاد الترقيم 1..N بلا فجوات.
        $a = $this->offer('أ', 0, 1);
        $b = $this->offer('ب', 10, 3);
        $c = $this->offer('ج', 20, 3);

        Book::syncOfferPositions();

        $this->assertSame(1, (int) $a->fresh()->offer_sort_order);
        $this->assertSame(2, (int) $b->fresh()->offer_sort_order); // الأحدث بين المكرَّرَين
        $this->assertSame(3, (int) $c->fresh()->offer_sort_order);
    }

    public function test_sync_ignores_non_discounted_books(): void
    {
        $plain = Book::factory()->create([
            'category_id' => Category::factory(),
            'title' => 'بلا خصم',
            'old_price' => null,
            'offer_sort_order' => 0,
        ]);

        Book::syncOfferPositions();

        $this->assertSame(0, (int) $plain->fresh()->offer_sort_order); // لم يُلمَس
    }

    public function test_sync_ignores_unpublished_discounted_books(): void
    {
        // مجموعة الترتيب = المنشور + المخصوم فقط (تطابق المتجر وجدول الأدمن والسحب).
        $published = $this->offer('منشور مخصوم', 0, 0);
        $hidden = $this->offer('غير منشور مخصوم', 0, 0, published: false);

        Book::syncOfferPositions();

        $this->assertSame(1, (int) $published->fresh()->offer_sort_order);
        $this->assertSame(0, (int) $hidden->fresh()->offer_sort_order); // خارج المجموعة → لم يُلمَس
    }

    public function test_move_to_offer_position_ignores_unpublished_books(): void
    {
        $a = $this->offer('أ', 0, 1);
        $b = $this->offer('ب', 0, 2);
        $hidden = $this->offer('غير منشور', 0, 5, published: false);

        Book::moveToOfferPosition($b->id, 1); // ب → الأول ضمن المنشور فقط

        $this->assertSame(1, (int) $b->fresh()->offer_sort_order);
        $this->assertSame(2, (int) $a->fresh()->offer_sort_order);
        $this->assertSame(5, (int) $hidden->fresh()->offer_sort_order); // لم يدخل إعادة الترقيم
    }
}
