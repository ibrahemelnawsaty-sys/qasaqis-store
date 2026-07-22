<?php

declare(strict_types=1);

namespace App\Services\Seo;

/**
 * نتيجة تدقيق SEO واحدة: مشكلة على كيان (كتاب/مقال/صفحة/قسم) أو على مستوى الموقع.
 * قيمة غير قابلة للتغيير يبنيها SeoAuditor وتعرضها لوحة «تدقيق SEO».
 */
final readonly class SeoFinding
{
    public const DANGER = 'danger';

    public const WARNING = 'warning';

    public const INFO = 'info';

    public function __construct(
        public string $severity,   // danger | warning | info
        public string $group,      // «الكتب» | «المقالات» | «الصفحات» | «الأقسام» | «الموقع»
        public string $label,      // اسم/عنوان الكيان
        public string $issue,      // نصّ المشكلة (عربي)
        public ?string $editUrl = null,
    ) {}
}
