<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Concerns\HasResourcePermissions;
use App\Filament\Resources\CouponResource\Pages;
use App\Models\Coupon;
use App\Providers\Filament\AdminPanelProvider;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Discount coupons (group: الطلبات والدفع).
 *
 * Permissions — docs/04 §3.3 models coupons as coupons.view + coupons.manage
 * (not full CRUD). The coarse view gate stays as the trait default (coupons.view);
 * create/edit/delete map to coupons.manage below.
 *
 * All enum values (type / applies_to) and the books/categories relations are
 * taken verbatim from the migration + Coupon model — nothing invented (1.1).
 */
class CouponResource extends Resource
{
    use HasResourcePermissions;

    protected static ?string $model = Coupon::class;

    protected static ?string $navigationIcon = 'heroicon-o-ticket';

    protected static ?string $navigationGroup = AdminPanelProvider::GROUP_ORDERS_PAYMENTS;

    protected static ?int $navigationSort = 30;

    protected static ?string $navigationLabel = 'الكوبونات';

    protected static ?string $modelLabel = 'كوبون';

    protected static ?string $pluralModelLabel = 'الكوبونات';

    public static function permissionPrefix(): string
    {
        return 'coupons';
    }

    // coupons.view is the default view gate (trait). Mutations require coupons.manage.
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
            Section::make('الكوبون')
                ->schema([
                    TextInput::make('code')
                        ->label('الكود')
                        ->required()
                        ->maxLength(50)
                        ->unique(ignoreRecord: true),
                    TextInput::make('description')
                        ->label('الوصف')
                        ->maxLength(200),
                    Select::make('type')
                        ->label('نوع الخصم')
                        ->options([
                            'percentage' => 'نسبة مئوية (%)',
                            'fixed' => 'مبلغ ثابت (ج.م)',
                        ])
                        ->required()
                        ->default('percentage')
                        ->live(),
                    TextInput::make('value')
                        ->label('القيمة')
                        ->numeric()
                        ->required()
                        ->minValue(0)
                        ->helperText(fn (callable $get): string => $get('type') === 'percentage'
                            ? 'نسبة من 0 إلى 100'
                            : 'قيمة بالجنيه'),
                    TextInput::make('min_order_total')
                        ->label('حد أدنى للطلب (ج.م)')
                        ->numeric()
                        ->minValue(0),
                    TextInput::make('max_discount')
                        ->label('حد أقصى للخصم (ج.م)')
                        ->numeric()
                        ->minValue(0)
                        // Cap only meaningful for percentage coupons.
                        ->visible(fn (callable $get): bool => $get('type') === 'percentage'),
                ])
                ->columns(2),

            Section::make('الصلاحية والاستخدام')
                ->schema([
                    DateTimePicker::make('starts_at')->label('يبدأ في'),
                    DateTimePicker::make('expires_at')
                        ->label('ينتهي في')
                        ->after('starts_at'),
                    TextInput::make('usage_limit')
                        ->label('حد الاستخدام الكلي')
                        ->numeric()
                        ->minValue(0),
                    TextInput::make('usage_limit_per_user')
                        ->label('حد الاستخدام لكل مستخدم')
                        ->numeric()
                        ->minValue(0),
                    // Denormalised counter — display only, never editable.
                    TextInput::make('used_count')
                        ->label('عدد مرات الاستخدام')
                        ->numeric()
                        ->disabled()
                        ->dehydrated(false),
                    Toggle::make('is_active')
                        ->label('مفعّل')
                        ->default(true)
                        ->inline(false),
                    Toggle::make('free_shipping')
                        ->label('شحن مجاني')
                        ->inline(false),
                ])
                ->columns(2),

            Section::make('نطاق التطبيق')
                ->schema([
                    Select::make('applies_to')
                        ->label('يطبَّق على')
                        ->options([
                            'all' => 'كل المنتجات',
                            'categories' => 'أقسام محددة',
                            'products' => 'كتب محددة',
                        ])
                        ->required()
                        ->default('all')
                        ->live(),
                    Select::make('categories')
                        ->label('الأقسام')
                        ->relationship('categories', 'name')
                        ->multiple()
                        ->preload()
                        ->searchable()
                        ->visible(fn (callable $get): bool => $get('applies_to') === 'categories'),
                    Select::make('books')
                        ->label('الكتب')
                        ->relationship('books', 'title')
                        ->multiple()
                        ->preload()
                        ->searchable()
                        ->visible(fn (callable $get): bool => $get('applies_to') === 'products'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('code')
                    ->label('الكود')
                    ->badge()
                    ->searchable(),
                TextColumn::make('type')
                    ->label('النوع')
                    ->formatStateUsing(fn (string $state): string => $state === 'percentage' ? 'نسبة %' : 'مبلغ ثابت'),
                TextColumn::make('value')
                    ->label('القيمة')
                    ->numeric(),
                TextColumn::make('used_count')
                    ->label('الاستخدام')
                    ->numeric(),
                TextColumn::make('usage_limit')
                    ->label('الحد الكلي')
                    ->placeholder('∞'),
                IconColumn::make('free_shipping')
                    ->label('شحن مجاني')
                    ->boolean(),
                IconColumn::make('is_active')
                    ->label('مفعّل')
                    ->boolean(),
                TextColumn::make('expires_at')
                    ->label('ينتهي')
                    ->dateTime('Y-m-d')
                    ->placeholder('—')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')->label('مفعّل'),
                Tables\Filters\SelectFilter::make('type')
                    ->label('النوع')
                    ->options([
                        'percentage' => 'نسبة مئوية',
                        'fixed' => 'مبلغ ثابت',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->label('تعديل'),
                Tables\Actions\DeleteAction::make()->label('حذف'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCoupons::route('/'),
            'create' => Pages\CreateCoupon::route('/create'),
            'edit' => Pages\EditCoupon::route('/{record}/edit'),
        ];
    }
}
