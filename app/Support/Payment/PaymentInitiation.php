<?php

declare(strict_types=1);

namespace App\Support\Payment;

/**
 * Result of asking a gateway to start (initiate) an online payment.
 *
 * This is a clean structural contract: the concrete gateways (Paymob/Kashier)
 * are stubs in this milestone — they build the intent and return this DTO but
 * do not yet perform real HTTP calls. `success=false` with a message is returned
 * when keys are missing, so the checkout flow never falsely claims payment.
 */
final readonly class PaymentInitiation
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public bool $success,
        public ?string $redirectUrl = null,
        public ?string $reference = null,
        public ?string $messageKey = null,
        public array $raw = [],
    ) {
    }

    public static function failed(string $messageKey): self
    {
        return new self(false, null, null, $messageKey);
    }
}
