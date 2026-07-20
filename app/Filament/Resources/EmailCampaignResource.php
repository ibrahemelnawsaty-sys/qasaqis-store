<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Concerns\HasResourcePermissions;
use App\Filament\Resources\EmailCampaignResource\Pages;
use App\Filament\Resources\EmailCampaignResource\RelationManagers\RecipientsRelationManager;
use App\Models\EmailCampaign;
use App\Providers\Filament\AdminPanelProvider;
use Filament\Forms\Form;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * سجل الحملات البريدية (مجموعة: التفاعل والدعم) — تدقيق «من أرسل ماذا لمن ومتى».
 *
 * **للقراءة فقط.** الحملات تُنشأ من صفحة «إرسال بريد للعملاء» عبر CampaignDispatcher،
 * لا من هنا؛ سجلّ يمكن تحريره/إنشاؤه يدويًا لا معنى له. تُغلق بوّابات الطفرة بإرجاع
 * false صريح لا بغياب الصلاحية وحده (super_admin يمرّ عبر Gate::before). الصلاحية
 * المستهلَكة `campaigns.view` موجودة في RolePermissionSeeder (بند 1.1).
 */
class EmailCampaignResource extends Resource
{
    use HasResourcePermissions;

    protected static ?string $model = EmailCampaign::class;

    protected static ?string $navigationIcon = 'heroicon-o-inbox-stack';

    protected static ?string $navigationGroup = AdminPanelProvider::GROUP_ENGAGEMENT_SUPPORT;

    protected static ?int $navigationSort = 20;

    protected static ?string $navigationLabel = 'سجل الحملات البريدية';

    protected static ?string $modelLabel = 'حملة';

    protected static ?string $pluralModelLabel = 'الحملات البريدية';

    public const STATUS_LABELS = [
        'draft' => 'مسودّة',
        'queued' => 'في الانتظار',
        'sending' => 'يُرسَل الآن',
        'sent' => 'اكتمل',
        'failed' => 'فشل جزئي',
    ];

    public static function permissionPrefix(): string
    {
        // يُترجَم عبر HasResourcePermissions إلى campaigns.view.
        return 'campaigns';
    }

    // ----- بوّابات الطفرة: مغلقة نهائيًا -------------------------------------

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

    // ----- الواجهة ----------------------------------------------------------

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label('التاريخ')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
                TextColumn::make('subject')
                    ->label('العنوان')
                    ->searchable()
                    ->limit(45)
                    ->wrap(),
                TextColumn::make('status')
                    ->label('الحالة')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => self::STATUS_LABELS[$state] ?? $state)
                    ->color(fn (string $state): string => self::statusColor($state)),
                TextColumn::make('total_recipients')
                    ->label('الإجمالي')
                    ->numeric()
                    ->alignCenter(),
                TextColumn::make('sent_count')
                    ->label('أُرسل')
                    ->numeric()
                    ->color('success')
                    ->alignCenter(),
                TextColumn::make('failed_count')
                    ->label('فشل')
                    ->numeric()
                    ->color(fn (EmailCampaign $r): string => $r->failed_count > 0 ? 'danger' : 'gray')
                    ->alignCenter(),
                TextColumn::make('creator.name')
                    ->label('المُرسِل')
                    ->placeholder('—')
                    ->searchable(),
                TextColumn::make('sent_at')
                    ->label('اكتمل في')
                    ->dateTime('Y-m-d H:i')
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('الحالة')
                    ->options(self::STATUS_LABELS),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()->label('عرض'),
            ])
            ->bulkActions([]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Section::make('الحملة')
                ->schema([
                    TextEntry::make('subject')->label('العنوان')->columnSpanFull(),
                    TextEntry::make('preheader')->label('نص المعاينة')->placeholder('—')->columnSpanFull(),
                    TextEntry::make('status')
                        ->label('الحالة')
                        ->badge()
                        ->formatStateUsing(fn (string $state): string => self::STATUS_LABELS[$state] ?? $state)
                        ->color(fn (string $state): string => self::statusColor($state)),
                    TextEntry::make('template_key')->label('القالب')->placeholder('مخصّص'),
                    TextEntry::make('creator.name')->label('المُرسِل')->placeholder('—'),
                    TextEntry::make('created_at')->label('أُنشئت')->dateTime('Y-m-d H:i'),
                    TextEntry::make('sent_at')->label('اكتملت')->dateTime('Y-m-d H:i')->placeholder('—'),
                ])
                ->columns(2),

            Section::make('الأرقام')
                ->schema([
                    TextEntry::make('total_recipients')->label('إجمالي المستلمين')->numeric(),
                    TextEntry::make('sent_count')->label('أُرسل بنجاح')->numeric()->color('success'),
                    TextEntry::make('failed_count')->label('فشل')->numeric()->color('danger'),
                ])
                ->columns(3),
        ]);
    }

    /**
     * @return array<int, class-string>
     */
    public static function getRelations(): array
    {
        return [
            RecipientsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEmailCampaigns::route('/'),
            'view' => Pages\ViewEmailCampaign::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        // تحميل مسبق للمُرسِل: عمود creator.name في القائمة كان سينتج N+1 (بند 2.5).
        return parent::getEloquentQuery()->with('creator');
    }

    protected static function statusColor(string $state): string
    {
        return match ($state) {
            'sent' => 'success',
            'sending' => 'info',
            'queued' => 'warning',
            'failed' => 'danger',
            default => 'gray',
        };
    }
}
