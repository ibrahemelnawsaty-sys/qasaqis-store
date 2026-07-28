<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Filament\Pages\SeoAudit;
use App\Models\User;
use App\Services\Seo\SeoAuditor;
use App\Services\Seo\SeoFinding;
use Filament\Notifications\Notification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * يجعل مدقّق SEO «يعمل لوحده»: يُشغَّل مجدولًا (routes/console.php) فيفحص المحتوى المنشور،
 * يُنعش خزينة شارة اللوحة كي يرى المالك العدد محدَّثًا دون فتح اللوحة، ويُرسل تنبيه أدمن
 * في جرس Filament فقط عند **ازدياد** المشاكل الحرِجة عن آخر مرّة نبّهنا فيها (لا إزعاج متكرّر).
 *
 * لماذا التنبيه عند الازدياد فقط: كتاب يُنشر بلا وصف/غلاف كان يمرّ صامتًا حتى يفتح أحدهم
 * اللوحة؛ الآن يصل تنبيه فورًا. وإصلاح المشاكل يخفض العتبة تلقائيًّا فينبّه ثانيةً عند أي جديد.
 */
class SeoAuditCommand extends Command
{
    protected $signature = 'seo:audit {--notify : أرسل تنبيه أدمن عند ظهور مشاكل حرِجة جديدة}';

    protected $description = 'فحص SEO تلقائي للموقع: يُنعش شارة اللوحة وينبّه الأدمن عند مشاكل حرِجة جديدة.';

    /** آخر عدد «حرِج» نبّهنا عنه — نُنبّه فقط حين يتجاوزه العدد الحالي. */
    private const BASELINE_KEY = 'seo.audit.notified_danger';

    public function handle(SeoAuditor $auditor): int
    {
        $findings = $auditor->run();

        $summary = [
            'danger' => $findings->where('severity', SeoFinding::DANGER)->count(),
            'warning' => $findings->where('severity', SeoFinding::WARNING)->count(),
            'info' => $findings->where('severity', SeoFinding::INFO)->count(),
            'total' => $findings->count(),
        ];

        // يُنعش خزينة الشارة فورًا (نفس مفتاح اللوحة) فتعكس النتيجة دون انتظار فتح اللوحة.
        Cache::put(SeoAudit::BADGE_CACHE_KEY, $summary, now()->addMinutes(15));

        $this->line(sprintf(
            'تدقيق SEO: %d حرِج · %d تحذير · %d معلومة (%d إجمالي).',
            $summary['danger'], $summary['warning'], $summary['info'], $summary['total'],
        ));

        // مسار التنبيه (الجدولة تمرّر --notify): يُنبّه عند ازدياد المشاكل الحرِجة عن آخر
        // عتبة، ويُحدّث العتبة **هنا فقط** — فتشغيل يدوي بلا --notify (لإنعاش الشارة/العرض)
        // لا يمسّ العتبة ولا يكتم التنبيه المجدول التالي عن مشاكل قائمة.
        if ($this->option('notify')) {
            $baseline = (int) Cache::get(self::BASELINE_KEY, 0);

            if ($summary['danger'] > $baseline) {
                $this->alertAdmins($summary);
                Log::warning('SEO audit: مشاكل حرِجة جديدة', $summary);
                $this->warn(sprintf('أُرسل تنبيه أدمن (%d حرِج، كان %d).', $summary['danger'], $baseline));
            }

            // إصلاح المشاكل يخفض العتبة فيُنبَّه ثانيةً عند أي جديد لاحق.
            Cache::forever(self::BASELINE_KEY, $summary['danger']);
        }

        return self::SUCCESS;
    }

    /**
     * تنبيه في جرس Filament لكل أدمن نشِط ذي دور مسؤول عن الظهور (super_admin/admin/marketing).
     * مغلّف بـ rescue: فشل الإشعار (جدول/قناة) لا يُسقط الفحص المجدول ولا يوقف الشارة.
     *
     * @param  array{danger:int, warning:int, info:int, total:int}  $summary
     */
    private function alertAdmins(array $summary): void
    {
        rescue(function () use ($summary): void {
            $admins = User::query()
                ->where('is_active', true)
                ->whereHas('roles', fn ($q) => $q->whereIn('name', ['super_admin', 'admin', 'marketing']))
                ->get();

            if ($admins->isEmpty()) {
                return;
            }

            Notification::make()
                ->title('تدقيق SEO: مشاكل تحتاج انتباهك')
                ->body(sprintf(
                    '%d مشكلة حرِجة و%d تحذير في محتوى الموقع (وصف/غلاف ناقص، عناوين مكرّرة…). افتح لوحة «تدقيق SEO» لمراجعتها.',
                    $summary['danger'], $summary['warning'],
                ))
                ->danger()
                ->icon('heroicon-o-shield-exclamation')
                ->sendToDatabase($admins);
        }, report: false);
    }
}
