<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\EmailSuppressionResource\Pages;
use App\Models\EmailSuppression;
use App\Providers\Filament\AdminPanelProvider;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * من ألغوا الاشتراك في استقبال الإيميلات (قائمة الحظر). كل بريد هنا يُستبعَد من كل
 * حملة تسويقية. يتيح للأدمن **إعادة تفعيل** بريد (حذفه من القائمة فيعود يستقبل)،
 * فرديًّا أو جماعيًّا، وحظر بريد يدويًا. لا يمسّ رسائل المعاملات (تأكيد الطلب/التحقّق).
 *
 * الصلاحية المستهلَكة: campaigns.suppressions.manage (موجودة في RolePermissionSeeder،
 * يحملها super_admin/admin/marketing). تُفرَض خادميًا في كل إجراء (بند 4.4).
 */
class EmailSuppressionResource extends Resource
{
    protected static ?string $model = EmailSuppression::class;

    protected static ?string $navigationIcon = 'heroicon-o-no-symbol';

    protected static ?string $navigationGroup = AdminPanelProvider::GROUP_ENGAGEMENT_SUPPORT;

    protected static ?int $navigationSort = 30;

    protected static ?string $navigationLabel = 'من ألغوا الاشتراك';

    protected static ?string $modelLabel = 'بريد ملغى';

    protected static ?string $pluralModelLabel = 'من ألغوا الاشتراك';

    public const REASON_LABELS = [
        'unsubscribe' => 'ألغى الاشتراك',
        'bounce' => 'ارتداد',
        'manual' => 'حظر يدوي',
        'complaint' => 'شكوى',
    ];

    protected static function canManage(): bool
    {
        return (bool) auth()->user()?->can('campaigns.suppressions.manage');
    }

    public static function canViewAny(): bool
    {
        return static::canManage();
    }

    public static function canView(Model $record): bool
    {
        return static::canManage();
    }

    public static function canCreate(): bool
    {
        return static::canManage();
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return static::canManage();
    }

    public static function canDeleteAny(): bool
    {
        return static::canManage();
    }

    public static function form(Form $form): Form
    {
        // نموذج الحظر اليدوي (Modal): بريد + سبب.
        return $form->schema([
            TextInput::make('email')
                ->label('البريد الإلكتروني')
                ->email()
                ->required()
                ->unique(ignoreRecord: true)
                ->maxLength(255),
            Select::make('reason')
                ->label('السبب')
                ->options(self::REASON_LABELS)
                ->default('manual')
                ->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('suppressed_at', 'desc')
            ->emptyStateHeading('لا أحد ألغى اشتراكه')
            ->emptyStateDescription('كل المشتركين يستقبلون رسائلك.')
            ->columns([
                TextColumn::make('email')
                    ->label('البريد')
                    ->searchable()
                    ->copyable(),
                TextColumn::make('reason')
                    ->label('السبب')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => self::REASON_LABELS[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        'complaint' => 'danger',
                        'bounce' => 'warning',
                        'manual' => 'info',
                        default => 'gray',
                    }),
                TextColumn::make('suppressed_at')
                    ->label('تاريخ الإلغاء')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('reason')
                    ->label('السبب')
                    ->options(self::REASON_LABELS),
            ])
            ->actions([
                Tables\Actions\Action::make('reactivate')
                    ->label('إعادة التفعيل')
                    ->icon('heroicon-o-arrow-path')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('إعادة تفعيل استقبال الإيميلات')
                    ->modalDescription(fn (EmailSuppression $record): string => "سيعود «{$record->email}» لاستقبال الحملات والعروض. متأكّد؟")
                    ->modalSubmitActionLabel('نعم، أعِد التفعيل')
                    ->visible(fn (): bool => static::canManage())
                    ->action(function (EmailSuppression $record): void {
                        abort_unless(static::canManage(), 403);

                        $email = $record->email;
                        $record->delete();

                        Notification::make()
                            ->title('أُعيد التفعيل')
                            ->body("«{$email}» سيستقبل الإيميلات مجددًا.")
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('reactivateBulk')
                    ->label('إعادة تفعيل المحدّد')
                    ->icon('heroicon-o-arrow-path')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('إعادة تفعيل المحدّدين')
                    ->modalDescription('سيعود كل المحدّدين لاستقبال الحملات والعروض.')
                    ->visible(fn (): bool => static::canManage())
                    ->deselectRecordsAfterCompletion()
                    ->action(function (Collection $records): void {
                        abort_unless(static::canManage(), 403);

                        $count = $records->count();
                        EmailSuppression::query()->whereIn('id', $records->pluck('id'))->delete();

                        Notification::make()
                            ->title("أُعيد تفعيل {$count} بريدًا")
                            ->success()
                            ->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEmailSuppressions::route('/'),
        ];
    }
}
