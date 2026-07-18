<?php

declare(strict_types=1);

namespace Tests\Feature\Storefront;

use App\Models\Book;
use Database\Seeders\CategorySeeder;
use Database\Seeders\PublisherSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * حارس انحدار لشريط الترقيم: كان يطبع مفاتيح خام (pagination.previous) ونصًا
 * إنجليزيًا (Showing … results) لغياب lang/ar/pagination.php وقالب مخصّص.
 * كل نص ظاهر يجب أن يأتي من ملفات الترجمة العربية (الدستور 6.4 / ممنوع 4).
 *
 * الفهرس يعرض 12 كتابًا لكل صفحة (Storefront\Concerns\FiltersBooks::paginate).
 *
 * HONESTY (1.3/1.5): NOT executed in this PHP-less environment; runs on the
 * hosting via `php artisan test`.
 */
final class PaginationLocalizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([CategorySeeder::class, PublisherSeeder::class]);
    }

    /**
     * 20 كتابًا => صفحتان، فيظهر شريط الترقيم فعليًا (hasPages).
     */
    private function booksIndexWithPagination(): \Illuminate\Testing\TestResponse
    {
        Book::factory()->count(20)->create();

        return $this->get(route('books.index'));
    }

    public function test_pagination_labels_are_arabic(): void
    {
        $response = $this->booksIndexWithPagination();

        $response->assertOk()
            ->assertSee('السابق')
            ->assertSee('التالي')
            ->assertSee('تصفّح صفحات النتائج');
    }

    public function test_result_summary_is_arabic_and_counts_are_correct(): void
    {
        $this->booksIndexWithPagination()
            ->assertOk()
            // lang/ar/pagination.php => 'عرض :first–:last من أصل :total نتيجة'
            ->assertSee('عرض 1–12 من أصل 20 نتيجة');
    }

    public function test_raw_translation_keys_never_leak_to_the_page(): void
    {
        $this->booksIndexWithPagination()
            ->assertOk()
            ->assertDontSee('pagination.previous')
            ->assertDontSee('pagination.next')
            ->assertDontSee('pagination.showing');
    }

    public function test_no_english_pagination_text_leaks_to_the_page(): void
    {
        $response = $this->booksIndexWithPagination()->assertOk();

        // نص قالب Laravel الافتراضي الذي كان يظهر للمستخدم المصري.
        // ملاحظة: لا نفحص كلمة "results" وحدها — فهي جزء من معرّفات/فئات
        // البحث في الهيدر (s-results, s-ov-results) فيصير الفحص كاذبًا.
        foreach (['Showing', 'Go to page', 'pagination.previous', 'pagination.next'] as $english) {
            $response->assertDontSee($english);
        }
    }

    public function test_custom_pagination_view_is_the_one_rendered(): void
    {
        $this->booksIndexWithPagination()
            ->assertOk()
            // فئات نظام التصميم، لا فئات Tailwind الرمادية الافتراضية.
            ->assertSee('class="pgn"', false)
            ->assertDontSee('rtl:flex-row-reverse', false);
    }
}
