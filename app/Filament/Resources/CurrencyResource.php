<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Concerns\HasResourcePermissions;
use App\Filament\Resources\CurrencyResource\Pages;
use App\Models\Currency;
use App\Providers\Filament\AdminPanelProvider;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * عملات العرض (تسعير متعدّد العملات). يعدّل المالكُ هنا **معدّل التحويل بالهامش**
 * لكلّ عملة، وتقريبَها، وتفعيلَها. العملة الأساس (EGP) هي مصدر الحقيقة الماليّ ولا
 * تُحذَف. صلاحية view+manage (كالناشرين/الأقسام) — لا أسماء صلاحيات مخترَعة (بند 1.1).
 */
class CurrencyResource extends Resource
{
    use HasResourcePermissions;

    protected static ?string $model = Currency::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = AdminPanelProvider::GROUP_SITE_CMS;

    protected static ?int $navigationSort = 9;

    protected static ?string $recordTitleAttribute = 'name_ar';

    public static function permissionPrefix(): string
    {
        return 'currencies';
    }

    // «currencies» has only view + manage — map all mutations to «manage».
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
        // العملة الأساس لا تُحذَف أبدًا (مرجع كلّ التحويلات والتحصيل).
        return static::userCan('manage') && ! $record->is_base;
    }

    public static function getModelLabel(): string
    {
        return 'عملة';
    }

    public static function getPluralModelLabel(): string
    {
        return 'العملات';
    }

    public static function getNavigationLabel(): string
    {
        return 'العملات';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make()
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('code')
                        ->label('الرمز (ISO)')
                        ->required()
                        ->maxLength(3)
                        // فريد: منع تكرار المفتاح الأساس (خطأ 500 عند 1062). على التعديل
                        // الحقل معطَّل وغير مُرطَّب فيتخطّى التحقّق. الترتيب غير حسّاس لحالة
                        // الأحرف افتراضيًّا فيلتقط sar/SAR، وdehydrate يخزّنه كبيرًا.
                        ->unique(ignoreRecord: true)
                        ->disabled(fn (?Currency $record): bool => $record !== null) // مفتاح؛ لا يتغيّر بعد الإنشاء
                        ->dehydrateStateUsing(fn (?string $state): string => strtoupper((string) $state))
                        ->helperText('رمز عملة عالميّ: SAR / AED / USD …'),

                    Forms\Components\TextInput::make('name_ar')
                        ->label('الاسم')
                        ->required()
                        ->maxLength(60),

                    Forms\Components\TextInput::make('symbol')
                        ->label('الرمز المعروض')
                        ->required()
                        ->maxLength(12)
                        ->helperText('يظهر بجانب السعر: ر.س / د.إ / $'),

                    Forms\Components\TextInput::make('egp_per_unit')
                        ->label('معدّل التحويل بالجنيه (بعد هامشك)')
                        ->required()
                        ->numeric()
                        ->minValue(0)
                        ->step(0.0001)
                        ->helperText('كم جنيهًا تعادل الوحدةُ الواحدة **بعد ربحك**؟ مثال: الريال الحقيقيّ ١٣ج لكن تكتب ١٠ فتربح الفرق. العملة الأساس (الجنيه) = ١.'),

                    Forms\Components\TextInput::make('reference_rate')
                        ->label('السعر الحقيقيّ (للمقارنة فقط)')
                        ->numeric()
                        ->minValue(0)
                        ->step(0.0001)
                        ->helperText('السعر السوقيّ الفعليّ — لرؤية هامشك فقط، لا يدخل الحساب.'),

                    Forms\Components\TextInput::make('rounding_step')
                        ->label('خطوة التقريب')
                        ->required()
                        ->numeric()
                        ->minValue(0)
                        ->step(0.0001)
                        ->default(1)
                        ->helperText('لتجميل السعر المحوّل: ٥ للريال/الدرهم، ٠٫٢٥ للدينار، ١ لبلا كسور.'),

                    Forms\Components\Select::make('rounding_mode')
                        ->label('اتّجاه التقريب')
                        ->required()
                        ->native(false)
                        ->options([
                            'up' => 'صعودًا (لصالح الهامش)',
                            'nearest' => 'لأقرب خطوة',
                        ])
                        ->default('up'),

                    Forms\Components\TextInput::make('decimals')
                        ->label('خانات العرض العشريّة')
                        ->required()
                        ->integer()
                        ->minValue(0)
                        ->maxValue(3)
                        ->default(2)
                        ->helperText('٢ غالبًا، ٣ للدينار الكويتيّ/البحرينيّ.'),

                    Forms\Components\TextInput::make('sort_order')
                        ->label('الترتيب')
                        ->integer()
                        ->default(0)
                        ->required(),

                    Forms\Components\Toggle::make('is_active')
                        ->label('مُفعّلة')
                        ->default(true)
                        ->helperText('تعطيلها يُرجِع زوّار بلدها إلى الجنيه.'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label('الرمز')
                    ->badge()
                    ->sortable(),

                Tables\Columns\TextColumn::make('name_ar')
                    ->label('الاسم')
                    ->searchable(),

                Tables\Columns\TextColumn::make('symbol')
                    ->label('العلامة'),

                // تحرير مباشر من الجدول: يضبط المالك معدّل كل عملة بسرعة (بند 4.4:
                // مُقيّد بإذن التعديل، والتحقّق النهائيّ خادميّ). الأساس (١) غير قابل للتعديل.
                Tables\Columns\TextInputColumn::make('egp_per_unit')
                    ->label('المعدّل (بالهامش)')
                    ->type('number')
                    ->rules(['numeric', 'min:0'])
                    ->disabled(fn (Currency $record): bool => $record->is_base || ! static::canEdit($record)),

                Tables\Columns\TextColumn::make('reference_rate')
                    ->label('السوق الحقيقيّ')
                    ->placeholder('—')
                    ->toggleable(),

                // مثالٌ حيّ: كم يظهر كتابُ ٣٠٠ج بهذه العملة (يرى المالك أثر معدّله فورًا).
                Tables\Columns\TextColumn::make('example')
                    ->label('مثال: ٣٠٠ ج.م ⟵')
                    ->state(fn (Currency $record): string => $record->format($record->convertFromEgp(300))),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('مُفعّلة')
                    ->boolean()
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_base')
                    ->label('أساس')
                    ->boolean(),
            ])
            ->defaultSort('sort_order')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('مُفعّلة'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn (Currency $record): bool => static::canDelete($record)),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCurrencies::route('/'),
            'create' => Pages\CreateCurrency::route('/create'),
            'edit' => Pages\EditCurrency::route('/{record}/edit'),
        ];
    }
}
