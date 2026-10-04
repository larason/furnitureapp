<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\PresentsFurnitureRequest;
use App\Models\FurnitureRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Frozen customer/created representation for a Furniture Request (REQ-001).
 *
 * Allow-listed projection only: public `notes` is sourced from the persistence
 * `message` column, and operational/schema-only fields (`message`, `style`,
 * `product_details`, `staff_internal_notes`, `user_id`, `request_reference`,
 * numeric primary key) are never serialized.
 *
 * @property-read FurnitureRequest $resource
 */
class FurnitureRequestResource extends JsonResource
{
    use PresentsFurnitureRequest;

    public function toArray(Request $request): array
    {
        return $this->furnitureRequestData($this->resource);
    }
}
