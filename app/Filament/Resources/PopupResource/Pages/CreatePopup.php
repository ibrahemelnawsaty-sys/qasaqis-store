<?php

declare(strict_types=1);

namespace App\Filament\Resources\PopupResource\Pages;

use App\Filament\Resources\PopupResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePopup extends CreateRecord
{
    protected static string $resource = PopupResource::class;
}
