<?php

namespace App\Models;

use Database\Factories\ProductVariantFactory;
use DomainException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
        static::saving(function (ProductVariant $variant) {
            if (trim($variant->variant_name ?? '') === '') {
                throw new DomainException('A variant name is required.');
            }

            if (trim($variant->sku ?? '') === '') {
                throw new DomainException('A variant SKU is required.');
            }

            self::assertMoneyPairs($variant);

            if ($variant->width_cm !== null && $variant->width_cm <= 0) {
                throw new DomainException('Variant width must be greater than zero.');
            }
            if ($variant->height_cm !== null && $variant->height_cm <= 0) {
                throw new DomainException('Variant height must be greater than zero.');
            }
            if ($variant->depth_cm !== null && $variant->depth_cm <= 0) {
                throw new DomainException('Variant depth must be greater than zero.');
            }
            if ($variant->weight_kg !== null && $variant->weight_kg <= 0) {
                throw new DomainException('Variant weight must be greater than zero.');
            }

            if ($variant->is_default) {
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
        });
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
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
            ['compare_at_price_amount', 'compare_at_price_currency'],
            ['cost_price_amount', 'cost_price_currency'],
        ];

        foreach ($pairs as $pair) {
            $amount = $variant->{$pair[0]};
            $currency = $variant->{$pair[1]};

            if ($amount !== null && $currency === null) {
                throw new DomainException(ucfirst($pair[0]).' requires a matching currency.');
            }
            if ($amount === null && $currency !== null) {
                throw new DomainException(ucfirst($pair[1]).' requires a matching amount.');
            }
        }
    }
}
