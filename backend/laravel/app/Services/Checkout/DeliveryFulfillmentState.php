<?php

namespace App\Services\Checkout;

use App\Models\Order;
use App\Support\AddressField;
use App\Support\DeliveryFeeStatus;
use App\Support\FulfillmentType;
use App\Support\OrderStatus;
use DomainException;

final readonly class DeliveryFulfillmentState
{
    private const MAX_RECIPIENT_NAME = 255;

    private const MAX_PHONE = 30;

    private const PHONE_PATTERN = '/^\+?[0-9][0-9 ().-]{6,29}$/';

    private function __construct(
        private array $deliveryAddress,
        private array $billingAddress,
    ) {}

    /** @param array{fulfillment_type?: mixed, delivery_address?: mixed} $input */
    public static function fromInput(array $input): self
    {
        self::assertTopLevelInput($input);

        $rawAddress = $input['delivery_address'] ?? null;

        if (! is_array($rawAddress)) {
            throw new DomainException('DELIVERY fulfillment requires a delivery address.');
        }

        $address = self::normalizeAddress($rawAddress);

        return new self($address, $address);
    }

    /** @return array{fulfillment_type: string, delivery_address: array{recipient_name: string, phone: string, address_line: string, city: string}, billing_address: array{recipient_name: string, phone: string, address_line: string, city: string}, delivery_fee: null, delivery_fee_status: string} */
    public function toArray(): array
    {
        return [
            'fulfillment_type' => FulfillmentType::DELIVERY->value,
            'delivery_address' => $this->deliveryAddress,
            'billing_address' => $this->billingAddress,
            'delivery_fee' => null,
            'delivery_fee_status' => DeliveryFeeStatus::PENDING->value,
        ];
    }

    /** @return array{fulfillment_type: string, status: string, delivery_fee_status: string, currency: string, subtotal_amount: int, delivery_fee_amount: null, total_amount: int, recipient_name: string, recipient_phone: string, delivery_address: array{address_line: string, city: string}} */
    public function orderAttributesForSubtotal(int $subtotalAmount): array
    {
        if ($subtotalAmount < 0) {
            throw new DomainException('Delivery subtotal must not be negative.');
        }

        return [
            'fulfillment_type' => FulfillmentType::DELIVERY->value,
            'status' => OrderStatus::PENDING_PAYMENT->value,
            'delivery_fee_status' => DeliveryFeeStatus::PENDING->value,
            'currency' => Order::CURRENCY_TZS,
            'subtotal_amount' => $subtotalAmount,
            'delivery_fee_amount' => null,
            'total_amount' => $subtotalAmount,
            'recipient_name' => $this->deliveryAddress['recipient_name'],
            'recipient_phone' => $this->deliveryAddress['phone'],
            'delivery_address' => [
                'address_line' => $this->deliveryAddress['address_line'],
                'city' => $this->deliveryAddress['city'],
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function responseForSubtotal(int $subtotalAmount): array
    {
        $attributes = $this->orderAttributesForSubtotal($subtotalAmount);

        return [
            'fulfillment_type' => $attributes['fulfillment_type'],
            'delivery_address' => $this->deliveryAddress,
            'status' => $attributes['status'],
            'subtotal' => self::money($subtotalAmount),
            'delivery_fee' => null,
            'delivery_fee_status' => $attributes['delivery_fee_status'],
            'total' => self::money($attributes['total_amount']),
            'currency' => $attributes['currency'],
            'payment' => null,
        ];
    }

    /** @return array{delivery_address: array{recipient_name: string, phone: string, address_line: string, city: string}, billing_address: array{recipient_name: string, phone: string, address_line: string, city: string}} */
    public function addressSnapshots(): array
    {
        return [
            'delivery_address' => $this->deliveryAddress,
            'billing_address' => $this->billingAddress,
        ];
    }

    /** @param array<string, mixed> $input */
    private static function assertTopLevelInput(array $input): void
    {
        $unknown = array_diff(array_keys($input), ['fulfillment_type', 'delivery_address']);

        if ($unknown !== []) {
            throw new DomainException('Delivery fulfillment input contains unsupported fields.');
        }

        if (($input['fulfillment_type'] ?? null) !== FulfillmentType::DELIVERY->value) {
            throw new DomainException('Delivery fulfillment requires the DELIVERY fulfillment type.');
        }

        if (! array_key_exists('delivery_address', $input)) {
            throw new DomainException('DELIVERY fulfillment requires a delivery address.');
        }
    }

    /** @param array<string, mixed> $address */
    private static function normalizeAddress(array $address): array
    {
        $unknown = array_diff(array_keys($address), ['recipient_name', 'phone', 'address_line', 'city']);

        if ($unknown !== []) {
            throw new DomainException('Delivery address contains unsupported fields.');
        }

        $nested = AddressField::normalize([
            'address_line' => $address['address_line'] ?? null,
            'city' => $address['city'] ?? null,
        ]);

        return [
            'recipient_name' => self::normalizeRequiredString('recipient_name', $address['recipient_name'] ?? null, self::MAX_RECIPIENT_NAME),
            'phone' => self::normalizePhone($address['phone'] ?? null),
            ...$nested,
        ];
    }

    private static function normalizeRequiredString(string $field, mixed $value, ?int $maxLength = null): string
    {
        if (! is_string($value) || trim($value) === '') {
            throw new DomainException("Delivery address {$field} is required.");
        }

        $normalized = trim($value);

        if ($maxLength !== null && mb_strlen($normalized) > $maxLength) {
            throw new DomainException("Delivery address {$field} is too long.");
        }

        return $normalized;
    }

    private static function normalizePhone(mixed $value): string
    {
        $phone = self::normalizeRequiredString('phone', $value, self::MAX_PHONE);

        if (preg_match(self::PHONE_PATTERN, $phone) !== 1) {
            throw new DomainException('Delivery address phone is invalid.');
        }

        return $phone;
    }

    /** @return array{amount: int, currency: string} */
    private static function money(int $amount): array
    {
        return ['amount' => $amount, 'currency' => Order::CURRENCY_TZS];
    }
}
