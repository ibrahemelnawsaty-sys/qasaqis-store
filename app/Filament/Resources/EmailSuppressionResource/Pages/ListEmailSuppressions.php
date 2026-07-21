<?php

declare(strict_types=1);

namespace App\Filament\Resources\EmailSuppressionResource\Pages;

use App\Filament\Resources\EmailSuppressionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

/**
 * قائمة من ألغوا الاشتراك. زر «حظر بريد يدويًا» (Modal) يضيف بريدًا للقائمة، وكل
 * صفّ فيه زر «إعادة التفعيل». الإنشاء عبر Modal فلا حاجة لصفحة منفصلة.
 */
class ListEmailSuppressions extends ListRecords
{
    protected static string $resource = EmailSuppressionResource::class;

    /**
     * @return array<int, \Filament\Actions\Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('حظر بريد يدويًا')
                ->icon('heroicon-o-no-symbol')
                ->modalHeading('حظر بريد من الحملات')
                ->modalDescription('لن يستقبل هذا البريد أي حملة تسويقية (تبقى رسائل المعاملات تصله).')
                ->modalSubmitActionLabel('حظر'),
        ];
    }
}
