<?php

namespace App\Jobs;

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
use App\Support\NotificationType;
use App\Support\PermissionName;
use App\Support\RoleName;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Resend\Exceptions\ErrorException as ResendErrorException;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Throwable;

final class SendOperationalNotificationEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries;

    private ?int $claimRetryAfter = null;

    public function __construct(public readonly int $notificationId)
    {
        $this->tries = $this->maxAttempts();
    }

    public function handle(): void
    {
        $notification = Notification::query()->find($this->notificationId);
        if ($notification === null) {
            return;
        }

        $claim = $this->claim($notification);
        if ($claim === false) {
            $this->release($this->claimRetryAfter ?? 1);

            return;
        }

        if ($claim === null) {
            return;
        }

        $delivery = EmailDelivery::query()->findOrFail($claim);
        $recipient = $delivery->recipient;
        if (! $recipient instanceof User
            || ! $this->isEligible($recipient, $notification)
            || ! filter_var($recipient->email, FILTER_VALIDATE_EMAIL)) {
            $this->markFailed($delivery, 'RECIPIENT_NOT_ELIGIBLE', true);

            return;
        }

        if (! $this->emailIsEnabled()) {
            $this->markSkipped($delivery);

            return;
        }

        if (! $this->senderIsConfigured()) {
            $this->markFailed($delivery, 'SENDER_NOT_CONFIGURED', true);

            return;
        }

        try {
            $sent = Mail::mailer((string) config('notifications.email.mailer'))
                ->to($recipient)
                ->send($this->mailable($notification));

            $delivery->forceFill([
                'status' => EmailDeliveryStatus::ACCEPTED,
                'provider_message_id' => is_object($sent) && method_exists($sent, 'getMessageId')
                    ? $sent->getMessageId()
                    : null,
                'error_category' => null,
                'failed_at' => null,
                'updated_at' => now(),
            ])->save();
        } catch (Throwable $exception) {
            [$errorCategory, $transient] = $this->classifyFailure($exception);
            $this->markFailed($delivery, $errorCategory, ! $transient);
            Log::warning('notification.email_delivery_failed', [
                'delivery_id' => $delivery->getKey(),
                'notification_type' => $notification->type->value,
                'error_category' => $errorCategory,
                'provider_status_code' => $this->providerStatusCode($exception),
                'exception' => $exception::class,
            ]);

            if ($transient && $delivery->attempt_count < $this->maxAttempts()) {
                $this->release($this->retryDelay($delivery->attempt_count, $errorCategory));
            }
        }
    }

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [60, 300, 900, 1800];
    }

    private function claim(Notification $notification): int|false|null
    {
        $this->claimRetryAfter = null;

        return DB::transaction(
            function () use ($notification): int|false|null {
                $delivery = EmailDelivery::query()
                    ->where('notification_id', $notification->getKey())
                    ->where('channel', EmailDelivery::CHANNEL)
                    ->lockForUpdate()
                    ->first();

                if ($delivery === null) {
                    $delivery = EmailDelivery::query()->create([
                        'notification_id' => $notification->getKey(),
                        'recipient_user_id' => $notification->recipient_user_id,
                        'channel' => EmailDelivery::CHANNEL,
                        'status' => EmailDeliveryStatus::PENDING,
                        'attempt_count' => 0,
                    ]);
                }

                if (in_array($delivery->status, [EmailDeliveryStatus::ACCEPTED, EmailDeliveryStatus::SKIPPED], true)
                    || ($delivery->status === EmailDeliveryStatus::FAILED && $delivery->failed_at !== null)) {
                    return null;
                }

                if ($delivery->attempt_count >= $this->maxAttempts()) {
                    $this->markFailed($delivery, 'RETRY_LIMIT_EXCEEDED', false);

                    return null;
                }

                if ($delivery->status === EmailDeliveryStatus::SENDING
                    && $delivery->last_attempted_at?->greaterThan(now()->subMinutes($this->staleAfterMinutes()))) {
                    $retryAt = $delivery->last_attempted_at->copy()->addMinutes($this->staleAfterMinutes());

                    $this->claimRetryAfter = max(1, $retryAt->timestamp - now()->timestamp);

                    return false;
                }

                $delivery->forceFill([
                    'status' => EmailDeliveryStatus::SENDING,
                    'attempt_count' => $delivery->attempt_count + 1,
                    'last_attempted_at' => now(),
                    'error_category' => null,
                    'failed_at' => null,
                ])->save();

                return (int) $delivery->getKey();
            },
        );
    }

    private function isEligible(User $recipient, Notification $notification): bool
    {
        $permission = match ($notification->type) {
            NotificationType::NEW_MADE_TO_ORDER_REQUEST => PermissionName::REQUESTS_MANAGE->value,
            NotificationType::NEW_ENQUIRY => PermissionName::ENQUIRIES_MANAGE->value,
            default => null,
        };

        return $permission !== null
            && $recipient->account_state === 'ACTIVE'
            && $recipient->hasAnyRole([RoleName::STAFF->value, RoleName::ADMIN->value])
            && $recipient->checkPermissionTo($permission);
    }

    private function emailIsEnabled(): bool
    {
        return (bool) config('notifications.email.enabled');
    }

    private function senderIsConfigured(): bool
    {
        $mailer = (string) config('notifications.email.mailer');
        $key = config('services.resend.key');

        return is_string(config('notifications.email.from_address'))
            && filter_var(config('notifications.email.from_address'), FILTER_VALIDATE_EMAIL)
            && $mailer !== ''
            && ($mailer !== 'resend' || (is_string($key) && trim($key) !== ''));
    }

    private function mailable(Notification $notification): NewFurnitureRequestNotification|NewEnquiryNotification
    {
        return match ($notification->type) {
            NotificationType::NEW_MADE_TO_ORDER_REQUEST => new NewFurnitureRequestNotification(
                $notification,
                FurnitureRequest::query()->findOrFail(FurnitureRequestIdentifier::decode((string) $notification->source_id)),
            ),
            NotificationType::NEW_ENQUIRY => new NewEnquiryNotification(
                $notification,
                Enquiry::query()->findOrFail(EnquiryIdentifier::decode((string) $notification->source_id)),
            ),
            default => throw new \LogicException('Unsupported operational email notification type.'),
        };
    }

    private function markFailed(EmailDelivery $delivery, string $category, bool $permanent): void
    {
        $delivery->forceFill([
            'status' => EmailDeliveryStatus::FAILED,
            'error_category' => $category,
            'failed_at' => $permanent || $delivery->attempt_count >= $this->maxAttempts() ? now() : null,
            'updated_at' => now(),
        ])->save();
    }

    private function markSkipped(EmailDelivery $delivery): void
    {
        $delivery->forceFill([
            'status' => EmailDeliveryStatus::SKIPPED,
            'error_category' => 'EMAIL_DISABLED',
            'updated_at' => now(),
        ])->save();
    }

    /** @return array{0: string, 1: bool} */
    private function classifyFailure(Throwable $exception): array
    {
        $statusCode = $this->providerStatusCode($exception);

        if ($statusCode === 429) {
            return ['RATE_LIMITED', true];
        }

        if ($statusCode === 408 || $statusCode === 425 || ($statusCode !== null && $statusCode >= 500)) {
            return ['TRANSPORT_FAILURE', true];
        }

        if ($statusCode !== null) {
            return ['PROVIDER_REJECTION', false];
        }

        return $exception instanceof TransportExceptionInterface
            ? ['TRANSPORT_FAILURE', true]
            : ['PROVIDER_REJECTION', false];
    }

    private function providerStatusCode(Throwable $exception): ?int
    {
        $current = $exception;

        while ($current !== null) {
            if ($current instanceof ResendErrorException) {
                return $current->getErrorCode();
            }

            if ($current instanceof TransportExceptionInterface && $current->getCode() > 0) {
                return $current->getCode();
            }

            $current = $current->getPrevious();
        }

        return null;
    }

    private function maxAttempts(): int
    {
        return max(1, (int) config('notifications.email.max_attempts', 3));
    }

    private function staleAfterMinutes(): int
    {
        return max(1, (int) config('notifications.email.stale_after_minutes', 15));
    }

    private function retryDelay(int $attempt, string $errorCategory): int
    {
        if ($errorCategory === 'RATE_LIMITED') {
            return 300;
        }

        $backoff = $this->backoff();

        return $backoff[min(max(0, $attempt - 1), count($backoff) - 1)];
    }
}
