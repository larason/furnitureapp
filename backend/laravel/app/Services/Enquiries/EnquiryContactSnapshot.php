<?php

namespace App\Services\Enquiries;

/**
 * Historical, self-contained contact snapshot for an Enquiry.
 */
final readonly class EnquiryContactSnapshot
{
    public function __construct(
        public string $name,
        public ?string $phone,
        public ?string $email,
    ) {}
}
