<?php

declare(strict_types=1);

/*
| إعدادات أكواد التحقق (M9).
|
| القناة قابلة للتبديل: 'email' اليوم، و'sms'/'whatsapp' حين يُفعَّل OTP الجوال —
| دون تغيير أي منطق، فقط هذا المفتاح + صنف قناة جديد يطبّق VerificationChannel.
| القيم من البيئة كي يضبطها الأدمن بلا تعديل كود (بند 0.8).
*/

return [

    // القناة الافتراضية لإرسال الكود. لاحقًا: VERIFICATION_CHANNEL=sms.
    'channel' => env('VERIFICATION_CHANNEL', 'email'),

    // عدد خانات الكود. ستّ خانات عدديّة مألوفة وسهلة الإدخال على الجوال.
    'code_length' => (int) env('VERIFICATION_CODE_LENGTH', 6),

    // صلاحية الكود بالدقائق. قصيرة عمدًا لتقليل نافذة إساءة الاستخدام.
    'expiry_minutes' => (int) env('VERIFICATION_EXPIRY_MINUTES', 15),

    // أقصى محاولات إدخال خاطئة قبل إبطال الكود (يمنع التخمين).
    'max_attempts' => (int) env('VERIFICATION_MAX_ATTEMPTS', 5),

];
