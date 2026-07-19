<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Concerns\HasResourcePermissions;
use App\Filament\Resources\PageResource\Pages;
use App\Models\Page;
use App\Providers\Filament\AdminPanelProvider;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Dynamic CMS pages (constitution 0.8 / doc 04 §4): the admin adds/edits static
 * pages (about, shipping, FAQ, return policy) with per-page SEO.
 *
 * Permission prefix "pages" — the atomic actions pages.view/create/update/delete
 * (doc 04 §3.2) map one-to-one onto the HasResourcePermissions defaults, so no
 * override is needed here.
 */
class PageResource extends Resource
{
    use HasResourcePermissions;

    protected static ?string $model = Page::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = AdminPanelProvider::GROUP_SITE_CMS;

    protected static ?int $navigationSort = 10;

    protected static ?string $navigationLabel = 'الصفحات';

    protected static ?string $modelLabel = 'صفحة';

    protected static ?string $pluralModelLabel = 'الصفحات';

    public static function permissionPrefix(): string
    {
        return 'pages';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('محتوى الصفحة')
                ->schema([
                    Forms\Components\TextInput::make('title')
                        ->label('العنوان')
                        ->required()
                        ->maxLength(200)
                        ->live(onBlur: true)
                        ->afterStateUpdated(function (string $operation, $state, Forms\Set $set): void {
                            // Auto-fill the slug from the title only while creating,
                            // so editing an existing page never breaks its public URL.
                            if ($operation === 'create') {
                                $set('slug', \Illuminate\Support\Str::slug((string) $state));
                            }
                        }),
                    Forms\Components\TextInput::make('slug')
                        ->label('المُعرّف (slug)')
                        ->required()
                        ->maxLength(220)
                        ->unique(ignoreRecord: true)
                        ->helperText('يُستخدم في رابط الصفحة، بأحرف إنجليزية وشرطات.'),
                    Forms\Components\RichEditor::make('content')
                        ->label('المحتوى')
                        ->columnSpanFull(),
                    Forms\Components\TextInput::make('template')
                        ->label('القالب')
                        ->maxLength(60)
                        ->helperText('اسم قالب Blade اختياري لعرض الصفحة.'),

                    Forms\Components\Select::make('background_pattern')
                        ->label('نقش الخلفية')
                        ->options(\App\Enums\BackgroundPattern::options())
                        ->native(false)
                        ->placeholder('الافتراضي — نقش الصفحات الثابتة')
                        ->helperText('اتركيه فارغًا لتتبع الصفحة النقش المضبوط في «نقوش الخلفية».'),
                ])
                ->columns(2),

            Forms\Components\Section::make('النشر')
                ->schema([
                    Forms\Components\Toggle::make('is_published')
                        ->label('منشورة'),
                    Forms\Components\DateTimePicker::make('published_at')
                        ->label('تاريخ النشر'),
                    Forms\Components\TextInput::make('sort_order')
                        ->label('الترتيب')
                        ->integer()
                        ->default(0),
                ])
                ->columns(3),

            Forms\Components\Section::make('تحسين محركات البحث (SEO)')
                ->description('بيانات SEO خاصة بهذه الصفحة.')
                ->collapsible()
                ->schema([
                    // relationship() on a Group is the documented Filament v3 way to
                    // persist a hasOne/morphOne relation (Page morphOne SeoMeta).
                    Forms\Components\Group::make()
                        ->relationship('seo')
                        ->schema([
                            Forms\Components\TextInput::make('meta_title')
                                ->label('عنوان الميتا')
                                ->maxLength(255),
                            Forms\Components\Textarea::make('meta_description')
                                ->label('وصف الميتا')
                                ->maxLength(320)
                                ->rows(2),
                            Forms\Components\TextInput::make('meta_keywords')
                                ->label('الكلمات المفتاحية')
                                ->maxLength(255),
                            Forms\Components\TextInput::make('canonical_url')
                                ->label('الرابط الأساسي (canonical)')
                                ->url()
                                ->maxLength(300),
                            Forms\Components\Select::make('robots')
                                ->label('توجيه محركات البحث (robots)')
                                ->options([
                                    'index,follow' => 'index,follow',
                                    'noindex,follow' => 'noindex,follow',
                                    'index,nofollow' => 'index,nofollow',
                                    'noindex,nofollow' => 'noindex,nofollow',
                                ])
                                ->default('index,follow'),
                            Forms\Components\TextInput::make('og_title')
                                ->label('عنوان OpenGraph')
                                ->maxLength(255),
                            Forms\Components\Textarea::make('og_description')
                                ->label('وصف OpenGraph')
                                ->maxLength(320)
                                ->rows(2),
                            Forms\Components\FileUpload::make('og_image_path')
                                ->label('صورة OpenGraph')
                                ->image()
                                ->disk('public')
                                ->directory('seo/og')
                                ->visibility('public')
                                ->maxSize(2048),
                        ])
                        ->columns(2),
                ]),
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
                Tables\Columns\TextColumn::make('slug')
                    ->label('المُعرّف')
                    ->searchable(),
                Tables\Columns\IconColumn::make('is_published')
                    ->label('منشورة')
                    ->boolean()
                    ->sortable(),
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('الترتيب')
                    ->sortable(),
                Tables\Columns\TextColumn::make('published_at')
                    ->label('تاريخ النشر')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->defaultSort('sort_order')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_published')
                    ->label('حالة النشر'),
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
                Tables\Actions\RestoreAction::make(),
                Tables\Actions\ForceDeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        // Eager-load the morphOne SEO row to avoid N+1 on the list/edit screens
        // (constitution 2.5), and keep soft-deleted pages reachable via the filter.
        return parent::getEloquentQuery()
            ->with('seo')
            ->withoutGlobalScopes([
                \Illuminate\Database\Eloquent\SoftDeletingScope::class,
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPages::route('/'),
            'create' => Pages\CreatePage::route('/create'),
            'edit' => Pages\EditPage::route('/{record}/edit'),
        ];
    }
}
