<?php

declare(strict_types=1);

namespace Tests\Feature\Seo;

use App\Models\Article;
use App\Models\Book;
use App\Models\Category;
use App\Models\Page;
use App\Models\Series;
use App\Support\Seo\SeoDefaults;
use Tests\TestCase;

/**
 * SeoDefaults هو المصدر الموحّد لبيانات SEO التلقائية: نفسه يغذّي القيمة الافتراضية
 * في الواجهة وقيمة الـplaceholder في لوحة الإدارة. يشتقّ من محتوى الكيان فقط.
 *
 * لا يمسّ قاعدة البيانات (يعمل على نماذج غير مخزّنة) فيصحّ تشغيله بلا MySQL، لكنه
 * يحتاج إقلاع التطبيق للترجمات (common.brand / seo.*)، فيرث Tests\TestCase.
 */
class SeoDefaultsTest extends TestCase
{
    private string $brand;

    protected function setUp(): void
    {
        parent::setUp();
        $this->brand = (string) __('common.brand');
    }

    public function test_book_title_appends_brand_once(): void
    {
        $book = tap(new Book, fn (Book $b) => $b->title = 'أنا لستُ شقيًا!');

        $title = SeoDefaults::title($book);

        $this->assertStringContainsString('أنا لستُ شقيًا!', $title);
        $this->assertStringContainsString($this->brand, $title);
        $this->assertSame(1, substr_count($title, $this->brand), 'brand must appear exactly once');
    }

    public function test_title_does_not_duplicate_brand_already_present(): void
    {
        $page = tap(new Page, fn (Page $p) => $p->title = $this->brand);

        $this->assertSame($this->brand, SeoDefaults::title($page));
    }

    public function test_book_description_prefers_short_description(): void
    {
        $book = tap(new Book, function (Book $b): void {
            $b->short_description = 'قصة عن ضبط الانفعالات.';
            $b->long_description = '<p>نصّ طويل لا يجب أن يظهر.</p>';
        });

        $this->assertSame('قصة عن ضبط الانفعالات.', SeoDefaults::description($book));
    }

    public function test_html_is_stripped_and_words_kept_separated(): void
    {
        $book = tap(new Book, function (Book $b): void {
            $b->long_description = '<p>الرِّضا &amp; القناعة.</p><ul><li>قيمة</li></ul>';
        });

        $desc = SeoDefaults::description($book);

        $this->assertStringNotContainsString('<', $desc);
        $this->assertStringContainsString('&', $desc, 'entities are decoded');
        $this->assertStringContainsString('القناعة. قيمة', $desc, 'block boundaries become spaces');
    }

    public function test_empty_content_falls_back_to_tagline(): void
    {
        $book = tap(new Book, fn (Book $b) => $b->title = 'كتاب بلا وصف');

        $this->assertSame((string) __('common.tagline'), SeoDefaults::description($book));
    }

    public function test_description_is_truncated_to_a_meta_safe_length(): void
    {
        $page = tap(new Page, fn (Page $p) => $p->content = str_repeat('كلمة ', 200));

        // 160 حرف محتوى + «…» = 161 كحدّ أقصى ظاهر.
        $this->assertLessThanOrEqual(161, mb_strlen(SeoDefaults::description($page)));
    }

    public function test_category_derives_from_name_when_no_description(): void
    {
        $category = tap(new Category, fn (Category $c) => $c->name = 'روايات');

        $this->assertStringContainsString('روايات', SeoDefaults::title($category));
        $this->assertStringContainsString('روايات', SeoDefaults::description($category));
        $this->assertStringContainsString($this->brand, SeoDefaults::description($category));
    }

    public function test_category_prefers_admin_description(): void
    {
        $category = tap(new Category, function (Category $c): void {
            $c->name = 'كتب دينية';
            $c->description = 'قصص دينية مبسّطة تغرس القيم.';
        });

        $this->assertSame('قصص دينية مبسّطة تغرس القيم.', SeoDefaults::description($category));
    }

    public function test_series_derives_from_name(): void
    {
        $series = tap(new Series, fn (Series $s) => $s->name = 'المكتشفون الصغار');

        $this->assertStringContainsString('سلسلة', SeoDefaults::title($series));
        $this->assertStringContainsString('المكتشفون الصغار', SeoDefaults::title($series));
    }

    public function test_article_prefers_excerpt_over_body(): void
    {
        $article = tap(new Article, function (Article $a): void {
            $a->title = 'كيف تقرأ لطفلك';
            $a->excerpt = 'خطوات عملية لجعل القراءة عادة ممتعة.';
            $a->content = '<p>محتوى طويل لا يظهر.</p>';
        });

        $this->assertSame('خطوات عملية لجعل القراءة عادة ممتعة.', SeoDefaults::description($article));
    }

    public function test_og_helpers_mirror_title_and_description(): void
    {
        $book = tap(new Book, function (Book $b): void {
            $b->title = 'هوّن عليك';
            $b->short_description = 'عن الرِّضا.';
        });

        $this->assertSame(SeoDefaults::title($book), SeoDefaults::ogTitle($book));
        $this->assertSame(SeoDefaults::description($book), SeoDefaults::ogDescription($book));
    }
}
