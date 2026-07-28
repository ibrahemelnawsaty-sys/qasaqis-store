<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Article;
use App\Models\Book;
use App\Models\BookImage;
use App\Services\Media\MediaCache;
use Illuminate\Console\Command;

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
        $failed = 0;

        $warm = function (?string $path) use (&$done, &$failed): void {
            if (! $this->isStored($path)) {
                return;
            }

            if ($this->option('force')) {
                $rel = MediaCache::relPath($path);
                if ($rel !== null && is_file(public_path($rel))) {
                    @unlink(public_path($rel));
                }
            }

            MediaCache::ensure($path) !== null ? $done++ : $failed++;
        };

        $this->info('توليد أغلفة الكتب…');
        Book::query()->withTrashed()->select(['id', 'cover_image'])->lazy()
            ->each(fn (Book $b) => $warm($b->cover_image));

        $this->info('توليد صور المعرض…');
        BookImage::query()->select(['id', 'path'])->lazy()
            ->each(fn (BookImage $i) => $warm($i->path));

        $this->info('توليد أغلفة المقالات…');
        Article::query()->withTrashed()->select(['id', 'cover_image'])->lazy()
            ->each(fn (Article $a) => $warm($a->cover_image));

        $this->info("تمّ: {$done} مشتقًّا".($failed > 0 ? " — تعذّر {$failed} (تحقّق من صلاحية كتابة public/media-cache)" : ''));

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
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
