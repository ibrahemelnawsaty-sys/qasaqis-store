<?php

declare(strict_types=1);

namespace App\Filament\Resources\InquiryResource\Pages;

use App\Filament\Resources\InquiryResource;
use App\Mail\InquiryReplied;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;

class EditInquiry extends EditRecord
{
    protected static string $resource = InquiryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    /**
     * عند وجود ردّ: اختم المُجيب ووقت الرد، وأغلِق الاستفسار تلقائيًا («تمّ الرد»)
     * ما لم يكن مغلقًا يدويًا (بند 4.4: القيم الحساسة تُضبط خادميًا).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (filled($data['admin_reply'] ?? null)) {
            $data['assigned_to'] = $this->record->assigned_to ?? Auth::id();
            $data['replied_at'] = $this->record->replied_at ?? now();

            if (($data['status'] ?? '') !== 'closed') {
                $data['status'] = 'answered';
            }
        }

        return $data;
    }

    /**
     * بعد الحفظ: أرسل ردّ الفريق إلى بريد العميل (إن وُجد) عند إضافة/تغيير الرد فقط
     * (wasChanged يمنع تكرار الإرسال عند أي تعديل لاحق). الإرسال تزامني وأفضل-جهد:
     * فشل البريد لا يُفشل الحفظ (الرد يبقى محفوظًا والاستفسار مغلقًا).
     */
    protected function afterSave(): void
    {
        $inquiry = $this->record;

        if (! $inquiry->wasChanged('admin_reply') || blank($inquiry->admin_reply)) {
            return;
        }

        if (blank($inquiry->email)) {
            Notification::make()
                ->info()
                ->title('لا يوجد بريد للعميل')
                ->body('حُفظ الرد وأُغلق الاستفسار؛ تواصل مع العميل عبر واتساب على الرقم المسجّل.')
                ->send();

            return;
        }

        try {
            Mail::to($inquiry->email)->send(new InquiryReplied($inquiry));

            Notification::make()
                ->success()
                ->title('تم إرسال الرد إلى بريد العميل ✅')
                ->send();
        } catch (\Throwable $e) {
            report($e);

            Notification::make()
                ->warning()
                ->title('تعذّر إرسال البريد')
                ->body('حُفظ الرد وأُغلق الاستفسار، لكن تعذّر إرسال الإيميل — تواصل مع العميل عبر واتساب.')
                ->send();
        }
    }
}
