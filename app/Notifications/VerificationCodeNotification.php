<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * رسالة كود تأكيد البريد (M9). **ليست ShouldQueue** عمدًا: العميلة تنتظر الكود
 * الآن، والإرسال المتزامن يضمن وصوله في الطلب نفسه لا في طابور قد لا يعمل على
 * الاستضافة المشتركة. كل النصوص من ملفات الترجمة (بند 6.4).
 */
class VerificationCodeNotification extends Notification
{
    public function __construct(public string $code)
    {
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $minutes = (int) config('verification.expiry_minutes', 15);

        // ‎->view()‎ لا ‎->line()‎: الأخيرة تُصيَّر بقالب Laravel الافتراضي العام،
        // بينما القالب المؤسسي (ترويسة/تذييل) يُستعمل عبر عرض يرث emails.layout —
        // نفس نمط CustomerOrderNotification/AdminOrderNotification.
        return (new MailMessage)
            ->subject(__('verification.email.subject'))
            ->view('emails.verification-code', [
                'code' => $this->code,
                'minutes' => $minutes,
            ]);
    }
}
