<?php

namespace App\Services\Requests;

/**
 * Normalized, schema-validated REQ-001 input.
 *
 * Values are trimmed/normalized and already carry public vocabulary
 * (`notes`, not `message`). Product business eligibility is intentionally not
 * decided here; Phase 10.4 owns it.
 */
final readonly class FurnitureRequestInput
{
    /**
     * @param  array{length?: int|float, width?: int|float, height?: int|float, unit: string}|null  $dimensions
     */
    public function __construct(
        public ?string $productId,
        public ?int $quantity,
        public string $name,
        public ?string $phone,
        public ?string $email,
        public ?array $dimensions,
        public ?string $material,
        public ?string $color,
        public ?string $notes,
    ) {}
}
