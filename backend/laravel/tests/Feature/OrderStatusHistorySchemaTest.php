<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\User;
use App\Support\OrderActorType;
use App\Support\OrderStatus;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OrderStatusHistorySchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_table_exists_with_expected_columns_and_no_updated_at(): void
    {
        $this->assertTrue(Schema::hasTable('order_status_history'));
        $this->assertTrue(Schema::hasColumns('order_status_history', [
            'id',
            'order_id',
            'from_status',
            'to_status',
            'actor_type',
            'actor_id',
            'customer_note',
            'internal_note',
            'occurred_at',
            'created_at',
        ]));
        $this->assertFalse(Schema::hasColumn('order_status_history', 'updated_at'));
    }

    public function test_history_belongs_to_order(): void
    {
        $order = Order::factory()->create();
        $event = OrderStatusHistory::factory()->for($order)->create();

        $this->assertSame($order->id, $event->order_id);
        $this->assertTrue($event->order->is($order));
    }

    public function test_order_has_many_status_history_events(): void
    {
        $order = Order::factory()->create();
        $first = OrderStatusHistory::factory()->for($order)->create();
        $second = OrderStatusHistory::factory()->for($order)->create();

        $history = $order->fresh()->statusHistory;

        $this->assertCount(2, $history);
        $this->assertTrue($history->contains($first));
        $this->assertTrue($history->contains($second));
    }

    public function test_actor_relationship_resolves_for_user_event(): void
    {
        $order = Order::factory()->create();
        $staff = User::factory()->create();
        $event = OrderStatusHistory::factory()->byUser($staff)->for($order)->create();

        $this->assertTrue($event->actor->is($staff));
    }

    public function test_actor_is_null_for_system_event(): void
    {
        $event = OrderStatusHistory::factory()->create();

        $this->assertNull($event->actor);
    }

    public function test_event_cannot_exist_without_order(): void
    {
        $this->expectException(QueryException::class);

        OrderStatusHistory::factory()->create(['order_id' => 999999]);
    }

    public function test_deleting_order_cascades_to_history(): void
    {
        $order = Order::factory()->create();
        OrderStatusHistory::factory()->for($order)->create();
        OrderStatusHistory::factory()->for($order)->create();

        $this->assertSame(2, OrderStatusHistory::count());

        $order->delete();

        $this->assertSame(0, OrderStatusHistory::count());
    }

    public function test_deleting_actor_user_nulls_actor_id_without_deleting_event(): void
    {
        $staff = User::factory()->create();
        $event = OrderStatusHistory::factory()->byUser($staff)->create();

        $staff->delete();

        $fresh = $event->fresh();
        $this->assertNotNull($fresh);
        $this->assertNull($fresh->actor_id);
    }

    public function test_event_with_deleted_actor_nulls_but_stays_immutable(): void
    {
        $staff = User::factory()->create();
        $event = OrderStatusHistory::factory()->byUser($staff)->create();
        $staff->delete();

        $fresh = $event->fresh();
        $this->assertNull($fresh->actor_id);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Order status history events are immutable.');

        $fresh->customer_note = 'changed';
        $fresh->save();
    }

    public function test_initial_event_can_be_persisted(): void
    {
        $event = OrderStatusHistory::factory()->initial()->create();

        $this->assertNull($event->from_status);
        $this->assertSame(OrderStatus::PENDING_PAYMENT, $event->to_status);
        $this->assertSame(OrderActorType::SYSTEM, $event->actor_type);
        $this->assertNull($event->actor_id);
    }

    public function test_transition_event_can_be_persisted(): void
    {
        $order = Order::factory()->create();
        $staff = User::factory()->create();

        $event = OrderStatusHistory::factory()
            ->transition(OrderStatus::PROCESSING, OrderStatus::SHIPPED)
            ->byUser($staff)
            ->for($order)
            ->create();

        $this->assertSame(OrderStatus::PROCESSING, $event->from_status);
        $this->assertSame(OrderStatus::SHIPPED, $event->to_status);
        $this->assertSame(OrderActorType::STAFF, $event->actor_type);
        $this->assertSame($staff->id, $event->actor_id);
    }

    public function test_system_event_must_not_have_actor_id(): void
    {
        $user = User::factory()->create();

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('System events must not carry an actor id.');

        OrderStatusHistory::factory()->create(['actor_type' => OrderActorType::SYSTEM, 'actor_id' => $user->id]);
    }

    public function test_by_user_factory_rejects_system_actor(): void
    {
        $user = User::factory()->create();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('System events cannot have a user actor.');

        OrderStatusHistory::factory()->byUser($user, OrderActorType::SYSTEM);
    }

    public function test_non_system_event_requires_actor_id(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Customer, staff, and admin events require an actor id.');

        OrderStatusHistory::factory()->create(['actor_type' => OrderActorType::CUSTOMER, 'actor_id' => null]);
    }

    public function test_to_status_must_be_closed_v1_status(): void
    {
        $this->expectException(\ValueError::class);

        OrderStatusHistory::factory()->create(['to_status' => 'ON_HOLD']);
    }

    public function test_unknown_from_status_is_rejected(): void
    {
        $this->expectException(\ValueError::class);

        OrderStatusHistory::factory()->create(['from_status' => 'NEW']);
    }

    public function test_events_are_immutable_after_creation(): void
    {
        $event = OrderStatusHistory::factory()->create();

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Order status history events are immutable.');

        $event->internal_note = 'changed';
        $event->save();
    }

    public function test_events_cannot_be_deleted(): void
    {
        $event = OrderStatusHistory::factory()->create();

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Order status history events are append-only and cannot be deleted.');

        $event->delete();
    }

    public function test_ordering_is_occurred_at_asc_then_id_asc(): void
    {
        $order = Order::factory()->create();
        $t1 = Carbon::parse('2026-09-10 10:00:00');
        $t2 = Carbon::parse('2026-09-10 11:00:00');

        $eventP1 = OrderStatusHistory::factory()->for($order)->at($t2)->create();
        $eventA = OrderStatusHistory::factory()->for($order)->at($t1)->create();
        $eventB = OrderStatusHistory::factory()->for($order)->at($t1)->create();
        $eventP2 = OrderStatusHistory::factory()->for($order)->at($t2)->create();

        $ordered = $order->fresh()->statusHistory->pluck('id')->all();

        $this->assertSame([$eventA->id, $eventB->id, $eventP1->id, $eventP2->id], $ordered);
    }

    public function test_internal_note_can_exist_without_customer_note(): void
    {
        $event = OrderStatusHistory::factory()->withInternalNote('internal only')->create();

        $this->assertNull($event->customer_note);
        $this->assertSame('internal only', $event->internal_note);
    }

    public function test_customer_note_can_exist_without_internal_note(): void
    {
        $event = OrderStatusHistory::factory()->withCustomerNote('visible')->create();

        $this->assertSame('visible', $event->customer_note);
        $this->assertNull($event->internal_note);
    }

    public function test_no_staff_profile_fields_duplicated(): void
    {
        $columns = Schema::getColumnListing('order_status_history');

        foreach (['staff_name', 'staff_phone', 'admin_name', 'sender_name', 'actor_email'] as $col) {
            $this->assertNotContains($col, $columns);
        }
    }

    public function test_no_gps_or_logistics_fields(): void
    {
        $columns = Schema::getColumnListing('order_status_history');

        foreach (['latitude', 'longitude', 'carrier', 'tracking_number', 'tracking_url', 'delivery_route', 'vehicle', 'cancelled_at'] as $col) {
            $this->assertNotContains($col, $columns);
        }
    }

    public function test_status_history_is_not_general_audit_log(): void
    {
        $columns = Schema::getColumnListing('order_status_history');

        foreach (['request_id', 'action', 'resource_type', 'old_state', 'new_state_json'] as $col) {
            $this->assertNotContains($col, $columns);
        }
    }
}
