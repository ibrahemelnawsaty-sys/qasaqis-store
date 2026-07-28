<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * ترويسات أمان تحمي المنصّة والمحتوى (بلا CDN): منع تأطير الموقع في مواقع أخرى
 * (clickjacking/سرقة عرض)، منع تضمين الموارد عبر النطاقات (CORP)، ومنع تخمين الأنواع
 * وتسريب المُحيل. CSP مقصورة على frame-ancestors فقط (script-src كامل يكسر Filament/
 * Livewire/Alpine — بند الخبرة). لا تمسّ Googlebot/Bing ولا معاينات فيسبوك/واتساب
 * (جلبها من الخادم لا يتأثّر بـCORP المفروضة في المتصفّح).
 */
class SecurityHeaders
{
    /** @var array<string, string> */
    private const HEADERS = [
        'X-Content-Type-Options' => 'nosniff',
        'X-Frame-Options' => 'SAMEORIGIN',
        'Content-Security-Policy' => "frame-ancestors 'self'",
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
        'Permissions-Policy' => 'geolocation=(), camera=(), microphone=(), browsing-topics=()',
        'Cross-Origin-Resource-Policy' => 'same-origin',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        foreach (self::HEADERS as $key => $value) {
            if (! $response->headers->has($key)) {
                $response->headers->set($key, $value);
            }
        }

        // إخفاء بصمة التقنية (قدر الإمكان).
        $response->headers->remove('X-Powered-By');

        // HSTS على HTTPS فقط (الموقع كلّه HTTPS).
        if ($request->secure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
