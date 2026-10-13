<?php

namespace Tests\Feature;

use App\Events\NotificationStateChanged;
use App\Models\Notification;
use App\Models\User;
use App\Support\NotificationIdentifier;
use App\Support\NotificationRealtimeChange;
use App\Support\PermissionName;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\Support\AuthenticatesApiUser;
use Tests\TestCase;

final class NotificationApiTest extends TestCase
{
    use AuthenticatesApiUser;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);
    }

    public function test_notifications_are_recipient_scoped_and_paginated(): void
    {
        $customer = User::factory()->customer()->create(['clerk_user_id' => 'notification_customer']);
        $other = User::factory()->customer()->create(['clerk_user_id' => 'notification_other']);
        $owned = Notification::factory()->forRecipient($customer)->create();
        Notification::factory()->forRecipient($other)->create();

        $this->withHeaders($this->authenticateAs($customer))
            ->getJson('/api/v1/me/notifications?unread=true')
            ->assertOk()
            ->assertHeaderContains('Cache-Control', 'private')
            ->assertJsonPath('meta.pagination.total', 1)
            ->assertJsonPath('meta.unread_count', 1)
            ->assertJsonPath('data.0.id', NotificationIdentifier::encodeId((int) $owned->getKey()));
    }

    public function test_only_the_notification_recipient_can_change_read_state(): void
    {
        $customer = User::factory()->customer()->create(['clerk_user_id' => 'notification_owner']);
        $other = User::factory()->customer()->create(['clerk_user_id' => 'notification_foreign']);
        $owned = Notification::factory()->forRecipient($customer)->create();
        $foreign = Notification::factory()->forRecipient($other)->create();

        $this->withHeaders($this->authenticateAs($customer))
            ->patchJson('/api/v1/me/notifications/'.NotificationIdentifier::encodeId((int) $owned->getKey()), ['read' => true])
            ->assertOk()
            ->assertJsonPath('data.is_read', true)
            ->assertJsonStructure(['data' => ['read_at']]);

        $this->withHeaders($this->authenticateAs($customer))
            ->patchJson('/api/v1/me/notifications/'.NotificationIdentifier::encodeId((int) $foreign->getKey()), ['read' => true])
            ->assertNotFound()
            ->assertJsonPath('errors.0.code', 'RESOURCE_NOT_FOUND');

        $this->assertNull($foreign->fresh()->read_at);
    }

    public function test_operational_notification_list_rechecks_current_permissions(): void
    {
        $staff = User::factory()->staff()->create(['clerk_user_id' => 'notification_permission_list']);
        $requestNotification = Notification::factory()->forRecipient($staff)->create([
            'type' => 'NEW_MADE_TO_ORDER_REQUEST',
        ]);
        Notification::factory()->forRecipient($staff)->create(['type' => 'NEW_ENQUIRY']);
        $staff->roles->first()->revokePermissionTo(PermissionName::REQUESTS_MANAGE->value);

        $this->withHeaders($this->authenticateAs($staff))
            ->getJson('/api/v1/me/notifications')
            ->assertOk()
            ->assertJsonPath('meta.pagination.total', 1)
            ->assertJsonMissing(['id' => NotificationIdentifier::encodeId((int) $requestNotification->getKey())]);

        $this->withHeaders($this->authenticateAs($staff))
            ->patchJson('/api/v1/me/notifications/'.NotificationIdentifier::encodeId((int) $requestNotification->getKey()), ['read' => true])
            ->assertNotFound()
            ->assertJsonPath('errors.0.code', 'RESOURCE_NOT_FOUND');

        $this->assertNull($requestNotification->fresh()->read_at);
    }

    public function test_customer_cannot_read_or_mutate_historical_operational_notifications(): void
    {
        $staff = User::factory()->staff()->create(['clerk_user_id' => 'notification_role_changed']);
        $notification = Notification::factory()->forRecipient($staff)->create([
            'type' => 'NEW_ENQUIRY',
        ]);
        $staff->syncRoles('CUSTOMER');

        $this->withHeaders($this->authenticateAs($staff))
            ->getJson('/api/v1/me/notifications')
            ->assertOk()
            ->assertJsonPath('meta.pagination.total', 0);

        $this->withHeaders($this->authenticateAs($staff))
            ->patchJson('/api/v1/me/notifications/'.NotificationIdentifier::encodeId((int) $notification->getKey()), ['read' => true])
            ->assertNotFound()
            ->assertJsonPath('errors.0.code', 'RESOURCE_NOT_FOUND');

        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_out_of_range_page_reports_the_requested_page_with_empty_data(): void
    {
        $customer = User::factory()->customer()->create(['clerk_user_id' => 'notification_pagination']);
        Notification::factory()->count(2)->forRecipient($customer)->create();

        $this->withHeaders($this->authenticateAs($customer))
            ->getJson('/api/v1/me/notifications?per_page=1&page=9')
            ->assertOk()
            ->assertJsonPath('data', [])
            ->assertJsonPath('meta.pagination.current_page', 9)
            ->assertJsonPath('meta.pagination.last_page', 2)
            ->assertJsonPath('meta.pagination.has_next', false)
            ->assertJsonPath('meta.pagination.has_previous', true);
    }

    public function test_repeated_read_request_preserves_the_first_read_timestamp(): void
    {
        Event::fake([NotificationStateChanged::class]);
        $customer = User::factory()->customer()->create(['clerk_user_id' => 'notification_read_timestamp']);
        $firstReadAt = now()->subHour()->startOfSecond();
        $notification = Notification::factory()->forRecipient($customer)->create(['read_at' => $firstReadAt]);

        $this->withHeaders($this->authenticateAs($customer))
            ->patchJson('/api/v1/me/notifications/'.NotificationIdentifier::encodeId((int) $notification->getKey()), ['read' => true])
            ->assertOk();

        $this->assertSame(
            $firstReadAt->toISOString(),
            $notification->fresh()->read_at?->toISOString(),
        );
        Event::assertNotDispatched(NotificationStateChanged::class);
    }

    public function test_read_state_change_broadcasts_a_recipient_scoped_signal(): void
    {
        Event::fake([NotificationStateChanged::class]);
        $customer = User::factory()->customer()->create(['clerk_user_id' => 'notification_realtime_read']);
        $notification = Notification::factory()->forRecipient($customer)->create();

        $this->withHeaders($this->authenticateAs($customer))
            ->patchJson('/api/v1/me/notifications/'.NotificationIdentifier::encodeId((int) $notification->getKey()), ['read' => true])
            ->assertOk();

        Event::assertDispatched(
            NotificationStateChanged::class,
            function (NotificationStateChanged $event) use ($customer, $notification): bool {
                return $event->broadcastOn()->name === 'private-notifications.'.$customer->id
                    && $event->broadcastWith() === [
                        'notification_id' => NotificationIdentifier::encodeId((int) $notification->getKey()),
                        'change' => NotificationRealtimeChange::ReadStateChanged->value,
                    ];
            },
        );
    }
}
