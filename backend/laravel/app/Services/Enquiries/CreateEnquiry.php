<?php

namespace App\Services\Enquiries;

use App\Models\Attachment;
use App\Models\Enquiry;
use App\Services\Attachments\AttachFileToParent;
use App\Support\EnquiryStatus;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Authoritative ENQ-001 creation service.
 *
 * Derives the historical contact snapshot, resolves the optional public
 * Product and owned Order context, optionally stores the inline private
 * attachment, and persists the Enquiry as `OPEN`. It creates no
 * Order/Payment/inventory/quote effect and never converts to or from a
 * Furniture Request. When an attachment file was written but the creation
 * transaction fails, the file is compensatorily deleted.
 */
final class CreateEnquiry
{
    public function __construct(
        private readonly EnquiryContactSnapshotFactory $contacts,
        private readonly PublicEnquiryProductResolver $products,
        private readonly OwnedEnquiryOrderResolver $orders,
        private readonly AttachFileToParent $attachments,
    ) {}

    public function create(CreateEnquiryCommand $command): Enquiry
    {
        $contact = $this->contacts->build($command->actor, $command->name, $command->phone, $command->email);
        $product = $this->products->resolve($command->productId);
        $order = $command->actor === null ? null : $this->orders->resolve($command->orderId, $command->actor);
        $storedAttachment = null;

        try {
            return DB::transaction(function () use ($command, $contact, $product, $order, &$storedAttachment): Enquiry {
                $enquiry = new Enquiry;
                $enquiry->user_id = $command->actor === null ? null : (int) $command->actor->getKey();
                $enquiry->product_id = $product?->getKey();
                $enquiry->order_id = $order?->getKey();
                $enquiry->name = $contact->name;
                $enquiry->phone = $contact->phone;
                $enquiry->email = $contact->email;
                $enquiry->subject = $command->subject;
                $enquiry->message = $command->message;
                $enquiry->category = $command->category;
                $enquiry->enquiry_status = EnquiryStatus::OPEN;
                $enquiry->save();

                if ($command->attachment !== null) {
                    $storedAttachment = $this->attachments->attachEnquiry($enquiry, $command->attachment);
                }

                return $enquiry;
            });
        } catch (Throwable $exception) {
            if ($storedAttachment instanceof Attachment) {
                $this->attachments->delete($storedAttachment);
            }

            throw $exception;
        }
    }
}
