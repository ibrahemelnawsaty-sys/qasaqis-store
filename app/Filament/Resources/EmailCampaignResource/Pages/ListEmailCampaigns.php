<?php

declare(strict_types=1);

namespace App\Filament\Resources\EmailCampaignResource\Pages;

use App\Filament\Resources\EmailCampaignResource;
use Filament\Resources\Pages\ListRecords;

/**
 * قائمة سجل الحملات — للقراءة فقط، لا زرّ إنشاء (الحملات تُنشأ من صفحة الإرسال).
 */
class ListEmailCampaigns extends ListRecords
{
    protected static string $resource = EmailCampaignResource::class;

    /**
     * @return array<int, \Filament\Actions\Action>
     */
    protected function getHeaderActions(): array
    {
        return [];
    }
}
