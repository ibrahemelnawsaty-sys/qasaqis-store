<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Concerns\HasResourcePermissions;
use App\Filament\Resources\PaymentMethodResource\Pages;
use App\Models\PaymentMethod;
use App\Providers\Filament\AdminPanelProvider;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Enable/disable payment methods and edit their (non-secret) details
 * (group: الطلبات والدفع).
 *
 * Permission model — docs/04 §3.4 does NOT treat payment methods as generic CRUD;
 * it defines three distinct atomic permissions:
 *   - payments.methods.toggle          → enable/disable a method (admin, it)
 *   - payments.manual_accounts.manage  → transfer account details (super_admin, admin)
 *   - payments.settings                → sensitive gateway/API config (super_admin, it)
 *
 * The task memo said "payments.settings" for this resource, but per docs/04 that
 * permission is reserved for the sensitive API config and would wrongly lock the
 * admin role out of toggling manual/COD methods. Following the roles matrix (the
 * source of truth, constitution 1.1/10.5), the coarse resource gate is
 * payments.methods.toggle, and the sensitive sub-sections are gated separately in
 * the form. Methods are a fixed seeded set (cod/instapay/vodafone_cash/
 * bank_transfer/online_gateway), so create/delete are closed.
 */
class PaymentMethodResource extends Resource
{
    use HasResourcePermissions;

    protected static ?string $model = PaymentMethod::class;

    protected static ?string $navigationIcon = 'heroicon-o-credit-card';

    protected static ?string $navigationGroup = AdminPanelProvider::GROUP_ORDERS_PAYMENTS;

    protected static ?int $navigationSort = 20;

    protected static ?string $navigationLabel = 'طرق الدفع';

    protected static ?string $modelLabel = 'طريقة دفع';

    protected static ?string $pluralModelLabel = 'طرق الدفع';

    /** payment_methods.type enum (create_payment_methods_table). */
    public const TYPE_LABELS = [
        'cash_on_delivery' => 'الدفع عند الاستلام',
        'manual_transfer' => 'تحويل يدوي',
        'online_gateway' => 'بوابة أونلاين',
    ];

    public static function permissionPrefix(): string
    {
        return 'payments';
    }

    // Coarse gate = payments.methods.toggle (userCan concatenates prefix + action,
    // yielding "payments.methods.toggle" exactly as in docs/04 §3.4).
    public static function canViewAny(): bool
    {
        return static::userCan('methods.toggle');
    }

    public static function canView(Model $record): bool
    {
        return static::userCan('methods.toggle');
    }

    public static function canEdit(Model $record): bool
    {
        return static::userCan('methods.toggle');
    }

    public static function canCreate(): bool
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

    public static function form(Form $form): Form
    {
        $canManageAccounts = auth()->user()?->can('payments.manual_accounts.manage') === true;
        $canSettings = auth()->user()?->can('payments.settings') === true;

        return $form->schema([
            Section::make('الطريقة')
                ->schema([
                    // Identity of a seeded method — shown but never mutated.
                    TextInput::make('code')
                        ->label('الرمز')
                        ->disabled()
                        ->dehydrated(false),
                    TextInput::make('type')
                        ->label('النوع')
                        ->formatStateUsing(fn (?string $state): ?string => $state !== null ? (self::TYPE_LABELS[$state] ?? $state) : null)
                        ->disabled()
                        ->dehydrated(false),
                    TextInput::make('name')
                        ->label('الاسم')
                        ->required()
                        ->maxLength(80),
                    TextInput::make('sort_order')
                        ->label('الترتيب')
                        ->numeric()
                        ->default(0),
                    Toggle::make('is_enabled')
                        ->label('مفعّلة')
                        ->inline(false),
                    Toggle::make('requires_proof')
                        ->label('تتطلب إثبات دفع')
                        ->inline(false),
                    Textarea::make('instructions')
                        ->label('تعليمات للعميل')
                        ->maxLength(1000)
                        ->columnSpanFull(),
                ])
                ->columns(2),

            Section::make('بيانات حسابات التحويل اليدوي')
                ->description('أرقام المحافظ/الحسابات (غير سرّية) — صلاحية payments.manual_accounts.manage')
                ->schema([
                    // Non-secret wallet/IBAN pairs. Viewable to method managers, but
                    // editable only with payments.manual_accounts.manage; when disabled
                    // Filament does not dehydrate it, so existing values are preserved.
                    KeyValue::make('account_details')
                        ->label('بيانات الحساب')
                        ->keyLabel('المفتاح')
                        ->valueLabel('القيمة')
                        ->disabled(! $canManageAccounts),
                ]),

            Section::make('إعدادات البوابة الأونلاين (تقني حسّاس)')
                ->description('صلاحية payments.settings — المفاتيح السرّية تعيش في .env لا هنا')
                ->schema([
                    TextInput::make('gateway_provider')
                        ->label('مزوّد البوابة')
                        ->maxLength(40),
                    KeyValue::make('config')
                        ->label('إعدادات غير سرّية')
                        ->keyLabel('المفتاح')
                        ->valueLabel('القيمة'),
                ])
                ->columns(2)
                // Hidden entirely from users without payments.settings; hidden fields
                // are not dehydrated, so their DB values stay intact on save.
                ->visible($canSettings),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('name')
                    ->label('الاسم')
                    ->searchable(),
                TextColumn::make('code')
                    ->label('الرمز')
                    ->badge(),
                TextColumn::make('type')
                    ->label('النوع')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => self::TYPE_LABELS[$state] ?? $state),
                IconColumn::make('is_enabled')
                    ->label('مفعّلة')
                    ->boolean(),
                IconColumn::make('requires_proof')
                    ->label('تتطلب إثبات')
                    ->boolean(),
                TextColumn::make('sort_order')
                    ->label('الترتيب')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_enabled')->label('مفعّلة'),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->label('تعديل'),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPaymentMethods::route('/'),
            'edit' => Pages\EditPaymentMethod::route('/{record}/edit'),
        ];
    }
}
