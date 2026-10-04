<?php

namespace App\Services\Enquiries;

use App\Support\EnquiryCategory;
use App\Support\EnquiryStatus;
use Carbon\CarbonImmutable;

final readonly class OperationalEnquiryQuery
{
    public function __construct(
        public ?string $search,
        public ?EnquiryStatus $status,
        public ?EnquiryCategory $category,
        public ?int $productId,
        public ?string $orderPublicId,
        public ?CarbonImmutable $createdFrom,
        public ?CarbonImmutable $createdTo,
        public int $page,
        public int $perPage,
    ) {}
}
