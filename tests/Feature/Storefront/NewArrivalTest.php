<?php

declare(strict_types=1);

namespace Tests\Feature\Storefront;

use App\Models\Book;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * شارة «جديد» + صفحة/فلتر «وصل حديثًا».
 *
 * المنطق هجين: العلم اليدوي is_new (إظهار قسريّ) أو أي كتاب نُشر خلال آخر N يوم
 * (N = إعداد new_badge_days القابل للتعديل، افتراضه 30). يغطّي هذا الملف: منطق الصف
 * (newBadgeVisible)، النطاق (scopeNewArrivals)، اشتقاق المدّة من الإعداد، تصيير
 * الشارة على البطاقة، وصفحة /new بترويستها وقبول معامل new دون خطأ تحقّق (422).
 */
final class NewArrivalTest extends TestCase
{
    use RefreshDatabase;

    private function book(array $attrs = []): Book
    {
        return Book::factory()->create(array_merge([
            'is_published' => true,
            'is_new' => false,
        ], $attrs));
    }

    // ----- منطق الصف والنطاق (المدّة الافتراضية 30) -----------------------

    public function test_manual_flag_marks_a_book_as_new_regardless_of_date(): void
    {
        $book = $this->book(['is_new' => true, 'published_at' => now()->subDays(200)]);

        $this->assertTrue($book->newBadgeVisible());
    }

    public function test_recently_published_book_is_new_automatically(): void
    {
        $book = $this->book(['is_new' => false, 'published_at' => now()->subDays(5)]);

        $this->assertTrue($book->newBadgeVisible());
    }

    public function test_old_unflagged_book_is_not_new(): void
    {
        $book = $this->book(['is_new' => false, 'published_at' => now()->subDays(45)]);

        $this->assertFalse($book->newBadgeVisible());
    }

    public function test_book_without_publish_date_is_new_only_if_flagged(): void
    {
        $this->assertFalse($this->book(['is_new' => false, 'published_at' => null])->newBadgeVisible());
        $this->assertTrue($this->book(['is_new' => true, 'published_at' => null])->newBadgeVisible());
    }

    public function test_new_arrivals_scope_returns_manual_and_recent_only(): void
    {
        $recent = $this->book(['is_new' => false, 'published_at' => now()->subDays(3)]);
        $manualOld = $this->book(['is_new' => true, 'published_at' => now()->subDays(120)]);
        $old = $this->book(['is_new' => false, 'published_at' => now()->subDays(120)]);

        $ids = Book::query()->newArrivals()->pluck('id');

        $this->assertTrue($ids->contains($recent->id));
        $this->assertTrue($ids->contains($manualOld->id));
        $this->assertFalse($ids->contains($old->id));
    }

    public function test_future_dated_book_is_not_a_new_arrival(): void
    {
        // كتاب بتاريخ نشر مستقبليّ (منشور بالفعل) ليس «وصل حديثًا» — فرع التاريخ محصور
        // بالماضي القريب. لكنّ الفرع اليدوي (is_new) يبقى مستقلًّا عن التاريخ.
        $future = $this->book(['is_new' => false, 'published_at' => now()->addDays(5)]);
        $manualFuture = $this->book(['is_new' => true, 'published_at' => now()->addDays(5)]);

        $this->assertFalse($future->newBadgeVisible());
        $this->assertTrue($manualFuture->newBadgeVisible());

        $ids = Book::query()->newArrivals()->pluck('id');
        $this->assertFalse($ids->contains($future->id));
        $this->assertTrue($ids->contains($manualFuture->id));
    }

    public function test_duration_is_read_from_the_editable_setting(): void
    {
        // مدّة مخصّصة 7 أيام: كتاب نُشر قبل 10 أيام يصير «قديمًا».
        Setting::updateOrCreate(['key' => 'new_badge_days'], [
            'group' => 'catalog', 'value' => '7', 'type' => 'string',
            'is_encrypted' => false, 'autoload' => true,
        ]);
        app()->forgetInstance('shop.new_badge_days'); // تأكيد قراءة القيمة المخصّصة.

        $this->assertSame(7, Book::newBadgeDays());
        $this->assertFalse($this->book(['is_new' => false, 'published_at' => now()->subDays(10)])->newBadgeVisible());
        $this->assertTrue($this->book(['is_new' => false, 'published_at' => now()->subDays(3)])->newBadgeVisible());
    }

    // ----- تصيير الشارة على البطاقة --------------------------------------

    public function test_card_shows_the_new_ribbon_for_a_new_book(): void
    {
        $this->book(['published_at' => now()->subDays(4), 'title' => 'كتاب وصل حديثًا']);

        $this->get(route('books.index'))->assertOk()
            ->assertSee('new-badge', false)
            ->assertSee(__('book.new_badge'));
    }

    public function test_card_hides_the_ribbon_for_an_old_book(): void
    {
        $this->book(['is_new' => false, 'published_at' => now()->subDays(90), 'title' => 'كتاب قديم']);

        $this->get(route('books.index'))->assertOk()
            ->assertSee('كتاب قديم')
            ->assertDontSee('new-badge', false);
    }

    // ----- صفحة/فلتر «وصل حديثًا» -----------------------------------------

    public function test_new_page_lists_only_new_arrivals_with_its_heading(): void
    {
        $recent = $this->book(['published_at' => now()->subDays(2), 'title' => 'الأحدث تمامًا']);
        $manual = $this->book(['is_new' => true, 'published_at' => now()->subDays(150), 'title' => 'قديم لكن مثبّت جديد']);
        $old = $this->book(['is_new' => false, 'published_at' => now()->subDays(150), 'title' => 'قديم لا يظهر']);

        $res = $this->get(route('books.new'))->assertOk();

        $res->assertSee(__('catalog.new_heading'));
        $res->assertSee($recent->title);
        $res->assertSee($manual->title);
        $res->assertDontSee($old->title);
    }

    public function test_new_filter_param_is_accepted_without_validation_error(): void
    {
        // يحرس ضدّ خطأ 422 السابق: أي معامل toggle غير مُدرَج في قواعد التحقّق يُرفض.
        $this->book(['published_at' => now()->subDays(3)]);

        $this->get(route('books.index', ['new' => 1]))->assertOk();
    }
}
