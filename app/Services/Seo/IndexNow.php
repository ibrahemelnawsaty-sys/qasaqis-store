<?php

declare(strict_types=1);

namespace App\Services\Seo;

use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * إرسال فوري لروابط المحتوى إلى IndexNow (Bing/Yandex) عند نشرها/تعديلها — فتُفهرَس
 * خلال دقائق بدل انتظار الزحف الدوري. Google لا يدعم IndexNow (لا مكافئ له لأي موقع).
 *
 * معطّل ما لم يُضبط seo.indexnow_key. الفشل لا يكسر شيئًا (المحرّكات تزحف لاحقًا).
 */
final class IndexNow
{
    private const ENDPOINT = 'https://api.indexnow.org/indexnow';

    /**
     * @param  list<string>  $urls
     */
    public function submit(array $urls): void
    {
        $key = trim((string) config('seo.indexnow_key'));
        $urls = array_values(array_filter($urls, static fn ($u): bool => filled($u)));

        if ($key === '' || $urls === []) {
            return;
        }

        $siteUrl = rtrim((string) config('seo.site_url'), '/');
        $host = (string) parse_url($siteUrl, PHP_URL_HOST);

        try {
            Http::timeout(8)->acceptJson()->post(self::ENDPOINT, [
                'host' => $host,
                'key' => $key,
                'keyLocation' => $siteUrl . '/' . $key . '.txt',
                'urlList' => $urls,
            ]);
        } catch (Throwable $e) {
            // فشل الإبلاغ لا يوقف شيئًا — نسجّله فقط.
            report($e);
        }
    }
}
