<?php

namespace App\Services;

use App\Exceptions\Api\ApiException;
use App\Http\Resources\OrderSummaryResource;
use App\Models\Order;
use App\Models\User;
use App\Support\ApiErrorCode;
use App\Support\AuditAction;
use App\Support\AuditResourceType;
use App\Support\DeliveryFeeStatus;
use App\Support\FulfillmentType;
use App\Support\OrderIdentifier;
use App\Support\OrderStatus;
use Illuminate\Support\Facades\DB;

final class DeliveryFeeFinalizer
{
    public const ACTION = 'ORD-014';

    public function __construct(
        private readonly IdempotencyService $idempotency,
        private readonly AuditRecorder $audit,
    ) {}

    /** @return array<string, mixed> */
    public function finalize(
        string $orderIdentifier,
        int $amount,
        User $actor,
        string $idempotencyKey,
        ?string $requestId,
    ): array {
        $orderId = OrderIdentifier::decode($orderIdentifier);
        if ($orderId === null) {
            throw $this->orderNotFound();
        }

        $intent = [
            'order' => $orderIdentifier,
            'delivery_fee' => ['amount' => $amount, 'currency' => Order::CURRENCY_TZS],
        ];

        return $this->idempotency->execute(
            $actor,
            self::ACTION,
            $idempotencyKey,
            $intent,
            fn (): array => $this->apply($orderId, $amount, $actor, $requestId),
        )->body;
    }

    /** @return array<string, mixed> */
    private function apply(int $orderId, int $amount, User $actor, ?string $requestId): array
    {
        return DB::transaction(function () use ($orderId, $amount, $actor, $requestId): array {
            $order = Order::query()->lockForUpdate()->find($orderId);
            if ($order === null) {
                throw $this->orderNotFound();
            }

            $this->assertFinalizable($order);
            $previous = $this->snapshot($order);

            $order->delivery_fee_amount = $amount;
            $order->delivery_fee_status = DeliveryFeeStatus::FINALIZED;
            $order->total_amount = $order->subtotal_amount + $amount;
            $order->save();

            $this->audit->record(
                $actor,
                AuditAction::DELIVERY_FEE_FINALIZED,
                AuditResourceType::ORDER,
                OrderIdentifier::encode($order),
                $previous,
                $this->snapshot($order),
                $requestId,
            );

            return (new OrderSummaryResource($order))->resolve();
        });
    }

    private function assertFinalizable(Order $order): void
    {
        if ($order->fulfillment_type === FulfillmentType::PICKUP) {
            throw new ApiException(ApiErrorCode::BUSINESS_RULE_VIOLATION, 'A delivery fee cannot be set for a pickup order.', 422, 'delivery_fee');
        }

        if ($order->status !== OrderStatus::PENDING_PAYMENT
            || $order->delivery_fee_status !== DeliveryFeeStatus::PENDING) {
            throw new ApiException(ApiErrorCode::INVALID_ORDER_TRANSITION, 'The order is not eligible for delivery-fee finalization.', 409);
        }
    }

    /** @return array<string, mixed> */
    private function snapshot(Order $order): array
    {
        return [
            'delivery_fee_status' => $order->delivery_fee_status->value,
            'delivery_fee_amount' => $order->delivery_fee_amount,
            'total_amount' => $order->total_amount,
        ];
    }

    private function orderNotFound(): ApiException
    {
        return new ApiException(ApiErrorCode::ORDER_NOT_FOUND, 'The requested order was not found.', 404);
    }
}
