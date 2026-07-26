<?php

declare(strict_types=1);

namespace Tests\Feature\Cms;

use Database\Seeders\PageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * صفحة «من نحن» تعرض قصّة العلامة الحقيقية (بدأت 2022، كتب/ألعاب/أوراق عمل) — محتوى
 * فريد كافٍ للثقة والفهرسة بدل قشرة رقيقة، ويُصيَّر HTML عبر المطهّر (وسوم آمنة، بلا روابط).
 */
final class AboutPageContentTest extends TestCase
{
    use RefreshDatabase;

    public function test_about_page_renders_enriched_trust_content(): void
    {
        $this->seed(PageSeeder::class);

        $this->get('/pages/about')
            ->assertOk()
            ->assertSee('من نحن')                            // العنوان H1
            ->assertSee('رحلة بدأت عام')                      // القصّة
            ->assertSee('2022')                               // سنة التأسيس
            ->assertSee('أوراق العمل التعليمية والتفاعلية')    // عرض حقيقي
            ->assertSee('رسالتنا')                            // عنوان قسم
            ->assertSee('نخدم العائلات في مصر')               // السوق المحليّ
            ->assertSee('<strong>', false)                    // يُصيَّر HTML فعليًّا لا نصًّا مهرَّبًا
            ->assertDontSee('مكتبة إلكترونية متخصّصة');        // لم يبقَ النصّ القديم الرقيق
    }
}
