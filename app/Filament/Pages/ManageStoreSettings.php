<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Models\Setting;
use App\Providers\Filament\AdminPanelProvider;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;

/**
 * Store settings (constitution 0.8 / doc 04 §4): store identity, contact, and
 * payment-method toggles — all CMS-managed key/value rows in the `settings` table.
 *
 * SECURITY (constitution 4.3 / doc 04 §7.5): this page NEVER reads or writes any
 * secret. Only the explicit non-secret whitelist below is handled, and every write
 * forces is_encrypted=false. Payment gateway API keys live in .env / encrypted
 * settings and are out of scope here — no secret is displayed or persisted.
 *
 * Authorization: page access requires settings.view; saving requires
 * settings.general.edit (doc 04 §3.6). There is no "settings.manage" permission in
 * the catalogue, so the real atomic permissions are used instead of inventing one
 * (constitution 1.1).
 */
class ManageStoreSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationGroup = AdminPanelProvider::GROUP_SITE_CMS;

    protected static ?int $navigationSort = 30;

    protected static ?string $navigationLabel = 'إعدادات المتجر';

    protected static ?string $title = 'إعدادات المتجر';

    protected static string $view = 'filament.pages.manage-store-settings';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    /**
     * The ONLY settings this page manages. Every entry is non-secret. Shape:
     * key => [group, type]. Booleans are persisted as '1'/'0' strings with
     * type=boolean, matching SettingSeeder.
     *
     * @var array<string, array{0: string, 1: string}>
     */
    private const MANAGED = [
        'site_name' => ['general', 'string'],
        'site_tagline' => ['general', 'string'],
        'currency' => ['general', 'string'],
        'whatsapp_number' => ['contact', 'string'],
        'contact_email' => ['contact', 'string'],
        'online_payment_enabled' => ['payment', 'boolean'],
        'manual_instapay_enabled' => ['payment', 'boolean'],
        'manual_vodafone_enabled' => ['payment', 'boolean'],
        'manual_bank_enabled' => ['payment', 'boolean'],
        'cod_enabled' => ['payment', 'boolean'],
    ];

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('settings.view');
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        // Load current non-secret values, never touching encrypted rows.
        $stored = Setting::query()
            ->whereIn('key', array_keys(self::MANAGED))
            ->where('is_encrypted', false)
            ->pluck('value', 'key');

        $state = [];
        foreach (self::MANAGED as $key => [$group, $type]) {
            $raw = $stored[$key] ?? null;
            $state[$key] = $type === 'boolean' ? ($raw === '1') : $raw;
        }

        $this->form->fill($state);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('هوية المتجر')
                    ->schema([
                        Forms\Components\TextInput::make('site_name')
                            ->label('اسم المتجر')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('site_tagline')
                            ->label('الوصف المختصر')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('currency')
                            ->label('العملة')
                            ->maxLength(10),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('التواصل')
                    ->schema([
                        Forms\Components\TextInput::make('whatsapp_number')
                            ->label('رقم واتساب')
                            ->tel()
                            ->maxLength(20)
                            ->helperText('بصيغة دولية بدون + (مثل 2010xxxxxxxx).'),
                        Forms\Components\TextInput::make('contact_email')
                            ->label('البريد الإلكتروني للتواصل')
                            ->email()
                            ->maxLength(255),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('طرق الدفع')
                    ->description('تفعيل/إيقاف طرق الدفع الظاهرة للعميل. مفاتيح بوابات الدفع السرّية تُدار خارج هذه الصفحة.')
                    ->schema([
                        Forms\Components\Toggle::make('online_payment_enabled')
                            ->label('الدفع الأونلاين (بوابة)'),
                        Forms\Components\Toggle::make('manual_instapay_enabled')
                            ->label('إنستاباي (يدوي)'),
                        Forms\Components\Toggle::make('manual_vodafone_enabled')
                            ->label('فودافون كاش (يدوي)'),
                        Forms\Components\Toggle::make('manual_bank_enabled')
                            ->label('تحويل بنكي (يدوي)'),
                        Forms\Components\Toggle::make('cod_enabled')
                            ->label('الدفع عند الاستلام (COD)'),
                    ])
                    ->columns(2),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        // Server-side enforcement (constitution 4.4): saving is a privileged action.
        abort_unless((bool) auth()->user()?->can('settings.general.edit'), 403);

        $state = $this->form->getState();

        DB::transaction(function () use ($state): void {
            foreach (self::MANAGED as $key => [$group, $type]) {
                $value = $state[$key] ?? null;

                if ($type === 'boolean') {
                    $value = ! empty($value) ? '1' : '0';
                } elseif ($value !== null) {
                    $value = (string) $value;
                }

                Setting::updateOrCreate(
                    ['key' => $key],
                    [
                        'group' => $group,
                        'value' => $value,
                        'type' => $type,
                        'is_encrypted' => false,
                        'autoload' => true,
                    ],
                );
            }
        });

        Notification::make()
            ->title('تم حفظ الإعدادات')
            ->success()
            ->send();
    }

    /**
     * @return array<int, \Filament\Actions\Action>
     */
    protected function getFormActions(): array
    {
        return [
            \Filament\Actions\Action::make('save')
                ->label('حفظ')
                ->submit('save')
                ->visible(fn (): bool => (bool) auth()->user()?->can('settings.general.edit')),
        ];
    }
}
