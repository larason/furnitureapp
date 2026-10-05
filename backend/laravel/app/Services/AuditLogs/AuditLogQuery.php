<?php

namespace App\Services\AuditLogs;

use App\Support\AuditAction;
use App\Support\AuditResourceType;
use Carbon\CarbonImmutable;

final readonly class AuditLogQuery
{
    public function __construct(
        public ?int $actorId,
        public ?AuditAction $action,
        public ?AuditResourceType $resourceType,
        public ?string $resourceId,
        public ?CarbonImmutable $createdFrom,
        public ?CarbonImmutable $createdTo,
        public int $page,
        public int $perPage,
    ) {}
}
