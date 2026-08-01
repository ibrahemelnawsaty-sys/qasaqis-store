<?php

declare(strict_types=1);

namespace App\Filament\Resources\CategoryResource\Pages;

use App\Actions\Category\SyncCategoryBookOrder;
use App\Filament\Resources\CategoryResource;
use App\Models\Category;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditCategory extends EditRecord
{
    protected static string $resource = CategoryResource::class;

    /**
     * عند فتح القسم نوائم جدول ترتيب كتبه مع عضويّته الفعليّة، فتعرض قائمة السحب كل
     * كتب القسم الحاليّة (الرئيسي + الإضافي) لا غير. عمليّة خفيفة idempotent.
     */
    public function mount(int|string $record): void
    {
        parent::mount($record);

        app(SyncCategoryBookOrder::class)->execute($this->record);
    }

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
