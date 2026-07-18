<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Concerns\HasResourcePermissions;
use App\Filament\Resources\TrustItemResource\Pages;
use App\Models\TrustItem;
use App\Providers\Filament\AdminPanelProvider;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * شريط المزايا/الثقة في الرئيسية (شحن، تغليف، جودة، دعم…). محتوى في قاعدة البيانات
 * يحرّره الأدمن ويضيف إليه (بند 6.4: لا نصوص واجهة ثابتة في القوالب).
 *
 * صلاحية «homepage» نفسها (view + edit) كبلوكات الرئيسية — لا تحتاج صلاحيات جديدة.
 */
class TrustItemResource extends Resource
{
    use HasResourcePermissions;

    protected static ?string $model = TrustItem::class;

    protected static ?string $navigationIcon = 'heroicon-o-sparkles';

    protected static ?string $navigationGroup = AdminPanelProvider::GROUP_SITE_CMS;

    protected static ?int $navigationSort = 6;

    protected static ?string $navigationLabel = 'شريط المزايا';

    protected static ?string $modelLabel = 'ميزة';

    protected static ?string $pluralModelLabel = 'شريط المزايا';

    protected static ?string $recordTitleAttribute = 'title';

    public static function permissionPrefix(): string
    {
        return 'homepage';
    }

    // التعديلات تتطلّب homepage.edit؛ العرض على homepage.view (نفس بلوكات الرئيسية).
    public static function canCreate(): bool
    {
        return static::userCan('edit');
    }

    public static function canEdit(Model $record): bool
    {
        return static::userCan('edit');
    }

    public static function canDelete(Model $record): bool
    {
        return static::userCan('edit');
    }

    public static function canDeleteAny(): bool
    {
        return static::userCan('edit');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make()
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('icon')
                        ->label('الأيقونة')
                        ->required()
                        ->default('badge-check')
                        ->native(false)
                        ->searchable()
                        ->options([
                            'globe' => 'كرة أرضية — شحن/دولي',
                            'truck' => 'شاحنة — توصيل',
                            'gift' => 'هدية — تغليف',
                            'badge-check' => 'شارة صح — جودة/أصلي',
                            'shield-check' => 'درع — ضمان/أمان',
                            'sparkles' => 'بريق — تميّز/فخامة',
                            'star' => 'نجمة — تقييم',
                            'heart' => 'قلب — عناية',
                            'chat' => 'محادثة — دعم',
                            'phone' => 'هاتف — تواصل',
                            'clock' => 'ساعة — سرعة',
                            'credit-card' => 'بطاقة — دفع',
                            'cart' => 'سلة — تسوّق',
                            'home' => 'منزل',
                            'grid' => 'شبكة — أقسام',
                        ])
                        ->helperText('اختر أيقونة تناسب الميزة (تظهر بنفس شكل الموقع).'),

                    Forms\Components\Toggle::make('is_active')
                        ->label('مُفعّلة')
                        ->default(true),

                    Forms\Components\TextInput::make('title')
                        ->label('العنوان')
                        ->required()
                        ->maxLength(150)
                        ->columnSpanFull(),

                    Forms\Components\TextInput::make('description')
                        ->label('الوصف')
                        ->maxLength(255)
                        ->columnSpanFull(),

                    Forms\Components\TextInput::make('sort_order')
                        ->label('الترتيب')
                        ->integer()
                        ->default(0)
                        ->helperText('الأصغر يظهر أولًا (أو رتّبها بالسحب من الجدول).'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            // السحب لإعادة الترتيب يكتب في قاعدة البيانات، فنقيّده بصلاحية homepage.edit
            // كبقية التعديلات (بند 4.4: فرض الصلاحية خادميًا).
            ->reorderable('sort_order', static::userCan('edit'))
            ->defaultSort('sort_order')
            ->columns([
                Tables\Columns\TextColumn::make('icon')
                    ->label('الأيقونة')
                    ->badge(),

                Tables\Columns\TextColumn::make('title')
                    ->label('العنوان')
                    ->searchable()
                    ->wrap(),

                Tables\Columns\TextColumn::make('description')
                    ->label('الوصف')
                    ->toggleable()
                    ->wrap(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('مُفعّلة')
                    ->boolean()
                    ->sortable(),

                Tables\Columns\TextColumn::make('sort_order')
                    ->label('الترتيب')
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

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTrustItems::route('/'),
            'create' => Pages\CreateTrustItem::route('/create'),
            'edit' => Pages\EditTrustItem::route('/{record}/edit'),
        ];
    }
}
