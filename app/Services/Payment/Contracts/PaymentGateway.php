<?php

declare(strict_types=1);

namespace App\Services\Payment\Contracts;

use App\Models\Order;
use App\Support\Payment\PaymentInitiation;

/**
 * Payment gateway abstraction (docs/04 §5.1): the order flow depends only on
 * this interface, so Paymob can be swapped for Kashier without touching the
 * checkout logic. Concrete implementations read their keys from config
 * (config/payment.php), never hardcoded (constitution 4.3).
 */
interface PaymentGateway
{
    /** Provider key, e.g. 'paymob' | 'kashier'. */
    public function key(): string;

    /** Whether this gateway has the credentials it needs to operate. */
    public function isConfigured(): bool;

    /**
     * Start a payment for the given order and return where to send the customer.
     */
    public function initiate(Order $order): PaymentInitiation;

    /**
     * Verify an incoming webhook/callback payload (HMAC) before trusting it.
     *
     * @param  array<string, mixed>  $payload
     */
    public function verify(array $payload): bool;
}
