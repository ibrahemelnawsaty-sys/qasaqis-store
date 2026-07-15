<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Filament\Resources\OrderResource;
use App\Models\Order;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

/**
 * تنبيه الأدمن (M4) بطلب جديد أو إثبات دفع مرفوع. قناتان: البريد + قاعدة البيانات
 * (جرس Filament). ShouldQueue. لمستقبِل احتياطي on-demand (بريد فقط، لا جرس) عند
 * غياب مستخدم بصلاحية orders.view. كل النصوص من الترجمة.
 */
class AdminOrderNotification extends Notification implements ShouldQueue
{
    use Queueable;
    // يُسلسِل الطلب كمُعرِّف (لا كل الحقول) فلا تبقى PII العميل نصًّا في jobs.
    use SerializesModels;

    public const NEW_ORDER = 'new_order';

    public const PROOF_SUBMITTED = 'proof_submitted';

    public function __construct(
        public Order $order,
        public string $kind,
    ) {
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        // المستقبِل الاحتياطي (on-demand) لا يملك جدول إشعارات → بريد فقط.
        return $notifiable instanceof AnonymousNotifiable ? ['mail'] : ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->title())
            ->view('emails.admin-alert', [
                'title' => $this->title(),
                'body' => $this->body(),
                'url' => $this->adminUrl(),
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->title($this->title())
            ->body($this->body())
            ->icon($this->kind === self::NEW_ORDER ? 'heroicon-o-shopping-bag' : 'heroicon-o-clipboard-document-check')
            ->getDatabaseMessage();
    }

    private function title(): string
    {
        return __('mail.admin.'.$this->kind.'.title');
    }

    private function body(): string
    {
        return __('mail.admin.'.$this->kind.'.body', [
            'number' => $this->order->order_number,
            'total' => number_format((float) $this->order->grand_total, 0),
            'currency' => __('common.currency'),
            'method' => __('payment.methods.'.$this->order->payment_method),
        ]);
    }

    private function adminUrl(): string
    {
        return OrderResource::getUrl('view', ['record' => $this->order->getKey()], panel: 'admin');
    }
}
