<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Models\Order;
use App\Services\Payment\Contracts\PaymentGateway;
use App\Support\Payment\PaymentInitiation;

/**
 * Paymob gateway (STUB — structural only for milestone M5).
 *
 * Keys are read from config('payment.gateways.paymob'); no secret is hardcoded.
 * The real HTTP calls (auth token -> order registration -> payment key -> iframe
 * URL) and HMAC webhook verification are intentionally NOT implemented here yet
 * and are marked with TODO. Per constitution 1.3/1.5 this class never pretends a
 * payment succeeded: initiate() returns a failed PaymentInitiation until the
 * integration is wired.
 */
class PaymobGateway implements PaymentGateway
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(private readonly array $config)
    {
    }

    public function key(): string
    {
        return 'paymob';
    }

    public function isConfigured(): bool
    {
        return filled($this->config['api_key'] ?? null);
    }

    public function initiate(Order $order): PaymentInitiation
    {
        if (! $this->isConfigured()) {
            return PaymentInitiation::failed('payment.gateway.unavailable');
        }

        // TODO(M-online): implement Paymob flow using $this->config:
        //   1) POST /auth/tokens                     -> auth_token
        //   2) POST /ecommerce/orders                -> paymob order id
        //   3) POST /acceptance/payment_keys         -> payment_key (amount in
        //      piastres = order->grand_total * 100, currency EGP)
        //   4) build iframe URL with iframe_id + payment_key -> redirectUrl
        // Until then we do not fabricate a redirect (honesty: 1.3/1.5).
        return PaymentInitiation::failed('payment.gateway.not_implemented');
    }

    public function verify(array $payload): bool
    {
        // TODO(M-online): recompute HMAC over the ordered Paymob fields using
        // $this->config['hmac_secret'] and hash_equals() against payload['hmac'].
        // Never trust the browser return — only the verified webhook.
        return false;
    }
}
