<?php

declare(strict_types=1);

namespace App\Filament\Resources\BookResource\RelationManagers;

use App\Models\Currency;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * تسعيرٌ يدويّ لكلّ عملة (book_country_prices): تجاوزٌ صريح للتحويل التلقائيّ لهذا
 * الكتاب بعملةٍ معيّنة. غياب صفٍّ لعملةٍ = تحويلٌ آليّ من سعر الجنيه. صفٌّ واحد لكلّ
 * عملة (قيد فريد). صلاحية «products» كباقي إدارة الكتاب (بند 4.4، خادميًّا لا بإخفاء الزرّ).
 */
class CountryPricesRelationManager extends RelationManager
{
    protected static string $relationship = 'countryPrices';

    protected static ?string $title = 'أسعار خاصّة لكل دولة';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return auth()->user()?->can('products.view') ?? false;
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('currency_code')
                ->label('العملة')
                ->required()
                ->native(false)
                // العملات المتاحة: غير الأساس والمفعّلة، مع استبعاد ما سُعِّر لهذا الكتاب
                // سلفًا (قيد فريد لكلّ كتاب/عملة). تُقفَل عند التعديل (لا تُغيَّر عملة صفٍّ قائم).
                ->options(function (RelationManager $livewire, ?Model $record): array {
                    $used = $livewire->getOwnerRecord()->countryPrices()
                        ->when($record !== null, fn ($q) => $q->whereKeyNot($record->getKey()))
                        ->pluck('currency_code')
                        ->all();

                    return Currency::query()
                        ->where('is_base', false)
                        ->where('is_active', true)
                        ->orderBy('sort_order')
                        ->get()
                        ->reject(fn (Currency $c): bool => in_array($c->code, $used, true))
                        ->mapWithKeys(fn (Currency $c): array => [$c->code => $c->name_ar.' ('.$c->symbol.')'])
                        ->all();
                })
                ->disabledOn('edit'),

            Forms\Components\TextInput::make('price')
                ->label('السعر (بعملة الدولة)')
                ->required()
                ->numeric()
                ->minValue(0)
                ->step(0.001)
                ->helperText('السعر النهائيّ بهذه العملة — يتجاوز التحويل التلقائيّ.'),

            Forms\Components\TextInput::make('old_price')
                ->label('السعر قبل الخصم (اختياريّ)')
                ->numeric()
                ->minValue(0)
                ->step(0.001)
                ->helperText('اتركه فارغًا إن لا يوجد خصم.'),

            Forms\Components\Toggle::make('is_active')
                ->label('مُفعّل')
                ->default(true)
                ->helperText('تعطيله يُرجِع هذا الكتاب للتحويل التلقائيّ بهذه العملة.'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('currency.name_ar')
                    ->label('العملة')
                    ->badge()
                    ->formatStateUsing(fn (?string $state, Model $record): string => ($state ?? $record->currency_code)
                        .' ('.($record->currency?->symbol ?? $record->currency_code).')'),

                Tables\Columns\TextColumn::make('price')
                    ->label('السعر')
                    ->formatStateUsing(fn ($state, Model $record): string => $record->currency?->formatExact((float) $state) ?? (string) $state),

                Tables\Columns\TextColumn::make('old_price')
                    ->label('قبل الخصم')
                    ->placeholder('—')
                    ->formatStateUsing(fn ($state, Model $record): ?string => $state === null ? null : ($record->currency?->formatExact((float) $state) ?? (string) $state)),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('مُفعّل')
                    ->boolean(),
            ])
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
