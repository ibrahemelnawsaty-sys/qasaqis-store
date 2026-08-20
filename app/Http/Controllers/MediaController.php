<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Book;
use App\Models\BookImage;
use App\Services\Media\MediaCache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * يخدم صور الكتب/المقالات موسومةً بالعلامة المائية عبر مسار بالمعرّف/slug — فلا يُكشف
 * اسم الملف الأصلي في /storage (لا يمكن تعديل الرابط للوصول للأصل النظيف).
 *
 * هذا المسار **احتياطيّ ومولّد**: عند أوّل طلب لصورة غير محمّاة يولّد المشتقّ الثابت في
 * public/media-cache (MediaCache::ensure) ويخدمه، فتعيد coverUrl() بعده رابطه الثابت
 * ويخدمه خادم الويب مباشرةً بلا PHP (لا يعود لهذا المسار). أمر media:warm يولّدها كلها
 * سلفًا. صامد: أي فشل توليد/كتابة عامّة يخدم الأصل الخاصّ عبر المسار نفسه — لا تنكسر
 * الصورة ولا يُكشف اسم الملف.
 */
class MediaController extends Controller
{
    public function bookCover(Book $book): mixed
    {
        return $this->serve($book->cover_image);
    }

    public function bookImage(BookImage $image): mixed
    {
        return $this->serve($image->path);
    }

    /**
     * نسخة بطاقة متجاوبة (srcset) بعرض محدَّد: تولّد المشتقّ الخفيف الثابت عند أوّل طلب
     * وتخدمه، فتعيد coverSrcset() بعده رابطه الثابت (static بلا PHP). العرض مقيَّد بالمسار
     * (whereIn CARD_WIDTHS) وبتحقّق مزدوج هنا فلا يُطلَب حجم عشوائيّ.
     */
    public function bookVariant(Book $book, string $width): mixed
    {
        return $this->serveVariant($book->cover_image, (int) $width);
    }

    public function articleCover(Article $article): mixed
    {
        return $this->serve($article->cover_image);
    }

    /**
     * مصغّر غلاف الكتاب للوحة الإدارة (قائمة الكتب): يولّد المصغّر الصغير الثابت عند
     * أوّل طلب ويخدمه، فتعيد adminThumbUrl() بعده رابطه الثابت (static بلا PHP).
     */
    public function bookThumb(Book $book): mixed
    {
        return $this->serveThumb($book->cover_image);
    }

    private function serve(?string $path): mixed
    {
        // مسار خارجي (http) لا يُخدَم هنا — النموذج يُرجِعه مباشرةً فلا يصل لهذا المسار.
        if (blank($path) || Str::startsWith($path, ['http://', 'https://'])) {
            abort(404);
        }

        if (! is_file(Storage::disk('public')->path($path))) {
            abort(404);
        }

        $headers = ['Cache-Control' => 'public, max-age=31536000, immutable'];

        // يولّد (أو يجد) المشتقّ الثابت العام في public/media-cache ويخدمه — فيصبح
        // الطلب التالي لنفس الصورة static مباشرة عبر رابط coverUrl، بلا PHP.
        $publicFile = MediaCache::ensure($path);

        if ($publicFile !== null) {
            // النوع من امتداد المشتقّ الفعليّ (webp/jpg) — يطابق ما كتبه CoverWatermark.
            $mime = str_ends_with($publicFile, '.webp') ? 'image/webp' : 'image/jpeg';

            return response()->file($publicFile, ['Content-Type' => $mime] + $headers);
        }

        // تعذّر توليد/كتابة المشتقّ العام (تعليم فاشل أو public غير قابل للكتابة) —
        // نخدم الأصل عبر المسار نفسه (يبقى اسم الملف مخفيًّا).
        return response()->file(Storage::disk('public')->path($path), $headers);
    }

    /**
     * مثل serve لكن يولّد/يخدم نسخة بطاقة خفيفة بعرض $width (MediaCache::ensureVariant).
     * تحقّق مزدوج أنّ العرض ضمن CARD_WIDTHS (المسار يقيّده أصلًا) قبل أي توليد. أي فشل
     * يخدم الأصل عبر المسار نفسه فلا تنكسر الصورة.
     */
    private function serveVariant(?string $path, int $width): mixed
    {
        if (! in_array($width, MediaCache::CARD_WIDTHS, true)) {
            abort(404);
        }

        if (blank($path) || Str::startsWith($path, ['http://', 'https://'])) {
            abort(404);
        }

        if (! is_file(Storage::disk('public')->path($path))) {
            abort(404);
        }

        $headers = ['Cache-Control' => 'public, max-age=31536000, immutable'];

        $publicFile = MediaCache::ensureVariant($path, $width);

        if ($publicFile !== null) {
            $mime = str_ends_with($publicFile, '.webp') ? 'image/webp' : 'image/jpeg';

            return response()->file($publicFile, ['Content-Type' => $mime] + $headers);
        }

        return response()->file(Storage::disk('public')->path($path), $headers);
    }

    /**
     * مثل serve لكن يولّد/يخدم مصغّرًا صغيرًا (MediaCache::ensureThumb). أي فشل توليد
     * يخدم الأصل عبر المسار نفسه فلا تنكسر الصورة (أثقل قليلًا مؤقّتًا حتى يُولَّد).
     */
    private function serveThumb(?string $path): mixed
    {
        if (blank($path) || Str::startsWith($path, ['http://', 'https://'])) {
            abort(404);
        }

        if (! is_file(Storage::disk('public')->path($path))) {
            abort(404);
        }

        $headers = ['Cache-Control' => 'public, max-age=31536000, immutable'];

        $publicFile = MediaCache::ensureThumb($path);

        if ($publicFile !== null) {
            $mime = str_ends_with($publicFile, '.webp') ? 'image/webp' : 'image/jpeg';

            return response()->file($publicFile, ['Content-Type' => $mime] + $headers);
        }

        return response()->file(Storage::disk('public')->path($path), $headers);
    }
}
