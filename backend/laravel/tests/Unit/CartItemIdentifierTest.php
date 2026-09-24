<?php

namespace Tests\Unit;

use App\Models\CartItem;
use App\Support\CartItemIdentifier;
use PHPUnit\Framework\TestCase;

class CartItemIdentifierTest extends TestCase
{
    public function test_encode_matches_base36_of_the_key(): void
    {
        $item = new CartItem;
        $item->setAttribute('id', 9007199254740991);

        $this->assertSame('item_2gosa7pa2gv', CartItemIdentifier::encode($item));
    }

    public function test_encode_decode_roundtrip_is_lossless_across_the_int_range(): void
    {
        $ids = [1, 2, 35, 36, 1295, 9007199254740991, 1000000000000, PHP_INT_MAX];

        foreach ($ids as $id) {
            $item = new CartItem;
            $item->setAttribute('id', $id);

            $this->assertSame($id, CartItemIdentifier::decode(CartItemIdentifier::encode($item)));
        }
    }

    public function test_decode_is_exact_across_the_int_range(): void
    {
        $this->assertSame(1, CartItemIdentifier::decode('item_1'));
        $this->assertSame(9007199254740991, CartItemIdentifier::decode('item_2gosa7pa2gv'));
        $this->assertSame(PHP_INT_MAX, CartItemIdentifier::decode('item_1y2p0ij32e8e7'));
    }

    public function test_decode_returns_null_for_out_of_range_or_invalid_values(): void
    {
        $this->assertNull(CartItemIdentifier::decode('item_zzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzz'));
        $this->assertNull(CartItemIdentifier::decode('item_0'));
        $this->assertNull(CartItemIdentifier::decode('item_'));
        $this->assertNull(CartItemIdentifier::decode('item_ABC'));
        $this->assertNull(CartItemIdentifier::decode('cart_1'));
    }
}
