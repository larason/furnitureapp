<?php

namespace Tests\Unit;

use App\Models\CartItem;
use App\Services\Cart\CartItemValidationResult;
use App\Services\Cart\CartStockRevalidationResult;
use PHPUnit\Framework\TestCase;

class CartStockRevalidationResultTest extends TestCase
{
    public function test_it_matches_results_by_internal_cart_item_id(): void
    {
        $first = (new CartItem)->setAttribute('id', 11);
        $second = (new CartItem)->setAttribute('id', 22);
        $result = new CartItemValidationResult(isPurchasable: true, availability: 'available', stockIndicator: 'IN_STOCK');

        $revalidation = new CartStockRevalidationResult([22 => $result]);

        $this->assertNull($revalidation->forItem($first));
        $this->assertSame($result, $revalidation->forItem($second));
    }
}
