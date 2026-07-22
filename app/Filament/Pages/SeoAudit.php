<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Providers\Filament\AdminPanelProvider;
use App\Services\Seo\SeoAuditor;
use App\Services\Seo\SeoFinding;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * لوحة «تدقيق SEO» — تفحص المحتوى المنشور تلقائيًا وتُبرز نواقص الظهور في جوجل
 * (وصف ناقص، صورة غلاف مفقودة، عنوان طويل، Search Console غير مربوط) مع رابط
 * تعديل مباشر لكل عنصر. نظير تحليل Yoast لكن على مستوى الموقع كله.
 *
 * الأمان: عرض فقط، محروس بـ seo.view (كبقية أدوات SEO). لا يعدّل شيئًا؛ الروابط
 * تنقل لمورد الكيان حيث تُطبَّق صلاحياته الخاصة.
 */
class SeoAudit extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationGroup = AdminPanelProvider::GROUP_SITE_CMS;

    protected static ?int $navigationSort = 9;

    protected static ?string $navigationLabel = 'تدقيق SEO';

    protected static ?string $title = 'تدقيق SEO التلقائي';

    protected static string $view = 'filament.pages.seo-audit';

    /** مفتاح تخزين ملخّص الشارة (يُحدَّث كل ربع ساعة أو عند إعادة الفحص يدويًا). */
    private const BADGE_CACHE_KEY = 'seo.audit.summary';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('seo.view');
    }

    /** شارة تنقّل بعدد النواقص — تنبيه بصري للمالك. مخزّنة كي لا تُفحَص كل طلب. */
    public static function getNavigationBadge(): ?string
    {
        $total = static::cachedSummary()['total'];

        return $total > 0 ? (string) $total : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return static::cachedSummary()['danger'] > 0 ? 'danger' : 'warning';
    }

    /**
     * @return array{danger:int, warning:int, info:int, total:int}
     */
    private static function cachedSummary(): array
    {
        return Cache::remember(
            self::BADGE_CACHE_KEY,
            now()->addMinutes(15),
            fn (): array => app(SeoAuditor::class)->summarize(),
        );
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
    }

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('rescan')
                ->label('إعادة الفحص')
                ->icon('heroicon-o-arrow-path')
                ->action(function (): void {
                    Cache::forget(self::BADGE_CACHE_KEY);
                    Notification::make()->title('أُعيد الفحص.')->success()->send();
                }),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        /** @var Collection<int, SeoFinding> $findings */
        $findings = app(SeoAuditor::class)->run();

        // حدّث خزينة الشارة بنتيجة هذا الفحص الطازج.
        $summary = [
            'danger' => $findings->where('severity', SeoFinding::DANGER)->count(),
            'warning' => $findings->where('severity', SeoFinding::WARNING)->count(),
            'info' => $findings->where('severity', SeoFinding::INFO)->count(),
            'total' => $findings->count(),
        ];
        Cache::put(self::BADGE_CACHE_KEY, $summary, now()->addMinutes(15));

        return [
            'grouped' => $findings->groupBy('group'),
            'summary' => $summary,
        ];
    }
}
