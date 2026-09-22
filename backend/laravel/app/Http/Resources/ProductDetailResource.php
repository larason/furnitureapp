<?php

namespace App\Http\Resources;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Support\CategoryIdentifier;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * @property-read Product $resource
 * @property-read string|null $description
 * @property-read Category $category
 * @property-read Collection<int, ProductImage> $images
 * @property-read Collection<int, ProductVariant> $variants
 * @property-read Carbon|null $created_at
 * @property-read Carbon|null $updated_at
 */
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
