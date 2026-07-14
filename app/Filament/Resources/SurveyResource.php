<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Concerns\HasResourcePermissions;
use App\Filament\Resources\SurveyResource\Pages;
use App\Filament\Resources\SurveyResource\RelationManagers;
use App\Models\Survey;
use App\Providers\Filament\AdminPanelProvider;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Manage surveys, their questions, and view responses/results.
 *
 * docs/04 §3.3 exposes a single atomic permission for the whole feature —
 * surveys.manage — so every CRUD action maps onto «manage» (there is no
 * surveys.view/create/update/delete). Per docs §3.7 this is granted to
 * super_admin and marketing.
 */
class SurveyResource extends Resource
{
    use HasResourcePermissions;

    protected static ?string $model = Survey::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationGroup = AdminPanelProvider::GROUP_ENGAGEMENT_SUPPORT;

    protected static ?int $navigationSort = 30;

    protected static ?string $navigationLabel = 'الاستبيانات';

    protected static ?string $modelLabel = 'استبيان';

    protected static ?string $pluralModelLabel = 'الاستبيانات';

    protected static ?string $recordTitleAttribute = 'title';

    public static function permissionPrefix(): string
    {
        return 'surveys';
    }

    // ----- Authorization: the whole feature is gated by surveys.manage -------

    public static function canViewAny(): bool
    {
        return static::userCan('manage');
    }

    public static function canView(Model $record): bool
    {
        return static::userCan('manage');
    }

    public static function canCreate(): bool
    {
        return static::userCan('manage');
    }

    public static function canEdit(Model $record): bool
    {
        return static::userCan('manage');
    }

    public static function canDelete(Model $record): bool
    {
        return static::userCan('manage');
    }

    public static function canDeleteAny(): bool
    {
        return static::userCan('manage');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('بيانات الاستبيان')
                ->schema([
                    Forms\Components\TextInput::make('title')
                        ->label('العنوان')
                        ->required()
                        ->maxLength(200)
                        ->live(onBlur: true)
                        ->afterStateUpdated(function (string $operation, ?string $state, Forms\Set $set): void {
                            // Auto-suggest a slug from the title on create only.
                            if ($operation === 'create' && filled($state)) {
                                $set('slug', Str::slug($state));
                            }
                        }),
                    Forms\Components\TextInput::make('slug')
                        ->label('المُعرّف (slug)')
                        ->required()
                        ->maxLength(220)
                        ->unique(ignoreRecord: true),
                    Forms\Components\Textarea::make('description')
                        ->label('الوصف')
                        ->rows(3)
                        ->maxLength(2000)
                        ->columnSpanFull(),
                    Forms\Components\Toggle::make('is_active')
                        ->label('مفعّل')
                        ->default(true),
                    Forms\Components\DateTimePicker::make('starts_at')
                        ->label('يبدأ في'),
                    Forms\Components\DateTimePicker::make('ends_at')
                        ->label('ينتهي في')
                        ->after('starts_at'),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label('العنوان')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('questions_count')
                    ->label('الأسئلة')
                    ->counts('questions'),
                Tables\Columns\TextColumn::make('responses_count')
                    ->label('الردود')
                    ->counts('responses'),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('مفعّل')
                    ->boolean()
                    ->sortable(),
                Tables\Columns\TextColumn::make('starts_at')
                    ->label('يبدأ')
                    ->dateTime()
                    ->placeholder('—')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('ends_at')
                    ->label('ينتهي')
                    ->dateTime()
                    ->placeholder('—')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('أُنشئ')
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('الحالة'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\QuestionsRelationManager::class,
            RelationManagers\ResponsesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSurveys::route('/'),
            'create' => Pages\CreateSurvey::route('/create'),
            'edit' => Pages\EditSurvey::route('/{record}/edit'),
        ];
    }
}
