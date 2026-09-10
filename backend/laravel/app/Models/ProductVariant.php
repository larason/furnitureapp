<?php

namespace App\Models;

use Database\Factories\ProductVariantFactory;
use DomainException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'sku',
    'variant_name',
    'price_amount',
    'price_currency',
    'compare_at_price_amount',
    'compare_at_price_currency',
    'cost_price_amount',
    'cost_price_currency',
    'width_cm',
    'height_cm',
    'depth_cm',
    'weight_kg',
    'attributes',
    'is_default',
    'is_active',
    'display_order',
])]
class ProductVariant extends Model
{
    /** @use HasFactory<ProductVariantFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::saving(fn (ProductVariant $variant) => $variant->assertValid());
    }

    public function assertValid(): void
    {
        if (trim($this->variant_name ?? '') === '') {
            throw new DomainException('A variant name is required.');
        }

        if (trim($this->sku ?? '') === '') {
            throw new DomainException('A variant SKU is required.');
        }

        self::assertMoneyPairs($this);
        self::assertPositiveMeasurements($this);
        self::assertSingleDefault($this);
    }

    private static function assertPositiveMeasurements(ProductVariant $variant): void
    {
        $measurements = [
            'width_cm' => 'width',
            'height_cm' => 'height',
            'depth_cm' => 'depth',
            'weight_kg' => 'weight',
        ];

        foreach ($measurements as $field => $label) {
            $value = $variant->{$field};

            if ($value !== null && $value <= 0) {
                throw new DomainException("Variant {$label} must be greater than zero.");
            }
        }
    }

    private static function assertSingleDefault(ProductVariant $variant): void
    {
        if (! $variant->is_default) {
            return;
        }

        $query = ProductVariant::query()
            ->where('product_id', $variant->product_id)
            ->where('is_default', true);

        if ($variant->exists) {
            $query->whereKeyNot($variant->id);
        }

        if ($query->exists()) {
            throw new DomainException('A product can only have one default variant.');
        }
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(ProductStock::class);
    }

    protected function casts(): array
    {
        return [
            'attributes' => 'array',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
            'display_order' => 'integer',
            'width_cm' => 'decimal:2',
            'height_cm' => 'decimal:2',
            'depth_cm' => 'decimal:2',
            'weight_kg' => 'decimal:2',
        ];
    }

    private static function assertMoneyPairs(ProductVariant $variant): void
    {
        $pairs = [
            'compare_at_price' => ['compare_at_price_amount', 'compare_at_price_currency'],
            'cost_price' => ['cost_price_amount', 'cost_price_currency'],
        ];

        foreach ($pairs as [$amountField, $currencyField]) {
            $amount = $variant->{$amountField};
            $currency = $variant->{$currencyField};

            if ($amount !== null && $currency === null) {
                throw new DomainException(ucfirst($amountField).' requires a matching currency.');
            }
            if ($amount === null && $currency !== null) {
                throw new DomainException(ucfirst($currencyField).' requires a matching amount.');
            }
        }
    }
}
