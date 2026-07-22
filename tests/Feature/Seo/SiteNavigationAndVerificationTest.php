<?php

declare(strict_types=1);

namespace Tests\Feature\Seo;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * إشارات SEO المضافة لدعم الروابط الفرعية: مخطّط SiteNavigationElement للقائمة
 * الرئيسية، ووسم تحقّق Google Search Console (يظهر فقط عند ضبطه).
 */
class SiteNavigationAndVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_emits_site_navigation_schema(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('SiteNavigationElement', $html);
        // الأقسام الأربعة بأسمائها وروابطها المطلقة.
        $this->assertStringContainsString('المتجر', $html);
        $this->assertStringContainsString('من نحن', $html);
        $this->assertStringContainsString(config('seo.site_url').'/books', $html);
        $this->assertStringContainsString(config('seo.site_url').'/pages/about', $html);
    }

    public function test_verification_meta_renders_only_when_configured(): void
    {
        config(['seo.google_site_verification' => '']);
        $this->get('/')->assertOk()->assertDontSee('google-site-verification', false);

        config(['seo.google_site_verification' => 'abc123verifycode']);
        $this->get('/')->assertOk()
            ->assertSee('name="google-site-verification"', false)
            ->assertSee('abc123verifycode', false);
    }
}
