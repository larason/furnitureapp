<?php

namespace App\Http\Controllers\Api\V1;

use App\Events\NotificationStateChanged;
use App\Exceptions\Api\ApiException;
use App\Http\Requests\ListNotificationsRequest;
use App\Http\Requests\UpdateNotificationRequest;
use App\Http\Resources\NotificationResource;
use App\Models\Notification;
use App\Models\User;
use App\Support\ApiErrorCode;
use App\Support\NotificationIdentifier;
use App\Support\NotificationRealtimeChange;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class NotificationController extends V1Controller
{
    public function meIndex(ListNotificationsRequest $request): JsonResponse
    {
        $user = $this->authenticatedUser($request);
        $filters = $request->filters();
        $query = Notification::query()
            ->where('recipient_user_id', $user->getKey())
            ->orderByDesc('created_at')
            ->orderBy('id');

        if ($filters['unread'] === true) {
            $query->whereNull('read_at');
        }

        if ($filters['unread'] === false) {
            $query->whereNotNull('read_at');
        }

        if ($filters['type'] !== null) {
            $query->where('type', $filters['type']->value);
        }

        $paginator = $query->paginate($filters['per_page'], ['*'], 'page', $filters['page']);
        $lastPage = max(1, $paginator->lastPage());
        $currentPage = $paginator->currentPage();

        return response()->json([
            'data' => NotificationResource::collection($paginator->getCollection())->resolve($request),
            'meta' => [
                'pagination' => [
                    'current_page' => $currentPage,
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'last_page' => $lastPage,
                    'has_next' => $currentPage < $lastPage,
                    'has_previous' => $currentPage > 1,
                ],
                'unread_count' => Notification::query()
                    ->where('recipient_user_id', $user->getKey())
                    ->whereNull('read_at')
                    ->count(),
            ],
        ])->withHeaders($this->privateHeaders());
    }

    public function meUpdate(UpdateNotificationRequest $request, string $notification): JsonResponse
    {
        $user = $this->authenticatedUser($request);
        $id = NotificationIdentifier::decode($notification);
        $model = $id === null
            ? null
            : Notification::query()
                ->whereKey($id)
                ->where('recipient_user_id', $user->getKey())
                ->first();

        if ($model === null) {
            throw new ApiException(ApiErrorCode::RESOURCE_NOT_FOUND, 'The requested notification was not found.', 404);
        }

        $read = $request->read();
        $stateChanged = false;

        if ($read) {
            if ($model->read_at === null) {
                $model->read_at = now();
                $stateChanged = true;
            }
        } elseif ($model->read_at !== null) {
            $model->read_at = null;
            $stateChanged = true;
        }

        $model->save();
        if ($stateChanged) {
            $this->broadcastReadStateChange($model);
        }

        return (new NotificationResource($model))
            ->response()
            ->withHeaders($this->privateHeaders());
    }

    private function authenticatedUser(Request $request): User
    {
        $user = $request->user();
        if (! $user instanceof User) {
            throw new ApiException(ApiErrorCode::AUTHENTICATION_REQUIRED, 'Authentication is required.', 401);
        }

        return $user;
    }

    private function broadcastReadStateChange(Notification $notification): void
    {
        try {
            NotificationStateChanged::dispatch($notification, NotificationRealtimeChange::ReadStateChanged);
        } catch (Throwable $exception) {
            Log::warning('Notification read-state broadcast dispatch failed.', [
                'notification_id' => $notification->getKey(),
                'exception' => $exception::class,
            ]);
        }
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
