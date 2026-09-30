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
        $input = $request->normalizedInput();
        $actor = $request->user();

        $furnitureRequest = $creator->create(new CreateFurnitureRequestCommand(
            actor: $actor instanceof User ? $actor : null,
            productId: $this->productId($input->productId),
            quantity: $input->quantity,
            name: $input->name,
            phone: $input->phone,
            email: $input->email,
            dimensions: $input->dimensions,
            material: $input->material,
            color: $input->color,
            notes: $input->notes,
        ));

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

    private function productId(?string $value): ?int
    {
        if ($value === null) {
            return null;
        }

        $id = ProductIdentifier::decode($value);

        if ($id === null) {
            throw new ApiException(ApiErrorCode::INVALID_FORMAT, 'The product reference is invalid.', 422, 'product_id');
        }

        return $id;
    }
}
