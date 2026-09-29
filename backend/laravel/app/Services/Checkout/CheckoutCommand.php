<?php

namespace App\Services\Checkout;

use App\Models\User;
use App\Support\FulfillmentType;

/**
 * Immutable, already-validated Checkout intent. Phase 7.8 builds this from the
 * request; it never carries client-controlled financials, ownership, status,
 * warehouse, or Cart selectors.
 */
final readonly class CheckoutCommand
{
    /**
     * @param  array<string, mixed>|null  $deliveryAddress  normalized address (null for PICKUP)
     */
    public function __construct(
        public User $customer,
        public FulfillmentType $fulfillmentType,
        public ?array $deliveryAddress,
        public string $idempotencyKey,
    ) {}

    public static function pickup(User $customer, string $idempotencyKey): self
    {
        return new self($customer, FulfillmentType::PICKUP, null, $idempotencyKey);
    }

    /**
     * @param  array<string, mixed>  $deliveryAddress
     */
    public static function delivery(User $customer, array $deliveryAddress, string $idempotencyKey): self
    {
        return new self($customer, FulfillmentType::DELIVERY, $deliveryAddress, $idempotencyKey);
    }
}
