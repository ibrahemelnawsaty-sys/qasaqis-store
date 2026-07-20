<?php

declare(strict_types=1);

namespace App\Support\Verification;

use App\Notifications\VerificationCodeNotification;
use Illuminate\Support\Facades\Notification;

/**
 * قناة البريد (M9) — الافتراضية حتى يُفعَّل OTP الجوال. تُرسِل on-demand لأن الهوية
 * قد تكون بريدًا مجرّدًا لا موديلًا (Notifiable).
 */
final class EmailVerificationChannel implements VerificationChannel
{
    public function send(string $identifier, string $code): void
    {
        Notification::route('mail', $identifier)
            ->notify(new VerificationCodeNotification($code));
    }

    public function name(): string
    {
        return 'email';
    }
}
