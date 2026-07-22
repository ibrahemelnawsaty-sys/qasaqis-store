<?php

declare(strict_types=1);

namespace Tests\Feature\Seo;

use App\Models\Article;
use App\Models\Book;
use App\Models\Category;
use App\Models\Page;
use App\Services\Seo\SeoAuditor;
use App\Services\Seo\SeoFinding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * تدقيق SEO التلقائي: يكشف نواقص المحتوى المنشور (وصف/صورة/عنوان) وفحوص الموقع،
 * ويتجاهل غير المنشور، ويرتّب النتائج بالخطورة. (نظير تحليل Yoast على مستوى الموقع.)
 */
class SeoAuditorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake(); // منعًا لأي اتصال IndexNow عند حفظ محتوى منشور في الاختبار.
    }

    private function auditor(): SeoAuditor
    {
        return app(SeoAuditor::class);
    }

    private function categoryWithSeo(): Category
    {
        return Category::factory()->create(['is_active' => true, 'description' => 'وصف قسم كافٍ']);
    }

    public function test_published_book_missing_description_and_cover_flags_two_dangers(): void
    {
        Book::factory()->create([
            'category_id' => $this->categoryWithSeo()->id,
            'is_published' => true,
            'title' => 'كتاب',
            'short_description' => '',
            'long_description' => '',
            'cover_image' => '',
        ]);

        $books = $this->auditor()->run()->where('group', 'الكتب');

        $this->assertCount(2, $books);
        $this->assertSame(2, $books->where('severity', SeoFinding::DANGER)->count());
    }

    public function test_well_formed_book_produces_no_book_findings(): void
    {
        Book::factory()->create([
            'category_id' => $this->categoryWithSeo()->id,
            'is_published' => true,
            'title' => 'كتاب جميل للأطفال',
            'short_description' => 'قصة قصيرة عن القيم للأطفال.',
            'cover_image' => 'covers/nice.jpg',
        ]);

        $this->assertTrue($this->auditor()->run()->where('group', 'الكتب')->isEmpty());
    }

    public function test_unpublished_content_is_ignored(): void
    {
        Book::factory()->create([
            'category_id' => $this->categoryWithSeo()->id,
            'is_published' => false,
            'short_description' => '',
            'cover_image' => '',
        ]);

        $this->assertTrue($this->auditor()->run()->where('group', 'الكتب')->isEmpty());
    }

    public function test_long_title_is_a_warning(): void
    {
        Book::factory()->create([
            'category_id' => $this->categoryWithSeo()->id,
            'is_published' => true,
            'title' => str_repeat('ا', SeoAuditor::TITLE_MAX + 5),
            'short_description' => 'وصف قصير كافٍ.',
            'cover_image' => 'covers/x.jpg',
        ]);

        $books = $this->auditor()->run()->where('group', 'الكتب');

        $this->assertCount(1, $books);
        $this->assertSame(SeoFinding::WARNING, $books->first()->severity);
    }

    public function test_article_without_excerpt_or_cover_warns(): void
    {
        // لا يوجد ArticleFactory في المستودع، فنُنشئ المقال مباشرةً.
        Article::create([
            'title' => 'مقال',
            'slug' => 'article-no-excerpt',
            'excerpt' => '',
            'content' => 'نصّ المقال هنا.',
            'cover_image' => '',
            'author_name' => 'كاتب',
            'category' => 'عام',
            'is_published' => true,
        ]);

        $articles = $this->auditor()->run()->where('group', 'المقالات');

        // تحذيران: لا مقتطف + لا صورة غلاف.
        $this->assertCount(2, $articles);
        $this->assertSame(2, $articles->where('severity', SeoFinding::WARNING)->count());
    }

    public function test_page_without_content_flags_danger(): void
    {
        Page::create([
            'title' => 'صفحة فارغة',
            'slug' => 'empty-page',
            'content' => '',
            'is_published' => true,
        ]);

        $pages = $this->auditor()->run()->where('group', 'الصفحات');

        $this->assertCount(1, $pages);
        $this->assertSame(SeoFinding::DANGER, $pages->first()->severity);
    }

    public function test_category_without_description_warns(): void
    {
        // المستودع يبذر أقسامًا أساسية عبر مِهجرة، فنقيس الفارق لا العدد المطلق.
        $before = $this->auditor()->run()->where('group', 'الأقسام')->count();

        Category::factory()->create(['is_active' => true, 'description' => '', 'name' => 'قسم-بلا-وصف-فريد']);
        Category::factory()->create(['is_active' => true, 'description' => 'وصف كافٍ', 'name' => 'قسم-موصوف-فريد']);

        $cats = $this->auditor()->run()->where('group', 'الأقسام');

        // القسم بلا وصف يضيف نتيجة واحدة؛ القسم الموصوف لا يضيف شيئًا.
        $this->assertCount($before + 1, $cats);

        $mine = $cats->firstWhere('label', 'قسم-بلا-وصف-فريد');
        $this->assertNotNull($mine);
        $this->assertSame(SeoFinding::WARNING, $mine->severity);
        $this->assertNull($cats->firstWhere('label', 'قسم-موصوف-فريد'));
    }

    public function test_site_checks_toggle_with_config(): void
    {
        config(['seo.google_site_verification' => '', 'seo.indexnow_key' => '']);
        $site = $this->auditor()->run()->where('group', 'الموقع');
        $this->assertCount(2, $site); // Search Console (تحذير) + IndexNow (معلومة)

        config(['seo.google_site_verification' => 'verify-token', 'seo.indexnow_key' => 'index-key']);
        $this->assertTrue($this->auditor()->run()->where('group', 'الموقع')->isEmpty());
    }

    public function test_dangers_are_sorted_first_and_summary_is_consistent(): void
    {
        config(['seo.google_site_verification' => 'verify-token', 'seo.indexnow_key' => 'index-key']);

        // كتاب بلا وصف ولا غلاف → حرِج.
        Book::factory()->create([
            'category_id' => $this->categoryWithSeo()->id,
            'is_published' => true,
            'title' => 'كتاب',
            'short_description' => '',
            'long_description' => '',
            'cover_image' => '',
        ]);

        $auditor = $this->auditor();
        $findings = $auditor->run();
        $summary = $auditor->summarize();

        $this->assertSame(SeoFinding::DANGER, $findings->first()->severity);
        $this->assertSame($findings->count(), $summary['total']);
        $this->assertSame($findings->where('severity', SeoFinding::DANGER)->count(), $summary['danger']);
        $this->assertSame($findings->where('severity', SeoFinding::WARNING)->count(), $summary['warning']);
    }
}
