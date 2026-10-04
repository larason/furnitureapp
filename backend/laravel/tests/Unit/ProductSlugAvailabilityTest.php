<?php

namespace Tests\Unit;

use App\Exceptions\Api\ApiException;
use App\Services\Products\ProductSlugAvailability;
use Illuminate\Database\QueryException;
use PDOException;
use Tests\TestCase;

class ProductSlugAvailabilityTest extends TestCase
{
    public function test_product_slug_unique_constraint_violation_maps_to_conflict(): void
    {
        $exception = new QueryException(
            'sqlite',
            'insert into "products"',
            [],
            new PDOException('UNIQUE constraint failed: products.slug', '23000'),
        );

        try {
            app(ProductSlugAvailability::class)->throwIfUniqueViolation($exception);
            $this->fail('Expected Product slug collision to be translated.');
        } catch (ApiException $conflict) {
            $this->assertSame('CONFLICT', $conflict->errorCode()->value);
            $this->assertSame(409, $conflict->status());
            $this->assertSame('slug', $conflict->field());
        }
    }
}
