<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Services\Checkout\OrderTotals;
use App\Services\Checkout\OrderTotalsCalculator;
use App\Support\FulfillmentType;
use App\Support\OrderStatus;
use DomainException;
use Tests\TestCase;

/**
 * Phase 7.6 calculator <-> Order model agreement. Builds model states from
 * calculated totals without persisting a Checkout Order (Phase 7.4 DELIVERY
 * persistence remains blocked by the billing snapshot gap).
 */
class OrderTotalsCompatibilityTest extends TestCase
{
    public function test_pickup_totals_satisfy_the_order_model_invariant(): void
    {
        $order = $this->orderFrom(OrderTotalsCalculator::forPickup(350_000), [
            'fulfillment_type' => FulfillmentType::PICKUP,
            'delivery_address' => null,
            'recipient_name' => null,
            'recipient_phone' => null,
        ]);

        $order->assertValid();

        $this->assertSame(0, $order->delivery_fee_amount);
        $this->assertSame($order->subtotal_amount, $order->total_amount);
        $this->addToAssertionCount(1);
    }

    public function test_delivery_pending_totals_satisfy_the_order_model_invariant(): void
    {
        $order = $this->orderFrom(OrderTotalsCalculator::forDeliveryPending(170_000), [
            'fulfillment_type' => FulfillmentType::DELIVERY,
            'delivery_address' => ['address_line' => 'Block C, Mikocheni B', 'city' => 'Dar es Salaam'],
            'recipient_name' => 'Asha Mwangi',
            'recipient_phone' => '+255700000001',
        ]);

        $order->assertValid();

        $this->assertNull($order->delivery_fee_amount);
        $this->assertSame($order->subtotal_amount, $order->total_amount);
    }

    public function test_delivery_finalized_totals_satisfy_the_order_model_invariant(): void
    {
        $order = $this->orderFrom(OrderTotalsCalculator::forDeliveryFinalized(170_000, 2_500_000), [
            'fulfillment_type' => FulfillmentType::DELIVERY,
            'delivery_address' => ['address_line' => 'Block C, Mikocheni B', 'city' => 'Dar es Salaam'],
            'recipient_name' => 'Asha Mwangi',
            'recipient_phone' => '+255700000001',
        ]);

        $order->assertValid();

        $this->assertSame(2_500_000, $order->delivery_fee_amount);
        $this->assertSame($order->subtotal_amount + $order->delivery_fee_amount, $order->total_amount);
    }

    public function test_model_still_rejects_financial_state_the_calculator_cannot_produce(): void
    {
        // The calculator can never emit a nonzero PICKUP fee; the model must
        // reject that impossible state too (no contradictory rules).
        $order = $this->orderFrom(OrderTotalsCalculator::forPickup(350_000), [
            'fulfillment_type' => FulfillmentType::PICKUP,
            'delivery_address' => null,
            'delivery_fee_amount' => 1,
        ]);

        $this->expectException(DomainException::class);

        $order->assertValid();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function orderFrom(OrderTotals $totals, array $overrides): Order
    {
        $order = new Order;
        $order->forceFill([
            'order_reference' => 'OD-ABCDE',
            'status' => OrderStatus::PENDING_PAYMENT,
            'delivery_fee_status' => $totals->deliveryFeeStatus,
            'currency' => $totals->currency,
            'subtotal_amount' => $totals->subtotalAmount,
            'delivery_fee_amount' => $totals->deliveryFeeAmount,
            'total_amount' => $totals->totalAmount,
            ...$overrides,
        ]);

        return $order;
    }
}
