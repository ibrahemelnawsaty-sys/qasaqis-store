<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Jobs\PingIndexNowJob;
use Illuminate\Database\Eloquent\Model;

/**
 * يُبلّغ IndexNow برابط النموذج العام عند نشره/تعديله فيُفهرَس في Bing/Yandex فورًا.
 * المستعمِل يعرّف indexNowUrl() (يعيد الرابط المطلق إن كان منشورًا، وإلا null).
 *
 * لا إرسال إن لم يُضبط المفتاح، ولا على حفظ بلا تغيير فعليّ (لتفادي الإغراق).
 */
trait NotifiesIndexNow
{
    public static function bootNotifiesIndexNow(): void
    {
        static::saved(function (Model $model): void {
            if (blank(config('seo.indexnow_key'))) {
                return;
            }

            /** @var string|null $url */
            $url = $model->indexNowUrl();

            if ($url === null) {
                return; // غير منشور/بلا رابط عام.
            }

            // جديد أو تغيّر فعلًا فقط (increment الصامت للمشاهدات لا يُطلق saved أصلًا).
            if (! $model->wasRecentlyCreated && ! $model->wasChanged()) {
                return;
            }

            PingIndexNowJob::dispatch([$url]);
        });
    }

    /**
     * الرابط العام المطلق للنموذج إن كان قابلًا للفهرسة، وإلا null.
     */
    abstract public function indexNowUrl(): ?string;
}
