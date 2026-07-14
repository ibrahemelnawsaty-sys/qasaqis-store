<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Concerns\HasResourcePermissions;
use App\Filament\Concerns\RestrictsSupportToProducts;
use App\Filament\Resources\ReviewResource\Pages;
use App\Models\Review;
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
 * Moderate and reply to customer book reviews.
 *
 * Atomic permissions (docs/04 §3.5): reviews.view / reviews.reply /
 * reviews.moderate — there is no reviews.create/update/delete, so the trait's
 * default action mapping is overridden below: editing a review == moderating it
 * (reviews.moderate) and replying is a distinct table action (reviews.reply).
 *
 * The «support» role is scoped SERVER-SIDE via RestrictsSupportToProducts: the
 * list query, per-record view/edit/delete, and the reply action all reject books
 * outside the user's support_user_products scope (constitution 4.4 / anti-pattern
 * 30). super_admin/admin/content_editor see every product's reviews.
 *
 * Only top-level reviews (parent_id IS NULL) are listed — replies are staff-
 * generated, auto-published child rows and need no moderation of their own.
 */
class ReviewResource extends Resource
{
    use HasResourcePermissions;
    use RestrictsSupportToProducts;

    protected static ?string $model = Review::class;

    protected static ?string $navigationIcon = 'heroicon-o-star';

    protected static ?string $navigationGroup = AdminPanelProvider::GROUP_ENGAGEMENT_SUPPORT;

    protected static ?int $navigationSort = 10;

    protected static ?string $navigationLabel = 'المراجعات والتعليقات';

    protected static ?string $modelLabel = 'مراجعة';

    protected static ?string $pluralModelLabel = 'المراجعات';

    public static function permissionPrefix(): string
    {
        return 'reviews';
    }

    // ----- Authorization -----------------------------------------------------

    public static function canView(Model $record): bool
    {
        return static::userCan('view') && static::bookInSupportScope($record->book_id);
    }

    public static function canCreate(): bool
    {
        // Reviews originate from customers; staff replies use the reply action.
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        // Editing a review == moderating its status.
        return static::userCan('moderate') && static::bookInSupportScope($record->book_id);
    }

    public static function canDelete(Model $record): bool
    {
        return static::userCan('moderate') && static::bookInSupportScope($record->book_id);
    }

    public static function canDeleteAny(): bool
    {
        return static::userCan('moderate');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('المراجعة')
                ->schema([
                    Forms\Components\Placeholder::make('book_title')
                        ->label('الكتاب')
                        ->content(fn (?Review $record): string => $record?->book?->title ?? '—'),
                    Forms\Components\Placeholder::make('author_name')
                        ->label('صاحب المراجعة')
                        ->content(fn (?Review $record): string => $record?->author_name ?? '—'),
                    Forms\Components\Placeholder::make('rating')
                        ->label('التقييم')
                        ->content(fn (?Review $record): string => $record?->rating ? $record->rating . ' / 5' : '—'),
                    Forms\Components\Placeholder::make('title')
                        ->label('العنوان')
                        ->content(fn (?Review $record): string => $record?->title ?? '—'),
                    Forms\Components\Placeholder::make('body')
                        ->label('النص')
                        ->content(fn (?Review $record): string => $record?->body ?? '—')
                        ->columnSpanFull(),
                ])
                ->columns(2),

            Forms\Components\Section::make('الإشراف')
                ->schema([
                    Forms\Components\Select::make('status')
                        ->label('الحالة')
                        ->options([
                            'pending' => 'قيد المراجعة',
                            'published' => 'منشورة',
                            'hidden' => 'مخفية',
                            'spam' => 'سبام',
                        ])
                        ->required()
                        ->native(false),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('book.title')
                    ->label('الكتاب')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('author_name')
                    ->label('صاحب المراجعة')
                    ->searchable(),
                Tables\Columns\TextColumn::make('rating')
                    ->label('التقييم')
                    ->badge()
                    ->formatStateUsing(fn (?int $state): string => $state ? $state . ' / 5' : '—'),
                Tables\Columns\TextColumn::make('status')
                    ->label('الحالة')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending' => 'قيد المراجعة',
                        'published' => 'منشورة',
                        'hidden' => 'مخفية',
                        'spam' => 'سبام',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'published' => 'success',
                        'pending' => 'warning',
                        'hidden' => 'gray',
                        'spam' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\IconColumn::make('has_media')
                    ->label('وسائط')
                    ->boolean()
                    ->toggleable(),
                Tables\Columns\IconColumn::make('is_verified_purchase')
                    ->label('شراء موثّق')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('التاريخ')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('الحالة')
                    ->options([
                        'pending' => 'قيد المراجعة',
                        'published' => 'منشورة',
                        'hidden' => 'مخفية',
                        'spam' => 'سبام',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
                    ->label('إشراف'),
                Tables\Actions\Action::make('reply')
                    ->label('رد')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->color('primary')
                    // reviews.reply + support scope, enforced server-side.
                    ->visible(fn (Review $record): bool => static::userCan('reply')
                        && static::bookInSupportScope($record->book_id))
                    ->form([
                        Forms\Components\Textarea::make('body')
                            ->label('نص الرد')
                            ->required()
                            ->maxLength(2000),
                    ])
                    ->action(function (Review $record, array $data): void {
                        $actor = Auth::user();

                        // Store the staff reply as a published child review row.
                        Review::create([
                            'book_id' => $record->book_id,
                            'user_id' => $actor?->getKey(),
                            'parent_id' => $record->getKey(),
                            'author_name' => $actor?->name ?? 'الدعم',
                            'rating' => null,
                            'body' => $data['body'],
                            'status' => 'published',
                            'replied_by' => $actor?->getKey(),
                        ]);
                    })
                    ->successNotificationTitle('تم إرسال الرد'),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->whereNull('parent_id') // top-level reviews only
            ->with(['book', 'user', 'repliedBy']);

        $user = Auth::user();

        if (static::isSupportScoped($user)) {
            // [-1] guarantees an empty result when the user has no scope rows.
            $query->whereIn('book_id', static::supportAllowedBookIds($user) ?: [-1]);
        }

        return $query;
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::getEloquentQuery()->where('status', 'pending')->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListReviews::route('/'),
            'edit' => Pages\EditReview::route('/{record}/edit'),
        ];
    }
}
