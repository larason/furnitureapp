<?php

namespace App\Http\Resources;

use App\Models\ProductStock;
use App\Support\InventoryIdentifier;
use App\Support\ProductIdentifier;
use App\Support\VariantIdentifier;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read ProductStock $resource
 */
final class InventoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $stock = $this->resource;
        $variant = $stock->productVariant;

        return [
            'id' => InventoryIdentifier::encode($stock),
            'product_id' => ProductIdentifier::encodeId($variant->product_id),
            'variant_id' => VariantIdentifier::encode($variant),
            'warehouse_location' => $stock->warehouse_location,
            'quantity' => (int) $stock->quantity,
            'reserved_quantity' => (int) $stock->reserved_quantity,
            'available_quantity' => $stock->available_quantity,
            'updated_at' => $stock->updated_at?->toISOString(),
        ];
    }
}
