<?php

declare(strict_types=1);

namespace Tests\Feature\Seo;

use Tests\TestCase;

/**
 * robots.txt يحجب زواحف الذكاء الاصطناعي عن سحب المحتوى/الصور للتدريب، دون المساس
 * بفهرسة Googlebot في البحث. (المسار الديناميكي يطابق public/robots.txt الساكن.)
 */
final class RobotsAiBlockTest extends TestCase
{
    public function test_robots_blocks_known_ai_crawlers(): void
    {
        $body = $this->get('/robots.txt')->assertOk()->getContent();

        foreach (['GPTBot', 'ClaudeBot', 'Google-Extended', 'CCBot', 'PerplexityBot', 'ImagesiftBot', 'Bytespider'] as $bot) {
            $this->assertStringContainsString('User-agent: '.$bot, $body, "يجب حجب {$bot}.");
        }

        // كتلة الذكاء الاصطناعي تمنع كل شيء، وGooglebot (البحث) غير محجوب.
        $this->assertStringContainsString('Disallow: /', $body);
        $this->assertStringNotContainsString('User-agent: Googlebot', $body);
    }
}
