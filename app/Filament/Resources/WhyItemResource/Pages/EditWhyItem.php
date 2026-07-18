<?php

declare(strict_types=1);

namespace App\Filament\Resources\WhyItemResource\Pages;

use App\Filament\Resources\WhyItemResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditWhyItem extends EditRecord
{
    protected static string $resource = WhyItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
