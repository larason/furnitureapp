<?php

namespace App\Http\Resources;

use App\Models\Category;
use App\Support\CategoryIdentifier;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Category */
final class CategoryDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => CategoryIdentifier::encode($this->resource),
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'image' => $this->image_url === null ? null : ['url' => $this->image_url],
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
