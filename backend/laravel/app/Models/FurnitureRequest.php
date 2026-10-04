<?php

namespace App\Models;

use App\Models\Builders\AttachmentParentBuilder;
use App\Models\Validation\FurnitureRequestValidator;
use App\Support\RequestStatus;
use Database\Factories\FurnitureRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $user_id
 * @property string $request_reference
 * @property int|null $product_id
 * @property array|null $product_details
 * @property string|null $style
 * @property string $name
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $message
 * @property int|null $quantity
 * @property array|null $dimensions
 * @property string|null $material
 * @property string|null $color
 * @property RequestStatus $request_status
 * @property string|null $staff_internal_notes
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read User|null $user
 * @property-read Product|null $product
 */
#[Fillable([
    'user_id',
    'request_reference',
    'product_id',
    'product_details',
    'style',
    'name',
    'email',
    'phone',
    'message',
    'quantity',
    'dimensions',
    'material',
    'color',
    'request_status',
    'staff_internal_notes',
])]
class FurnitureRequest extends Model
{
    public const TABLE = 'furniture_requests';

    public const REFERENCE_PREFIX = 'REQ-';

    public const MAX_NAME = 120;

    public const MAX_EMAIL = 255;

    public const MAX_PHONE = 30;

    public const MAX_STYLE = 200;

    public const MAX_MESSAGE = 5000;

    public const MAX_MATERIAL = 500;

    public const MAX_COLOR = 200;

    public const MAX_QUANTITY = 100;

    public const MAX_STAFF_NOTES = 5000;

    /** @use HasFactory<FurnitureRequestFactory> */
    use HasFactory;

    protected $table = self::TABLE;

    public function newEloquentBuilder(mixed $query): AttachmentParentBuilder
    {
        return new AttachmentParentBuilder($query);
    }

    protected static function booted(): void
    {
        static::saving(function (FurnitureRequest $request): void {
            $request->normalizeFields();
            $request->assertValid();
        });
    }

    private function normalizeFields(): void
    {
        foreach (['style', 'name', 'email', 'phone', 'message', 'material', 'color'] as $field) {
            if (is_string($this->{$field})) {
                $this->{$field} = trim($this->{$field});
            }
        }

        if (is_array($this->product_details)) {
            $this->product_details = $this->normalizeNestedStrings($this->product_details);
        }

        if (is_array($this->dimensions)) {
            $this->dimensions = $this->normalizeNestedStrings($this->dimensions);
        }
    }

    private function normalizeNestedStrings(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $data[$key] = trim($value);
            }
        }

        return $data;
    }

    public function assertValid(): void
    {
        FurnitureRequestValidator::validate($this);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return HasMany<Attachment, $this> */
    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class);
    }

    protected function casts(): array
    {
        return [
            'product_details' => 'array',
            'dimensions' => 'array',
            'request_status' => RequestStatus::class,
            'quantity' => 'integer',
        ];
    }
}
