<?php

namespace App\Models;

use App\Support\NotificationType;
use DomainException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $recipient_user_id
 * @property NotificationType|null $type
 * @property string $title
 * @property string $message
 * @property array<string,mixed>|null $target
 * @property string|null $source_type
 * @property string|null $source_id
 * @property Carbon|null $read_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable([
    'recipient_user_id',
    'type',
    'title',
    'message',
    'target',
    'source_type',
    'source_id',
    'read_at',
])]
class Notification extends Model
{
    use HasFactory;

    public const TABLE = 'notifications';

    public const MAX_TITLE = 255;

    public const MAX_MESSAGE = 2000;

    public const MAX_SOURCE_TYPE = 80;

    public const MAX_SOURCE_ID = 36;

    public const TARGET_KEY_TYPE = 'type';

    public const TARGET_KEY_ID = 'id';

    protected $table = self::TABLE;

    protected $casts = [
        'type' => NotificationType::class,
        'target' => 'array',
        'read_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (Notification $notification): void {
            $notification->assertValid();
        });
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_user_id');
    }

    public function isRead(): bool
    {
        return $this->read_at !== null;
    }

    public function assertValid(): void
    {
        $this->assertRecipient();
        $this->assertType();
        $this->assertTitle();
        $this->assertMessage();
        $this->assertTarget();
        $this->assertSource();
    }

    private function assertRecipient(): void
    {
        if (empty($this->recipient_user_id)) {
            throw new DomainException('Notification recipient_user_id is required.');
        }
    }

    private function assertType(): void
    {
        if ($this->type === null) {
            throw new DomainException('Notification type must be a valid NotificationType.');
        }
    }

    private function assertTitle(): void
    {
        $title = trim((string) $this->title);

        if ($title === '') {
            throw new DomainException('Notification title is required.');
        }

        if (mb_strlen($title) > self::MAX_TITLE) {
            throw new DomainException('Notification title exceeds maximum length.');
        }
    }

    private function assertMessage(): void
    {
        $message = trim((string) $this->message);

        if ($message === '') {
            throw new DomainException('Notification message is required.');
        }

        if (mb_strlen($message) > self::MAX_MESSAGE) {
            throw new DomainException('Notification message exceeds maximum length.');
        }
    }

    private function assertTarget(): void
    {
        $target = $this->target;

        if ($target === null) {
            return;
        }

        $hasExpectedShape = count($target) === 2;
        $hasType = $hasExpectedShape
            && isset($target[self::TARGET_KEY_TYPE])
            && is_string($target[self::TARGET_KEY_TYPE])
            && trim($target[self::TARGET_KEY_TYPE]) !== '';
        $hasId = $hasExpectedShape
            && isset($target[self::TARGET_KEY_ID])
            && is_string($target[self::TARGET_KEY_ID])
            && trim($target[self::TARGET_KEY_ID]) !== '';

        if (! $hasExpectedShape || ! $hasType || ! $hasId) {
            throw new DomainException('Notification target must contain exactly "type" and "id" as non-empty strings.');
        }
    }

    private function assertSource(): void
    {
        $type = $this->source_type;
        $id = $this->source_id;

        $hasType = $type !== null;
        $hasId = $id !== null;

        if ($hasType !== $hasId) {
            throw new DomainException('Notification source_type and source_id must both be present or both be null.');
        }

        if (! $hasType) {
            return;
        }

        $this->assertSourceValue($type, 'source_type', self::MAX_SOURCE_TYPE);
        $this->assertSourceValue($id, 'source_id', self::MAX_SOURCE_ID);
    }

    private function assertSourceValue(string $value, string $field, int $max): void
    {
        if (trim($value) === '') {
            throw new DomainException("Notification {$field} must not be empty.");
        }

        if (mb_strlen($value) > $max) {
            throw new DomainException("Notification {$field} exceeds maximum length of {$max}.");
        }
    }
}
