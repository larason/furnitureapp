<?php

namespace App\Http\Resources;

use App\Models\Order;
use App\Support\OrderIdentifier;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read Order $resource
 */
final class OrderSummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $order = $this->resource;

        return [
            'id' => OrderIdentifier::encode($order),
            'order_reference' => $order->order_reference,
            'status' => $order->status?->value,
            'fulfillment_type' => $order->fulfillment_type->value,
            'delivery_fee_status' => $order->delivery_fee_status->value,
            'subtotal' => $this->money($order->subtotal_amount),
            'delivery_fee' => $this->money($order->delivery_fee_amount),
            'total' => $this->money($order->total_amount),
            'currency' => $order->currency,
            'created_at' => $order->created_at?->toISOString(),
            'updated_at' => $order->updated_at?->toISOString(),
        ];
    }

    /** @return array{amount: int, currency: string}|null */
    private function money(?int $amount): ?array
    {
        if ($amount === null) {
            return null;
        }

        return ['amount' => $amount, 'currency' => $this->resource->currency];
    }
}
