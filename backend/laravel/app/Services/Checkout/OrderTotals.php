<?php

namespace App\Services\Checkout;

use App\Models\Order;
use App\Support\DeliveryFeeStatus;

/**
 * Immutable authoritative financial result for a Checkout Order. `isFinal`
 * expresses financial finality (delivery fee finalized), never payment status.
 */
final readonly class OrderTotals
{
    public function __construct(
        public int $subtotalAmount,
        public ?int $deliveryFeeAmount,
        public DeliveryFeeStatus $deliveryFeeStatus,
        public int $totalAmount,
        public bool $isFinal,
        public string $currency = Order::CURRENCY_TZS,
    ) {}
}
