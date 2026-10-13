<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\Api\ApiException;
use App\Http\Requests\RegisterDeviceRequest;
use App\Http\Resources\DeviceInstallationResource;
use App\Models\User;
use App\Services\Push\DeviceDeactivationService;
use App\Services\Push\DeviceRegistrationService;
use App\Support\ApiErrorCode;
use App\Support\DeviceInstallationIdentifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Authenticated customer device registration and deactivation.
 *
 * The authenticated principal is the only source of ownership. There is no
 * anonymous path and no staff or administrative path.
 */
class DeviceInstallationController extends V1Controller
{
    public function store(RegisterDeviceRequest $request, DeviceRegistrationService $registrations): JsonResponse
    {
        $customer = $this->authenticatedCustomer($request);
        $registration = $registrations->register($customer, $request->validated());
        $status = $registration->created ? Response::HTTP_CREATED : Response::HTTP_OK;

        return (new DeviceInstallationResource($registration->device))
            ->response()
            ->setStatusCode($status)
            ->withHeaders($this->privateHeaders());
    }

    public function destroy(Request $request, string $device, DeviceDeactivationService $deactivations): Response
    {
        $customer = $this->authenticatedCustomer($request);
        $id = DeviceInstallationIdentifier::decode($device);

        // An unknown identifier and a device owned by another customer are
        // reported identically so the route cannot enumerate other devices.
        $deactivated = $id === null
            ? null
            : $deactivations->deactivate($customer, $id);

        if ($deactivated === null) {
            throw new ApiException(ApiErrorCode::RESOURCE_NOT_FOUND, 'The requested device was not found.', 404);
        }

        return response()->noContent()->withHeaders($this->privateHeaders());
    }

    private function authenticatedCustomer(Request $request): User
    {
        $user = $request->user();
        if (! $user instanceof User) {
            throw new ApiException(ApiErrorCode::AUTHENTICATION_REQUIRED, 'Authentication is required.', 401);
        }

        return $user;
    }

    /** @return array<string, string> */
    private function privateHeaders(): array
    {
        return [
            'Cache-Control' => 'private, no-store',
            'Vary' => 'Authorization',
        ];
    }
}
