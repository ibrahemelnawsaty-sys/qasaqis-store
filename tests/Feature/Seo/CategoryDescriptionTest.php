<?php

declare(strict_types=1);

namespace Tests\Feature\Seo;

use App\Models\Category;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * أوصاف SEO لأقسام الكتب: تظهر نصًّا تعريفيًّا على صفحة القسم وتُغذّي وصف الميتا،
 * وتُضبط فقط للأقسام الفارغة (لا تدهس تعديل المالك).
 */
final class CategoryDescriptionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CategorySeeder::class);
        Http::fake();
    }

    public function test_seeded_categories_have_seo_descriptions(): void
    {
        foreach (['novels', 'science', 'behavior-emotions', 'stories', 'early-childhood', 'religious'] as $slug) {
            $this->assertNotEmpty(
                Category::where('slug', $slug)->value('description'),
                "القسم «{$slug}» يجب أن يحمل وصفًا.",
            );
        }
    }

    public function test_category_page_renders_its_description(): void
    {
        $this->get('/category/science')
            ->assertOk()
            ->assertSee('كتب علمية للأطفال تبسّط العلوم'); // مقطع من الوصف
    }

    public function test_description_is_not_overwritten_when_already_set(): void
    {
        // إعادة البذر لا تدهس وصفًا كتبه المالك.
        Category::where('slug', 'novels')->update(['description' => 'وصف المالك المخصّص']);

        $this->seed(CategorySeeder::class);

        $this->assertSame('وصف المالك المخصّص', Category::where('slug', 'novels')->value('description'));
    }
}
