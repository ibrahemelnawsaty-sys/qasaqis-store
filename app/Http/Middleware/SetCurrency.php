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
 *   ?currency=  ←  كوكي «currency»  ←  تخمين Accept-Language  ←  الأساس (EGP).
 *
 * قراءةٌ فقط (لا يكتب الكوكي — ذلك من CurrencyController::switch). مقصور على مجموعة
 * web (لا يمسّ الويبهوك/الـJSON). القائمة النشطة مخزّنة مؤقّتًا (تُبطَل عند حفظ عملة).
 */
class SetCurrency
{
    public const CACHE_KEY = 'shop.currencies.active';

    /** خريطة رمز الدولة ISO ⇒ رمز العملة، لتخمين Accept-Language. */
    private const REGION_CURRENCY = [
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

        // 3) تخمينٌ خفيف من Accept-Language (قابل للتجاوز بالمبدّل دومًا).
        $guess = $this->fromAcceptLanguage($request, $active);
        if ($guess !== null) {
            return $guess;
        }

        // 4) الافتراضيّ: الأساس (EGP) — يحمي السوق المصريّ من كشفٍ خاطئ.
        return Currency::baseCode();
    }

    /**
     * تخمين عملةٍ من ترويسة Accept-Language. حذِرٌ عمدًا: منطقة خليجيّة/مصريّة صريحة ⇒
     * عملتها؛ زائرٌ *غير عربيّ* بمنطقةٍ أجنبيّة ⇒ الدولار (إن كان مفعّلًا)؛ ما عدا ذلك
     * ⇒ لا تخمين (يبقى EGP). فلا يُحوَّل الزائر العربيّ/المصريّ للدولار خطأً.
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
        $regions = array_map('strtoupper', $matches[1]);

        foreach ($regions as $region) {
            $code = self::REGION_CURRENCY[$region] ?? null;
            if ($code !== null && $active->has($code)) {
                return $code;
            }
        }

        // لا عربيّة في الترويسة + منطقة أجنبيّة معروفة ⇒ الدولار (بقيّة العالم).
        $hasArabic = str_contains(strtolower($header), 'ar');
        if (! $hasArabic && $regions !== [] && $active->has('USD')) {
            return 'USD';
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
