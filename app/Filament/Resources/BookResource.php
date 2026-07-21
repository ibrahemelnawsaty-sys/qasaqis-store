<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Concerns\HasResourcePermissions;
use App\Filament\Resources\BookResource\Pages;
use App\Filament\Resources\BookResource\RelationManagers\ImagesRelationManager;
use App\Filament\Resources\BookResource\RelationManagers\ReviewsRelationManager;
use App\Filament\Support\SeoPlaceholder;
use App\Models\Book;
use App\Models\Category;
use App\Providers\Filament\AdminPanelProvider;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\SoftDeletingScope;

/**
 * Books catalogue resource (23 books, six sections).
 *
 * Permission prefix is «products» (docs/04 §3.1) — the book IS the product; the
 * standard view/create/update/delete actions all exist for it, so the baseline
 * HasResourcePermissions trait maps cleanly with no overrides.
 *
 * Content honesty (constitution 0.4 / anti-patterns 21-22): price and cover_image
 * are nullable in the schema and stay nullable here — BOOK1 has no price and
 * BOOK10 has no cover; nothing is invented or defaulted.
 */
class BookResource extends Resource
{
    use HasResourcePermissions;

    protected static ?string $model = Book::class;

    protected static ?string $navigationIcon = 'heroicon-o-book-open';

    protected static ?string $navigationGroup = AdminPanelProvider::GROUP_BOOKS_CONTENT;

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'title';

    public static function permissionPrefix(): string
    {
        // docs/04 §3.1 — books are products; do NOT invent a «books» prefix.
        return 'products';
    }

    public static function getModelLabel(): string
    {
        return 'كتاب';
    }

    public static function getPluralModelLabel(): string
    {
        return 'الكتب';
    }

    public static function getNavigationLabel(): string
    {
        return 'الكتب';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('البيانات الأساسية')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('title')
                        ->label('العنوان')
                        ->required()
                        ->maxLength(200)
                        ->columnSpanFull(),

                    Forms\Components\TextInput::make('slug')
                        ->label('المُعرّف (Slug)')
                        ->required()
                        ->maxLength(220)
                        ->unique(ignoreRecord: true)
                        ->helperText('يُكتب يدويًا بحروف لاتينية/أرقام؛ التوليد التلقائي لا يصلح للعربية.'),

                    Forms\Components\TextInput::make('sku')
                        ->label('رمز المنتج (SKU)')
                        ->maxLength(60)
                        ->unique(ignoreRecord: true),

                    Forms\Components\Select::make('category_id')
                        ->label('القسم الرئيسي')
                        ->relationship('category', 'name')
                        ->searchable()
                        ->preload()
                        ->required(),

                    // أقسام إضافية (many-to-many عبر book_category). Filament يزامن الجدول
                    // الوسيط تلقائيًا. القسم الرئيسي أعلاه يبقى إلزاميًا؛ هذه إضافية فقط.
                    Forms\Components\Select::make('categories')
                        ->label('أقسام إضافية')
                        ->relationship('categories', 'name')
                        ->multiple()
                        ->searchable()
                        ->preload()
                        ->helperText('أقسام أخرى يظهر فيها الكتاب أيضًا (اختياري) — بالإضافة إلى القسم الرئيسي.'),

                    Forms\Components\Select::make('publisher_id')
                        ->label('دار النشر')
                        ->relationship('publisher', 'name')
                        ->searchable()
                        ->preload()
                        ->helperText('اتركه فارغًا للكتب بلا دار نشر ظاهرة — لا تُسنِد دارًا تخمينًا.'),

                    // السلسلة (اختياري): إن كان الكتاب أحد عناوين سلسلة.
                    Forms\Components\Select::make('series_id')
                        ->label('السلسلة')
                        ->relationship('series', 'name')
                        ->searchable()
                        ->preload()
                        ->helperText('اختر السلسلة إن كان الكتاب جزءًا منها (اختياري) — أنشئ السلاسل من «سلاسل الكتب».'),

                    Forms\Components\TextInput::make('series_position')
                        ->label('ترتيب العنوان داخل السلسلة')
                        ->integer()
                        ->minValue(0)
                        ->helperText('رقم ترتيب هذا العنوان بين عناوين السلسلة (يظهر بالترتيب في المبدّل).'),

                    Forms\Components\TextInput::make('author')
                        ->label('المؤلف')
                        ->maxLength(150),

                    Forms\Components\TextInput::make('illustrator')
                        ->label('الرسّام')
                        ->maxLength(150),
                ]),

            Forms\Components\Section::make('الوصف')
                ->schema([
                    Forms\Components\Textarea::make('short_description')
                        ->label('الوصف القصير')
                        ->maxLength(500)
                        ->rows(3),

                    Forms\Components\RichEditor::make('long_description')
                        ->label('الوصف الطويل')
                        ->columnSpanFull(),

                    Forms\Components\TagsInput::make('learning_outcomes')
                        ->label('مخرجات التعلّم')
                        ->helperText('أضف كل مخرج تعلّم كوسم منفصل.'),
                ]),

            Forms\Components\Section::make('السعر')
                ->columns(3)
                ->schema([
                    // Nullable on purpose (BOOK1 has no price) — never forced.
                    Forms\Components\TextInput::make('price')
                        ->label('السعر الحالي (ج.م)')
                        ->numeric()
                        ->minValue(0)
                        ->helperText('اتركه فارغًا إن لم يتوفر سعر بعد (يُعرض «السعر غير متاح»).'),

                    Forms\Components\TextInput::make('old_price')
                        ->label('السعر القديم/المشطوب (ج.م)')
                        ->numeric()
                        ->minValue(0),

                    // تكلفة الشراء سرّية (الدستور 0.7): تظهر لمن يملك products.cost.view،
                    // وتُعطّل لمن لا يملك products.cost.update. dehydrated المربوط بالصلاحية
                    // يمنع كتابتها من طلب مُلفَّق حتى لو تلاعب بالحقل (الدستور 4.1) — ومن لا
                    // يراها يبقى صفّها في القاعدة سليمًا لأن الحقل لا يُرطَّب عند الحفظ.
                    Forms\Components\TextInput::make('cost_price')
                        ->label('سعر التكلفة (ج.م)')
                        ->numeric()
                        ->minValue(0)
                        ->visible(fn (): bool => static::userCan('cost.view'))
                        ->disabled(fn (): bool => ! static::userCan('cost.update'))
                        ->dehydrated(fn (): bool => static::userCan('cost.update'))
                        // Book::$hidden يحذف cost_price من attributesToArray الذي يملأ
                        // منه Filament النموذج، فيظهر الحقل فارغًا ويُكتب NULL عند الحفظ
                        // (إتلاف صامت للتكلفة). نُعيد ترطيبه من القيمة الخام (getRawOriginal
                        // يتجاوز $hidden) لمن يملك الرؤية فقط، فيُعرض ويُحفظ سليمًا.
                        ->afterStateHydrated(function (Forms\Components\TextInput $component, ?Book $record): void {
                            if ($record !== null && static::userCan('cost.view')) {
                                $component->state($record->getRawOriginal('cost_price'));
                            }
                        }),
                ]),

            Forms\Components\Section::make('المخزون')
                ->columns(3)
                ->schema([
                    Forms\Components\TextInput::make('stock_quantity')
                        ->label('الكمية')
                        ->integer()
                        ->default(0)
                        ->required(),

                    Forms\Components\Select::make('stock_status')
                        ->label('حالة المخزون')
                        ->options([
                            'in_stock' => 'متوفر',
                            'out_of_stock' => 'غير متوفر',
                            'preorder' => 'طلب مسبق',
                        ])
                        ->default('in_stock')
                        ->required(),

                    Forms\Components\Toggle::make('manage_stock')
                        ->label('تتبّع المخزون')
                        ->default(true),
                ]),

            Forms\Components\Section::make('تفاصيل الكتاب')
                ->columns(3)
                ->schema([
                    Forms\Components\TextInput::make('age_min')
                        ->label('أصغر عمر')
                        ->integer()
                        ->minValue(0)
                        ->maxValue(255),

                    Forms\Components\TextInput::make('age_max')
                        ->label('أكبر عمر')
                        ->integer()
                        ->minValue(0)
                        ->maxValue(255),

                    Forms\Components\TextInput::make('age_label')
                        ->label('الفئة العمرية (نص)')
                        ->maxLength(50)
                        ->placeholder('مثال: 4 - 8 سنوات'),

                    Forms\Components\TextInput::make('pages_count')
                        ->label('عدد الصفحات')
                        ->integer()
                        ->minValue(0),

                    Forms\Components\TextInput::make('isbn')
                        ->label('ISBN')
                        ->maxLength(20),

                    Forms\Components\TextInput::make('weight_grams')
                        ->label('الوزن (جرام)')
                        ->integer()
                        ->minValue(0),
                ]),

            Forms\Components\Section::make('الغلاف والنشر')
                ->columns(2)
                ->schema([
                    // Nullable (BOOK10 has no cover) — no invented/generated image.
                    Forms\Components\FileUpload::make('cover_image')
                        ->label('صورة الغلاف')
                        ->image()
                        ->disk('public')
                        ->directory('books/covers')
                        ->visibility('public')
                        ->maxSize(2048)
                        ->helperText('اتركه فارغًا إن لم يتوفر غلاف — يُعرض عنصر بديل محايد.')
                        ->columnSpanFull(),

                    Forms\Components\Toggle::make('is_published')
                        ->label('منشور'),

                    Forms\Components\Toggle::make('is_featured')
                        ->label('مميّز'),

                    Forms\Components\Toggle::make('is_bestseller')
                        ->label('الأكثر مبيعًا'),

                    Forms\Components\DateTimePicker::make('published_at')
                        ->label('تاريخ النشر'),

                    Forms\Components\TextInput::make('sort_order')
                        ->label('الترتيب')
                        ->integer()
                        ->default(0)
                        ->required(),
                ]),

            // SEO stored via the polymorphic seo() morphOne relation (seo_meta).
            // الحقول اختيارية: ما يُترك فارغًا يُشتقّ تلقائيًا من محتوى الكتاب عبر
            // SeoDefaults (نفس مصدر الـplaceholder هنا وقيمة الإصدار في الواجهة).
            Forms\Components\Section::make(__('seo.admin.section'))
                ->description(__('seo.admin.section_hint'))
                ->relationship('seo')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('meta_title')
                        ->label('عنوان الميتا')
                        ->maxLength(255)
                        ->placeholder(fn ($livewire): string => SeoPlaceholder::title($livewire)),

                    Forms\Components\Select::make('robots')
                        ->label('توجيه الروبوتات')
                        ->options([
                            'index,follow' => 'index,follow',
                            'noindex,follow' => 'noindex,follow',
                            'index,nofollow' => 'index,nofollow',
                            'noindex,nofollow' => 'noindex,nofollow',
                        ])
                        ->default('index,follow'),

                    Forms\Components\Textarea::make('meta_description')
                        ->label('وصف الميتا')
                        ->maxLength(320)
                        ->rows(2)
                        ->columnSpanFull()
                        ->placeholder(fn ($livewire): string => SeoPlaceholder::description($livewire)),

                    Forms\Components\TextInput::make('meta_keywords')
                        ->label('الكلمات المفتاحية')
                        ->maxLength(255),

                    Forms\Components\TextInput::make('canonical_url')
                        ->label('الرابط القانوني (Canonical)')
                        ->url()
                        ->maxLength(300),

                    Forms\Components\TextInput::make('og_title')
                        ->label('عنوان OpenGraph')
                        ->maxLength(255)
                        ->placeholder(fn ($livewire): string => SeoPlaceholder::title($livewire)),

                    Forms\Components\Textarea::make('og_description')
                        ->label('وصف OpenGraph')
                        ->maxLength(320)
                        ->rows(2)
                        ->columnSpanFull()
                        ->placeholder(fn ($livewire): string => SeoPlaceholder::description($livewire)),

                    Forms\Components\FileUpload::make('og_image_path')
                        ->label('صورة المشاركة (OG)')
                        ->image()
                        ->disk('public')
                        ->directory('seo/og')
                        ->visibility('public')
                        ->maxSize(2048)
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('cover_image')
                    ->label('الغلاف')
                    ->disk('public')
                    ->square(),

                Tables\Columns\TextColumn::make('title')
                    ->label('العنوان')
                    ->searchable()
                    ->sortable()
                    ->wrap(),

                // الأعمدة الثانوية مخفية افتراضيًا كي يظهر الجدول كاملًا بلا سحب أفقي؛
                // تُفعَّل بنقرة من قائمة «الأعمدة» أعلى الجدول عند الحاجة.
                Tables\Columns\TextColumn::make('author')
                    ->label('المؤلف')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('category.name')
                    ->label('القسم')
                    ->badge()
                    ->sortable(),

                Tables\Columns\TextColumn::make('publisher.name')
                    ->label('دار النشر')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('series.name')
                    ->label('السلسلة')
                    ->badge()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('price')
                    ->label('السعر')
                    ->money('EGP')
                    // BOOK1 has no price — show «غير متاح», never a fake value.
                    ->placeholder('غير متاح')
                    ->sortable(),

                Tables\Columns\TextColumn::make('stock_status')
                    ->label('المخزون')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'in_stock' => 'متوفر',
                        'out_of_stock' => 'غير متوفر',
                        'preorder' => 'طلب مسبق',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'in_stock' => 'success',
                        'out_of_stock' => 'danger',
                        'preorder' => 'warning',
                        default => 'gray',
                    }),

                Tables\Columns\IconColumn::make('is_published')
                    ->label('منشور')
                    ->boolean()
                    ->sortable(),

                Tables\Columns\TextColumn::make('stock_quantity')
                    ->label('الكمية')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\IconColumn::make('is_featured')
                    ->label('مميّز')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\IconColumn::make('is_bestseller')
                    ->label('الأكثر مبيعًا')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),

                // عمود «الترتيب» قابل للكتابة مباشرةً (بديل عن السحب حين تكون الكتب
                // كثيرة). الأصغر يظهر أولًا. للمحرّرين فقط؛ لغيرهم للقراءة.
                Tables\Columns\TextInputColumn::make('sort_order')
                    ->label('الترتيب')
                    ->type('number')
                    ->rules(['integer', 'min:0'])
                    ->sortable()
                    ->disabled(fn (): bool => ! static::userCan('update'))
                    ->toggleable(),

                Tables\Columns\TextColumn::make('reviews_count')
                    ->label('المراجعات')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('sort_order')
            // سحب وإفلات لضبط ترتيب ظهور الكتب (يؤثّر على أقسام الرئيسية التي ترتّب
            // بـsort_order: مختارات/قسم/عروض). تصاعدي = الأعلى أولًا. للمحرّرين فقط.
            ->reorderable('sort_order', fn (): bool => static::userCan('update'))
            ->defaultPaginationPageOption(25)
            ->paginated([25, 50, 100, 'all'])
            ->filters([
                Tables\Filters\SelectFilter::make('category_id')
                    ->label('القسم')
                    ->relationship('category', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('publisher_id')
                    ->label('دار النشر')
                    ->relationship('publisher', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('stock_status')
                    ->label('حالة المخزون')
                    ->options([
                        'in_stock' => 'متوفر',
                        'out_of_stock' => 'غير متوفر',
                        'preorder' => 'طلب مسبق',
                    ]),

                Tables\Filters\TernaryFilter::make('is_published')
                    ->label('منشور'),

                Tables\Filters\TernaryFilter::make('is_featured')
                    ->label('مميّز'),

                Tables\Filters\TernaryFilter::make('is_bestseller')
                    ->label('الأكثر مبيعًا'),

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
                    // إجراءات جماعية للكتب المحدَّدة. كلها مقيّدة بصلاحية products.update
                    // خادميًا (بند 4.4)، وتُحفظ عبر النموذج لا باستعلام جماعي حتى تعمل
                    // المراقِبات (إعادة بناء فهرس البحث).
                    Tables\Actions\BulkAction::make('publish')
                        ->label('نشر المحدَّد')
                        ->icon('heroicon-o-eye')
                        ->color('success')
                        ->requiresConfirmation()
                        ->deselectRecordsAfterCompletion()
                        ->visible(fn (): bool => static::userCan('update'))
                        ->action(function (Collection $records): void {
                            $records->each(function (Book $book): void {
                                $book->update([
                                    'is_published' => true,
                                    // نختم تاريخ النشر مرة واحدة فقط.
                                    'published_at' => $book->published_at ?? now(),
                                ]);
                            });

                            Notification::make()
                                ->title('تم نشر '.$records->count().' كتابًا')
                                ->success()
                                ->send();
                        }),

                    Tables\Actions\BulkAction::make('unpublish')
                        ->label('إلغاء نشر المحدَّد')
                        ->icon('heroicon-o-eye-slash')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->deselectRecordsAfterCompletion()
                        ->visible(fn (): bool => static::userCan('update'))
                        ->action(function (Collection $records): void {
                            $records->each(fn (Book $book) => $book->update(['is_published' => false]));

                            Notification::make()
                                ->title('تم إلغاء نشر '.$records->count().' كتابًا')
                                ->success()
                                ->send();
                        }),

                    Tables\Actions\BulkAction::make('feature')
                        ->label('تمييز / إلغاء التمييز')
                        ->icon('heroicon-o-star')
                        ->deselectRecordsAfterCompletion()
                        ->visible(fn (): bool => static::userCan('update'))
                        ->form([
                            Forms\Components\Toggle::make('is_featured')
                                ->label('مميّز')
                                ->default(true),
                        ])
                        ->action(function (Collection $records, array $data): void {
                            $featured = (bool) ($data['is_featured'] ?? false);
                            $records->each(fn (Book $book) => $book->update(['is_featured' => $featured]));

                            Notification::make()
                                ->title(($featured ? 'تم تمييز ' : 'أُلغي تمييز ').$records->count().' كتابًا')
                                ->success()
                                ->send();
                        }),

                    Tables\Actions\BulkAction::make('setStock')
                        ->label('تعديل المخزون')
                        ->icon('heroicon-o-archive-box')
                        ->deselectRecordsAfterCompletion()
                        ->visible(fn (): bool => static::userCan('update'))
                        ->form([
                            Forms\Components\TextInput::make('stock_quantity')
                                ->label('الكمية')
                                ->integer()
                                ->minValue(0)
                                ->required()
                                ->helperText('تُطبَّق على كل الكتب المحدَّدة. الصفر يجعلها «غير متوفر».'),
                        ])
                        ->action(function (Collection $records, array $data): void {
                            $qty = max(0, (int) $data['stock_quantity']);

                            $records->each(fn (Book $book) => $book->update([
                                'stock_quantity' => $qty,
                                // الحالة تُشتق من الكمية فلا تتناقض مع المعروض.
                                'stock_status' => $qty > 0 ? 'in_stock' : 'out_of_stock',
                            ]));

                            Notification::make()
                                ->title('تم ضبط مخزون '.$records->count().' كتابًا على '.$qty)
                                ->success()
                                ->send();
                        }),

                    Tables\Actions\BulkAction::make('setCategory')
                        ->label('نقل إلى قسم')
                        ->icon('heroicon-o-folder-arrow-down')
                        ->deselectRecordsAfterCompletion()
                        ->visible(fn (): bool => static::userCan('update'))
                        ->form([
                            Forms\Components\Select::make('category_id')
                                ->label('القسم')
                                // استعلام صريح مؤجَّل: نموذج الإجراء الجماعي بلا سجلّ
                                // مرجعي، فـ relationship() لا تجد موديلًا تشتقّ منه.
                                ->options(fn (): array => Category::query()
                                    ->orderBy('name')
                                    ->pluck('name', 'id')
                                    ->all())
                                ->searchable()
                                ->required()
                                ->helperText('يغيّر القسم الرئيسي للكتب المحدَّدة. الأقسام الإضافية تبقى كما هي.'),
                        ])
                        ->action(function (Collection $records, array $data): void {
                            $categoryId = (int) $data['category_id'];

                            $records->each(fn (Book $book) => $book->update([
                                'category_id' => $categoryId,
                            ]));

                            Notification::make()
                                ->title('تم نقل '.$records->count().' كتابًا إلى القسم المحدَّد')
                                ->success()
                                ->send();
                        }),

                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            ImagesRelationManager::class,
            ReviewsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBooks::route('/'),
            'create' => Pages\CreateBook::route('/create'),
            'edit' => Pages\EditBook::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        // Eager-load list relations (constitution 2.5 — no N+1) and include
        // soft-deleted books so they can be restored/force-deleted.
        return parent::getEloquentQuery()
            ->with(['category', 'publisher', 'series'])
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}
