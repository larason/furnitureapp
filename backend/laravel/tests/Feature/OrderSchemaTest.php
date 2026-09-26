<?php

namespace Tests\Feature;

use App\Models\Delivery;
use App\Models\Order;
use App\Models\User;
use App\Support\DeliveryFeeStatus;
use App\Support\FulfillmentType;
use App\Support\OrderStatus;
use Database\Factories\OrderFactory;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class OrderSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_orders_table_exists_after_migration(): void
    {
        $this->assertTrue(Schema::hasTable('orders'));
        $this->assertTrue(Schema::hasColumns('orders', [
            'id',
            'customer_id',
            'order_reference',
            'status',
            'fulfillment_type',
            'delivery_fee_status',
            'currency',
            'subtotal_amount',
            'delivery_fee_amount',
            'total_amount',
            'recipient_name',
            'recipient_phone',
            'delivery_address',
            'created_at',
            'updated_at',
        ]));
    }

    public function test_migration_rolls_back_and_reapplies(): void
    {
        Artisan::call('migrate:rollback');
        $this->assertFalse(Schema::hasTable('orders'));

        Artisan::call('migrate');
        $this->assertTrue(Schema::hasTable('orders'));
    }

    public function test_order_belongs_to_customer_via_customer_id(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->for($user, 'customer')->create();

        $this->assertSame($user->id, $order->customer_id);
        $this->assertTrue($order->customer->is($user));
    }

    public function test_customer_has_many_orders(): void
    {
        $user = User::factory()->create();
        $first = Order::factory()->for($user, 'customer')->create();
        $second = Order::factory()->for($user, 'customer')->create();

        $orders = $user->fresh()->orders;

        $this->assertCount(2, $orders);
        $this->assertTrue($orders->contains($first));
        $this->assertTrue($orders->contains($second));
    }

    public function test_deleting_customer_with_orders_is_restricted(): void
    {
        $user = User::factory()->create();
        Order::factory()->for($user, 'customer')->create();

        $this->expectException(QueryException::class);
        $user->delete();
    }

    public function test_server_controlled_fields_are_not_mass_assignable(): void
    {
        $fillable = (new Order)->getFillable();

        foreach (['customer_id', 'order_reference', 'status', 'delivery_fee_status', 'subtotal_amount', 'delivery_fee_amount', 'total_amount', 'currency', 'created_at', 'updated_at'] as $field) {
            $this->assertNotContains($field, $fillable);
        }
    }

    public function test_reference_matches_od_format(): void
    {
        $order = Order::factory()->create();

        $this->assertMatchesRegularExpression('/^OD-[A-Z0-9]{5}$/', $order->order_reference);
    }

    public function test_reference_is_unique(): void
    {
        $first = Order::factory()->create();
        $second = Order::factory()->create();

        $this->assertNotSame($first->order_reference, $second->order_reference);
    }

    public function test_duplicate_reference_is_rejected_by_database(): void
    {
        $existing = Order::factory()->create();

        $this->expectException(QueryException::class);

        DB::table('orders')->insert([
            'customer_id' => User::factory()->create()->id,
            'public_id' => strtolower((string) Str::ulid()),
            'order_reference' => $existing->order_reference,
            'status' => OrderStatus::PENDING_PAYMENT->value,
            'fulfillment_type' => FulfillmentType::PICKUP->value,
            'delivery_fee_status' => DeliveryFeeStatus::FINALIZED->value,
            'currency' => Order::CURRENCY_TZS,
            'subtotal_amount' => 10000,
            'delivery_fee_amount' => 0,
            'total_amount' => 10000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_reference_is_immutable_after_creation(): void
    {
        $order = Order::factory()->create();

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Order reference is immutable.');

        $order->order_reference = OrderFactory::generateReference();
        $order->save();
    }

    public function test_malformed_reference_is_rejected(): void
    {
        $order = Order::factory()->make(['order_reference' => 'ORDER-123']);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Order reference must follow OD-*****.');

        $order->save();
    }

    public function test_status_uses_exactly_the_nine_v1_states(): void
    {
        $this->assertSame(
            ['PENDING_PAYMENT', 'PAID', 'ACCEPTED', 'PROCESSING', 'READY_FOR_PICKUP', 'SHIPPED', 'DELIVERED', 'COMPLETED', 'CANCELLED'],
            array_column(OrderStatus::cases(), 'value')
        );
    }

    public function test_status_defaults_to_pending_payment(): void
    {
        $row = DB::table('orders')->insertGetId([
            'customer_id' => User::factory()->create()->id,
            'public_id' => strtolower((string) Str::ulid()),
            'order_reference' => OrderFactory::generateReference(),
            'fulfillment_type' => FulfillmentType::PICKUP->value,
            'delivery_fee_status' => DeliveryFeeStatus::FINALIZED->value,
            'currency' => Order::CURRENCY_TZS,
            'subtotal_amount' => 10000,
            'delivery_fee_amount' => 0,
            'total_amount' => 10000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertSame('PENDING_PAYMENT', DB::table('orders')->where('id', $row)->value('status'));
        $this->assertSame(OrderStatus::PENDING_PAYMENT, Order::find($row)->status);
    }

    public function test_public_id_cannot_be_cleared_by_raw_update(): void
    {
        $order = Order::factory()->create();

        $this->expectException(QueryException::class);

        DB::table('orders')->where('id', $order->id)->update(['public_id' => null]);
    }

    public function test_new_order_with_paid_status_is_rejected(): void
    {
        $order = Order::factory()->make(['status' => OrderStatus::PAID]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('New orders must start in PENDING_PAYMENT status.');

        $order->save();
    }

    public function test_new_order_without_status_defaults_to_pending_payment(): void
    {
        $order = Order::factory()->make(['status' => null]);
        $order->save();

        $this->assertSame(OrderStatus::PENDING_PAYMENT, $order->fresh()->status);
    }

    public function test_unknown_status_is_rejected(): void
    {
        $this->expectException(\ValueError::class);

        OrderStatus::from('ON_HOLD');
    }

    public function test_fulfillment_uses_only_pickup_and_delivery(): void
    {
        $this->assertSame(
            [FulfillmentType::PICKUP, FulfillmentType::DELIVERY],
            FulfillmentType::cases()
        );
    }

    public function test_unknown_fulfillment_type_is_rejected(): void
    {
        $this->expectException(\ValueError::class);

        FulfillmentType::from('EXPRESS');
    }

    public function test_fee_status_uses_only_pending_and_finalized(): void
    {
        $this->assertSame(
            [DeliveryFeeStatus::PENDING, DeliveryFeeStatus::FINALIZED],
            DeliveryFeeStatus::cases()
        );
    }

    public function test_pickup_order_with_delivery_address_is_rejected(): void
    {
        $order = Order::factory()->pickup()->make([
            'delivery_address' => ['address_line' => '123 Example Street', 'city' => 'Dar es Salaam'],
        ]);

        $this->expectException(DomainException::class);
        $order->save();
    }

    public function test_delivery_order_with_delivery_cannot_flip_to_pickup(): void
    {
        $order = Order::factory()->deliveryPending()->create();
        Delivery::factory()->forOrder($order)->create();

        $order->fulfillment_type = FulfillmentType::PICKUP;

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('An order with a delivery record cannot change to PICKUP.');

        $order->save();
    }

    public function test_delivery_order_without_delivery_can_flip_to_pickup(): void
    {
        $order = Order::factory()->deliveryPending()->create();

        $order->fulfillment_type = FulfillmentType::PICKUP;
        $order->delivery_fee_status = DeliveryFeeStatus::FINALIZED;
        $order->delivery_fee_amount = 0;
        $order->delivery_address = null;
        $order->save();

        $this->assertSame(FulfillmentType::PICKUP, $order->fresh()->fulfillment_type);
    }

    public function test_pickup_order_with_null_subtotal_is_rejected_by_application(): void
    {
        $order = Order::factory()->pickup()->make(['subtotal_amount' => null]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Order subtotal is required.');

        $order->save();
    }

    public function test_pickup_order_has_finalized_zero_fee_and_subtotal_total(): void
    {
        $order = Order::factory()->pickup()->create()->fresh();

        $this->assertTrue($order->isPickup());
        $this->assertFalse($order->isDelivery());
        $this->assertSame(DeliveryFeeStatus::FINALIZED, $order->delivery_fee_status);
        $this->assertSame(0, $order->delivery_fee_amount);
        $this->assertSame($order->subtotal_amount, $order->total_amount);
        $this->assertNull($order->delivery_address);
    }

    public function test_financial_values_are_immutable_once_paid(): void
    {
        $order = Order::factory()->pickup()->create();
        $order->status = OrderStatus::PAID;
        $order->save();

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Order financial values are immutable');

        $order->subtotal_amount += 5000;
        $order->delivery_fee_amount = 0;
        $order->total_amount += 5000;
        $order->save();
    }

    public function test_financial_values_are_immutable_once_fee_finalized(): void
    {
        $order = Order::factory()->deliveryFinalized(8000)->create();

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Order financial values are immutable');

        $order->subtotal_amount += 5000;
        $order->delivery_fee_amount = 8000;
        $order->total_amount = $order->subtotal_amount + 8000;
        $order->save();
    }

    public function test_delivery_fee_finalization_flow_is_still_allowed_while_pending_payment(): void
    {
        $order = Order::factory()->deliveryPending()->create();
        $subtotal = $order->subtotal_amount;
        $fee = 8000;

        $order->delivery_fee_status = DeliveryFeeStatus::FINALIZED;
        $order->delivery_fee_amount = $fee;
        $order->total_amount = $subtotal + $fee;
        $order->save();

        $fresh = $order->fresh();
        $this->assertSame(DeliveryFeeStatus::FINALIZED, $fresh->delivery_fee_status);
        $this->assertSame($fee, $fresh->delivery_fee_amount);
        $this->assertSame($subtotal + $fee, $fresh->total_amount);
    }

    public function test_currency_is_immutable_once_paid(): void
    {
        $order = Order::factory()->pickup()->create();
        $order->status = OrderStatus::PAID;
        $order->save();

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Order financial values are immutable');

        $order->currency = 'USD';
        $order->save();
    }

    public function test_delivery_pending_order_has_null_fee_and_provisional_total(): void
    {
        $order = Order::factory()->deliveryPending()->create()->fresh();

        $this->assertTrue($order->isDelivery());
        $this->assertSame(DeliveryFeeStatus::PENDING, $order->delivery_fee_status);
        $this->assertNull($order->delivery_fee_amount);
        $this->assertSame($order->subtotal_amount, $order->total_amount);
        $this->assertIsArray($order->delivery_address);
    }

    public function test_delivery_pending_order_with_total_not_equal_to_subtotal_is_rejected(): void
    {
        $order = Order::factory()->deliveryPending()->make(['total_amount' => 30000]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Pending delivery orders require a null delivery fee and a provisional total equal to subtotal.');
        $order->save();
    }

    public function test_delivery_finalized_order_has_fee_and_computed_total(): void
    {
        $order = Order::factory()->deliveryFinalized(8000)->create()->fresh();

        $this->assertSame(DeliveryFeeStatus::FINALIZED, $order->delivery_fee_status);
        $this->assertSame(8000, $order->delivery_fee_amount);
        $this->assertSame($order->subtotal_amount + 8000, $order->total_amount);
    }

    public function test_delivery_finalized_order_with_wrong_total_is_rejected(): void
    {
        $order = Order::factory()->deliveryFinalized(8000)->make(['total_amount' => 1]);

        $this->expectException(DomainException::class);
        $order->save();
    }

    public function test_finalized_delivery_order_requires_delivery_address(): void
    {
        $order = Order::factory()->deliveryFinalized(8000)->make(['delivery_address' => null]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Finalized delivery orders require a non-negative delivery fee, total equal to subtotal plus delivery fee, and a delivery address.');

        $order->save();
    }

    public function test_finalized_delivery_order_rejects_empty_address(): void
    {
        $order = Order::factory()->deliveryFinalized(8000)->make(['delivery_address' => []]);

        $this->expectException(DomainException::class);

        $order->save();
    }

    public function test_finalized_delivery_order_rejects_incomplete_address(): void
    {
        $order = Order::factory()->deliveryFinalized(8000)->make([
            'delivery_address' => ['address_line' => '1 St'],
        ]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Delivery address city is required.');

        $order->save();
    }

    public function test_order_delivery_snapshot_is_immutable_once_delivery_exists(): void
    {
        $order = Order::factory()->deliveryFinalized()->create();
        Delivery::factory()->forOrder($order)->create();

        $order->recipient_name = 'Changed Name';

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Order delivery snapshot is immutable once a delivery record exists.');

        $order->save();
    }

    public function test_order_delivery_address_is_immutable_once_delivery_exists(): void
    {
        $order = Order::factory()->deliveryFinalized()->create();
        Delivery::factory()->forOrder($order)->create();

        $order->delivery_address = ['address_line' => 'New St', 'city' => 'X'];

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Order delivery snapshot is immutable once a delivery record exists.');

        $order->save();
    }

    public function test_order_delivery_snapshot_remains_mutable_before_delivery_exists(): void
    {
        $order = Order::factory()->deliveryFinalized()->create();

        $order->recipient_name = 'Changed Name';
        $order->save();

        $this->assertSame('Changed Name', $order->fresh()->recipient_name);
    }

    public function test_amounts_are_integer_minor_units(): void
    {
        $order = Order::factory()->deliveryFinalized(8000)->create()->fresh();

        $this->assertIsInt($order->subtotal_amount);
        $this->assertIsInt($order->delivery_fee_amount);
        $this->assertIsInt($order->total_amount);
        $this->assertSame($order->subtotal_amount, DB::table('orders')->where('id', $order->id)->value('subtotal_amount'));
        $this->assertSame($order->subtotal_amount + 8000, DB::table('orders')->where('id', $order->id)->value('total_amount'));
    }

    public function test_currency_is_tzs(): void
    {
        $order = Order::factory()->create()->fresh();

        $this->assertSame('TZS', $order->currency);
        $this->assertSame('TZS', DB::table('orders')->where('id', $order->id)->value('currency'));
    }

    public function test_negative_subtotal_is_rejected_by_database(): void
    {
        $this->expectException(QueryException::class);

        DB::table('orders')->insert([
            'customer_id' => User::factory()->create()->id,
            'public_id' => strtolower((string) Str::ulid()),
            'order_reference' => OrderFactory::generateReference(),
            'status' => OrderStatus::PENDING_PAYMENT->value,
            'fulfillment_type' => FulfillmentType::PICKUP->value,
            'delivery_fee_status' => DeliveryFeeStatus::FINALIZED->value,
            'currency' => Order::CURRENCY_TZS,
            'subtotal_amount' => -100,
            'delivery_fee_amount' => 0,
            'total_amount' => -100,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_negative_delivery_fee_is_rejected_by_database(): void
    {
        $this->expectException(QueryException::class);

        DB::table('orders')->insert([
            'customer_id' => User::factory()->create()->id,
            'public_id' => strtolower((string) Str::ulid()),
            'order_reference' => OrderFactory::generateReference(),
            'status' => OrderStatus::PENDING_PAYMENT->value,
            'fulfillment_type' => FulfillmentType::DELIVERY->value,
            'delivery_fee_status' => DeliveryFeeStatus::FINALIZED->value,
            'currency' => Order::CURRENCY_TZS,
            'subtotal_amount' => 10000,
            'delivery_fee_amount' => -500,
            'total_amount' => 9500,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_subtotal_is_required(): void
    {
        $this->expectException(QueryException::class);

        DB::table('orders')->insert([
            'customer_id' => User::factory()->create()->id,
            'public_id' => strtolower((string) Str::ulid()),
            'order_reference' => OrderFactory::generateReference(),
            'status' => OrderStatus::PENDING_PAYMENT->value,
            'fulfillment_type' => FulfillmentType::PICKUP->value,
            'delivery_fee_status' => DeliveryFeeStatus::FINALIZED->value,
            'currency' => Order::CURRENCY_TZS,
            'subtotal_amount' => null,
            'delivery_fee_amount' => 0,
            'total_amount' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_invalid_customer_is_rejected_by_foreign_key(): void
    {
        $this->expectException(QueryException::class);

        Order::factory()->create(['customer_id' => 999999]);
    }

    public function test_recipient_snapshot_is_independent_of_customer_profile(): void
    {
        $user = User::factory()->create(['name' => 'Original Name', 'phone' => '+255700000001']);
        $order = Order::factory()->for($user, 'customer')->create([
            'recipient_name' => 'Snapshot Name',
            'recipient_phone' => '+255700000002',
        ]);

        $user->update(['name' => 'Changed Name', 'phone' => '+255700000003']);

        $order = $order->fresh();

        $this->assertSame('Snapshot Name', $order->recipient_name);
        $this->assertSame('+255700000002', $order->recipient_phone);
    }

    public function test_delivery_address_snapshot_is_independent_of_later_changes(): void
    {
        $address = ['address_line' => '123 Example Street', 'city' => 'Dar es Salaam'];
        $order = Order::factory()->deliveryFinalized()->create(['delivery_address' => $address])->fresh();

        $this->assertSame($address, $order->delivery_address);
    }

    public function test_order_has_no_saved_address_or_product_references(): void
    {
        $columns = Schema::getColumnListing('orders');

        foreach (['shipping_address_id', 'saved_address_id', 'customer_address_id', 'product_id', 'variant_id', 'items_json', 'line_items_json', 'payment_id', 'payment_status', 'tracking_number', 'courier', 'deleted_at', 'paid_at', 'shipped_at'] as $column) {
            $this->assertNotContains($column, $columns);
        }
    }

    public function test_order_does_not_use_soft_deletes(): void
    {
        $order = Order::factory()->create();

        $this->assertFalse(Schema::hasColumn('orders', 'deleted_at'));
        $this->assertFalse(method_exists($order, 'trashed'));
    }

    public function test_update_delivery_snapshot_mutates_under_lock(): void
    {
        $order = Order::factory()->deliveryFinalized()->create();

        $order->updateDeliverySnapshot(function (Order $locked): void {
            $locked->recipient_name = 'Changed Name';
        });

        $this->assertSame('Changed Name', $order->fresh()->recipient_name);
    }

    public function test_update_delivery_snapshot_rejects_when_delivery_exists(): void
    {
        $order = Order::factory()->deliveryFinalized()->create();
        Delivery::factory()->forOrder($order)->create();

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Order delivery snapshot is immutable once a delivery record exists.');

        $order->updateDeliverySnapshot(function (Order $locked): void {
            $locked->recipient_name = 'Changed Name';
        });
    }

    public function test_create_delivery_copies_order_snapshot(): void
    {
        $order = Order::factory()->deliveryFinalized()->create();

        $delivery = $order->createDelivery();

        $this->assertSame($order->id, $delivery->order_id);
        $this->assertSame($order->recipient_name, $delivery->recipient_name);
        $this->assertSame($order->recipient_phone, $delivery->recipient_phone);
        $this->assertSame($order->delivery_address, $delivery->delivery_address);
    }

    public function test_create_delivery_rejects_when_delivery_already_exists(): void
    {
        $order = Order::factory()->deliveryFinalized()->create();
        Delivery::factory()->forOrder($order)->create();

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('A delivery record already exists for this order.');

        $order->createDelivery();
    }

    public function test_create_delivery_rejects_pickup_order(): void
    {
        $order = Order::factory()->pickup()->create();

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('A delivery record is only valid for a DELIVERY order.');

        $order->createDelivery();
    }
}
