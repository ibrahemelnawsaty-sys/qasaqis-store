<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Concerns\HasResourcePermissions;
use App\Filament\Resources\WhyItemResource\Pages;
use App\Models\WhyItem;
use App\Providers\Filament\AdminPanelProvider;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * بطاقات قسم «ليه الأمهات بيحبونا» في الرئيسية. محتوى في قاعدة البيانات يحرّره الأدمن
 * ويضيف إليه (بند 6.4). صلاحية «homepage» نفسها (view + edit) — لا صلاحيات جديدة.
 * لون خلفية البطاقة يتناوب تلقائيًا حسب الترتيب في القالب.
 */
class WhyItemResource extends Resource
{
    use HasResourcePermissions;

    protected static ?string $model = WhyItem::class;

    protected static ?string $navigationIcon = 'heroicon-o-heart';

    protected static ?string $navigationGroup = AdminPanelProvider::GROUP_SITE_CMS;

    protected static ?int $navigationSort = 7;

    protected static ?string $navigationLabel = 'مزايا «ليه بيحبونا»';

    protected static ?string $modelLabel = 'بطاقة';

    protected static ?string $pluralModelLabel = 'قسم «ليه الأمهات بيحبونا»';

    protected static ?string $recordTitleAttribute = 'title';

    public static function permissionPrefix(): string
    {
        return 'homepage';
    }

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

    /**
     * مفاتيح مكتبة الأيقونات في resources/views/components/why-icon.blade.php.
     * أي مفتاح هنا يجب أن يوجد هناك، وإلّا طُبع نصًّا في الواجهة.
     *
     * @return array<string, string>
     */
    public static function iconOptions(): array
    {
        return [
            'target-curated' => 'انتقاء تربوي — كتاب مرفوع فوق الرفّ',
            'harakat-letter' => 'لغة وتشكيل — حرف عربي بحركة',
            'pigment-sweep' => 'رسوم وألوان — قلم وأثره',
            'value-tag' => 'سعر وقيمة — بطاقة سعر',
            'open-book' => 'كتاب مفتوح',
            'heart-care' => 'رعاية وعناية — قلب بين كفّين',
            'shield-trust' => 'ثقة وأمان — درع',
            'delivery-truck' => 'توصيل — شاحنة',
            'star-merit' => 'تميّز وجدارة — وسام',
            'family-duo' => 'أمّ وطفل',
            'mind-sprout' => 'عقل ونموّ — برعم',
            'gift-wrap' => 'هدية وتغليف',
            'clock-fast' => 'سرعة — صاعقة',
            'chat-support' => 'دعم ومحادثة',
        ];
    }

    /**
     * خيارات قائمة الأيقونة لسجلّ بعينه.
     *
     * بطاقة أضافها الأدمن بإيموجي قبل هذه المكتبة قيمتها ليست ضمن المفاتيح الـ14.
     * لو تركنا القائمة ساكنة لظهر الحقل فارغًا، ومع required() يمتنع حفظ أي تعديل
     * — حتى تصحيح خطأ مطبعي في العنوان — حتى يستبدل الأدمن أيقونته، أي أن الواجهة
     * تُجبره على إتلاف بياناته وهو نقيض بند أمانة المحتوى. فنُبقي قيمته خيارًا قائمًا.
     *
     * $record فارغ في صفحة الإنشاء، فالبطاقات الجديدة تبقى مقصورة على المكتبة.
     *
     * @return array<string, string>
     */
    public static function iconOptionsFor(?Model $record): array
    {
        $options = self::iconOptions();
        $current = $record?->icon;

        if (filled($current) && ! array_key_exists($current, $options)) {
            return [$current => $current.' — أيقونتك الحالية'] + $options;
        }

        return $options;
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
                        ->default('heart-care')
                        ->native(false)
                        ->searchable()
                        ->options(fn (?Model $record): array => self::iconOptionsFor($record))
                        ->helperText('أيقونة مرسومة تتلوّن تلقائيًا بلون البطاقة وتعمل في الوضعين الفاتح والداكن.'),

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
                        ->helperText('الأصغر يظهر أولًا (أو رتّبها بالسحب من الجدول). لون الخلفية يتناوب تلقائيًا.'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            // السحب لإعادة الترتيب يكتب في قاعدة البيانات، فنقيّده بـ homepage.edit (بند 4.4).
            ->reorderable('sort_order', static::userCan('edit'))
            ->defaultSort('sort_order')
            ->columns([
                // نعرض الأيقونة نفسها لا مفتاحها — المفتاح وحده لا يقول شيئًا للأدمن.
                Tables\Columns\ViewColumn::make('icon')
                    ->label('الأيقونة')
                    ->view('filament.tables.columns.why-icon'),

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
            'index' => Pages\ListWhyItems::route('/'),
            'create' => Pages\CreateWhyItem::route('/create'),
            'edit' => Pages\EditWhyItem::route('/{record}/edit'),
        ];
    }
}
