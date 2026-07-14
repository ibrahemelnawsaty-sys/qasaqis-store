<?php

declare(strict_types=1);

namespace App\Filament\Resources\SurveyResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Questions of a survey. The whole surveys feature exposes a single atomic
 * permission — surveys.manage (docs/04 §3.3; no surveys.view/create/update/delete
 * exists in the seeder) — so viewing the tab and every mutation are gated on it.
 * Enforced server-side here as defense-in-depth (constitution 4.4 / anti-pattern
 * 13), consistent with the Images/Reviews/Items relation managers.
 */
class QuestionsRelationManager extends RelationManager
{
    protected static string $relationship = 'questions';

    protected static ?string $title = 'الأسئلة';

    protected static ?string $modelLabel = 'سؤال';

    /**
     * Question types — kept identical to the survey_questions enum
     * (verified against the migration): text, textarea, single_choice,
     * multi_choice, rating, yes_no.
     *
     * @var array<string, string>
     */
    protected const TYPES = [
        'text' => 'نص قصير',
        'textarea' => 'نص طويل',
        'single_choice' => 'اختيار واحد',
        'multi_choice' => 'اختيار متعدد',
        'rating' => 'تقييم',
        'yes_no' => 'نعم / لا',
    ];

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return (bool) auth()->user()?->can('surveys.manage');
    }

    protected function canCreate(): bool
    {
        return (bool) auth()->user()?->can('surveys.manage');
    }

    protected function canEdit(Model $record): bool
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

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('question_text')
                ->label('نص السؤال')
                ->required()
                ->maxLength(500)
                ->columnSpanFull(),
            Forms\Components\Select::make('type')
                ->label('النوع')
                ->options(self::TYPES)
                ->required()
                ->native(false)
                ->live(),
            Forms\Components\TagsInput::make('options')
                ->label('الخيارات')
                ->helperText('خيار في كل إدخال — للأسئلة ذات الاختيارات فقط.')
                // options JSON is only meaningful for choice questions.
                ->visible(fn (Forms\Get $get): bool => in_array(
                    $get('type'),
                    ['single_choice', 'multi_choice'],
                    true,
                )),
            Forms\Components\Toggle::make('is_required')
                ->label('إجباري')
                ->default(false),
            Forms\Components\TextInput::make('sort_order')
                ->label('الترتيب')
                ->numeric()
                ->default(0),
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('question_text')
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->columns([
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('#')
                    ->sortable(),
                Tables\Columns\TextColumn::make('question_text')
                    ->label('السؤال')
                    ->wrap()
                    ->searchable(),
                Tables\Columns\TextColumn::make('type')
                    ->label('النوع')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => self::TYPES[$state] ?? $state),
                Tables\Columns\IconColumn::make('is_required')
                    ->label('إجباري')
                    ->boolean(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
