<?php

namespace App\Services\Cart;

use App\Exceptions\Api\ApiException;
use App\Support\ApiErrorCode;

/**
 * Internal Cart-line ineligibility reasons (Phase 6.6). Never serialized:
 * each maps to the frozen Cart error vocabulary for admission/mutation and
 * to `is_purchasable = false` for projection.
 */
enum CartItemInvalidReason: string
{
    case PRODUCT_MISSING = 'PRODUCT_MISSING';
    case PRODUCT_INACTIVE = 'PRODUCT_INACTIVE';
    case PRODUCT_UNPUBLISHED = 'PRODUCT_UNPUBLISHED';
    case PRODUCT_DELETED = 'PRODUCT_DELETED';
    case CATEGORY_INACTIVE = 'CATEGORY_INACTIVE';
    case MADE_TO_ORDER = 'MADE_TO_ORDER';
    case PRODUCT_WITHOUT_VARIANT = 'PRODUCT_WITHOUT_VARIANT';
    case VARIANT_REQUIRED = 'VARIANT_REQUIRED';
    case VARIANT_MISSING = 'VARIANT_MISSING';
    case VARIANT_WRONG_PARENT = 'VARIANT_WRONG_PARENT';
    case VARIANT_INACTIVE = 'VARIANT_INACTIVE';
    case OUT_OF_STOCK = 'OUT_OF_STOCK';
    case INSUFFICIENT_FOR_CART_QUANTITY = 'INSUFFICIENT_FOR_CART_QUANTITY';

    public function errorCode(): ApiErrorCode
    {
        return match ($this) {
            self::MADE_TO_ORDER, self::PRODUCT_WITHOUT_VARIANT => ApiErrorCode::PRODUCT_NOT_PURCHASABLE,
            self::VARIANT_REQUIRED, self::VARIANT_MISSING, self::VARIANT_WRONG_PARENT, self::VARIANT_INACTIVE => ApiErrorCode::INVALID_PRODUCT_VARIANT,
            self::OUT_OF_STOCK, self::INSUFFICIENT_FOR_CART_QUANTITY => ApiErrorCode::INSUFFICIENT_STOCK,
            default => ApiErrorCode::PRODUCT_UNAVAILABLE,
        };
    }

    public function field(): string
    {
        return match ($this) {
            self::OUT_OF_STOCK, self::INSUFFICIENT_FOR_CART_QUANTITY => 'quantity',
            self::VARIANT_REQUIRED, self::VARIANT_MISSING, self::VARIANT_WRONG_PARENT, self::VARIANT_INACTIVE => 'variant_id',
            default => 'product_id',
        };
    }

    public function message(): string
    {
        return match ($this) {
            self::MADE_TO_ORDER => 'The product must be requested instead of purchased.',
            self::PRODUCT_WITHOUT_VARIANT => 'The product has no purchasable variant.',
            self::VARIANT_REQUIRED, self::VARIANT_MISSING, self::VARIANT_WRONG_PARENT, self::VARIANT_INACTIVE => 'The selected variant is not available for this product.',
            self::OUT_OF_STOCK, self::INSUFFICIENT_FOR_CART_QUANTITY => 'The requested quantity is not available.',
            default => 'The product is not available for purchase.',
        };
    }

    public function toApiException(): ApiException
    {
        return new ApiException($this->errorCode(), $this->message(), 422, $this->field());
    }
}
