<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Book;
use App\Models\BookImage;
use App\Services\Media\CoverWatermark;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * يخدم صور الكتب/المقالات موسومةً بالعلامة المائية عبر مسار بالمعرّف/slug — فلا يُكشف
 * اسم الملف الأصلي في /storage (لا يمكن تعديل الرابط للوصول للأصل النظيف). النتيجة
 * مخزّنة مؤقتًا على القرص (خاصّ) ويخدمها متصفّح/Cloudflare بكاش طويل.
 *
 * المسار يأتي من النموذج لا من إدخال المستخدم، فلا خطر عبور مسارات. صامد: أي فشل
 * تعليم يخدم الأصل عبر المسار نفسه (لا يكشف /storage) — الصورة لا تنكسر.
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

    public function articleCover(Article $article): mixed
    {
        return $this->serve($article->cover_image);
    }

    private function serve(?string $path): mixed
    {
        // مسار خارجي (http) لا يُخدَم هنا — النموذج يُرجِعه مباشرةً فلا يصل لهذا المسار.
        if (blank($path) || Str::startsWith($path, ['http://', 'https://'])) {
            abort(404);
        }

        $abs = Storage::disk('public')->path($path);
        if (! is_file($abs)) {
            abort(404);
        }

        $headers = ['Cache-Control' => 'public, max-age=31536000, immutable'];
        $cacheFile = storage_path('app/media-wm/' . sha1($path) . '-' . filemtime($abs) . '.jpg');

        if (! is_file($cacheFile)) {
            $binary = app(CoverWatermark::class)->apply($abs);

            if ($binary === null) {
                // تعذّر التعليم — نخدم الأصل عبر المسار نفسه (يبقى اسم الملف مخفيًّا).
                return response()->file($abs, $headers);
            }

            if (! is_dir(dirname($cacheFile))) {
                @mkdir(dirname($cacheFile), 0755, true);
            }
            @file_put_contents($cacheFile, $binary);

            if (! is_file($cacheFile)) {
                // تعذّرت كتابة الكاش (أذونات) — أرجِع البايتات مباشرةً بلا كاش قرصيّ.
                return response($binary, 200, ['Content-Type' => 'image/jpeg'] + $headers);
            }
        }

        return response()->file($cacheFile, ['Content-Type' => 'image/jpeg'] + $headers);
    }
}
