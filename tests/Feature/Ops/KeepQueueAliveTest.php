<?php

declare(strict_types=1);

namespace Tests\Feature\Ops;

use App\Http\Middleware\KeepQueueAlive;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * بديل cron الذاتيّ: النبضة تشغّل المُجدول مرّة واحدة فقط في النافذة (قفل ذرّي).
 */
class KeepQueueAliveTest extends TestCase
{
    public function test_handle_passes_the_request_through_untouched(): void
    {
        Cache::flush();
        $mw = new KeepQueueAlive();
        $request = Request::create('/', 'GET');
        $sentinel = new Response('body', 200);

        $result = $mw->handle($request, fn () => $sentinel);

        $this->assertSame($sentinel, $result);
    }

    public function test_tick_runs_scheduler_once_per_window(): void
    {
        Cache::flush();
        // مرّتان متتاليتان: القفل يسمح بواحدة فقط. (نختبر tickOnce مباشرةً لأن
        // terminate يتخطّى عمدًا في بيئة الاختبار.)
        Artisan::shouldReceive('call')->once()->with('schedule:run')->andReturn(0);

        $mw = new KeepQueueAlive();

        $mw->tickOnce(); // يدقّ (القفل حرّ)
        $mw->tickOnce(); // يتخطّى (القفل مأخوذ)
    }

    public function test_terminate_is_a_noop_under_tests(): void
    {
        Cache::flush();
        // في الاختبارات لا يجب أن يُستدعى المُجدول إطلاقًا من terminate.
        Artisan::shouldReceive('call')->never();

        (new KeepQueueAlive())->terminate(Request::create('/', 'GET'), new Response('ok', 200));
    }
}
