<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Concerns\HasResourcePermissions;
use App\Filament\Resources\AdminActivityResource\Pages;
use App\Models\AdminActivity;
use App\Providers\Filament\AdminPanelProvider;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Form;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * سجل «من غيّر ماذا ومتى» (مجموعة: المستخدمون والصلاحيات).
 *
 * **للقراءة فقط بالمطلق.** الجدول append-only يكتبه
 * App\Support\Audit\RecordsAdminActivity وحده؛ أثر التدقيق الذي يمكن تحريره أو
 * حذفه من اللوحة لا قيمة له. لذلك تُغلق كل بوابات الطفرة بإرجاع false صريح —
 * لا بالاكتفاء بغياب الصلاحية: الصلاحيات الذرّية system.logs.create/update/delete
 * غير موجودة أصلًا في RolePermissionSeeder، لكن super_admin يمرّ عبر Gate::before
 * فكان سيحصل عليها ضمنًا (بند 4.4 / ممنوع 13).
 *
 * الصلاحية المستهلَكة: `system.logs.view` — موجودة فعلًا في
 * RolePermissionSeeder::PERMISSIONS ولم تُخترع (بند 1.1). يحملها اليوم
 * super_admin و it.
 */
class AdminActivityResource extends Resource
{
    use HasResourcePermissions;

    protected static ?string $model = AdminActivity::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationGroup = AdminPanelProvider::GROUP_USERS_PERMISSIONS;

    protected static ?int $navigationSort = 30;

    protected static ?string $navigationLabel = 'سجل النشاط الإداري';

    protected static ?string $modelLabel = 'سطر نشاط';

    protected static ?string $pluralModelLabel = 'سجل النشاط الإداري';

    /**
     * قيم enum عمود `event` حرفيًا من الهجرة مع تسمياتها العربية — نفس نمط
     * OrderResource::STATUS_LABELS: القيم على الموديل، والعرض هنا في طبقة Filament.
     */
    public const EVENT_LABELS = [
        AdminActivity::EVENT_CREATED => 'إنشاء',
        AdminActivity::EVENT_UPDATED => 'تعديل',
        AdminActivity::EVENT_DELETED => 'حذف',
        AdminActivity::EVENT_RESTORED => 'استعادة',
    ];

    public static function permissionPrefix(): string
    {
        // يُترجم عبر HasResourcePermissions إلى system.logs.view.
        return 'system.logs';
    }

    // ----- بوابات الطفرة: مغلقة نهائيًا -------------------------------------

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function canRestore(Model $record): bool
    {
        return false;
    }

    public static function canRestoreAny(): bool
    {
        return false;
    }

    public static function canForceDelete(Model $record): bool
    {
        return false;
    }

    public static function canForceDeleteAny(): bool
    {
        return false;
    }

    // ----- الواجهة ----------------------------------------------------------

    public static function form(Form $form): Form
    {
        // لا نموذج تحرير: المورد للقراءة، وصفحة العرض تستعمل infolist أدناه.
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label('التاريخ والوقت')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label('المستخدم')
                    ->searchable()
                    ->placeholder('مستخدم محذوف'),
                TextColumn::make('event')
                    ->label('الحدث')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => self::EVENT_LABELS[$state] ?? $state)
                    ->color(fn (string $state): string => self::eventColor($state)),
                TextColumn::make('subject_type')
                    ->label('النوع')
                    ->formatStateUsing(fn (?string $state): string => self::subjectLabel($state)),
                TextColumn::make('subject_id')
                    ->label('المعرّف')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('changed_fields')
                    ->label('الحقول المتغيّرة')
                    ->getStateUsing(fn (AdminActivity $record): string => self::changedFieldsText($record))
                    ->wrap()
                    ->limit(80),
                TextColumn::make('ip_address')
                    ->label('عنوان IP')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('user_id')
                    ->label('المستخدم')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('event')
                    ->label('الحدث')
                    ->options(self::EVENT_LABELS),
                SelectFilter::make('subject_type')
                    ->label('النوع')
                    // الأنواع الحاضرة فعلًا في السجل لا قائمة مخترعة (بند 1.1).
                    // استعلام واحد رخيص يستفيد من صدر الفهرس (subject_type, subject_id).
                    ->options(fn (): array => AdminActivity::query()
                        ->select('subject_type')
                        ->distinct()
                        ->orderBy('subject_type')
                        ->pluck('subject_type')
                        ->mapWithKeys(fn (string $type): array => [$type => self::subjectLabel($type)])
                        ->all()),
                Filter::make('created_at')
                    ->label('التاريخ')
                    ->form([
                        DatePicker::make('from')->label('من تاريخ'),
                        DatePicker::make('until')->label('إلى تاريخ'),
                    ])
                    // مدى على العمود الخام لا whereDate(): الأخيرة تلفّ العمود بـ
                    // DATE() فتُبطل فهرس created_at (بند 3.2).
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when(
                            $data['from'] ?? null,
                            fn (Builder $q, string $date): Builder => $q->where('created_at', '>=', Carbon::parse($date)->startOfDay()),
                        )
                        ->when(
                            $data['until'] ?? null,
                            fn (Builder $q, string $date): Builder => $q->where('created_at', '<=', Carbon::parse($date)->endOfDay()),
                        ))
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];

                        if (filled($data['from'] ?? null)) {
                            $indicators[] = 'من '.$data['from'];
                        }

                        if (filled($data['until'] ?? null)) {
                            $indicators[] = 'إلى '.$data['until'];
                        }

                        return $indicators;
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()->label('عرض'),
            ])
            // لا إجراءات جماعية: الحذف الجماعي لأثر تدقيق مرفوض من حيث المبدأ.
            ->bulkActions([]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Section::make('الحدث')
                ->schema([
                    TextEntry::make('created_at')->label('التاريخ والوقت')->dateTime('Y-m-d H:i:s'),
                    TextEntry::make('user.name')->label('المستخدم')->placeholder('مستخدم محذوف'),
                    TextEntry::make('user.email')->label('البريد')->placeholder('—'),
                    TextEntry::make('event')
                        ->label('نوع الحدث')
                        ->badge()
                        ->formatStateUsing(fn (string $state): string => self::EVENT_LABELS[$state] ?? $state)
                        ->color(fn (string $state): string => self::eventColor($state)),
                    TextEntry::make('subject_type')
                        ->label('السجل المتأثّر')
                        ->formatStateUsing(fn (?string $state): string => self::subjectLabel($state)),
                    TextEntry::make('subject_id')->label('معرّف السجل'),
                    TextEntry::make('ip_address')->label('عنوان IP')->placeholder('—'),
                ])
                ->columns(2),

            Section::make('تفاصيل التغيير')
                ->schema([
                    RepeatableEntry::make('diff')
                        ->label('')
                        ->getStateUsing(fn (AdminActivity $record): array => self::diffRows($record))
                        ->schema([
                            TextEntry::make('field')->label('الحقل'),
                            TextEntry::make('old')->label('القيمة السابقة')->placeholder('—'),
                            TextEntry::make('new')->label('القيمة الجديدة')->placeholder('—'),
                        ])
                        ->columns(3)
                        ->visible(fn (AdminActivity $record): bool => $record->changesArray() !== []),

                    TextEntry::make('empty_diff')
                        ->label('')
                        ->getStateUsing(fn (): string => 'لا تفاصيل حقول مسجَّلة لهذا الحدث.')
                        ->visible(fn (AdminActivity $record): bool => $record->changesArray() === []),
                ]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAdminActivities::route('/'),
            'view' => Pages\ViewAdminActivity::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        // تحميل مسبق للفاعل: عمود user.name في القائمة كان سينتج N+1 (بند 2.5).
        // العلاقة subject (morphTo) لا تُحمَّل عمدًا — أنواع مختلطة واستعلام لكل نوع،
        // والقائمة تعرض النوع والمعرّف نصًّا بلا أي استعلام.
        return parent::getEloquentQuery()->with('user');
    }

    // ----- مساعدات العرض ----------------------------------------------------

    /**
     * اسم عربي مقروء لنوع السجل. مبني على اسم الصنف المجرّد لا على استدعاء الصنف
     * نفسه، فلا ينكسر عرض سطر تاريخي إن حُذف موديل أو أُعيدت تسميته (بند 1.1).
     * الأنواع المسمّاة كلها موديلات متحقَّق من وجودها في app/Models.
     */
    public static function subjectLabel(?string $type): string
    {
        $base = $type === null ? '' : class_basename($type);

        return match ($base) {
            'Order' => 'طلب',
            'Book' => 'كتاب',
            'Coupon' => 'كوبون',
            'Setting' => 'إعداد',
            'PaymentMethod' => 'طريقة دفع',
            'User' => 'مستخدم',
            'Page' => 'صفحة',
            'Category' => 'قسم',
            'Publisher' => 'دار نشر',
            '' => '—',
            default => $base,
        };
    }

    protected static function eventColor(string $state): string
    {
        return match ($state) {
            AdminActivity::EVENT_CREATED => 'success',
            AdminActivity::EVENT_UPDATED => 'info',
            AdminActivity::EVENT_DELETED => 'danger',
            AdminActivity::EVENT_RESTORED => 'warning',
            default => 'gray',
        };
    }

    /**
     * أسماء الحقول المتغيّرة مفصولة بفاصلة عربية — ملخّص سطر واحد في القائمة.
     */
    protected static function changedFieldsText(AdminActivity $record): string
    {
        $fields = $record->changedFields();

        return $fields === [] ? '—' : implode('، ', $fields);
    }

    /**
     * صفوف الفرق للعرض: حقل / قبل / بعد. كل القيم تُحوَّل إلى نص هنا كي يعرضها
     * TextEntry كما هي (وهو يهرّبها تلقائيًا — لا {!! !!} إطلاقًا، بند 4.2).
     *
     * @return list<array{field: string, old: string, new: string}>
     */
    protected static function diffRows(AdminActivity $record): array
    {
        $rows = [];

        foreach ($record->changesArray() as $field => $pair) {
            $rows[] = [
                'field' => $field,
                'old' => self::valueText($pair['old']),
                'new' => self::valueText($pair['new']),
            ];
        }

        return $rows;
    }

    protected static function valueText(mixed $value): string
    {
        return match (true) {
            $value === null => '—',
            $value === true => 'نعم',
            $value === false => 'لا',
            is_int($value), is_float($value) => (string) $value,
            is_string($value) => $value === '' ? '(فارغ)' : $value,
            default => '—',
        };
    }
}
