<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Domain error raised while placing an order (empty cart, insufficient stock,
 * disallowed payment method). Carries a translation key so the controller can
 * flash a localized message without hardcoding text (constitution 6.4).
 */
class CheckoutException extends RuntimeException
{
    public function __construct(
        public readonly string $messageKey,
        /** @var array<string, scalar> */
        public readonly array $replace = [],
    ) {
        parent::__construct($messageKey);
    }

    public static function emptyCart(): self
    {
        return new self('payment.errors.empty_cart');
    }

    public static function outOfStock(string $bookTitle): self
    {
        return new self('payment.errors.out_of_stock', ['book' => $bookTitle]);
    }

    public static function invalidPaymentMethod(): self
    {
        return new self('payment.errors.invalid_method');
    }

    /** Localized, human-readable message for flashing to the user. */
    public function localizedMessage(): string
    {
        return (string) __($this->messageKey, $this->replace);
    }
}
