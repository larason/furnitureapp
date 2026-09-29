<?php

namespace App\Services\Checkout;

use App\Support\DeliveryFeeStatus;
use DomainException;

/**
 * Single authoritative, pure calculation boundary for Checkout/Order financial
 * totals. Performs no database query, inventory mutation, Cart mutation, or
 * persistence. Integer minor units only (1 TZS = 100 minor units).
 *
 * Canonical rules:
 * - PICKUP: fee 0, FINALIZED, total = subtotal.
 * - DELIVERY pending: fee null, PENDING, total = subtotal (provisional).
 * - DELIVERY finalized: fee >= 0, FINALIZED, total = subtotal + fee.
 */
final class OrderTotalsCalculator
{
    public static function calculateLine(mixed $unitPriceAmount, mixed $quantity): OrderLineAmount
    {
        $unitPrice = self::amount($unitPriceAmount, 'unit_price_amount', 0);
        $quantity = self::amount($quantity, 'quantity', 1);

        return new OrderLineAmount($unitPrice, $quantity, self::multiply($unitPrice, $quantity));
    }

    /**
     * @param  iterable<mixed>  $lines
     */
    public static function calculateSubtotal(iterable $lines): int
    {
        $subtotal = 0;

        foreach ($lines as $line) {
            if (! $line instanceof OrderLineAmount) {
                throw new DomainException('Order subtotal lines must be OrderLineAmount values.');
            }

            $subtotal = self::add($subtotal, $line->lineTotalAmount);
        }

        return $subtotal;
    }

    public static function forPickup(mixed $subtotalAmount): OrderTotals
    {
        $subtotal = self::amount($subtotalAmount, 'subtotal_amount', 0);

        return new OrderTotals(
            subtotalAmount: $subtotal,
            deliveryFeeAmount: 0,
            deliveryFeeStatus: DeliveryFeeStatus::FINALIZED,
            totalAmount: $subtotal,
            isFinal: true,
        );
    }

    public static function forDeliveryPending(mixed $subtotalAmount): OrderTotals
    {
        $subtotal = self::amount($subtotalAmount, 'subtotal_amount', 0);

        return new OrderTotals(
            subtotalAmount: $subtotal,
            deliveryFeeAmount: null,
            deliveryFeeStatus: DeliveryFeeStatus::PENDING,
            totalAmount: $subtotal,
            isFinal: false,
        );
    }

    public static function forDeliveryFinalized(mixed $subtotalAmount, mixed $deliveryFeeAmount): OrderTotals
    {
        $subtotal = self::amount($subtotalAmount, 'subtotal_amount', 0);
        $deliveryFee = self::amount($deliveryFeeAmount, 'delivery_fee_amount', 0);

        return new OrderTotals(
            subtotalAmount: $subtotal,
            deliveryFeeAmount: $deliveryFee,
            deliveryFeeStatus: DeliveryFeeStatus::FINALIZED,
            totalAmount: self::add($subtotal, $deliveryFee),
            isFinal: true,
        );
    }

    /**
     * Validates the raw value before any scalar coercion: a float reaching a
     * non-strict caller must be rejected, never silently truncated.
     */
    private static function amount(mixed $value, string $field, int $minimum): int
    {
        if (! is_int($value)) {
            throw new DomainException("Order {$field} must be an integer minor-unit amount.");
        }

        if ($value < $minimum) {
            throw new DomainException("Order {$field} must not be less than {$minimum}.");
        }

        return $value;
    }

    private static function multiply(int $unitPriceAmount, int $quantity): int
    {
        if ($unitPriceAmount !== 0 && $unitPriceAmount > intdiv(PHP_INT_MAX, $quantity)) {
            throw new DomainException('Order line total exceeds the supported monetary range.');
        }

        return $unitPriceAmount * $quantity;
    }

    private static function add(int $left, int $right): int
    {
        if ($left > PHP_INT_MAX - $right) {
            throw new DomainException('Order total exceeds the supported monetary range.');
        }

        return $left + $right;
    }
}
