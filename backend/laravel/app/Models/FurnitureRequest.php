<?php

namespace App\Models;

use App\Support\RequestField;
use App\Support\RequestStatus;
use Database\Factories\FurnitureRequestFactory;
use DomainException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $user_id
 * @property string $request_reference
 * @property int|null $product_id
 * @property array|null $product_details
 * @property string $style
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

    private const REFERENCE_PATTERN = '/^REQ-[A-Z0-9]{10}$/';

    /** @use HasFactory<FurnitureRequestFactory> */
    use HasFactory;

    protected $table = self::TABLE;

    protected static function booted(): void
    {
        static::saving(function (FurnitureRequest $request): void {
            $request->assertValid();
        });
    }

    public function assertValid(): void
    {
        $this->assertReference();
        $this->assertContact();
        $this->assertProductDetails();
        $this->assertStyle();
        $this->assertMessage();
        $this->assertOptionalSpecs();
        $this->assertStatus();
        $this->assertStaffNotes();
        $this->assertIntakeImmutable();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
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

    private function assertReference(): void
    {
        $reference = (string) $this->request_reference;

        if (preg_match(self::REFERENCE_PATTERN, $reference) !== 1) {
            throw new DomainException('Request reference must follow REQ-**********.');
        }

        if ($this->exists && $this->getOriginal('request_reference') !== $this->request_reference) {
            throw new DomainException('Request reference is immutable.');
        }
    }

    private function assertContact(): void
    {
        $this->assertRequiredString('name', $this->name, self::MAX_NAME);

        if ($this->email === null && $this->phone === null) {
            throw new DomainException('A request requires at least one of email or phone.');
        }

        if ($this->email !== null) {
            $this->assertRequiredString('email', $this->email, self::MAX_EMAIL);
        }

        if ($this->phone !== null) {
            $this->assertRequiredString('phone', $this->phone, self::MAX_PHONE);
        }
    }

    private function assertRequiredString(string $field, mixed $value, int $max): void
    {
        if (! is_string($value) || trim($value) === '') {
            throw new DomainException("Request {$field} is required.");
        }

        if (mb_strlen(trim($value)) > $max) {
            throw new DomainException("Request {$field} is too long.");
        }
    }

    private function assertProductDetails(): void
    {
        $details = $this->product_details;

        if (! is_array($details)) {
            throw new DomainException('Request product details are required.');
        }

        RequestField::validateProductDetails($details);
    }

    private function assertStyle(): void
    {
        $this->assertRequiredString('style', $this->style, self::MAX_STYLE);
    }

    private function assertMessage(): void
    {
        if (! is_string($this->message) || trim($this->message) === '') {
            throw new DomainException('Request message is required.');
        }

        if (mb_strlen(trim($this->message)) > self::MAX_MESSAGE) {
            throw new DomainException('Request message is too long.');
        }
    }

    private function assertOptionalSpecs(): void
    {
        if ($this->quantity !== null) {
            $this->assertQuantity();
        }

        if ($this->dimensions !== null) {
            $this->assertDimensions();
        }

        if ($this->material !== null) {
            $this->assertRequiredString('material', $this->material, self::MAX_MATERIAL);
        }

        if ($this->color !== null) {
            $this->assertRequiredString('color', $this->color, self::MAX_COLOR);
        }
    }

    private function assertQuantity(): void
    {
        if (! is_int($this->quantity) || $this->quantity < 1 || $this->quantity > self::MAX_QUANTITY) {
            throw new DomainException('Request quantity must be between 1 and '.self::MAX_QUANTITY.'.');
        }
    }

    private function assertDimensions(): void
    {
        RequestField::validateDimensions($this->dimensions);
    }

    private function assertStatus(): void
    {
        if (! in_array($this->request_status, RequestStatus::cases(), true)) {
            throw new DomainException('Request status must be a closed V1 request status.');
        }
    }

    private function assertStaffNotes(): void
    {
        if ($this->staff_internal_notes !== null && mb_strlen($this->staff_internal_notes) > self::MAX_STAFF_NOTES) {
            throw new DomainException('Request staff notes are too long.');
        }
    }

    private function assertIntakeImmutable(): void
    {
        if (! $this->exists) {
            return;
        }

        foreach (['user_id', 'request_reference', 'product_id', 'product_details', 'style', 'name', 'email', 'phone', 'message', 'quantity', 'dimensions', 'material', 'color'] as $field) {
            if ($this->isDirty($field)) {
                throw new DomainException("Request {$field} is immutable once submitted.");
            }
        }
    }
}
