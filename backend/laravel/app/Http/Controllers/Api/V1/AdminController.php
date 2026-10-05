<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\Api\ApiException;
use App\Http\Requests\ListAuditLogsRequest;
use App\Http\Requests\ListCustomersRequest;
use App\Http\Resources\AdministrativeCustomerResource;
use App\Http\Resources\AuditLogResource;
use App\Models\User;
use App\Services\AuditLogs\ListAuditLogs;
use App\Support\ApiErrorCode;
use App\Support\RoleName;
use App\Support\UserIdentifier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

class AdminController extends V1Controller
{
    public function staffIndex(): JsonResponse
    {
        return $this->notImplemented();
    }

    public function staffShow(): JsonResponse
    {
        return $this->notImplemented();
    }

    public function staffStore(): JsonResponse
    {
        return $this->notImplemented();
    }

    public function staffApprove(): JsonResponse
    {
        return $this->notImplemented();
    }

    public function staffSuspend(): JsonResponse
    {
        return $this->notImplemented();
    }

    public function staffReactivate(): JsonResponse
    {
        return $this->notImplemented();
    }

    public function userIndex(ListCustomersRequest $request): JsonResponse
    {
        $pagination = $request->pagination();
        $paginator = $this->customers()
            ->orderByDesc('users.created_at')
            ->orderBy('users.id')
            ->paginate($pagination['per_page'], ['*'], 'page', $pagination['page']);

        return response()->json([
            'data' => AdministrativeCustomerResource::collection($paginator->getCollection())->resolve(),
            'meta' => ['pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => max(1, $paginator->lastPage()),
                'has_next' => $paginator->hasMorePages(),
                'has_previous' => $paginator->currentPage() > 1,
            ]],
        ])->withHeaders($this->privateHeaders());
    }

    public function userShow(string $user): JsonResponse
    {
        $id = UserIdentifier::decode($user);
        $customer = $id === null ? null : $this->customers()->whereKey($id)->first();

        if ($customer === null) {
            throw new ApiException(ApiErrorCode::RESOURCE_NOT_FOUND, 'The requested customer was not found.', 404);
        }

        return (new AdministrativeCustomerResource($customer))->response()->withHeaders($this->privateHeaders());
    }

    public function auditLogIndex(ListAuditLogsRequest $request, ListAuditLogs $auditLogs): JsonResponse
    {
        $paginator = $auditLogs->paginate($request->normalizedQuery());

        return response()->json([
            'data' => AuditLogResource::collection($paginator->getCollection())->resolve(),
            'meta' => ['pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => max(1, $paginator->lastPage()),
                'has_next' => $paginator->hasMorePages(),
                'has_previous' => $paginator->currentPage() > 1,
            ]],
        ])->withHeaders($this->privateHeaders());
    }

    private function customers(): Builder
    {
        return User::query()
            ->whereHas('roles', fn (Builder $query): Builder => $query->where('name', RoleName::CUSTOMER->value))
            ->whereDoesntHave('roles', fn (Builder $query): Builder => $query->where('name', '!=', RoleName::CUSTOMER->value));
    }

    private function privateHeaders(): array
    {
        return [
            'Cache-Control' => 'private, no-store',
            'Vary' => 'Authorization',
        ];
    }
}
