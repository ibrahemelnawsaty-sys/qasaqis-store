<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Article;
use App\Models\Book;
use App\Models\Category;
use App\Models\Page;
use App\Models\Series;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Console\Command;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * زاحف صحّة الروابط: يتأكّد أن كل صفحة محتوى منشور (كتاب/مقال/قسم/سلسلة/صفحة) +
 * صفحات المحاور تُرجع 2xx، فيكشف الصفحات المكسورة (404/500) التي لا يراها المدقّق
 * (يفحص وجود الحقول لا حياة الصفحة). يُشغَّل مجدولًا وينبّه الأدمن — «يعمل لوحده».
 *
 * يطلب الصفحات عبر HTTP على دومين الإنتاج (config seo.site_url). ملاحظة نشر: إن حجب
 * جدار الحماية (Cloudflare) طلبات الخادم لنفسه، اسمح للـUA أدناه. الطلبات GET فقط.
 */
class CheckLinksCommand extends Command
{
    protected $signature = 'seo:check-links
        {--notify : أرسل تنبيه أدمن عند وجود روابط مكسورة}
        {--limit=1500 : أقصى عدد روابط يُفحص (حماية من الحمل)}';

    protected $description = 'يفحص أن صفحات المحتوى المنشور تُرجع 2xx (كشف الروابط المكسورة) وينبّه الأدمن.';

    private const CHUNK = 10;

    private const TIMEOUT = 15;

    private const UA = 'Mozilla/5.0 (compatible; QasaqisLinkCheck/1.0)';

    public function handle(): int
    {
        $urls = $this->collectUrls();
        $limit = max(1, (int) $this->option('limit'));

        if (count($urls) > $limit) {
            $this->warn(sprintf('الروابط (%d) تتجاوز الحدّ (%d) — يُفحص الأوائل فقط.', count($urls), $limit));
            $urls = array_slice($urls, 0, $limit);
        }

        $broken = [];

        foreach (array_chunk($urls, self::CHUNK) as $chunk) {
            $responses = Http::pool(fn (Pool $pool): array => array_map(
                fn (string $url) => $pool->withUserAgent(self::UA)->timeout(self::TIMEOUT)->get($url),
                $chunk,
            ));

            foreach ($chunk as $i => $url) {
                $res = $responses[$i] ?? null;
                $status = $res instanceof Response ? $res->status() : 0;

                // ناجح = 2xx بعد اتّباع التحويلات (301 كتاب مُعاد تسميته يتبع لصفحته الحيّة).
                if ($status < 200 || $status >= 400) {
                    $broken[] = ['url' => $url, 'status' => $status];
                }
            }
        }

        $this->line(sprintf('فحص الروابط: %d رابطًا مفحوصًا · %d مكسور.', count($urls), count($broken)));

        if ($broken === []) {
            return self::SUCCESS;
        }

        foreach (array_slice($broken, 0, 20) as $b) {
            $this->warn(sprintf('  ✗ [%s] %s', $b['status'] ?: 'اتصال', $b['url']));
        }

        Log::warning('SEO check-links: روابط مكسورة', [
            'count' => count($broken),
            'sample' => array_slice($broken, 0, 20),
        ]);

        if ($this->option('notify')) {
            $this->alertAdmins($broken);
        }

        return self::SUCCESS;
    }

    /**
     * كل مسارات المحتوى العام المنشور + صفحات المحاور، كروابط مطلقة على دومين الإنتاج.
     *
     * @return list<string>
     */
    private function collectUrls(): array
    {
        $base = rtrim((string) config('seo.site_url'), '/');

        $paths = array_merge(
            ['/', '/books', '/offers', '/blog'],
            Book::query()->where('is_published', true)->orderBy('id')->pluck('slug')->map(fn ($s): string => '/books/'.$s)->all(),
            Article::query()->where('is_published', true)->orderBy('id')->pluck('slug')->map(fn ($s): string => '/blog/'.$s)->all(),
            Category::query()->where('is_active', true)->orderBy('id')->pluck('slug')->map(fn ($s): string => '/category/'.$s)->all(),
            Series::query()->where('is_active', true)->orderBy('id')->pluck('slug')->map(fn ($s): string => '/series/'.$s)->all(),
            Page::query()->where('is_published', true)->orderBy('id')->pluck('slug')->map(fn ($s): string => '/pages/'.$s)->all(),
        );

        return array_values(array_map(
            fn (string $p): string => $base.$p,
            array_filter($paths, fn ($p): bool => is_string($p) && $p !== ''),
        ));
    }

    /**
     * تنبيه جرس Filament للأدمن النشِط المسؤول عن الظهور. مغلّف بـrescue فلا يُسقط الفحص.
     *
     * @param  list<array{url:string,status:int}>  $broken
     */
    private function alertAdmins(array $broken): void
    {
        rescue(function () use ($broken): void {
            $admins = User::query()
                ->where('is_active', true)
                ->whereHas('roles', fn ($q) => $q->whereIn('name', ['super_admin', 'admin', 'marketing']))
                ->get();

            if ($admins->isEmpty()) {
                return;
            }

            Notification::make()
                ->title('روابط مكسورة على الموقع')
                ->body(sprintf(
                    '%d صفحة منشورة لا تُرجع نجاحًا (404/خطأ). راجع سجلّ seo:check-links. أوّلها: %s',
                    count($broken), $broken[0]['url'],
                ))
                ->danger()
                ->icon('heroicon-o-link-slash')
                ->sendToDatabase($admins);
        }, report: false);
    }
}
