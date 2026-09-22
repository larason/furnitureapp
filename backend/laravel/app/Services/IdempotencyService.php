<?php

namespace App\Services;

use App\Exceptions\Api\ApiException;
use App\Exceptions\IdempotencyClaimConflict;
use App\Models\IdempotencyKey;
use App\Models\User;
use App\Support\ApiErrorCode;
use App\Support\ConcurrentTransaction;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Shared durable idempotency store scoped by authenticated identity + action
 * + key (ADR/API-SEC-006). The stored key value is hashed, never persisted raw.
 */
final class IdempotencyService
{
    /**
     * @param  array<string, mixed>  $intent
     * @param  Closure(): array<string, mixed>  $operation
     */
    public function execute(User $actor, string $action, string $key, array $intent, Closure $operation): IdempotentOutcome
    {
        $keyHash = hash('sha256', $key);
        $fingerprint = hash('sha256', json_encode($intent, JSON_THROW_ON_ERROR));

        try {
            return ConcurrentTransaction::run(fn (): IdempotentOutcome => $this->run($actor, $action, $keyHash, $fingerprint, $operation));
        } catch (IdempotencyClaimConflict) {
            return $this->existingOutcome($actor, $action, $keyHash, $fingerprint);
        }
    }

    /**
     * @param  Closure(): array<string, mixed>  $operation
     */
    private function run(User $actor, string $action, string $keyHash, string $fingerprint, Closure $operation): IdempotentOutcome
    {
        $existing = $this->find($actor, $action, $keyHash);

        if ($existing !== null && $existing->isExpired()) {
            $existing->delete();
            $existing = null;
        }

        if ($existing !== null) {
            return $this->outcomeFrom($existing, $fingerprint);
        }

        $record = $this->claim($actor, $action, $keyHash, $fingerprint);

        $body = $operation();

        $record->fill([
            'response_status' => 200,
            'response_body' => $body,
        ])->save();

        return new IdempotentOutcome($body, false);
    }

    private function claim(User $actor, string $action, string $keyHash, string $fingerprint): IdempotencyKey
    {
        try {
            return IdempotencyKey::create([
                'actor_id' => $actor->getKey(),
                'action' => $action,
                'key_hash' => $keyHash,
                'request_fingerprint' => $fingerprint,
                'expires_at' => now()->addHours((int) config('idempotency.retention_hours')),
            ]);
        } catch (UniqueConstraintViolationException $exception) {
            throw new IdempotencyClaimConflict('The idempotency key is already claimed.', 0, $exception);
        }
    }

    private function existingOutcome(User $actor, string $action, string $keyHash, string $fingerprint): IdempotentOutcome
    {
        $existing = $this->find($actor, $action, $keyHash);

        if ($existing === null) {
            throw new ApiException(ApiErrorCode::CONFLICT, 'The idempotent operation is already in progress.', 409);
        }

        return $this->outcomeFrom($existing, $fingerprint);
    }

    private function outcomeFrom(IdempotencyKey $record, string $fingerprint): IdempotentOutcome
    {
        if (! hash_equals($record->request_fingerprint, $fingerprint)) {
            throw new ApiException(ApiErrorCode::DUPLICATE_OPERATION, 'The idempotency key was reused with a different request.', 409);
        }

        if ($record->response_status === null || $record->response_body === null) {
            throw new ApiException(ApiErrorCode::CONFLICT, 'The idempotent operation is already in progress.', 409);
        }

        return new IdempotentOutcome($record->response_body, true);
    }

    private function find(User $actor, string $action, string $keyHash): ?IdempotencyKey
    {
        return IdempotencyKey::query()
            ->where('actor_id', $actor->getKey())
            ->where('action', $action)
            ->where('key_hash', $keyHash)
            ->first();
    }
}
