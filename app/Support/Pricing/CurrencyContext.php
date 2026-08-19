<?php

declare(strict_types=1);

namespace App\Support\Pricing;

use App\Models\Currency;

/**
 * العملة النشطة للطلب الحاليّ. يضبطها وسيط SetCurrency مرّةً (من ?currency/كوكي/
 * Accept-Language)، وتقرؤها PricingService والقوالب. مفردة لكلّ طلب (singleton).
 * ترجع بأمانٍ إلى الأساس (EGP) قبل الهجرة أو في سياق الكونسول.
 */
final class CurrencyContext
{
    private ?Currency $current = null;

    public function set(Currency $currency): void
    {
        $this->current = $currency;
    }

    public function get(): Currency
    {
        return $this->current ??= Currency::baseFallback();
    }

    public function isBase(): bool
    {
        return $this->get()->isBase();
    }
}
