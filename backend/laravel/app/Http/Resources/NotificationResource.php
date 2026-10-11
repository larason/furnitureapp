<?php

namespace App\Http\Resources;

use App\Models\Notification;
use App\Support\NotificationIdentifier;
use App\Support\UserIdentifier;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @property-read Notification $resource */
final class NotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $notification = $this->resource;

        return [
            'id' => NotificationIdentifier::encodeId((int) $notification->getKey()),
            'recipient_user_id' => UserIdentifier::encodeId((int) $notification->recipient_user_id),
            'type' => $notification->type->value,
            'title' => $notification->title,
            'message' => $notification->message,
            'read_at' => $notification->read_at?->toISOString(),
            'is_read' => $notification->isRead(),
            'target' => $notification->target,
            'source' => $notification->source_type === null ? null : [
                'type' => match ($notification->source_type) {
                    'furniture_request' => 'REQUEST',
                    'enquiry' => 'ENQUIRY',
                    default => $notification->source_type,
                },
                'id' => $notification->source_id,
            ],
            'created_at' => $notification->created_at->toISOString(),
        ];
    }
}
