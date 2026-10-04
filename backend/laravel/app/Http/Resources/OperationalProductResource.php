<?php

namespace App\Http\Resources;

use App\Models\Product;
use App\Models\ProductStock;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class OperationalProductResource extends JsonResource
{
    public function __construct(mixed $resource, private readonly bool $includeInventory)
    {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        $product = $this->product();

        return array_merge((new ProductDetailResource($product))->resolve($request), [
            'is_active' => $product->is_active,
            'is_published' => $product->is_published,
            'inventory' => $this->includeInventory ? $this->inventory() : null,
        ]);
    }

    /** @return array{quantity: int, reserved_quantity: int, available_quantity: int} */
    private function inventory(): array
    {
        $stocks = $this->product()->variants->flatMap(fn ($variant) => $variant->stocks);

        return [
            'quantity' => $stocks->sum(fn (ProductStock $stock): int => (int) $stock->quantity),
            'reserved_quantity' => $stocks->sum(fn (ProductStock $stock): int => (int) $stock->reserved_quantity),
            'available_quantity' => $stocks->sum(fn (ProductStock $stock): int => $stock->available_quantity),
        ];
    }

    private function product(): Product
    {
        if (! $this->resource instanceof Product) {
            throw new \LogicException('OperationalProductResource requires a Product.');
        }

        return $this->resource;
    }
}
