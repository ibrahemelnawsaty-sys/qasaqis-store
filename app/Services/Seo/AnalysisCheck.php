<?php

declare(strict_types=1);

namespace App\Services\Seo;

/**
 * فحص واحد في تحليل محتوى المحرّر (نقطة خضراء/برتقالية/حمراء بأسلوب Yoast).
 */
final readonly class AnalysisCheck
{
    public const GOOD = 'good';   // أخضر — جيّد

    public const OK = 'ok';       // برتقالي — مقبول/قابل للتحسين

    public const BAD = 'bad';     // أحمر — يحتاج إصلاحًا

    public function __construct(
        public string $status,
        public string $message,
    ) {}
}
