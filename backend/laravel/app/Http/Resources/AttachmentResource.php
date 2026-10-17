<?php

namespace App\Http\Resources;

use App\Models\Attachment;
use App\Support\AttachmentIdentifier;
use App\Support\FurnitureRequestIdentifier;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;

/**
 * Customer-safe attachment metadata. Storage keys, disks, digests, and numeric
 * ids are never serialized. `url` is a short-lived signed delivery link whose
 * signature is the only credential; it is null when no authorized delivery
 * route exists for the attachment's parent (enquiry delivery arrives with
 * ENQ-008).
 *
 * @property-read Attachment $resource
 */
class AttachmentResource extends JsonResource
{
    private const REQUEST_DOWNLOAD_ROUTE = 'api.requests.attachments.download';

    public function toArray(Request $request): array
    {
        $attachment = $this->resource;

        return [
            'id' => AttachmentIdentifier::encode($attachment),
            'filename' => $attachment->filename,
            'content_type' => $attachment->content_type,
            'size' => $attachment->size,
            'url' => $this->deliveryUrl($attachment),
        ];
    }

    private function deliveryUrl(Attachment $attachment): ?string
    {
        if ($attachment->furniture_request_id === null) {
            return null;
        }

        if (! Route::has(self::REQUEST_DOWNLOAD_ROUTE)) {
            return null;
        }

        return URL::temporarySignedRoute(
            self::REQUEST_DOWNLOAD_ROUTE,
            now()->addMinutes((int) config('attachments.download_url_ttl_minutes')),
            [
                'request' => FurnitureRequestIdentifier::encodeId((int) $attachment->furniture_request_id),
                'attachment' => AttachmentIdentifier::encode($attachment),
            ],
        );
    }
}
