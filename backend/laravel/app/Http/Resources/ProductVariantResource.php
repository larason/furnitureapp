<?php

namespace App\Http\Resources;

use App\Support\CatalogAvailability;
use App\Support\ProductIdentifier;
use App\Support\VariantIdentifier;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class ProductVariantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $availability = CatalogAvailability::variant($this->resource);

        return [
            'id' => VariantIdentifier::encode($this->resource),
            'product_id' => ProductIdentifier::encode($this->product),
            'sku' => $this->sku,
            'name' => $this->variant_name,
            'price' => [
                'amount' => (int) $this->price_amount,
                'currency' => $this->price_currency,
            ],
            'availability' => $availability['availability'],
            'stock_indicator' => $availability['stock_indicator'],
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
