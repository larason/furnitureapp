<?php

namespace App\Http\Resources;

use App\Models\FurnitureRequest;
use App\Support\FurnitureRequestIdentifier;
use App\Support\ProductIdentifier;
use App\Support\RequestField;
use App\Support\UserIdentifier;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @property-read FurnitureRequest $resource */
final class OperationalFurnitureRequestResource extends JsonResource
{
    public function __construct($resource, private readonly bool $includeInternalNotes = true)
    {
        parent::__construct($resource);
    }

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
            'dimensions' => $this->dimensions($furnitureRequest),
            'material' => $furnitureRequest->material,
            'color' => $furnitureRequest->color,
            'notes' => $furnitureRequest->message,
            'request_status' => $furnitureRequest->request_status->value,
            'staff_internal_notes' => $this->includeInternalNotes ? $furnitureRequest->staff_internal_notes : null,
            'user_id' => UserIdentifier::encodeId($furnitureRequest->user_id),
            'attachments' => AttachmentResource::collection($furnitureRequest->attachments)->resolve(),
            'created_at' => $furnitureRequest->created_at->toISOString(),
            'updated_at' => $furnitureRequest->updated_at->toISOString(),
        ];
    }

    /** @return array{length: int|float|null, width: int|float|null, height: int|float|null, unit: string}|null */
    private function dimensions(FurnitureRequest $furnitureRequest): ?array
    {
        $dimensions = $furnitureRequest->dimensions;

        if ($dimensions === null) {
            return null;
        }

        return [
            RequestField::LENGTH => $dimensions[RequestField::LENGTH] ?? null,
            RequestField::WIDTH => $dimensions[RequestField::WIDTH] ?? null,
            RequestField::HEIGHT => $dimensions[RequestField::HEIGHT] ?? null,
            RequestField::UNIT => (string) ($dimensions[RequestField::UNIT] ?? RequestField::UNIT_CM),
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
