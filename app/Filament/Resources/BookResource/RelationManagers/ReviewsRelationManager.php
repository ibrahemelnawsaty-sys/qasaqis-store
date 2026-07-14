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
 * Reviews for a book (reviews table).
 *
 * Reviews are authored by customers, not admins — so there is no create action.
 * Authorization uses the «reviews» permissions (docs/04 §3.5): view to see them,
 * moderate to change status / delete. Enforced server-side (constitution 4.4).
 */
class ReviewsRelationManager extends RelationManager
{
    protected static string $relationship = 'reviews';

    protected static ?string $title = 'المراجعات';

    protected static ?string $recordTitleAttribute = 'author_name';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return auth()->user()?->can('reviews.view') ?? false;
    }

    public function form(Form $form): Form
    {
        // Moderation form: an admin can change the publication status.
        return $form->schema([
            Forms\Components\Select::make('status')
                ->label('الحالة')
                ->options([
                    'pending' => 'قيد المراجعة',
                    'published' => 'منشورة',
                    'hidden' => 'مخفية',
                    'spam' => 'سبام',
                ])
                ->required(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('author_name')
            ->columns([
                Tables\Columns\TextColumn::make('author_name')
                    ->label('صاحب المراجعة')
                    ->searchable(),

                Tables\Columns\TextColumn::make('rating')
                    ->label('التقييم')
                    ->placeholder('—')
                    ->sortable(),

                Tables\Columns\TextColumn::make('title')
                    ->label('العنوان')
                    ->toggleable()
                    ->wrap(),

                Tables\Columns\TextColumn::make('body')
                    ->label('النص')
                    ->limit(60)
                    ->wrap(),

                Tables\Columns\TextColumn::make('status')
                    ->label('الحالة')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending' => 'قيد المراجعة',
                        'published' => 'منشورة',
                        'hidden' => 'مخفية',
                        'spam' => 'سبام',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'published' => 'success',
                        'pending' => 'warning',
                        'hidden' => 'gray',
                        'spam' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\IconColumn::make('is_verified_purchase')
                    ->label('شراء موثّق')
                    ->boolean()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('التاريخ')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('الحالة')
                    ->options([
                        'pending' => 'قيد المراجعة',
                        'published' => 'منشورة',
                        'hidden' => 'مخفية',
                        'spam' => 'سبام',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('إدارة الحالة')
                    ->visible(fn (): bool => auth()->user()?->can('reviews.moderate') ?? false),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn (): bool => auth()->user()?->can('reviews.moderate') ?? false),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn (): bool => auth()->user()?->can('reviews.moderate') ?? false),
                ]),
            ]);
    }
}
