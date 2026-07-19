<?php

declare(strict_types=1);

namespace App\Support\Shipping;

use App\Enums\ShippingCostSource;

/**
 * نتيجة حساب الشحن لوجهة وسلة معيّنتين (M8).
 *
 * كائن واحد يخدم ثلاثة مستهلكين بلا حساب مكرّر: صفحة الدفع (تعرض الإجمالي الحيّ)،
 * و`PlaceOrderAction` (يحفظ `shipping_total`)، ولوحة المالك (تفسّر مصدر السعر).
 * **الرقم المعروض للعميلة والرقم المحفوظ في الطلب يخرجان من هنا حصرًا** — وهذا
 * شرط قبول لا تفصيل تنفيذي.
 *
 * `cost` نصّ عشري دائمًا (bcmath عبر App\Support\Money) — لا float (بند 3.5).
 */
final readonly class ShippingQuote
{
    public function __construct(
        public string $cost,
        public ShippingCostSource $source,
        public ?int $deliveryDaysMin = null,
        public ?int $deliveryDaysMax = null,
        /** المتبقّي لبلوغ عتبة الشحن المجاني — null حين لا عتبة أو بُلغت. */
        public ?string $remainingForFreeShipping = null,
    ) {
    }

    /** لا سعر في أي رتبة: الطلب يُرفض بدل قبوله بصفر صامت. */
    public function isUnavailable(): bool
    {
        return $this->source === ShippingCostSource::Unavailable;
    }

    public function isFree(): bool
    {
        return ! $this->isUnavailable() && $this->cost === '0.00';
    }

    /** هل يستحق عرض شريط «أضيفي X ج.م ليصبح الشحن مجانيًا»؟ */
    public function hasFreeShippingProgress(): bool
    {
        return $this->remainingForFreeShipping !== null;
    }
}
