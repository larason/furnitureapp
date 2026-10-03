<?php

namespace App\Http\Resources;

use App\Models\FurnitureRequest;
use App\Support\FurnitureRequestIdentifier;
use App\Support\ProductIdentifier;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @property-read FurnitureRequest $resource */
final class CustomerFurnitureRequestSummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $furnitureRequest = $this->resource;

        return [
            'id' => FurnitureRequestIdentifier::encode($furnitureRequest),
            'product_id' => $furnitureRequest->product_id === null
                ? null
                : ProductIdentifier::encodeId((int) $furnitureRequest->product_id),
            'quantity' => $furnitureRequest->quantity,
            'request_status' => $furnitureRequest->request_status->value,
            'created_at' => $furnitureRequest->created_at->toISOString(),
        ];
    }
}
