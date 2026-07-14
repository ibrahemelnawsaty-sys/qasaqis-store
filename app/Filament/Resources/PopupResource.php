<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Concerns\HasResourcePermissions;
use App\Filament\Resources\PopupResource\Pages;
use App\Models\Popup;
use App\Providers\Filament\AdminPanelProvider;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Pop-ups: promos / surveys / newsletter / announcements with display conditions
 * and scheduling (doc 04 §4).
 *
 * Permission prefix "popups". Doc 04 §3.3 exposes popups.view + popups.manage, so
 * mutating hooks check popups.manage while viewing stays on popups.view.
 */
class PopupResource extends Resource
{
    use HasResourcePermissions;

    protected static ?string $model = Popup::class;

    protected static ?string $navigationIcon = 'heroicon-o-megaphone';

    protected static ?string $navigationGroup = AdminPanelProvider::GROUP_SITE_CMS;

    protected static ?int $navigationSort = 20;

    protected static ?string $navigationLabel = 'البوب أب';

    protected static ?string $modelLabel = 'بوب أب';

    protected static ?string $pluralModelLabel = 'البوب أب';

    public static function permissionPrefix(): string
    {
        return 'popups';
    }

    // ---- Permission overrides: mutations require popups.manage ----------

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
            Forms\Components\Section::make('المحتوى')
                ->schema([
                    Forms\Components\TextInput::make('title')
                        ->label('العنوان')
                        ->required()
                        ->maxLength(200),
                    Forms\Components\Select::make('type')
                        ->label('النوع')
                        ->required()
                        ->live()
                        ->options([
                            'promo' => 'دعاية',
                            'survey' => 'استبيان',
                            'newsletter' => 'نشرة بريدية',
                            'announcement' => 'إعلان',
                        ]),
                    Forms\Components\Select::make('survey_id')
                        ->label('الاستبيان المرتبط')
                        ->relationship('survey', 'title')
                        ->searchable()
                        ->preload()
                        ->visible(fn (Forms\Get $get): bool => $get('type') === 'survey')
                        ->helperText('يُختار عند نوع «استبيان» فقط.'),
                    Forms\Components\Textarea::make('content')
                        ->label('النص/المحتوى')
                        ->rows(4)
                        ->columnSpanFull(),
                    Forms\Components\FileUpload::make('image_path')
                        ->label('الصورة')
                        ->image()
                        ->disk('public')
                        ->directory('popups')
                        ->visibility('public')
                        ->maxSize(2048),
                    Forms\Components\TextInput::make('cta_label')
                        ->label('نص زر الإجراء')
                        ->maxLength(80),
                    Forms\Components\TextInput::make('cta_url')
                        ->label('رابط زر الإجراء')
                        ->url()
                        ->maxLength(300),
                ])
                ->columns(2),

            Forms\Components\Section::make('شروط الظهور والجدولة')
                ->schema([
                    Forms\Components\Select::make('display_trigger')
                        ->label('محفّز الظهور')
                        ->required()
                        ->default('on_load')
                        ->options([
                            'on_load' => 'عند تحميل الصفحة',
                            'on_exit' => 'عند نية المغادرة',
                            'on_scroll' => 'عند التمرير',
                            'after_delay' => 'بعد مهلة',
                        ]),
                    Forms\Components\TextInput::make('delay_seconds')
                        ->label('المهلة (ثوانٍ)')
                        ->integer()
                        ->minValue(0)
                        ->maxValue(65535),
                    Forms\Components\Select::make('display_frequency')
                        ->label('تكرار الظهور')
                        ->required()
                        ->default('once_per_session')
                        ->options([
                            'once' => 'مرة واحدة',
                            'once_per_session' => 'مرة كل جلسة',
                            'always' => 'دائمًا',
                        ]),
                    Forms\Components\TagsInput::make('target_pages')
                        ->label('صفحات الاستهداف')
                        ->helperText('مسارات الصفحات المستهدفة، اتركها فارغة لكل الصفحات.'),
                    Forms\Components\CheckboxList::make('target_devices')
                        ->label('الأجهزة المستهدفة')
                        ->options([
                            'mobile' => 'موبايل',
                            'tablet' => 'تابلت',
                            'desktop' => 'سطح المكتب',
                        ]),
                    Forms\Components\DateTimePicker::make('starts_at')
                        ->label('يبدأ في'),
                    Forms\Components\DateTimePicker::make('ends_at')
                        ->label('ينتهي في')
                        ->after('starts_at'),
                    Forms\Components\TextInput::make('priority')
                        ->label('الأولوية')
                        ->integer()
                        ->default(0),
                    Forms\Components\Toggle::make('is_active')
                        ->label('مُفعّل')
                        ->default(true),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label('العنوان')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('type')
                    ->label('النوع')
                    ->badge()
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('مُفعّل')
                    ->boolean()
                    ->sortable(),
                Tables\Columns\TextColumn::make('priority')
                    ->label('الأولوية')
                    ->sortable(),
                Tables\Columns\TextColumn::make('starts_at')
                    ->label('يبدأ')
                    ->dateTime()
                    ->toggleable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('ends_at')
                    ->label('ينتهي')
                    ->dateTime()
                    ->toggleable()
                    ->sortable(),
            ])
            ->defaultSort('priority', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label('النوع')
                    ->options([
                        'promo' => 'دعاية',
                        'survey' => 'استبيان',
                        'newsletter' => 'نشرة بريدية',
                        'announcement' => 'إعلان',
                    ]),
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
            'index' => Pages\ListPopups::route('/'),
            'create' => Pages\CreatePopup::route('/create'),
            'edit' => Pages\EditPopup::route('/{record}/edit'),
        ];
    }
}
