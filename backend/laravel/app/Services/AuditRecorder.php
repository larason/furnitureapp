<?php

namespace App\Services;

use App\Models\AuditEvent;
use App\Models\User;
use App\Support\AuditAction;
use App\Support\AuditResourceType;

/**
 * Durable privileged-action audit recorder (api-contract.md §30.16).
 */
class AuditRecorder
{
    /**
     * @param  array<string, mixed>|null  $previousState
     * @param  array<string, mixed>|null  $resultingState
     */
    public function record(
        ?User $actor,
        AuditAction $action,
        AuditResourceType $resourceType,
        string $resourceId,
        ?array $previousState,
        ?array $resultingState,
        ?string $requestId,
    ): AuditEvent {
        return AuditEvent::create([
            'actor_id' => $actor?->getKey(),
            'actor_role' => $actor?->getRoleNames()->first(),
            'action' => $action->value,
            'resource_type' => $resourceType->value,
            'resource_id' => $resourceId,
            'previous_state' => $previousState,
            'resulting_state' => $resultingState,
            'request_id' => $requestId,
            'occurred_at' => now(),
        ]);
    }
}
