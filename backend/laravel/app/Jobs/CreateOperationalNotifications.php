<?php

namespace App\Jobs;

use App\Models\Enquiry;
use App\Models\FurnitureRequest;
use App\Models\Notification;
use App\Models\User;
use App\Support\EnquiryIdentifier;
use App\Support\FurnitureRequestIdentifier;
use App\Support\NotificationType;
use App\Support\PermissionName;
use App\Support\RoleName;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

final class CreateOperationalNotifications implements ShouldQueue
{
    use Dispatchable, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public readonly string $sourceType,
        public readonly int $sourceId,
        public readonly NotificationType $type,
    ) {}

    public function handle(): void
    {
        $source = $this->source();
        if ($source === null) {
            return;
        }

        $permission = $this->permission();
        $recipients = User::query()
            ->where('account_state', 'ACTIVE')
            ->whereHas('roles', fn ($query) => $query->whereIn('name', [
                RoleName::STAFF->value,
                RoleName::ADMIN->value,
            ]))
            ->get()
            ->filter(fn (User $user): bool => $user->checkPermissionTo($permission));

        if ($recipients->isEmpty()) {
            Log::warning('Operational notification skipped: no eligible recipients.', [
                'notification_type' => $this->type->value,
                'source_type' => $this->sourceType,
            ]);

            return;
        }

        foreach ($recipients as $recipient) {
            try {
                Notification::query()->insertOrIgnore([
                    'recipient_user_id' => $recipient->getKey(),
                    'type' => $this->type->value,
                    'title' => $this->title(),
                    'message' => $this->message(),
                    'target' => json_encode($this->target(), JSON_THROW_ON_ERROR),
                    'source_type' => $this->sourceType,
                    'source_id' => $this->sourceIdentifier(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } catch (Throwable $exception) {
                Log::warning('Operational notification creation failed.', [
                    'notification_type' => $this->type->value,
                    'source_type' => $this->sourceType,
                    'exception' => $exception::class,
                ]);

                throw $exception;
            }
        }
    }

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [5, 30];
    }

    private function source(): FurnitureRequest|Enquiry|null
    {
        return match ($this->sourceType) {
            'furniture_request' => FurnitureRequest::query()->find($this->sourceId),
            'enquiry' => Enquiry::query()->find($this->sourceId),
            default => null,
        };
    }

    private function permission(): string
    {
        return match ($this->type) {
            NotificationType::NEW_MADE_TO_ORDER_REQUEST => PermissionName::REQUESTS_MANAGE->value,
            NotificationType::NEW_ENQUIRY => PermissionName::ENQUIRIES_MANAGE->value,
            default => throw new \LogicException('Unsupported operational notification type.'),
        };
    }

    private function title(): string
    {
        return match ($this->type) {
            NotificationType::NEW_MADE_TO_ORDER_REQUEST => 'New made-to-order request',
            NotificationType::NEW_ENQUIRY => 'New enquiry',
            default => throw new \LogicException('Unsupported operational notification type.'),
        };
    }

    private function message(): string
    {
        return match ($this->type) {
            NotificationType::NEW_MADE_TO_ORDER_REQUEST => 'A new made-to-order request requires review.',
            NotificationType::NEW_ENQUIRY => 'A new enquiry requires review.',
            default => throw new \LogicException('Unsupported operational notification type.'),
        };
    }

    /** @return array{type: string, id: string} */
    private function target(): array
    {
        return [
            'type' => $this->sourceType === 'furniture_request' ? 'REQUEST' : 'ENQUIRY',
            'id' => $this->sourceIdentifier(),
        ];
    }

    private function sourceIdentifier(): string
    {
        return match ($this->sourceType) {
            'furniture_request' => FurnitureRequestIdentifier::encodeId($this->sourceId),
            'enquiry' => EnquiryIdentifier::encodeId($this->sourceId),
            default => (string) $this->sourceId,
        };
    }
}
