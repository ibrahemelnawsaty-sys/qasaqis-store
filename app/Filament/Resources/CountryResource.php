<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Concerns\HasResourcePermissions;
use App\Filament\Resources\CountryResource\Pages;
use App\Models\Country;
use App\Providers\Filament\AdminPanelProvider;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * الدول المدعومة للشحن (M5). كل دولة مرتبطة بمنطقة شحن تحدّد تكلفتها. صلاحية
 * «shipping» عبر HasResourcePermissions (مشتركة مع مناطق الشحن).
 */
class CountryResource extends Resource
{
    use HasResourcePermissions;

    protected static ?string $model = Country::class;

    protected static ?string $navigationIcon = 'heroicon-o-flag';

    protected static ?string $navigationGroup = AdminPanelProvider::GROUP_ORDERS_PAYMENTS;

    protected static ?int $navigationSort = 41;

    public static function permissionPrefix(): string
    {
        return 'shipping';
    }

    public static function getModelLabel(): string
    {
        return 'دولة';
    }

    public static function getPluralModelLabel(): string
    {
        return 'الدول';
    }

    public static function getNavigationLabel(): string
    {
        return 'دول الشحن';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make()
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('iso_code')
                        ->label('رمز الدولة (ISO)')
                        ->required()
                        ->maxLength(2)
                        ->minLength(2)
                        ->unique(ignoreRecord: true)
                        ->helperText('حرفان لاتينيان (EG، SA…).')
                        ->extraInputAttributes(['style' => 'text-transform:uppercase']),

                    Forms\Components\Select::make('shipping_zone_id')
                        ->label('منطقة الشحن')
                        ->relationship('shippingZone', 'name_ar')
                        ->required()
                        ->searchable()
                        ->preload(),

                    Forms\Components\TextInput::make('name_ar')
                        ->label('الاسم بالعربية')
                        ->required()
                        ->maxLength(120),

                    Forms\Components\TextInput::make('name_en')
                        ->label('الاسم بالإنجليزية')
                        ->required()
                        ->maxLength(120),

                    Forms\Components\TextInput::make('dial_code')
                        ->label('رمز الاتصال')
                        ->maxLength(8)
                        ->placeholder('+20'),

                    Forms\Components\TextInput::make('sort_order')
                        ->label('الترتيب')
                        ->integer()
                        ->default(0)
                        ->required(),

                    Forms\Components\Toggle::make('is_active')
                        ->label('مُفعّلة')
                        ->default(true),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('iso_code')->label('الرمز')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('name_ar')->label('الاسم')->searchable(),
                Tables\Columns\TextColumn::make('shippingZone.name_ar')->label('منطقة الشحن')->sortable(),
                Tables\Columns\TextColumn::make('dial_code')->label('رمز الاتصال')->toggleable(),
                Tables\Columns\TextColumn::make('sort_order')->label('الترتيب')->sortable(),
                Tables\Columns\IconColumn::make('is_active')->label('مُفعّلة')->boolean()->sortable(),
            ])
            ->defaultSort('sort_order')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')->label('مُفعّلة'),
                Tables\Filters\SelectFilter::make('shipping_zone_id')
                    ->label('منطقة الشحن')
                    ->relationship('shippingZone', 'name_ar'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageCountries::route('/'),
        ];
    }
}
