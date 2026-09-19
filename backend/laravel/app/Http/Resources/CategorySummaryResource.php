<?php

namespace App\Http\Resources;

use App\Models\Category;
use App\Support\CategoryIdentifier;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Category */
final class CategorySummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => CategoryIdentifier::encode($this->resource),
            'name' => $this->name,
            'slug' => $this->slug,
            'image' => $this->image_url === null ? null : ['url' => $this->image_url],
        ];
    }
}
