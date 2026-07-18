<?php

declare(strict_types=1);

namespace App\Filament\Resources\TrustItemResource\Pages;

use App\Filament\Resources\TrustItemResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTrustItem extends EditRecord
{
    protected static string $resource = TrustItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
