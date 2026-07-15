<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Book;
use App\Models\Category;
use App\Models\Page;
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

        $body = implode("\n", [
            'User-agent: *',
            'Disallow: /admin',
            '',
            'Sitemap: '.$site.'/sitemap.xml',
            '',
        ]);

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
        $lines[] = '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

        foreach ($urls as $url) {
            $lines[] = '  <url>';
            $lines[] = '    <loc>'.$this->esc($url['loc']).'</loc>';

            if (! empty($url['lastmod'])) {
                $lines[] = '    <lastmod>'.$this->esc($url['lastmod']).'</lastmod>';
            }

            $lines[] = '    <changefreq>'.$url['changefreq'].'</changefreq>';
            $lines[] = '    <priority>'.$url['priority'].'</priority>';
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
            ->select(['slug', 'updated_at'])
            ->orderBy('id')
            ->each(function (Book $book) use (&$urls): void {
                $urls[] = [
                    'loc' => $this->abs('/books/'.$book->slug),
                    'lastmod' => $this->stamp($book->updated_at),
                    'changefreq' => 'weekly',
                    'priority' => '0.8',
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

        // مقالات المدونة المنشورة فقط (/blog/{slug}) — lastmod من updated_at.
        Article::query()
            ->where('is_published', true)
            ->select(['slug', 'updated_at'])
            ->orderBy('id')
            ->each(function (Article $article) use (&$urls): void {
                $urls[] = [
                    'loc' => $this->abs('/blog/'.$article->slug),
                    'lastmod' => $this->stamp($article->updated_at),
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
        return (string) config('seo.site_url').'/'.ltrim($path, '/');
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
