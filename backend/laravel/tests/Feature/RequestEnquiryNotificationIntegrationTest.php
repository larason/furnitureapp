<?php

namespace Tests\Feature;

use App\Jobs\CreateOperationalNotifications;
use App\Jobs\SendOperationalNotificationEmail;
use App\Models\Enquiry;
use App\Models\FurnitureRequest;
use App\Models\User;
use App\Support\NotificationType;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Support\AuthenticatesApiUser;
use Tests\TestCase;

final class RequestEnquiryNotificationIntegrationTest extends TestCase
{
    use AuthenticatesApiUser;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);
        config([
            'requests.route_enabled' => true,
            'enquiries.route_enabled' => true,
        ]);
    }

    public function test_anonymous_request_creates_recipient_scoped_operational_notifications(): void
    {
        Queue::fake();
        [$staff, $admin] = $this->operationalRecipients();

        $this->postJson('/api/v1/requests', [
            'name' => 'Anonymous requester',
            'phone' => '+255700000001',
            'notes' => 'A made-to-order table request.',
        ])->assertCreated();

        $request = FurnitureRequest::query()->sole();
        $this->assertNotificationJobWasDispatched(
            'furniture_request',
            $request->id,
            NotificationType::NEW_MADE_TO_ORDER_REQUEST,
        );

        $this->createNotifications('furniture_request', $request->id, NotificationType::NEW_MADE_TO_ORDER_REQUEST);

        $this->assertRecipients($staff, $admin, NotificationType::NEW_MADE_TO_ORDER_REQUEST);
    }

    public function test_authenticated_customer_enquiry_creates_recipient_scoped_operational_notifications(): void
    {
        Queue::fake();
        [$staff, $admin] = $this->operationalRecipients();
        $customer = User::factory()->customer()->create(['clerk_user_id' => 'notification_enquiry_customer']);

        $this->withHeaders($this->authenticateAs($customer))
            ->postJson('/api/v1/enquiries', [
                'subject' => 'Custom furniture enquiry',
                'message' => 'Please contact me about a custom table.',
            ])
            ->assertCreated();

        $enquiry = Enquiry::query()->sole();
        $this->assertNotificationJobWasDispatched('enquiry', $enquiry->id, NotificationType::NEW_ENQUIRY);

        $this->createNotifications('enquiry', $enquiry->id, NotificationType::NEW_ENQUIRY);

        $this->assertRecipients($staff, $admin, NotificationType::NEW_ENQUIRY);
        $this->assertDatabaseMissing('notifications', ['recipient_user_id' => $customer->id]);
    }

    public function test_authenticated_customer_request_uses_the_same_operational_event(): void
    {
        Queue::fake();
        [$staff, $admin] = $this->operationalRecipients();
        $customer = User::factory()->customer()->create(['clerk_user_id' => 'notification_request_customer']);

        $this->withHeaders($this->authenticateAs($customer))
            ->postJson('/api/v1/requests', [
                'name' => 'Authenticated requester',
                'phone' => '+255700000003',
            ])
            ->assertCreated();

        $request = FurnitureRequest::query()->sole();
        $this->assertNotificationJobWasDispatched(
            'furniture_request',
            $request->id,
            NotificationType::NEW_MADE_TO_ORDER_REQUEST,
        );

        $this->createNotifications('furniture_request', $request->id, NotificationType::NEW_MADE_TO_ORDER_REQUEST);

        $this->assertRecipients($staff, $admin, NotificationType::NEW_MADE_TO_ORDER_REQUEST);
        $this->assertDatabaseMissing('notifications', ['recipient_user_id' => $customer->id]);
    }

    public function test_anonymous_enquiry_uses_the_same_operational_event(): void
    {
        Queue::fake();
        [$staff, $admin] = $this->operationalRecipients();

        $this->postJson('/api/v1/enquiries', [
            'name' => 'Anonymous enquirer',
            'phone' => '+255700000004',
            'subject' => 'Custom furniture enquiry',
            'message' => 'Please contact me about a custom table.',
        ])->assertCreated();

        $enquiry = Enquiry::query()->sole();
        $this->assertNotificationJobWasDispatched('enquiry', $enquiry->id, NotificationType::NEW_ENQUIRY);

        $this->createNotifications('enquiry', $enquiry->id, NotificationType::NEW_ENQUIRY);

        $this->assertRecipients($staff, $admin, NotificationType::NEW_ENQUIRY);
    }

    public function test_invalid_public_submissions_do_not_dispatch_notification_work(): void
    {
        Queue::fake();

        $this->postJson('/api/v1/requests', ['name' => 'Missing contact'])
            ->assertUnprocessable();
        $this->postJson('/api/v1/enquiries', [
            'name' => 'Missing subject',
            'phone' => '+255700000002',
            'message' => 'A valid message without a subject.',
        ])->assertUnprocessable();

        Queue::assertNothingPushed();
        $this->assertDatabaseCount('notifications', 0);
    }

    /** @return array{0: User, 1: User} */
    private function operationalRecipients(): array
    {
        return [User::factory()->staff()->create(), User::factory()->admin()->create()];
    }

    private function assertNotificationJobWasDispatched(string $sourceType, int $sourceId, NotificationType $type): void
    {
        Queue::assertPushed(
            CreateOperationalNotifications::class,
            fn (CreateOperationalNotifications $job): bool => $job->sourceType === $sourceType
                && $job->sourceId === $sourceId
                && $job->type === $type,
        );
    }

    private function createNotifications(string $sourceType, int $sourceId, NotificationType $type): void
    {
        (new CreateOperationalNotifications($sourceType, $sourceId, $type))->handle();
    }

    private function assertRecipients(User $staff, User $admin, NotificationType $type): void
    {
        $this->assertDatabaseCount('notifications', 2);
        $this->assertDatabaseHas('notifications', [
            'recipient_user_id' => $staff->id,
            'type' => $type->value,
        ]);
        $this->assertDatabaseHas('notifications', [
            'recipient_user_id' => $admin->id,
            'type' => $type->value,
        ]);
        Queue::assertPushed(SendOperationalNotificationEmail::class, 2);
    }
}
