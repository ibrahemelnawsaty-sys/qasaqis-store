<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\Seo\IndexNow;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * يُبلّغ IndexNow (Bing/Yandex) بروابط تغيّرت. مُصفّف كي لا يؤخّر حفظ الأدمن ولا
 * يتأثّر بتأخّر الشبكة الخارجية.
 */
class PingIndexNowJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 2;

    /**
     * @param  list<string>  $urls
     */
    public function __construct(public array $urls) {}

    public function handle(IndexNow $indexNow): void
    {
        $indexNow->submit($this->urls);
    }
}
