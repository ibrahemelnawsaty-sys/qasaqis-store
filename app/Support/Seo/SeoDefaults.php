<?php

declare(strict_types=1);

namespace App\Support\Seo;

use App\Models\Article;
use App\Models\Book;
use App\Models\Category;
use App\Models\Page;
use App\Models\Series;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * مصدر واحد لاشتقاق بيانات SEO التلقائية من محتوى الكيان نفسه.
 *
 * يُستخدم في موضعين ليتطابق ما تعرضه لوحة الإدارة مع ما يُصدره الموقع فعلًا:
 *  - القوالب (render): القيمة الافتراضية حين لا يكتب الأدمن تجاوزًا (override).
 *  - Filament: نصّ placeholder يُظهر للأدمن ما سيُصدَر إن ترك الحقل فارغًا.
 *
 * لا يقرأ هذا الصنف جدول seo_meta ولا حقول الـSEO المخزّنة؛ يشتقّ من حقول المحتوى
 * فقط. سلسلة «التجاوز المخزّن ?: المشتقّ» تبقى في القالب/الـplaceholder، وهذا الصنف
 * يوفّر الطرف المشتقّ ليكون مصدرًا واحدًا لا يتكرّر منطقه في كل قالب.
 */
final class SeoDefaults
{
    /**
     * أقصى طول لوصف الميتا. الخانة في seo_meta تسع 320، لكن مقتطف نتائج البحث
     * يُقتطع عمليًا عند ~155–160 حرفًا، فنولّد في هذا الحدّ حتى لا نبني وصفًا يُبتر.
     */
    private const DESCRIPTION_LIMIT = 160;

    /**
     * عنوان الميتا المشتقّ (بلا اعتبار أي تجاوز مخزّن). يُلحق اسم العلامة ما لم يكن
     * العنوان يحويه أصلًا، حتى لا يتكرّر «قصاقيص أطفال — قصاقيص أطفال».
     */
    public static function title(Model $model): string
    {
        $brand = (string) __('common.brand');

        $base = trim((string) match (true) {
            $model instanceof Book => (string) $model->title,
            $model instanceof Page => (string) $model->title,
            $model instanceof Article => (string) $model->title,
            $model instanceof Category => (string) __('seo.category_title', ['name' => (string) $model->name]),
            $model instanceof Series => (string) __('seo.series_title', ['name' => (string) $model->name]),
            default => '',
        });

        if ($base === '') {
            return $brand;
        }

        return Str::contains($base, $brand) ? $base : $base.' — '.$brand;
    }

    /**
     * وصف الميتا المشتقّ (بلا اعتبار أي تجاوز مخزّن). يُنظَّف من HTML ويُقتطع، ويرجع
     * إلى شعار العلامة حين لا يوجد محتوى يُشتقّ منه (بند 1.1: لا نخترع نصًّا).
     */
    public static function description(Model $model): string
    {
        $brand = ['brand' => (string) __('common.brand')];

        $derived = match (true) {
            $model instanceof Book => self::firstFilled([$model->short_description, self::fromHtml($model->long_description)]),
            $model instanceof Page => self::fromHtml($model->content),
            $model instanceof Article => self::firstFilled([$model->excerpt, self::fromHtml($model->content)]),
            $model instanceof Category => self::firstFilled([
                $model->description,
                (string) __('seo.category_description', ['name' => (string) $model->name] + $brand),
            ]),
            $model instanceof Series => self::firstFilled([
                $model->description,
                (string) __('seo.series_description', ['name' => (string) $model->name] + $brand),
            ]),
            default => '',
        };

        $derived = self::normalize($derived);

        return $derived !== '' ? $derived : (string) __('common.tagline');
    }

    /** عنوان OpenGraph المشتقّ — يساوي عنوان الميتا افتراضيًا. */
    public static function ogTitle(Model $model): string
    {
        return self::title($model);
    }

    /** وصف OpenGraph المشتقّ — يساوي وصف الميتا افتراضيًا. */
    public static function ogDescription(Model $model): string
    {
        return self::description($model);
    }

    /** أول قيمة غير فارغة بعد التشذيب، أو '' إن غابت كلها. */
    private static function firstFilled(array $candidates): string
    {
        foreach ($candidates as $candidate) {
            $value = trim((string) ($candidate ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    /** يحوّل HTML الذي يحرّره الأدمن إلى نصّ عادي صالح لوصف الميتا. */
    private static function fromHtml(?string $html): string
    {
        if ($html === null || trim($html) === '') {
            return '';
        }

        // نستبدل كل وسم بمسافة (لا نحذفه بـ strip_tags) كي لا تلتصق الكلمات عبر حدود
        // العناصر الكتلية (</p><li> → «القناعة. قيمة» لا «القناعة.قيمة»). normalize
        // يوحّد المسافات الناتجة. html_entity_decode يعيد الكيانات (&amp; → &) كي لا
        // تظهر في المقتطف. لا نحتاج تطهير XSS لأن الناتج يُخرَج عبر {{ }} المهرِّبة.
        $text = (string) preg_replace('/<[^>]+>/u', ' ', $html);

        return html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /** يوحّد المسافات (بينها أسطر HTML) ويقتطع عند حدّ آمن دون بتر منتصف كلمة. */
    private static function normalize(string $text): string
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', $text));

        if ($text === '') {
            return '';
        }

        return Str::limit($text, self::DESCRIPTION_LIMIT, '…');
    }
}
