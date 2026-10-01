<?php

namespace App\Http\Resources;

use App\Models\Enquiry;
use App\Models\Order;
use App\Models\Product;
use App\Support\EnquiryIdentifier;
use App\Support\OrderIdentifier;
use App\Support\ProductIdentifier;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Frozen customer/created representation for a General Enquiry (ENQ-001).
 *
 * Allow-listed projection only: `staff_internal_notes`, `user_id`, internal
 * foreign keys, and Order commerce details (totals/payment/delivery) are never
 * serialized.
 *
 * @property-read Enquiry $resource
 */
class EnquiryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $enquiry = $this->resource;

        return [
            'id' => EnquiryIdentifier::encode($enquiry),
            'name' => $enquiry->name,
            'email' => $enquiry->email,
            'phone' => $enquiry->phone,
            'subject' => $enquiry->subject,
            'message' => $enquiry->message,
            'category' => $enquiry->category?->value,
            'product_id' => $this->productIdentifier($enquiry),
            'product' => $this->productSummary($enquiry),
            'order_id' => $this->orderIdentifier($enquiry),
            'order' => $this->orderSummary($enquiry),
            'enquiry_status' => $enquiry->enquiry_status->value,
            'attachments' => [],
            'created_at' => $enquiry->created_at->toISOString(),
            'updated_at' => $enquiry->updated_at->toISOString(),
        ];
    }

    private function productIdentifier(Enquiry $enquiry): ?string
    {
        return $enquiry->product_id === null
            ? null
            : ProductIdentifier::encodeId((int) $enquiry->product_id);
    }

    /** @return array{id: string, name: string, slug: string}|null */
    private function productSummary(Enquiry $enquiry): ?array
    {
        $product = $enquiry->product_id === null ? null : $enquiry->product;

        return $product instanceof Product
            ? [
                'id' => ProductIdentifier::encode($product),
                'name' => $product->name,
                'slug' => $product->slug,
            ]
            : null;
    }

    private function orderIdentifier(Enquiry $enquiry): ?string
    {
        $order = $enquiry->order_id === null ? null : $enquiry->order;

        return $order instanceof Order ? OrderIdentifier::encode($order) : null;
    }

    /** @return array{id: string, order_reference: string, status: string|null}|null */
    private function orderSummary(Enquiry $enquiry): ?array
    {
        $order = $enquiry->order_id === null ? null : $enquiry->order;

        return $order instanceof Order
            ? [
                'id' => OrderIdentifier::encode($order),
                'order_reference' => $order->order_reference,
                'status' => $order->status?->value,
            ]
            : null;
    }
}
