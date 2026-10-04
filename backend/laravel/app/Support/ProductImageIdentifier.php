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
}
