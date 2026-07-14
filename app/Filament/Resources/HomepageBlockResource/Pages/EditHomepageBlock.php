<?php

declare(strict_types=1);

namespace App\Filament\Resources\HomepageBlockResource\Pages;

use App\Filament\Resources\HomepageBlockResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditHomepageBlock extends EditRecord
{
    protected static string $resource = HomepageBlockResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
