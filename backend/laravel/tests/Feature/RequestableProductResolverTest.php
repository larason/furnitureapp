<?php

namespace Tests\Feature;

use App\Exceptions\Api\ApiException;
use App\Exceptions\ProductNotRequestable;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\ProductVariant;
use App\Services\Requests\RequestableProductResolver;
use App\Support\ApiErrorCode;
use App\Support\ProductIdentifier;
use Closure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\ProvidesNonPublicProducts;
use Tests\TestCase;

class RequestableProductResolverTest extends TestCase
{
    use ProvidesNonPublicProducts;
    use RefreshDatabase;

    public function test_null_identifier_resolves_to_null_without_a_product_query(): void
    {
        DB::enableQueryLog();

        $this->assertNull($this->resolver()->resolve(null));

        $this->assertSame([], DB::getQueryLog());
    }

    public function test_public_made_to_order_product_resolves(): void
    {
        $product = Product::factory()->madeToOrder()->create();

        $resolved = $this->resolver()->resolve(ProductIdentifier::encode($product));

        $this->assertNotNull($resolved);
        $this->assertSame($product->id, $resolved->id);
    }

    public function test_public_in_stock_product_is_not_requestable(): void
    {
        $product = Product::factory()->inStock()->create();

        try {
            $this->resolver()->resolve(ProductIdentifier::encode($product));
            $this->fail('Expected ProductNotRequestable.');
        } catch (ProductNotRequestable $exception) {
            $this->assertSame(ApiErrorCode::PRODUCT_NOT_REQUESTABLE, $exception->errorCode());
            $this->assertSame(409, $exception->status());
            $this->assertSame('product_id', $exception->field());
        }
    }

    #[DataProvider('nonPublicProductProvider')]
    public function test_non_public_products_resolve_as_not_found(Closure $factory): void
    {
        $product = $factory();

        try {
            $this->resolver()->resolve(ProductIdentifier::encode($product));
            $this->fail('Expected the non-public product to be rejected.');
        } catch (ApiException $exception) {
            $this->assertSame(ApiErrorCode::RESOURCE_NOT_FOUND, $exception->errorCode());
            $this->assertSame(404, $exception->status());
            $this->assertSame('product_id', $exception->field());
        }
    }

    public function test_unknown_opaque_identifier_resolves_as_not_found(): void
    {
        try {
            $this->resolver()->resolve(ProductIdentifier::encodeId(999999));
            $this->fail('Expected an unknown product to be rejected.');
        } catch (ApiException $exception) {
            $this->assertSame(ApiErrorCode::RESOURCE_NOT_FOUND, $exception->errorCode());
        }
    }

    public function test_zero_stock_made_to_order_product_is_requestable(): void
    {
        $product = Product::factory()->madeToOrder()->create();

        $resolved = $this->resolver()->resolve(ProductIdentifier::encode($product));

        $this->assertNotNull($resolved);
        $this->assertSame(0, $product->variants()->count());
    }

    public function test_made_to_order_requestability_ignores_variant_and_stock_state(): void
    {
        $product = Product::factory()->madeToOrder()->create();
        $variant = ProductVariant::factory()->create(['product_id' => $product->id, 'is_active' => false]);
        ProductStock::factory()->forVariant($variant)->create(['quantity' => 0, 'reserved_quantity' => 0]);

        $this->assertNotNull($this->resolver()->resolve(ProductIdentifier::encode($product)));
    }

    private function resolver(): RequestableProductResolver
    {
        return app(RequestableProductResolver::class);
    }
}
