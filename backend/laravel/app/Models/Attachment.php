<?php

namespace App\Models;

use Database\Factories\AttachmentFactory;
use DomainException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Private attachment metadata for a Furniture Request or General Enquiry.
 * V1 allows at most one attachment per parent (enforced by unique FKs); the
 * parent is exactly one of the two nullable foreign keys.
 *
 * @property int $id
 * @property int|null $furniture_request_id
 * @property int|null $enquiry_id
 * @property string $storage_disk
 * @property string $storage_key
 * @property string $filename
 * @property string $content_type
 * @property int $size
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable([
    'furniture_request_id',
    'enquiry_id',
    'storage_disk',
    'storage_key',
    'filename',
    'content_type',
    'size',
])]
class Attachment extends Model
{
    public const TABLE = 'attachments';

    /** @use HasFactory<AttachmentFactory> */
    use HasFactory;

    protected $table = self::TABLE;

    protected static function booted(): void
    {
        static::saving(function (Attachment $attachment): void {
            $attachment->assertExactlyOneParent();
        });
    }

    public function furnitureRequest(): BelongsTo
    {
        return $this->belongsTo(FurnitureRequest::class);
    }

    public function enquiry(): BelongsTo
    {
        return $this->belongsTo(Enquiry::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['size' => 'integer'];
    }

    private function assertExactlyOneParent(): void
    {
        if (($this->furniture_request_id !== null) === ($this->enquiry_id !== null)) {
            throw new DomainException('An attachment must belong to exactly one parent.');
        }
    }
}
