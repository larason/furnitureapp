<?php

namespace App\Http\Resources;

use App\Models\FurnitureRequest;
use App\Support\FurnitureRequestIdentifier;
use App\Support\ProductIdentifier;
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
    public function toArray(Request $request): array
    {
        $furnitureRequest = $this->resource;

        return [
            'id' => FurnitureRequestIdentifier::encode($furnitureRequest),
            'product_id' => $this->productIdentifier($furnitureRequest),
            'product' => $this->productSummary($furnitureRequest),
            'quantity' => $furnitureRequest->quantity,
            'name' => $furnitureRequest->name,
            'phone' => $furnitureRequest->phone,
            'email' => $furnitureRequest->email,
            'dimensions' => $furnitureRequest->dimensions,
            'material' => $furnitureRequest->material,
            'color' => $furnitureRequest->color,
            'notes' => $furnitureRequest->message,
            'request_status' => $furnitureRequest->request_status->value,
            'attachments' => [],
            'created_at' => $furnitureRequest->created_at->toISOString(),
            'updated_at' => $furnitureRequest->updated_at->toISOString(),
        ];
    }

    private function productIdentifier(FurnitureRequest $furnitureRequest): ?string
    {
        return $furnitureRequest->product_id === null
            ? null
            : ProductIdentifier::encodeId((int) $furnitureRequest->product_id);
    }

    /** @return array{id: string, name: string, slug: string}|null */
    private function productSummary(FurnitureRequest $furnitureRequest): ?array
    {
        if ($furnitureRequest->product_id === null) {
            return null;
        }

        $product = $furnitureRequest->product;

        if ($product === null) {
            return null;
        }

        return [
            'id' => ProductIdentifier::encode($product),
            'name' => $product->name,
            'slug' => $product->slug,
        ];
    }
}
