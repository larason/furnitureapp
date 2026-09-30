<?php

namespace App\Services\Requests;

use App\Models\User;

/**
 * Immutable, trusted creation command for a Made-to-Order Furniture Request.
 *
 * Carries only public/intake fields plus the server-resolved actor. It never
 * carries client authority over ownership, reference, status, staff notes, or
 * commerce fields; those are derived inside the creation service.
 */
final readonly class CreateFurnitureRequestCommand
{
    /**
     * @param  array{length: int|float, width: int|float, height: int|float, unit: string}|null  $dimensions
     */
    public function __construct(
        public ?User $actor,
        public ?int $productId,
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
