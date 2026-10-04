<?php

namespace App\Services\Requests;

use App\Support\RequestStatus;
use Carbon\CarbonImmutable;

final readonly class OperationalFurnitureRequestQuery
{
    public function __construct(
        public ?string $search,
        public ?RequestStatus $requestStatus,
        public ?int $productId,
        public ?CarbonImmutable $createdFrom,
        public ?CarbonImmutable $createdTo,
        public int $page,
        public int $perPage,
    ) {}
}
