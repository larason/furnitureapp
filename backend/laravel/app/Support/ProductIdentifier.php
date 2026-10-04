<?php

namespace App\Support;

use App\Models\Product;

final class ProductIdentifier
{
    private const PREFIX = 'prod_';

    public static function encode(Product $product): string
    {
        return self::encodeId((int) $product->getKey());
    }

    public static function encodeId(int $id): string
    {
        return Base36Identifier::encode(self::PREFIX, $id);
    }

    public static function decode(string $identifier): ?int
    {
        return Base36Identifier::decode($identifier, self::PREFIX);
    }
}
