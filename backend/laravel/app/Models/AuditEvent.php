<?php

namespace App\Models;

use DomainException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $actor_id
 * @property string|null $actor_role
 * @property string $action
 * @property string $resource_type
 * @property string $resource_id
 * @property array<string, mixed>|null $previous_state
 * @property array<string, mixed>|null $resulting_state
 * @property string|null $request_id
 * @property Carbon $occurred_at
 * @property Carbon $created_at
 */
#[Fillable([
    'actor_id',
    'actor_role',
    'action',
    'resource_type',
    'resource_id',
    'previous_state',
    'resulting_state',
    'request_id',
    'occurred_at',
])]
class AuditEvent extends Model
{
    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        static::deleting(fn () => throw new DomainException('Audit events are append-only and cannot be deleted.'));
        static::updating(fn () => throw new DomainException('Audit events are immutable.'));
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    protected function casts(): array
    {
        return [
            'previous_state' => 'array',
            'resulting_state' => 'array',
            'occurred_at' => 'datetime',
        ];
    }
}
