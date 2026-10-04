<?php

namespace App\Support;

use App\Models\Category;

final class CategoryIdentifier
{
    private const PREFIX = 'cat_';

    public static function encode(Category $category): string
    {
        return Base36Identifier::encode(self::PREFIX, (int) $category->getKey());
    }

    public static function decode(string $identifier): ?int
    {
        return Base36Identifier::decode($identifier, self::PREFIX);
    }
}
