<?php

namespace App\Services\Checkout;

use App\Models\Order;
use App\Support\DeliveryFeeStatus;
use App\Support\FulfillmentType;
use App\Support\OrderStatus;
use DomainException;

final readonly class PickupFulfillmentState
{
    private function __construct() {}

    /**
     * @param  array{fulfillment_type?: mixed, delivery_address?: mixed}  $input
     */
    public static function fromInput(array $input): self
    {
        $allowedFields = ['fulfillment_type', 'delivery_address'];

        if (array_diff(array_keys($input), $allowedFields) !== []) {
            throw new DomainException('Pickup fulfillment input contains unsupported fields.');
        }

        if (($input['fulfillment_type'] ?? null) !== FulfillmentType::PICKUP->value) {
            throw new DomainException('Pickup fulfillment requires the PICKUP fulfillment type.');
        }

        if (array_key_exists('delivery_address', $input) && $input['delivery_address'] !== null) {
            throw new DomainException('PICKUP fulfillment does not accept a delivery address.');
        }

        return new self;
    }

    /** @return array{fulfillment_type: string, delivery_address: null, billing_address: null, delivery_fee: array{amount: int, currency: string}, delivery_fee_status: string} */
    public function toArray(): array
    {
        return [
            'fulfillment_type' => FulfillmentType::PICKUP->value,
            'delivery_address' => null,
            'billing_address' => null,
            'delivery_fee' => $this->deliveryFee(),
            'delivery_fee_status' => DeliveryFeeStatus::FINALIZED->value,
        ];
    }

    public function totalAmountForSubtotal(int $subtotalAmount): int
    {
        if ($subtotalAmount < 0) {
            throw new DomainException('Pickup subtotal must not be negative.');
        }

        return $subtotalAmount;
    }

    /** @return array<string, mixed> */
    public function orderProjectionForSubtotal(int $subtotalAmount): array
    {
        $totalAmount = $this->totalAmountForSubtotal($subtotalAmount);
        $projection = $this->toArray();

        return [
            ...$projection,
            'status' => OrderStatus::PENDING_PAYMENT->value,
            'subtotal' => $this->money($subtotalAmount),
            'total' => $this->money($totalAmount),
            'currency' => Order::CURRENCY_TZS,
            'payment' => null,
        ];
    }

    /** @return array{amount: int, currency: string} */
    private function deliveryFee(): array
    {
        return [
            'amount' => 0,
            'currency' => Order::CURRENCY_TZS,
        ];
    }

    /** @return array{amount: int, currency: string} */
    private function money(int $amount): array
    {
        return [
            'amount' => $amount,
            'currency' => Order::CURRENCY_TZS,
        ];
    }
}
