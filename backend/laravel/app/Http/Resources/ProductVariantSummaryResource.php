<?php

namespace App\Http\Resources;

use App\Models\ProductVariant;
use App\Support\CatalogAvailability;
use App\Support\VariantIdentifier;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read ProductVariant $resource
 * @property-read string $sku
 * @property-read string $variant_name
 * @property-read int $price_amount
 * @property-read string $price_currency
 */
final class ProductVariantSummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $availability = CatalogAvailability::variant($this->resource);

        return [
            'id' => VariantIdentifier::encode($this->resource),
            'sku' => $this->sku,
            'name' => $this->variant_name,
            'price' => [
                'amount' => (int) $this->price_amount,
                'currency' => $this->price_currency,
            ],
            'availability' => $availability['availability'],
            'stock_indicator' => $availability['stock_indicator'],
        ];
    }
}
