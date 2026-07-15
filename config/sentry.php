<?php

/*
 |--------------------------------------------------------------------------
 | إعداد رصد الأخطاء (sentry/sentry-laravel v4)
 |--------------------------------------------------------------------------
 | مُؤلَّف على قالب الحزمة الرسمي. بعد `composer require` على السيرفر يُفضَّل
 | تشغيل: php artisan sentry:publish --dsn=... ومقارنة الناتج بهذا الملف.
 |
 | مبدأ الخصوصية (الدستور 4): send_default_pii=false افتراضيًا كي لا تُرسَل
 | بيانات المستخدمين (بريد/IP/جسم الطلب) إلى Sentry دون قصد. المفاتيح كلها
 | من .env — لا أسرار في الكود.
 */

return [

    // DSN من .env؛ إن كان فارغًا يتعطّل الإرسال بصمت دون كسر التطبيق.
    'dsn' => env('SENTRY_LARAVEL_DSN', env('SENTRY_DSN')),

    'release' => env('SENTRY_RELEASE'),

    'environment' => env('SENTRY_ENVIRONMENT', env('APP_ENV', 'production')),

    // نسبة أخذ عيّنات الأخطاء (1.0 = كل الأخطاء).
    'sample_rate' => env('SENTRY_SAMPLE_RATE') === null
        ? 1.0
        : (float) env('SENTRY_SAMPLE_RATE'),

    // تتبّع الأداء — منخفض/معطّل افتراضيًا لتوفير حصة الخطة.
    'traces_sample_rate' => env('SENTRY_TRACES_SAMPLE_RATE') === null
        ? null
        : (float) env('SENTRY_TRACES_SAMPLE_RATE'),

    'profiles_sample_rate' => env('SENTRY_PROFILES_SAMPLE_RATE') === null
        ? null
        : (float) env('SENTRY_PROFILES_SAMPLE_RATE'),

    // خصوصية: لا تُرسل بيانات تعريفية للمستخدم افتراضيًا.
    'send_default_pii' => (bool) env('SENTRY_SEND_DEFAULT_PII', false),

    'ignore_exceptions' => [],

    'ignore_transactions' => [],

    'breadcrumbs' => [
        'logs' => true,
        'cache' => true,
        'livewire' => true,
        'sql_queries' => true,
        // لا نسجّل قيم استعلامات SQL (قد تحمل PII).
        'sql_bindings' => false,
        'queue_info' => true,
        'command_info' => true,
        'http_client_requests' => true,
        'notifications' => true,
    ],

    'tracing' => [
        'queue_job_transactions' => (bool) env('SENTRY_TRACE_QUEUE_ENABLED', false),
        'queue_jobs' => true,
        'sql_queries' => true,
        // لا نلتقط قيم الربط في آثار الأداء (خصوصية).
        'sql_bindings' => false,
        'redis_commands' => (bool) env('SENTRY_TRACE_REDIS_COMMANDS', false),
        'http_client_requests' => true,
        'views' => true,
        'livewire' => true,
        'default_integrations' => true,
        'missing_routes' => false,
        'continue_after_response' => true,
        'enable_decompression' => true,
    ],
];
