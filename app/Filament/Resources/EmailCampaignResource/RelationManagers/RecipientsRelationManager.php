<?php

declare(strict_types=1);

namespace App\Filament\Resources\EmailCampaignResource\RelationManagers;

use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * مستلمو الحملة — للقراءة فقط. يعرض لكل عنوان: مصدره وحالة إرساله وسبب الفشل إن وُجد.
 * لا إنشاء/تحرير/حذف: السجلّ أثر تدقيق يكتبه خطّ الإرسال وحده.
 */
class RecipientsRelationManager extends RelationManager
{
    protected static string $relationship = 'recipients';

    protected static ?string $title = 'المستلمون';

    protected static ?string $recordTitleAttribute = 'email';

    public const SOURCE_LABELS = [
        'customer' => 'عميل مسجّل',
        'panel_user' => 'فريق العمل',
        'external' => 'خارجي',
    ];

    public const STATUS_LABELS = [
        'queued' => 'في الانتظار',
        'sent' => 'أُرسل',
        'failed' => 'فشل',
        'unsubscribed' => 'ألغى الاشتراك',
    ];

    public function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('email')
            ->defaultSort('id')
            ->columns([
                TextColumn::make('email')
                    ->label('البريد')
                    ->searchable()
                    ->copyable(),
                TextColumn::make('name')
                    ->label('الاسم')
                    ->placeholder('—'),
                TextColumn::make('source')
                    ->label('المصدر')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => self::SOURCE_LABELS[$state] ?? $state),
                TextColumn::make('status')
                    ->label('الحالة')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => self::STATUS_LABELS[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        'sent' => 'success',
                        'failed' => 'danger',
                        'unsubscribed' => 'gray',
                        default => 'warning',
                    }),
                TextColumn::make('sent_at')
                    ->label('وقت الإرسال')
                    ->dateTime('Y-m-d H:i')
                    ->placeholder('—'),
                TextColumn::make('error')
                    ->label('سبب الفشل')
                    ->placeholder('—')
                    ->limit(60)
                    ->wrap()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('الحالة')
                    ->options(self::STATUS_LABELS),
                SelectFilter::make('source')
                    ->label('المصدر')
                    ->options(self::SOURCE_LABELS),
            ])
            ->headerActions([])
            ->actions([])
            ->bulkActions([]);
    }
}
