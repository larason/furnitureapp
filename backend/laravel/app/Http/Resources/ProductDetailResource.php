<?php

namespace App\Http\Resources;

use App\Support\CategoryIdentifier;
use Illuminate\Http\Request;

final class ProductDetailResource extends ProductSummaryResource
{
    public function toArray(Request $request): array
    {
        $summary = parent::toArray($request);

        return array_merge($summary, [
            'description' => $this->description,
            'category' => [
                'id' => CategoryIdentifier::encode($this->category),
                'slug' => $this->category->slug,
                'name' => $this->category->name,
                'description' => $this->category->description,
            ],
            'images' => ProductImageResource::collection($this->images)->resolve(),
            'variants' => ProductVariantSummaryResource::collection($this->variants)->resolve(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ]);
    }
}
