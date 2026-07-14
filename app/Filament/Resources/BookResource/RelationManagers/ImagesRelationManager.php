<?php

declare(strict_types=1);

namespace App\Filament\Resources\BookResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Gallery/cover images for a book (book_images table).
 *
 * Server-side authorization (constitution 4.4 / anti-pattern 13): visibility and
 * every mutating action are gated on the «products» permissions, since images are
 * part of managing the product. This is enforced here, not only by hiding buttons.
 */
class ImagesRelationManager extends RelationManager
{
    protected static string $relationship = 'images';

    protected static ?string $title = 'الصور';

    protected static ?string $recordTitleAttribute = 'alt';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return auth()->user()?->can('products.view') ?? false;
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('collection')
                ->label('المجموعة')
                ->options([
                    'cover' => 'غلاف',
                    'gallery' => 'معرض',
                ])
                ->default('gallery')
                ->required(),

            // Secure upload: images only, size-limited, random-named, stored on the
            // public disk under a dedicated directory (constitution 4.5).
            Forms\Components\FileUpload::make('path')
                ->label('الصورة')
                ->image()
                ->disk('public')
                ->directory('books/gallery')
                ->visibility('public')
                ->maxSize(2048)
                ->required()
                ->columnSpanFull(),

            Forms\Components\TextInput::make('alt')
                ->label('النص البديل (alt)')
                ->maxLength(255),

            Forms\Components\Toggle::make('is_cover')
                ->label('غلاف رئيسي'),

            Forms\Components\TextInput::make('sort_order')
                ->label('الترتيب')
                ->integer()
                ->default(0)
                ->required(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('alt')
            ->columns([
                Tables\Columns\ImageColumn::make('path')
                    ->label('الصورة')
                    ->disk('public')
                    ->square(),

                Tables\Columns\TextColumn::make('collection')
                    ->label('المجموعة')
                    ->badge(),

                Tables\Columns\TextColumn::make('alt')
                    ->label('النص البديل')
                    ->toggleable(),

                Tables\Columns\IconColumn::make('is_cover')
                    ->label('غلاف')
                    ->boolean(),

                Tables\Columns\TextColumn::make('sort_order')
                    ->label('الترتيب')
                    ->sortable(),
            ])
            ->defaultSort('sort_order')
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->visible(fn (): bool => auth()->user()?->can('products.update') ?? false),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->visible(fn (): bool => auth()->user()?->can('products.update') ?? false),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn (): bool => auth()->user()?->can('products.update') ?? false),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn (): bool => auth()->user()?->can('products.update') ?? false),
                ]),
            ]);
    }
}
