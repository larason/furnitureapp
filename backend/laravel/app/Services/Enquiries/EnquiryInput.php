<?php

namespace App\Services\Enquiries;

use App\Support\EnquiryCategory;

/**
 * Normalized, schema-validated ENQ-001 input (public fields only).
 */
final readonly class EnquiryInput
{
    public function __construct(
        public ?string $name,
        public ?string $phone,
        public ?string $email,
        public string $subject,
        public string $message,
        public ?EnquiryCategory $category,
        public ?string $productId,
        public ?string $orderId,
    ) {}
}
