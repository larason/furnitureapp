<?php

namespace App\Http\Resources;

use App\Models\Cart;
use App\Models\Order;
use App\Support\CartIdentifier;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read Cart $resource
 */
final class CartResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $cart = $this->resource;
        $items = CartItemResource::collection($cart->items)->resolve();

        return [
            'id' => CartIdentifier::encode($cart),
            'items_count' => $cart->items->count(),
            'items' => $items,
            'subtotal' => [
                'amount' => array_sum(array_map(fn (array $item): int => $item['line_total']['amount'] ?? 0, $items)),
                'currency' => Order::CURRENCY_TZS,
            ],
            'updated_at' => $cart->updated_at?->toISOString(),
        ];
    }
}
