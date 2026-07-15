<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * Admin panel for «قصاقيص أطفال» on /admin.
 *
 * Conventions established here for every other resource agent (see the handoff
 * memo): the five navigation groups below are the ONLY groups. A Resource nests
 * under a group by matching its label string exactly via one of the
 * self::GROUP_* constants (do not invent new groups).
 *
 * RTL: direction is driven by the Arabic app locale. AppServiceProvider::boot()
 * runs App::setLocale('ar') on every request, so Filament's base layout renders
 * `dir="rtl"` from its bundled Arabic translations (constitution 6.2).
 */
class AdminPanelProvider extends PanelProvider
{
    /**
     * Canonical navigation-group labels — the single source of truth.
     *
     * Resources MUST reference these constants (e.g.
     * `protected static ?string $navigationGroup = AdminPanelProvider::GROUP_BOOKS_CONTENT;`)
     * so group membership always matches what is registered below.
     *
     * The labels are Arabic literals on purpose: PanelProvider::register() (which
     * calls panel()) runs during the provider REGISTER phase, before any boot()
     * has set the locale — so an eager __() here would resolve against the 'en'
     * default and leak the raw key. A literal renders correctly under any locale.
     */
    public const GROUP_BOOKS_CONTENT = 'الكتب والمحتوى';

    public const GROUP_ORDERS_PAYMENTS = 'الطلبات والدفع';

    public const GROUP_SITE_CMS = 'إدارة الموقع/CMS';

    public const GROUP_USERS_PERMISSIONS = 'المستخدمون والصلاحيات';

    public const GROUP_ENGAGEMENT_SUPPORT = 'التفاعل والدعم';

    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->brandName('قصاقيص أطفال')
            ->login()
            ->colors([
                // Brand primary — بنفسجي «قصاقيص أطفال».
                'primary' => Color::hex('#6E2FB0'),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                AccountWidget::class,
            ])
            ->navigationGroups([
                NavigationGroup::make(self::GROUP_BOOKS_CONTENT)
                    ->icon('heroicon-o-book-open'),
                NavigationGroup::make(self::GROUP_ORDERS_PAYMENTS)
                    ->icon('heroicon-o-shopping-bag'),
                NavigationGroup::make(self::GROUP_SITE_CMS)
                    ->icon('heroicon-o-globe-alt'),
                NavigationGroup::make(self::GROUP_USERS_PERMISSIONS)
                    ->icon('heroicon-o-users'),
                NavigationGroup::make(self::GROUP_ENGAGEMENT_SUPPORT)
                    ->icon('heroicon-o-chat-bubble-left-right'),
            ])
            ->sidebarCollapsibleOnDesktop()
            // جرس إشعارات الأدمن من قاعدة البيانات (طلب جديد/إثبات دفع) — M4.
            ->databaseNotifications()
            ->databaseNotificationsPolling('30s')
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
