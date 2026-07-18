<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Concerns\HasResourcePermissions;
use App\Filament\Resources\FeedbackImageResource\Pages;
use App\Models\FeedbackImage;
use App\Providers\Filament\AdminPanelProvider;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * معرض شهادات العملاء (صور) في الرئيسية. يرفع الأدمن صورًا جديدة ويرتّبها/يخفيها.
 * صلاحية «homepage» نفسها (view + edit) — لا صلاحيات جديدة. الرفوعات على قرص public.
 */
class FeedbackImageResource extends Resource
{
    use HasResourcePermissions;

    protected static ?string $model = FeedbackImage::class;

    protected static ?string $navigationIcon = 'heroicon-o-photo';

    protected static ?string $navigationGroup = AdminPanelProvider::GROUP_SITE_CMS;

    protected static ?int $navigationSort = 8;

    protected static ?string $navigationLabel = 'شهادات العملاء (صور)';

    protected static ?string $modelLabel = 'صورة شهادة';

    protected static ?string $pluralModelLabel = 'معرض شهادات العملاء';

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

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make()
                ->columns(2)
                ->schema([
                    Forms\Components\FileUpload::make('image_path')
                        ->label('الصورة')
                        ->image()
                        ->disk('public')
                        ->directory('feedback')
                        ->visibility('public')
                        ->maxSize(4096)
                        ->imageEditor()
                        ->required()
                        ->helperText('ارفع صورة شهادة العميل (يُفضَّل تحسين حجمها قبل الرفع لتبقى الصفحة خفيفة).')
                        ->columnSpanFull(),

                    Forms\Components\TextInput::make('alt')
                        ->label('وصف الصورة (اختياري)')
                        ->maxLength(255)
                        ->helperText('يفيد الوصول ومحركات البحث؛ اتركه فارغًا لوصف افتراضي.'),

                    Forms\Components\Toggle::make('is_active')
                        ->label('مُفعّلة')
                        ->default(true),

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
            // السحب لإعادة الترتيب يكتب في قاعدة البيانات، فنقيّده بـ homepage.edit (بند 4.4).
            ->reorderable('sort_order', static::userCan('edit'))
            ->defaultSort('sort_order')
            ->columns([
                Tables\Columns\ImageColumn::make('image_path')
                    ->label('الصورة')
                    // المُلحِق url يحلّ الصور الساكنة والرفوعات معًا؛ Filament يعرض الرابط المطلق مباشرة.
                    ->getStateUsing(fn (FeedbackImage $record): string => $record->url)
                    ->height(56),

                Tables\Columns\TextColumn::make('alt')
                    ->label('الوصف')
                    ->placeholder('—')
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
            'index' => Pages\ListFeedbackImages::route('/'),
            'create' => Pages\CreateFeedbackImage::route('/create'),
            'edit' => Pages\EditFeedbackImage::route('/{record}/edit'),
        ];
    }
}
