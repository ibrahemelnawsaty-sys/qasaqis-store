<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Book;
use App\Models\Category;
use App\Models\Order;
use App\Models\Setting;
use App\Models\User;
use App\Enums\PatternSurface;
use App\Observers\BookObserver;
use App\Observers\OrderObserver;
use App\Services\Cms\BackgroundPatternService;
use App\Services\Cms\PopupService;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Arabic RTL is the storefront default (config/app.php may ship 'en').
        // Also drives the Filament admin panel's dir="rtl" (constitution 6.2).
        App::setLocale('ar');

        // تثبيت أصل الروابط المولَّدة على دومين SEO في الإنتاج. بدونه يبني Laravel
        // الروابط من ترويسة Host الواردة، فتُصدر نسخة www وسومًا canonical تشير
        // إلى نفسها — أي نسختان كاملتان متنافستان في الفهرس. إعادة التوجيه 301 في
        // public/.htaccess تعالج الطلب، وهذا يعالج ما نولّده نحن (canonical/sitemap/OG).
        //
        // ‏forceRootUrl يحكم **كل** ما يولّده asset()/url()، بما فيه روابط أصول Vite.
        // فقيمة فيها خطأ مطبعي أو مسار فرعي لا تُنتج sitemap معطوبًا فحسب، بل موقعًا
        // كاملًا بلا CSS ولا JS لكل زائر — وسببه يبدو غير مرتبط بما غُيّر. لذلك نتحقّق
        // أن القيمة رابط مطلق سليم ذو مضيف قبل فرضها، ونتركها كما هي إن لم تكن.
        if (App::environment('production')) {
            $siteUrl = (string) config('seo.site_url');

            // المسار: null بلا شرطة، و'/' معها — كلاهما جذر مقبول.
            if (filter_var($siteUrl, FILTER_VALIDATE_URL) !== false
                && filled(parse_url($siteUrl, PHP_URL_HOST))
                && in_array(parse_url($siteUrl, PHP_URL_PATH), [null, '', '/'], true)) {
                URL::forceRootUrl($siteUrl);
            }

            URL::forceScheme('https');
        }

        // Super admin bypasses every ability check (docs/04 §2, §7.2). Returning
        // null (not false) for everyone else lets spatie's own Gate::before and
        // the normal policy checks decide. Supports multiple super_admin accounts.
        Gate::before(static fn (User $user): ?bool => $user->hasRole('super_admin') ? true : null);

        // Index admin-created/edited books for the Arabic search engine (0.9):
        // rebuilds title_normalized + search_index on every save.
        Book::observe(BookObserver::class);

        // يعيد مخزون الطلب عند الانتقال إلى حالة نهائية غير منفّذة (M2).
        Order::observe(OrderObserver::class);

        // Shared data for every storefront view (layout, partials, and the page
        // content section alike). Computed once per request. Wrapped in rescue()
        // so views still render before the tables are migrated/seeded (empty data).
        View::share('navCategories', $this->navCategories());
        View::share('storeSettings', $this->storeSettings());

        // أعلام التحليلات العامة فقط (M6) — بلا أسرار الخادم (api_secret/capi_token).
        View::share('analytics', [
            'enabled' => (bool) config('analytics.enabled'),
            'ga4_id' => config('analytics.ga4.measurement_id'),
            'meta_pixel_id' => config('analytics.meta.pixel_id'),
            'currency' => config('analytics.currency', 'EGP'),
        ]);

        // Active CMS pop-up for the current request (constitution 0.8). Bound to
        // the storefront layout only (not View::share) so the query is skipped on
        // JSON/AJAX endpoints (search suggest, cart update) that never render it.
        // Server-side gating (is_active + schedule + page targeting) lives in the
        // service; device/trigger/frequency are handled client-side in the partial.
        View::composer('layouts.app', static function (ViewContract $view): void {
            $view->with('activePopup', app(PopupService::class)->forRequest(request()));

            // نقش خلفية الصفحة الحالية (CMS، الدستور 0.8). يُحلّ وقت العرض لا في
            // boot() لأن المسار لم يُطابَق بعد حينها. القالب يقرؤه كقيمة افتراضية
            // لـ @yield('body_class')، فتظل أي صفحة قادرة على تجاوزه صراحةً.
            $surface = self::surfaceForRoute(request()->route()?->getName());

            $view->with('bodyPattern', $surface === null
                ? ''
                : app(BackgroundPatternService::class)->cssClass($surface));
        });

        // خريطة نقوش أقسام الرئيسية — تُحسب مرة واحدة للقالب بلا منطق داخل Blade.
        View::composer('home', static function (ViewContract $view): void {
            $view->with('sectionPatterns', app(BackgroundPatternService::class)->sectionClasses());
        });

        $this->registerBackupSafeguards();
    }

    /**
     * يربط اسم المسار بسطح النقش المقابل. الأسماء منقولة حرفيًا من routes/web.php
     * ولم تُخمَّن (الدستور 1.1). أي مسار غير مذكور (صفحات الخطأ مثلًا) يعود بلا
     * نقش — وهو السلوك المقصود لا إغفال.
     *
     * ملاحظة: pages.show يعود بـ PageStatic كافتراضي فقط؛ الصفحة نفسها قد
     * تتجاوزه بعمود background_pattern عبر Page::patternClass().
     */
    protected static function surfaceForRoute(?string $routeName): ?PatternSurface
    {
        return match ($routeName) {
            'home' => PatternSurface::PageHome,
            'books.index', 'categories.show', 'series.show',
            'search', 'search.index' => PatternSurface::PageCatalog,
            'books.show' => PatternSurface::PageBook,
            'blog.index', 'blog.show' => PatternSurface::PageBlog,
            'pages.show' => PatternSurface::PageStatic,
            'cart.show' => PatternSurface::PageCart,
            'checkout.show' => PatternSurface::PageCheckout,
            'orders.payment' => PatternSurface::PageOrderPayment,
            'orders.track.show', 'orders.track.lookup' => PatternSurface::PageOrderTrack,
            'orders.thankyou' => PatternSurface::PageThankYou,
            default => null,
        };
    }

    /**
     * ضمانات النسخ الاحتياطي (M1):
     *  1) حارس تشفير: يمنع رفع أرشيف غير مشفّر يحوي بيانات العملاء وإثباتات
     *     الدفع (PII) إلى الوجهة الخارجية عند نسيان BACKUP_ARCHIVE_PASSWORD في
     *     الإنتاج — يفشل backup:run بصوت مسموع بدل النجاح الصامت (الدستور 3.4).
     *  2) قناة إنذار مستقلة عن SMTP: يحوّل أحداث فشل/اعتلال النسخ إلى Sentry،
     *     لأن إشعارات spatie البريدية قد تصمت (MAIL=log أو مستقبِل غير مضبوط).
     *     تُسجَّل المستمعات بأسماء أصناف نصية فلا تعتمد على تثبيت الحزمة وقت
     *     الاختبار (تُنفَّذ فقط حين يقع الحدث في الإنتاج).
     */
    protected function registerBackupSafeguards(): void
    {
        Event::listen(CommandStarting::class, static function (CommandStarting $event): void {
            if ($event->command === 'backup:run'
                && App::environment('production')
                && blank(config('backup.backup.password'))) {
                throw new RuntimeException(
                    'BACKUP_ARCHIVE_PASSWORD مطلوبة لتشفير النسخ الاحتياطية في الإنتاج — أُلغي backup:run.'
                );
            }
        });

        foreach ([
            'Spatie\Backup\Events\BackupHasFailed',
            'Spatie\Backup\Events\UnhealthyBackupWasFound',
            'Spatie\Backup\Events\CleanupHasFailed',
        ] as $failureEvent) {
            Event::listen($failureEvent, static function (object $event): void {
                if (! function_exists('Sentry\captureMessage')) {
                    return;
                }

                $detail = isset($event->exception) ? ' — '.$event->exception->getMessage() : '';

                \Sentry\captureMessage('[backup] '.$event::class.$detail);
            });
        }
    }

    /**
     * The six categories with published book counts, for the header strip & footer.
     * All categories are kept, even the currently empty ones (constitution 0.3).
     */
    protected function navCategories()
    {
        return rescue(
            fn () => Category::query()
                ->where('is_active', true)
                ->whereNull('parent_id')
                ->orderBy('sort_order')
                ->withCount(['books as books_count' => function (Builder $q): void {
                    $q->where('is_published', true);
                }])
                ->get(),
            collect(),
            report: false,
        );
    }

    /**
     * Store-level settings (WhatsApp number, socials) editable from the admin CMS.
     * No secrets here — only public contact/display values.
     *
     * @return array<string, string>
     */
    protected function storeSettings(): array
    {
        // Primary source is the seeded `whatsapp_number` setting (DB, CMS-editable).
        // Fallback comes from config (STORE_WHATSAPP_NUMBER) so it works after
        // config:cache — never from env() at runtime, which returns null then.
        $defaults = [
            'whatsapp_number' => (string) config('services.store.whatsapp', ''),
            // هوية ونصوص قابلة للتحرير من لوحة الإعدادات (تقرؤها الرئيسية/الفوتر).
            'store_name' => '',
            'tagline' => '',
            'hero_title' => '',
            'hero_subtitle' => '',
            'contact_phone' => '',
            'contact_email' => '',
            'contact_address' => '',
            'store_maps_url' => '',
            'shipping_note' => '',
            // روابط السوشيال (المفاتيح الجديدة الموحّدة social_*).
            'social_facebook' => '',
            'social_instagram' => '',
            'social_tiktok' => '',
            'social_youtube' => '',
            'social_twitter' => '',
            'social_snapchat' => '',
            'social_telegram' => '',
        ];

        $fromDb = rescue(
            fn () => Setting::query()
                ->whereIn('key', array_keys($defaults))
                ->pluck('value', 'key')
                ->toArray(),
            [],
            report: false,
        );

        $settings = [];
        foreach ($defaults as $key => $default) {
            $settings[$key] = (string) ($fromDb[$key] ?? $default);
        }

        return $settings;
    }
}
