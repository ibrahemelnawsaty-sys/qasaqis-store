<?php

declare(strict_types=1);

namespace App\Filament\Resources\CategoryResource\Pages;

use App\Filament\Resources\CategoryResource;
use App\Models\Category;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditCategory extends EditRecord
{
    protected static string $resource = CategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->before(function (Category $record, Actions\DeleteAction $action): void {
                    if ($record->books()->exists()) {
                        Notification::make()
                            ->title('لا يمكن حذف قسم يحتوي على كتب')
                            ->body('انقل الكتب إلى قسم آخر أو احذفها أولًا.')
                            ->danger()
                            ->send();

                        $action->halt();
                    }
                }),
        ];
    }
}
