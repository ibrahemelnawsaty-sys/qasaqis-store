<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Currency;
use App\Support\Pricing\CurrencyContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * يحلّ العملة النشطة للطلب ويشاركها مع القوالب و PricingService. الأولويّة:
 *   ?currency=  ←  كوكي  ←  **دولة الزائر من Cloudflare (CF-IPCountry)**  ←
 *   تخمين Accept-Language  ←  الأساس (EGP).
 *
 * الكشف الأساسيّ = ترويسة CF-IPCountry التي يحقنها Cloudflare (دقيقة وفوريّة، بلا
 * نداء خارجيّ). خليج ⇒ عملته، مصر ⇒ الجنيه، بقيّة العالم ⇒ الدولار، والمجهول ⇒
 * تخمين اللغة ثمّ الجنيه. قبل تفعيل Cloudflare لا توجد الترويسة فيُعتمَد التخمين.
 *
 * قراءةٌ فقط. مقصور على مجموعة web (لا يمسّ الويبهوك/الـJSON). القائمة النشطة مخزّنة
 * مؤقّتًا (تُبطَل عند حفظ عملة).
 */
class SetCurrency
{
    public const CACHE_KEY = 'shop.currencies.active';

    /** خريطة رمز الدولة ISO 3166-1 ⇒ رمز العملة (لكشف Cloudflare وتخمين Accept-Language). */
    private const COUNTRY_CURRENCY = [
        'EG' => 'EGP', 'SA' => 'SAR', 'AE' => 'AED', 'QA' => 'QAR',
        'KW' => 'KWD', 'BH' => 'BHD', 'OM' => 'OMR',
    ];

    public function __construct(private readonly CurrencyContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $active = $this->activeCurrencies();

        $currency = $active->isEmpty()
            ? Currency::baseFallback()
            : ($active->get($this->resolveCode($request, $active)) ?? Currency::baseFallback());

        $this->context->set($currency);
        View::share('currency', $currency);
        View::share('currencies', $active->values());

        return $next($request);
    }

    /** @param  Collection<string, Currency>  $active */
    private function resolveCode(Request $request, Collection $active): string
    {
        // 1) وسيط الرابط ?currency= (روابط مشارَكة/اختبار) — بلا تثبيت كوكي.
        //    حذار: query('currency') قد تُرجِع مصفوفةً (?currency[]=x)، و(string)[] تُطلق
        //    تحذيرًا يصعّده Laravel إلى 500 — لذا نحرس النوع صراحةً قبل التحويل.
        $rawQuery = $request->query('currency');
        $q = is_string($rawQuery) ? strtoupper($rawQuery) : '';
        if ($active->has($q)) {
            return $q;
        }

        // 2) الكوكي (اختيار المستخدم الصريح عبر المبدّل) — نفس الحرص على النوع.
        $rawCookie = $request->cookie('currency');
        $cookie = is_string($rawCookie) ? strtoupper($rawCookie) : '';
        if ($active->has($cookie)) {
            return $cookie;
        }

        // 3) دولة الزائر من Cloudflare (CF-IPCountry) — الكشف الأساسيّ الدقيق.
        $byCountry = $this->fromCfCountry($request, $active);
        if ($byCountry !== null) {
            return $byCountry;
        }

        // 4) تخمينٌ خفيف من Accept-Language (احتياطيّ قبل تفعيل Cloudflare).
        $guess = $this->fromAcceptLanguage($request, $active);
        if ($guess !== null) {
            return $guess;
        }

        // 5) الافتراضيّ: الأساس (EGP) — يحمي السوق المصريّ من كشفٍ خاطئ.
        return Currency::baseCode();
    }

    /**
     * عملة الزائر من ترويسة Cloudflare CF-IPCountry (رمز دولة ISO alpha-2). القيم
     * الخاصّة (XX مجهول، T1 عبر Tor) تُهمَل فيُنتقَل للاحتياطيّ.
     *
     * @param  Collection<string, Currency>  $active
     */
    private function fromCfCountry(Request $request, Collection $active): ?string
    {
        $iso = $request->header('CF-IPCountry');

        return is_string($iso) && $iso !== ''
            ? $this->currencyForCountry(strtoupper($iso), $active)
            : null;
    }

    /**
     * يربط رمز دولة بعملةٍ نشطة: خليج/مصر ⇒ عملتها، وأيّ دولةٍ أخرى ⇒ الدولار (رغبة
     * المالك)، وإلّا الأساس. رمزٌ غير صالح (XX/T1/غير حرفين) ⇒ null (احتياطيّ لاحق).
     *
     * @param  Collection<string, Currency>  $active
     */
    private function currencyForCountry(string $iso, Collection $active): ?string
    {
        if (! preg_match('/^[A-Z]{2}$/', $iso) || in_array($iso, ['XX', 'T1'], true)) {
            return null;
        }

        $code = self::COUNTRY_CURRENCY[$iso] ?? 'USD';

        if ($active->has($code)) {
            return $code;
        }

        return $active->has(Currency::baseCode()) ? Currency::baseCode() : null;
    }

    /**
     * تخمينٌ *احتياطيّ* من Accept-Language قبل تفعيل Cloudflare فقط: منطقة خليجيّة/
     * مصريّة صريحة في اللغة ⇒ عملتها، وما عدا ذلك ⇒ لا تخمين (يبقى EGP). **لا نخمّن
     * الدولار من اللغة** — «بقيّة العالم ⇒ دولار» تأتي من CF-IPCountry الدقيق (لا من
     * اللغة)، حمايةً للسوق المصريّ من متصفّحٍ إنجليزيّ يظهر له الدولار خطأً.
     *
     * @param  Collection<string, Currency>  $active
     */
    private function fromAcceptLanguage(Request $request, Collection $active): ?string
    {
        $header = (string) $request->header('Accept-Language', '');
        if ($header === '') {
            return null;
        }

        preg_match_all('/[A-Za-z]{2,3}-([A-Za-z]{2})\b/', $header, $matches);

        foreach (array_map('strtoupper', $matches[1]) as $region) {
            $code = self::COUNTRY_CURRENCY[$region] ?? null;
            if ($code !== null && $active->has($code)) {
                return $code;
            }
        }

        return null;
    }

    /**
     * العملات النشطة مفهرسة بالرمز، مخزّنة مؤقّتًا. rescue: تُصيَّر الواجهات قبل الهجرة.
     *
     * @return Collection<string, Currency>
     */
    private function activeCurrencies(): Collection
    {
        return rescue(
            fn (): Collection => Cache::remember(
                self::CACHE_KEY,
                600,
                fn (): Collection => Currency::query()->active()->get()->keyBy('code'),
            ),
            collect(),
            report: false,
        );
    }
}
