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

        return (new MailMessage)
            ->subject(__('verification.email.subject'))
            ->greeting(__('verification.email.greeting'))
            ->line(__('verification.email.intro'))
            // الكود بارزًا في سطر مستقلّ — panel أوضح من دفنه في نصّ.
            ->line('## '.$this->code)
            ->line(__('verification.email.expiry', ['minutes' => $minutes]))
            ->line(__('verification.email.ignore'));
    }
}
