<?php

declare(strict_types=1);

namespace Tests\Feature\Seo;

use App\Models\Book;
use Database\Seeders\CategorySeeder;
use Database\Seeders\PublisherSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * تحسينات الكيان والفهرسة: أسماء «قصاقيص» البديلة + وصف/knowsAbout على المؤسسة
 * (لربط الكلمة القصيرة بالعلامة)، ونصّ فريد لصفحة العروض (كي لا تكون نسخة من /books).
 */
class BrandEntityAndOffersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([CategorySeeder::class, PublisherSeeder::class]);
        Http::fake();
    }

    public function test_homepage_organization_schema_carries_arabic_alternate_names_and_knows_about(): void
    {
        Book::factory()->count(2)->create(['is_published' => true]);

        $this->get('/')
            ->assertOk()
            ->assertSee('alternateName')
            ->assertSee('متجر قصاقيص')          // اسم بديل عربي محدّد
            ->assertSee('knowsAbout')
            ->assertSee('كتب الأطفال')           // قيمة knowsAbout
            ->assertSee('متجر كتب ومنتجات أطفال'); // Organization.description
    }

    public function test_offers_page_has_unique_intro_distinguishing_it_from_books(): void
    {
        $this->get('/offers')
            ->assertOk()
            ->assertSee('كل كتب الأطفال المخفّضة حاليًا'); // نصّ offers_intro الفريد
    }
}
