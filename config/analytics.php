<?php

declare(strict_types=1);

/*
| إعدادات التحليلات والبكسل (M6). المعرّفات من env فقط (بند 4.3: لا أسرار في
| الكود). أسرار الخادم (api_secret / capi_token) لا تُشارَك أبدًا مع الواجهة.
| البوابة العامة enabled تُبقيها معطّلة حتى التحقق في الإنتاج.
*/

return [

    'enabled' => (bool) env('ANALYTICS_ENABLED', false),

    'currency' => env('ANALYTICS_CURRENCY', 'EGP'),

    'ga4' => [
        'measurement_id' => env('GA4_ID'),      // عام (يُحقن في الصفحة).
        'api_secret' => env('GA4_API_SECRET'),  // خادمي فقط (Measurement Protocol).
    ],

    'meta' => [
        'pixel_id' => env('META_PIXEL_ID'),                  // عام.
        'capi_token' => env('META_CAPI_TOKEN'),              // خادمي فقط (CAPI).
        'test_event_code' => env('META_CAPI_TEST_EVENT_CODE'), // اختبار CAPI.
    ],

];
