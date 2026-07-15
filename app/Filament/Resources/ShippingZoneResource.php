<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Concerns\HasResourcePermissions;
use App\Filament\Resources\ShippingZoneResource\Pages;
use App\Models\ShippingZone;
use App\Providers\Filament\AdminPanelProvider;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * مناطق الشحن الدولي (M5) — يضبط الأدمن تكلفتها بالجنيه. صلاحية «shipping»
 * (view/create/update/delete) عبر HasResourcePermissions — لا اختراع (بند 1.1).
 */
class ShippingZoneResource extends Resource
{
    use HasResourcePermissions;

    protected static ?string $model = ShippingZone::class;

    protected static ?string $navigationIcon = 'heroicon-o-globe-alt';

    protected static ?string $navigationGroup = AdminPanelProvider::GROUP_ORDERS_PAYMENTS;

    protected static ?int $navigationSort = 40;

    public static function permissionPrefix(): string
    {
        return 'shipping';
    }

    public static function getModelLabel(): string
    {
        return 'منطقة شحن';
    }

    public static function getPluralModelLabel(): string
    {
        return 'مناطق الشحن';
    }

    public static function getNavigationLabel(): string
    {
        return 'مناطق الشحن';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make()
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('code')
                        ->label('الرمز')
                        ->required()
                        ->maxLength(50)
                        ->unique(ignoreRecord: true)
                        ->helperText('رمز لاتيني ثابت (مثل GULF).'),

                    Forms\Components\TextInput::make('flat_cost')
                        ->label('تكلفة الشحن (ج.م)')
                        ->numeric()
                        ->minValue(0)
                        ->default(0)
                        ->required()
                        ->helperText('التحصيل بالجنيه المصري.'),

                    Forms\Components\TextInput::make('name_ar')
                        ->label('الاسم بالعربية')
                        ->required()
                        ->maxLength(120),

                    Forms\Components\TextInput::make('name_en')
                        ->label('الاسم بالإنجليزية')
                        ->required()
                        ->maxLength(120),

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
                Tables\Columns\TextColumn::make('code')->label('الرمز')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('name_ar')->label('الاسم')->searchable(),
                Tables\Columns\TextColumn::make('flat_cost')->label('الشحن (ج.م)')->numeric(2)->sortable(),
                Tables\Columns\TextColumn::make('countries_count')->label('عدد الدول')->counts('countries')->sortable(),
                Tables\Columns\TextColumn::make('sort_order')->label('الترتيب')->sortable(),
                Tables\Columns\IconColumn::make('is_active')->label('مُفعّلة')->boolean()->sortable(),
            ])
            ->defaultSort('sort_order')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')->label('مُفعّلة'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageShippingZones::route('/'),
        ];
    }
}
