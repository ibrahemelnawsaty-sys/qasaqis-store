<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Concerns\HasResourcePermissions;
use App\Filament\Resources\OrderResource\Pages;
use App\Models\Order;
use App\Providers\Filament\AdminPanelProvider;
use Filament\Forms\Form;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * Orders & manual-payment review for «قصص أطفال» (group: الطلبات والدفع).
 *
 * Read-mostly by design: orders are created by customers at checkout, never in
 * the panel. All state changes go through gated header actions on the View page
 * (proof review / status / shipping) — see OrderResource\Pages\ViewOrder.
 *
 * Coarse permission gate via HasResourcePermissions → orders.view. The finer
 * actions (payment_proof.review, orders.update_status, orders.ship) are enforced
 * server-side inside ViewOrder (constitution 4.4 / anti-pattern 13).
 *
 * All enum values below are copied verbatim from the migrations (orders /
 * payments / payment_proofs) — never invented (constitution 1.1).
 */
class OrderResource extends Resource
{
    use HasResourcePermissions;

    protected static ?string $model = Order::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';

    protected static ?string $navigationGroup = AdminPanelProvider::GROUP_ORDERS_PAYMENTS;

    protected static ?int $navigationSort = 10;

    protected static ?string $navigationLabel = 'الطلبات';

    protected static ?string $modelLabel = 'طلب';

    protected static ?string $pluralModelLabel = 'الطلبات';

    /** orders.status enum (create_orders_table). */
    public const STATUS_LABELS = [
        'pending' => 'قيد الانتظار',
        'confirmed' => 'مؤكَّد',
        'processing' => 'قيد التجهيز',
        'shipped' => 'تم الشحن',
        'delivered' => 'تم التسليم',
        'completed' => 'مكتمل',
        'cancelled' => 'ملغى',
        'refused' => 'مرفوض الاستلام',
        'refunded' => 'مُسترد',
    ];

    /** orders.payment_status enum (create_orders_table). */
    public const PAYMENT_STATUS_LABELS = [
        'unpaid' => 'غير مدفوع',
        'pending_review' => 'قيد مراجعة الإثبات',
        'partially_paid' => 'مدفوع جزئيًا',
        'paid' => 'مدفوع',
        'refunded' => 'مُسترد',
        'failed' => 'فشل',
    ];

    /** orders.payment_method enum (create_orders_table). */
    public const PAYMENT_METHOD_LABELS = [
        'cod' => 'الدفع عند الاستلام',
        'instapay' => 'إنستاباي',
        'vodafone_cash' => 'فودافون كاش',
        'bank_transfer' => 'تحويل بنكي',
        'online_gateway' => 'بوابة أونلاين',
    ];

    /** payment_proofs.review_status enum (create_payment_proofs_table). */
    public const REVIEW_STATUS_LABELS = [
        'pending_review' => 'قيد المراجعة',
        'approved' => 'مقبول',
        'rejected' => 'مرفوض',
    ];

    public static function permissionPrefix(): string
    {
        return 'orders';
    }

    // No orders.create / orders.update / orders.delete atomic permission exists in
    // docs/04 — orders are never authored, edited as records, or hard-deleted from
    // the panel. These coarse gates stay closed; mutations run through the gated
    // View-page actions instead.
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

    public static function getNavigationBadge(): ?string
    {
        // Manual-payment review queue size (docs/04 §6.2). Cheap: payment_status
        // is indexed. Only rendered for users who can see the nav item (orders.view).
        $count = static::getModel()::query()->where('payment_status', 'pending_review')->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function form(Form $form): Form
    {
        // Orders are read-only in the panel; the View page renders an infolist and
        // the header actions handle every mutation. No editable form is exposed.
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('order_number')
                    ->label('رقم الطلب')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('customer_name')
                    ->label('العميل')
                    ->searchable(),
                TextColumn::make('customer_phone')
                    ->label('الهاتف')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('grand_total')
                    ->label('الإجمالي')
                    ->money('EGP')
                    ->sortable(),
                TextColumn::make('status')
                    ->label('حالة الطلب')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => self::STATUS_LABELS[$state] ?? $state)
                    ->color(fn (string $state): string => self::statusColor($state)),
                TextColumn::make('payment_status')
                    ->label('حالة الدفع')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => self::PAYMENT_STATUS_LABELS[$state] ?? $state)
                    ->color(fn (string $state): string => self::paymentStatusColor($state)),
                TextColumn::make('payment_method')
                    ->label('طريقة الدفع')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => self::PAYMENT_METHOD_LABELS[$state] ?? $state),
                TextColumn::make('created_at')
                    ->label('تاريخ الإنشاء')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('حالة الطلب')
                    ->options(self::STATUS_LABELS),
                SelectFilter::make('payment_status')
                    ->label('حالة الدفع')
                    ->options(self::PAYMENT_STATUS_LABELS),
                SelectFilter::make('payment_method')
                    ->label('طريقة الدفع')
                    ->options(self::PAYMENT_METHOD_LABELS),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()->label('عرض'),
            ])
            ->bulkActions([]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Section::make('بيانات الطلب')
                ->schema([
                    TextEntry::make('order_number')->label('رقم الطلب'),
                    TextEntry::make('status')
                        ->label('حالة الطلب')
                        ->badge()
                        ->formatStateUsing(fn (string $state): string => self::STATUS_LABELS[$state] ?? $state)
                        ->color(fn (string $state): string => self::statusColor($state)),
                    TextEntry::make('payment_status')
                        ->label('حالة الدفع')
                        ->badge()
                        ->formatStateUsing(fn (string $state): string => self::PAYMENT_STATUS_LABELS[$state] ?? $state)
                        ->color(fn (string $state): string => self::paymentStatusColor($state)),
                    TextEntry::make('payment_method')
                        ->label('طريقة الدفع')
                        ->formatStateUsing(fn (string $state): string => self::PAYMENT_METHOD_LABELS[$state] ?? $state),
                    TextEntry::make('subtotal')->label('المجموع الفرعي')->money('EGP'),
                    TextEntry::make('discount_total')->label('الخصم')->money('EGP'),
                    TextEntry::make('shipping_total')->label('الشحن')->money('EGP'),
                    TextEntry::make('grand_total')->label('الإجمالي')->money('EGP')->weight('bold'),
                    TextEntry::make('coupon_code')->label('كود الكوبون')->placeholder('—'),
                    TextEntry::make('created_at')->label('تاريخ الإنشاء')->dateTime('Y-m-d H:i'),
                ])
                ->columns(2),

            Section::make('بيانات العميل والشحن')
                ->schema([
                    TextEntry::make('customer_name')->label('الاسم'),
                    TextEntry::make('customer_phone')->label('الهاتف'),
                    TextEntry::make('customer_phone_alt')->label('هاتف بديل')->placeholder('—'),
                    TextEntry::make('customer_email')->label('البريد')->placeholder('—'),
                    TextEntry::make('governorate')->label('المحافظة'),
                    TextEntry::make('city')->label('المدينة')->placeholder('—'),
                    TextEntry::make('address_line')->label('العنوان')->columnSpanFull(),
                    TextEntry::make('address_notes')->label('ملاحظات العنوان')->placeholder('—')->columnSpanFull(),
                    TextEntry::make('shipping_company')->label('شركة الشحن')->placeholder('—'),
                    TextEntry::make('tracking_number')->label('رقم التتبّع')->placeholder('—'),
                    TextEntry::make('whatsapp_confirmed_at')->label('تأكيد واتساب')->dateTime('Y-m-d H:i')->placeholder('—'),
                    TextEntry::make('customer_note')->label('ملاحظة العميل')->placeholder('—')->columnSpanFull(),
                    TextEntry::make('admin_note')->label('ملاحظة إدارية')->placeholder('—')->columnSpanFull(),
                ])
                ->columns(2),

            Section::make('بنود الطلب')
                ->schema([
                    RepeatableEntry::make('items')
                        ->label('')
                        ->schema([
                            TextEntry::make('book_title')->label('الكتاب'),
                            TextEntry::make('unit_price')->label('سعر الوحدة')->money('EGP'),
                            TextEntry::make('quantity')->label('الكمية'),
                            TextEntry::make('line_total')->label('الإجمالي')->money('EGP'),
                        ])
                        ->columns(4),
                ]),

            Section::make('إثباتات الدفع اليدوي')
                ->schema([
                    RepeatableEntry::make('paymentProofs')
                        ->label('')
                        ->schema([
                            TextEntry::make('method_code')
                                ->label('الطريقة')
                                ->formatStateUsing(fn (string $state): string => self::PAYMENT_METHOD_LABELS[$state] ?? $state),
                            TextEntry::make('amount')->label('المبلغ')->money('EGP')->placeholder('—'),
                            TextEntry::make('sender_reference')->label('مرجع المُرسِل')->placeholder('—'),
                            TextEntry::make('review_status')
                                ->label('حالة المراجعة')
                                ->badge()
                                ->formatStateUsing(fn (string $state): string => self::REVIEW_STATUS_LABELS[$state] ?? $state)
                                ->color(fn (string $state): string => match ($state) {
                                    'approved' => 'success',
                                    'rejected' => 'danger',
                                    default => 'warning',
                                }),
                            // Proofs live on the PRIVATE 'local' disk (storage/app/private).
                            // config/filesystems.php sets 'serve' => true, which lets
                            // Laravel 11 mint a short-lived signed URL rather than a public
                            // link (constitution 4.5 / docs 6.3). Works for jpg/png/pdf.
                            TextEntry::make('file_path')
                                ->label('الملف')
                                ->formatStateUsing(fn (): string => 'عرض إثبات الدفع')
                                ->url(fn (Model $record): ?string => self::proofTemporaryUrl($record->file_path))
                                ->openUrlInNewTab()
                                ->color('primary'),
                            TextEntry::make('review_note')->label('ملاحظة المراجعة')->placeholder('—'),
                            TextEntry::make('reviewer.name')->label('راجعها')->placeholder('—'),
                            TextEntry::make('reviewed_at')->label('تاريخ المراجعة')->dateTime('Y-m-d H:i')->placeholder('—'),
                        ])
                        ->columns(3),
                ])
                ->visible(fn (Order $record): bool => $record->paymentProofs()->exists()),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrders::route('/'),
            'view' => Pages\ViewOrder::route('/{record}'),
        ];
    }

    /**
     * Short-lived signed URL for a manual-payment proof on the PRIVATE 'local'
     * disk (constitution 4.5 / docs 6.3). Returns null (a plain, non-linked label)
     * if the path is empty or the disk cannot mint a temporary URL — never lets a
     * storage misconfiguration 500 the whole order view.
     */
    protected static function proofTemporaryUrl(?string $path): ?string
    {
        if (blank($path)) {
            return null;
        }

        try {
            return Storage::disk('local')->temporaryUrl($path, now()->addMinutes(10));
        } catch (\Throwable) {
            return null;
        }
    }

    protected static function statusColor(string $state): string
    {
        return match ($state) {
            'pending' => 'warning',
            'confirmed', 'processing' => 'info',
            'shipped' => 'primary',
            'delivered', 'completed' => 'success',
            'cancelled', 'refused' => 'danger',
            default => 'gray',
        };
    }

    protected static function paymentStatusColor(string $state): string
    {
        return match ($state) {
            'unpaid', 'failed' => 'danger',
            'pending_review' => 'warning',
            'partially_paid' => 'info',
            'paid' => 'success',
            default => 'gray',
        };
    }
}
