<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\HtmlSanitizer;
use PHPUnit\Framework\TestCase;

/**
 * The admin rich-text sanitizer (App\Support\HtmlSanitizer::clean) is the ONLY
 * gate before CMS HTML is echoed through Blade {!! !!} (pages.show), so it must
 * strip every XSS vector — <script>, on* handlers, javascript: URLs — while
 * keeping safe structural tags and the Arabic prose intact (constitution 4.2 /
 * anti-pattern 8). Whitelist-only: unknown tags are unwrapped to their text and
 * ALL attributes are removed from kept tags.
 *
 * HONESTY (1.3/1.5): NOT executed in this PHP-less environment; runs under
 * `php artisan test` on the hosting (requires the ext-dom / libxml extension).
 */
final class HtmlSanitizerTest extends TestCase
{
    public function test_it_removes_script_tags_and_their_contents(): void
    {
        $out = HtmlSanitizer::clean('<p>مرحبا</p><script>alert(1)</script>');

        $this->assertStringNotContainsString('<script', $out);
        $this->assertStringNotContainsString('alert(1)', $out);
        // Safe surrounding content survives.
        $this->assertStringContainsString('مرحبا', $out);
        $this->assertStringContainsString('<p>', $out);
    }

    public function test_it_removes_on_event_handler_attributes(): void
    {
        $out = HtmlSanitizer::clean(
            '<p onmouseover="steal()">نص</p><strong onerror="boom()">قوي</strong>'
        );

        $this->assertStringNotContainsString('onmouseover', $out);
        $this->assertStringNotContainsString('onerror', $out);
        $this->assertStringNotContainsString('steal()', $out);
        $this->assertStringNotContainsString('boom()', $out);
        // The tags themselves stay, only stripped of attributes.
        $this->assertStringContainsString('<p>نص</p>', $out);
        $this->assertStringContainsString('<strong>قوي</strong>', $out);
    }

    public function test_it_removes_javascript_urls_in_href_and_src(): void
    {
        // <a>/<img> are not whitelisted: the anchor is unwrapped to its text and
        // the image is dropped entirely — either way no javascript: URL remains.
        $out = HtmlSanitizer::clean(
            '<a href="javascript:alert(1)">اضغط هنا</a>'
            .'<img src="javascript:alert(2)" onerror="alert(3)">'
        );

        $this->assertStringNotContainsString('javascript:', $out);
        $this->assertStringNotContainsString('href', $out);
        $this->assertStringNotContainsString('src', $out);
        $this->assertStringNotContainsString('onerror', $out);
        // The anchor's visible text is preserved.
        $this->assertStringContainsString('اضغط هنا', $out);
    }

    public function test_it_keeps_safe_tags_and_arabic_text_intact(): void
    {
        $out = HtmlSanitizer::clean(
            '<p>فقرة <strong>عريضة</strong></p>'
            .'<ul><li>عنصر أول</li><li>عنصر ثانٍ</li></ul>'
        );

        // Structural whitelist tags remain.
        $this->assertStringContainsString('<p>', $out);
        $this->assertStringContainsString('<strong>', $out);
        $this->assertStringContainsString('<ul>', $out);
        $this->assertStringContainsString('<li>', $out);
        // Arabic text is not mangled or entity-encoded away.
        $this->assertStringContainsString('فقرة', $out);
        $this->assertStringContainsString('عريضة', $out);
        $this->assertStringContainsString('عنصر أول', $out);
        $this->assertStringContainsString('عنصر ثانٍ', $out);
    }

    public function test_empty_input_yields_empty_output(): void
    {
        $this->assertSame('', HtmlSanitizer::clean(''));
        $this->assertSame('', HtmlSanitizer::clean('   '));
    }
}
