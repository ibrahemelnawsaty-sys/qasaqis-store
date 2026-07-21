<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * بديل cron ذاتيّ بلا أي طرف خارجي: كل طلب ويب يدقّ المُجدول **بعد** إرسال الرد
 * للزائر (في terminate، عبر fastcgi_finish_request) فلا يتأخّر تصفّحه إطلاقًا.
 *
 * قفل ذرّي (Cache::add) يضمن نبضة **واحدة كل ~دقيقة فقط** ولا تداخل مهما كثرت
 * الطلبات المتزامنة. الآلية تعتمد على مرور زيارات المتجر أو نشاطك في اللوحة لتدقّ؛
 * وحين لا يوجد شيء في الطابور يخرج queue:work فورًا (--stop-when-empty) فالنبضة
 * شبه فورية في الوضع العادي، ولا تطول إلا أثناء إرسال فعلي.
 *
 * مسجّل عالميًا في bootstrap/app.php فيغطّي المتجر واللوحة معًا. يبقى مسار
 * /tasks/run/{token} متاحًا لدفعة يدوية عند الحاجة.
 */
class KeepQueueAlive
{
    /**
     * مفتاح القفل ومدّته (ثوانٍ). أقلّ من 60 كي لا تتّسع الفجوة عن دقيقة.
     */
    private const LOCK_KEY = 'qa:cron-tick';

    private const LOCK_SECONDS = 50;

    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        // لا ندقّ أثناء الاختبارات كي لا يُشغّل schedule:run الطابور في كل طلب اختبار.
        if (app()->runningUnitTests()) {
            return;
        }

        $this->tickOnce();
    }

    /**
     * نبضة واحدة محميّة بقفل ذرّي — عامّة كي تُختبَر مباشرةً دون حارس بيئة الاختبار.
     */
    public function tickOnce(): void
    {
        // Cache::add ذرّي: يعيد true لأوّل طلب فقط في النافذة (لا يوجد المفتاح)،
        // ويحجزه LOCK_SECONDS. بقية الطلبات ترى المفتاح فتتخطّى بلا عمل.
        if (! Cache::add(self::LOCK_KEY, 1, self::LOCK_SECONDS)) {
            return;
        }

        try {
            // لا نتوقّف إن أُغلق الاتصال، وبلا سقف PHP (سقف queue:work الداخلي يحدّها).
            ignore_user_abort(true);
            @set_time_limit(0);

            Artisan::call('schedule:run');
        } catch (Throwable $e) {
            // الرد أُرسل بالفعل؛ نسجّل الخطأ فقط دون التأثير على الزائر.
            report($e);
        }
    }
}
