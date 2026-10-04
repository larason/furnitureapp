<?php

namespace App\Models;

use App\Models\Builders\AttachmentParentBuilder;
use App\Support\EnquiryCategory;
use App\Support\EnquiryStatus;
use App\Support\ReferenceGenerator;
use Database\Factories\EnquiryFactory;
use DomainException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $user_id
 * @property string|null $enquiry_reference
 * @property int|null $product_id
 * @property int|null $order_id
 * @property string $name
 * @property string|null $email
 * @property string|null $phone
 * @property EnquiryCategory|null $category
 * @property string $subject
 * @property string $message
 * @property EnquiryStatus|null $enquiry_status
 * @property string|null $staff_internal_notes
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable([
    'user_id',
    'product_id',
    'order_id',
    'name',
    'email',
    'phone',
    'category',
    'subject',
    'message',
])]
class Enquiry extends Model
{
    public const TABLE = 'enquiries';

    public const REFERENCE_PREFIX = 'ENQ-';

    public const MAX_NAME = 120;

    public const MAX_EMAIL = 255;

    public const MAX_PHONE = 30;

    public const MAX_SUBJECT = 200;

    public const MAX_MESSAGE = 5000;

    public const MAX_STAFF_NOTES = 5000;

    private const REFERENCE_PATTERN = '/^ENQ-[A-Z0-9]{10}$/';

    private const IMMUTABLE_FIELDS = [
        'user_id',
        'enquiry_reference',
        'product_id',
        'order_id',
        'name',
        'email',
        'phone',
        'category',
        'subject',
        'message',
    ];

    /** @use HasFactory<EnquiryFactory> */
    use HasFactory;

    protected $table = self::TABLE;

    public function newEloquentBuilder(mixed $query): AttachmentParentBuilder
    {
        return new AttachmentParentBuilder($query);
    }

    protected static function booted(): void
    {
        static::saving(function (Enquiry $enquiry): void {
            $enquiry->ensureServerDefaults();
            $enquiry->normalizeFields();
            $enquiry->assertValid();
        });
    }

    private function ensureServerDefaults(): void
    {
        if (! is_string($this->enquiry_reference) || trim($this->enquiry_reference) === '') {
            $this->enquiry_reference = ReferenceGenerator::generate(self::REFERENCE_PREFIX, 10);
        }

        if ($this->enquiry_status === null) {
            $this->enquiry_status = EnquiryStatus::OPEN;
        }
    }

    private function normalizeFields(): void
    {
        foreach (['name', 'email', 'phone', 'subject', 'message'] as $field) {
            $val = $this->getAttribute($field);
            if (is_string($val)) {
                $this->setAttribute($field, trim($val));
            }
        }
    }

    public function assertValid(): void
    {
        $this->assertReference();
        $this->assertContact();
        $this->assertCategory();
        $this->assertSubject();
        $this->assertMessage();
        $this->assertStatus();
        $this->assertStaffNotes();
        $this->assertContentImmutable();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return HasMany<Attachment, $this> */
    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class);
    }

    protected function casts(): array
    {
        return [
            'category' => EnquiryCategory::class,
            'enquiry_status' => EnquiryStatus::class,
        ];
    }

    private function assertReference(): void
    {
        $reference = (string) $this->enquiry_reference;

        if (preg_match(self::REFERENCE_PATTERN, $reference) !== 1) {
            throw new DomainException('Enquiry reference must follow ENQ-**********.');
        }

        if ($this->exists && $this->getOriginal('enquiry_reference') !== $this->enquiry_reference) {
            throw new DomainException('Enquiry reference is immutable.');
        }
    }

    private function assertContact(): void
    {
        $this->assertRequiredString('name', $this->name, self::MAX_NAME);

        if ($this->email === null && $this->phone === null) {
            throw new DomainException('An enquiry requires at least one of email or phone.');
        }

        if ($this->email !== null) {
            $this->assertRequiredString('email', $this->email, self::MAX_EMAIL);

            if (filter_var($this->email, FILTER_VALIDATE_EMAIL) === false) {
                throw new DomainException('Enquiry email must be a valid email address.');
            }
        }

        if ($this->phone !== null) {
            $this->assertRequiredString('phone', $this->phone, self::MAX_PHONE);
        }
    }

    private function assertRequiredString(string $field, mixed $value, int $max): void
    {
        if (! is_string($value) || trim($value) === '') {
            throw new DomainException("Enquiry {$field} is required.");
        }

        if (mb_strlen(trim($value)) > $max) {
            throw new DomainException("Enquiry {$field} is too long.");
        }
    }

    private function assertCategory(): void
    {
        if ($this->category !== null && ! in_array($this->category, EnquiryCategory::cases(), true)) {
            throw new DomainException('Enquiry category must be a closed V1 enquiry category.');
        }
    }

    private function assertSubject(): void
    {
        $this->assertRequiredString('subject', $this->subject, self::MAX_SUBJECT);
    }

    private function assertMessage(): void
    {
        $this->assertRequiredString('message', $this->message, self::MAX_MESSAGE);
    }

    private function assertStatus(): void
    {
        if (! in_array($this->enquiry_status, EnquiryStatus::cases(), true)) {
            throw new DomainException('Enquiry status must be a closed V1 enquiry status.');
        }
    }

    private function assertStaffNotes(): void
    {
        if ($this->staff_internal_notes !== null && mb_strlen($this->staff_internal_notes) > self::MAX_STAFF_NOTES) {
            throw new DomainException('Enquiry staff notes are too long.');
        }
    }

    private function assertContentImmutable(): void
    {
        if (! $this->exists) {
            return;
        }

        foreach (self::IMMUTABLE_FIELDS as $field) {
            if ($this->isDirty($field)) {
                throw new DomainException("Enquiry {$field} is immutable once submitted.");
            }
        }
    }
}
