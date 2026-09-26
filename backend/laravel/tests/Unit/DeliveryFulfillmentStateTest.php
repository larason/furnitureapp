<?php

namespace Tests\Unit;

use App\Services\Checkout\DeliveryFulfillmentState;
use DomainException;
use PHPUnit\Framework\TestCase;

class DeliveryFulfillmentStateTest extends TestCase
{
    private function input(): array
    {
        return [
            'fulfillment_type' => 'DELIVERY',
            'delivery_address' => [
                'recipient_name' => ' Asha Mwangi ',
                'phone' => ' +255700000001 ',
                'address_line' => ' Block C, Mikocheni B ',
                'city' => ' Dar es Salaam ',
            ],
        ];
    }

    public function test_delivery_state_normalizes_address_and_copies_billing_snapshot(): void
    {
        $state = DeliveryFulfillmentState::fromInput($this->input());

        $expectedAddress = [
            'recipient_name' => 'Asha Mwangi',
            'phone' => '+255700000001',
            'address_line' => 'Block C, Mikocheni B',
            'city' => 'Dar es Salaam',
        ];

        $snapshots = $state->addressSnapshots();
        $snapshots['delivery_address']['city'] = 'Dodoma';

        $this->assertSame($expectedAddress, $state->toArray()['delivery_address']);
        $this->assertSame($expectedAddress, $state->toArray()['billing_address']);
        $this->assertSame('Dar es Salaam', $snapshots['billing_address']['city']);
    }

    public function test_delivery_state_has_pending_fee_provisional_total_and_no_payment(): void
    {
        $state = DeliveryFulfillmentState::fromInput($this->input());

        $this->assertSame([
            'fulfillment_type' => 'DELIVERY',
            'status' => 'PENDING_PAYMENT',
            'delivery_fee_status' => 'PENDING',
            'currency' => 'TZS',
            'subtotal_amount' => 170000,
            'delivery_fee_amount' => null,
            'total_amount' => 170000,
            'recipient_name' => 'Asha Mwangi',
            'recipient_phone' => '+255700000001',
            'delivery_address' => [
                'address_line' => 'Block C, Mikocheni B',
                'city' => 'Dar es Salaam',
            ],
        ], $state->orderAttributesForSubtotal(170000));

        $response = $state->responseForSubtotal(170000);
        $this->assertSame(['amount' => 170000, 'currency' => 'TZS'], $response['subtotal']);
        $this->assertNull($response['delivery_fee']);
        $this->assertSame(['amount' => 170000, 'currency' => 'TZS'], $response['total']);
        $this->assertNull($response['payment']);
    }

    public function test_delivery_rejects_missing_null_and_unknown_address_fields(): void
    {
        foreach ([
            ['fulfillment_type' => 'DELIVERY'],
            ['fulfillment_type' => 'DELIVERY', 'delivery_address' => null],
            ['fulfillment_type' => 'DELIVERY', 'delivery_address' => [...$this->input()['delivery_address'], 'region' => 'Coastal Zone']],
        ] as $input) {
            try {
                DeliveryFulfillmentState::fromInput($input);
                $this->fail('Expected invalid delivery input to be rejected.');
            } catch (DomainException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_delivery_rejects_missing_nested_fields_and_client_controlled_fields(): void
    {
        foreach ([
            ['fulfillment_type' => 'DELIVERY', 'delivery_address' => ['phone' => '+255700000001', 'address_line' => 'Street', 'city' => 'Dar es Salaam']],
            ['fulfillment_type' => 'DELIVERY', 'delivery_address' => $this->input()['delivery_address'], 'delivery_fee' => null],
            ['fulfillment_type' => 'delivery', 'delivery_address' => $this->input()['delivery_address']],
        ] as $input) {
            try {
                DeliveryFulfillmentState::fromInput($input);
                $this->fail('Expected invalid delivery input to be rejected.');
            } catch (DomainException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_delivery_rejects_overlong_recipient_name_and_invalid_phone(): void
    {
        foreach ([
            ['recipient_name' => str_repeat('A', 256)],
            ['phone' => str_repeat('7', 31)],
            ['phone' => 'not-a-phone'],
        ] as $replacement) {
            $input = $this->input();
            $input['delivery_address'] = [...$input['delivery_address'], ...$replacement];

            try {
                DeliveryFulfillmentState::fromInput($input);
                $this->fail('Expected invalid delivery contact to be rejected.');
            } catch (DomainException) {
                $this->addToAssertionCount(1);
            }
        }
    }
}
