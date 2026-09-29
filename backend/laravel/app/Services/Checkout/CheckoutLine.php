<?php

namespace App\Services\Checkout;

/**
 * Trusted, current transaction-time line facts for one Checkout OrderItem.
 * Built by the Checkout transaction from locked Cart lines and authoritative
 * catalog records; never from client input or the Cart projection.
 */
final readonly class CheckoutLine
{
    public function __construct(
        public OrderLineAmount $amount,
        public int $productId,
        public int $variantId,
        public string $sku,
        public string $name,
        public ?string $variantName,
    ) {}
}
