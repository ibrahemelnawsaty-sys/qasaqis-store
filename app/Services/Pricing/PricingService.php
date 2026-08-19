<?php

declare(strict_types=1);

namespace App\Services\Pricing;

use App\Models\Book;
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

        // تحويلٌ آليّ من الأساس.
        $amount = $currency->convertFromEgp($baseEgp);
        $old = $baseOld === null ? null : $currency->convertFromEgp($baseOld);

        return new ResolvedPrice($amount, $old, $currency, 'auto', $baseEgp);
    }

    /** التجاوز اليدويّ الفعّال لهذا الكتاب/العملة — من العلاقة المُحمّلة إن وُجدت، وإلّا استعلام مفرد. */
    private function override(Book $book, string $currencyCode): ?object
    {
        if ($book->relationLoaded('countryPrices')) {
            return $book->countryPrices
                ->firstWhere(fn ($p): bool => $p->currency_code === $currencyCode && (bool) $p->is_active);
        }

        return $book->countryPrices()
            ->where('currency_code', $currencyCode)
            ->where('is_active', true)
            ->first();
    }
}
