<?php

namespace App\Http\Resources;

use App\Support\CategoryIdentifier;
use App\Support\ProductIdentifier;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class ProductSummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $available = (bool) $this->summary_has_available_stock;

        return [
            'id' => ProductIdentifier::encode($this->resource),
            'name' => $this->name,
            'slug' => $this->slug,
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
            'availability' => $available ? 'available' : 'unavailable',
        ];
    }
}
