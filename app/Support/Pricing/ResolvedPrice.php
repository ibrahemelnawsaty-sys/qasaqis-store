<?php

declare(strict_types=1);

namespace App\Support\Pricing;

use App\Models\Currency;

/**
 * نتيجة حلّ سعرٍ للعرض بعملةٍ ما: المبلغ (والمشطوب إن وُجد) بعملة الوجهة، ومصدره
 * (أساس/تجاوز يدويّ/تحويل آليّ). **للعرض فقط** — لا يدخل مسار المال (يبقى بالجنيه).
 */
final class ResolvedPrice
{
    public function __construct(
        public readonly float $amount,
        public readonly ?float $old,
        public readonly Currency $currency,
        public readonly string $source, // base | manual | auto
        public readonly float $baseEgp,
    ) {}

    public function isOnSale(): bool
    {
        return $this->old !== null && $this->old > $this->amount;
    }

    public function formattedAmount(): string
    {
        return $this->formatValue($this->amount);
    }

    public function formattedOld(): ?string
    {
        return $this->old === null ? null : $this->formatValue($this->old);
    }

    /**
     * التجاوز اليدويّ يُعرَض بدقّته المُدخَلة (formatExact)؛ الأساس والتحويل الآليّ
     * بخانات خطوة التقريب (format).
     */
    private function formatValue(float $value): string
    {
        return $this->source === 'manual'
            ? $this->currency->formatExact($value)
            : $this->currency->format($value);
    }

    /** نسبة الخصم الصحيحة (0–100) أو null. */
    public function discountPercent(): ?int
    {
        if (! $this->isOnSale() || $this->old === null || $this->old <= 0.0) {
            return null;
        }

        return (int) round((($this->old - $this->amount) / $this->old) * 100);
    }
}
