<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\Redirect;
use Illuminate\Database\Eloquent\Model;

/**
 * يُنشئ تحويل 301 تلقائيًا عند تغيير slug النموذج، فلا يصير رابطه القديم 404 (يحفظ
 * ترتيب Google والروابط الخلفية). المستعمِل يعرّف seoUrlBase() (مثل «/books»).
 *
 * يمنع السلاسل: لو كان هناك تحويل وجهته المسار القديم، تُحدَّث وجهته للمسار الجديد.
 */
trait TracksSlugRedirects
{
    public static function bootTracksSlugRedirects(): void
    {
        static::updated(function (Model $model): void {
            if (! $model->wasChanged('slug')) {
                return;
            }

            $old = trim((string) $model->getOriginal('slug'));
            $new = trim((string) $model->getAttribute('slug'));

            if ($old === '' || $new === '' || $old === $new) {
                return;
            }

            $base = rtrim($model->seoUrlBase(), '/');
            $fromPath = Redirect::normalizePath($base . '/' . $old);
            $toPath = Redirect::normalizePath($base . '/' . $new);

            // لا تُحوِّل مسارًا إلى نفسه (بعد التطبيع).
            if ($fromPath === $toPath) {
                return;
            }

            Redirect::updateOrCreate(
                ['from_path' => $fromPath],
                [
                    'to_path' => $toPath,
                    'status_code' => 301,
                    'is_active' => true,
                    'source' => 'auto',
                ],
            );

            // اقطع السلاسل: أي تحويل كان يشير للمسار القديم يُوجَّه مباشرةً للجديد.
            Redirect::query()
                ->where('to_path', $fromPath)
                ->where('from_path', '!=', $toPath)
                ->update(['to_path' => $toPath]);
        });
    }

    /**
     * جذر المسار العام للنموذج بلا slug ولا نطاق (مثل «/books»).
     */
    abstract public function seoUrlBase(): string;
}
