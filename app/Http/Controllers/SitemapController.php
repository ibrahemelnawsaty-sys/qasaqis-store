<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Book;
use App\Models\Category;
use App\Models\Page;
use App\Models\Series;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * خريطة الموقع (sitemap.xml) وملف الروبوتس الديناميكي.
 *
 * يشمل الـ sitemap: الرئيسية، الأقسام الفعّالة، الكتب المنشورة، والصفحات
 * المنشورة. لا يُدرَج دور النشر لعدم وجود مسار عام له (تحقّق من routes/web.php).
 * الروابط مطلقة على دومين الإنتاج (config seo.site_url) مستقلًّا عن APP_URL،
 * ومخزّنة مؤقتًا ساعة (بند 5.4) لتخفيف الحمل على الشبكة الضعيفة.
 */
class SitemapController extends Controller
{
    public function index(): Response
    {
        $ttl = (int) config('seo.sitemap_ttl', 3600);

        $xml = Cache::remember('seo.sitemap.xml', $ttl, fn (): string => $this->build());

        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
        ]);
    }

    /**
     * ملف robots ديناميكي (احتياطي). في الإنتاج يخدم public/robots.txt الساكن
     * أولًا؛ يبقى هذا المسار عاملًا حين يغيب الملف الساكن (مثل php artisan serve).
     */
    public function robots(): Response
    {
        $site = (string) config('seo.site_url');

        // يطابق public/robots.txt الساكن — أي تباعد بينهما يعني سلوكًا مختلفًا بين
        // الإنتاج (الساكن) وبيئة التطوير (هذا المسار). صفحات المعاملات غير محجوبة
        // عمدًا: تحمل noindex، والحجب هنا كان سيمنع Google من رؤيته أصلًا.
        // زواحف الذكاء الاصطناعي المحجوبة (تطابق public/robots.txt) — لا تسحب المحتوى
        // والصور للتدريب. لا تمسّ Googlebot (فهرسة البحث)؛ Google-Extended للتدريب فقط.
        $aiBots = [
            'GPTBot', 'ChatGPT-User', 'OAI-SearchBot', 'CCBot', 'Google-Extended',
            'ClaudeBot', 'anthropic-ai', 'Claude-Web', 'PerplexityBot', 'Bytespider',
            'Amazonbot', 'Applebot-Extended', 'Meta-ExternalAgent', 'ImagesiftBot',
            'Diffbot', 'Omgilibot',
        ];

        $lines = [
            'User-agent: *',
            'Disallow: /admin',
            'Disallow: /search/suggest',
            'Disallow: /search/index.json',
            'Disallow: /coupon/',
            'Disallow: /inquiries',
            '',
        ];

        foreach ($aiBots as $bot) {
            $lines[] = 'User-agent: '.$bot;
        }
        $lines[] = 'Disallow: /';
        $lines[] = '';
        $lines[] = 'Sitemap: '.$site.'/sitemap.xml';
        $lines[] = '';

        $body = implode("\n", $lines);

        return response($body, 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }

    /**
     * يبني نصّ الـ XML كاملًا من عناصر URL المجمّعة.
     */
    private function build(): string
    {
        $urls = $this->urls();

        $lines = ['<?xml version="1.0" encoding="UTF-8"?>'];
        $lines[] = '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"'
            .' xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">';

        foreach ($urls as $url) {
            $lines[] = '  <url>';
            $lines[] = '    <loc>'.$this->esc($url['loc']).'</loc>';

            if (! empty($url['lastmod'])) {
                $lines[] = '    <lastmod>'.$this->esc($url['lastmod']).'</lastmod>';
            }

            $lines[] = '    <changefreq>'.$url['changefreq'].'</changefreq>';
            $lines[] = '    <priority>'.$url['priority'].'</priority>';

            // صور المنتج (أغلفة الكتب) — يساعد اكتشاف Google Images لمتجر بصريّ (371 غلافًا).
            foreach ($url['images'] ?? [] as $image) {
                $lines[] = '    <image:image><image:loc>'.$this->esc($image).'</image:loc></image:image>';
            }

            $lines[] = '  </url>';
        }

        $lines[] = '</urlset>';

        return implode("\n", $lines)."\n";
    }

    /**
     * تجميع كل الروابط القابلة للفهرسة مع lastmod من updated_at.
     *
     * @return list<array{loc:string,lastmod:?string,changefreq:string,priority:string}>
     */
    private function urls(): array
    {
        $urls = [];

        // الرئيسية: أحدث updated_at من الكتب المنشورة كتاريخ تعديل تقريبي.
        $homeLastmod = Book::query()
            ->where('is_published', true)
            ->max('updated_at');

        $urls[] = [
            'loc' => $this->abs('/'),
            'lastmod' => $this->stamp($homeLastmod),
            'changefreq' => 'daily',
            'priority' => '1.0',
        ];

        // صفحات المحاور: التصفّح الكامل والعروض وفهرس المدوّنة. الثلاثة مربوطة داخليًا
        // بكثافة (هيدر/فوتر/الشريط السفلي) لكنها كانت غائبة عن الخريطة، فبقيت ناقصة
        // أهمّ روابطها بعد الرئيسية. /offers صار مسار 200 يشير canonical إلى نفسه
        // (لا تحويلًا إلى /books كما كان)، فصار إدراجه صحيحًا.
        $urls[] = [
            'loc' => $this->abs('/books'),
            'lastmod' => $this->stamp($homeLastmod),
            'changefreq' => 'daily',
            'priority' => '0.9',
        ];

        $urls[] = [
            'loc' => $this->abs('/offers'),
            'lastmod' => $this->stamp($homeLastmod),
            'changefreq' => 'daily',
            'priority' => '0.7',
        ];

        // «وصل حديثًا» (/new): مسار 200 مفهرَس ذاتيّ المرجعيّة كـ/offers، وكان غائبًا.
        $urls[] = [
            'loc' => $this->abs('/new'),
            'lastmod' => $this->stamp($homeLastmod),
            'changefreq' => 'weekly',
            'priority' => '0.7',
        ];

        $blogLastmod = Article::query()
            ->where('is_published', true)
            ->max('updated_at');

        $urls[] = [
            'loc' => $this->abs('/blog'),
            'lastmod' => $this->stamp($blogLastmod),
            'changefreq' => 'weekly',
            'priority' => '0.8',
        ];

        // الأقسام الفعّالة (تبقى الأقسام الستة حتى الفارغة — بند 0.3).
        Category::query()
            ->where('is_active', true)
            ->select(['slug', 'updated_at'])
            ->orderBy('id')
            ->each(function (Category $category) use (&$urls): void {
                $urls[] = [
                    'loc' => $this->abs('/category/'.$category->slug),
                    'lastmod' => $this->stamp($category->updated_at),
                    'changefreq' => 'weekly',
                    'priority' => '0.7',
                ];
            });

        // الكتب المنشورة فقط.
        Book::query()
            ->where('is_published', true)
            ->select(['id', 'slug', 'updated_at', 'cover_image'])
            ->orderBy('id')
            ->each(function (Book $book) use (&$urls): void {
                // coverUrl() يعطي الرابط المخدوم فعلًا (http خارجي / أصل ثابت / مسار /media)،
                // ويرجع null بلا غلاف فلا نُصدر image:loc فارغًا.
                $cover = $book->coverUrl();

                $urls[] = [
                    'loc' => $this->abs('/books/'.$book->slug),
                    'lastmod' => $this->stamp($book->updated_at),
                    'changefreq' => 'weekly',
                    'priority' => '0.8',
                    'images' => $cover !== null ? [$cover] : [],
                ];
            });

        // صفحات CMS المنشورة فقط.
        Page::query()
            ->where('is_published', true)
            ->select(['slug', 'updated_at'])
            ->orderBy('id')
            ->each(function (Page $page) use (&$urls): void {
                $urls[] = [
                    'loc' => $this->abs('/pages/'.$page->slug),
                    'lastmod' => $this->stamp($page->updated_at),
                    'changefreq' => 'monthly',
                    'priority' => '0.5',
                ];
            });

        // مقالات المدونة المنشورة فقط (/blog/{slug}) — lastmod من updated_at + غلاف الصورة.
        Article::query()
            ->where('is_published', true)
            ->select(['slug', 'updated_at', 'cover_image'])
            ->orderBy('id')
            ->each(function (Article $article) use (&$urls): void {
                // غلاف المقال في خريطة الصور (كالكتب) — null بلا غلاف فلا نُصدر image فارغًا.
                $cover = $article->coverUrl();

                $urls[] = [
                    'loc' => $this->abs('/blog/'.$article->slug),
                    'lastmod' => $this->stamp($article->updated_at),
                    'changefreq' => 'weekly',
                    'priority' => '0.6',
                    'images' => $cover !== null ? [$cover] : [],
                ];
            });

        // سلاسل الكتب الفعّالة (/series/{slug}) — كانت غائبة عن الخريطة فبقيت شبه يتيمة.
        Series::query()
            ->where('is_active', true)
            ->select(['slug', 'updated_at'])
            ->orderBy('id')
            ->each(function (Series $series) use (&$urls): void {
                $urls[] = [
                    'loc' => $this->abs('/series/'.$series->slug),
                    'lastmod' => $this->stamp($series->updated_at),
                    'changefreq' => 'weekly',
                    'priority' => '0.6',
                ];
            });

        return $urls;
    }

    /**
     * رابط مطلق على دومين الإنتاج مستقلًّا عن APP_URL.
     */
    private function abs(string $path): string
    {
        // الجذر بلا شرطة أخيرة كي يطابق canonical الرئيسية (بايت-بايت). المسارات الأخرى كالمعتاد.
        $path = ltrim($path, '/');

        return $path === '' ? (string) config('seo.site_url') : (string) config('seo.site_url').'/'.$path;
    }

    /**
     * تنسيق التاريخ بمعيار W3C (ISO 8601) المقبول في بروتوكول sitemap.
     */
    private function stamp(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return $value instanceof Carbon
            ? $value->toAtomString()
            : Carbon::parse((string) $value)->toAtomString();
    }

    /**
     * هروب XML للـ <loc> (الروابط قد تحوي & أو رموزًا خاصة).
     */
    private function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
