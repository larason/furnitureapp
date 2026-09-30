<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\Api\ApiException;
use App\Http\Requests\CreateFurnitureRequestRequest;
use App\Http\Resources\FurnitureRequestResource;
use App\Models\User;
use App\Services\Requests\CreateFurnitureRequest;
use App\Services\Requests\CreateFurnitureRequestCommand;
use App\Support\ApiErrorCode;
use App\Support\ProductIdentifier;
use Illuminate\Http\JsonResponse;

class RequestController extends V1Controller
{
    public function store(CreateFurnitureRequestRequest $request, CreateFurnitureRequest $creator): JsonResponse
    {
        $furnitureRequest = $creator->create($this->command($request));
        $furnitureRequest->loadMissing('product');

        return (new FurnitureRequestResource($furnitureRequest))
            ->response()
            ->setStatusCode(201)
            ->withHeaders([
                'Cache-Control' => 'private, no-store',
                'Vary' => 'Authorization',
            ]);
    }

    public function meIndex(): JsonResponse
    {
        return $this->notImplemented();
    }

    public function meShow(): JsonResponse
    {
        return $this->notImplemented();
    }

    public function index(): JsonResponse
    {
        return $this->notImplemented();
    }

    public function show(): JsonResponse
    {
        return $this->notImplemented();
    }

    public function update(): JsonResponse
    {
        return $this->notImplemented();
    }

    public function storeAttachment(): JsonResponse
    {
        return $this->notImplemented();
    }

    private function command(CreateFurnitureRequestRequest $request): CreateFurnitureRequestCommand
    {
        $validated = $request->validated();
        $user = $request->user();
        $dimensions = $validated['dimensions'] ?? null;

        return new CreateFurnitureRequestCommand(
            actor: $user instanceof User ? $user : null,
            productId: $this->productId($validated['product_id'] ?? null),
            quantity: isset($validated['quantity']) ? (int) $validated['quantity'] : null,
            name: (string) $validated['name'],
            phone: isset($validated['phone']) ? (string) $validated['phone'] : null,
            email: isset($validated['email']) ? (string) $validated['email'] : null,
            dimensions: is_array($dimensions) && $dimensions !== [] ? $dimensions : null,
            material: isset($validated['material']) ? (string) $validated['material'] : null,
            color: isset($validated['color']) ? (string) $validated['color'] : null,
            notes: isset($validated['notes']) ? (string) $validated['notes'] : null,
        );
    }

    private function productId(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }

        $id = ProductIdentifier::decode((string) $value);

        if ($id === null) {
            throw new ApiException(ApiErrorCode::INVALID_VALUE, 'The product reference is invalid.', 422, 'product_id');
        }

        return $id;
    }
}
