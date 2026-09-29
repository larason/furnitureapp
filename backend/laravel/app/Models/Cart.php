<?php

namespace App\Models;

use App\Support\CartStatus;
use Database\Factories\CartFactory;
use DomainException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $user_id
 * @property string|null $guest_token_digest
 * @property CartStatus $status
 * @property-read Collection<int, CartItem> $items
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'user_id',
    'guest_token_digest',
    'status',
])]
#[Hidden(['guest_token_digest'])]
class Cart extends Model
{
    /**
     * Maximum distinct cart lines. Bounds cart read/projection cost and
     * database growth; `quantity` per line is additionally capped by
     * CartItem::MAX_QUANTITY.
     */
    public const MAX_ITEMS = 100;

    /** @use HasFactory<CartFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::saving(fn (Cart $cart) => $cart->assertValid());
    }

    public function assertValid(): void
    {
        $hasUser = $this->user_id !== null;
        $hasGuest = $this->guest_token_digest !== null;

        if ($hasUser === $hasGuest) {
            throw new DomainException('A cart must be owned by exactly one owner: a customer or a guest, never both and never neither.');
        }
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<CartItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    public function isGuest(): bool
    {
        return $this->user_id === null && $this->guest_token_digest !== null;
    }

    public function isCustomerOwned(): bool
    {
        return $this->user_id !== null && $this->guest_token_digest === null;
    }

    public function isActive(): bool
    {
        return $this->status === CartStatus::ACTIVE;
    }

    protected function casts(): array
    {
        return [
            'status' => CartStatus::class,
        ];
    }
}
