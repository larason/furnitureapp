<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Hashed, single-use capability for a scoped attachment upload.
 *
 * @property int $id
 * @property string $parent_type
 * @property int $parent_id
 * @property string $token_hash
 * @property Carbon $expires_at
 * @property Carbon|null $used_at
 */
#[Fillable(['parent_type', 'parent_id', 'token_hash', 'expires_at', 'used_at'])]
final class AttachmentUploadCapability extends Model
{
    protected $table = 'attachment_upload_capabilities';

    protected function casts(): array
    {
        return [
            'parent_id' => 'integer',
            'expires_at' => 'immutable_datetime',
            'used_at' => 'immutable_datetime',
        ];
    }
}
