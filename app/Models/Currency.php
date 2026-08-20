<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * عملة عرض. القاعدة EGP مصدر الحقيقة الماليّ؛ البقيّة تُحوَّل من الأساس للعرض فقط
 * عبر egp_per_unit (المعدّل بالهامش) + تقريب. انظر migration create_currencies_table.
 *
 * @property string $code
 * @property string $symbol
 * @property string $name_ar
 * @property float $egp_per_unit
 * @property float|null $reference_rate
 * @property int $decimals
 * @property float $rounding_step
 * @property string $rounding_mode
 * @property bool $is_active
 * @property bool $is_base
 * @property int $sort_order
 */
class Currency extends Model
{
    protected $primaryKey = 'code';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'code', 'symbol', 'name_ar', 'egp_per_unit', 'reference_rate',
        'decimals', 'rounding_step', 'rounding_mode', 'is_active', 'is_base', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'egp_per_unit' => 'decimal:4',
            'reference_rate' => 'decimal:4',
            'decimals' => 'integer',
            'rounding_step' => 'decimal:4',
            'is_active' => 'boolean',
            'is_base' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /** @param  Builder<Currency>  $query */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }

    public function isBase(): bool
    {
        return (bool) $this->is_base || $this->code === self::baseCode();
    }

    public static function baseCode(): string
    {
        return 'EGP';
    }

    /**
     * العملة الأساس من القاعدة، أو عملةٌ اصطناعيّة في الذاكرة لو لم تُهاجَر/تُزرَع
     * الجداول بعد (الدستور: تُصيَّر الواجهات قبل الهجرة). لا تصل قاعدة البيانات إلّا
     * عند الحاجة، وترجع بأمانٍ إلى «ج.م» بلا خانات.
     */
    public static function baseFallback(): self
    {
        $base = rescue(
            fn () => self::query()->where('is_base', true)->first()
                ?? self::query()->whereKey(self::baseCode())->first(),
            null,
            report: false,
        );

        if ($base instanceof self) {
            return $base;
        }

        return (new self)->forceFill([
            'code' => self::baseCode(),
            'symbol' => __('common.currency'),
            'name_ar' => __('common.currency'),
            'egp_per_unit' => 1,
            'reference_rate' => null,
            'decimals' => 0,
            'rounding_step' => 1,
            'rounding_mode' => 'nearest',
            'is_active' => true,
            'is_base' => true,
            'sort_order' => 0,
        ]);
    }

    /**
     * يحوّل مبلغًا بالجنيه إلى هذه العملة (بعد الهامش والتقريب). القاعدة تُرجِعه كما هو.
     */
    public function convertFromEgp(float $egp): float
    {
        if ($this->isBase()) {
            return round($egp, $this->decimals);
        }

        $rate = (float) $this->egp_per_unit;
        if ($rate <= 0) {
            // معدّل غير صالح: لا نختلق سعرًا خاطئًا — نرجع للأساس بلا تحويل.
            return round($egp, $this->decimals);
        }

        return $this->applyRounding($egp / $rate);
    }

    /** يقرّب القيمة إلى أقرب خطوة (صعودًا أو لأقرب) بحسب إعداد العملة. */
    public function applyRounding(float $value): float
    {
        $step = (float) $this->rounding_step;

        if ($step <= 0) {
            return round($value, $this->decimals);
        }

        $units = $value / $step;
        $rounded = $this->rounding_mode === 'up' ? ceil($units) : round($units);

        return round($rounded * $step, $this->decimals);
    }

    /**
     * يحوّل مبلغًا بالجنيه لهذه العملة **بخانات العرض فقط، بلا تقريب-خطوة** (round لا
     * ceil): لفروق التوفير كي لا يضخّم تقريبُ-الخطوة المستقلّ فارقَ السعر القديم.
     */
    public function convertRaw(float $egp): float
    {
        if ($this->isBase()) {
            return round($egp, $this->decimals);
        }

        $rate = (float) $this->egp_per_unit;
        if ($rate <= 0) {
            return round($egp, $this->decimals);
        }

        return round($egp / $rate, $this->displayDecimals());
    }

    /** عدد خانات العرض المشتقّة من خطوة التقريب (5⇒0، 0.5⇒1، 0.25⇒2)، بحدّ decimals. */
    public function displayDecimals(): int
    {
        $step = (float) $this->rounding_step;

        // بلا تقريب-خطوة (step<=0): خانات العملة الكاملة (يطابق applyRounding).
        if ($step <= 0) {
            return $this->decimals;
        }

        if ($step != floor($step)) {
            // خطوة كسريّة (0.5/0.25): اشتقّ الخانات منها بحدّ decimals.
            $stepStr = rtrim(rtrim(number_format($step, 4, '.', ''), '0'), '.');
            $dot = strpos($stepStr, '.');
            $fromStep = $dot === false ? 0 : strlen($stepStr) - $dot - 1;

            return min($this->decimals, max(0, $fromStep));
        }

        return 0; // خطوة صحيحة (1/5/10) ⇒ بلا كسور
    }

    /** الرقم فقط (بلا رمز) لسعرٍ محوّلٍ آليًّا، بخانات مشتقّة من خطوة التقريب: «30». */
    public function formatNumber(float $amount): string
    {
        return number_format($amount, $this->displayDecimals());
    }

    /**
     * الرقم فقط لسعرٍ **يدويّ** (تجاوز) محافظًا على دقّته كما أدخلها المالك (خانات
     * العملة مع إزالة الأصفار الزائدة): 35.000 ⇒ «35»، 34.500 ⇒ «34.5». لا يُقرَّب
     * لخطوة التحويل الآليّ كي لا يُطمَس السعر الذي حدّده المالك بنفسه.
     */
    public function formatExactNumber(float $amount): string
    {
        $formatted = number_format($amount, $this->decimals);

        return str_contains($formatted, '.') ? rtrim(rtrim($formatted, '0'), '.') : $formatted;
    }

    /** رقم + رمز لسعرٍ محوّلٍ آليًّا: «30 ر.س». */
    public function format(float $amount): string
    {
        return $this->formatNumber($amount).' '.$this->symbol;
    }

    /** رقم + رمز لسعرٍ يدويّ (تجاوز) بدقّته: «34.5 ر.س». */
    public function formatExact(float $amount): string
    {
        return $this->formatExactNumber($amount).' '.$this->symbol;
    }
}
