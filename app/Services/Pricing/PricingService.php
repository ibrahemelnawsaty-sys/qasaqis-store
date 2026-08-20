<?php

declare(strict_types=1);

namespace App\Services\Pricing;

use App\Models\Book;
use App\Models\BookCountryPrice;
use App\Models\Currency;
use App\Support\Pricing\CurrencyContext;
use App\Support\Pricing\ResolvedPrice;

/**
 * المصدر الوحيد لحلّ سعر العرض. الترتيب:
 *   1) لا سعر أساس (price === null) ⇒ null (قاعدة الصدق: لا يُختلَق سعر بأيّ عملة).
 *   2) العملة القاعدة (EGP) ⇒ السعر الأساس كما هو.
 *   3) تجاوز يدويّ فعّال في book_country_prices لهذه العملة ⇒ يُمنَح كما هو.
 *   4) وإلا تحويلٌ آليّ من الأساس (÷ egp_per_unit + تقريب).
 *
 * **للعرض فقط.** مسار المال (السلة/الدفع/الطلب) يبقى بالجنيه دومًا (إعادة تسعير خادميّة).
 */
final class PricingService
{
    /**
     * خرائط التجاوزات اليدويّة محمّلةً دفعةً واحدة لكلّ عملة (مفردة لكلّ طلب): تفادي
     * N+1 على الشبكات. [رمز العملة => [معرّف الكتاب => BookCountryPrice]].
     *
     * @var array<string, array<int, BookCountryPrice>>
     */
    private array $overrideMaps = [];

    public function __construct(private readonly CurrencyContext $context) {}

    public function resolve(Book $book, ?Currency $currency = null): ?ResolvedPrice
    {
        if ($book->price === null) {
            return null; // لا سعر أساس ⇒ لا سعر بأيّ عملة
        }

        $currency ??= $this->context->get();
        $baseEgp = (float) $book->price;
        $baseOld = $book->old_price === null ? null : (float) $book->old_price;

        // العملة القاعدة: السعر الأساس بلا تحويل.
        if ($currency->isBase()) {
            return new ResolvedPrice($baseEgp, $baseOld, $currency, 'base', $baseEgp);
        }

        // تجاوز يدويّ (إن وُجد وفعّال).
        $override = $this->override($book, $currency->code);
        if ($override !== null) {
            $overOld = $override->old_price === null ? null : (float) $override->old_price;

            return new ResolvedPrice((float) $override->price, $overOld, $currency, 'manual', $baseEgp);
        }

        // تحويلٌ آليّ من الأساس. السعر يُقرَّب صعودًا للخطوة (هامش). أمّا السعر القديم
        // (المشطوب) فيُشتَقّ من السعر + التوفيرِ المحوَّل بدقّة العرض — لا بتحويلٍ مستقلٍّ
        // للقديم؛ فتقريبُ-الخطوة المستقلّ يضخّم الفارقَ ويشوّه نسبة الخصم أو يُخفي بيعًا
        // حقيقيًّا. وإن كان التوفير أصغر من أصغر وحدة عرض ⇒ لا خصمَ يُعرَض (صدقٌ لا تناقض).
        $amount = $currency->convertFromEgp($baseEgp);
        $old = null;
        if ($baseOld !== null && $baseOld > $baseEgp) {
            $savings = $currency->convertRaw($baseOld - $baseEgp);
            if ($savings > 0) {
                $old = $amount + $savings;
            }
        }

        return new ResolvedPrice($amount, $old, $currency, 'auto', $baseEgp);
    }

    /**
     * التجاوز اليدويّ الفعّال لهذا الكتاب/العملة — من العلاقة المُحمّلة إن وُجدت، وإلّا
     * من خريطةٍ تُحمَّل دفعةً واحدة لكلّ عملة (فلا N+1 على الشبكات).
     */
    private function override(Book $book, string $currencyCode): ?BookCountryPrice
    {
        if ($book->relationLoaded('countryPrices')) {
            return $book->countryPrices
                ->firstWhere(fn (BookCountryPrice $p): bool => $p->currency_code === $currencyCode && (bool) $p->is_active);
        }

        $map = $this->overrideMaps[$currencyCode] ??= $this->loadOverrides($currencyCode);

        return $map[$book->id] ?? null;
    }

    /**
     * كلّ التجاوزات الفعّالة لعملةٍ ما، مفهرسةً بمعرّف الكتاب. الجدول متناثر (استثناءات
     * فقط)، فاستعلامٌ واحد يخدم كلّ بطاقات الصفحة. rescue: آمن قبل الهجرة.
     *
     * @return array<int, BookCountryPrice>
     */
    private function loadOverrides(string $currencyCode): array
    {
        return rescue(
            fn (): array => BookCountryPrice::query()
                ->where('currency_code', $currencyCode)
                ->where('is_active', true)
                ->get()
                ->keyBy('book_id')
                ->all(),
            [],
            report: false,
        );
    }
}
