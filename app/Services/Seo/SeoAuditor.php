<?php

declare(strict_types=1);

namespace App\Services\Seo;

use App\Filament\Resources\ArticleResource;
use App\Filament\Resources\BookResource;
use App\Filament\Resources\CategoryResource;
use App\Filament\Resources\PageResource;
use App\Models\Article;
use App\Models\Book;
use App\Models\Category;
use App\Models\Page;
use App\Support\Seo\SeoDefaults;
use Illuminate\Support\Collection;

/**
 * تدقيق SEO تلقائي للموقع (نظير Yoast SEO analysis على مستوى الموقع). يفحص المحتوى
 * المنشور عن نواقص تُضعف الظهور في جوجل — وصف ناقص، صورة غلاف مفقودة (og:image)،
 * عنوان طويل يُبتر — ويعيد قائمة نتائج مرتّبة بالخطورة، تعرضها لوحة «تدقيق SEO».
 *
 * لا يشتقّ منطقًا جديدًا لبناء الميتا؛ يبني على قواعد SeoDefaults نفسها (الوصف يُشتقّ
 * من محتوى الكيان، ويرجع لشعار عام عند غيابه) فيُبلّغ متى سيُستخدم ذلك الرجوع العام.
 */
class SeoAuditor
{
    /** أقصى طول مُوصى به لعنوان الصفحة قبل بتره في نتائج جوجل (~600px ≈ 60 حرفًا). */
    public const TITLE_MAX = 60;

    /**
     * كل النتائج مرتّبة: الحرِج (danger) أولًا ثم التحذيرات ثم المعلومات.
     *
     * @return Collection<int, SeoFinding>
     */
    public function run(): Collection
    {
        $findings = collect()
            ->concat($this->auditBooks())
            ->concat($this->auditArticles())
            ->concat($this->auditPages())
            ->concat($this->auditCategories())
            ->concat($this->auditDuplicates())
            ->concat($this->auditSite());

        $order = [SeoFinding::DANGER => 0, SeoFinding::WARNING => 1, SeoFinding::INFO => 2];

        return $findings
            ->sortBy(fn (SeoFinding $f): int => $order[$f->severity] ?? 3)
            ->values();
    }

    /**
     * ملخّص عددي للنتائج حسب الخطورة (لشارة التنقّل ورأس اللوحة).
     *
     * @return array{danger:int, warning:int, info:int, total:int}
     */
    public function summarize(): array
    {
        $findings = $this->run();

        return [
            'danger' => $findings->where('severity', SeoFinding::DANGER)->count(),
            'warning' => $findings->where('severity', SeoFinding::WARNING)->count(),
            'info' => $findings->where('severity', SeoFinding::INFO)->count(),
            'total' => $findings->count(),
        ];
    }

    /**
     * @return array<int, SeoFinding>
     */
    private function auditBooks(): array
    {
        $out = [];

        Book::query()
            ->where('is_published', true)
            ->get(['id', 'title', 'short_description', 'long_description', 'cover_image'])
            ->each(function (Book $book) use (&$out): void {
                $label = (string) $book->title;
                $url = $this->editUrl(BookResource::class, $book->getKey());

                if (blank($book->short_description) && blank($book->long_description)) {
                    $out[] = new SeoFinding(SeoFinding::DANGER, 'الكتب', $label,
                        'لا يوجد وصف للكتاب — يظهر شعار الموقع العام في نتائج جوجل بدل وصف مميّز.', $url);
                } elseif (blank($book->short_description)) {
                    $out[] = new SeoFinding(SeoFinding::INFO, 'الكتب', $label,
                        'لا يوجد وصف قصير — يُشتقّ الوصف من الوصف الطويل. الأفضل كتابة وصف قصير مخصّص (~155 حرفًا).', $url);
                }

                if (blank($book->cover_image)) {
                    $out[] = new SeoFinding(SeoFinding::DANGER, 'الكتب', $label,
                        'لا توجد صورة غلاف — لن تظهر صورة عند مشاركة الرابط على واتساب/فيسبوك (og:image).', $url);
                }

                if (($len = mb_strlen($label)) > self::TITLE_MAX) {
                    $out[] = new SeoFinding(SeoFinding::WARNING, 'الكتب', $label,
                        "عنوان الكتاب طويل ({$len} حرفًا) — قد يُبتر في نتائج جوجل. الأفضل ≤ ".self::TITLE_MAX.'.', $url);
                }
            });

        return $out;
    }

    /**
     * @return array<int, SeoFinding>
     */
    private function auditArticles(): array
    {
        $out = [];

        Article::query()
            ->where('is_published', true)
            ->get(['id', 'title', 'seo_title', 'excerpt', 'content', 'cover_image'])
            ->each(function (Article $article) use (&$out): void {
                $label = (string) $article->title;
                $url = $this->editUrl(ArticleResource::class, $article->getKey());

                if (blank($article->excerpt) && blank($article->content)) {
                    $out[] = new SeoFinding(SeoFinding::DANGER, 'المقالات', $label,
                        'المقال بلا مقتطف ولا محتوى — لا يوجد وصف SEO مفيد.', $url);
                } elseif (blank($article->excerpt)) {
                    $out[] = new SeoFinding(SeoFinding::WARNING, 'المقالات', $label,
                        'لا يوجد مقتطف (excerpt) — يُشتقّ الوصف من نصّ المقال. الأفضل كتابة مقتطف موجز.', $url);
                }

                if (blank($article->cover_image)) {
                    $out[] = new SeoFinding(SeoFinding::WARNING, 'المقالات', $label,
                        'لا توجد صورة غلاف للمقال — لن تظهر صورة عند المشاركة (og:image).', $url);
                }

                $effectiveTitle = filled($article->seo_title) ? (string) $article->seo_title : $label;
                if (($len = mb_strlen($effectiveTitle)) > self::TITLE_MAX) {
                    $out[] = new SeoFinding(SeoFinding::WARNING, 'المقالات', $label,
                        "عنوان المقال طويل ({$len} حرفًا) — قد يُبتر في نتائج جوجل. الأفضل ≤ ".self::TITLE_MAX.'.', $url);
                }
            });

        return $out;
    }

    /**
     * @return array<int, SeoFinding>
     */
    private function auditPages(): array
    {
        $out = [];

        Page::query()
            ->where('is_published', true)
            ->get(['id', 'title', 'content'])
            ->each(function (Page $page) use (&$out): void {
                $label = (string) $page->title;
                $url = $this->editUrl(PageResource::class, $page->getKey());

                if (blank($page->content)) {
                    $out[] = new SeoFinding(SeoFinding::DANGER, 'الصفحات', $label,
                        'الصفحة بلا محتوى — لا يوجد وصف SEO ولا محتوى للزائر.', $url);
                }

                if (($len = mb_strlen($label)) > self::TITLE_MAX) {
                    $out[] = new SeoFinding(SeoFinding::WARNING, 'الصفحات', $label,
                        "عنوان الصفحة طويل ({$len} حرفًا) — قد يُبتر في نتائج جوجل. الأفضل ≤ ".self::TITLE_MAX.'.', $url);
                }
            });

        return $out;
    }

    /**
     * @return array<int, SeoFinding>
     */
    private function auditCategories(): array
    {
        $out = [];

        Category::query()
            ->where('is_active', true)
            ->get(['id', 'name', 'description'])
            ->each(function (Category $category) use (&$out): void {
                if (blank($category->description)) {
                    $out[] = new SeoFinding(SeoFinding::WARNING, 'الأقسام', (string) $category->name,
                        'لا يوجد وصف للقسم — يُستخدم وصف عام. الأفضل كتابة وصف مميّز لتحسين ظهوره في جوجل.',
                        $this->editUrl(CategoryResource::class, $category->getKey()));
                }
            });

        return $out;
    }

    /**
     * يكشف العناوين المكرّرة بين الصفحات المنشورة (تنافس داخلي / keyword cannibalization):
     * صفحتان بعنوان ميتا واحد تتنافسان على نفس الاستعلام في جوجل فتُضعفان بعضهما. يجزّئ على
     * **العنوان الفعّال المُصدَر فعلًا** لكل كيان (تجاوز الأدمن المخزّن ?: مشتقّ SeoDefaults)
     * مطابقًا للقوالب (books/blog/pages show)، فلا إيجابيات كاذبة عند اختلاف تجاوزَين ولا
     * سلبيات كاذبة عند توحيد عنوانين بتجاوز واحد. يحمّل أعمدة/علاقة العنوان فقط (خفيف).
     *
     * @return array<int, SeoFinding>
     */
    private function auditDuplicates(): array
    {
        /** @var array<string, array<int, array{group: string, label: string, url: string|null}>> $seen */
        $seen = [];

        $collect = function (iterable $models, string $group, string $resource, callable $effectiveTitle) use (&$seen): void {
            foreach ($models as $model) {
                $title = trim((string) $effectiveTitle($model));

                if ($title === '') {
                    continue;
                }

                $seen[mb_strtolower($title)][] = [
                    'group' => $group,
                    'label' => (string) ($model->title ?? $model->name ?? $title),
                    'url' => $this->editUrl($resource, $model->getKey()),
                ];
            }
        };

        // العنوان الفعّال = التجاوز المخزّن (seo->meta_title / seo_title) أولًا، وإلا مشتقّ
        // SeoDefaults (يُلحق العلامة) — تمامًا كما يبنيه القالب المقابل لكل نوع.
        $collect(
            Book::query()->where('is_published', true)->with('seo')->get(['id', 'title']), 'الكتب', BookResource::class,
            fn (Book $b): string => filled($b->seo?->meta_title) ? (string) $b->seo->meta_title : SeoDefaults::title($b),
        );
        $collect(
            Article::query()->where('is_published', true)->get(['id', 'title', 'seo_title']), 'المقالات', ArticleResource::class,
            fn (Article $a): string => filled($a->seo_title) ? (string) $a->seo_title : SeoDefaults::title($a),
        );
        $collect(
            Page::query()->where('is_published', true)->with('seo')->get(['id', 'title']), 'الصفحات', PageResource::class,
            fn (Page $p): string => filled($p->seo?->meta_title) ? (string) $p->seo->meta_title : SeoDefaults::title($p),
        );
        // القسم: لا يُطبّق تجاوز meta_title على <title> صفحة التصفّح (تستعمل $heading)، فالمشتقّ أقرب.
        $collect(
            Category::query()->where('is_active', true)->get(['id', 'name']), 'الأقسام', CategoryResource::class,
            fn (Category $c): string => SeoDefaults::title($c),
        );

        $out = [];

        foreach ($seen as $items) {
            if (count($items) < 2) {
                continue;
            }

            foreach ($items as $item) {
                $out[] = new SeoFinding(
                    SeoFinding::WARNING,
                    $item['group'],
                    $item['label'],
                    'عنوان مكرّر مع '.(count($items) - 1).' صفحة أخرى بنفس العنوان — تتنافس على نفس الكلمة في جوجل (تنافس داخلي). ميّز العناوين.',
                    $item['url'],
                );
            }
        }

        return $out;
    }

    /**
     * فحوص على مستوى الموقع (تُضبط من .env لا من اللوحة، فلا رابط تعديل).
     *
     * @return array<int, SeoFinding>
     */
    private function auditSite(): array
    {
        $out = [];

        if (blank(config('seo.google_site_verification'))) {
            $out[] = new SeoFinding(SeoFinding::WARNING, 'الموقع', 'Google Search Console',
                'أداة مسؤولي المواقع غير مربوطة — أضف رمز GOOGLE_SITE_VERIFICATION في إعدادات الخادم (.env) ليصل جوجل ويفهرس أسرع.');
        }

        if (blank(config('seo.indexnow_key'))) {
            $out[] = new SeoFinding(SeoFinding::INFO, 'الموقع', 'IndexNow',
                'الفهرسة الفورية غير مُفعّلة — ضبط INDEXNOW_KEY يُبلّغ Bing/Yandex فور نشر أي محتوى.');
        }

        return $out;
    }

    /**
     * رابط تعديل الكيان في اللوحة. يُبنى ضمن سياق لوحة Filament؛ خارجه (اختبار وحدة)
     * يرجع null بدل أن يفشل، فمنطق التدقيق يبقى قابلًا للاختبار مستقلًّا.
     */
    private function editUrl(string $resource, int|string $key): ?string
    {
        return rescue(fn (): string => $resource::getUrl('edit', ['record' => $key]), null, false);
    }
}
