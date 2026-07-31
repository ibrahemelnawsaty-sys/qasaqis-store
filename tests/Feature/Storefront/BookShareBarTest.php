<?php

declare(strict_types=1);

namespace Tests\Feature\Storefront;

use App\Models\Book;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * شريط مشاركة الكتاب تحت الغلاف (صفحة الكتاب): نسخ الرابط + مشاركة أصليّة + سوشيال.
 * يؤكّد أنه يُصيَّر داخل عمود الصورة بروابط مشاركة تحمل رابط الكتاب المُرمَّز.
 */
final class BookShareBarTest extends TestCase
{
    use RefreshDatabase;

    public function test_book_page_renders_the_share_bar_with_correct_links(): void
    {
        $book = Book::factory()->create(['is_published' => true, 'title' => 'كتابي التجريبي']);
        $url = route('books.show', $book);
        $enc = rawurlencode($url);

        $res = $this->get($url)->assertOk();

        // الشريط + عنوانه، داخل عمود الصورة (تحت الغلاف).
        $res->assertSee('pdp-media', false);
        $res->assertSee('pdp-share', false);
        $res->assertSee(__('book.share.label'));

        // روابط المشاركة تحمل رابط الكتاب المُرمَّز.
        $res->assertSee('https://www.facebook.com/sharer/sharer.php?u='.$enc, false);
        $res->assertSee('https://t.me/share/url?url='.$enc, false);
        $res->assertSee('https://wa.me/?text=', false);
        $res->assertSee('twitter.com/intent/tweet', false);

        // تسميات الوصوليّة موجودة (a11y).
        $res->assertSee(__('book.share.whatsapp'));
        $res->assertSee(__('book.share.copy'));
    }

    public function test_share_text_carries_the_book_title(): void
    {
        $book = Book::factory()->create(['is_published' => true, 'title' => 'عنوان فريد للمشاركة']);

        // الرسالة المعبّأة (نصّ المشاركة) تتضمّن عنوان الكتاب — مُرمَّزة داخل روابط السوشيال.
        $expected = rawurlencode(__('book.share.text', ['title' => 'عنوان فريد للمشاركة']));

        $this->get(route('books.show', $book))->assertOk()->assertSee($expected, false);
    }
}
