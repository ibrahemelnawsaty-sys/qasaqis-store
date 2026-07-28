<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// تدقيق SEO تلقائي يوميًّا: يُنعش شارة اللوحة وينبّه الأدمن عند مشاكل حرِجة جديدة
// (يجعل المدقّق «يعمل لوحده» بلا فتح اللوحة). يتطلّب مُجدوِل Laravel على الخادم:
// «* * * * * php artisan schedule:run».
Schedule::command('seo:audit --notify')
    ->dailyAt('06:00')
    ->withoutOverlapping();

// فحص صحّة الروابط أسبوعيًّا (الإثنين): كل صفحة منشورة تُرجع 2xx، وإلا تنبيه أدمن.
// أثقل من التدقيق (طلبات HTTP للصفحات) فأُسبوعيّ لا يوميّ. يتطلّب المُجدوِل نفسه.
Schedule::command('seo:check-links --notify')
    ->weeklyOn(1, '06:30')
    ->withoutOverlapping();
