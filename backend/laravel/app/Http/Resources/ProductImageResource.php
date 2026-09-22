<?php

namespace App\Http\Resources;

use App\Models\ProductImage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

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
            'id' => $this->id,
            'url' => Storage::disk(config('filesystems.default'))->url($this->file_path),
            'alt_text' => $this->alt_text,
            'sort_order' => $this->sort_order,
            'is_primary' => $this->is_primary,
        ];
    }
}
