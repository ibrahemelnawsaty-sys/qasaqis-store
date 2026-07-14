<?php

declare(strict_types=1);

namespace App\Filament\Resources\MenuResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Manages the links (menu_items) of a menu. Uses the Menu::allItems() relation so
 * both root links and nested children are editable in one place.
 *
 * Server-side authorization (constitution 4.4 / anti-pattern 13): viewing the tab
 * requires menus.view, while creating/editing/deleting links requires
 * menus.links.manage (doc 04 §3.2). These checks run in the authorization hooks
 * below, not merely by hiding UI.
 */
class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'allItems';

    protected static ?string $title = 'روابط القائمة';

    protected static ?string $modelLabel = 'رابط';

    protected static ?string $pluralModelLabel = 'الروابط';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return (bool) auth()->user()?->can('menus.view');
    }

    protected function canCreate(): bool
    {
        return (bool) auth()->user()?->can('menus.links.manage');
    }

    protected function canEdit(Model $record): bool
    {
        return (bool) auth()->user()?->can('menus.links.manage');
    }

    protected function canDelete(Model $record): bool
    {
        return (bool) auth()->user()?->can('menus.links.manage');
    }

    protected function canDeleteAny(): bool
    {
        return (bool) auth()->user()?->can('menus.links.manage');
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('label')
                ->label('النص')
                ->required()
                ->maxLength(100),
            Forms\Components\Select::make('parent_id')
                ->label('العنصر الأب')
                // Exclude the current item so a link can never be made its own
                // parent (self-referencing loop) — mirrors CategoryResource.parent_id.
                ->options(function (?Model $record): array {
                    $query = $this->getOwnerRecord()->allItems();

                    if ($record !== null) {
                        $query = $query->whereKeyNot($record->getKey());
                    }

                    return $query->pluck('label', 'id')->all();
                })
                ->searchable()
                ->helperText('اتركه فارغًا ليكون العنصر في المستوى الأعلى.'),
            Forms\Components\Select::make('link_type')
                ->label('نوع الرابط')
                ->required()
                ->default('url')
                ->options([
                    'url' => 'رابط مباشر',
                    'page' => 'صفحة',
                    'category' => 'قسم',
                    'product' => 'منتج',
                    'publisher' => 'دار نشر',
                ]),
            Forms\Components\TextInput::make('url')
                ->label('الرابط (URL)')
                ->url()
                ->maxLength(300)
                ->required(fn (Forms\Get $get): bool => $get('link_type') === 'url'),
            Forms\Components\Select::make('target')
                ->label('طريقة الفتح')
                ->required()
                ->default('_self')
                ->options([
                    '_self' => 'نفس النافذة',
                    '_blank' => 'نافذة جديدة',
                ]),
            Forms\Components\TextInput::make('icon')
                ->label('الأيقونة')
                ->maxLength(60),
            Forms\Components\TextInput::make('sort_order')
                ->label('الترتيب')
                ->integer()
                ->default(0),
            Forms\Components\Toggle::make('is_active')
                ->label('مُفعّل')
                ->default(true),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('label')
            ->columns([
                Tables\Columns\TextColumn::make('label')
                    ->label('النص')
                    ->searchable(),
                Tables\Columns\TextColumn::make('parent.label')
                    ->label('العنصر الأب')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('link_type')
                    ->label('النوع')
                    ->badge(),
                Tables\Columns\TextColumn::make('url')
                    ->label('الرابط')
                    ->limit(40)
                    ->toggleable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('مُفعّل')
                    ->boolean(),
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('الترتيب')
                    ->sortable(),
            ])
            ->defaultSort('sort_order')
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
