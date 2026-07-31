<?php

declare(strict_types=1);

namespace Tests\Feature\Storefront;

use App\Models\Book;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * زرّ المشاركة الصغير على بطاقة الكتاب (قوائم الكتب).
 * يؤكّد: ظهور الزرّ بكل بطاقة يحمل رابط الكتاب ونصّ مشاركته، وأن القائمة المنبثقة
 * المشتركة تُصيَّر مرّة واحدة للصفحة مهما تعدّدت البطاقات، وأن الزرّ يظهر حتى لكتاب
 * بلا سعر (المشاركة مستقلّة عن قابلية الشراء — حالة BOOK1).
 */
final class BookCardShareTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_card_has_a_share_button_carrying_the_book_link_and_text(): void
    {
        $book = Book::factory()->create(['is_published' => true, 'title' => 'كتاب المشاركة']);
        $url = route('books.show', $book);

        $res = $this->get(route('books.index'))->assertOk();

        // الزرّ موجود بمعرّفاته: رابط الكتاب + نصّ المشاركة + تسمية الوصوليّة بالعنوان.
        $res->assertSee('class="book-share"', false);
        $res->assertSee('data-share-url="'.$url.'"', false);
        $res->assertSee(__('book.share.text', ['title' => 'كتاب المشاركة']));
        $res->assertSee(__('book.share.card_aria', ['title' => 'كتاب المشاركة']));

        // ARIA صحيحة: الزرّ يُعلن نافذة منبثقة (لا «menu» بلا تنقّل بالأسهم).
        $res->assertSee('aria-haspopup="true"', false);
        $res->assertDontSee('role="menuitem"', false);
    }

    public function test_shared_share_menu_is_rendered_exactly_once_per_page(): void
    {
        Book::factory()->count(3)->create(['is_published' => true]);

        $html = $this->get(route('books.index'))->assertOk()->getContent();

        // القائمة المنبثقة + عناصرها تُصيَّر مرّة واحدة (عبر @once) رغم تعدّد البطاقات.
        $this->assertSame(1, substr_count($html, 'id="book-share-pop"'));
        $this->assertSame(3, substr_count($html, 'class="book-share"'));

        // القائمة مجموعة موسومة (role=group) لا «menu» — يطابق سلوك التنقّل بالـTab المُنفَّذ.
        $this->assertStringContainsString('id="book-share-pop" class="book-share-pop" role="group"', $html);

        // تسميات عناصر القائمة موجودة (نسخ/واتساب/فيسبوك).
        $this->assertStringContainsString(__('book.share.copy'), $html);
        $this->assertStringContainsString(__('book.share.whatsapp'), $html);
        $this->assertStringContainsString(__('book.share.facebook'), $html);
    }

    public function test_share_button_appears_even_for_a_book_without_a_price(): void
    {
        // كتاب بلا سعر (مثل BOOK1): زرّ الإضافة مُعطَّل، لكن المشاركة تبقى متاحة.
        $book = Book::factory()->create([
            'is_published' => true,
            'price' => null,
            'old_price' => null,
            'title' => 'كتاب بلا سعر',
        ]);

        $this->get(route('books.index'))->assertOk()
            ->assertSee('data-share-url="'.route('books.show', $book).'"', false);
    }
}
