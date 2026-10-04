<?php

namespace App\Http\Resources;

use App\Exceptions\Api\ApiException;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Cart\CartItemEligibility;
use App\Services\Cart\CartItemValidationResult;
use App\Services\ProductImages\ProductImageStorage;
use App\Support\ApiErrorCode;
use App\Support\CartItemIdentifier;
use App\Support\ProductIdentifier;
use App\Support\ProductImageIdentifier;
use App\Support\VariantIdentifier;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read CartItem $resource
 */
final class CartItemResource extends JsonResource
{
    private ?CartItemValidationResult $validation = null;

    /** Consume a Cart-wide revalidation result instead of evaluating stock here. */
    public function withValidation(?CartItemValidationResult $validation): static
    {
        $this->validation = $validation;

        return $this;
    }

    public function toArray(Request $request): array
    {
        $item = $this->resource;
        $variant = $item->variant;
        $product = $item->product;

        if ($product === null) {
            throw new ApiException(ApiErrorCode::CONFLICT, 'The cart item is inconsistent.', 409);
        }

        if ($variant !== null) {
            $variant->setRelation('product', $product);
        }

        $evaluation = $this->validation ?? CartItemEligibility::evaluate($product, $variant, $item->quantity);
        $unitPrice = $this->unitPrice($variant);

        return [
            'id' => CartItemIdentifier::encode($item),
            'product_id' => ProductIdentifier::encode($product),
            'variant_id' => $variant === null ? null : VariantIdentifier::encode($variant),
            'product' => $this->productSummary($product),
            'variant' => $variant === null ? null : [
                'id' => VariantIdentifier::encode($variant),
                'sku' => $variant->sku,
                'name' => $variant->variant_name,
                'price' => $this->money((int) $variant->price_amount, $variant->price_currency),
            ],
            'quantity' => $item->quantity,
            'unit_price' => $unitPrice,
            'line_total' => $unitPrice === null ? null : $this->money($unitPrice['amount'] * $item->quantity, $unitPrice['currency']),
            'availability' => $evaluation->availability,
            'stock_indicator' => $evaluation->stockIndicator,
            'is_purchasable' => $evaluation->isPurchasable,
            'created_at' => $item->created_at?->toISOString(),
            'updated_at' => $item->updated_at?->toISOString(),
        ];
    }

    /**
     * Prices only the item's own active variant. A missing or inactive variant
     * is an unpriceable (stale) line and resolves to null, never a borrowed or
     * fabricated price.
     *
     * @return array{amount: int, currency: string}|null
     */
    private function unitPrice(?ProductVariant $variant): ?array
    {
        if ($variant === null || ! $variant->is_active) {
            return null;
        }

        return $this->money((int) $variant->price_amount, $variant->price_currency);
    }

    /**
     * Embedded `ProductSummary`: `price` is the product's base/display price,
     * independent of this line's variant `unit_price`.
     *
     * @return array<string, mixed>
     */
    private function productSummary(Product $product): array
    {
        return [
            'id' => ProductIdentifier::encode($product),
            'name' => $product->name,
            'slug' => $product->slug,
            'product_type' => $product->product_type->value,
            'price' => $this->money((int) $product->price_amount, $product->price_currency),
            'primary_image' => $product->primaryImage === null ? null : [
                'id' => ProductImageIdentifier::encode($product->primaryImage),
                'url' => app(ProductImageStorage::class)->publicUrl($product->primaryImage->file_path),
                'alt_text' => $product->primaryImage->alt_text,
            ],
        ];
    }

    /** @return array{amount: int, currency: string} */
    private function money(int $amount, string $currency): array
    {
        return ['amount' => $amount, 'currency' => $currency];
    }
}
