<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use Tests\TestCase;

/**
 * ترويسات الأمان على كل استجابة: منع التأطير/التضمين عبر النطاقات (clickjacking +
 * سرقة العرض)، nosniff، وسياسة المُحيل. CSP مقصورة على frame-ancestors (لا تكسر
 * Filament). لا تحتاج قاعدة بيانات (تُفحَص على /robots.txt).
 */
final class SecurityHeadersTest extends TestCase
{
    public function test_all_responses_carry_protective_headers(): void
    {
        $response = $this->get('/robots.txt')->assertOk();

        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Content-Security-Policy', "frame-ancestors 'self'");
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeader('Cross-Origin-Resource-Policy', 'same-origin');
        $this->assertFalse($response->headers->has('X-Powered-By'));
    }
}
