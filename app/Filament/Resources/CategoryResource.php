<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Concerns\HasResourcePermissions;
use App\Filament\Resources\CategoryResource\Pages;
use App\Models\Category;
use App\Providers\Filament\AdminPanelProvider;
use App\Filament\Support\SeoFieldset;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Sections/categories resource (the six sections — kept even when empty,
 * constitution 0.3 / anti-pattern 25).
 *
 * Permission prefix «categories» exposes only view + manage (docs/04 §3.1) — it
 * has NO atomic create/update/delete permissions. So the trait's default
 * create/edit/delete (which would check categories.create etc.) are overridden to
 * check «categories.manage», avoiding invented permission names (constitution 1.1).
 *
 * Delete safety: the books.category_id FK is restrictOnDelete; a category holding
 * books cannot be deleted. That DB guard is surfaced as a friendly check here so
 * the admin sees a clear message instead of a raw SQL error (task requirement).
 */
class CategoryResource extends Resource
{
    use HasResourcePermissions;

    protected static ?string $model = Category::class;

    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';

    protected static ?string $navigationGroup = AdminPanelProvider::GROUP_BOOKS_CONTENT;

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'name';

    public static function permissionPrefix(): string
    {
        return 'categories';
    }

    // «categories» has only view + manage — map all mutations to «manage».
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

    public static function getModelLabel(): string
    {
        return 'قسم';
    }

    public static function getPluralModelLabel(): string
    {
        return 'الأقسام';
    }

    public static function getNavigationLabel(): string
    {
        return 'الأقسام';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make()
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('الاسم')
                        ->required()
                        ->maxLength(120),

                    Forms\Components\TextInput::make('slug')
                        ->label('المُعرّف (Slug)')
                        ->required()
                        ->maxLength(140)
                        ->unique(ignoreRecord: true)
                        ->helperText('حروف لاتينية/أرقام؛ يُكتب يدويًا.'),

                    Forms\Components\Select::make('parent_id')
                        ->label('القسم الأب')
                        ->relationship(
                            name: 'parent',
                            titleAttribute: 'name',
                            // Never allow a category to be its own parent.
                            modifyQueryUsing: fn (Builder $query, ?Category $record) => $record
                                ? $query->whereKeyNot($record->getKey())
                                : $query,
                        )
                        ->searchable()
                        ->preload()
                        ->helperText('اختياري — لأقسام فرعية مستقبلية.'),

                    Forms\Components\ColorPicker::make('color_hex')
                        ->label('اللون'),

                    Forms\Components\TextInput::make('icon')
                        ->label('الأيقونة')
                        ->maxLength(100)
                        ->helperText('اسم أيقونة (مثل heroicon-o-book-open).'),

                    Forms\Components\TextInput::make('sort_order')
                        ->label('الترتيب')
                        ->integer()
                        ->default(0)
                        ->required(),

                    Forms\Components\Toggle::make('is_active')
                        ->label('مُفعّل')
                        ->default(true),

                    Forms\Components\FileUpload::make('image_path')
                        ->label('صورة القسم')
                        ->image()
                        ->disk('public')
                        ->directory('categories')
                        ->visibility('public')
                        ->maxSize(2048)
                        ->columnSpanFull(),

                    Forms\Components\Textarea::make('description')
                        ->label('الوصف')
                        ->rows(3)
                        ->columnSpanFull()
                        ->helperText('يُستخدم أيضًا كوصف الميتا الافتراضي للقسم إن تُرك حقل SEO فارغًا.'),
                ]),

            // قسم SEO: كان القسم يملك علاقة seo() دون واجهة تملؤها (علاقة ميتة).
            SeoFieldset::make(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ColorColumn::make('color_hex')
                    ->label('اللون'),

                Tables\Columns\TextColumn::make('name')
                    ->label('الاسم')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('slug')
                    ->label('المُعرّف')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('books_count')
                    ->label('عدد الكتب')
                    ->counts('books')
                    ->sortable(),

                Tables\Columns\TextColumn::make('sort_order')
                    ->label('الترتيب')
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('مُفعّل')
                    ->boolean()
                    ->sortable(),
            ])
            ->defaultSort('sort_order')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('مُفعّل'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    // Refuse to delete a section that still holds books (mirrors the
                    // restrictOnDelete FK) with a clear message, not a SQL error.
                    ->before(function (Category $record, Tables\Actions\DeleteAction $action): void {
                        if ($record->books()->exists()) {
                            Notification::make()
                                ->title('لا يمكن حذف قسم يحتوي على كتب')
                                ->body('انقل الكتب إلى قسم آخر أو احذفها أولًا.')
                                ->danger()
                                ->send();

                            $action->halt();
                        }
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->action(function (\Illuminate\Support\Collection $records, Tables\Actions\DeleteBulkAction $action): void {
                            // Guard bulk delete the same way as the single delete.
                            $blocked = $records->filter(fn (Category $record): bool => $record->books()->exists());

                            if ($blocked->isNotEmpty()) {
                                Notification::make()
                                    ->title('بعض الأقسام تحتوي على كتب ولم تُحذف')
                                    ->body('تم تخطّي الأقسام التي بها كتب.')
                                    ->warning()
                                    ->send();
                            }

                            $records->reject(fn (Category $record): bool => $record->books()->exists())
                                ->each(fn (Category $record) => $record->delete());
                        }),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCategories::route('/'),
            'create' => Pages\CreateCategory::route('/create'),
            'edit' => Pages\EditCategory::route('/{record}/edit'),
        ];
    }
}
