<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\Api\ApiException;
use App\Http\Requests\CheckoutRequest;
use App\Models\User;
use App\Services\Checkout\CheckoutCommand;
use App\Services\Checkout\CheckoutTransaction;
use App\Support\ApiErrorCode;
use App\Support\FulfillmentType;
use Illuminate\Http\JsonResponse;

class CheckoutController extends V1Controller
{
    public function store(CheckoutRequest $request, CheckoutTransaction $transaction): JsonResponse
    {
        $customer = $request->user();

        if (! $customer instanceof User) {
            throw new ApiException(ApiErrorCode::AUTHENTICATION_REQUIRED, 'Authentication is required.', 401);
        }

        $command = new CheckoutCommand(
            $customer,
            FulfillmentType::from((string) $request->validated('fulfillment_type')),
            $request->normalizedDeliveryAddress(),
            $request->idempotencyKey(),
        );

        $outcome = $transaction->execute($command);

        return response()->json(['data' => $outcome->body], $outcome->status)
            ->withHeaders([
                'Cache-Control' => 'private, no-store',
                'Vary' => 'Authorization',
            ]);
    }
}
