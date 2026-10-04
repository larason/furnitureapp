<?php

namespace App\Http\Resources;

use App\Models\Attachment;
use App\Support\AttachmentIdentifier;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Customer-safe attachment metadata. Storage keys, disks, digests, and numeric
 * ids are never serialized; `url` is null in V1 because private delivery is
 * not implemented.
 *
 * @property-read Attachment $resource
 */
class AttachmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $attachment = $this->resource;

        return [
            'id' => AttachmentIdentifier::encode($attachment),
            'filename' => $attachment->filename,
            'content_type' => $attachment->content_type,
            'size' => $attachment->size,
            'url' => null,
        ];
    }
}
