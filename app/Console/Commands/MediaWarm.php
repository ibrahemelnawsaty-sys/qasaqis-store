<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Article;
use App\Models\Book;
use App\Models\BookImage;
use App\Services\Media\MediaCache;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * يولّد كل المشتقّات الموسومة الثابتة في public/media-cache سلفًا (أغلفة الكتب + صور
 * المعرض + أغلفة المقالات) — فلا يدفع أوّل زائر كلفة توليد GD، وتُخدَم كل الصور static
 * بلا PHP فورًا. يُشغَّل بعد كل نشر يمسّ الصور. آمن للتكرار (يتخطّى الموجود).
 *
 * لا يمسّ الروابط الخارجية (http) ولا الأصول الثابتة المشحونة (images/) — تلك تُخدَم
 * كما هي بلا مشتقّ.
 */
class MediaWarm extends Command
{
    protected $signature = 'media:warm {--force : أعِد التوليد حتى لو وُجد المشتقّ}';

    protected $description = 'توليد مشتقّات الأغلفة الموسومة الثابتة في public/media-cache';

    public function handle(): int
    {
        $done = 0;
        /** @var list<string> $missing مصدر مفقود على القرص — يُخدَم بالبديل، غير معطِّل. */
        $missing = [];
        /** @var list<string> $failed مصدر موجود لكن تعذّرت المعالجة/الكتابة — يستحق التحقّق. */
        $failed = [];

        $warm = function (?string $path) use (&$done, &$missing, &$failed): void {
            if (! $this->isStored($path)) {
                return;
            }

            // مصدر مفقود على القرص (مثل غلاف لم يُرفَع بعد، BOOK10): تخطٍّ لا فشل —
            // يُخدَم بالبديل المحايد، ولا سبيل لتوليد مشتقٍّ من ملفٍّ غير موجود.
            if (! is_file(Storage::disk('public')->path($path))) {
                $missing[] = $path;

                return;
            }

            if ($this->option('force')) {
                $rel = MediaCache::relPath($path);
                if ($rel !== null && is_file(public_path($rel))) {
                    @unlink(public_path($rel));
                }
            }

            MediaCache::ensure($path) !== null ? $done++ : $failed[] = $path;
        };

        $this->info('توليد أغلفة الكتب…');
        Book::query()->withTrashed()->select(['id', 'cover_image'])->lazy()
            ->each(function (Book $b) use ($warm): void {
                $warm($b->cover_image);
                // مصغّر قائمة الأدمن أيضًا (صغير، static) للأغلفة المخزَّنة الموجودة فقط،
                // فلا يدفع أوّل فتح للقائمة كلفة توليد عشرات/مئات المصغّرات.
                if ($this->isStored($b->cover_image)
                    && is_file(Storage::disk('public')->path($b->cover_image))) {
                    MediaCache::ensureThumb($b->cover_image);
                }
            });

        $this->info('توليد صور المعرض…');
        BookImage::query()->select(['id', 'path'])->lazy()
            ->each(fn (BookImage $i) => $warm($i->path));

        $this->info('توليد أغلفة المقالات…');
        Article::query()->withTrashed()->select(['id', 'cover_image'])->lazy()
            ->each(fn (Article $a) => $warm($a->cover_image));

        $this->newLine();
        $this->info("تمّ توليد {$done} مشتقًّا.");

        // تشخيص دقيق بدل تلميح مضلِّل: نميّز «مصدر مفقود» (متوقّع، غير معطِّل) عن
        // «تعذّرت المعالجة» (المصدر موجود — يستحق التحقّق فعلًا).
        if ($missing !== []) {
            $this->warn(count($missing).' بلا ملفّ مصدر على القرص (تُخدَم بالبديل المحايد — غير معطِّلة):');
            foreach ($missing as $p) {
                $this->line("  - {$p}");
            }
        }

        if ($failed !== []) {
            $this->error(count($failed).' مصدرها موجود لكن تعذّرت معالجتها (تحقّق من GD أو صلاحية كتابة public/media-cache):');
            foreach ($failed as $p) {
                $this->line("  ✗ {$p}");
            }
        }

        // فشلٌ فعليّ فقط يُرجِع رمز خطأ؛ المصادر المفقودة متوقّعة ولا تُفشِل النشر.
        return $failed !== [] ? self::FAILURE : self::SUCCESS;
    }

    /**
     * مسار مخزَّن يمرّ بخطّ العلامة المائية (لا فارغ، لا http، لا أصل ثابت images/).
     */
    private function isStored(?string $path): bool
    {
        return filled($path)
            && ! str_starts_with($path, 'http://')
            && ! str_starts_with($path, 'https://')
            && ! str_starts_with($path, 'images/');
    }
}
