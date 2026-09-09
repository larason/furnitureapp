<?php

namespace App\Services;

use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;

class ProductVariantService
{
    public function setAsDefault(ProductVariant $variant): ProductVariant
    {
        return DB::transaction(function () use ($variant) {
            ProductVariant::where('product_id', $variant->product_id)
                ->where('is_default', true)
                ->whereKeyNot($variant->getKey())
                ->lockForUpdate()
                ->update(['is_default' => false]);

            $variant->is_default = true;
            $variant->save();

            return $variant->fresh();
        });
    }
}
