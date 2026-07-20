<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use Carbon\CarbonImmutable;

/**
 * يحوّل فلاتر صفحة القسم المالي إلى نطاق تاريخي [from, to] بتوقيت القاهرة.
 * مشترك بين كل ويدجت فلا يتكرّر المنطق (الدستور 2.3)، ويطبّق قائمة بيضاء صارمة
 * على المسبقات مع التحقق من صحة التاريخ المخصّص خادميًا (الدستور 4.1).
 */
final class FinanceRange
{
    private const TZ = 'Africa/Cairo';

    /** أقصى امتداد مسموح للنطاق المخصّص: سنة، درءًا لاستعلام ضخم. */
    private const MAX_DAYS = 366;

    /**
     * @param  array<string, mixed>  $filters
     * @return array{0:CarbonImmutable, 1:CarbonImmutable}
     */
    public static function fromFilters(array $filters): array
    {
        $preset = is_string($filters['preset'] ?? null) ? $filters['preset'] : '30d';
        $now = CarbonImmutable::now(self::TZ);

        return match ($preset) {
            'today' => [$now->startOfDay(), $now->endOfDay()],
            '7d' => [$now->subDays(6)->startOfDay(), $now->endOfDay()],
            'month' => [$now->startOfMonth(), $now->endOfDay()],
            'custom' => self::custom($filters, $now),
            default => [$now->subDays(29)->startOfDay(), $now->endOfDay()], // 30d
        };
    }

    /**
     * نطاق مخصّص: يقرأ from/to، ويصحّح أي إدخال خاطئ بدل الوثوق به —
     * from ≤ to، والامتداد مقيّد، والقيم غير الصالحة ترجع لآخر ٣٠ يومًا.
     *
     * @param  array<string, mixed>  $filters
     * @return array{0:CarbonImmutable, 1:CarbonImmutable}
     */
    private static function custom(array $filters, CarbonImmutable $now): array
    {
        $from = self::parse($filters['from'] ?? null);
        $to = self::parse($filters['to'] ?? null);

        if ($from === null || $to === null) {
            return [$now->subDays(29)->startOfDay(), $now->endOfDay()];
        }

        // اقلب إن جاءا معكوسين بدل رفض الطلب.
        if ($from->greaterThan($to)) {
            [$from, $to] = [$to, $from];
        }

        // اقصُر الامتداد على السقف من جهة البداية.
        if ($from->diffInDays($to) > self::MAX_DAYS) {
            $from = $to->subDays(self::MAX_DAYS);
        }

        return [$from->startOfDay(), $to->endOfDay()];
    }

    private static function parse(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return rescue(
            fn (): CarbonImmutable => CarbonImmutable::parse($value, self::TZ),
            null,
            report: false,
        );
    }
}
