<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Article;
use Illuminate\Console\Command;

/**
 * يربط أغلفة المدونة المصمَّمة (public/images/articles/blog-cover-NN.webp) بمقالاتها
 * عبر الـslug. الأغلفة بطاقاتٌ على هوية «قصاقيص» (تدرّج + أيقونة الكتب + عنوان المقال
 * + الشعار) وُلّدت مسبقًا من عناوين المقالات نفسها — لا صورة مخترَعة (بند 0.4 / 1.1).
 *
 * قواعد السلامة:
 *  - يضبط cover_image فقط إن كان فارغًا أو غلافًا مُدارًا (يبدأ بـ images/articles/)؛
 *    أي غلاف مخصّص (رابط خارجي أو مسار آخر رفعه الأدمن) لا يُدهَس إلا بـ --force.
 *  - لا يضبط مسارًا لملفٍّ غير موجود على القرص (يتجنّب غلافًا مكسورًا).
 *  - المطابقة على slug الثابت؛ المقال غير الموجود يُتخطّى بوضوح لا يوقف الأمر.
 *  - آمن لإعادة التشغيل (idempotent) وقابل للمعاينة بـ --dry-run.
 */
class ApplyArticleCovers extends Command
{
    protected $signature = 'articles:apply-covers {--force : استبدل حتى الأغلفة المخصّصة} {--dry-run : اعرض بلا حفظ}';

    protected $description = 'ربط أغلفة المدونة المصمَّمة بمقالاتها عبر الـslug (قابل لإعادة التشغيل بأمان).';

    /** @var array<string, string> slug => مسار الغلاف تحت public/ */
    private const COVERS = [
        'اختيار-كتاب-مناسب-لعمر-الطفل' => 'images/articles/blog-cover-01.webp',
        'اختيار-كتب-القيم-للاطفال' => 'images/articles/blog-cover-02.webp',
        'اختيار-كتب-تعليم-الحروف-العربية' => 'images/articles/blog-cover-03.webp',
        'اختيار-كتب-دينية-للاطفال' => 'images/articles/blog-cover-04.webp',
        'افضل-الكتب-العلمية-للاطفال' => 'images/articles/blog-cover-05.webp',
        'الذكاء-العاطفي-للاطفال-وقصص-المشاعر' => 'images/articles/blog-cover-06.webp',
        'العاب-تعليمية-تنمي-الذكاء' => 'images/articles/blog-cover-07.webp',
        'القراءة-للرضع-متى-وكيف' => 'images/articles/blog-cover-08.webp',
        'انشطة-المهارات-الحركية-الدقيقة' => 'images/articles/blog-cover-09.webp',
        'انشطة-تعليمية-منزلية-بسيطة' => 'images/articles/blog-cover-10.webp',
        'اوراق-عمل-تعليمية-للاطفال' => 'images/articles/blog-cover-11.webp',
        'تعليم-الحروف-والارقام-باللعب' => 'images/articles/blog-cover-12.webp',
        'تنمية-الفضول-وحب-الاستطلاع' => 'images/articles/blog-cover-13.webp',
        'تنمية-مهارات-اللغة-عند-الطفل' => 'images/articles/blog-cover-14.webp',
        'ركن-قراءة-للطفل-في-البيت' => 'images/articles/blog-cover-15.webp',
        'روايات-للاطفال-9-12-سنة' => 'images/articles/blog-cover-16.webp',
        'غيرة-الطفل-من-المولود-الجديد' => 'images/articles/blog-cover-17.webp',
        'فوائد-القراءة-قبل-النوم-للاطفال' => 'images/articles/blog-cover-18.webp',
        'قصص-احترام-الاخرين-للاطفال' => 'images/articles/blog-cover-19.webp',
        'قصص-التغلب-على-الخوف' => 'images/articles/blog-cover-20.webp',
        'قصص-السيرة-النبوية-للاطفال' => 'images/articles/blog-cover-21.webp',
        'قصص-الصدق-للاطفال' => 'images/articles/blog-cover-22.webp',
        'قصص-تعليم-المشاركة-للاطفال' => 'images/articles/blog-cover-23.webp',
        'قصص-تعليم-النظافة-للاطفال' => 'images/articles/blog-cover-24.webp',
        'قصص-لتعليم-الطفل-التحكم-في-الغضب' => 'images/articles/blog-cover-25.webp',
        'قصص-مصورة-للاطفال' => 'images/articles/blog-cover-26.webp',
        'كتب-اطفال-عمر-3-سنوات' => 'images/articles/blog-cover-27.webp',
        'كتب-اطفال-ما-قبل-المدرسة' => 'images/articles/blog-cover-28.webp',
        'كتب-الطفولة-المبكرة-0-3' => 'images/articles/blog-cover-29.webp',
        'كيف-اجعل-طفلي-يحب-القراءة' => 'images/articles/blog-cover-30.webp',
        'كيف-تعزز-ثقة-طفلك-بنفسه' => 'images/articles/blog-cover-31.webp',
        'هدايا-الكتب-للاطفال' => 'images/articles/blog-cover-32.webp',
        'وقت-الشاشة-والقراءة-للاطفال' => 'images/articles/blog-cover-33.webp',
    ];

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');
        $stats = ['set' => 0, 'skipped' => 0, 'missingArticle' => 0, 'missingFile' => 0];

        foreach (self::COVERS as $slug => $path) {
            $article = Article::query()->where('slug', $slug)->first();

            if (! $article) {
                $this->warn("  ! مقال غير موجود: {$slug}");
                $stats['missingArticle']++;

                continue;
            }

            if (! is_file(public_path($path))) {
                $this->warn("  ! ملف غلاف مفقود على القرص: {$path}");
                $stats['missingFile']++;

                continue;
            }

            $current = (string) $article->cover_image;
            $managed = $current === '' || str_starts_with($current, 'images/articles/');

            if (! $managed && ! $force) {
                $this->line("  ~ تُرك (غلاف مخصّص): {$slug}");
                $stats['skipped']++;

                continue;
            }

            if ($current === $path) {
                $stats['skipped']++;

                continue;
            }

            if ($dry) {
                $this->line("  + سيُضبط [{$slug}] → {$path}");
                $stats['set']++;

                continue;
            }

            $article->update(['cover_image' => $path]);
            $stats['set']++;
            $this->line("  ✓ {$slug}");
        }

        $this->newLine();
        $this->info(sprintf(
            'تمّ. ضُبط: %d | متخطّى: %d | مقال مفقود: %d | ملف غلاف مفقود: %d.',
            $stats['set'], $stats['skipped'], $stats['missingArticle'], $stats['missingFile'],
        ));

        if ($dry) {
            $this->warn('تشغيل تجريبي — لم يُحفظ أي تغيير.');
        }

        return self::SUCCESS;
    }
}
