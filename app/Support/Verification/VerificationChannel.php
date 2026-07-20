<?php

declare(strict_types=1);

namespace App\Support\Verification;

/**
 * قناة إرسال كود التحقق (M9). التجريد هنا هو ما يجعل الانتقال من البريد إلى OTP
 * الجوال تغييرَ إعداد + صنف قناة جديد، لا إعادة كتابة منطق التحقق كله.
 */
interface VerificationChannel
{
    /**
     * يرسل الكود إلى الهوية عبر هذه القناة.
     *
     * يرمي عند فشل الإرسال؛ المستدعي (الخدمة) يقرّر هل يُسقِط العملية أم يمضي
     * بلطف (التسجيل لا يُحبَط بفشل بريد — القرار المعماري).
     */
    public function send(string $identifier, string $code): void;

    /** اسم القناة كما في config/verification.php ('email', 'sms', …). */
    public function name(): string;
}
