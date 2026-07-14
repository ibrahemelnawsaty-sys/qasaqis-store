<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Models\PaymentMethod;
use Illuminate\Support\Collection;

/**
 * Decides which payment methods are offered to the customer (docs/04 §5.1).
 *
 * All enabled methods come from the payment_methods table. The online gateway
 * (type = online_gateway) is shown ONLY when online payment is enabled AND the
 * default gateway has an API key configured; otherwise it is hidden and the UI
 * shows «الدفع الأونلاين مغلق حاليًا» (payment.online_closed) — while manual
 * transfer + COD keep working, always.
 */
class PaymentMethodResolver
{
    public function __construct(
        private readonly PaymentGatewayFactory $gateways,
    ) {
    }

    /**
     * Enabled + offerable methods, ordered for display.
     *
     * @return Collection<int, PaymentMethod>
     */
    public function available(): Collection
    {
        $onlineEnabled = $this->isOnlineEnabled();

        return PaymentMethod::query()
            ->enabled()
            ->orderBy('sort_order')
            ->get()
            ->reject(fn (PaymentMethod $method): bool =>
                $method->type === 'online_gateway' && ! $onlineEnabled)
            ->values();
    }

    /**
     * The `code` whitelist for validating the chosen payment_method server-side.
     *
     * @return array<int, string>
     */
    public function availableCodes(): array
    {
        return $this->available()
            ->pluck('code')
            ->all();
    }

    public function find(string $code): ?PaymentMethod
    {
        return $this->available()
            ->firstWhere('code', $code);
    }

    /**
     * Online payments are live only when the master switch is on AND the default
     * gateway actually holds credentials (config/payment.php, keys from .env).
     */
    public function isOnlineEnabled(): bool
    {
        if (! (bool) config('payment.online_enabled', false)) {
            return false;
        }

        return $this->gateways->default()->isConfigured();
    }

    /**
     * Translation key shown when online payment is hidden. The controller/view
     * only renders it when the online method is unavailable.
     */
    public function onlineDisabledMessageKey(): string
    {
        return 'payment.online_closed';
    }
}
