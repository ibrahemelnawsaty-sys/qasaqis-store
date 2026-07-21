<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\BackgroundPattern;
use App\Filament\Concerns\HasResourcePermissions;
use App\Filament\Resources\HomepageSectionResource\Pages;
use App\Filament\Resources\HomepageSectionResource\RelationManagers\BooksRelationManager;
use App\Models\HomepageSection;
use App\Providers\Filament\AdminPanelProvider;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * أقسام كتب الرئيسية (كاروسيلات). يضيف الأدمن/يحذف/يرتّب الأقسام بالسحب، وكل قسم
 * تلقائي بقاعدة (source_type) مع تعديل يدوي عبر «الكتب المثبّتة» (RelationManager).
 * البديل الرسمي لنوع products_grid الميت في HomepageBlock.
 *
 * الصلاحية: prefix «sections» (موجود في RolePermissionSeeder، غير مربوط سابقًا).
 * trait يربط view→sections.view؛ ونعيد تعريف الطفرة على sections.manage (لا توجد
 * صلاحيات sections.create/update/delete منفصلة) — نفس أسلوب موارد الرئيسية.
 */
class HomepageSectionResource extends Resource
{
    use HasResourcePermissions;

    protected static ?string $model = HomepageSection::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationGroup = AdminPanelProvider::GROUP_SITE_CMS;

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'أقسام كتب الرئيسية';

    protected static ?string $modelLabel = 'قسم';

    protected static ?string $pluralModelLabel = 'أقسام كتب الرئيسية';

    protected static ?string $recordTitleAttribute = 'title';

    public static function permissionPrefix(): string
    {
        return 'sections';
    }

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
            Forms\Components\Section::make('القسم')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('title')
                        ->label('العنوان')
                        ->required()
                        ->maxLength(150)
                        ->columnSpanFull(),

                    Forms\Components\TextInput::make('eyebrow')
                        ->label('التمهيد (فوق العنوان)')
                        ->maxLength(60)
                        ->helperText('نص صغير أعلى العنوان، مثل «⭐ الأكثر حبًا».'),

                    Forms\Components\TextInput::make('subtitle')
                        ->label('الوصف (تحت العنوان)')
                        ->maxLength(255),

                    Forms\Components\Select::make('source_type')
                        ->label('مصدر الكتب')
                        ->options(HomepageSection::SOURCE_TYPES)
                        ->required()
                        ->default('latest')
                        ->native(false)
                        ->live()
                        ->helperText('تلقائي بقاعدة، أو «يدوي» لتختار الكتب بنفسك. في كل الحالات تقدر تثبّت كتبًا من تبويب «الكتب المثبّتة».'),

                    Forms\Components\Select::make('category_id')
                        ->label('القسم')
                        ->relationship('category', 'name')
                        ->searchable()
                        ->preload()
                        ->visible(fn (Get $get): bool => $get('source_type') === 'category')
                        ->required(fn (Get $get): bool => $get('source_type') === 'category'),

                    Forms\Components\TextInput::make('item_limit')
                        ->label('عدد الكتب المعروضة')
                        ->integer()
                        ->default(8)
                        ->minValue(1)
                        ->maxValue(24)
                        ->required(),

                    Forms\Components\Toggle::make('is_active')
                        ->label('مُفعّل')
                        ->default(true),

                    Forms\Components\TextInput::make('sort_order')
                        ->label('ترتيب القسم في الصفحة')
                        ->integer()
                        ->default(0)
                        ->helperText('الأصغر يظهر أولًا (أو رتّب الأقسام بالسحب من الجدول).'),
                ]),

            Forms\Components\Section::make('زرّ وتصميم (اختياري)')
                ->columns(2)
                ->collapsed()
                ->schema([
                    Forms\Components\TextInput::make('cta_label')
                        ->label('نص زرّ «عرض الكل»')
                        ->maxLength(60)
                        ->placeholder('شوفي كل الكتب ←'),

                    Forms\Components\TextInput::make('cta_url')
                        ->label('رابط الزرّ')
                        ->maxLength(255)
                        ->placeholder('https://qasaqis.store/books')
                        ->helperText('اتركه فارغًا فلا يظهر زرّ.'),

                    Forms\Components\Select::make('background_pattern')
                        ->label('نقش الخلفية')
                        ->options(BackgroundPattern::options())
                        ->native(false)
                        ->placeholder('بلا نقش'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            // السحب يكتب في قاعدة البيانات، فيُقيَّد بصلاحية الإدارة (بند 4.4).
            ->reorderable('sort_order', static::userCan('manage'))
            ->defaultSort('sort_order')
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label('العنوان')
                    ->searchable()
                    ->wrap(),

                Tables\Columns\TextColumn::make('source_type')
                    ->label('المصدر')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => HomepageSection::SOURCE_TYPES[$state] ?? $state)
                    ->color(fn (string $state): string => $state === 'manual' ? 'warning' : 'gray'),

                Tables\Columns\TextColumn::make('category.name')
                    ->label('القسم')
                    ->placeholder('—')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('item_limit')
                    ->label('العدد')
                    ->alignCenter(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('مُفعّل')
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

    /**
     * @return array<int, class-string>
     */
    public static function getRelations(): array
    {
        return [
            BooksRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListHomepageSections::route('/'),
            'create' => Pages\CreateHomepageSection::route('/create'),
            'edit' => Pages\EditHomepageSection::route('/{record}/edit'),
        ];
    }
}
