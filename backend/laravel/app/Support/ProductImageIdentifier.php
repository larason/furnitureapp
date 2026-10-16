<?php

namespace App\Support;

use App\Models\ProductImage;

final class ProductImageIdentifier
{
    private const PREFIX = 'img_';

    public static function encode(ProductImage $image): string
    {
        return Base36Identifier::encode(self::PREFIX, (int) $image->getKey());
    }

    /** Decodes an `img_` identifier; malformed input resolves to null rather than an id. */
    public static function decode(string $identifier): ?int
    {
        return Base36Identifier::decode($identifier, self::PREFIX);
    }
}
