<?php

declare(strict_types=1);

/*
| إعدادات الدفع. الأسرار (مفاتيح Paymob/Kashier) تُقرأ من .env فقط —
| يُمنع كتابتها في الكود أو الـ Git (بند 4.3). البوابات تقرأ مفاتيحها من هنا.
|
| منطق الإخفاء التلقائي للدفع الأونلاين (وثيقة 04 §5.1):
| يظهر خيار «الدفع الأونلاين» فقط إذا كان online_enabled=true ووُجد مفتاح API
| للبوابة الافتراضية؛ وإلا يُخفى ويُعرض «الدفع الأونلاين مغلق حاليًا».
*/

return [

    // مفتاح التفعيل العام للدفع الأونلاين.
    'online_enabled' => (bool) env('ONLINE_PAYMENT_ENABLED', false),

    // البوابة الافتراضية (paymob | kashier).
    'default' => env('PAYMENT_GATEWAY', 'paymob'),

    // مهلة إلغاء الطلبات المهجورة بالساعات (أونلاين غير مدفوع، أو تحويل يدوي
    // بلا إثبات مرفوع) — بعدها يلغيها orders:cancel-expired ويحرّر مخزونها (M2).
    'pending_expiry_hours' => (int) env('PENDING_ORDER_EXPIRY_HOURS', 48),

    'gateways' => [

        'paymob' => [
            'api_key' => env('PAYMOB_API_KEY'),
            'integration_id' => env('PAYMOB_INTEGRATION_ID'),
            'iframe_id' => env('PAYMOB_IFRAME_ID'),
            'hmac_secret' => env('PAYMOB_HMAC_SECRET'),
        ],

        'kashier' => [
            // Kashier يستخدم api_key (مفتاح الدفع) لتوليد هاش الطلب والتحقّق من التوقيع،
            // وهو أيضًا مفتاح التفعيل الأساسي للكشف عن التوفّر.
            'api_key' => env('KASHIER_API_KEY'),
            'merchant_id' => env('KASHIER_MERCHANT_ID'),
            // secret_key: لنداءات REST على api.kashier.io (استرداد/استعلام) — غير مستخدَم
            // في تدفّق الصفحة المستضافة الحالي، لكنه مقروء من .env للاستعمال المستقبلي.
            'secret_key' => env('KASHIER_SECRET_KEY'),
            // وضع البوابة: test (افتراضي) أو live.
            'mode' => env('KASHIER_MODE', 'test'),
            // التضمين: true (افتراضي) يعرض نموذج الدفع مدمجًا داخل المتجر (iframe)؛
            // false يوجّه العميلة للصفحة المستضافة الخارجية.
            'embed' => (bool) env('KASHIER_EMBED', true),
            // أصل الصفحة المستضافة (وسكربت التضمين) — قابل للتهيئة تحسّبًا لتغيّر النطاق.
            'hpp_url' => env('KASHIER_HPP_URL', 'https://checkout.kashier.io'),
            // طرق الدفع المسموح بها على الصفحة المستضافة (card,wallet,bank_installments).
            'allowed_methods' => env('KASHIER_ALLOWED_METHODS', 'card,wallet'),
        ],

    ],

];
