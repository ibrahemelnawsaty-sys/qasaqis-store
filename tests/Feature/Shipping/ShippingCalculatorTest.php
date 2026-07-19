<?php

declare(strict_types=1);

namespace Tests\Feature\Shipping;

use App\Enums\ShippingCostSource;
use App\Models\Country;
use App\Models\Governorate;
use App\Models\Setting;
use App\Models\ShippingZone;
use App\Services\Shipping\ShippingCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * سلسلة حسم سعر الشحن (M8): محافظة → دولة → منطقة، مع كوبون وعتبة فوقها.
 *
 * محور الاختبار كله هو التفريق بين NULL و0.00:
 *   NULL = «لم يُضبط» ⇒ ورِّث من الرتبة الأعلى.
 *   0.00 = «مجاني عمدًا» ⇒ توقّف، لا وراثة.
 * خلطهما هو العيب الذي أوقفته مراجعتان عدائيتان قبل كتابة الهجرة، وهذه
 * الاختبارات هي ما يمنع عودته.
 *
 * HONESTY (1.3/1.5): لم تُشغَّل محليًا (لا vendor/). تعمل في CI على MySQL 8.
 */
final class ShippingCalculatorTest extends TestCase
{
    use RefreshDatabase;

    private ShippingCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = app(ShippingCalculator::class);
        ShippingCalculator::flushCache();
    }

    protected function tearDown(): void
    {
        ShippingCalculator::flushCache();
        parent::tearDown();
    }

    private function zone(string $code, string $flat = '300.00', bool $active = true): ShippingZone
    {
        return ShippingZone::create([
            'code' => $code,
            'name_ar' => 'منطقة '.$code,
            'name_en' => $code,
            'flat_cost' => $flat,
            'is_active' => $active,
            'sort_order' => 0,
        ]);
    }

    private function country(string $iso, ShippingZone $zone, ?string $cost = null): Country
    {
        return Country::create([
            'iso_code' => $iso,
            'name_ar' => 'دولة '.$iso,
            'name_en' => $iso,
            'shipping_zone_id' => $zone->id,
            'shipping_cost' => $cost,
            'is_active' => true,
            'sort_order' => 0,
        ]);
    }

    private function governorate(string $name, ?string $cost = null): Governorate
    {
        return Governorate::create([
            'name_ar' => $name,
            'shipping_cost' => $cost,
            'is_active' => true,
            'sort_order' => 0,
        ]);
    }

    // ── السلسلة ──────────────────────────────────────────────────────────────

    public function test_governorate_price_wins_over_country_and_zone(): void
    {
        $zone = $this->zone('EGYPT', '300.00');
        $this->country('EG', $zone, '80.00');
        $this->governorate('القاهرة', '45.00');

        $quote = $this->calculator->quote('EG', 'القاهرة', '500.00');

        $this->assertSame('45.00', $quote->cost);
        $this->assertSame(ShippingCostSource::Governorate, $quote->source);
    }

    public function test_unpriced_governorate_inherits_the_country_price(): void
    {
        $zone = $this->zone('EGYPT', '300.00');
        $this->country('EG', $zone, '80.00');
        $this->governorate('أسوان', null); // لم تُسعَّر بعد.

        $quote = $this->calculator->quote('EG', 'أسوان', '500.00');

        $this->assertSame('80.00', $quote->cost);
        $this->assertSame(ShippingCostSource::Country, $quote->source);
        $this->assertTrue($quote->source->isInherited());
    }

    public function test_unpriced_country_falls_through_to_the_zone(): void
    {
        $zone = $this->zone('GULF', '300.00');
        $this->country('SA', $zone, null);

        $quote = $this->calculator->quote('SA', null, '500.00');

        $this->assertSame('300.00', $quote->cost);
        $this->assertSame(ShippingCostSource::Zone, $quote->source);
    }

    public function test_zero_on_the_governorate_stops_inheritance_and_means_deliberately_free(): void
    {
        $zone = $this->zone('EGYPT', '300.00');
        $this->country('EG', $zone, '80.00');
        $this->governorate('القاهرة', '0.00'); // قرار مجانية صريح.

        $quote = $this->calculator->quote('EG', 'القاهرة', '100.00');

        // لو عُومل 0.00 كـ«لم يُضبط» لورث 80.00 من الدولة — وهذا العيب بعينه.
        $this->assertSame('0.00', $quote->cost);
        $this->assertSame(ShippingCostSource::Governorate, $quote->source);
        $this->assertTrue($quote->isFree());
    }

    public function test_zero_on_the_country_also_stops_inheritance(): void
    {
        $zone = $this->zone('GULF', '300.00');
        $this->country('SA', $zone, '0.00');

        $quote = $this->calculator->quote('SA', null, '100.00');

        $this->assertSame('0.00', $quote->cost);
        $this->assertSame(ShippingCostSource::Country, $quote->source);
    }

    // ── الرفض بدل الصفر الصامت ───────────────────────────────────────────────

    public function test_unknown_country_is_unavailable_not_free(): void
    {
        $quote = $this->calculator->quote('ZZ', null, '500.00');

        $this->assertTrue($quote->isUnavailable());
        $this->assertFalse($quote->isFree());
    }

    public function test_inactive_zone_is_unavailable_not_free(): void
    {
        $zone = $this->zone('GULF', '300.00', active: false);
        $this->country('SA', $zone, null);

        $quote = $this->calculator->quote('SA', null, '500.00');

        // قبولها بصفر يعني شحن الطلب مجانًا إلى الخارج بلا قرار من أحد.
        $this->assertTrue($quote->isUnavailable());
    }

    public function test_egypt_without_a_governorates_table_row_still_prices_from_the_country(): void
    {
        $zone = $this->zone('EGYPT', '300.00');
        $this->country('EG', $zone, '80.00');
        // لا صف محافظة إطلاقًا (اختبارات قائمة تُرسل «القاهرة» بلا بذر الجدول).

        $quote = $this->calculator->quote('EG', 'القاهرة', '500.00');

        $this->assertSame('80.00', $quote->cost);
        $this->assertSame(ShippingCostSource::Country, $quote->source);
    }

    // ── العتبة والكوبون ──────────────────────────────────────────────────────

    private function threshold(string $value): void
    {
        Setting::create([
            'group' => 'shipping',
            'key' => ShippingCalculator::FREE_THRESHOLD_KEY,
            'value' => $value,
            'type' => 'string',
        ]);
        ShippingCalculator::flushCache();
    }

    public function test_threshold_is_measured_after_discount(): void
    {
        $zone = $this->zone('EGYPT', '300.00');
        $this->country('EG', $zone, '80.00');
        $this->threshold('500.00');

        // سلة 600 قبل الخصم صارت 450 بعده ⇒ دون العتبة ⇒ الشحن يُدفع.
        $quote = $this->calculator->quote('EG', null, '450.00');

        $this->assertSame('80.00', $quote->cost);
        $this->assertSame('50.00', $quote->remainingForFreeShipping);
    }

    public function test_reaching_the_threshold_makes_shipping_free(): void
    {
        $zone = $this->zone('EGYPT', '300.00');
        $this->country('EG', $zone, '80.00');
        $this->threshold('500.00');

        $quote = $this->calculator->quote('EG', null, '500.00');

        $this->assertSame('0.00', $quote->cost);
        $this->assertSame(ShippingCostSource::FreeThreshold, $quote->source);
        $this->assertNull($quote->remainingForFreeShipping);
    }

    public function test_zero_threshold_means_no_threshold_not_everything_free(): void
    {
        $zone = $this->zone('EGYPT', '300.00');
        $this->country('EG', $zone, '80.00');
        $this->threshold('0.00'); // خطأ إدخال شائع.

        $quote = $this->calculator->quote('EG', null, '10.00');

        // لو عُوملت كعتبة صفر لصار كل شحن في المتجر مجانيًا بصمت.
        $this->assertSame('80.00', $quote->cost);
        $this->assertNull($this->calculator->freeShippingThreshold());
    }

    public function test_coupon_free_shipping_beats_every_rank(): void
    {
        $zone = $this->zone('EGYPT', '300.00');
        $this->country('EG', $zone, '80.00');
        $this->governorate('القاهرة', '45.00');

        $quote = $this->calculator->quote('EG', 'القاهرة', '100.00', couponFreeShipping: true);

        $this->assertSame('0.00', $quote->cost);
        $this->assertSame(ShippingCostSource::Coupon, $quote->source);
    }

    public function test_no_threshold_configured_means_no_progress_bar(): void
    {
        $zone = $this->zone('EGYPT', '300.00');
        $this->country('EG', $zone, '80.00');

        $quote = $this->calculator->quote('EG', null, '100.00');

        $this->assertFalse($quote->hasFreeShippingProgress());
    }

    // ── مدة التوصيل ──────────────────────────────────────────────────────────

    public function test_delivery_days_follow_the_same_chain(): void
    {
        $zone = $this->zone('EGYPT', '300.00');
        $zone->update(['delivery_days_min' => 7, 'delivery_days_max' => 14]);
        $this->country('EG', $zone, '80.00')->update(['delivery_days_min' => 3, 'delivery_days_max' => 5]);
        ShippingCalculator::flushCache();

        $quote = $this->calculator->quote('EG', null, '100.00');

        $this->assertSame(3, $quote->deliveryDaysMin);
        $this->assertSame(5, $quote->deliveryDaysMax);
    }

    public function test_a_governorate_priced_without_days_inherits_days_only(): void
    {
        $zone = $this->zone('EGYPT', '300.00');
        $this->country('EG', $zone, '80.00')->update(['delivery_days_min' => 3, 'delivery_days_max' => 5]);
        $this->governorate('أسوان', '95.00'); // سعر خاص، بلا مدة.
        ShippingCalculator::flushCache();

        $quote = $this->calculator->quote('EG', 'أسوان', '100.00');

        // السعر من المحافظة والمدة من الدولة — السلسلتان مستقلتان.
        $this->assertSame('95.00', $quote->cost);
        $this->assertSame(ShippingCostSource::Governorate, $quote->source);
        $this->assertSame(3, $quote->deliveryDaysMin);
    }
}
