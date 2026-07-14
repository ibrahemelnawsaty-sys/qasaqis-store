<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Concerns\HasResourcePermissions;
use App\Filament\Concerns\RestrictsSupportToProducts;
use App\Filament\Resources\InquiryResource\Pages;
use App\Models\Inquiry;
use App\Providers\Filament\AdminPanelProvider;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Customer inquiries (contact / product question / complaint / wholesale) and the
 * staff response flow.
 *
 * Atomic permissions (docs/04 §3.5): inquiries.view / inquiries.respond. Reading
 * uses inquiries.view; editing == responding, so canEdit maps to inquiries.respond
 * (there is no inquiries.update). Creation is disabled (inquiries arrive from the
 * storefront). Delete keeps the trait default (inquiries.delete is undefined, so
 * effectively super_admin-only via the Gate::before bypass).
 *
 * The «support» role is scoped SERVER-SIDE via RestrictsSupportToProducts: only
 * inquiries tied to a book inside the user's scope are listed/viewable/answerable.
 * Inquiries with no book are never in a support scope.
 */
class InquiryResource extends Resource
{
    use HasResourcePermissions;
    use RestrictsSupportToProducts;

    protected static ?string $model = Inquiry::class;

    protected static ?string $navigationIcon = 'heroicon-o-inbox';

    protected static ?string $navigationGroup = AdminPanelProvider::GROUP_ENGAGEMENT_SUPPORT;

    protected static ?int $navigationSort = 20;

    protected static ?string $navigationLabel = 'الاستفسارات';

    protected static ?string $modelLabel = 'استفسار';

    protected static ?string $pluralModelLabel = 'الاستفسارات';

    public static function permissionPrefix(): string
    {
        return 'inquiries';
    }

    // ----- Authorization -----------------------------------------------------

    public static function canView(Model $record): bool
    {
        return static::userCan('view') && static::bookInSupportScope($record->book_id);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        // Editing an inquiry == responding to it.
        return static::userCan('respond') && static::bookInSupportScope($record->book_id);
    }

    public static function canDelete(Model $record): bool
    {
        // inquiries.delete is undefined → only super_admin (Gate::before) passes.
        return static::userCan('delete') && static::bookInSupportScope($record->book_id);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('الاستفسار')
                ->schema([
                    Forms\Components\Placeholder::make('type')
                        ->label('النوع')
                        ->content(fn (?Inquiry $record): string => match ($record?->type) {
                            'contact' => 'تواصل عام',
                            'product_question' => 'سؤال عن منتج',
                            'complaint' => 'شكوى',
                            'wholesale_b2b' => 'جملة / B2B',
                            default => '—',
                        }),
                    Forms\Components\Placeholder::make('book_title')
                        ->label('الكتاب')
                        ->content(fn (?Inquiry $record): string => $record?->book?->title ?? '—'),
                    Forms\Components\Placeholder::make('name')
                        ->label('الاسم')
                        ->content(fn (?Inquiry $record): string => $record?->name ?? '—'),
                    Forms\Components\Placeholder::make('phone')
                        ->label('الهاتف')
                        ->content(fn (?Inquiry $record): string => $record?->phone ?? '—'),
                    Forms\Components\Placeholder::make('email')
                        ->label('البريد')
                        ->content(fn (?Inquiry $record): string => $record?->email ?? '—'),
                    Forms\Components\Placeholder::make('subject')
                        ->label('الموضوع')
                        ->content(fn (?Inquiry $record): string => $record?->subject ?? '—'),
                    Forms\Components\Placeholder::make('message')
                        ->label('الرسالة')
                        ->content(fn (?Inquiry $record): string => $record?->message ?? '—')
                        ->columnSpanFull(),
                ])
                ->columns(2),

            Forms\Components\Section::make('الرد')
                ->schema([
                    Forms\Components\Select::make('status')
                        ->label('الحالة')
                        ->options([
                            'new' => 'جديد',
                            'in_progress' => 'قيد المعالجة',
                            'answered' => 'تم الرد',
                            'closed' => 'مغلق',
                        ])
                        ->required()
                        ->native(false),
                    Forms\Components\Textarea::make('admin_reply')
                        ->label('رد الإدارة')
                        ->rows(4)
                        ->maxLength(4000)
                        ->columnSpanFull(),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('type')
                    ->label('النوع')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'contact' => 'تواصل عام',
                        'product_question' => 'سؤال عن منتج',
                        'complaint' => 'شكوى',
                        'wholesale_b2b' => 'جملة / B2B',
                        default => $state,
                    }),
                Tables\Columns\TextColumn::make('name')
                    ->label('الاسم')
                    ->searchable(),
                Tables\Columns\TextColumn::make('phone')
                    ->label('الهاتف')
                    ->searchable(),
                Tables\Columns\TextColumn::make('book.title')
                    ->label('الكتاب')
                    ->placeholder('—')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('الحالة')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'new' => 'جديد',
                        'in_progress' => 'قيد المعالجة',
                        'answered' => 'تم الرد',
                        'closed' => 'مغلق',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'new' => 'warning',
                        'in_progress' => 'info',
                        'answered' => 'success',
                        'closed' => 'gray',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('التاريخ')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('الحالة')
                    ->options([
                        'new' => 'جديد',
                        'in_progress' => 'قيد المعالجة',
                        'answered' => 'تم الرد',
                        'closed' => 'مغلق',
                    ]),
                Tables\Filters\SelectFilter::make('type')
                    ->label('النوع')
                    ->options([
                        'contact' => 'تواصل عام',
                        'product_question' => 'سؤال عن منتج',
                        'complaint' => 'شكوى',
                        'wholesale_b2b' => 'جملة / B2B',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
                    ->label('رد'),
                Tables\Actions\DeleteAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['book', 'assignee']);

        $user = Auth::user();

        if (static::isSupportScoped($user)) {
            // whereIn on book_id excludes null-book (general) inquiries too.
            $query->whereIn('book_id', static::supportAllowedBookIds($user) ?: [-1]);
        }

        return $query;
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::getEloquentQuery()->where('status', 'new')->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInquiries::route('/'),
            'edit' => Pages\EditInquiry::route('/{record}/edit'),
        ];
    }
}
