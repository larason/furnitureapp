<?php

namespace App\Services\AuditLogs;

use App\Models\AuditEvent;
use Illuminate\Pagination\LengthAwarePaginator;

final class ListAuditLogs
{
    /** @return LengthAwarePaginator<int, AuditEvent> */
    public function paginate(AuditLogQuery $filters): LengthAwarePaginator
    {
        return AuditEvent::query()
            ->when($filters->actorId !== null, fn ($query) => $query->where('actor_id', $filters->actorId))
            ->when($filters->action !== null, fn ($query) => $query->where('action', $filters->action->value))
            ->when($filters->resourceType !== null, fn ($query) => $query->where('resource_type', $filters->resourceType->value))
            ->when($filters->resourceId !== null, fn ($query) => $query->where('resource_id', $filters->resourceId))
            ->when($filters->createdFrom !== null, fn ($query) => $query->where('occurred_at', '>=', $filters->createdFrom))
            ->when($filters->createdTo !== null, fn ($query) => $query->where('occurred_at', '<=', $filters->createdTo))
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->paginate($filters->perPage, ['*'], 'page', $filters->page);
    }
}
