<?php

declare(strict_types=1);

namespace App\Services\Cms;

use App\Enums\BackgroundPattern;
use App\Enums\PatternSurface;
use Illuminate\Support\Facades\Cache;

/**
 * يحلّ نقش الخلفية لكل سطح (صفحة أو قسم) من اختيارات الأدمن في جدول settings،
 * مع الرجوع للافتراضي المعرَّف في PatternSurface حين لا اختيار.
 *
 * الرئيسية وحدها تسأل عن 12 قسمًا في الطلب الواحد، لذا تُقرأ كل الصفوف مرة
 * واحدة وتُخزَّن مؤقتًا (الدستور 5.4) بدل استعلام لكل سطح.
 *
 * rescue() مقصود: القوالب يجب أن تُصيَّر قبل تنفيذ الهجرة/البذور — عندها
 * يعود كل سطح لافتراضيه بدل رمي استثناء (نفس نهج PopupService).
 */
class BackgroundPatternService
{
    public const CACHE_KEY = 'cms.background_patterns';

    private const CACHE_TTL_SECONDS = 600;

    /** @var array<string, string>|null */
    private ?array $memo = null;

    /**
     * النقش المُسنَد لسطح بعينه.
     */
    public function for(PatternSurface $surface): BackgroundPattern
    {
        $stored = $this->stored()[$surface->settingKey()] ?? null;

        // قيمة فارغة تعني «لم يُحفظ شيء» فنرجع للافتراضي، بينما 'none'
        // اختيار صريح من الأدمن بإزالة النقش — والفرق بينهما مقصود.
        if ($stored === null || $stored === '') {
            return $surface->default();
        }

        return BackgroundPattern::fromValue($stored);
    }

    /**
     * فئة CSS للسطح، أو سلسلة فارغة حين لا نقش (تُطبع مباشرة في class).
     */
    public function cssClass(PatternSurface $surface): string
    {
        return $this->for($surface)->cssClass() ?? '';
    }

    /**
     * خريطة أقسام الرئيسية: مفتاح القسم => فئة CSS (أو '').
     * تُشارَك مع قالب الرئيسية فيقرأها بلا منطق داخل الـBlade.
     *
     * @return array<string, string>
     */
    public function sectionClasses(): array
    {
        $map = [];

        foreach (PatternSurface::sections() as $surface) {
            // مفتاح مختصر بلا بادئة 'section.' ليقرأه القالب بوضوح.
            $map[substr($surface->value, strlen('section.'))] = $this->cssClass($surface);
        }

        return $map;
    }

    /**
     * القيم المحفوظة فعلًا (بلا افتراضيات) — تستخدمها شاشة الأدمن للتعبئة.
     *
     * @return array<string, string>
     */
    public function stored(): array
    {
        return $this->memo ??= rescue(
            fn (): array => Cache::remember(
                self::CACHE_KEY,
                self::CACHE_TTL_SECONDS,
                static fn (): array => \App\Models\Setting::query()
                    ->where('group', 'appearance')
                    ->where('key', 'like', 'pattern.%')
                    ->pluck('value', 'key')
                    ->all()
            ),
            [],
            report: false,
        );
    }

    /**
     * يُبطل الكاش بعد حفظ الأدمن — بدونه يبقى الاختيار غير مرئي حتى انتهاء TTL.
     */
    public function flush(): void
    {
        $this->memo = null;
        Cache::forget(self::CACHE_KEY);
    }
}
