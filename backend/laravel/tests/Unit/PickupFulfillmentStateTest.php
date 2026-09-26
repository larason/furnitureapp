<?php

namespace Tests\Unit;

use App\Services\Checkout\PickupFulfillmentState;
use DomainException;
use PHPUnit\Framework\TestCase;

class PickupFulfillmentStateTest extends TestCase
{
    public function test_pickup_state_has_final_zero_fee_and_null_addresses(): void
    {
        $state = PickupFulfillmentState::fromInput([
            'fulfillment_type' => 'PICKUP',
        ]);

        $this->assertSame([
            'fulfillment_type' => 'PICKUP',
            'delivery_address' => null,
            'billing_address' => null,
            'delivery_fee' => ['amount' => 0, 'currency' => 'TZS'],
            'delivery_fee_status' => 'FINALIZED',
        ], $state->toArray());
        $this->assertSame(125000, $state->totalAmountForSubtotal(125000));
        $this->assertSame([
            'status' => 'PENDING_PAYMENT',
            'subtotal' => ['amount' => 125000, 'currency' => 'TZS'],
            'total' => ['amount' => 125000, 'currency' => 'TZS'],
            'currency' => 'TZS',
            'payment' => null,
        ], array_intersect_key(
            $state->orderProjectionForSubtotal(125000),
            array_flip(['status', 'subtotal', 'total', 'currency', 'payment']),
        ));
    }

    public function test_absent_and_null_delivery_addresses_have_the_same_state(): void
    {
        $withoutAddress = PickupFulfillmentState::fromInput(['fulfillment_type' => 'PICKUP']);
        $withNullAddress = PickupFulfillmentState::fromInput([
            'fulfillment_type' => 'PICKUP',
            'delivery_address' => null,
        ]);

        $this->assertSame($withoutAddress->toArray(), $withNullAddress->toArray());
    }

    public function test_populated_delivery_address_is_rejected(): void
    {
        $this->expectException(DomainException::class);

        PickupFulfillmentState::fromInput([
            'fulfillment_type' => 'PICKUP',
            'delivery_address' => [
                'recipient_name' => 'Asha',
                'phone' => '+255700000001',
                'address_line' => 'Jengo Street',
                'city' => 'Dar es Salaam',
            ],
        ]);
    }

    public function test_aliases_and_client_controlled_fields_are_rejected(): void
    {
        foreach ([
            ['fulfillment_type' => 'pickup'],
            ['fulfillment_type' => 'SELF_PICKUP'],
            ['fulfillment_type' => 'PICKUP', 'delivery_fee' => ['amount' => 0, 'currency' => 'TZS']],
            ['fulfillment_type' => 'PICKUP', 'total' => ['amount' => 1, 'currency' => 'TZS']],
            ['fulfillment_type' => 'PICKUP', 'status' => 'PENDING_PAYMENT'],
            ['fulfillment_type' => 'PICKUP', 'currency' => 'TZS'],
        ] as $input) {
            try {
                PickupFulfillmentState::fromInput($input);
                $this->fail('Expected invalid pickup input to be rejected.');
            } catch (DomainException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_billing_address_and_pickup_location_are_rejected(): void
    {
        foreach (['billing_address', 'pickup_location_id'] as $field) {
            try {
                PickupFulfillmentState::fromInput([
                    'fulfillment_type' => 'PICKUP',
                    $field => 'not-allowed',
                ]);
                $this->fail('Expected unsupported pickup field to be rejected.');
            } catch (DomainException) {
                $this->addToAssertionCount(1);
            }
        }
    }
}
