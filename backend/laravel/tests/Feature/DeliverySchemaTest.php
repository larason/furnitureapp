<?php

namespace Tests\Feature;

use App\Models\Delivery;
use App\Models\Order;
use App\Support\AddressField;
use App\Support\FulfillmentType;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DeliverySchemaTest extends TestCase
{
    use RefreshDatabase;

    private function address(): array
    {
        return [
            'address_line' => '123 Example Street',
            'city' => 'Dar es Salaam',
        ];
    }

    public function test_deliveries_table_exists_with_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('deliveries'));
        $this->assertTrue(Schema::hasColumns('deliveries', [
            'id',
            'order_id',
            'recipient_name',
            'recipient_phone',
            'delivery_address',
            'delivery_instructions',
            'created_at',
            'updated_at',
        ]));
    }

    public function test_delivery_belongs_to_order(): void
    {
        $order = Order::factory()->deliveryFinalized()->create();
        $delivery = Delivery::factory()->forOrder($order->fresh())->create();

        $this->assertSame($order->id, $delivery->order_id);
        $this->assertTrue($delivery->order->is($order));
    }

    public function test_order_has_one_delivery(): void
    {
        $order = Order::factory()->deliveryFinalized()->create();
        $delivery = Delivery::factory()->forOrder($order->fresh())->create();

        $this->assertTrue($order->fresh()->delivery->is($delivery));
    }

    public function test_delivery_can_be_created_for_delivery_order(): void
    {
        $order = Order::factory()->deliveryFinalized()->create();

        $delivery = Delivery::factory()->forOrder($order)->create();

        $this->assertSame(FulfillmentType::DELIVERY, $order->fulfillment_type);
        $this->assertSame($order->recipient_name, $delivery->recipient_name);
        $this->assertSame($order->recipient_phone, $delivery->recipient_phone);
        $this->assertSame($order->delivery_address, $delivery->delivery_address);
    }

    public function test_pickup_order_cannot_receive_delivery_record(): void
    {
        $order = Order::factory()->pickup()->create();

        $delivery = Delivery::factory()->make([
            'order_id' => $order->id,
            'recipient_name' => $order->recipient_name,
            'recipient_phone' => $order->recipient_phone,
            'delivery_address' => $this->address(),
        ]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('A delivery record is only valid for a DELIVERY order.');

        $delivery->save();
    }

    public function test_factory_default_uses_delivery_order(): void
    {
        $delivery = Delivery::factory()->create();

        $this->assertSame(FulfillmentType::DELIVERY, $delivery->order->fulfillment_type);
    }

    public function test_delivery_requires_existing_order(): void
    {
        $delivery = Delivery::factory()->make([
            'order_id' => 999999,
            'recipient_name' => 'Cust',
            'recipient_phone' => '+255700000000',
            'delivery_address' => $this->address(),
        ]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Delivery requires an existing order.');

        $delivery->save();
    }

    public function test_duplicate_delivery_for_same_order_is_rejected(): void
    {
        $order = Order::factory()->deliveryFinalized()->create();
        Delivery::factory()->forOrder($order)->create();

        $this->expectException(QueryException::class);

        Delivery::factory()->forOrder($order)->create();
    }

    public function test_delivery_snapshot_is_independent_of_user_profile_changes(): void
    {
        $order = Order::factory()->deliveryFinalized()->create();
        $user = $order->customer;

        $delivery = Delivery::factory()->forOrder($order)->create();

        $user->update(['name' => 'Changed Name', 'phone' => '+255700000099']);

        $fresh = $delivery->fresh();

        $this->assertNotSame('Changed Name', $fresh->recipient_name);
        $this->assertNotSame('+255700000099', $fresh->recipient_phone);
        $this->assertSame($order->recipient_name, $fresh->recipient_name);
        $this->assertSame($order->recipient_phone, $fresh->recipient_phone);
    }

    public function test_delivery_recipient_name_must_match_order_snapshot(): void
    {
        $order = Order::factory()->deliveryFinalized()->create();

        $delivery = Delivery::factory()->make([
            'order_id' => $order->id,
            'recipient_name' => 'Different Person',
            'recipient_phone' => $order->recipient_phone,
            'delivery_address' => $order->delivery_address,
        ]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Delivery recipient name must match the order snapshot.');

        $delivery->save();
    }

    public function test_delivery_recipient_phone_must_match_order_snapshot(): void
    {
        $order = Order::factory()->deliveryFinalized()->create();

        $delivery = Delivery::factory()->make([
            'order_id' => $order->id,
            'recipient_name' => $order->recipient_name,
            'recipient_phone' => '+255700000111',
            'delivery_address' => $order->delivery_address,
        ]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Delivery recipient phone must match the order snapshot.');

        $delivery->save();
    }

    public function test_delivery_address_must_match_order_snapshot(): void
    {
        $order = Order::factory()->deliveryFinalized()->create();

        $delivery = Delivery::factory()->make([
            'order_id' => $order->id,
            'recipient_name' => $order->recipient_name,
            'recipient_phone' => $order->recipient_phone,
            'delivery_address' => $this->address(),
        ]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Delivery address must match the order snapshot.');

        $delivery->save();
    }

    public function test_delivery_instructions_may_differ_from_order_snapshot(): void
    {
        $order = Order::factory()->deliveryFinalized()->create();

        $delivery = Delivery::factory()->make([
            'order_id' => $order->id,
            'recipient_name' => $order->recipient_name,
            'recipient_phone' => $order->recipient_phone,
            'delivery_address' => $order->delivery_address,
            'delivery_instructions' => 'Gate code 4321.',
        ]);

        $delivery->save();

        $this->assertSame('Gate code 4321.', $delivery->fresh()->delivery_instructions);
        $this->assertSame($order->recipient_name, $delivery->recipient_name);
        $this->assertSame($order->delivery_address, $delivery->delivery_address);
    }

    public function test_valid_address_persists(): void
    {
        $order = Order::factory()->deliveryFinalized()->create([
            'delivery_address' => $this->address(),
        ]);

        $delivery = Delivery::factory()->forOrder($order)->create();

        $this->assertSame($this->address(), $delivery->fresh()->delivery_address);
    }

    public function test_address_with_unknown_fields_is_rejected(): void
    {
        $delivery = Delivery::factory()->make([
            'delivery_address' => ['anything' => 'arbitrary', 'secret' => 'x'],
        ]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Delivery address contains unsupported fields.');

        $delivery->save();
    }

    public function test_region_is_not_an_address_field(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Delivery address contains unsupported fields.');

        AddressField::normalize([...$this->address(), 'region' => 'Dar es Salaam']);
    }

    public function test_legacy_region_snapshot_is_normalized_to_city(): void
    {
        $order = Order::factory()->deliveryFinalized()->create([
            'delivery_address' => [
                ...$this->address(),
                'region' => 'Dar es Salaam',
                'postal_code' => null,
            ],
        ]);

        $this->assertSame($this->address(), $order->fresh()->delivery_address);
    }

    public function test_legacy_snapshot_accepts_non_matching_region_and_postal_code(): void
    {
        $order = Order::factory()->deliveryFinalized()->create([
            'delivery_address' => [
                ...$this->address(),
                'region' => 'Coastal Zone',
                'postal_code' => '14101',
            ],
        ]);

        $this->assertSame($this->address(), $order->fresh()->delivery_address);
    }

    public function test_delivery_creation_supports_legacy_region_snapshot(): void
    {
        $order = Order::factory()->deliveryFinalized()->create();
        $legacyAddress = [...$this->address(), 'region' => 'Dar es Salaam', 'postal_code' => null];

        $order->getConnection()->table('orders')->where('id', $order->id)->update([
            'delivery_address' => json_encode($legacyAddress, JSON_THROW_ON_ERROR),
        ]);

        $delivery = $order->fresh()->createDelivery();

        $this->assertSame($this->address(), $delivery->delivery_address);
    }

    public function test_instructions_update_does_not_rewrite_legacy_delivery_address(): void
    {
        $order = Order::factory()->deliveryFinalized()->create();
        $legacyAddress = [...$this->address(), 'region' => 'Coastal Zone', 'postal_code' => '14101'];

        $delivery = Delivery::factory()->forOrder($order)->create();
        $order->getConnection()->table('orders')->where('id', $order->id)->update([
            'delivery_address' => json_encode($legacyAddress, JSON_THROW_ON_ERROR),
        ]);
        $delivery->getConnection()->table('deliveries')->where('id', $delivery->id)->update([
            'delivery_address' => json_encode($legacyAddress, JSON_THROW_ON_ERROR),
        ]);

        $delivery = $delivery->fresh();
        $delivery->delivery_instructions = 'Leave at the gate.';
        $delivery->save();

        $this->assertSame($legacyAddress, $delivery->fresh()->delivery_address);
    }

    public function test_address_fields_are_trimmed_before_persistence(): void
    {
        $order = Order::factory()->deliveryFinalized()->create([
            'delivery_address' => [
                'address_line' => '  123 Example Street  ',
                'city' => '  Dar es Salaam  ',
            ],
        ]);

        $this->assertSame([
            'address_line' => '123 Example Street',
            'city' => 'Dar es Salaam',
        ], $order->fresh()->delivery_address);
    }

    public function test_address_without_required_fields_is_rejected(): void
    {
        $delivery = Delivery::factory()->make([
            'delivery_address' => ['city' => 'Dar es Salaam'],
        ]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Delivery address address_line is required.');

        $delivery->save();
    }

    public function test_recipient_name_is_required(): void
    {
        $delivery = Delivery::factory()->make(['recipient_name' => '   ']);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Delivery recipient name is required.');

        $delivery->save();
    }

    public function test_recipient_phone_is_required(): void
    {
        $delivery = Delivery::factory()->make(['recipient_phone' => '']);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Delivery recipient phone is required.');

        $delivery->save();
    }

    public function test_delivery_instructions_are_optional(): void
    {
        $delivery = Delivery::factory()->create(['delivery_instructions' => null]);

        $this->assertNull($delivery->delivery_instructions);
    }

    public function test_delivery_instructions_are_persisted(): void
    {
        $delivery = Delivery::factory()->withInstructions('Gate code 1234.')->create();

        $this->assertSame('Gate code 1234.', $delivery->fresh()->delivery_instructions);
    }

    public function test_blank_instructions_become_null(): void
    {
        $delivery = Delivery::factory()->make(['delivery_instructions' => '   ']);

        $delivery->save();

        $this->assertNull($delivery->fresh()->delivery_instructions);
    }

    public function test_snapshot_fields_are_immutable_after_creation(): void
    {
        $delivery = Delivery::factory()->create();

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Delivery recipient_name is immutable once recorded.');

        $delivery->recipient_name = 'Changed';
        $delivery->save();
    }

    public function test_no_status_field_exists(): void
    {
        $columns = Schema::getColumnListing('deliveries');

        $this->assertNotContains('delivery_status', $columns);
        $this->assertNotContains('status', $columns);
    }

    public function test_no_carrier_tracking_or_gps_fields_exist(): void
    {
        $columns = Schema::getColumnListing('deliveries');

        foreach (['tracking_number', 'tracking_url', 'carrier', 'carrier_name', 'carrier_code', 'latitude', 'longitude', 'route', 'eta', 'driver_id', 'assigned_staff_id', 'delivery_agent_id', 'vehicle'] as $column) {
            $this->assertNotContains($column, $columns, "Forbidden column {$column} must not exist");
        }
    }

    public function test_no_payment_inventory_or_product_fields_exist(): void
    {
        $columns = Schema::getColumnListing('deliveries');

        foreach (['payment_id', 'payment_status', 'amount_paid', 'payment_reference', 'provider_transaction_id', 'reserved_quantity', 'stock_quantity', 'warehouse_location', 'product_id', 'variant_id', 'sku', 'saved_address_id', 'customer_address_id'] as $column) {
            $this->assertNotContains($column, $columns, "Forbidden column {$column} must not exist");
        }
    }

    public function test_no_delivery_fee_or_total_fields_exist(): void
    {
        $columns = Schema::getColumnListing('deliveries');

        foreach (['delivery_fee_amount', 'delivery_fee_status', 'subtotal_amount', 'total_amount', 'delivery_fee'] as $column) {
            $this->assertNotContains($column, $columns, "Forbidden column {$column} must not exist");
        }
    }

    public function test_deleting_order_is_restricted_when_delivery_exists(): void
    {
        $delivery = Delivery::factory()->create();

        $this->expectException(QueryException::class);

        $delivery->order->delete();
    }

    public function test_deleting_user_is_restricted_while_they_own_an_order(): void
    {
        $order = Order::factory()->deliveryFinalized()->create();
        $delivery = Delivery::factory()->forOrder($order)->create();
        $user = $order->customer;

        $this->expectException(QueryException::class);

        $user->delete();
    }

    public function test_delivery_has_no_independent_status_or_financial_source(): void
    {
        $delivery = Delivery::factory()->create();

        $this->assertFalse(property_exists($delivery, 'fulfillment_type'));
        $this->assertNull($delivery->getAttribute('status'));
        $this->assertNull($delivery->getAttribute('total_amount'));
        $this->assertNull($delivery->getAttribute('payment_status'));
    }
}
