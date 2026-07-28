<?php

declare(strict_types=1);

namespace Tests\Feature\Storefront;

use App\Models\Book;
use App\Models\Category;
use App\Support\Cache\StorefrontCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * حمولة الرئيسية المجمّعة تُخزَّن مؤقّتًا (بدل ~12–18 استعلامًا على كل زيارة) وتُبطَل
 * عند تغيّر أيٍّ من مصادرها عبر أحداث الموديل — فلا تبقى قديمة بعد تحرير الأدمن.
 */
final class HomepageCacheTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_payload_is_cached_after_first_render(): void
    {
        Cache::forget(StorefrontCache::HOMEPAGE);
        $this->assertFalse(Cache::has(StorefrontCache::HOMEPAGE));

        $this->get('/')->assertOk();

        $this->assertTrue(Cache::has(StorefrontCache::HOMEPAGE));
    }

    public function test_saving_a_source_model_invalidates_the_homepage_cache(): void
    {
        $this->get('/')->assertOk();
        $this->assertTrue(Cache::has(StorefrontCache::HOMEPAGE));

        // حفظ كتاب (أحد مصادر الرئيسية: الأقسام التلقائية تُشتقّ منه) يُبطل الكاش المجمّع.
        Book::factory()->create(['category_id' => Category::factory()->create()->id]);

        $this->assertFalse(Cache::has(StorefrontCache::HOMEPAGE));
    }
}
