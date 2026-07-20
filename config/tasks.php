<?php

declare(strict_types=1);

/*
| مشغّل المهام المجدولة عبر HTTP — بديل cron حين تمنعه الاستضافة.
| اضبط TASK_RUNNER_TOKEN في .env بقيمة عشوائية طويلة (48+ حرفًا). فارغ = المسار معطّل.
*/
return [
    'runner_token' => env('TASK_RUNNER_TOKEN', ''),
];
