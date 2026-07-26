<?php

declare(strict_types=1);

namespace Tests\Feature\Cms;

use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * ٥ مقالات طويلة الذيل تُنشرها المِهجرة seed_longtail_blog_articles (تُشغَّل ضمن
 * RefreshDatabase) — منشورة، تظهر في المدوّنة، وصفحاتها تُصيَّر المحتوى عبر المطهّر.
 */
final class LongtailArticlesTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<int, string> */
    private array $slugs = [
        'كتب-اطفال-عمر-3-سنوات',
        'كتب-اطفال-ما-قبل-المدرسة',
        'اوراق-عمل-تعليمية-للاطفال',
        'قصص-مصورة-للاطفال',
        'اختيار-كتاب-مناسب-لعمر-الطفل',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CategorySeeder::class); // للتنقّل في التخطيط
        Http::fake();
    }

    public function test_migration_publishes_the_five_articles(): void
    {
        foreach ($this->slugs as $slug) {
            $this->assertDatabaseHas('articles', ['slug' => $slug, 'is_published' => true]);
        }
    }

    public function test_article_page_renders_content_as_html(): void
    {
        $this->get('/blog/اوراق-عمل-تعليمية-للاطفال')
            ->assertOk()
            ->assertSee('أوراق العمل التعليمية للأطفال')     // العنوان
            ->assertSee('المهارات الحركية الدقيقة')           // مقطع من المحتوى
            ->assertSee('<h2>', false);                        // يُصيَّر HTML فعليًّا
    }

    public function test_blog_index_lists_a_new_article(): void
    {
        $this->get('/blog')
            ->assertOk()
            ->assertSee('القصص المصوّرة للأطفال');
    }
}
