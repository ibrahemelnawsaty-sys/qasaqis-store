<?php

declare(strict_types=1);

namespace App\Filament\Resources\SurveyResource\RelationManagers;

use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Read-only view of a survey's responses (results). Responses are submitted by
 * respondents on the storefront, so there is no create/edit here — only viewing
 * the submitted answers and deleting spam/test rows.
 *
 * The surveys feature exposes a single atomic permission — surveys.manage
 * (docs/04 §3.3; no surveys.view exists in the seeder) — so viewing this tab and
 * deleting a response are gated on it, server-side, as defense-in-depth
 * (constitution 4.4 / anti-pattern 13), consistent with the other relation managers.
 */
class ResponsesRelationManager extends RelationManager
{
    protected static string $relationship = 'responses';

    protected static ?string $title = 'الردود والنتائج';

    protected static ?string $modelLabel = 'رد';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return (bool) auth()->user()?->can('surveys.manage');
    }

    protected function canView(Model $record): bool
    {
        return (bool) auth()->user()?->can('surveys.manage');
    }

    protected function canDelete(Model $record): bool
    {
        return (bool) auth()->user()?->can('surveys.manage');
    }

    protected function canDeleteAny(): bool
    {
        return (bool) auth()->user()?->can('surveys.manage');
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\TextEntry::make('respondent_name')
                ->label('اسم المُجيب')
                ->placeholder('—'),
            Infolists\Components\TextEntry::make('respondent_phone')
                ->label('الهاتف')
                ->placeholder('—'),
            Infolists\Components\TextEntry::make('submitted_at')
                ->label('وقت الإرسال')
                ->dateTime()
                ->placeholder('—'),
            Infolists\Components\RepeatableEntry::make('answers')
                ->label('الإجابات')
                ->schema([
                    Infolists\Components\TextEntry::make('question.question_text')
                        ->label('السؤال'),
                    Infolists\Components\TextEntry::make('answer_text')
                        ->label('الإجابة')
                        ->placeholder('—'),
                    Infolists\Components\TextEntry::make('answer_options')
                        ->label('الخيارات المختارة')
                        ->badge()
                        ->placeholder('—'),
                    Infolists\Components\TextEntry::make('rating_value')
                        ->label('التقييم')
                        ->placeholder('—'),
                ])
                ->columns(2)
                ->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('respondent_name')
            ->modifyQueryUsing(fn ($query) => $query->withCount('answers')->with('answers.question'))
            ->columns([
                Tables\Columns\TextColumn::make('respondent_name')
                    ->label('اسم المُجيب')
                    ->placeholder('—')
                    ->searchable(),
                Tables\Columns\TextColumn::make('respondent_phone')
                    ->label('الهاتف')
                    ->placeholder('—')
                    ->searchable(),
                Tables\Columns\TextColumn::make('answers_count')
                    ->label('عدد الإجابات'),
                Tables\Columns\TextColumn::make('submitted_at')
                    ->label('وقت الإرسال')
                    ->dateTime()
                    ->placeholder('—')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('أُنشئ')
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            // Read-only: no header create action.
            ->headerActions([])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
