<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\User;
use App\Support\NotificationIdentifier;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
