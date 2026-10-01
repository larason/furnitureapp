<?php

namespace Tests\Support;

use App\Models\Category;
use App\Models\Product;
use Closure;

/**
 * Shared fixtures for "not publicly visible" MADE_TO_ORDER products used by the
 * Phase 10.4 resolver and API tests.
 */
trait ProvidesNonPublicProducts
{
    /** @return array<string, array{0: Closure(): Product}> */
    public static function nonPublicProductProvider(): array
    {
        return [
            'inactive' => [fn (): Product => Product::factory()->madeToOrder()->inactive()->create()],
            'unpublished' => [fn (): Product => Product::factory()->madeToOrder()->draft()->create()],
            'soft deleted' => [fn (): Product => tap(
                Product::factory()->madeToOrder()->create(),
                fn (Product $product) => $product->delete(),
            )],
            'inactive category' => [fn (): Product => Product::factory()->madeToOrder()->create([
                'category_id' => Category::factory()->inactive()->create()->id,
            ])],
        ];
    }
}
