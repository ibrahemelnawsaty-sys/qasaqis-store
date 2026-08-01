<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Book;
use App\Models\Publisher;
use Illuminate\Console\Command;

/**
 * حذف نهائيّ لكل كتب ناشر معيّن — لإعادة استيراد نظيفة بعد تعديل الـfeed.
 *
 * لماذا نهائيّ (forceDelete) لا ناعم: books:import يتخطّى المحذوف ناعمًا عمدًا،
 * فالحذف الناعم يمنع إعادة إنشائها. يشمل المحذوف مسبقًا (withTrashed) وينظّف
 * علاقاته (الأقسام + الصور) أوّلًا تفاديًا لصفوف يتيمة.
 *
 * وقاية: لا يحذف إلا بـ --force صراحةً؛ بدونها يعرض العدد فقط.
 */
class PurgeBooksCommand extends Command
{
    protected $signature = 'books:purge
        {--publisher= : اسم دار النشر المراد حذف كتبها (إلزامي)}
        {--force : نفّذ الحذف فعلًا (بدونه: عرض العدد فقط)}';

    protected $description = 'حذف نهائيّ لكل كتب ناشر معيّن (لإعادة استيراد نظيفة).';

    public function handle(): int
    {
        $name = trim((string) $this->option('publisher'));

        if ($name === '') {
            $this->error('الخيار --publisher إلزاميّ. مثال: --publisher="المستقبل الرقمي"');

            return self::FAILURE;
        }

        $publisher = Publisher::withTrashed()->where('name', $name)->first();

        if (! $publisher) {
            $this->error("لا يوجد ناشر باسم: {$name}");

            return self::FAILURE;
        }

        $count = Book::withTrashed()->where('publisher_id', $publisher->id)->count();

        if ($count === 0) {
            $this->info("لا كتب للناشر «{$name}».");

            return self::SUCCESS;
        }

        if (! $this->option('force')) {
            $this->warn("سيُحذف نهائيًّا {$count} كتابًا للناشر «{$name}». أضِف --force للتنفيذ.");

            return self::SUCCESS;
        }

        $deleted = 0;

        // get()->each لا chunk: الحذف أثناء التصفّح بالمعرّف قد يتخطّى صفوفًا. العدد صغير.
        Book::withTrashed()
            ->where('publisher_id', $publisher->id)
            ->get()
            ->each(function (Book $book) use (&$deleted): void {
                // تنظيف العلاقات أوّلًا (تفاديًا لقيود المفتاح الأجنبيّ / صفوف يتيمة).
                $book->categories()->detach();
                $book->images()->delete();
                $book->forceDelete();
                $deleted++;
            });

        $this->info("حُذف نهائيًّا {$deleted} كتابًا للناشر «{$name}». أعِد الآن الاستيراد.");

        return self::SUCCESS;
    }
}
