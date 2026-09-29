<?php

namespace App\Services\Checkout;

/**
 * Immutable calculated line values for a future OrderItem snapshot. Built only
 * by {@see OrderTotalsCalculator}; the line total is unit price x quantity.
 */
final readonly class OrderLineAmount
{
    public function __construct(
        public int $unitPriceAmount,
        public int $quantity,
        public int $lineTotalAmount,
    ) {}
}
