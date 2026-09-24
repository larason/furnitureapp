<?php

namespace Tests\Unit;

use App\Services\Cart\CartItemInvalidReason;
use App\Support\ApiErrorCode;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CartItemInvalidReasonTest extends TestCase
{
    /**
     * @return array<string, array{0: CartItemInvalidReason, 1: ApiErrorCode, 2: string}>
     */
    public static function mappings(): array
    {
        return [
            'made to order' => [CartItemInvalidReason::MADE_TO_ORDER, ApiErrorCode::PRODUCT_NOT_PURCHASABLE, 'product_id'],
            'no variant' => [CartItemInvalidReason::PRODUCT_WITHOUT_VARIANT, ApiErrorCode::PRODUCT_NOT_PURCHASABLE, 'product_id'],
            'inactive product' => [CartItemInvalidReason::PRODUCT_INACTIVE, ApiErrorCode::PRODUCT_UNAVAILABLE, 'product_id'],
            'unpublished product' => [CartItemInvalidReason::PRODUCT_UNPUBLISHED, ApiErrorCode::PRODUCT_UNAVAILABLE, 'product_id'],
            'deleted product' => [CartItemInvalidReason::PRODUCT_DELETED, ApiErrorCode::PRODUCT_UNAVAILABLE, 'product_id'],
            'missing product' => [CartItemInvalidReason::PRODUCT_MISSING, ApiErrorCode::PRODUCT_UNAVAILABLE, 'product_id'],
            'inactive category' => [CartItemInvalidReason::CATEGORY_INACTIVE, ApiErrorCode::PRODUCT_UNAVAILABLE, 'product_id'],
            'variant required' => [CartItemInvalidReason::VARIANT_REQUIRED, ApiErrorCode::INVALID_PRODUCT_VARIANT, 'variant_id'],
            'variant missing' => [CartItemInvalidReason::VARIANT_MISSING, ApiErrorCode::INVALID_PRODUCT_VARIANT, 'variant_id'],
            'wrong parent' => [CartItemInvalidReason::VARIANT_WRONG_PARENT, ApiErrorCode::INVALID_PRODUCT_VARIANT, 'variant_id'],
            'inactive variant' => [CartItemInvalidReason::VARIANT_INACTIVE, ApiErrorCode::INVALID_PRODUCT_VARIANT, 'variant_id'],
            'out of stock' => [CartItemInvalidReason::OUT_OF_STOCK, ApiErrorCode::INSUFFICIENT_STOCK, 'quantity'],
            'insufficient for quantity' => [CartItemInvalidReason::INSUFFICIENT_FOR_CART_QUANTITY, ApiErrorCode::INSUFFICIENT_STOCK, 'quantity'],
        ];
    }

    #[DataProvider('mappings')]
    public function test_reason_maps_to_the_frozen_cart_error_contract(CartItemInvalidReason $reason, ApiErrorCode $code, string $field): void
    {
        $exception = $reason->toApiException();

        $this->assertSame($code, $reason->errorCode());
        $this->assertSame($field, $reason->field());
        $this->assertSame($code, $exception->errorCode());
        $this->assertSame(422, $exception->status());
        $this->assertSame($field, $exception->field());
    }
}
