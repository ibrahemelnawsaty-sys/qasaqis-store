<?php

declare(strict_types=1);

namespace Tests\Feature\Cms;

use App\Models\Article;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * ٢٠ مقالًا (٥ لكل قسم مدوّنة) تُنشرها المِهجرة seed_category_blog_articles من ملف
 * البذرة JSON (تُشغَّل ضمن RefreshDatabase) — منشورة، موزّعة على الأقسام، وتُصيَّر HTML.
 */
final class CategoryArticlesTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string, array<int, string>> */
    private array $byCategory = [
        'نصائح تربوية' => [
            'القراءة-للرضع-متى-وكيف', 'وقت-الشاشة-والقراءة-للاطفال', 'تنمية-الفضول-وحب-الاستطلاع',
            'تنمية-مهارات-اللغة-عند-الطفل', 'هدايا-الكتب-للاطفال',
        ],
        'تربية بالقصص' => [
            'قصص-تعليم-المشاركة-للاطفال', 'قصص-التغلب-على-الخوف', 'قصص-تعليم-النظافة-للاطفال',
            'قصص-الصدق-للاطفال', 'قصص-احترام-الاخرين-للاطفال',
        ],
        'مراجعات كتب' => [
            'اختيار-كتب-تعليم-الحروف-العربية', 'روايات-للاطفال-9-12-سنة', 'اختيار-كتب-القيم-للاطفال',
            'كتب-الطفولة-المبكرة-0-3', 'اختيار-كتب-دينية-للاطفال',
        ],
        'أنشطة وتعليم' => [
            'انشطة-تعليمية-منزلية-بسيطة', 'تعليم-الحروف-والارقام-باللعب', 'انشطة-المهارات-الحركية-الدقيقة',
            'العاب-تعليمية-تنمي-الذكاء', 'ركن-قراءة-للطفل-في-البيت',
        ],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CategorySeeder::class);
        Http::fake();
    }

    public function test_migration_publishes_five_articles_in_each_category(): void
    {
        foreach ($this->byCategory as $category => $slugs) {
            foreach ($slugs as $slug) {
                $this->assertDatabaseHas('articles', ['slug' => $slug, 'is_published' => true]);
            }

            $count = Article::whereIn('slug', $slugs)->where('category', $category)->count();
            $this->assertSame(5, $count, "القسم «{$category}» يجب أن يحوي ٥ مقالات.");
        }
    }

    public function test_each_article_has_a_focus_keyword_and_seo_description(): void
    {
        $slugs = array_merge(...array_values($this->byCategory));

        foreach (Article::whereIn('slug', $slugs)->get() as $article) {
            $this->assertNotEmpty($article->focus_keyword, "{$article->slug} بلا كلمة مفتاحية.");
            $this->assertNotEmpty($article->seo_description, "{$article->slug} بلا وصف SEO.");
        }
    }

    public function test_a_category_article_page_renders_html(): void
    {
        $this->get('/blog/القراءة-للرضع-متى-وكيف')
            ->assertOk()
            ->assertSee('القراءة للرضع')
            ->assertSee('<h2>', false);
    }
}
