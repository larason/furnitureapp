<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\Api\ApiException;
use App\Http\Requests\SetDeliveryFeeRequest;
use App\Models\User;
use App\Services\DeliveryFeeFinalizer;
use App\Support\ApiErrorCode;
use App\Support\IdempotencyKeyHeader;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends V1Controller
{
    public function index(): JsonResponse
    {
        return $this->notImplemented();
    }

    public function show(): JsonResponse
    {
        return $this->notImplemented();
    }

    public function accept(): JsonResponse
    {
        return $this->notImplemented();
    }

    public function process(): JsonResponse
    {
        return $this->notImplemented();
    }

    public function readyForPickup(): JsonResponse
    {
        return $this->notImplemented();
    }

    public function ship(): JsonResponse
    {
        return $this->notImplemented();
    }

    public function deliver(): JsonResponse
    {
        return $this->notImplemented();
    }

    public function complete(): JsonResponse
    {
        return $this->notImplemented();
    }

    public function deliveryFee(Request $request, SetDeliveryFeeRequest $validated, string $order, DeliveryFeeFinalizer $finalizer): JsonResponse
    {
        $actor = $request->user();
        if (! $actor instanceof User) {
            throw new ApiException(ApiErrorCode::AUTHENTICATION_REQUIRED, 'Authentication is required.', 401);
        }

        $body = $finalizer->finalize(
            $order,
            (int) $validated->validated('delivery_fee.amount'),
            $actor,
            IdempotencyKeyHeader::require($request),
            $request->attributes->get('request_id'),
        );

        return response()->json(['data' => $body])->withHeaders([
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function tracking(): JsonResponse
    {
        return $this->notImplemented();
    }
}
