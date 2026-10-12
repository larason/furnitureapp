<?php

namespace Tests\Feature;

use App\Events\NotificationStateChanged;
use App\Jobs\CreateOperationalNotifications;
use App\Jobs\SendOperationalNotificationEmail;
use App\Mail\NewEnquiryNotification;
use App\Mail\NewFurnitureRequestNotification;
use App\Models\EmailDelivery;
use App\Models\Enquiry;
use App\Models\FurnitureRequest;
use App\Models\Notification;
use App\Models\User;
use App\Support\EmailDeliveryStatus;
use App\Support\EnquiryIdentifier;
use App\Support\FurnitureRequestIdentifier;
use App\Support\NotificationIdentifier;
use App\Support\NotificationRealtimeChange;
use App\Support\NotificationType;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Resend\Exceptions\ErrorException as ResendErrorException;
use Spatie\Permission\Models\Role;
use Symfony\Component\Mailer\Exception\TransportException;
use Tests\TestCase;

final class OperationalNotificationEmailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);
        config([
            'notifications.email.enabled' => true,
            'notifications.email.mailer' => 'array',
            'notifications.email.from_address' => 'notifications@example.test',
            'notifications.email.from_name' => 'SL Furnitures',
            'services.resend.key' => 'test-key',
        ]);
    }

    public function test_request_email_is_sent_to_the_existing_authorized_recipient(): void
    {
        Mail::fake();
        $recipient = User::factory()->staff()->create();
        $request = FurnitureRequest::factory()->create();
        $notification = $this->createNotification($recipient, NotificationType::NEW_MADE_TO_ORDER_REQUEST, 'furniture_request', $request->id);

        (new SendOperationalNotificationEmail($notification->id))->handle();

        Mail::assertSent(NewFurnitureRequestNotification::class, fn ($mail): bool => true);
        $this->assertDatabaseHas('notification_email_deliveries', [
            'notification_id' => $notification->id,
            'status' => EmailDeliveryStatus::ACCEPTED->value,
            'attempt_count' => 1,
        ]);
    }

    public function test_enquiry_email_template_has_html_and_plain_text_without_private_content(): void
    {
        $recipient = User::factory()->staff()->create();
        $enquiry = Enquiry::factory()->general()->create([
            'subject' => '<script>alert(1)</script>',
            'message' => 'Private enquiry body must not appear.',
        ]);
        $notification = $this->createNotification($recipient, NotificationType::NEW_ENQUIRY, 'enquiry', $enquiry->id);

        $mail = new NewEnquiryNotification($notification, $enquiry);
        $html = $mail->render();

        $this->assertStringContainsString('New customer enquiry received', $html);
        $this->assertStringContainsString('ENQ-', $html);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringNotContainsString('Private enquiry body must not appear.', $html);
        $text = view('emails.notifications.new-enquiry-text', [
            'enquiryReference' => $enquiry->enquiry_reference,
            'submittedAt' => $enquiry->created_at,
        ])->render();
        $this->assertStringContainsString('New customer enquiry received', $text);
    }

    public function test_email_templates_render_when_submission_time_is_missing(): void
    {
        $enquiryText = view('emails.notifications.new-enquiry-text', [
            'enquiryReference' => 'ENQ-TEST',
            'submittedAt' => null,
        ])->render();
        $requestText = view('emails.notifications.new-furniture-request-text', [
            'requestReference' => 'REQ-TEST',
            'submittedAt' => null,
        ])->render();

        $this->assertStringContainsString('Submitted:', $enquiryText);
        $this->assertStringContainsString('Submitted:', $requestText);
    }

    public function test_revoked_permission_prevents_email_and_records_permanent_failure(): void
    {
        Mail::fake();
        $recipient = User::factory()->staff()->create();
        Role::findByName('STAFF')->revokePermissionTo('requests.manage');
        $request = FurnitureRequest::factory()->create();
        $notification = $this->createNotification($recipient, NotificationType::NEW_MADE_TO_ORDER_REQUEST, 'furniture_request', $request->id);

        (new SendOperationalNotificationEmail($notification->id))->handle();

        Mail::assertNothingSent();
        $this->assertDatabaseHas('notification_email_deliveries', [
            'notification_id' => $notification->id,
            'status' => EmailDeliveryStatus::FAILED->value,
            'error_category' => 'RECIPIENT_NOT_ELIGIBLE',
        ]);
    }

    public function test_disabled_email_channel_skips_without_sending(): void
    {
        Mail::fake();
        config(['notifications.email.enabled' => false]);
        $recipient = User::factory()->staff()->create();
        $request = FurnitureRequest::factory()->create();
        $notification = $this->createNotification($recipient, NotificationType::NEW_MADE_TO_ORDER_REQUEST, 'furniture_request', $request->id);

        (new SendOperationalNotificationEmail($notification->id))->handle();

        Mail::assertNothingSent();
        $this->assertDatabaseHas('notification_email_deliveries', [
            'notification_id' => $notification->id,
            'status' => EmailDeliveryStatus::SKIPPED->value,
        ]);
    }

    public function test_duplicate_email_jobs_do_not_send_twice(): void
    {
        Mail::fake();
        $recipient = User::factory()->staff()->create();
        $request = FurnitureRequest::factory()->create();
        $notification = $this->createNotification($recipient, NotificationType::NEW_MADE_TO_ORDER_REQUEST, 'furniture_request', $request->id);

        $job = new SendOperationalNotificationEmail($notification->id);
        $job->handle();
        $job->handle();

        Mail::assertSentCount(1);
        $this->assertDatabaseCount('notification_email_deliveries', 1);
    }

    public function test_transient_transport_failure_is_recorded_and_released_for_retry(): void
    {
        $recipient = User::factory()->staff()->create();
        $request = FurnitureRequest::factory()->create();
        $notification = $this->createNotification($recipient, NotificationType::NEW_MADE_TO_ORDER_REQUEST, 'furniture_request', $request->id);
        $mailer = mock();
        $mailer->shouldReceive('to')->once()->withArgs(fn (User $actual): bool => $actual->is($recipient))->andReturnSelf();
        $mailer->shouldReceive('send')->once()->andThrow(new TransportException('temporary failure'));
        Mail::shouldReceive('mailer')->once()->with('array')->andReturn($mailer);

        $job = (new SendOperationalNotificationEmail($notification->id))->withFakeQueueInteractions();
        $job->handle();

        $this->assertDatabaseHas('notification_email_deliveries', [
            'notification_id' => $notification->id,
            'status' => EmailDeliveryStatus::FAILED->value,
            'attempt_count' => 1,
            'error_category' => 'TRANSPORT_FAILURE',
        ]);
        $job->assertReleased(60);
    }

    public function test_resend_recipient_rejection_is_permanent_and_not_released(): void
    {
        $recipient = User::factory()->staff()->create();
        $request = FurnitureRequest::factory()->create();
        $notification = $this->createNotification($recipient, NotificationType::NEW_MADE_TO_ORDER_REQUEST, 'furniture_request', $request->id);
        $mailer = mock();
        $mailer->shouldReceive('to')->once()->withArgs(fn (User $actual): bool => $actual->is($recipient))->andReturnSelf();
        $mailer->shouldReceive('send')->once()->andThrow(
            new TransportException(
                'Request to Resend API failed.',
                422,
                new ResendErrorException([
                    'message' => 'The recipient is invalid.',
                    'name' => 'validation_error',
                    'statusCode' => 422,
                ]),
            ),
        );
        Mail::shouldReceive('mailer')->once()->with('array')->andReturn($mailer);

        $job = (new SendOperationalNotificationEmail($notification->id))->withFakeQueueInteractions();
        $job->handle();

        $this->assertDatabaseHas('notification_email_deliveries', [
            'notification_id' => $notification->id,
            'status' => EmailDeliveryStatus::FAILED->value,
            'error_category' => 'PROVIDER_REJECTION',
        ]);
        $job->assertNotReleased();
    }

    public function test_permanently_failed_delivery_is_not_claimed_again_until_operator_reset(): void
    {
        Mail::fake();
        $recipient = User::factory()->staff()->create();
        $request = FurnitureRequest::factory()->create();
        $notification = $this->createNotification($recipient, NotificationType::NEW_MADE_TO_ORDER_REQUEST, 'furniture_request', $request->id);
        $delivery = EmailDelivery::query()->create([
            'notification_id' => $notification->id,
            'recipient_user_id' => $recipient->id,
            'channel' => EmailDelivery::CHANNEL,
            'status' => EmailDeliveryStatus::FAILED,
            'attempt_count' => 1,
            'error_category' => 'PROVIDER_REJECTION',
            'failed_at' => now(),
        ]);

        (new SendOperationalNotificationEmail($notification->id))->handle();

        Mail::assertNothingSent();
        $delivery->refresh();
        $this->assertSame(EmailDeliveryStatus::FAILED, $delivery->status);
        $this->assertSame(1, $delivery->attempt_count);

        $delivery->resetForOperatorRetry();
        (new SendOperationalNotificationEmail($notification->id))->handle();

        Mail::assertSent(NewFurnitureRequestNotification::class);
        $this->assertDatabaseHas('notification_email_deliveries', [
            'notification_id' => $notification->id,
            'status' => EmailDeliveryStatus::ACCEPTED->value,
            'attempt_count' => 1,
        ]);
    }

    public function test_recent_sending_delivery_is_released_until_stale_recovery_window(): void
    {
        Mail::fake();
        $recipient = User::factory()->staff()->create();
        $request = FurnitureRequest::factory()->create();
        $notification = $this->createNotification($recipient, NotificationType::NEW_MADE_TO_ORDER_REQUEST, 'furniture_request', $request->id);
        EmailDelivery::query()->create([
            'notification_id' => $notification->id,
            'recipient_user_id' => $recipient->id,
            'channel' => EmailDelivery::CHANNEL,
            'status' => EmailDeliveryStatus::SENDING,
            'attempt_count' => 1,
            'last_attempted_at' => now(),
        ]);

        $job = (new SendOperationalNotificationEmail($notification->id))->withFakeQueueInteractions();
        $job->handle();

        Mail::assertNothingSent();
        $job->assertReleased();
    }

    public function test_queue_retries_follow_configured_delivery_attempt_limit(): void
    {
        config(['notifications.email.max_attempts' => 2]);

        $job = new SendOperationalNotificationEmail(1);

        $this->assertSame(2, $job->tries);
    }

    public function test_logical_notification_dispatch_queues_email_after_creation(): void
    {
        Queue::fake();
        $recipient = User::factory()->staff()->create();
        $request = FurnitureRequest::factory()->create();

        (new CreateOperationalNotifications(
            'furniture_request',
            $request->id,
            NotificationType::NEW_MADE_TO_ORDER_REQUEST,
        ))->handle();

        (new CreateOperationalNotifications(
            'furniture_request',
            $request->id,
            NotificationType::NEW_MADE_TO_ORDER_REQUEST,
        ))->handle();

        Queue::assertPushed(SendOperationalNotificationEmail::class, 1);
        $this->assertDatabaseCount('notifications', 1);
        $this->assertSame($recipient->id, Notification::query()->value('recipient_user_id'));
    }

    public function test_logical_notification_broadcasts_a_minimal_created_signal_to_its_recipient(): void
    {
        Event::fake([NotificationStateChanged::class]);
        $recipient = User::factory()->staff()->create();
        $request = FurnitureRequest::factory()->create();

        (new CreateOperationalNotifications(
            'furniture_request',
            $request->id,
            NotificationType::NEW_MADE_TO_ORDER_REQUEST,
        ))->handle();

        $notification = Notification::query()->sole();
        Event::assertDispatched(
            NotificationStateChanged::class,
            function (NotificationStateChanged $event) use ($notification, $recipient): bool {
                return $event->broadcastOn()->name === 'private-notifications.'.$recipient->id
                    && $event->broadcastWith() === [
                        'notification_id' => NotificationIdentifier::encodeId($notification->id),
                        'change' => NotificationRealtimeChange::Created->value,
                    ];
            },
        );
    }

    public function test_enquiry_notification_broadcasts_a_minimal_created_signal_to_its_recipient(): void
    {
        Event::fake([NotificationStateChanged::class]);
        $recipient = User::factory()->staff()->create();
        $enquiry = Enquiry::factory()->general()->create();

        (new CreateOperationalNotifications(
            'enquiry',
            $enquiry->id,
            NotificationType::NEW_ENQUIRY,
        ))->handle();

        $notification = Notification::query()->sole();
        Event::assertDispatched(
            NotificationStateChanged::class,
            function (NotificationStateChanged $event) use ($notification, $recipient): bool {
                return $event->broadcastOn()->name === 'private-notifications.'.$recipient->id
                    && $event->broadcastWith() === [
                        'notification_id' => NotificationIdentifier::encodeId($notification->id),
                        'change' => NotificationRealtimeChange::Created->value,
                    ];
            },
        );
    }

    public function test_broadcast_failure_does_not_remove_the_persisted_notification(): void
    {
        Mail::fake();
        config(['broadcasting.default' => 'unavailable-reverb']);
        $recipient = User::factory()->staff()->create();
        $request = FurnitureRequest::factory()->create();

        (new CreateOperationalNotifications(
            'furniture_request',
            $request->id,
            NotificationType::NEW_MADE_TO_ORDER_REQUEST,
        ))->handle();

        $this->assertDatabaseCount('notifications', 1);
        $this->assertSame($recipient->id, Notification::query()->value('recipient_user_id'));
        Mail::assertSent(NewFurnitureRequestNotification::class);
    }

    private function createNotification(User $recipient, NotificationType $type, string $sourceType, int $sourceId): Notification
    {
        return Notification::factory()->forRecipient($recipient)->create([
            'type' => $type,
            'source_type' => $sourceType,
            'source_id' => $sourceType === 'furniture_request'
                ? FurnitureRequestIdentifier::encodeId($sourceId)
                : EnquiryIdentifier::encodeId($sourceId),
        ]);
    }
}
