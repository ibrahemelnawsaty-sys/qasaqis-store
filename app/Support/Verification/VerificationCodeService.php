<?php

declare(strict_types=1);

namespace App\Support\Verification;

use App\Models\VerificationCode;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * إصدار كود التحقق والتحقق منه (M9). مصدر الحقيقة الوحيد لدورة حياة الكود.
 *
 * أمان: الكود يُخزَّن مُجزّأً (Hash::make)؛ ويُبطَل بعد عدد محاولات خاطئة (منع
 * التخمين)؛ وإصدار كود جديد يُبطِل السابق (فلا يبقى كودان حيّان لنفس الهوية).
 *
 * القناة تُحقن (Contract) فيبقى الانتقال إلى OTP جوال تغييرَ ربط في الحاوية،
 * لا تعديلًا هنا.
 */
class VerificationCodeService
{
    public function __construct(private readonly VerificationChannel $channel)
    {
    }

    /**
     * يُصدر كودًا جديدًا، يُبطِل أي كود سابق حيّ لنفس (الهوية+الغرض)، ثم يرسله.
     *
     * @return bool  هل نجح الإرسال؟ false لا يُسقِط العملية — الكود مُصدَر ويمكن
     *               إعادة الإرسال؛ التسجيل لا يُحبَط بفشل بريد (القرار المعماري).
     */
    public function issueAndSend(string $identifier, string $purpose): bool
    {
        $code = $this->generateCode();

        // إبطال الأكواد السابقة الحيّة: كود واحد فعّال لكل هوية+غرض.
        VerificationCode::query()
            ->where('identifier', $identifier)
            ->where('purpose', $purpose)
            ->whereNull('consumed_at')
            ->update(['consumed_at' => now()]);

        VerificationCode::create([
            'identifier' => $identifier,
            'channel' => $this->channel->name(),
            'purpose' => $purpose,
            'code_hash' => Hash::make($code),
            'attempts' => 0,
            'expires_at' => now()->addMinutes((int) config('verification.expiry_minutes', 15)),
        ]);

        // فشل الإرسال (SMTP مقطوع مثلًا) يُبتلع ويُسجَّل: الحساب أُنشئ والكود جاهز،
        // والعميلة تُعيد الإرسال. رمي الخطأ هنا كان سيُسقِط تسجيلًا ناجحًا.
        try {
            $this->channel->send($identifier, $code);

            return true;
        } catch (\Throwable $e) {
            report($e);

            return false;
        }
    }

    /**
     * يتحقق من كود مُدخَل. يستهلك الكود عند النجاح، ويزيد عدّاد المحاولات عند
     * الفشل ويُبطِله عند تجاوز الحدّ.
     */
    public function verify(string $identifier, string $purpose, string $code): bool
    {
        $record = VerificationCode::query()
            ->where('identifier', $identifier)
            ->where('purpose', $purpose)
            // تقييد بالقناة الحالية: حين تُضاف SMS بنفس (identifier+purpose) لا
            // يتقاطع كود بريدٍ مع كود جوالٍ لنفس الهوية.
            ->where('channel', $this->channel->name())
            ->active()
            ->latest('id')
            ->first();

        if ($record === null) {
            return false;
        }

        $maxAttempts = (int) config('verification.max_attempts', 5);

        // تجاوز الحدّ: أبطِل الكود نهائيًا (لا مزيد من التخمين على هذا الكود).
        if ($record->attempts >= $maxAttempts) {
            $record->update(['consumed_at' => now()]);

            return false;
        }

        if (! Hash::check($code, (string) $record->code_hash)) {
            $record->increment('attempts');

            return false;
        }

        $record->update(['consumed_at' => now()]);

        return true;
    }

    /**
     * كود عدديّ بطول ثابت من config. random_int مصدر عشوائية مناسب للتشفير.
     */
    private function generateCode(): string
    {
        $length = (int) config('verification.code_length', 6);

        if ($length < 4 || $length > 9) {
            throw new RuntimeException('verification.code_length خارج المدى المعقول (4..9).');
        }

        $min = 10 ** ($length - 1);
        $max = (10 ** $length) - 1;

        return (string) random_int($min, $max);
    }
}
