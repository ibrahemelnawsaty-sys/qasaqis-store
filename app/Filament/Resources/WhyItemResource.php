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

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make()
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('icon')
                        ->label('الرمز (إيموجي)')
                        ->required()
                        ->maxLength(16)
                        ->default('💛')
                        ->helperText('أدخل إيموجي واحدًا يعبّر عن الميزة (مثل 🎯 أو 🎨).'),

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
                Tables\Columns\TextColumn::make('icon')
                    ->label('الرمز'),

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
