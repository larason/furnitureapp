<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $actor_id
 * @property string $action
 * @property string $key_hash
 * @property string $request_fingerprint
 * @property int|null $response_status
 * @property array<string, mixed>|null $response_body
 * @property Carbon $expires_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable([
    'actor_id',
    'action',
    'key_hash',
    'request_fingerprint',
    'response_status',
    'response_body',
    'expires_at',
])]
class IdempotencyKey extends Model
{
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    protected function casts(): array
    {
        return [
            'response_body' => 'array',
            'response_status' => 'integer',
            'expires_at' => 'datetime',
        ];
    }
}
