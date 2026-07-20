<?php

declare(strict_types=1);

namespace App\Filament\Resources\AdminActivityResource\Pages;

use App\Filament\Resources\AdminActivityResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListAdminActivities extends ListRecords
{
    protected static string $resource = AdminActivityResource::class;

    /**
     * لا إجراء إنشاء: السجل يكتبه RecordsAdminActivity وحده، ولا توجد صلاحية
     * system.logs.create أصلًا (بند 1.1).
     *
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [];
    }
}
