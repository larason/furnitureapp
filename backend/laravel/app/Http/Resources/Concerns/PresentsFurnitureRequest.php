<?php

namespace App\Http\Resources\Concerns;

use App\Http\Resources\AttachmentResource;
use App\Models\FurnitureRequest;
use App\Support\FurnitureRequestIdentifier;
use App\Support\ProductIdentifier;
use App\Support\RequestField;

/**
 * Shared frozen projection helpers for Furniture Request representations.
 */
trait PresentsFurnitureRequest
{
    /** @param array<string, mixed> $additionalFields */
    private function furnitureRequestData(FurnitureRequest $furnitureRequest, array $additionalFields = []): array
    {
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
            ...$additionalFields,
            'attachments' => AttachmentResource::collection($furnitureRequest->attachments)->resolve(),
            'created_at' => $furnitureRequest->created_at->toISOString(),
            'updated_at' => $furnitureRequest->updated_at->toISOString(),
        ];
    }

    /**
     * Expands stored (possibly partial) dimensions to the canonical frozen
     * customer shape, with absent measurements returned as `null`.
     *
     * @return array{length: int|float|null, width: int|float|null, height: int|float|null, unit: string}|null
     */
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
