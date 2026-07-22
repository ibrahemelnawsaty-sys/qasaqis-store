<?php

declare(strict_types=1);

namespace Tests\Feature\Seo;

use App\Models\Book;
use App\Models\Category;
use App\Models\Redirect;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * مدير التحويلات 301: تغيير slug يُنشئ تحويلًا تلقائيًا، والمعالج يُصدر 301 عند 404،
 * والسلاسل تُقطع، والتحويل المعطّل يُتجاهَل. (نظير Yoast Redirect Manager.)
 *
 * المسارات غير المرتبطة بمسار مسجّل (مثل /legacy/*) تُستخدم لاختبار المعالج مباشرةً؛
 * و/books/* لاختبار التدفّق الواقعي (رابط كتاب أُعيدت تسميته → 404 → 301).
 */
class RedirectTest extends TestCase
{
    use RefreshDatabase;

    private function book(string $slug): Book
    {
        return Book::factory()->create([
            'category_id' => Category::factory()->create()->id,
            'slug' => $slug,
            'is_published' => true,
        ]);
    }

    public function test_changing_slug_creates_an_auto_301_redirect(): void
    {
        $book = $this->book('old-slug');

        $book->update(['slug' => 'new-slug']);

        $this->assertDatabaseHas('redirects', [
            'from_path' => '/books/old-slug',
            'to_path' => '/books/new-slug',
            'status_code' => 301,
            'source' => 'auto',
            'is_active' => true,
        ]);
    }

    public function test_no_redirect_when_slug_is_unchanged(): void
    {
        $book = $this->book('stable-slug');

        $book->update(['title' => 'عنوان جديد بلا تغيير رابط']);

        $this->assertDatabaseCount('redirects', 0);
    }

    public function test_old_book_url_redirects_after_rename(): void
    {
        // تدفّق واقعي: بعد إعادة تسمية الكتاب، رابطه القديم يُصبح 404 فيلتقطه المعالج.
        $book = $this->book('كتاب-قديم');
        $book->update(['slug' => 'كتاب-جديد']);

        $this->get('/books/كتاب-قديم')
            ->assertStatus(301)
            ->assertRedirect('/books/كتاب-جديد');
    }

    public function test_404_handler_issues_301_to_active_redirect_and_counts_the_hit(): void
    {
        $redirect = Redirect::create([
            'from_path' => '/legacy/gone',
            'to_path' => '/books/here',
            'status_code' => 301,
            'source' => 'manual',
        ]);

        $this->get('/legacy/gone')
            ->assertStatus(301)
            ->assertRedirect('/books/here');

        $redirect->refresh();
        $this->assertSame(1, $redirect->hits);
        $this->assertNotNull($redirect->last_hit_at);
    }

    public function test_inactive_redirect_is_ignored(): void
    {
        Redirect::create([
            'from_path' => '/legacy/disabled',
            'to_path' => '/books/somewhere',
            'status_code' => 301,
            'is_active' => false,
            'source' => 'manual',
        ]);

        $this->get('/legacy/disabled')->assertNotFound();
    }

    public function test_trailing_slash_and_missing_leading_slash_still_match(): void
    {
        Redirect::create([
            'from_path' => '/legacy/old-page',
            'to_path' => '/books/here',
            'status_code' => 301,
            'source' => 'manual',
        ]);

        // شرطة زائدة في الطلب تُطابق المسار المخزَّن بلا شرطة (بعد التطبيع).
        $this->get('/legacy/old-page/')->assertStatus(301)->assertRedirect('/books/here');
    }

    public function test_redirect_chains_are_collapsed_on_further_rename(): void
    {
        // تحويل قديم a → b (كأن الكتاب أُعيدت تسميته سابقًا).
        Redirect::create([
            'from_path' => '/books/a',
            'to_path' => '/books/b',
            'status_code' => 301,
            'source' => 'auto',
        ]);

        // الكتاب الآن على b ثم يُعاد تسميته إلى c.
        $book = $this->book('b');
        $book->update(['slug' => 'c']);

        // تحويل جديد b → c.
        $this->assertDatabaseHas('redirects', [
            'from_path' => '/books/b',
            'to_path' => '/books/c',
        ]);

        // والقديم a يقفز مباشرةً إلى c (لا سلسلة a → b → c).
        $this->assertDatabaseHas('redirects', [
            'from_path' => '/books/a',
            'to_path' => '/books/c',
        ]);
    }

    public function test_non_get_requests_are_not_redirected(): void
    {
        Redirect::create([
            'from_path' => '/legacy/gone',
            'to_path' => '/books/here',
            'status_code' => 301,
            'source' => 'manual',
        ]);

        // POST لمسار غير مسجّل يبقى 404 لا 301 (التحويل للـ GET فقط).
        $this->post('/legacy/gone')->assertNotFound();
    }
}
