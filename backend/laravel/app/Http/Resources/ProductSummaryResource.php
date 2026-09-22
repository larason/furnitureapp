<?php

namespace App\Http\Resources;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Support\CatalogAvailability;
use App\Support\CategoryIdentifier;
use App\Support\ProductIdentifier;
use App\Support\ProductType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * @property-read Product $resource
 * @property-read string $name
 * @property-read string $slug
 * @property-read ProductType $product_type
 * @property-read int|null $summary_price_amount
 * @property-read string|null $summary_price_currency
 * @property-read Category $category
 * @property-read ProductImage|null $primaryImage
 */
class ProductSummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $availability = CatalogAvailability::product($this->resource);

        return [
            'id' => ProductIdentifier::encode($this->resource),
            'name' => $this->name,
            'slug' => $this->slug,
            'product_type' => $this->product_type->value,
            'price' => $this->summary_price_amount === null ? null : [
                'amount' => (int) $this->summary_price_amount,
                'currency' => $this->summary_price_currency,
            ],
            'category' => [
                'id' => CategoryIdentifier::encode($this->category),
                'slug' => $this->category->slug,
                'name' => $this->category->name,
            ],
            'primary_image' => $this->whenLoaded('primaryImage', fn () => $this->primaryImage === null ? null : [
                'id' => $this->primaryImage->id,
                'url' => Storage::disk(config('filesystems.default'))->url($this->primaryImage->file_path),
                'alt_text' => $this->primaryImage->alt_text,
            ]),
            'availability' => $availability['availability'],
            'stock_indicator' => $availability['stock_indicator'],
        ];
    }
}
