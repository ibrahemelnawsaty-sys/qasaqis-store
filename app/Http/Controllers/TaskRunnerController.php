<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\Artisan;

/**
 * تشغيل المهام المجدولة عبر نداء HTTP — بديل cron حين تمنعه الاستضافة ولا تتوفّر لوحة.
 * تناديه خدمة نبض خارجية (مثل cron-job.org) كل دقيقة على رابط يحمل التوكن السرّي،
 * فيشغّل schedule:run (ومنه queue:work) تمامًا كما يفعل cron: الحملات، تأكيدات
 * الطلبات، إلغاء الطلبات المهجورة، والنسخ الاحتياطية عند مواعيدها.
 *
 * الأمان:
 *  - التوكن (طويل عشوائي في .env) هو المفتاح؛ مقارنة زمن-ثابت (hash_equals).
 *  - 404 عند غياب/خطأ التوكن فلا يكشف وجود المسار ولا يميّز «خطأ» عن «غير موجود».
 *  - throttle على المسار يمنع الإغراق، ولا جلسة (يُسقَط StartSession في المسار).
 */
class TaskRunnerController extends Controller
{
    public function __invoke(string $token): Response
    {
        $expected = (string) config('tasks.runner_token', '');

        // معطّل إن لم يُضبط التوكن، ولا يُقبل إلا التطابق التامّ (زمن ثابت).
        abort_if($expected === '' || ! hash_equals($expected, $token), 404);

        // لا نتوقّف إن قطع النابض الاتصال، وبلا سقف زمني PHP كي تكتمل الدفعة
        // (سقف queue:work الداخلي --max-time=55 يظلّ يحدّها).
        ignore_user_abort(true);
        @set_time_limit(0);

        Artisan::call('schedule:run');

        return response('ok', 200)->header('Content-Type', 'text/plain; charset=utf-8');
    }
}
