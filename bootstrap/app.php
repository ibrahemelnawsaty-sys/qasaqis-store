<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Sentry\Laravel\Integration;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        //
    })
    /*
     | جدولة المهام. يُشغّلها إدخال cron وحيد على الاستضافة:
     |   * * * * * cd /path && php artisan schedule:run >> /dev/null 2>&1
     | هذا الإدخال يخدم النسخ الاحتياطي، الطابور، ومهام أخرى (مثل مهلة
     | المخزون التي يضيفها معلَم M2). التوقيت بتوقيت القاهرة (APP_TIMEZONE).
     */
    ->withSchedule(function (Schedule $schedule) {
        // تنظيف النسخ القديمة وفق سياسة الاحتفاظ ثم أخذ نسخة قاعدة البيانات
        // ثم النسخة الكاملة (قاعدة البيانات + إثباتات الدفع) في وقت الهدوء.
        // مهلة القفل (بالدقائق) تُحرّره حتى لو قُتل الأمر قسريًا دون تنظيف —
        // فلا يبقى عالقًا 24 ساعة (السلوك الافتراضي) ويُخطّى نسخ الغد.
        $schedule->command('backup:clean')
            ->daily()->at('03:30')
            ->withoutOverlapping(60);

        $schedule->command('backup:run --only-db')
            ->daily()->at('03:45')
            ->withoutOverlapping(60);

        $schedule->command('backup:run')
            ->daily()->at('04:00')
            ->withoutOverlapping(120);

        // مراقبة سلامة آخر نسخة (عمرها/حجمها) وإشعار عند الخلل.
        $schedule->command('backup:monitor')
            ->daily()->at('09:00');

        // معالجة الطابور على الاستضافة المشتركة دون Supervisor:
        // يُفرَّغ ما تراكم ثم يتوقف، ويُعاد تشغيله كل دقيقة عبر المُجدول.
        // مهلة قفل قصيرة (دقيقتان > max-time=55ث) كي لا يتجمّد الطابور 24 ساعة
        // إن قُتل العامل قسريًا (OOM/إعادة تشغيل) دون تحرير القفل.
        $schedule->command('queue:work --stop-when-empty --tries=3 --max-time=55')
            ->everyMinute()
            ->withoutOverlapping(2);

        // إلغاء الطلبات المهجورة (أونلاين غير مدفوع أو تحويل يدوي بلا إثبات)
        // بعد المهلة وتحرير مخزونها (M2).
        $schedule->command('orders:cancel-expired')
            ->hourly()
            ->withoutOverlapping(10);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // تسليم الاستثناءات إلى Sentry (النمط الرسمي لـ Laravel 11).
        Integration::handles($exceptions);
    })->create();
