<?php

namespace Tests\Feature;

use App\Jobs\CreateOperationalNotifications;
use App\Models\Enquiry;
use App\Models\FurnitureRequest;
use App\Models\Notification;
use App\Models\User;
use App\Support\NotificationType;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OperationalNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);
    }

    public function test_request_notifications_are_created_once_for_each_eligible_recipient(): void
    {
        $requestManager = User::factory()->staff()->create();
        $secondRequestManager = User::factory()->staff()->create();
        $enquiryOnly = User::factory()->staff()->create();
        Role::findByName('STAFF')->revokePermissionTo('requests.manage');
        $requestManager->givePermissionTo('requests.manage');
        $secondRequestManager->givePermissionTo('requests.manage');
        $adminWithoutRequestPermission = User::factory()->admin()->create();
        Role::findByName('ADMIN')->revokePermissionTo('requests.manage');
        $inactive = User::factory()->staff()->create(['account_state' => 'SUSPENDED']);

        $request = FurnitureRequest::factory()->create();
        $job = new CreateOperationalNotifications(
            'furniture_request',
            (int) $request->getKey(),
            NotificationType::NEW_MADE_TO_ORDER_REQUEST,
        );

        $job->handle();
        $job->handle();

        $this->assertDatabaseCount('notifications', 2);
        $this->assertDatabaseHas('notifications', ['recipient_user_id' => $requestManager->id]);
        $this->assertDatabaseHas('notifications', ['recipient_user_id' => $secondRequestManager->id]);
        $this->assertDatabaseMissing('notifications', ['recipient_user_id' => $enquiryOnly->id]);
        $this->assertDatabaseMissing('notifications', ['recipient_user_id' => $adminWithoutRequestPermission->id]);
        $this->assertDatabaseMissing('notifications', ['recipient_user_id' => $inactive->id]);
    }

    public function test_enquiry_notifications_use_the_existing_enquiry_permission(): void
    {
        $enquiryManager = User::factory()->staff()->create();
        $requestOnly = User::factory()->staff()->create();
        Role::findByName('STAFF')->revokePermissionTo('enquiries.manage');
        $enquiryManager->givePermissionTo('enquiries.manage');
        $requestOnly->givePermissionTo('requests.manage');
        $enquiry = Enquiry::factory()->general()->create();

        (new CreateOperationalNotifications(
            'enquiry',
            (int) $enquiry->getKey(),
            NotificationType::NEW_ENQUIRY,
        ))->handle();

        $notification = Notification::query()->firstOrFail();
        $this->assertSame($enquiryManager->id, $notification->recipient_user_id);
        $this->assertSame(NotificationType::NEW_ENQUIRY, $notification->type);
        $this->assertSame('ENQUIRY', $notification->target['type']);
        $this->assertDatabaseMissing('notifications', ['recipient_user_id' => $requestOnly->id]);
    }

    public function test_each_recipient_has_an_independent_read_state(): void
    {
        $first = User::factory()->staff()->create();
        $second = User::factory()->staff()->create();
        $request = FurnitureRequest::factory()->create();

        (new CreateOperationalNotifications(
            'furniture_request',
            (int) $request->getKey(),
            NotificationType::NEW_MADE_TO_ORDER_REQUEST,
        ))->handle();

        $firstNotification = Notification::query()->where('recipient_user_id', $first->id)->firstOrFail();
        $secondNotification = Notification::query()->where('recipient_user_id', $second->id)->firstOrFail();
        $firstNotification->update(['read_at' => now()]);

        $this->assertTrue($firstNotification->fresh()->isRead());
        $this->assertFalse($secondNotification->fresh()->isRead());
        $this->assertSame('SUBMITTED', $request->fresh()->request_status->value);
    }
}
