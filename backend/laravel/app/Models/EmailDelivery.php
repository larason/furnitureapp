<?php

namespace App\Models;

use App\Support\EmailDeliveryStatus;
use DomainException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $notification_id
 * @property int $recipient_user_id
 * @property string $channel
 * @property EmailDeliveryStatus $status
 * @property int $attempt_count
 * @property Carbon|null $last_attempted_at
 * @property string|null $provider_message_id
 * @property string|null $error_category
 * @property Carbon|null $failed_at
 */
#[Fillable([
    'notification_id',
    'recipient_user_id',
    'channel',
    'status',
    'attempt_count',
    'last_attempted_at',
    'provider_message_id',
    'error_category',
    'failed_at',
])]
final class EmailDelivery extends Model
{
    public const TABLE = 'notification_email_deliveries';

    public const CHANNEL = 'EMAIL';

    protected $table = self::TABLE;

    protected function casts(): array
    {
        return [
            'status' => EmailDeliveryStatus::class,
            'last_attempted_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    public function notification(): BelongsTo
    {
        return $this->belongsTo(Notification::class);
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_user_id');
    }

    public function resetForOperatorRetry(): void
    {
        if ($this->status !== EmailDeliveryStatus::FAILED || $this->failed_at === null) {
            throw new DomainException('Only permanently failed email deliveries can be reset.');
        }

        $this->forceFill([
            'status' => EmailDeliveryStatus::PENDING,
            'attempt_count' => 0,
            'last_attempted_at' => null,
            'provider_message_id' => null,
            'error_category' => null,
            'failed_at' => null,
        ])->save();
    }
}
