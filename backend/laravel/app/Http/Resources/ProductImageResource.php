<?php

namespace App\Http\Resources;

use App\Models\ProductImage;
use App\Services\ProductImages\ProductImageStorage;
use App\Support\ProductImageIdentifier;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read ProductImage $resource
 * @property-read int $id
 * @property-read string $file_path
 * @property-read string|null $alt_text
 * @property-read int $sort_order
 * @property-read bool $is_primary
 */
final class ProductImageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => ProductImageIdentifier::encode($this->resource),
            'url' => app(ProductImageStorage::class)->publicUrl($this->file_path),
            'alt_text' => $this->alt_text,
            'sort_order' => $this->sort_order,
            'is_primary' => $this->is_primary,
        ];
    }
}
