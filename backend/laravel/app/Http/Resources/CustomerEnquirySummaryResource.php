<?php

namespace App\Http\Resources;

use App\Models\Enquiry;
use App\Support\EnquiryIdentifier;
use App\Support\ProductIdentifier;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @property-read Enquiry $resource */
final class CustomerEnquirySummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $enquiry = $this->resource;

        return [
            'id' => EnquiryIdentifier::encode($enquiry),
            'subject' => $enquiry->subject,
            'category' => $enquiry->category?->value,
            'product_id' => $enquiry->product_id === null
                ? null
                : ProductIdentifier::encodeId((int) $enquiry->product_id),
            'enquiry_status' => $enquiry->enquiry_status->value,
            'created_at' => $enquiry->created_at->toISOString(),
        ];
    }
}
