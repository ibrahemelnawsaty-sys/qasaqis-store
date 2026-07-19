<?php

declare(strict_types=1);

namespace App\Services\Shipping;

use App\Enums\ShippingCostSource;
use App\Models\Country;
use App\Models\Governorate;
use App\Models\Setting;
use App\Models\ShippingZone;
use App\Support\Money;
use App\Support\Shipping\ShippingQuote;
use Illuminate\Support\Facades\Cache;

/**
 * مصدر الحقيقة الوحيد لسعر الشحن ومدة التوصيل (M8).
 *
 * كان الحساب محبوسًا في `PlaceOrderAction::resolveShipping` الخاصة، فلم يكن ممكنًا
 * عرض الإجمالي للعميلة قبل الضغط على «تأكيد الطلب» — وهي أعنف نقطة تسرّب في
 * المسار: تلتزم بمبلغ لا تعرفه. استخراجه هنا يجعل الرقم المعروض والرقم المحفوظ
 * يخرجان من الدالة نفسها.
 *
 * ── سلسلة الحسم ───────────────────────────────────────────────────────────────
 *   1. كوبون شحن مجاني            ⇒ 0.00
 *   2. عتبة الشحن المجاني (بعد الخصم) ⇒ 0.00
 *   3. المحافظة (مصر فقط)
 *   4. الدولة
 *   5. منطقة الشحن
 *   6. لا شيء                      ⇒ Unavailable (يُرفض الطلب، لا يُقبل بصفر صامت)
 *
 * NULL = «لم يُضبط» ⇒ انتقل للرتبة التالية. 0.00 = «مجاني عمدًا» ⇒ توقّف.
 * التفريق بينهما هو ما يجعل المالك يرى الفرق بين قرار ونسيان.
 *
 * ── العتبة تُقاس بعد الخصم ────────────────────────────────────────────────────
 * قرار المالك. قياسها قبل الخصم يجعل كوبون 30% يمنح شحنًا مجانيًا على سلة صغيرة،
 * فيخسر المتجر مرتين في الطلب الواحد.
 *
 * ── الأداء ────────────────────────────────────────────────────────────────────
 * جداول الشحن شبه ثابتة وتُقرأ في كل عرض لصفحة الدفع، فتُخبّأ صفوفها كاملة (لا
 * استعلام لكل تسعيرة). المفاتيح تحت بادئة واحدة ليُبطلها المورد الإداري دفعة
 * واحدة — الإبطال لا يصحّ أن يعتمد على ملاحظ Eloquent وحده لأن الإجراءات الجماعية
 * في Filament لا تُطلق أحداث الموديل.
 */
class ShippingCalculator
{
    public const CACHE_PREFIX = 'shipping:';

    private const CACHE_TTL_SECONDS = 3600;

    /** مفتاح عتبة الشحن المجاني في جدول الإعدادات. */
    public const FREE_THRESHOLD_KEY = 'free_shipping_threshold';

    /**
     * @param  string  $countryCode  ISO alpha-2 (EG, SA…)
     * @param  string|null  $governorate  اسم المحافظة كما في orders.governorate — لمصر فقط
     * @param  string  $subtotalAfterDiscount  المجموع الفرعي ناقص الخصم (نصّ عشري)
     * @param  bool  $couponFreeShipping  هل يمنح الكوبون المطبَّق شحنًا مجانيًا؟
     */
    public function quote(
        string $countryCode,
        ?string $governorate,
        string $subtotalAfterDiscount,
        bool $couponFreeShipping = false,
    ): ShippingQuote {
        [$daysMin, $daysMax] = $this->resolveDeliveryDays($countryCode, $governorate);

        if ($couponFreeShipping) {
            return new ShippingQuote(Money::ZERO, ShippingCostSource::Coupon, $daysMin, $daysMax);
        }

        $threshold = $this->freeShippingThreshold();

        if ($threshold !== null && Money::gte($subtotalAfterDiscount, $threshold)) {
            return new ShippingQuote(Money::ZERO, ShippingCostSource::FreeThreshold, $daysMin, $daysMax);
        }

        [$cost, $source] = $this->resolveCost($countryCode, $governorate);

        if ($source === ShippingCostSource::Unavailable) {
            return new ShippingQuote(Money::ZERO, $source, $daysMin, $daysMax);
        }

        return new ShippingQuote(
            cost: $cost,
            source: $source,
            deliveryDaysMin: $daysMin,
            deliveryDaysMax: $daysMax,
            remainingForFreeShipping: $this->remainingToThreshold($threshold, $subtotalAfterDiscount),
        );
    }

    /**
     * أول قيمة غير NULL في السلسلة تفوز.
     *
     * @return array{0: string, 1: ShippingCostSource}
     */
    private function resolveCost(string $countryCode, ?string $governorate): array
    {
        if ($countryCode === 'EG' && $governorate !== null) {
            $row = $this->governorates()[$governorate] ?? null;

            if ($row !== null && $row['shipping_cost'] !== null) {
                return [Money::normalize($row['shipping_cost']), ShippingCostSource::Governorate];
            }
        }

        $country = $this->countries()[$countryCode] ?? null;

        if ($country === null) {
            return [Money::ZERO, ShippingCostSource::Unavailable];
        }

        if ($country['shipping_cost'] !== null) {
            return [Money::normalize($country['shipping_cost']), ShippingCostSource::Country];
        }

        $zone = $this->zones()[$country['shipping_zone_id']] ?? null;

        // منطقة معطّلة أو محذوفة ⇒ لا سعر مُحدَّد. نرفض بدل القبول بصفر (يكمّل
        // حارس is_active في التحقق، ويطابق سلوك resolveShipping القائم).
        if ($zone === null || $zone['is_active'] !== true) {
            return [Money::ZERO, ShippingCostSource::Unavailable];
        }

        return [Money::normalize($zone['flat_cost']), ShippingCostSource::Zone];
    }

    /**
     * مدة التوصيل بالسلسلة نفسها. تُحسم مستقلةً عن السعر عمدًا: محافظة قد تُسعَّر
     * بلا مدة، فترث المدة من دولتها بينما سعرها خاصّ بها.
     *
     * @return array{0: int|null, 1: int|null}
     */
    private function resolveDeliveryDays(string $countryCode, ?string $governorate): array
    {
        if ($countryCode === 'EG' && $governorate !== null) {
            $row = $this->governorates()[$governorate] ?? null;

            if ($row !== null && $row['delivery_days_min'] !== null) {
                return [(int) $row['delivery_days_min'], $this->nullableInt($row['delivery_days_max'])];
            }
        }

        $country = $this->countries()[$countryCode] ?? null;

        if ($country === null) {
            return [null, null];
        }

        if ($country['delivery_days_min'] !== null) {
            return [(int) $country['delivery_days_min'], $this->nullableInt($country['delivery_days_max'])];
        }

        $zone = $this->zones()[$country['shipping_zone_id']] ?? null;

        if ($zone === null || $zone['delivery_days_min'] === null) {
            return [null, null];
        }

        return [(int) $zone['delivery_days_min'], $this->nullableInt($zone['delivery_days_max'])];
    }

    /** العتبة العامة، أو null حين لا عتبة مضبوطة (فلا شحن مجاني تلقائي). */
    public function freeShippingThreshold(): ?string
    {
        $raw = Cache::remember(
            self::CACHE_PREFIX.'free_threshold',
            self::CACHE_TTL_SECONDS,
            static fn () => Setting::query()->where('key', self::FREE_THRESHOLD_KEY)->value('value')
        );

        if ($raw === null || $raw === '') {
            return null;
        }

        $normalized = Money::normalize($raw);

        // عتبة صفر أو سالبة تعني «بلا عتبة» لا «كل شيء مجاني» — وإلا حوّل خطأ
        // إدخال واحد كل شحنات المتجر إلى مجانية بصمت.
        return Money::isPositive($normalized) ? $normalized : null;
    }

    private function remainingToThreshold(?string $threshold, string $subtotalAfterDiscount): ?string
    {
        if ($threshold === null) {
            return null;
        }

        $remaining = Money::sub($threshold, $subtotalAfterDiscount);

        return Money::isPositive($remaining) ? $remaining : null;
    }

    private function nullableInt(mixed $value): ?int
    {
        return $value === null ? null : (int) $value;
    }

    /**
     * @return array<string, array{shipping_cost: string|null, delivery_days_min: int|null, delivery_days_max: int|null}>
     */
    private function governorates(): array
    {
        return Cache::remember(
            self::CACHE_PREFIX.'governorates',
            self::CACHE_TTL_SECONDS,
            static fn () => Governorate::query()
                ->where('is_active', true)
                ->get(['name_ar', 'shipping_cost', 'delivery_days_min', 'delivery_days_max'])
                ->keyBy('name_ar')
                ->map(static fn (Governorate $g): array => [
                    'shipping_cost' => $g->shipping_cost,
                    'delivery_days_min' => $g->delivery_days_min,
                    'delivery_days_max' => $g->delivery_days_max,
                ])
                ->all()
        );
    }

    /**
     * @return array<string, array{shipping_zone_id: int, shipping_cost: string|null, delivery_days_min: int|null, delivery_days_max: int|null}>
     */
    private function countries(): array
    {
        return Cache::remember(
            self::CACHE_PREFIX.'countries',
            self::CACHE_TTL_SECONDS,
            static fn () => Country::query()
                ->where('is_active', true)
                ->get(['iso_code', 'shipping_zone_id', 'shipping_cost', 'delivery_days_min', 'delivery_days_max'])
                ->keyBy('iso_code')
                ->map(static fn (Country $c): array => [
                    'shipping_zone_id' => (int) $c->shipping_zone_id,
                    'shipping_cost' => $c->shipping_cost,
                    'delivery_days_min' => $c->delivery_days_min,
                    'delivery_days_max' => $c->delivery_days_max,
                ])
                ->all()
        );
    }

    /**
     * @return array<int, array{flat_cost: string, is_active: bool, delivery_days_min: int|null, delivery_days_max: int|null}>
     */
    private function zones(): array
    {
        return Cache::remember(
            self::CACHE_PREFIX.'zones',
            self::CACHE_TTL_SECONDS,
            static fn () => ShippingZone::query()
                ->get(['id', 'flat_cost', 'is_active', 'delivery_days_min', 'delivery_days_max'])
                ->keyBy('id')
                ->map(static fn (ShippingZone $z): array => [
                    'flat_cost' => (string) $z->flat_cost,
                    'is_active' => (bool) $z->is_active,
                    'delivery_days_min' => $z->delivery_days_min,
                    'delivery_days_max' => $z->delivery_days_max,
                ])
                ->all()
        );
    }

    /**
     * إبطال الكاش بعد أي تعديل من اللوحة.
     *
     * يُستدعى صراحةً من المورد الإداري — بما فيه الإجراءات الجماعية، لأنها لا تُطلق
     * أحداث Eloquent فلا يكفي ملاحظ الموديل وحده.
     */
    public static function flushCache(): void
    {
        foreach (['free_threshold', 'governorates', 'countries', 'zones'] as $key) {
            Cache::forget(self::CACHE_PREFIX.$key);
        }
    }
}
