<?php

namespace App\Http\Resources;

use App\Models\Enquiry;
use App\Models\Order;
use App\Models\Product;
use App\Support\EnquiryIdentifier;
use App\Support\OrderIdentifier;
use App\Support\ProductIdentifier;
use App\Support\UserIdentifier;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @property-read Enquiry $resource */
final class OperationalEnquiryResource extends JsonResource
{
    public function __construct(mixed $resource, private readonly bool $includeInternalNotes = true)
    {
        parent::__construct($resource);
    }

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
            'product_id' => $enquiry->product_id === null ? null : ProductIdentifier::encodeId((int) $enquiry->product_id),
            'product' => $this->productSummary($enquiry),
            'order_id' => $this->orderIdentifier($enquiry),
            'order' => $this->orderSummary($enquiry),
            'enquiry_status' => $enquiry->enquiry_status->value,
            'staff_internal_notes' => $this->includeInternalNotes ? $enquiry->staff_internal_notes : null,
            'user_id' => UserIdentifier::encodeId($enquiry->user_id),
            'attachments' => AttachmentResource::collection($enquiry->attachments)->resolve(),
            'created_at' => $enquiry->created_at->toISOString(),
            'updated_at' => $enquiry->updated_at->toISOString(),
        ];
    }

    private function productSummary(Enquiry $enquiry): ?array
    {
        $product = $enquiry->product;

        if (! $product instanceof Product) {
            return null;
        }

        return [
            'id' => ProductIdentifier::encode($product),
            'name' => $product->name,
            'slug' => $product->slug,
        ];
    }

    private function orderSummary(Enquiry $enquiry): ?array
    {
        $order = $enquiry->order;

        if (! $order instanceof Order) {
            return null;
        }

        return [
            'id' => OrderIdentifier::encode($order),
            'order_reference' => $order->order_reference,
            'status' => $order->status?->value,
        ];
    }

    private function orderIdentifier(Enquiry $enquiry): ?string
    {
        $order = $enquiry->order;

        return $order instanceof Order ? OrderIdentifier::encode($order) : null;
    }
}
