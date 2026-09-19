<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class ProductVariantSummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $available = $this->stocks->sum(fn ($stock) => $stock->quantity - $stock->reserved_quantity) > 0;

        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'name' => $this->variant_name,
            'price' => [
                'amount' => (int) $this->price_amount,
                'currency' => $this->price_currency,
            ],
            'availability' => $available ? 'available' : 'unavailable',
        ];
    }
}
