<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Concerns\HasResourcePermissions;
use App\Filament\Resources\MenuResource\Pages;
use App\Filament\Resources\MenuResource\RelationManagers\ItemsRelationManager;
use App\Models\Menu;
use App\Providers\Filament\AdminPanelProvider;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Navigation menus (header / footer / mobile) and their links (doc 04 §4).
 * Links are managed through the ItemsRelationManager.
 *
 * Permission prefix "menus". Doc 04 §3.2 exposes menus.view + menus.manage
 * (+ menus.links.manage handled inside the relation manager), so the mutating
 * hooks check menus.manage while viewing stays on menus.view.
 */
class MenuResource extends Resource
{
    use HasResourcePermissions;

    protected static ?string $model = Menu::class;

    protected static ?string $navigationIcon = 'heroicon-o-bars-3';

    protected static ?string $navigationGroup = AdminPanelProvider::GROUP_SITE_CMS;

    protected static ?int $navigationSort = 15;

    protected static ?string $navigationLabel = 'القوائم';

    protected static ?string $modelLabel = 'قائمة';

    protected static ?string $pluralModelLabel = 'القوائم';

    public static function permissionPrefix(): string
    {
        return 'menus';
    }

    // ---- Permission overrides: mutations require menus.manage -----------

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
            Forms\Components\Section::make()
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('الاسم')
                        ->required()
                        ->maxLength(80),
                    Forms\Components\Select::make('location')
                        ->label('الموقع')
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->options([
                            'header' => 'الترويسة (Header)',
                            'footer' => 'التذييل (Footer)',
                            'mobile' => 'الموبايل (Mobile)',
                        ])
                        ->helperText('كل موقع يقبل قائمة واحدة فقط.'),
                    Forms\Components\Toggle::make('is_active')
                        ->label('مُفعّلة')
                        ->default(true),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('الاسم')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('location')
                    ->label('الموقع')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('allItems_count')
                    ->counts('allItems')
                    ->label('عدد الروابط'),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('مُفعّلة')
                    ->boolean()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('التفعيل'),
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

    public static function getRelations(): array
    {
        return [
            ItemsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMenus::route('/'),
            'create' => Pages\CreateMenu::route('/create'),
            'edit' => Pages\EditMenu::route('/{record}/edit'),
        ];
    }
}
