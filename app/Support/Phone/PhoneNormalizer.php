<?php

declare(strict_types=1);

namespace App\Support\Phone;

/**
 * تطبيع رقم الجوال المصري إلى هوية واحدة قابلة للتخزين والمقارنة.
 *
 * منطق الاستخراج منقول حرفيًا من App\Actions\Order\FindGuestOrderAction::normalize()
 * (السطر 38): تجريد كل ما ليس رقمًا ثم أخذ آخر 10 خانات. هذا يتحمّل اختلاف البادئة
 * (+20 / 0020 / 20 / 0 / بلا بادئة) وعلامات التنسيق (مسافات، شرطات، أقواس) دون
 * فروع خاصة بكل بادئة.
 *
 * الإضافة الوحيدة فوق ذلك المنطق هي بوابة صحة: النتيجة تُقبل فقط إن طابقت شكل
 * الجوال المصري المطبّع (1 + [0125] + 8 أرقام)، وإلا فـ null. النمط مأخوذ من
 * App\Http\Requests\CheckoutRequest::EGYPT_PHONE_REGEX (السطر 24) بعد حذف جزء
 * البادئة الاختيارية منه، فلا يوجد رقم يقبله التحقق عند الشراء ويرفضه هذا الصنف.
 *
 * لماذا يلزم صنف منفصل بدل إعادة استخدام FindGuestOrderAction (الباب 8.3):
 * ذلك الفعل متساهل عمدًا — يعيد آخر 10 خانات لأي رقم بما فيه الأرقام الدولية
 * (M5: الشراء الدولي مسموح عبر INTL_PHONE_REGEX)، لأن مهمته مطابقة نصّية لا
 * إثبات مصرية الرقم. أما هذا الصنف فيخدم عمود الهوية customers.phone_normalized
 * وهو مصري حصرًا (CHAR(10)). لذلك:
 *
 *   ⚠️ يُمنع استبدال منطق FindGuestOrderAction بهذا الصنف — استبداله يُسقط قدرة
 *   العملاء الدوليين على تتبّع طلباتهم (يصبح normalize لهم null فلا يطابق أبدًا).
 *
 * قيود معلَنة (البند 1.5):
 * 1) الأرقام العربية-الهندية (٠١٢…) تُرجِع null: preg_replace('/\D/') بلا معدّل /u
 *    يجرّدها بايتًا بايتًا. هذا **مطابق** لسلوك FindGuestOrderAction وللتحقق في
 *    CheckoutRequest الذي يرفضها أصلًا، فلا يمكن أصلًا وجود طلب برقم بهذا الشكل.
 * 2) «آخر 10 خانات» وراثيًا لا تفحص البادئة: رقم دولي طويل تصادف أن آخر 10 خانات
 *    منه تطابق 1[0125]+8 سيُطبَّع كأنه مصري. البوابة الحقيقية للبادئة هي
 *    EGYPT_PHONE_REGEX في الـ Form Request قبل الوصول إلى هنا (الباب 2.4).
 */
final class PhoneNormalizer
{
    /** رمز الاتصال الدولي لمصر (نفس البادئة المقبولة في EGYPT_PHONE_REGEX). */
    private const EGYPT_CALLING_CODE = '20';

    /** طول الهوية المطبّعة = طول عمود customers.phone_normalized. */
    private const NORMALIZED_LENGTH = 10;

    /** شكل الجوال المصري بعد التطبيع: 1 + مشغّل [0125] + 8 أرقام. */
    private const NORMALIZED_PATTERN = '/^1[0125]\d{8}$/';

    /**
     * يعيد 10 خانات تبدأ بـ 1 (هوية العميل)، أو null إن لم يكن الرقم جوالًا مصريًا.
     */
    public static function normalize(?string $phone): ?string
    {
        // تجريد كل ما ليس رقمًا ثم آخر 10 خانات — نفس FindGuestOrderAction تمامًا.
        // ملاحظة: substr على نص أقصر من 10 يعيد النص كله (وعلى نص فارغ يعيد '')،
        // فالبوابة أدناه هي ما يمنع مرور رقم ناقص.
        $digits = (string) preg_replace('/\D/', '', (string) $phone);
        $candidate = substr($digits, -self::NORMALIZED_LENGTH);

        return preg_match(self::NORMALIZED_PATTERN, $candidate) === 1 ? $candidate : null;
    }

    /**
     * يعيد الرقم بصيغة E.164 (‎+20 + الهوية المطبّعة)، أو null لغير المصري.
     * يخزَّن في customers.phone_e164 للاستخدام مع أي مزوّد رسائل مستقبلًا.
     */
    public static function toE164(?string $phone): ?string
    {
        $normalized = self::normalize($phone);

        return $normalized === null ? null : '+'.self::EGYPT_CALLING_CODE.$normalized;
    }
}
