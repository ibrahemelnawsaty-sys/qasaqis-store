<?php

declare(strict_types=1);

namespace App\Filament\Resources\AdminActivityResource\Pages;

use App\Filament\Resources\AdminActivityResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;

class ViewAdminActivity extends ViewRecord
{
    protected static string $resource = AdminActivityResource::class;

    /**
     * سطر التدقيق لا يُعدَّل ولا يُحذف — لا إجراءات ترويسة إطلاقًا.
     *
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [];
    }
}
