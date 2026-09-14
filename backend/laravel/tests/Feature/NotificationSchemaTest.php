<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\User;
use App\Support\NotificationType;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class NotificationSchemaTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Migration / schema
    // -------------------------------------------------------------------------

    public function test_table_exists_with_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('notifications'));
        $this->assertTrue(Schema::hasColumns('notifications', [
            'id',
            'recipient_user_id',
            'type',
            'title',
            'message',
            'target',
            'source_type',
            'source_id',
            'read_at',
            'created_at',
            'updated_at',
        ]));
    }

    public function test_table_has_no_marketing_or_delivery_fields(): void
    {
        $forbidden = [
            'is_read',
            'campaign',
            'promotion',
            'coupon',
            'marketing_segment',
            'promotional_opt_in',
            'email_sent',
            'email_sent_at',
            'sms_sent',
            'push_sent',
            'fcm_token',
            'device_token',
            'is_staff_notification',
            'is_admin_notification',
            'unread_count',
        ];

        foreach ($forbidden as $column) {
            $this->assertFalse(
                Schema::hasColumn('notifications', $column),
                "Column '{$column}' must not exist in notifications table.",
            );
        }
    }

    // -------------------------------------------------------------------------
    // Recipient / ownership
    // -------------------------------------------------------------------------

    public function test_notification_requires_recipient_user_id(): void
    {
        $this->expectException(DomainException::class);

        $notification = new Notification([
            'type' => NotificationType::ORDER_RECEIVED,
            'title' => 'Test',
            'message' => 'Test message.',
        ]);
        $notification->save();
    }

    public function test_notification_belongs_to_user_as_recipient(): void
    {
        $user = User::factory()->create();
        $notification = Notification::factory()->forRecipient($user)->create();

        $this->assertTrue($notification->recipient->is($user));
    }

    public function test_recipient_identity_is_separate_from_message_content(): void
    {
        $user = User::factory()->create();
        $notification = Notification::factory()->forRecipient($user)->create([
            'message' => 'Your order OD-12345 has been received.',
        ]);

        $this->assertSame($user->id, $notification->recipient_user_id);
        $this->assertStringNotContainsString($user->email, $notification->message);
    }

    // -------------------------------------------------------------------------
    // Type
    // -------------------------------------------------------------------------

    public function test_notification_requires_valid_type(): void
    {
        $this->expectException(\ValueError::class);

        Notification::factory()->create(['type' => 'INVALID_TYPE']);
    }

    public function test_all_v1_order_notification_types_can_be_persisted(): void
    {
        $user = User::factory()->create();

        $orderTypes = [
            NotificationType::ORDER_RECEIVED,
            NotificationType::ORDER_ACCEPTED,
            NotificationType::ORDER_PROCESSING,
            NotificationType::ORDER_READY_FOR_PICKUP,
            NotificationType::ORDER_SHIPPED,
            NotificationType::ORDER_DELIVERED,
            NotificationType::ORDER_COMPLETED,
            NotificationType::ORDER_CANCELLED,
        ];

        foreach ($orderTypes as $type) {
            $notification = Notification::factory()->forRecipient($user)->orderNotification($type)->create();

            $this->assertSame($type, $notification->fresh()->type);
        }
    }

    public function test_all_v1_operational_notification_types_can_be_persisted(): void
    {
        $user = User::factory()->create();

        $operationalTypes = [
            NotificationType::NEW_ORDER,
            NotificationType::NEW_MADE_TO_ORDER_REQUEST,
            NotificationType::NEW_ENQUIRY,
        ];

        foreach ($operationalTypes as $type) {
            $notification = Notification::factory()->forRecipient($user)->operationalNotification($type)->create();

            $this->assertSame($type, $notification->fresh()->type);
        }
    }

    public function test_payment_types_are_not_defined_in_v1_registry(): void
    {
        $defined = array_column(NotificationType::cases(), 'value');

        foreach ($defined as $value) {
            $this->assertFalse(
                str_starts_with($value, 'PAYMENT_'),
                "PAYMENT_* enum value '{$value}' must not be defined until Group H finalises the payment contract.",
            );
        }
    }

    // -------------------------------------------------------------------------
    // Title / message
    // -------------------------------------------------------------------------

    public function test_notification_requires_title(): void
    {
        $this->expectException(DomainException::class);

        Notification::factory()->create(['title' => '']);
    }

    public function test_notification_requires_message(): void
    {
        $this->expectException(DomainException::class);

        Notification::factory()->create(['message' => '']);
    }

    public function test_title_and_message_do_not_contain_secrets(): void
    {
        $user = User::factory()->create();
        $notification = Notification::factory()->forRecipient($user)->create([
            'title' => 'Your order is ready',
            'message' => 'Your order OD-ABC123 is ready for pickup.',
        ]);

        $fresh = $notification->fresh();

        $this->assertStringNotContainsString('password', strtolower($fresh->title));
        $this->assertStringNotContainsString('token', strtolower($fresh->message));
        $this->assertStringNotContainsString('secret', strtolower($fresh->message));
    }

    // -------------------------------------------------------------------------
    // Read state
    // -------------------------------------------------------------------------

    public function test_new_notification_is_unread_by_default(): void
    {
        $notification = Notification::factory()->create();

        $this->assertNull($notification->read_at);
        $this->assertFalse($notification->isRead());
    }

    public function test_read_at_timestamp_marks_notification_as_read(): void
    {
        $notification = Notification::factory()->read()->create();

        $this->assertNotNull($notification->read_at);
        $this->assertTrue($notification->isRead());
    }

    public function test_no_is_read_column_exists(): void
    {
        $this->assertFalse(Schema::hasColumn('notifications', 'is_read'));
    }

    public function test_read_state_does_not_imply_business_state(): void
    {
        $notification = Notification::factory()->read()->create([
            'type' => NotificationType::ORDER_RECEIVED,
            'message' => 'Your order has been received.',
        ]);

        // Reading a notification does not change the notification type
        $this->assertSame(NotificationType::ORDER_RECEIVED, $notification->type);
    }

    // -------------------------------------------------------------------------
    // Ordering
    // -------------------------------------------------------------------------

    public function test_notifications_support_newest_first_ordering(): void
    {
        $user = User::factory()->create();

        $first = Notification::factory()->forRecipient($user)->create();
        Carbon::setTestNow(Carbon::now()->addSecond());
        $second = Notification::factory()->forRecipient($user)->create();
        Carbon::setTestNow(null);

        $results = Notification::where('recipient_user_id', $user->id)
            ->orderByDesc('created_at')
            ->orderBy('id')
            ->get();

        $this->assertTrue($results->first()->is($second));
        $this->assertTrue($results->last()->is($first));
    }

    // -------------------------------------------------------------------------
    // Target
    // -------------------------------------------------------------------------

    public function test_target_may_be_null(): void
    {
        $notification = Notification::factory()->create(['target' => null]);

        $this->assertNull($notification->fresh()->target);
    }

    public function test_valid_structured_target_can_be_persisted(): void
    {
        $notification = Notification::factory()->withTarget('ORDER', 'OD-12345')->create();

        $target = $notification->fresh()->target;

        $this->assertSame('ORDER', $target[Notification::TARGET_KEY_TYPE]);
        $this->assertSame('OD-12345', $target[Notification::TARGET_KEY_ID]);
    }

    public function test_target_missing_type_key_is_rejected(): void
    {
        $this->expectException(DomainException::class);

        Notification::factory()->create(['target' => ['id' => 'OD-12345']]);
    }

    public function test_target_missing_id_key_is_rejected(): void
    {
        $this->expectException(DomainException::class);

        Notification::factory()->create(['target' => ['type' => 'ORDER']]);
    }

    public function test_target_with_empty_type_is_rejected(): void
    {
        $this->expectException(DomainException::class);

        Notification::factory()->create(['target' => ['type' => '  ', 'id' => 'OD-12345']]);
    }

    public function test_target_with_empty_id_is_rejected(): void
    {
        $this->expectException(DomainException::class);

        Notification::factory()->create(['target' => ['type' => 'ORDER', 'id' => '']]);
    }

    public function test_target_with_non_string_id_is_rejected(): void
    {
        $this->expectException(DomainException::class);

        Notification::factory()->create(['target' => ['type' => 'ORDER', 'id' => 12345]]);
    }

    public function test_target_with_extra_fields_is_rejected(): void
    {
        $this->expectException(DomainException::class);

        Notification::factory()->create([
            'target' => ['type' => 'ORDER', 'id' => 'OD-12345', 'extra' => 'secret-data'],
        ]);
    }

    // -------------------------------------------------------------------------
    // Source
    // -------------------------------------------------------------------------

    public function test_source_fields_may_be_null(): void
    {
        $notification = Notification::factory()->create([
            'source_type' => null,
            'source_id' => null,
        ]);

        $this->assertNull($notification->source_type);
        $this->assertNull($notification->source_id);
    }

    public function test_source_traceability_can_be_persisted(): void
    {
        $notification = Notification::factory()
            ->withSource('ORDER_STATUS_HISTORY', 'evt-abc-123')
            ->create();

        $fresh = $notification->fresh();

        $this->assertSame('ORDER_STATUS_HISTORY', $fresh->source_type);
        $this->assertSame('evt-abc-123', $fresh->source_id);
    }

    public function test_source_type_without_source_id_is_rejected(): void
    {
        $this->expectException(DomainException::class);

        Notification::factory()->create([
            'source_type' => 'ORDER_STATUS_HISTORY',
            'source_id' => null,
        ]);
    }

    public function test_source_id_without_source_type_is_rejected(): void
    {
        $this->expectException(DomainException::class);

        Notification::factory()->create([
            'source_type' => null,
            'source_id' => 'evt-abc-123',
        ]);
    }

    public function test_empty_source_type_is_rejected(): void
    {
        $this->expectException(DomainException::class);

        Notification::factory()->create([
            'source_type' => '   ',
            'source_id' => 'evt-001',
        ]);
    }

    public function test_empty_source_id_is_rejected(): void
    {
        $this->expectException(DomainException::class);

        Notification::factory()->create([
            'source_type' => 'ORDER_STATUS_HISTORY',
            'source_id' => '',
        ]);
    }

    public function test_source_type_exceeding_max_length_is_rejected(): void
    {
        $this->expectException(DomainException::class);

        Notification::factory()->create([
            'source_type' => str_repeat('X', Notification::MAX_SOURCE_TYPE + 1),
            'source_id' => 'evt-001',
        ]);
    }

    public function test_source_id_exceeding_max_length_is_rejected(): void
    {
        $this->expectException(DomainException::class);

        Notification::factory()->create([
            'source_type' => 'ORDER_STATUS_HISTORY',
            'source_id' => str_repeat('x', Notification::MAX_SOURCE_ID + 1),
        ]);
    }

    public function test_source_identity_does_not_grant_resource_access(): void
    {
        // Source fields are purely for traceability — the values are stored strings
        // and carry no authorization capability. This test asserts the model does
        // not create any relationship or capability token from source fields.
        $notification = Notification::factory()
            ->withSource('ORDER_STATUS_HISTORY', 'evt-abc-123')
            ->create();

        $this->assertFalse(method_exists($notification, 'sourceModel'));
        $this->assertFalse(method_exists($notification, 'resolveSource'));
    }

    // -------------------------------------------------------------------------
    // Privacy / data minimisation
    // -------------------------------------------------------------------------

    public function test_customer_notification_excludes_internal_data(): void
    {
        $notification = Notification::factory()->create([
            'title' => 'Your order is confirmed',
            'message' => 'Your order OD-12345 has been confirmed.',
        ]);

        $attributes = $notification->fresh()->toArray();

        $internalKeys = ['staff_internal_notes', 'payment_secret', 'provider_key', 'webhook_signature'];

        foreach ($internalKeys as $key) {
            $this->assertArrayNotHasKey($key, $attributes);
        }
    }

    // -------------------------------------------------------------------------
    // Deduplication support
    // -------------------------------------------------------------------------

    public function test_same_source_event_can_be_identified_for_deduplication(): void
    {
        $user = User::factory()->create();

        $a = Notification::factory()->forRecipient($user)
            ->withSource('ORDER_STATUS_HISTORY', 'evt-001')
            ->create(['type' => NotificationType::ORDER_SHIPPED]);

        $existing = Notification::where([
            'recipient_user_id' => $user->id,
            'source_type' => 'ORDER_STATUS_HISTORY',
            'source_id' => 'evt-001',
            'type' => NotificationType::ORDER_SHIPPED->value,
        ])->first();

        $this->assertNotNull($existing);
        $this->assertTrue($existing->is($a));
    }

    public function test_different_source_events_are_not_blocked_as_duplicates(): void
    {
        $user = User::factory()->create();

        Notification::factory()->forRecipient($user)
            ->withSource('ORDER_STATUS_HISTORY', 'evt-001')
            ->create(['type' => NotificationType::ORDER_SHIPPED]);

        Notification::factory()->forRecipient($user)
            ->withSource('ORDER_STATUS_HISTORY', 'evt-002')
            ->create(['type' => NotificationType::ORDER_DELIVERED]);

        $this->assertSame(2, Notification::where('recipient_user_id', $user->id)->count());
    }
}
