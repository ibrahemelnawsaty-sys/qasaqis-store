<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Models\Order;
use App\Services\Payment\Contracts\PaymentGateway;
use App\Support\Payment\PaymentInitiation;

/**
 * Kashier gateway (STUB — structural only for milestone M5).
 *
 * Keys are read from config('payment.gateways.kashier'); no secret is hardcoded.
 * The real hosted-payment-page signature build and webhook signature check are
 * intentionally NOT implemented yet (TODO). initiate() returns a failed
 * PaymentInitiation until wired — it never fakes success (constitution 1.3/1.5).
 */
class KashierGateway implements PaymentGateway
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(private readonly array $config)
    {
    }

    public function key(): string
    {
        return 'kashier';
    }

    public function isConfigured(): bool
    {
        return filled($this->config['api_key'] ?? null)
            && filled($this->config['merchant_id'] ?? null);
    }

    public function initiate(Order $order): PaymentInitiation
    {
        if (! $this->isConfigured()) {
            return PaymentInitiation::failed('payment.gateway.unavailable');
        }

        // TODO(M-online): build the Kashier hosted payment page params
        //   (merchantId, orderId=order->order_number, amount=grand_total,
        //    currency=EGP) and the HMAC-SHA256 hash over them using
        //   $this->config['secret_key']; redirectUrl = HPP url + params.
        return PaymentInitiation::failed('payment.gateway.not_implemented');
    }

    public function verify(array $payload): bool
    {
        // TODO(M-online): recompute the signature with secret_key and compare via
        // hash_equals against the payload signature. Only trust verified callbacks.
        return false;
    }
}
