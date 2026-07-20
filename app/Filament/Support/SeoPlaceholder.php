<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Support\Seo\SeoDefaults;
use Illuminate\Database\Eloquent\Model;

/**
 * نصوص placeholder لحقول SEO في لوحة الإدارة.
 *
 * تعرض القيمة التلقائية المشتقّة من محتوى السجل (نفسها التي يُصدرها الموقع عبر
 * App\Support\Seo\SeoDefaults) كي يرى الأدمن بالضبط ما سيُنشر إن ترك الحقل فارغًا.
 * على شاشة الإنشاء لا سجلّ بعد، فنعرض تلميحًا عامًّا بدل القيمة المشتقّة.
 *
 * تُمرَّر $livewire (مكوّن صفحة Filament) وتُقرأ منه getRecord()؛ للأنواع ذات علاقة
 * seo (كتاب/صفحة/قسم/سلسلة) هو الكيان الأب، وللمقال هو المقال نفسه.
 */
final class SeoPlaceholder
{
    public static function title(mixed $livewire): string
    {
        $record = self::record($livewire);

        return $record !== null
            ? SeoDefaults::title($record)
            : (string) __('seo.admin.auto_from_content');
    }

    public static function description(mixed $livewire): string
    {
        $record = self::record($livewire);

        return $record !== null
            ? SeoDefaults::description($record)
            : (string) __('seo.admin.auto_from_content');
    }

    /** سجلّ الصفحة الحالي إن وُجد (null على شاشة الإنشاء). */
    private static function record(mixed $livewire): ?Model
    {
        if (is_object($livewire) && method_exists($livewire, 'getRecord')) {
            $record = $livewire->getRecord();

            return $record instanceof Model ? $record : null;
        }

        return null;
    }
}
