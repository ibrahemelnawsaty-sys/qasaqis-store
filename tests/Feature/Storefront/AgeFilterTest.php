<?php

declare(strict_types=1);

namespace Tests\Feature\Storefront;

use App\Models\Book;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * قائمة «الفئة العمرية» بجانب الترتيب في الكتالوج (M12): قائمة منسدلة سريعة تضبط
 * فلتر age (الذي يعالجه FiltersBooks::applyAgeFilter على تداخل age_min/age_max).
 * قائمة ملاحة مستقلّة تحفظ باقي الفلاتر ولا تتعارض مع age[] في اللوحة الجانبية.
 *
 * HONESTY (1.3/1.5): تعمل عبر php artisan test (MariaDB محليًا).
 */
final class AgeFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_catalog_shows_an_age_dropdown_with_all_buckets(): void
    {
        $response = $this->get(route('books.index'));

        $response->assertOk();
        $response->assertSee('كل الأعمار', false);          // خيار «كل الأعمار» (خاص بالقائمة الجديدة)
        $response->assertSee('حتى 3 سنوات', false);          // شريحة 0-3
        $response->assertSee('9 سنوات فأكثر', false);         // شريحة 9-99
    }

    public function test_filtering_by_age_narrows_the_listed_books(): void
    {
        // كتاب للرضّع (0-2) وكتاب للكبار (10-12) — نفلتر على شريحة «حتى 3 سنوات».
        Book::factory()->create([
            'title' => 'كتاب الرضّع', 'age_min' => 0, 'age_max' => 2,
        ]);
        Book::factory()->create([
            'title' => 'كتاب الكبار', 'age_min' => 10, 'age_max' => 12,
        ]);

        // age يُمرَّر مصفوفةً (قاعدة التحقّق تفرض ذلك، كفلتر age[] الجانبي).
        $response = $this->get(route('books.index', ['age' => ['0-3']]));

        $response->assertOk();
        $response->assertSee('كتاب الرضّع', false);      // يتداخل مع [0,3]
        $response->assertDontSee('كتاب الكبار', false);  // خارج النطاق
    }

    public function test_age_options_preserve_the_current_sort(): void
    {
        // كل خيار عمري رابطٌ يحفظ باقي الفلاتر (هنا الترتيب) ويضبط age لقيمة واحدة.
        $response = $this->get(route('books.index', ['sort' => 'price_desc']));

        $response->assertOk();
        // روابط خيارات العمر تحمل الترتيب الحالي + العمر مصفوفةً (age[0]=… مُرمَّز).
        $response->assertSee('sort=price_desc', false);
        $response->assertSee('age%5B0%5D=3-6', false);
    }

    public function test_the_active_age_bucket_is_marked_selected(): void
    {
        $response = $this->get(route('books.index', ['age' => ['6-9']]));

        $response->assertOk();
        // خيار الشريحة النشطة (6-9) يحمل selected؛ نتحقّق أنّ رابطه موجود ومختار.
        $this->assertMatchesRegularExpression(
            '/age%5B0%5D=6-9"[^>]*selected/u',
            $response->getContent(),
        );
    }
}
