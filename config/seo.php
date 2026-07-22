<?php

declare(strict_types=1);

/*
| إعدادات SEO التقنية لمتجر «قصص أطفال».
|
| القيم هنا ثابتة/بيئية فقط (لا أسرار). النصوص الظاهرة للمستخدم تأتي من
| ملفات الترجمة أو الـ CMS (بند 6.4) لا من هنا.
|
| site_url: الدومين المطلق للإنتاج، يُستخدم في sitemap.xml و robots.txt و
| JSON-LD (Organization/WebSite). نعتمد متغيّر بيئة مستقل SEO_SITE_URL بدل
| APP_URL حتى لا يتسرّب "http://localhost" الخاص ببيئة التطوير إلى الـ sitemap.
*/

return [
    // الدومين المطلق للموقع (بلا شرطة أخيرة).
    'site_url' => rtrim((string) env('SEO_SITE_URL', 'https://qasaqis.store'), '/'),

    // كود تحقّق Google Search Console (طريقة الـmeta-tag، بديلة عن DNS). الصق الكود
    // من Search Console في env GOOGLE_SITE_VERIFICATION فيظهر وسم التحقّق في <head>.
    'google_site_verification' => (string) env('GOOGLE_SITE_VERIFICATION', ''),

    // لون الثيم لشريط المتصفح (بنفسجي العلامة — بند 0.1).
    'theme_color' => '#5B2A86',

    // الصورة الافتراضية لبطاقات المشاركة (Open Graph/Twitter) — شعار العلامة.
    'default_image' => 'images/logo.png',

    // نوع بطاقة تويتر الافتراضية.
    'twitter_card' => 'summary_large_image',

    // لغة/إقليم Open Graph. الموقع عربي يستهدف كل الدول العربية.
    'og_locale' => 'ar_AR',

    // مدّة تخزين sitemap.xml المؤقّت بالثواني (ساعة).
    'sitemap_ttl' => 3600,
];
