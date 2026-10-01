<?php

namespace Tests\Feature;

use App\Authentication\AuthenticatedClerkIdentity;
use App\Authentication\Clerk\ClerkAuthenticationFailure;
use App\Authentication\ClerkTokenVerifier;
use App\Models\Cart;
use App\Models\Category;
use App\Models\Enquiry;
use App\Models\FurnitureRequest;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\ProductVariant;
use App\Models\User;
use App\Support\OrderIdentifier;
use App\Support\ProductIdentifier;
use Closure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class EnquiryValidationApiTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/api/v1/enquiries';

    private const BASE = [
        'name' => 'Asha Mwangi',
        'phone' => '+255700000001',
        'subject' => 'Delivery question',
        'message' => 'Do you deliver furniture to Dodoma?',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        config(['enquiries.route_enabled' => true]);
    }

    /** @return list<array{0: string}> */
    public static function unknownFieldProvider(): array
    {
        return array_map(
            static fn (string $field): array => [$field],
            ['user_id', 'enquiry_status', 'staff_internal_notes', 'enquiry_reference', 'id', 'created_at', 'updated_at', 'request_id', 'request_status', 'dimensions', 'material', 'color', 'quantity', 'notes', 'order_status', 'payment_status', 'delivery_fee', 'quoted_price', 'price', 'category_id', 'foo'],
        );
    }

    #[DataProvider('unknownFieldProvider')]
    public function test_unknown_and_server_controlled_fields_are_rejected(string $field): void
    {
        $response = $this->submit([...self::BASE, $field => 'x'])->assertStatus(422);

        $response->assertJsonPath('errors.0.code', 'INVALID_VALUE');
        $response->assertJsonPath('errors.0.field', $field);
        $this->assertDatabaseCount('enquiries', 0);
    }

    /** @return list<array{0: string, 1: mixed}> */
    public static function wrongTypeProvider(): array
    {
        return [
            ['name', 123],
            ['phone', ['+255700000001']],
            ['email', true],
            ['subject', 5],
            ['message', true],
            ['category', 5],
            ['product_id', 123],
            ['order_id', 123],
        ];
    }

    #[DataProvider('wrongTypeProvider')]
    public function test_wrong_scalar_types_are_rejected(string $field, mixed $value): void
    {
        $response = $this->submit([...self::BASE, $field => $value])->assertStatus(422);

        $response->assertJsonPath('errors.0.code', 'INVALID_TYPE');
        $response->assertJsonPath('errors.0.field', $field);
        $this->assertDatabaseCount('enquiries', 0);
    }

    /** @return list<array{0: int, 1: string}> */
    public static function subjectBoundaryProvider(): array
    {
        return [
            [4, 'INVALID_VALUE'],
            [5, 'CREATED'],
            [200, 'CREATED'],
            [201, 'INVALID_VALUE'],
        ];
    }

    #[DataProvider('subjectBoundaryProvider')]
    public function test_subject_boundaries(int $length, string $expected): void
    {
        $response = $this->submit([...self::BASE, 'subject' => str_repeat('s', $length)]);

        if ($expected === 'CREATED') {
            $response->assertStatus(201);

            return;
        }

        $response->assertStatus(422)->assertJsonPath('errors.0.code', $expected)->assertJsonPath('errors.0.field', 'subject');
    }

    public function test_subject_missing_blank_and_wrong_type(): void
    {
        $missing = ['name' => 'Asha', 'phone' => '+255700000001', 'message' => 'Do you deliver furniture to Dodoma?'];

        $this->submit([...self::BASE, 'subject' => ''])->assertStatus(422)->assertJsonPath('errors.0.code', 'MISSING_REQUIRED_FIELD');
        $this->submit([...self::BASE, 'subject' => '   '])->assertStatus(422)->assertJsonPath('errors.0.code', 'MISSING_REQUIRED_FIELD');
        $this->submit($missing)->assertStatus(422)->assertJsonPath('errors.0.field', 'subject');
    }

    /** @return list<array{0: int, 1: string}> */
    public static function messageBoundaryProvider(): array
    {
        return [
            [9, 'INVALID_VALUE'],
            [10, 'CREATED'],
            [5000, 'CREATED'],
            [5001, 'INVALID_VALUE'],
        ];
    }

    #[DataProvider('messageBoundaryProvider')]
    public function test_message_boundaries(int $length, string $expected): void
    {
        $response = $this->submit([...self::BASE, 'message' => str_repeat('m', $length)]);

        if ($expected === 'CREATED') {
            $response->assertStatus(201);

            return;
        }

        $response->assertStatus(422)->assertJsonPath('errors.0.code', $expected)->assertJsonPath('errors.0.field', 'message');
    }

    public function test_message_missing_blank_and_wrong_type(): void
    {
        $this->submit([...self::BASE, 'message' => ''])->assertStatus(422)->assertJsonPath('errors.0.code', 'MISSING_REQUIRED_FIELD');
        $this->submit([...self::BASE, 'message' => '   '])->assertStatus(422)->assertJsonPath('errors.0.code', 'MISSING_REQUIRED_FIELD');
    }

    public function test_category_is_nullable_and_closed(): void
    {
        $this->submit(self::BASE)->assertStatus(201)->assertJsonPath('data.category', null);
        $this->submit([...self::BASE, 'category' => null])->assertStatus(201)->assertJsonPath('data.category', null);

        foreach (['GENERAL', 'PRODUCT', 'DELIVERY', 'OTHER'] as $category) {
            $this->submit([...self::BASE, 'category' => $category])->assertStatus(201)->assertJsonPath('data.category', $category);
        }
    }

    public function test_category_rejects_aliases_and_unknown_values(): void
    {
        foreach (['general', 'Product', 'SHIPPING', 'PAYMENT', 'ORDER', 'SUPPORT'] as $category) {
            $this->submit([...self::BASE, 'category' => $category])
                ->assertStatus(422)
                ->assertJsonPath('errors.0.code', 'INVALID_VALUE')
                ->assertJsonPath('errors.0.field', 'category');
        }
    }

    /** @return array<string, array{0: array<string, mixed>, 1: int, 2: string|null}> */
    public static function anonymousContactProvider(): array
    {
        return [
            'name and phone' => [['name' => 'Asha', 'phone' => '+255700000001'], 201, null],
            'name and email' => [['name' => 'Asha', 'email' => 'asha@example.com'], 201, null],
            'name phone email' => [['name' => 'Asha', 'phone' => '+255700000001', 'email' => 'asha@example.com'], 201, null],
            'missing name' => [['phone' => '+255700000001'], 422, 'MISSING_REQUIRED_FIELD'],
            'no channel' => [['name' => 'Asha'], 422, 'MISSING_REQUIRED_FIELD'],
            'invalid phone' => [['name' => 'Asha', 'phone' => 'abc'], 422, 'INVALID_FORMAT'],
            'invalid email' => [['name' => 'Asha', 'email' => 'nope'], 422, 'INVALID_FORMAT'],
            'blank name' => [['name' => '   ', 'phone' => '+255700000001'], 422, 'MISSING_REQUIRED_FIELD'],
        ];
    }

    #[DataProvider('anonymousContactProvider')]
    public function test_anonymous_contact_matrix(array $contact, int $status, ?string $code): void
    {
        $response = $this->submit([
            'subject' => 'Delivery question',
            'message' => 'Do you deliver furniture to Dodoma?',
            ...$contact,
        ])->assertStatus($status);

        if ($code === null) {
            return;
        }

        $response->assertJsonPath('errors.0.code', $code);
        $this->assertDatabaseCount('enquiries', 0);
    }

    public function test_authenticated_customer_may_derive_contact_from_profile(): void
    {
        $customer = $this->customerProfileUser('enq_derive');

        $response = $this->submit(
            ['subject' => 'Delivery question', 'message' => 'Do you deliver furniture to Dodoma?'],
            $this->authenticateAs($customer),
        )->assertStatus(201);

        $response->assertJsonPath('data.name', 'Profile Name')
            ->assertJsonPath('data.email', 'profile@example.com')
            ->assertJsonPath('data.phone', '+255700000777');

        $this->assertDatabaseHas('enquiries', [
            'user_id' => $customer->id,
            'name' => 'Profile Name',
            'email' => 'profile@example.com',
            'phone' => '+255700000777',
            'enquiry_status' => 'OPEN',
        ]);
    }

    public function test_explicit_authenticated_contact_overrides_profile(): void
    {
        $customer = $this->customerProfileUser('enq_override');

        $response = $this->submit([
            'subject' => 'Delivery question',
            'message' => 'Do you deliver furniture to Dodoma?',
            'name' => 'Submitted Name',
            'phone' => '+255700000999',
        ], $this->authenticateAs($customer))->assertStatus(201);

        $response->assertJsonPath('data.name', 'Submitted Name')
            ->assertJsonPath('data.phone', '+255700000999')
            ->assertJsonPath('data.email', 'profile@example.com');
    }

    public function test_invalid_supplied_contact_is_not_replaced_by_profile(): void
    {
        $customer = $this->customerProfileUser('enq_invalid_supplied');

        $this->submit([
            'subject' => 'Delivery question',
            'message' => 'Do you deliver furniture to Dodoma?',
            'email' => 'not-an-email',
        ], $this->authenticateAs($customer))
            ->assertStatus(422)
            ->assertJsonPath('errors.0.code', 'INVALID_FORMAT')
            ->assertJsonPath('errors.0.field', 'email');

        $this->assertDatabaseCount('enquiries', 0);
    }

    public function test_authenticated_without_any_reachable_contact_is_rejected(): void
    {
        $customer = User::factory()->customer()->create([
            'clerk_user_id' => 'enq_unreachable',
            'name' => 'No Contact',
            'email' => 'broken-email',
            'phone' => '',
        ]);

        $this->submit(
            ['subject' => 'Delivery question', 'message' => 'Do you deliver furniture to Dodoma?'],
            $this->authenticateAs($customer),
        )->assertStatus(422)->assertJsonPath('errors.0.code', 'MISSING_REQUIRED_FIELD');

        $this->assertDatabaseCount('enquiries', 0);
    }

    public function test_over_long_derived_profile_name_is_rejected_not_a_server_error(): void
    {
        $customer = User::factory()->customer()->create([
            'clerk_user_id' => 'enq_long_name',
            'name' => str_repeat('N', 130),
            'email' => 'long@example.com',
            'phone' => '+255700000123',
        ]);

        $this->submit(
            ['subject' => 'Delivery question', 'message' => 'Do you deliver furniture to Dodoma?'],
            $this->authenticateAs($customer),
        )->assertStatus(422)->assertJsonPath('errors.0.code', 'MISSING_REQUIRED_FIELD');

        $this->assertDatabaseCount('enquiries', 0);
    }

    public function test_product_association_accepts_any_public_product_type(): void
    {
        $inStock = Product::factory()->inStock()->create(['name' => 'Ready Sofa', 'slug' => 'ready-sofa']);
        $madeToOrder = Product::factory()->madeToOrder()->create();

        $response = $this->submit([...self::BASE, 'product_id' => ProductIdentifier::encode($inStock)])->assertStatus(201);

        $response->assertJsonPath('data.product_id', ProductIdentifier::encode($inStock))
            ->assertJsonPath('data.product.name', 'Ready Sofa')
            ->assertJsonPath('data.product.slug', 'ready-sofa');

        $this->submit([...self::BASE, 'product_id' => ProductIdentifier::encode($madeToOrder)])->assertStatus(201);
    }

    /** @return array<string, array{0: Closure(): Product}> */
    public static function nonPublicProductProvider(): array
    {
        return [
            'inactive' => [fn (): Product => Product::factory()->inStock()->inactive()->create()],
            'unpublished' => [fn (): Product => Product::factory()->inStock()->draft()->create()],
            'soft deleted' => [fn (): Product => tap(
                Product::factory()->inStock()->create(),
                fn (Product $product) => $product->delete(),
            )],
            'inactive category' => [fn (): Product => Product::factory()->inStock()->create([
                'category_id' => Category::factory()->inactive()->create()->id,
            ])],
        ];
    }

    #[DataProvider('nonPublicProductProvider')]
    public function test_non_public_product_is_masked_as_not_found(Closure $factory): void
    {
        $product = $factory();

        $this->submit([...self::BASE, 'product_id' => ProductIdentifier::encode($product)])
            ->assertStatus(404)
            ->assertJsonPath('errors.0.code', 'RESOURCE_NOT_FOUND')
            ->assertJsonPath('errors.0.field', 'product_id');

        $this->assertDatabaseCount('enquiries', 0);
    }

    public function test_unknown_product_is_masked_as_not_found(): void
    {
        $this->submit([...self::BASE, 'product_id' => ProductIdentifier::encodeId(999999)])
            ->assertStatus(404)
            ->assertJsonPath('errors.0.code', 'RESOURCE_NOT_FOUND');
    }

    public function test_anonymous_non_null_order_reference_is_rejected(): void
    {
        $order = Order::factory()->create();

        $this->submit([...self::BASE, 'order_id' => OrderIdentifier::encode($order)])
            ->assertStatus(422)
            ->assertJsonPath('errors.0.code', 'INVALID_VALUE')
            ->assertJsonPath('errors.0.field', 'order_id');

        $this->assertDatabaseCount('enquiries', 0);
    }

    public function test_customer_may_reference_owned_order(): void
    {
        $customer = User::factory()->customer()->create(['clerk_user_id' => 'enq_owner']);
        $order = Order::factory()->create(['customer_id' => $customer->id]);

        $response = $this->submit(
            [...self::BASE, 'order_id' => OrderIdentifier::encode($order)],
            $this->authenticateAs($customer),
        )->assertStatus(201);

        $response->assertJsonPath('data.order_id', OrderIdentifier::encode($order))
            ->assertJsonPath('data.order.id', OrderIdentifier::encode($order))
            ->assertJsonPath('data.order.order_reference', $order->order_reference)
            ->assertJsonPath('data.order.status', 'PENDING_PAYMENT');
    }

    public function test_foreign_and_unknown_orders_are_masked_as_not_found(): void
    {
        $customer = User::factory()->customer()->create(['clerk_user_id' => 'enq_foreign']);
        $foreign = Order::factory()->create();

        foreach ([OrderIdentifier::encode($foreign), OrderIdentifier::encode(Order::factory()->create())] as $identifier) {
            $this->submit([...self::BASE, 'order_id' => $identifier], $this->authenticateAs($customer))
                ->assertStatus(404)
                ->assertJsonPath('errors.0.code', 'ORDER_NOT_FOUND')
                ->assertJsonPath('errors.0.field', 'order_id');
        }

        $this->assertDatabaseCount('enquiries', 0);
    }

    public function test_product_and_order_may_be_supplied_together(): void
    {
        $customer = User::factory()->customer()->create(['clerk_user_id' => 'enq_both']);
        $order = Order::factory()->create(['customer_id' => $customer->id]);
        $product = Product::factory()->inStock()->create();

        $this->submit([
            ...self::BASE,
            'product_id' => ProductIdentifier::encode($product),
            'order_id' => OrderIdentifier::encode($order),
        ], $this->authenticateAs($customer))->assertStatus(201);
    }

    public function test_general_enquiry_without_associations_is_valid(): void
    {
        $response = $this->submit(self::BASE)->assertStatus(201);

        $response->assertJsonPath('data.product_id', null)
            ->assertJsonPath('data.product', null)
            ->assertJsonPath('data.order_id', null)
            ->assertJsonPath('data.order', null)
            ->assertJsonPath('data.enquiry_status', 'OPEN');
    }

    public function test_invalid_bearer_is_rejected_before_validation(): void
    {
        $this->mock(ClerkTokenVerifier::class)
            ->shouldReceive('verify')
            ->andThrow(ClerkAuthenticationFailure::invalid());

        $this->submit([...self::BASE, 'subject' => ''], ['Authorization' => 'Bearer invalid-token'])
            ->assertStatus(401)
            ->assertJsonPath('errors.0.code', 'INVALID_AUTHENTICATION');
    }

    public function test_staff_and_admin_are_forbidden(): void
    {
        $staff = User::factory()->staff()->create(['clerk_user_id' => 'enq_staff']);
        $admin = User::factory()->admin()->create(['clerk_user_id' => 'enq_admin']);

        foreach ([$staff, $admin] as $actor) {
            $this->submit(self::BASE, $this->authenticateAs($actor))
                ->assertStatus(403)
                ->assertJsonPath('errors.0.code', 'FORBIDDEN');
        }
    }

    public function test_route_is_gated_by_default(): void
    {
        config(['enquiries.route_enabled' => false]);

        $this->submit(self::BASE)->assertStatus(501)->assertJsonPath('status', 'not_implemented');
        $this->assertDatabaseCount('enquiries', 0);
    }

    public function test_created_response_uses_private_cache_headers(): void
    {
        $response = $this->submit(self::BASE)->assertStatus(201);

        $response->assertHeaderContains('Cache-Control', 'private');
        $response->assertHeaderContains('Cache-Control', 'no-store');
        $response->assertHeaderContains('Vary', 'Authorization');
    }

    public function test_plain_text_markup_and_newlines_are_preserved(): void
    {
        $message = "<script>alert(1)</script>\n5 > 3 & okay";

        $response = $this->submit([...self::BASE, 'message' => $message])->assertStatus(201);

        $response->assertJsonPath('data.message', $message);
        $this->assertDatabaseHas('enquiries', ['message' => $message]);
    }

    public function test_duplicate_submissions_create_two_enquiries(): void
    {
        $this->submit(self::BASE)->assertStatus(201);
        $this->submit(self::BASE)->assertStatus(201);

        $this->assertSame(2, Enquiry::query()->count());
    }

    public function test_enquiry_creation_has_no_commerce_or_request_side_effects(): void
    {
        $product = Product::factory()->inStock()->create();
        $variant = ProductVariant::factory()->create(['product_id' => $product->id]);
        $stock = ProductStock::factory()->forVariant($variant)->create(['quantity' => 5, 'reserved_quantity' => 1]);

        $this->submit(self::BASE)->assertStatus(201);

        $this->assertSame(0, Order::query()->count());
        $this->assertSame(0, Payment::query()->count());
        $this->assertSame(0, Cart::query()->count());
        $this->assertSame(0, FurnitureRequest::query()->count());

        $this->assertSame(5, $stock->fresh()->quantity);
        $this->assertSame(1, $stock->fresh()->reserved_quantity);
    }

    private function submit(array $payload, array $headers = []): TestResponse
    {
        RateLimiter::clear('ip:127.0.0.1');
        RateLimiter::clear(md5('anonymous-submit'.'ip:127.0.0.1'));

        return $this->withHeaders($headers)->postJson(self::URL, $payload);
    }

    private function customerProfileUser(string $clerkUserId): User
    {
        return User::factory()->customer()->create([
            'clerk_user_id' => $clerkUserId,
            'name' => 'Profile Name',
            'email' => 'profile@example.com',
            'phone' => '+255700000777',
        ]);
    }

    /** @return array<string, string> */
    private function authenticateAs(User $user): array
    {
        $verifier = $this->mock(ClerkTokenVerifier::class);
        $verifier->shouldReceive('verify')->andReturn(new AuthenticatedClerkIdentity(
            (string) $user->clerk_user_id,
            'sess_test',
            'https://clerk.example.test',
        ));
        $this->app->instance(ClerkTokenVerifier::class, $verifier);

        return ['Authorization' => 'Bearer session-token'];
    }
}
