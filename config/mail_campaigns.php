<?php

declare(strict_types=1);

/**
 * إعدادات المُرسِل الجماعي. اضبط per_minute/hourly_cap **تحت** سقف مزوّد SMTP
 * (Hostinger) وإلا حُظِر الحساب. تُقرأ عبر config() فتنجو من config:cache.
 */
return [
    // طابور منفصل عن default حتى لا تتأخّر رسائل المعاملات خلف دفعة تسويقية.
    'queue' => env('CAMPAIGN_QUEUE', 'campaigns'),

    // معدّل الإرسال: يُترجَم إلى تأخير متدرّج على مهام الطابور (بلا اعتماد على cache).
    'per_minute' => (int) env('CAMPAIGN_PER_MINUTE', 30),

    // سقف ساعي احتياطي (يُستعمل مع RateLimiter إن فُضّل على التأخير المتدرّج).
    'hourly_cap' => (int) env('CAMPAIGN_HOURLY_CAP', 100),

    // عنوان الردّ على الحملات (Reply-To) — بريد حقيقي يتابعه الفريق.
    'reply_to' => env('CAMPAIGN_REPLY_TO'),
];
