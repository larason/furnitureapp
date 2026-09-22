<?php

namespace App\Services;

use App\Exceptions\Api\ApiException;
use App\Http\Resources\InventoryResource;
use App\Models\ProductStock;
use App\Models\User;
use App\Support\ApiErrorCode;
use App\Support\AuditAction;
use App\Support\AuditResourceType;
use App\Support\InventoryAdjustmentReason;
use App\Support\InventoryIdentifier;
use Illuminate\Support\Facades\DB;

/**
 * Controlled physical inventory adjustment (INV-003). Changes quantity only;
 * reservation, location and Variant/Product ownership are immutable here.
 */
final class InventoryAdjustmentService
{
    public const ACTION = 'INV-003';

    /** Unsigned 32-bit maximum of product_stocks.quantity (migration). */
    private const MAX_QUANTITY = 4_294_967_295;

    public function __construct(
        private readonly IdempotencyService $idempotency,
        private readonly AuditRecorder $audit,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function adjust(
        ProductStock $stock,
        int $quantityDelta,
        InventoryAdjustmentReason $reason,
        User $actor,
        string $idempotencyKey,
        ?string $requestId,
    ): array {
        $this->assertDirectionIsValid($reason, $quantityDelta);

        $intent = [
            'inventory' => InventoryIdentifier::encode($stock),
            'quantity_delta' => $quantityDelta,
            'reason' => $reason->value,
        ];

        return $this->idempotency->execute(
            $actor,
            self::ACTION,
            $idempotencyKey,
            $intent,
            fn (): array => $this->apply($stock->getKey(), $quantityDelta, $reason, $actor, $requestId),
        )->body;
    }

    /**
     * @return array<string, mixed>
     */
    private function apply(
        int $stockId,
        int $quantityDelta,
        InventoryAdjustmentReason $reason,
        User $actor,
        ?string $requestId,
    ): array {
        return DB::transaction(function () use ($stockId, $quantityDelta, $reason, $actor, $requestId): array {
            $stock = ProductStock::query()->with('productVariant')->lockForUpdate()->findOrFail($stockId);
            $newQuantity = $this->calculateNewQuantity($stock, $quantityDelta);

            $previous = $this->snapshot($stock, $quantityDelta, $reason);
            $stock->quantity = $newQuantity;
            $stock->save();

            $this->audit->record(
                $actor,
                AuditAction::INVENTORY_ADJUSTED,
                AuditResourceType::INVENTORY,
                InventoryIdentifier::encode($stock),
                $previous,
                $this->snapshot($stock, $quantityDelta, $reason),
                $requestId,
            );

            return (new InventoryResource($stock))->resolve();
        });
    }

    private function assertDirectionIsValid(InventoryAdjustmentReason $reason, int $quantityDelta): void
    {
        $invalid = ($reason->requiresPositiveDelta() && $quantityDelta < 0)
            || ($reason->requiresNegativeDelta() && $quantityDelta > 0);

        if ($invalid) {
            throw new ApiException(ApiErrorCode::INVALID_VALUE, 'The quantity_delta sign is not valid for the selected reason.', 422, 'quantity_delta');
        }
    }

    private function calculateNewQuantity(ProductStock $stock, int $quantityDelta): int
    {
        if ($quantityDelta > 0 && $stock->quantity > self::MAX_QUANTITY - $quantityDelta) {
            throw $this->invalidQuantityDelta();
        }

        $newQuantity = $stock->quantity + $quantityDelta;

        if ($newQuantity < 0 || $newQuantity < $stock->reserved_quantity) {
            throw $this->invalidQuantityDelta();
        }

        return $newQuantity;
    }

    private function invalidQuantityDelta(): ApiException
    {
        return new ApiException(ApiErrorCode::INVALID_VALUE, 'The adjustment would make inventory invalid.', 422, 'quantity_delta');
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(ProductStock $stock, int $quantityDelta, InventoryAdjustmentReason $reason): array
    {
        return [
            'quantity' => $stock->quantity,
            'reserved_quantity' => $stock->reserved_quantity,
            'available_quantity' => $stock->available_quantity,
            'warehouse_location' => $stock->warehouse_location,
            'quantity_delta' => $quantityDelta,
            'reason' => $reason->value,
        ];
    }
}
