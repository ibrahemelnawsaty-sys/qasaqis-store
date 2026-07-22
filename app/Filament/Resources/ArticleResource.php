<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Concerns\HasResourcePermissions;
use App\Filament\Resources\ArticleResource\Pages;
use App\Filament\Support\SeoPlaceholder;
use App\Models\Article;
use App\Providers\Filament\AdminPanelProvider;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * مقالات المدونة (constitution 0.8 — محتوى قابل للتحرير من الأدمن يُدار عبر CMS).
 *
 * الصلاحيات: تُستخدم بادئة "pages" الذرّية (docs/04 §3.2 — pages.view/create/
 * update/delete موجودة فعلًا في RolePermissionSeeder) وتنطبق واحدًا لواحد على
 * افتراضات HasResourcePermissions، فلا حاجة لإعادة تعريف. لا اختراع اسم صلاحية
 * جديد (بند 1.1). الفرض خادمي عبر الـ Gate/spatie (بند 4.4 / ممنوع 13).
 *
 * كل الأعمدة والعلاقة books() مأخوذة حرفيًا من الهجرة + موديل Article — لا افتراض.
 */
class ArticleResource extends Resource
{
    use HasResourcePermissions;

    protected static ?string $model = Article::class;

    protected static ?string $navigationIcon = 'heroicon-o-newspaper';

    protected static ?string $navigationGroup = AdminPanelProvider::GROUP_SITE_CMS;

    protected static ?int $navigationSort = 20;

    protected static ?string $navigationLabel = 'المدونة';

    protected static ?string $modelLabel = 'مقال';

    protected static ?string $pluralModelLabel = 'المقالات';

    public static function permissionPrefix(): string
    {
        return 'pages';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('محتوى المقال')
                ->schema([
                    Forms\Components\TextInput::make('title')
                        ->label('العنوان')
                        ->required()
                        ->maxLength(200)
                        ->live(onBlur: true)
                        ->afterStateUpdated(function (string $operation, $state, Forms\Set $set): void {
                            // توليد الـ slug من العنوان أثناء الإنشاء فقط، حتى لا
                            // ينكسر رابط مقال منشور عند تعديله لاحقًا.
                            if ($operation === 'create') {
                                $set('slug', Str::slug((string) $state));
                            }
                        }),
                    Forms\Components\TextInput::make('slug')
                        ->label('المُعرّف (slug)')
                        ->required()
                        ->maxLength(220)
                        ->unique(ignoreRecord: true)
                        ->live(onBlur: true)
                        ->helperText('يُستخدم في رابط المقال /blog/... بأحرف إنجليزية وشرطات.'),
                    Forms\Components\Textarea::make('excerpt')
                        ->label('المقتطف')
                        ->rows(3)
                        ->maxLength(500)
                        ->columnSpanFull()
                        ->live(onBlur: true)
                        ->helperText('ملخّص قصير يظهر في قائمة المدونة ونتائج البحث.'),
                    Forms\Components\RichEditor::make('content')
                        ->label('المحتوى')
                        ->required()
                        ->live(onBlur: true)
                        ->columnSpanFull(),
                ])
                ->columns(2),

            // تحليل SEO المباشر (نظير Yoast): كلمة مفتاحية + نقاط تتحدّث مع الكتابة.
            \App\Filament\Support\ContentSeoAnalysis::make(
                titleField: 'title',
                descriptionField: 'excerpt',
                bodyFields: ['content', 'excerpt'],
                slugField: 'slug',
            ),

            Forms\Components\Section::make('بيانات المقال')
                ->schema([
                    Forms\Components\Select::make('category')
                        ->label('القسم')
                        ->required()
                        ->options([
                            'نصائح تربوية' => 'نصائح تربوية',
                            'تربية بالقصص' => 'تربية بالقصص',
                            'مراجعات كتب' => 'مراجعات كتب',
                            'أنشطة وتعليم' => 'أنشطة وتعليم',
                        ])
                        ->searchable(),
                    Forms\Components\TextInput::make('author_name')
                        ->label('اسم الكاتب')
                        ->maxLength(150),
                    Forms\Components\TextInput::make('reading_minutes')
                        ->label('دقائق القراءة')
                        ->numeric()
                        ->minValue(0)
                        ->default(0),
                    // رفع آمن للغلاف: صور فقط، قرص عام، حجم محدود، دليل مخصص
                    // (بند 4.5). NULL => عنصر بديل محايد في الواجهة، لا غلاف مخترع.
                    Forms\Components\FileUpload::make('cover_image')
                        ->label('صورة الغلاف')
                        ->image()
                        ->disk('public')
                        ->directory('articles/covers')
                        ->visibility('public')
                        ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                        ->maxSize(2048)
                        ->imageEditor()
                        ->columnSpanFull(),
                ])
                ->columns(3),

            Forms\Components\Section::make('النشر والكتب المرتبطة')
                ->schema([
                    Forms\Components\Toggle::make('is_published')
                        ->label('منشور')
                        ->default(true),
                    Forms\Components\Toggle::make('is_featured')
                        ->label('مميّز'),
                    Forms\Components\DateTimePicker::make('published_at')
                        ->label('تاريخ النشر'),
                    Forms\Components\TextInput::make('sort_order')
                        ->label('الترتيب')
                        ->integer()
                        ->default(0),
                    // العلاقة متعددة إلى الكتب ذات الصلة عبر جدول article_book.
                    Forms\Components\Select::make('books')
                        ->label('الكتب المرتبطة')
                        ->relationship('books', 'title')
                        ->multiple()
                        ->preload()
                        ->searchable()
                        ->helperText('كتب ذات صلة تُعرض مع المقال (روابط داخلية + ترويج).')
                        ->columnSpanFull(),
                ])
                ->columns(2),

            Forms\Components\Section::make(__('seo.admin.section'))
                ->description(__('seo.admin.section_hint'))
                ->collapsible()
                ->schema([
                    // ما يُترك فارغًا يُشتقّ تلقائيًا من عنوان المقال/مقتطفه عبر
                    // SeoDefaults (نفس مصدر الـplaceholder هنا وقيمة الإصدار في الواجهة).
                    Forms\Components\TextInput::make('seo_title')
                        ->label('عنوان الميتا')
                        ->maxLength(255)
                        ->placeholder(fn ($livewire): string => SeoPlaceholder::title($livewire)),
                    Forms\Components\Textarea::make('seo_description')
                        ->label('وصف الميتا')
                        ->rows(2)
                        ->maxLength(320)
                        ->placeholder(fn ($livewire): string => SeoPlaceholder::description($livewire)),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('published_at', 'desc')
            ->columns([
                Tables\Columns\ImageColumn::make('cover_image')
                    ->label('الغلاف')
                    ->disk('public')
                    ->square()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('title')
                    ->label('العنوان')
                    ->searchable()
                    ->sortable()
                    ->limit(50),
                Tables\Columns\TextColumn::make('category')
                    ->label('القسم')
                    ->badge()
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('author_name')
                    ->label('الكاتب')
                    ->toggleable()
                    ->placeholder('—'),
                Tables\Columns\IconColumn::make('is_published')
                    ->label('منشور')
                    ->boolean()
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_featured')
                    ->label('مميّز')
                    ->boolean()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('views_count')
                    ->label('المشاهدات')
                    ->numeric()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('published_at')
                    ->label('تاريخ النشر')
                    ->dateTime('Y-m-d')
                    ->placeholder('—')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_published')
                    ->label('حالة النشر'),
                Tables\Filters\TernaryFilter::make('is_featured')
                    ->label('مميّز'),
                Tables\Filters\SelectFilter::make('category')
                    ->label('القسم')
                    ->options([
                        'نصائح تربوية' => 'نصائح تربوية',
                        'تربية بالقصص' => 'تربية بالقصص',
                        'مراجعات كتب' => 'مراجعات كتب',
                        'أنشطة وتعليم' => 'أنشطة وتعليم',
                    ]),
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
        // إبقاء المقالات المحذوفة ناعمًا قابلة للوصول عبر فلتر المحذوفات.
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                \Illuminate\Database\Eloquent\SoftDeletingScope::class,
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListArticles::route('/'),
            'create' => Pages\CreateArticle::route('/create'),
            'edit' => Pages\EditArticle::route('/{record}/edit'),
        ];
    }
}
