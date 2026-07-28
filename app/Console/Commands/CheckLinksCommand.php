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
 * **لطيف عمدًا على الاستضافة المشتركة:** كل طلب وارد يقرأ الجلسة والكاش (كلاهما DB)
 * فتوازٍ عالٍ يُرهق حدّ اتصالات MySQL فتُرفَض الطلبات بـ[2002] (500 كاذب). لذا التوازي
 * منخفض افتراضيًّا (3) مع تهدئة بين الدفعات، و**إعادة محاولة تسلسلية** للمشتبَه بها
 * (0/5xx) تُميّز الكسر الحقيقيّ من إرهاق اتصال عابر — فلا إيجابيات كاذبة.
 *
 * يطلب عبر HTTP على دومين الإنتاج (config seo.site_url). GET فقط. ملاحظة نشر: إن حجب
 * جدار الحماية (Cloudflare) طلبات الخادم لنفسه، اسمح للـUA أدناه.
 */
class CheckLinksCommand extends Command
{
    protected $signature = 'seo:check-links
        {--notify : أرسل تنبيه أدمن عند وجود روابط مكسورة}
        {--limit=1500 : أقصى عدد روابط يُفحص (حماية من الحمل)}
        {--concurrency=3 : عدد الطلبات المتوازية (منخفض عمدًا كي لا يُرهق اتصالات DB على الاستضافة المشتركة)}';

    protected $description = 'يفحص أن صفحات المحتوى المنشور تُرجع 2xx (كشف الروابط المكسورة) وينبّه الأدمن — لطيف على DB.';

    private const TIMEOUT = 15;

    /** تهدئة بين الدفعات (ميكروثانية): تُحرّر اتصالات DB فلا تُرفَض الطلبات التالية. */
    private const PACE_US = 250_000;

    private const UA = 'Mozilla/5.0 (compatible; QasaqisLinkCheck/1.0)';

    public function handle(): int
    {
        $urls = $this->collectUrls();
        $limit = max(1, (int) $this->option('limit'));

        if (count($urls) > $limit) {
            $this->warn(sprintf('الروابط (%d) تتجاوز الحدّ (%d) — يُفحص الأوائل فقط.', count($urls), $limit));
            $urls = array_slice($urls, 0, $limit);
        }

        $concurrency = max(1, (int) $this->option('concurrency'));

        // فحص متوازٍ لطيف. 4xx كسرٌ مؤكّد فورًا. أمّا 0 (تعذّر اتصال) و5xx فقد تكون إرهاق
        // اتصال عابرًا أثناء التوازي لا كسرًا حقيقيًّا — تُجمَع «مشتبَهًا بها» وتُعاد سريالًا.
        $broken = [];
        $suspect = [];

        foreach (array_chunk($urls, $concurrency) as $chunk) {
            foreach ($this->fetch($chunk) as $url => $status) {
                if ($status === 0 || $status >= 500) {
                    $suspect[$url] = $status;
                } elseif ($status < 200 || $status >= 400) {
                    $broken[$url] = $status;
                }
            }

            usleep(self::PACE_US);
        }

        // إعادة محاولة المشتبَه بها تسلسليًّا (طلب واحد بعد أن هدأ الضغط): الكسر الحقيقيّ
        // يبقى فاشلًا، وإرهاق الاتصال العابر يتعافى (2xx) فيُستبعَد — يُلغي الإيجابيات الكاذبة.
        foreach (array_keys($suspect) as $url) {
            usleep(self::PACE_US);

            $res = rescue(fn () => Http::withUserAgent(self::UA)->timeout(self::TIMEOUT)->get($url), null, report: false);
            $status = $res instanceof Response ? $res->status() : 0;

            if ($status < 200 || $status >= 400) {
                $broken[$url] = $status;
            }
        }

        $this->line(sprintf('فحص الروابط: %d رابطًا مفحوصًا · %d مكسور (بعد تأكيد تسلسليّ).', count($urls), count($broken)));

        if ($broken === []) {
            return self::SUCCESS;
        }

        $sample = array_slice($broken, 0, 20, true);

        foreach ($sample as $url => $status) {
            $this->warn(sprintf('  ✗ [%s] %s', $status !== 0 ? $status : 'اتصال', $url));
        }

        Log::warning('SEO check-links: روابط مكسورة', [
            'count' => count($broken),
            'sample' => $sample,
        ]);

        if ($this->option('notify')) {
            $this->alertAdmins($broken);
        }

        return self::SUCCESS;
    }

    /**
     * جلب دفعة روابط متوازيًا. يُرجِع خريطة url => رمز الحالة (0 عند تعذّر الاتصال).
     *
     * @param  list<string>  $chunk
     * @return array<string, int>
     */
    private function fetch(array $chunk): array
    {
        // ->as($url) يجعل الردود مفهرَسة بالرابط لا بالترتيب، فلا خلط عند الفشل الجزئي.
        $responses = Http::pool(fn (Pool $pool): array => array_map(
            fn (string $url) => $pool->as($url)->withUserAgent(self::UA)->timeout(self::TIMEOUT)->get($url),
            $chunk,
        ));

        $out = [];

        foreach ($chunk as $url) {
            $res = $responses[$url] ?? null;
            $out[$url] = $res instanceof Response ? $res->status() : 0;
        }

        return $out;
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
     * @param  array<string, int>  $broken  خريطة url => رمز الحالة
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
                    count($broken), (string) array_key_first($broken),
                ))
                ->danger()
                ->icon('heroicon-o-link-slash')
                ->sendToDatabase($admins);
        }, report: false);
    }
}
