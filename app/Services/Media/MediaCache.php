<?php

declare(strict_types=1);

namespace App\Services\Media;

use Illuminate\Support\Facades\Storage;

/**
 * يحوّل صور المتجر المخزَّنة (books/covers, books/gallery, articles/covers) إلى
 * مشتقّات موسومة بالعلامة المائية **ثابتة عامّة** في public/media-cache — يخدمها
 * خادم الويب مباشرةً بلا PHP/Laravel (كأصول Vite المبنيّة)، بدل تمرير كل غلاف عبر
 * مسار /media الذي يُقلع الإطار كاملًا + استعلام DB لكل صورة (20+ غلاف/صفحة).
 *
 * الحماية محفوظة: الأصل يبقى خاصًّا في storage (لا storage:link)، واسم المشتقّ
 * sha1 لا يكشف اسم الملف الأصلي، والعلامة/التصغير مخبوزان داخل الملف العام.
 *
 * التدفّق: coverUrl() تعيد الرابط الثابت فور جهوز المشتقّ (publicUrlIfReady،
 * بلا توليد — مسار العرض الساخن)، وإلا مسار /media الذي يولّده عند أول طلب
 * (ensure) فيصير كل ما بعده static. أمر media:warm يولّدها كلها بعد النشر.
 *
 * relPath/publicUrlIfReady ثابتتان بلا تبعيات (تُنادَيان لكل غلاف عند العرض)؛
 * ensure() وحدها تحلّ CoverWatermark وقت التوليد فقط.
 */
final class MediaCache
{
    /** المجلّد العام تحت docroot — يخدمه خادم الويب مباشرة بلا PHP. */
    public const PUBLIC_DIR = 'media-cache';

    /**
     * امتداد المشتقّ: webp إن توفّر imagewebp (الدستور 5.3)، وإلا jpg. **يجب** أن
     * يتبع الشرط نفسه في CoverWatermark::apply كي يوافق الاسمُ البايتاتِ المكتوبة.
     */
    public static function ext(): string
    {
        return function_exists('imagewebp') ? 'webp' : 'jpg';
    }

    /**
     * اسم المشتقّ الثابت (نسبةً لـ public/) للمسار المخزَّن، أو null إن غاب الأصل.
     * يتضمّن mtime فيتجدّد الاسم تلقائيًا عند تغيّر الصورة (تُهمَل النسخة القديمة).
     */
    public static function relPath(string $storagePath): ?string
    {
        $mtime = @filemtime(Storage::disk('public')->path($storagePath));

        if ($mtime === false) {
            return null;
        }

        return self::PUBLIC_DIR.'/'.sha1($storagePath).'-'.$mtime.'.'.self::ext();
    }

    /**
     * الرابط الثابت العام إن كان المشتقّ موجودًا فعلًا (بلا توليد — للاستدعاء الكثيف
     * من coverUrl أثناء العرض). null إن لم يُولَّد بعد أو غاب الأصل — فيسقط النموذج
     * إلى مسار /media الذي يولّده.
     */
    public static function publicUrlIfReady(string $storagePath): ?string
    {
        $rel = self::relPath($storagePath);

        if ($rel === null || ! is_file(public_path($rel))) {
            return null;
        }

        return asset($rel);
    }

    /**
     * يضمن وجود المشتقّ الثابت العام (يولّده إن غاب) ويعيد مساره المطلق على القرص،
     * أو null على أي فشل (أصل غائب / تعذّر تعليم / تعذّرت الكتابة في public). المتحكّم
     * يخدم عندها الأصل الخاصّ عبر المسار نفسه فلا تنكسر الصورة.
     */
    public static function ensure(string $storagePath): ?string
    {
        $rel = self::relPath($storagePath);

        if ($rel === null) {
            return null;
        }

        $target = public_path($rel);

        if (is_file($target)) {
            return $target;
        }

        $binary = app(CoverWatermark::class)->apply(Storage::disk('public')->path($storagePath));

        if ($binary === null) {
            return null;
        }

        $dir = dirname($target);

        if (! is_dir($dir) && ! @mkdir($dir, 0755, true) && ! is_dir($dir)) {
            return null;
        }

        return @file_put_contents($target, $binary) === false ? null : $target;
    }
}
