<?php

declare(strict_types=1);

namespace App\Support\Coupon;

use App\Models\Coupon;

/**
 * Outcome of validating a coupon against a cart. When invalid, $messageKey is a
 * payment.php translation key explaining why (no hardcoded text).
 */
final readonly class CouponResult
{
    public function __construct(
        public bool $valid,
        public ?Coupon $coupon,
        public string $discount,      // decimal string; '0.00' when invalid.
        public bool $freeShipping,
        public string $messageKey,    // e.g. 'payment.coupon.applied' or a reason key.
    ) {
    }

    public static function invalid(string $messageKey): self
    {
        return new self(false, null, '0.00', false, $messageKey);
    }

    public static function valid(Coupon $coupon, string $discount, bool $freeShipping): self
    {
        return new self(true, $coupon, $discount, $freeShipping, 'payment.coupon.applied');
    }
}
