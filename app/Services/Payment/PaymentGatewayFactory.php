<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Services\Payment\Contracts\PaymentGateway;
use InvalidArgumentException;

/**
 * Resolves a concrete PaymentGateway by provider key, injecting the matching
 * non-secret config slice. Secrets themselves live only in .env (4.3).
 */
class PaymentGatewayFactory
{
    /**
     * @param  string|null  $provider  provider key; defaults to config('payment.default')
     */
    public function make(?string $provider = null): PaymentGateway
    {
        $provider = $provider ?? (string) config('payment.default', 'paymob');

        /** @var array<string, mixed> $config */
        $config = (array) config("payment.gateways.{$provider}", []);

        return match ($provider) {
            'paymob' => new PaymobGateway($config),
            'kashier' => new KashierGateway($config),
            default => throw new InvalidArgumentException("Unknown payment gateway: {$provider}"),
        };
    }

    /** The gateway for the store's configured default provider. */
    public function default(): PaymentGateway
    {
        return $this->make();
    }
}
