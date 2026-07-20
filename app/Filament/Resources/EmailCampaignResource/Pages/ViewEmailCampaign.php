<?php

declare(strict_types=1);

namespace App\Filament\Resources\EmailCampaignResource\Pages;

use App\Filament\Resources\EmailCampaignResource;
use Filament\Resources\Pages\ViewRecord;

/**
 * عرض حملة: تفاصيلها وأرقامها (infolist) + جدول المستلمين (RelationManager) —
 * أثر التدقيق الكامل «لمن أُرسلت وبأيّ حالة».
 */
class ViewEmailCampaign extends ViewRecord
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
