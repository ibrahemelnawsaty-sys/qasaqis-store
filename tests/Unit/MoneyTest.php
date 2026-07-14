<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\Money;
use PHPUnit\Framework\TestCase;

/**
 * Decimal money arithmetic (constitution 3.5 / anti-pattern 27: never float).
 * These guarantee the prices used across cart/coupon/checkout stay as exact
 * 2-decimal strings with no binary floating-point drift.
 *
 * HONESTY (1.3/1.5): NOT executed in this (PHP-less) environment; runs under
 * `php artisan test` on the hosting. Requires the bcmath extension.
 */
final class MoneyTest extends TestCase
{
    public function test_normalize_produces_two_decimal_strings(): void
    {
        $this->assertSame('150.00', Money::normalize('150'));
        $this->assertSame('150.50', Money::normalize('150.5'));
        $this->assertSame('0.00', Money::normalize(null));
        $this->assertSame('0.00', Money::normalize(''));
    }

    public function test_addition_has_no_floating_point_drift(): void
    {
        // 0.1 + 0.2 must be exactly 0.30, not 0.30000000000000004.
        $this->assertSame('0.30', Money::add('0.10', '0.20'));
        $this->assertSame('450.00', Money::add('150.00', '300.00'));
    }

    public function test_multiply_by_quantity(): void
    {
        $this->assertSame('600.00', Money::multiplyByQty('200.00', 3));
        $this->assertSame('0.00', Money::multiplyByQty('200.00', 0));
    }

    public function test_percent_of(): void
    {
        $this->assertSame('20.00', Money::percentOf('200.00', '10'));
        $this->assertSame('33.33', Money::percentOf('100.00', '33.33'));
    }

    public function test_min_and_comparisons(): void
    {
        $this->assertSame('50.00', Money::min('50.00', '80.00'));
        $this->assertTrue(Money::gte('100.00', '100.00'));
        $this->assertFalse(Money::gte('99.99', '100.00'));
        $this->assertTrue(Money::isPositive('0.01'));
        $this->assertFalse(Money::isPositive('0.00'));
    }

    public function test_clamp_non_negative_floors_at_zero(): void
    {
        // A discount larger than the subtotal must never yield a negative total.
        $this->assertSame('0.00', Money::clampNonNegative(Money::sub('100.00', '150.00')));
        $this->assertSame('50.00', Money::clampNonNegative('50.00'));
    }
}
