<?php

declare(strict_types=1);

namespace App\Support\Analytics;

/**
 * تجزئة بيانات المستخدم (SHA-256) لإرسالها إلى Meta CAPI دون كشف القيمة الخام
 * (M6). التطبيع قبل التجزئة مطلوب من Meta: البريد lowercase/trim، الهاتف أرقام فقط.
 */
final class UserDataHasher
{
    public static function hashEmail(?string $email): ?string
    {
        if (blank($email)) {
            return null;
        }

        return hash('sha256', strtolower(trim($email)));
    }

    public static function hashPhone(?string $phone): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $phone);

        return blank($digits) ? null : hash('sha256', $digits);
    }
}
