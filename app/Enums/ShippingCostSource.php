<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * من أين جاء سعر الشحن المعروض (M8).
 *
 * ليس تفصيلًا تجميليًا: هو ما يجعل التسعير قابلًا للتفسير في لوحة المالك. حين يرى
 * «45 ج.م — موروث من المنطقة» يعرف أن المحافظة غير مسعَّرة، وحين يرى «0.00 — مجاني
 * عمدًا» يعرف أنه قرار لا إهمال. بلا هذا التمييز يصبح كل صفر متشابهًا.
 */
enum ShippingCostSource: string
{
    /** سعر مضبوط على المحافظة نفسها. */
    case Governorate = 'governorate';

    /** سعر مضبوط على الدولة (المحافظة بلا سعر، أو طلب دولي). */
    case Country = 'country';

    /** الرتبة الأخيرة: سعر منطقة الشحن. */
    case Zone = 'zone';

    /** كوبون يمنح شحنًا مجانيًا — يتقدّم على كل ما سبق. */
    case Coupon = 'coupon';

    /** بلغت السلة عتبة الشحن المجاني (تُقاس بعد الخصم). */
    case FreeThreshold = 'free_threshold';

    /** لا سعر في أي رتبة — الطلب يُرفض ولا يُقبل بصفر صامت. */
    case Unavailable = 'unavailable';

    /** هل السعر موروث من رتبة أعلى؟ يُستخدم لشارة «موروث» في اللوحة. */
    public function isInherited(): bool
    {
        return $this === self::Country || $this === self::Zone;
    }
}
