<?php

/*
 |--------------------------------------------------------------------------
 | إعداد النسخ الاحتياطي (spatie/laravel-backup v9)
 |--------------------------------------------------------------------------
 | هذا الملف مُؤلَّف على قالب الحزمة الرسمي مع تخصيصات المشروع (المصادر،
 | قرص الوجهة، التشفير، الإشعارات، سياسة التنظيف). بعد `composer require`
 | على السيرفر يُفضَّل تشغيل:
 |   php artisan vendor:publish --provider="Spatie\Backup\BackupServiceProvider"
 | ومقارنة الناتج بهذا الملف للتأكد من مطابقة البنية لإصدار الحزمة المثبَّت.
 |
 | ما يُنسَخ: قاعدة البيانات كاملة + مجلد إثباتات الدفع فقط. الكود لا يُنسَخ
 | (محفوظ في Git). الوجهة خارجية (قرص backup → Cloudflare R2). الأرشيف
 | مشفَّر بكلمة مرور من .env (الدستور 3.4 + 4.3).
 */

return [

    'backup' => [

        // اسم ASCII ثابت (لا APP_NAME العربي) كي لا يصير اسم الأرشيف/بادئة
        // مفتاح R2 عربيًا فيصعب ترميزه/استرجاعه عبر أدوات S3.
        'name' => env('BACKUP_NAME', 'qasaqis'),

        'source' => [

            'files' => [

                /*
                 * إثباتات الدفع اليدوي فقط (storage/app/private/payment-proofs).
                 * لا نُدرج قاعدة الكود (في Git) ولا الأصول العامة.
                 */
                'include' => [
                    storage_path('app/private/payment-proofs'),
                ],

                'exclude' => [
                    base_path('vendor'),
                    base_path('node_modules'),
                ],

                'follow_links' => false,
                'ignore_unreadable_directories' => false,
                'relative_path' => null,
            ],

            /*
             * اتصال قاعدة البيانات الفعلي (mysql في الإنتاج) — يُقرأ من الإعداد
             * ولا يُثبَّت اسمه في الكود.
             */
            'databases' => [
                config('database.default'),
            ],
        ],

        'database_dump_compressor' => null,

        'database_dump_file_timestamp_format' => null,

        'database_dump_filename_base' => 'database',

        'database_dump_file_extension' => '',

        'destination' => [

            'compression_method' => ZipArchive::CM_DEFAULT,

            'compression_level' => 9,

            'filename_prefix' => 'qasaqis-',

            /*
             * قرص الوجهة الخارجي المعرَّف في config/filesystems.php.
             */
            'disks' => [
                'backup',
            ],
        ],

        'temporary_directory' => storage_path('app/backup-temp'),

        /*
         * تشفير الأرشيف بكلمة مرور من .env (AES-256 افتراضي الحزمة). إن تُركت
         * فارغة يُنشأ أرشيف غير مشفَّر مع تحذير — يجب ضبطها في الإنتاج.
         */
        'password' => env('BACKUP_ARCHIVE_PASSWORD'),

        'encryption' => 'default',

        'tries' => 1,

        'retry_delay' => 0,
    ],

    'notifications' => [

        // إشعارات الفشل/الاعتلال فقط عبر البريد (+ قناة Sentry مستقلة في
        // AppServiceProvider). إشعارات النجاح مُطفأة ([]) لتفادي ~4 رسائل/يوم
        // تُغرق تنبيه الفشل الحقيقي وتدفع لتجاهله.
        'notifications' => [
            \Spatie\Backup\Notifications\Notifications\BackupHasFailedNotification::class => ['mail'],
            \Spatie\Backup\Notifications\Notifications\UnhealthyBackupWasFoundNotification::class => ['mail'],
            \Spatie\Backup\Notifications\Notifications\CleanupHasFailedNotification::class => ['mail'],
            \Spatie\Backup\Notifications\Notifications\BackupWasSuccessfulNotification::class => [],
            \Spatie\Backup\Notifications\Notifications\HealthyBackupWasFoundNotification::class => [],
            \Spatie\Backup\Notifications\Notifications\CleanupWasSuccessfulNotification::class => [],
        ],

        'notifiable' => \Spatie\Backup\Notifications\Notifiable::class,

        'mail' => [
            'to' => env('BACKUP_NOTIFICATION_EMAIL'),

            'from' => [
                'address' => env('MAIL_FROM_ADDRESS', 'hello@qasaqis.store'),
                'name' => env('MAIL_FROM_NAME', env('APP_NAME', 'قصص أطفال')),
            ],
        ],

        'slack' => [
            'webhook_url' => '',
            'channel' => null,
            'username' => null,
            'icon' => null,
        ],

        'discord' => [
            'webhook_url' => '',
            'username' => '',
            'avatar_url' => '',
        ],
    ],

    'monitor_backups' => [
        [
            'name' => env('BACKUP_NAME', 'qasaqis'),
            'disks' => ['backup'],
            'health_checks' => [
                \Spatie\Backup\Tasks\Monitor\HealthChecks\MaximumAgeInDays::class => 1,
                \Spatie\Backup\Tasks\Monitor\HealthChecks\MaximumStorageInMegabytes::class => 5000,
            ],
        ],
    ],

    'cleanup' => [

        'strategy' => \Spatie\Backup\Tasks\Cleanup\Strategies\DefaultStrategy::class,

        'default_strategy' => [

            'keep_all_backups_for_days' => 7,

            'keep_daily_backups_for_days' => 16,

            'keep_weekly_backups_for_weeks' => 8,

            'keep_monthly_backups_for_months' => 4,

            'keep_yearly_backups_for_years' => 2,

            'delete_oldest_backups_when_using_more_megabytes_than' => 5000,
        ],

        'tries' => 1,

        'retry_delay' => 0,
    ],
];
