<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Concerns\HasResourcePermissions;
use App\Filament\Resources\RedirectResource\Pages;
use App\Models\Redirect;
use App\Providers\Filament\AdminPanelProvider;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * مدير تحويلات 301/302 (نظير Yoast Redirect Manager). التحويلات «auto» تُنشأ
 * تلقائيًا عند تغيير رابط كتاب/مقال/صفحة (trait TracksSlugRedirects) فلا يصير
 * الرابط القديم 404؛ ويضيف الأدمن تحويلات «manual» يدويًا. التطبيق عند 404 فقط.
 *
 * الصلاحية: prefix «seo» (موجود). trait يربط العرض بـ seo.view؛ ونعيد تعريف
 * الطفرة على seo.edit (لا توجد seo.create/update/delete منفصلة) — دون اختراع صلاحيات.
 */
class RedirectResource extends Resource
{
    use HasResourcePermissions;

    protected static ?string $model = Redirect::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static ?string $navigationGroup = AdminPanelProvider::GROUP_SITE_CMS;

    protected static ?int $navigationSort = 8;

    protected static ?string $navigationLabel = 'تحويلات الروابط (301)';

    protected static ?string $modelLabel = 'تحويل';

    protected static ?string $pluralModelLabel = 'تحويلات الروابط';

    protected static ?string $recordTitleAttribute = 'from_path';

    public static function permissionPrefix(): string
    {
        return 'seo';
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
            Forms\Components\Section::make('التحويل')
                ->columns(1)
                ->schema([
                    Forms\Components\TextInput::make('from_path')
                        ->label('من المسار (القديم)')
                        ->required()
                        ->maxLength(255)
                        ->placeholder('/books/old-slug')
                        ->helperText('المسار القديم بلا النطاق، يبدأ بشرطة مائلة. مثال: /books/الرابط-القديم')
                        ->dehydrateStateUsing(fn (string $state): string => Redirect::normalizePath($state))
                        ->unique(ignoreRecord: true)
                        ->rule('different:to_path'),

                    Forms\Components\TextInput::make('to_path')
                        ->label('إلى (الوجهة الجديدة)')
                        ->required()
                        ->maxLength(255)
                        ->placeholder('/books/new-slug')
                        ->helperText('مسار داخلي (/books/الجديد) أو رابط كامل (https://...).')
                        ->dehydrateStateUsing(function (string $state): string {
                            $state = trim($state);

                            // رابط خارجي كامل يُترك كما هو؛ المسار الداخلي يُطبَّع.
                            return \Illuminate\Support\Str::startsWith($state, ['http://', 'https://'])
                                ? $state
                                : Redirect::normalizePath($state);
                        }),

                    Forms\Components\Select::make('status_code')
                        ->label('نوع التحويل')
                        ->options([
                            301 => '301 — دائم (يحفظ ترتيب SEO) — مُوصى به',
                            302 => '302 — مؤقّت',
                        ])
                        ->default(301)
                        ->native(false)
                        ->required(),

                    Forms\Components\Toggle::make('is_active')
                        ->label('مُفعّل')
                        ->default(true),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('from_path')
                    ->label('من')
                    ->searchable()
                    ->copyable()
                    ->wrap(),

                Tables\Columns\TextColumn::make('to_path')
                    ->label('إلى')
                    ->searchable()
                    ->wrap(),

                Tables\Columns\TextColumn::make('status_code')
                    ->label('النوع')
                    ->badge()
                    ->color(fn (int $state): string => $state === 301 ? 'success' : 'warning'),

                Tables\Columns\TextColumn::make('source')
                    ->label('المصدر')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === 'auto' ? 'تلقائي' : 'يدوي')
                    ->color(fn (string $state): string => $state === 'auto' ? 'gray' : 'info'),

                Tables\Columns\TextColumn::make('hits')
                    ->label('عدد الزيارات')
                    ->alignCenter()
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('مُفعّل')
                    ->boolean()
                    ->sortable(),

                Tables\Columns\TextColumn::make('last_hit_at')
                    ->label('آخر زيارة')
                    ->dateTime('Y-m-d H:i')
                    ->placeholder('—')
                    ->toggleable()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('source')
                    ->label('المصدر')
                    ->options(['auto' => 'تلقائي', 'manual' => 'يدوي']),

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
            'index' => Pages\ListRedirects::route('/'),
            'create' => Pages\CreateRedirect::route('/create'),
            'edit' => Pages\EditRedirect::route('/{record}/edit'),
        ];
    }
}
