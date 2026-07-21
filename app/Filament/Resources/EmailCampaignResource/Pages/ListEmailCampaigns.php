<?php

declare(strict_types=1);

namespace App\Filament\Resources\EmailCampaignResource\Pages;

use App\Filament\Resources\EmailCampaignResource;
use App\Filament\Support\FlushQueueAction;
use Filament\Resources\Pages\ListRecords;

/**
 * قائمة سجل الحملات — للقراءة فقط، لا زرّ إنشاء (الحملات تُنشأ من صفحة الإرسال).
 * يتوفّر زر «دفع الإرسال الآن» لمعالجة الطابور فورًا دون انتظار زيارة للموقع.
 */
class ListEmailCampaigns extends ListRecords
{
    protected static string $resource = EmailCampaignResource::class;

    /**
     * @return array<int, \Filament\Actions\Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            FlushQueueAction::make(),
        ];
    }
}
