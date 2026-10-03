<?php

namespace App\Services\Requests;

use App\Support\RequestStatus;

final readonly class FurnitureRequestOperationalUpdate
{
    public function __construct(
        public bool $statusProvided,
        public ?RequestStatus $status,
        public bool $notesProvided,
        public ?string $notes,
    ) {}
}
