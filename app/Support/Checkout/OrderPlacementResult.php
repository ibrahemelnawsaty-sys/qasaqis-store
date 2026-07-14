<?php

declare(strict_types=1);

namespace App\Support\Checkout;

use App\Models\Order;
use App\Support\Payment\PaymentInitiation;

/**
 * What PlaceOrderAction returns: the created order, plus (for the online
 * gateway path) the initiation result the controller uses to redirect.
 */
final readonly class OrderPlacementResult
{
    public function __construct(
        public Order $order,
        public ?PaymentInitiation $initiation = null,
    ) {
    }
}
