<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Decimal money arithmetic (constitution 3.5 / anti-pattern 27: never float).
 *
 * All amounts are handled as 2-decimal strings ("150.00") matching the
 * decimal(10,2) columns and the Eloquent `decimal:2` casts. Uses bcmath so
 * there is no binary floating-point drift when summing prices.
 */
final class Money
{
    public const SCALE = 2;

    public const ZERO = '0.00';

    public static function normalize(string|int|float|null $value): string
    {
        if ($value === null || $value === '') {
            return self::ZERO;
        }

        // Cast through string; bcadd with scale 2 canonicalises to "N.NN".
        return bcadd((string) $value, '0', self::SCALE);
    }

    public static function add(string $a, string $b): string
    {
        return bcadd(self::normalize($a), self::normalize($b), self::SCALE);
    }

    public static function sub(string $a, string $b): string
    {
        return bcsub(self::normalize($a), self::normalize($b), self::SCALE);
    }

    /** Multiply an amount by an integer quantity. */
    public static function multiplyByQty(string $amount, int $qty): string
    {
        return bcmul(self::normalize($amount), (string) $qty, self::SCALE);
    }

    /** Percentage of an amount, e.g. percentOf('100.00', '10') === '10.00'. */
    public static function percentOf(string $amount, string $percent): string
    {
        $raw = bcmul(self::normalize($amount), self::normalize($percent), 4);

        return bcdiv($raw, '100', self::SCALE);
    }

    /** Smaller of two amounts. */
    public static function min(string $a, string $b): string
    {
        $a = self::normalize($a);
        $b = self::normalize($b);

        return bccomp($a, $b, self::SCALE) <= 0 ? $a : $b;
    }

    /** true when $a >= $b. */
    public static function gte(string $a, string $b): bool
    {
        return bccomp(self::normalize($a), self::normalize($b), self::SCALE) >= 0;
    }

    /** true when amount > 0. */
    public static function isPositive(string $a): bool
    {
        return bccomp(self::normalize($a), self::ZERO, self::SCALE) > 0;
    }

    /** Clamp negative results to zero (grand totals can never go below zero). */
    public static function clampNonNegative(string $a): string
    {
        $a = self::normalize($a);

        return bccomp($a, self::ZERO, self::SCALE) < 0 ? self::ZERO : $a;
    }
}
