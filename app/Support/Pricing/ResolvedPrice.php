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

    /** رمز العملة (للعرض بجانب الرقم في القوالب): «ر.س». */
    public function currencySymbol(): string
    {
        return $this->currency->symbol;
    }

    /** الرقم فقط (بلا رمز) — للقوالب التي تنسّق الرقم والرمز في عنصرين. */
    public function amountNumber(): string
    {
        return $this->source === 'manual'
            ? $this->currency->formatExactNumber($this->amount)
            : $this->currency->formatNumber($this->amount);
    }

    /** الرقم القديم فقط (المشطوب) أو null. */
    public function oldNumber(): ?string
    {
        if ($this->old === null) {
            return null;
        }

        return $this->source === 'manual'
            ? $this->currency->formatExactNumber($this->old)
            : $this->currency->formatNumber($this->old);
    }

    /** رقم + رمز مجموعين: «30 ر.س» — للسلة/البحث/واتساب. */
    public function formattedAmount(): string
    {
        return $this->formatValue($this->amount);
    }

    public function formattedOld(): ?string
    {
        return $this->old === null ? null : $this->formatValue($this->old);
    }

    /** قيمة التوفير (القديم − الحاليّ) بعملة الوجهة، أو null. */
    public function savings(): ?float
    {
        return $this->isOnSale() && $this->old !== null ? $this->old - $this->amount : null;
    }

    /** التوفير منسّقًا رقمًا + رمزًا: «40 ر.س» — أو null. */
    public function savingsFormatted(): ?string
    {
        $savings = $this->savings();

        return $savings === null ? null : $this->formatValue($savings);
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

    /**
     * نسبة الخصم الصحيحة (1–100) أو null. أدناها 1 عند وجود بيعٍ فعليّ كي تتّسق شارة
     * الخصم مع السعر المشطوب و«وفّري…» (كلّها تظهر معًا أو تختفي معًا).
     */
    public function discountPercent(): ?int
    {
        if (! $this->isOnSale() || $this->old === null || $this->old <= 0.0) {
            return null;
        }

        return max(1, (int) round((($this->old - $this->amount) / $this->old) * 100));
    }
}
