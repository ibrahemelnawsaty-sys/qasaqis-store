<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Concerns\HasResourcePermissions;
use App\Filament\Resources\HomepageBlockResource\Pages;
use App\Models\HomepageBlock;
use App\Providers\Filament\AdminPanelProvider;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Editable homepage blocks (constitution 0.8 / doc 04 §4): hero text, links,
 * banners and other homepage areas — content lives in the DB, never hardcoded
 * in templates (constitution 6.4).
 *
 * Permission prefix "homepage". Doc 04 §3.2 exposes homepage.view + homepage.edit
 * (not the generic create/update/delete verbs), so the mutating hooks below are
 * overridden to check homepage.edit while viewing stays on homepage.view.
 */
class HomepageBlockResource extends Resource
{
    use HasResourcePermissions;

    protected static ?string $model = HomepageBlock::class;

    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';

    protected static ?string $navigationGroup = AdminPanelProvider::GROUP_SITE_CMS;

    protected static ?int $navigationSort = 5;

    protected static ?string $navigationLabel = 'بلوكات الرئيسية';

    protected static ?string $modelLabel = 'بلوك';

    protected static ?string $pluralModelLabel = 'بلوكات الرئيسية';

    public static function permissionPrefix(): string
    {
        return 'homepage';
    }

    // ---- Permission overrides: mutations require homepage.edit -----------

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
                ->schema([
                    Forms\Components\TextInput::make('key')
                        ->label('المُعرّف (key)')
                        ->required()
                        ->maxLength(80)
                        ->unique(ignoreRecord: true)
                        ->helperText('مُعرّف فريد للبلوك، مثل hero_slider.'),
                    Forms\Components\TextInput::make('area')
                        ->label('المنطقة')
                        ->required()
                        ->maxLength(60)
                        ->helperText('مكان ظهور البلوك، مثل homepage أو footer.'),
                    Forms\Components\Select::make('type')
                        ->label('النوع')
                        ->required()
                        // 'products_grid' مُستبعَد عمدًا: HomeController لا يعالجه فيُسقَط بصمت،
                        // فلا نتيح للأدمن إنشاء بلوك غير فعّال. قيمة الـ enum تبقى في الهجرة
                        // (لا نغيّر السكيمة). الأنواع الفعّالة فقط هي المعروضة هنا.
                        ->options([
                            'slider' => 'سلايدر',
                            'banner' => 'بانر',
                            'text' => 'نص',
                            'html' => 'HTML',
                            'image' => 'صورة',
                            'cta' => 'دعوة لإجراء (CTA)',
                        ]),
                    Forms\Components\TextInput::make('title')
                        ->label('العنوان')
                        ->maxLength(200),
                    Forms\Components\KeyValue::make('content')
                        ->label('المحتوى (مفتاح/قيمة)')
                        ->keyLabel('المفتاح')
                        ->valueLabel('القيمة')
                        ->helperText('حمولة مرنة حسب نوع البلوك: نصوص، روابط، رابط صورة… (مثل body، url، image_url).')
                        ->columnSpanFull(),
                    Forms\Components\Toggle::make('is_active')
                        ->label('مُفعّل')
                        ->default(true),
                    Forms\Components\TextInput::make('sort_order')
                        ->label('الترتيب')
                        ->integer()
                        ->default(0),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('key')
                    ->label('المُعرّف')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('area')
                    ->label('المنطقة')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('type')
                    ->label('النوع')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('title')
                    ->label('العنوان')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('مُفعّل')
                    ->boolean()
                    ->sortable(),
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('الترتيب')
                    ->sortable(),
            ])
            ->defaultSort('sort_order')
            ->filters([
                Tables\Filters\SelectFilter::make('area')
                    ->label('المنطقة')
                    ->options(fn (): array => HomepageBlock::query()
                        ->distinct()
                        ->orderBy('area')
                        ->pluck('area', 'area')
                        ->all()),
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
            'index' => Pages\ListHomepageBlocks::route('/'),
            'create' => Pages\CreateHomepageBlock::route('/create'),
            'edit' => Pages\EditHomepageBlock::route('/{record}/edit'),
        ];
    }
}
