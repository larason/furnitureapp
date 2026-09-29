<?php

namespace Tests\Unit;

use App\Models\Order;
use App\Services\Checkout\OrderLineAmount;
use App\Services\Checkout\OrderTotalsCalculator;
use App\Support\DeliveryFeeStatus;
use DomainException;
use PHPUnit\Framework\TestCase;

class OrderTotalsCalculatorTest extends TestCase
{
    public function test_line_total_is_unit_price_times_quantity(): void
    {
        $line = OrderTotalsCalculator::calculateLine(25_000, 4);

        $this->assertSame(25_000, $line->unitPriceAmount);
        $this->assertSame(4, $line->quantity);
        $this->assertSame(100_000, $line->lineTotalAmount);
    }

    public function test_subtotal_is_the_sum_of_line_totals(): void
    {
        $lines = [
            OrderTotalsCalculator::calculateLine(100_000, 1),
            OrderTotalsCalculator::calculateLine(250_000, 1),
            OrderTotalsCalculator::calculateLine(0, 1),
        ];

        $this->assertSame(350_000, OrderTotalsCalculator::calculateSubtotal($lines));
    }

    public function test_empty_line_set_has_zero_subtotal(): void
    {
        $this->assertSame(0, OrderTotalsCalculator::calculateSubtotal([]));
    }

    public function test_subtotal_rejects_non_line_values(): void
    {
        $this->expectException(DomainException::class);

        OrderTotalsCalculator::calculateSubtotal([new OrderLineAmount(1, 1, 1), 100]);
    }

    public function test_pickup_is_final_zero_fee_equal_to_subtotal(): void
    {
        $totals = OrderTotalsCalculator::forPickup(350_000);

        $this->assertSame(350_000, $totals->subtotalAmount);
        $this->assertSame(0, $totals->deliveryFeeAmount);
        $this->assertSame(DeliveryFeeStatus::FINALIZED, $totals->deliveryFeeStatus);
        $this->assertSame(350_000, $totals->totalAmount);
        $this->assertTrue($totals->isFinal);
    }

    public function test_delivery_pending_is_provisional_with_null_fee(): void
    {
        $totals = OrderTotalsCalculator::forDeliveryPending(350_000);

        $this->assertSame(350_000, $totals->subtotalAmount);
        $this->assertNull($totals->deliveryFeeAmount);
        $this->assertSame(DeliveryFeeStatus::PENDING, $totals->deliveryFeeStatus);
        $this->assertSame(350_000, $totals->totalAmount);
        $this->assertFalse($totals->isFinal);
    }

    public function test_delivery_finalized_adds_the_fee_to_the_subtotal(): void
    {
        $totals = OrderTotalsCalculator::forDeliveryFinalized(350_000, 50_000);

        $this->assertSame(350_000, $totals->subtotalAmount);
        $this->assertSame(50_000, $totals->deliveryFeeAmount);
        $this->assertSame(DeliveryFeeStatus::FINALIZED, $totals->deliveryFeeStatus);
        $this->assertSame(400_000, $totals->totalAmount);
        $this->assertTrue($totals->isFinal);
    }

    public function test_zero_fee_finalized_delivery_is_distinct_from_pending(): void
    {
        $pending = OrderTotalsCalculator::forDeliveryPending(350_000);
        $finalizedZero = OrderTotalsCalculator::forDeliveryFinalized(350_000, 0);

        // Both totals are numerically equal...
        $this->assertSame($pending->totalAmount, $finalizedZero->totalAmount);

        // ...but the null/zero fee and finality remain distinct states.
        $this->assertNull($pending->deliveryFeeAmount);
        $this->assertSame(0, $finalizedZero->deliveryFeeAmount);
        $this->assertSame(DeliveryFeeStatus::PENDING, $pending->deliveryFeeStatus);
        $this->assertSame(DeliveryFeeStatus::FINALIZED, $finalizedZero->deliveryFeeStatus);
        $this->assertFalse($pending->isFinal);
        $this->assertTrue($finalizedZero->isFinal);
    }

    public function test_maximum_cart_quantity_is_supported(): void
    {
        $line = OrderTotalsCalculator::calculateLine(1_234, 100);

        $this->assertSame(123_400, $line->lineTotalAmount);
    }

    public function test_currency_is_always_tzs(): void
    {
        $this->assertSame(Order::CURRENCY_TZS, OrderTotalsCalculator::forPickup(1)->currency);
        $this->assertSame(Order::CURRENCY_TZS, OrderTotalsCalculator::forDeliveryPending(1)->currency);
        $this->assertSame(Order::CURRENCY_TZS, OrderTotalsCalculator::forDeliveryFinalized(1, 1)->currency);
    }

    public function test_negative_unit_price_is_rejected(): void
    {
        $this->expectException(DomainException::class);

        OrderTotalsCalculator::calculateLine(-1, 1);
    }

    public function test_zero_and_negative_quantities_are_rejected(): void
    {
        foreach ([0, -1] as $quantity) {
            try {
                OrderTotalsCalculator::calculateLine(100, $quantity);
                $this->fail("Quantity {$quantity} must be rejected.");
            } catch (DomainException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_negative_delivery_fee_is_rejected(): void
    {
        $this->expectException(DomainException::class);

        OrderTotalsCalculator::forDeliveryFinalized(100_000, -1);
    }

    public function test_negative_subtotal_is_rejected(): void
    {
        $this->expectException(DomainException::class);

        OrderTotalsCalculator::forPickup(-1);
    }

    public function test_multiplication_overflow_is_rejected(): void
    {
        $this->expectException(DomainException::class);

        OrderTotalsCalculator::calculateLine(intdiv(PHP_INT_MAX, 2) + 1, 2);
    }

    public function test_subtotal_addition_overflow_is_rejected(): void
    {
        $line = OrderTotalsCalculator::calculateLine(intdiv(PHP_INT_MAX, 2) + 1, 1);

        $this->expectException(DomainException::class);

        OrderTotalsCalculator::calculateSubtotal([$line, $line]);
    }

    public function test_fee_addition_overflow_is_rejected(): void
    {
        $this->expectException(DomainException::class);

        OrderTotalsCalculator::forDeliveryFinalized(PHP_INT_MAX, 1);
    }

    public function test_float_inputs_are_rejected_not_coerced(): void
    {
        $calls = [
            fn () => OrderTotalsCalculator::calculateLine(100.5, 1),
            fn () => OrderTotalsCalculator::calculateLine(100, 1.5),
            fn () => OrderTotalsCalculator::forPickup(100.5),
            fn () => OrderTotalsCalculator::forDeliveryPending(100.5),
            fn () => OrderTotalsCalculator::forDeliveryFinalized(100, 0.5),
        ];

        foreach ($calls as $index => $call) {
            try {
                $call();
                $this->fail("Float input at index {$index} must be rejected.");
            } catch (DomainException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_non_integer_scalars_are_rejected(): void
    {
        foreach (['100', 100.0, true, null] as $value) {
            try {
                OrderTotalsCalculator::forDeliveryPending($value);
                $this->fail('Non-integer input must be rejected.');
            } catch (DomainException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_calculation_is_deterministic(): void
    {
        $first = OrderTotalsCalculator::forDeliveryFinalized(350_000, 50_000);
        $second = OrderTotalsCalculator::forDeliveryFinalized(350_000, 50_000);

        $this->assertEquals($first, $second);
        $this->assertSame($first->totalAmount, $second->totalAmount);
    }
}
