<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Currency;
use App\Support\Pricing\CurrencyContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * تبديل عملة العرض. يثبّت الاختيار في كوكي (سنة) ثمّ يعيد للصفحة السابقة. عملة العرض
 * النشطة تُحقَن للعميل عبر window.__CUR (خادميًّا) لا بقراءة الكوكي، فالكوكي httpOnly.
 */
class CurrencyController extends Controller
{
    /**
     * صفحة فحصٍ خفيفة: تُظهر لأيّ زائرٍ دولتَه المكتشَفة (CF-IPCountry) وعملتَه النشطة
     * ومثالًا. تشخيص التوطين (هل تصل ترويسة Cloudflare؟) بلا بيانات حسّاسة. no-store
     * كي لا تُكاش (تختلف بكلّ زائر).
     */
    public function whoami(Request $request, CurrencyContext $context): JsonResponse
    {
        $currency = $context->get();

        return response()->json([
            'detected_country' => $request->header('CF-IPCountry'),
            'currency' => $currency->code,
            'symbol' => $currency->symbol,
            'example_300_egp' => $currency->format($currency->convertFromEgp(300)),
        ])->header('Cache-Control', 'no-store, private');
    }

    public function switch(Request $request, string $code): RedirectResponse
    {
        $code = strtoupper($code);

        $isValid = rescue(
            fn (): bool => Currency::query()->active()->whereKey($code)->exists(),
            false,
            report: false,
        );

        $response = redirect()->to($this->safeBack($request));

        if ($isValid) {
            // 60×24×365 دقيقة (سنة)، path=/، sameSite=lax، httpOnly (لا يحتاجه JS).
            $response->withCookie(cookie(
                name: 'currency',
                value: $code,
                minutes: 60 * 24 * 365,
                path: '/',
                domain: null,
                secure: $request->secure(),
                httpOnly: true,
                raw: false,
                sameSite: 'lax',
            ));
        }

        return $response;
    }

    /**
     * وجهةٌ داخليّة آمنة من ترويسة Referer: نأخذ **المسار فقط** (لا المضيف) فيتعذّر
     * توجيه الزائر لموقعٍ خارجيّ. نرفض المسار البروتوكوليّ (//host) وأيّ مسارٍ لا يبدأ
     * بشرطةٍ واحدة، ونعود للجذر عندها. (فحص بادئة المضيف قابلٌ للتجاوز عبر
     * qasaqis.store.evil.com أو qasaqis.store@evil.com.)
     */
    private function safeBack(Request $request): string
    {
        $referer = (string) $request->headers->get('referer', '');
        $path = parse_url($referer, PHP_URL_PATH);

        if (! is_string($path) || $path === '' || $path[0] !== '/' || str_starts_with($path, '//')) {
            return '/';
        }

        $query = parse_url($referer, PHP_URL_QUERY);

        return $path.(is_string($query) && $query !== '' ? '?'.$query : '');
    }
}
