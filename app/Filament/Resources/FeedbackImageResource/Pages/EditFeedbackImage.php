<?php

declare(strict_types=1);

namespace App\Filament\Resources\FeedbackImageResource\Pages;

use App\Filament\Resources\FeedbackImageResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditFeedbackImage extends EditRecord
{
    protected static string $resource = FeedbackImageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
