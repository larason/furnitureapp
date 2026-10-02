<?php

namespace App\Services\Enquiries;

use App\Models\User;
use App\Services\Attachments\ValidatedAttachment;
use App\Support\EnquiryCategory;

/**
 * Immutable, trusted ENQ-001 creation command. `productId`/`orderId` are the
 * validated opaque public references, resolved by the creation service.
 */
final readonly class CreateEnquiryCommand
{
    public function __construct(
        public ?User $actor,
        public ?string $name,
        public ?string $phone,
        public ?string $email,
        public string $subject,
        public string $message,
        public ?EnquiryCategory $category,
        public ?string $productId,
        public ?string $orderId,
        public ?ValidatedAttachment $attachment = null,
    ) {}
}
