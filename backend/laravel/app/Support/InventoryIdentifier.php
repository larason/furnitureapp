<?php

namespace App\Support;

use App\Models\ProductStock;

final class InventoryIdentifier
{
    private const PREFIX = 'inv_';

    public static function encode(ProductStock $stock): string
    {
        return Base36Identifier::encode(self::PREFIX, (int) $stock->getKey());
    }

    public static function decode(string $identifier): ?int
    {
        return Base36Identifier::decode($identifier, self::PREFIX);
    }
}
