<?php

namespace Tests\Feature;

use App\Models\FurnitureRequest;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use App\Support\ProductIdentifier;
use Closure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\AuthenticatesApiUser;
use Tests\Support\ProvidesNonPublicProducts;
use Tests\Support\SubmitsApiRequests;
use Tests\TestCase;

class FurnitureRequestProductLinkApiTest extends TestCase
{
    use AuthenticatesApiUser;
    use ProvidesNonPublicProducts;
    use RefreshDatabase;
    use SubmitsApiRequests;

    private const URL = '/api/v1/requests';

    private const BASE = [
        'name' => 'Asha Mwangi',
        'phone' => '+255700000001',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        config(['requests.route_enabled' => true]);
    }

    public function test_anonymous_public_made_to_order_request_is_linked(): void
    {
        $product = Product::factory()->madeToOrder()->create(['name' => 'Oak Sofa', 'slug' => 'oak-sofa']);

        $response = $this->submit([...self::BASE, 'product_id' => ProductIdentifier::encode($product)])
            ->assertStatus(201);

        $response->assertJsonPath('data.product_id', ProductIdentifier::encode($product))
            ->assertJsonPath('data.product.id', ProductIdentifier::encode($product))
            ->assertJsonPath('data.product.name', 'Oak Sofa')
            ->assertJsonPath('data.product.slug', 'oak-sofa')
            ->assertJsonPath('data.request_status', 'SUBMITTED');

        $stored = FurnitureRequest::query()->sole();
        $this->assertSame($product->id, $stored->product_id);
        $this->assertNull($stored->user_id);
    }

    public function test_authenticated_customer_public_made_to_order_request_records_owner(): void
    {
        $product = Product::factory()->madeToOrder()->create();
        $customer = User::factory()->customer()->create(['clerk_user_id' => 'req_link_customer']);

        $this->submit(
            [...self::BASE, 'product_id' => ProductIdentifier::encode($product)],
            $this->authenticateAs($customer),
        )->assertStatus(201);

        $this->assertDatabaseHas('furniture_requests', [
            'user_id' => $customer->id,
            'product_id' => $product->id,
            'request_status' => 'SUBMITTED',
        ]);
    }

    public function test_public_in_stock_product_is_not_requestable(): void
    {
        $product = Product::factory()->inStock()->create();

        $response = $this->submit([...self::BASE, 'product_id' => ProductIdentifier::encode($product)])
            ->assertStatus(409);

        $response->assertJsonPath('errors.0.code', 'PRODUCT_NOT_REQUESTABLE');
        $response->assertJsonPath('errors.0.field', 'product_id');

        $this->assertDatabaseCount('furniture_requests', 0);
        $this->assertSame(0, Order::query()->count());
        $this->assertSame(0, Payment::query()->count());
    }

    #[DataProvider('nonPublicProductProvider')]
    public function test_non_public_products_are_reported_as_not_found(Closure $factory): void
    {
        $product = $factory();

        $this->submit([...self::BASE, 'product_id' => ProductIdentifier::encode($product)])
            ->assertStatus(404)
            ->assertJsonPath('errors.0.code', 'RESOURCE_NOT_FOUND')
            ->assertJsonPath('errors.0.field', 'product_id');

        $this->assertDatabaseCount('furniture_requests', 0);
    }

    public function test_unknown_product_is_reported_as_not_found(): void
    {
        $this->submit([...self::BASE, 'product_id' => ProductIdentifier::encodeId(999999)])
            ->assertStatus(404)
            ->assertJsonPath('errors.0.code', 'RESOURCE_NOT_FOUND');

        $this->assertDatabaseCount('furniture_requests', 0);
    }

    public function test_custom_request_without_product_is_still_valid(): void
    {
        $this->submit(self::BASE)
            ->assertStatus(201)
            ->assertJsonPath('data.product_id', null)
            ->assertJsonPath('data.product', null);
    }
}
