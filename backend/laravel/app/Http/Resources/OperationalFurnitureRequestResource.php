<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\PresentsFurnitureRequest;
use App\Models\FurnitureRequest;
use App\Support\UserIdentifier;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @property-read FurnitureRequest $resource */
final class OperationalFurnitureRequestResource extends JsonResource
{
    use PresentsFurnitureRequest;

    public function __construct(mixed $resource, private readonly bool $includeInternalNotes = true)
    {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        $furnitureRequest = $this->resource;

        return $this->furnitureRequestData($furnitureRequest, [
            'staff_internal_notes' => $this->includeInternalNotes ? $furnitureRequest->staff_internal_notes : null,
            'user_id' => UserIdentifier::encodeId($furnitureRequest->user_id),
        ]);
    }
}
