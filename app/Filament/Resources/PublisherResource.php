<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Concerns\HasResourcePermissions;
use App\Filament\Resources\PublisherResource\Pages;
use App\Models\Publisher;
use App\Providers\Filament\AdminPanelProvider;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

/**
 * Publishers resource (real houses from the covers + a default one).
 *
 * Like categories, «publishers» exposes only view + manage (docs/04 §3.1), so the
 * create/edit/delete hooks are overridden to check «publishers.manage» — no
 * invented permission names (constitution 1.1).
 *
 * name_normalized is filled by the model's setNameAttribute mutator, so it is NOT
 * a form field. Publisher uses SoftDeletes so removing one never breaks book FKs
 * (books.publisher_id is nullOnDelete).
 */
class PublisherResource extends Resource
{
    use HasResourcePermissions;

    protected static ?string $model = Publisher::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-library';

    protected static ?string $navigationGroup = AdminPanelProvider::GROUP_BOOKS_CONTENT;

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'name';

    public static function permissionPrefix(): string
    {
        return 'publishers';
    }

    // «publishers» has only view + manage — map all mutations to «manage».
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

    public static function getModelLabel(): string
    {
        return 'دار نشر';
    }

    public static function getPluralModelLabel(): string
    {
        return 'دور النشر';
    }

    public static function getNavigationLabel(): string
    {
        return 'دور النشر';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make()
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('الاسم')
                        ->required()
                        ->maxLength(190),

                    Forms\Components\TextInput::make('slug')
                        ->label('المُعرّف (Slug)')
                        ->required()
                        ->maxLength(190)
                        ->unique(ignoreRecord: true)
                        ->helperText('حروف لاتينية/أرقام؛ يُكتب يدويًا.'),

                    Forms\Components\TextInput::make('website')
                        ->label('الموقع الإلكتروني')
                        ->url()
                        ->maxLength(255),

                    Forms\Components\TextInput::make('sort_order')
                        ->label('الترتيب')
                        ->integer()
                        ->default(0)
                        ->required(),

                    Forms\Components\TextInput::make('cost_discount_percent')
                        ->label('نسبة خصم الشراء (٪)')
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(100)
                        ->suffix('٪')
                        ->helperText('خصمك عن سعر البيع عند الشراء من هذه الدار. تُقدَّر تكلفة كتابٍ بلا سعر شراء مُدخَل = السعر × (١ − النسبة). اتركها فارغة لاستعمال الافتراضي العام (٢٥٪).'),

                    Forms\Components\Toggle::make('is_active')
                        ->label('مُفعّلة')
                        ->default(true),

                    Forms\Components\FileUpload::make('logo_path')
                        ->label('الشعار')
                        ->image()
                        ->disk('public')
                        ->directory('publishers')
                        ->visibility('public')
                        ->maxSize(2048)
                        ->columnSpanFull(),

                    Forms\Components\Textarea::make('description')
                        ->label('الوصف')
                        ->rows(3)
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('logo_path')
                    ->label('الشعار')
                    ->disk('public')
                    ->square(),

                Tables\Columns\TextColumn::make('name')
                    ->label('الاسم')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('slug')
                    ->label('المُعرّف')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('books_count')
                    ->label('عدد الكتب')
                    ->counts('books')
                    ->sortable(),

                Tables\Columns\TextColumn::make('sort_order')
                    ->label('الترتيب')
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('مُفعّلة')
                    ->boolean()
                    ->sortable(),
            ])
            ->defaultSort('sort_order')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('مُفعّلة'),

                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
                Tables\Actions\RestoreAction::make(),
                Tables\Actions\ForceDeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPublishers::route('/'),
            'create' => Pages\CreatePublisher::route('/create'),
            'edit' => Pages\EditPublisher::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}
