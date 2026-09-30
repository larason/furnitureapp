<?php

namespace Tests\Feature;

use App\Authentication\AuthenticatedClerkIdentity;
use App\Authentication\Clerk\ClerkAuthenticationFailure;
use App\Authentication\ClerkTokenVerifier;
use App\Models\FurnitureRequest;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use App\Support\ProductIdentifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FurnitureRequestValidationApiTest extends TestCase
{
    use RefreshDatabase;

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

    /** @return list<array{0: string}> */
    public static function unknownFieldProvider(): array
    {
        return array_map(
            static fn (string $field): array => [$field],
            ['message', 'style', 'product_details', 'user_id', 'request_status', 'request_reference', 'staff_internal_notes', 'order_id', 'payment_status', 'delivery_fee', 'quoted_price', 'attachment', 'region', 'subject', 'category', 'foo'],
        );
    }

    #[DataProvider('unknownFieldProvider')]
    public function test_unknown_and_server_controlled_fields_are_rejected(string $field): void
    {
        $response = $this->submit([...self::BASE, $field => 'x'])->assertStatus(422);

        $response->assertJsonPath('errors.0.code', 'INVALID_VALUE');
        $response->assertJsonPath('errors.0.field', $field);
        $this->assertDatabaseCount('furniture_requests', 0);
    }

    /** @return list<array{0: string, 1: mixed}> */
    public static function wrongTypeProvider(): array
    {
        return [
            ['name', 123],
            ['name', true],
            ['quantity', '2'],
            ['quantity', 1.5],
            ['phone', ['+255700000001']],
            ['email', true],
            ['product_id', 123],
            ['product_id', ['prod_1']],
            ['dimensions', 'large'],
            ['material', ['oak']],
            ['color', ['red']],
            ['notes', true],
        ];
    }

    #[DataProvider('wrongTypeProvider')]
    public function test_wrong_scalar_types_are_rejected_without_coercion(string $field, mixed $value): void
    {
        $response = $this->submit([...self::BASE, $field => $value])->assertStatus(422);

        $response->assertJsonPath('errors.0.code', 'INVALID_TYPE');
        $response->assertJsonPath('errors.0.field', $field);
        $this->assertDatabaseCount('furniture_requests', 0);
    }

    /** @return list<array{0: mixed, 1: int, 2: string|null}> */
    public static function quantityProvider(): array
    {
        return [
            [1, 201, null],
            [100, 201, null],
            [0, 422, 'INVALID_VALUE'],
            [101, 422, 'INVALID_VALUE'],
            [-1, 422, 'INVALID_VALUE'],
            [1.5, 422, 'INVALID_TYPE'],
            ['1', 422, 'INVALID_TYPE'],
            [true, 422, 'INVALID_TYPE'],
            [[], 422, 'INVALID_TYPE'],
        ];
    }

    #[DataProvider('quantityProvider')]
    public function test_quantity_boundaries(mixed $quantity, int $status, ?string $code): void
    {
        $response = $this->submit([...self::BASE, 'quantity' => $quantity])->assertStatus($status);

        if ($code !== null) {
            $response->assertJsonPath('errors.0.code', $code);
            $response->assertJsonPath('errors.0.field', 'quantity');
            $this->assertDatabaseCount('furniture_requests', 0);

            return;
        }

        $this->assertSame($quantity, FurnitureRequest::query()->sole()->quantity);
    }

    public function test_omitted_quantity_remains_null(): void
    {
        $this->submit(self::BASE)->assertStatus(201)->assertJsonPath('data.quantity', null);

        $this->assertNull(FurnitureRequest::query()->sole()->quantity);
    }

    /** @return list<array{0: array<string, mixed>, 1: int, 2: string|null}> */
    public static function contactMatrixProvider(): array
    {
        return [
            'phone only' => [['name' => 'Asha', 'phone' => '+255700000001'], 201, null],
            'email only' => [['name' => 'Asha', 'email' => 'asha@example.com'], 201, null],
            'phone and email' => [['name' => 'Asha', 'phone' => '+255700000001', 'email' => 'asha@example.com'], 201, null],
            'neither' => [['name' => 'Asha'], 422, 'MISSING_REQUIRED_FIELD'],
            'both null' => [['name' => 'Asha', 'phone' => null, 'email' => null], 422, 'MISSING_REQUIRED_FIELD'],
            'both blank' => [['name' => 'Asha', 'phone' => '', 'email' => ''], 422, 'MISSING_REQUIRED_FIELD'],
            'invalid phone with valid email' => [['name' => 'Asha', 'phone' => 'abc', 'email' => 'asha@example.com'], 422, 'INVALID_FORMAT'],
            'valid phone with invalid email' => [['name' => 'Asha', 'phone' => '+255700000001', 'email' => 'nope'], 422, 'INVALID_FORMAT'],
            'valid phone with blank email' => [['name' => 'Asha', 'phone' => '+255700000001', 'email' => ''], 201, null],
        ];
    }

    #[DataProvider('contactMatrixProvider')]
    public function test_contact_channel_matrix(array $payload, int $status, ?string $code): void
    {
        $response = $this->submit($payload)->assertStatus($status);

        if ($code === null) {
            return;
        }

        $response->assertJsonPath('errors.0.code', $code);
        $this->assertDatabaseCount('furniture_requests', 0);
    }

    public function test_missing_contact_reports_the_phone_field_deterministically(): void
    {
        $this->submit(['name' => 'Asha'])
            ->assertStatus(422)
            ->assertJsonPath('errors.0.code', 'MISSING_REQUIRED_FIELD')
            ->assertJsonPath('errors.0.field', 'phone');
    }

    /** @return list<array{0: mixed, 1: int, 2: string|null}> */
    public static function nameProvider(): array
    {
        return [
            ['A', 201, null],
            [str_repeat('a', 120), 201, null],
            [str_repeat('a', 121), 422, 'INVALID_VALUE'],
            ['', 422, 'MISSING_REQUIRED_FIELD'],
            ['   ', 422, 'MISSING_REQUIRED_FIELD'],
            [null, 422, 'MISSING_REQUIRED_FIELD'],
            [123, 422, 'INVALID_TYPE'],
        ];
    }

    #[DataProvider('nameProvider')]
    public function test_name_boundaries(mixed $name, int $status, ?string $code): void
    {
        $response = $this->submit([...self::BASE, 'name' => $name])->assertStatus($status);

        if ($code !== null) {
            $response->assertJsonPath('errors.0.code', $code);
            $response->assertJsonPath('errors.0.field', 'name');
        }
    }

    public function test_name_collapses_internal_whitespace_and_trims(): void
    {
        $this->submit([...self::BASE, 'name' => '   Asha    Mwangi   '])
            ->assertStatus(201)
            ->assertJsonPath('data.name', 'Asha Mwangi');

        $this->assertSame('Asha Mwangi', FurnitureRequest::query()->sole()->name);
    }

    public function test_name_preserves_unicode(): void
    {
        $this->submit([...self::BASE, 'name' => 'Nguyễn Văn Ánh'])
            ->assertStatus(201)
            ->assertJsonPath('data.name', 'Nguyễn Văn Ánh');
    }

    public function test_email_is_trimmed_and_lowercased(): void
    {
        $this->submit(['name' => 'Asha', 'email' => ' ASHA@Example.COM '])
            ->assertStatus(201)
            ->assertJsonPath('data.email', 'asha@example.com');

        $this->assertSame('asha@example.com', FurnitureRequest::query()->sole()->email);
    }

    public function test_phone_is_trimmed(): void
    {
        $this->submit(['name' => 'Asha', 'phone' => '  +255700000001  '])
            ->assertStatus(201)
            ->assertJsonPath('data.phone', '+255700000001');
    }

    /** @return list<array{0: string, 1: mixed, 2: int, 3: string|null}> */
    public static function freeTextProvider(): array
    {
        return [
            ['material', str_repeat('m', 500), 201, null],
            ['material', str_repeat('m', 501), 422, 'INVALID_VALUE'],
            ['color', str_repeat('c', 200), 201, null],
            ['color', str_repeat('c', 201), 422, 'INVALID_VALUE'],
            ['notes', str_repeat('n', 5000), 201, null],
            ['notes', str_repeat('n', 5001), 422, 'INVALID_VALUE'],
        ];
    }

    #[DataProvider('freeTextProvider')]
    public function test_free_text_bounds(string $field, mixed $value, int $status, ?string $code): void
    {
        $response = $this->submit([...self::BASE, $field => $value])->assertStatus($status);

        if ($code !== null) {
            $response->assertJsonPath('errors.0.code', $code);
            $response->assertJsonPath('errors.0.field', $field);
        }
    }

    public function test_blank_free_text_is_normalized_to_null(): void
    {
        $this->submit([...self::BASE, 'material' => '  ', 'color' => '', 'notes' => '   '])
            ->assertStatus(201);

        $request = FurnitureRequest::query()->sole();
        $this->assertNull($request->material);
        $this->assertNull($request->color);
        $this->assertNull($request->message);
    }

    public function test_notes_preserve_meaningful_newlines(): void
    {
        $this->submit([...self::BASE, 'notes' => "Line one\nLine two"])
            ->assertStatus(201)
            ->assertJsonPath('data.notes', "Line one\nLine two");
    }

    /** @return list<array{0: mixed, 1: int, 2: string|null, 3: string|null}> */
    public static function dimensionsProvider(): array
    {
        $full = ['length' => 220, 'width' => 90, 'height' => 85, 'unit' => 'cm'];

        return [
            'full valid' => [$full, 201, null, null],
            'decimal' => [['length' => 220.5, 'width' => 90.25, 'height' => 85, 'unit' => 'cm'], 201, null, null],
            'max edge' => [['length' => 10000, 'width' => 1, 'height' => 1, 'unit' => 'cm'], 201, null, null],
            'partial' => [['length' => 220, 'unit' => 'cm'], 201, null, null],
            'null member skipped' => [['length' => null, 'width' => 90, 'unit' => 'cm'], 201, null, null],
            'null' => [null, 201, null, null],
            'zero' => [['length' => 0, 'width' => 90, 'height' => 85, 'unit' => 'cm'], 422, 'INVALID_VALUE', 'dimensions.length'],
            'negative' => [['length' => -1, 'width' => 90, 'height' => 85, 'unit' => 'cm'], 422, 'INVALID_VALUE', 'dimensions.length'],
            'over max' => [['length' => 10000.1, 'width' => 90, 'height' => 85, 'unit' => 'cm'], 422, 'INVALID_VALUE', 'dimensions.length'],
            'numeric string' => [['length' => '220', 'width' => 90, 'height' => 85, 'unit' => 'cm'], 422, 'INVALID_TYPE', 'dimensions.length'],
            'missing unit' => [['length' => 220], 422, 'MISSING_REQUIRED_FIELD', 'dimensions.unit'],
            'unit uppercase' => [['length' => 220, 'unit' => 'CM'], 422, 'INVALID_VALUE', 'dimensions.unit'],
            'unit inch' => [['length' => 220, 'unit' => 'inch'], 422, 'INVALID_VALUE', 'dimensions.unit'],
            'unknown key' => [['length' => 220, 'unit' => 'cm', 'depth' => 60], 422, 'INVALID_VALUE', 'dimensions.depth'],
            'string' => ['large', 422, 'INVALID_TYPE', 'dimensions'],
            'list' => [[220, 90], 422, 'INVALID_TYPE', 'dimensions'],
            'unit only' => [['unit' => 'cm'], 422, 'INVALID_VALUE', 'dimensions'],
            'null only' => [['length' => null, 'unit' => 'cm'], 422, 'INVALID_VALUE', 'dimensions'],
        ];
    }

    #[DataProvider('dimensionsProvider')]
    public function test_dimensions_boundaries(mixed $dimensions, int $status, ?string $code, ?string $field): void
    {
        $response = $this->submit([...self::BASE, 'dimensions' => $dimensions])->assertStatus($status);

        if ($code === null) {
            return;
        }

        $response->assertJsonPath('errors.0.code', $code);
        $response->assertJsonPath('errors.0.field', $field);
        $this->assertDatabaseCount('furniture_requests', 0);
    }

    public function test_decimal_dimensions_are_persisted_without_loss(): void
    {
        $this->submit([...self::BASE, 'dimensions' => ['length' => 220.5, 'unit' => 'cm']])->assertStatus(201);

        $dimensions = FurnitureRequest::query()->sole()->dimensions;
        $this->assertSame('cm', $dimensions['unit']);
        $this->assertSame(220.5, $dimensions['length']);
    }

    /** @return list<array{0: mixed, 1: int, 2: string|null}> */
    public static function productIdProvider(): array
    {
        return [
            'null' => [null, 201, null],
            'integer' => [123, 422, 'INVALID_TYPE'],
            'array' => [[123], 422, 'INVALID_TYPE'],
            'malformed' => ['not-a-product', 422, 'INVALID_FORMAT'],
            'empty reference' => ['prod_', 422, 'INVALID_FORMAT'],
            'no prefix' => ['123', 422, 'INVALID_FORMAT'],
        ];
    }

    #[DataProvider('productIdProvider')]
    public function test_product_id_structural_validation(mixed $productId, int $status, ?string $code): void
    {
        $response = $this->submit([...self::BASE, 'product_id' => $productId])->assertStatus($status);

        if ($code !== null) {
            $response->assertJsonPath('errors.0.code', $code);
            $response->assertJsonPath('errors.0.field', 'product_id');
        }
    }

    public function test_omitted_product_id_is_a_valid_custom_request(): void
    {
        $this->submit(self::BASE)
            ->assertStatus(201)
            ->assertJsonPath('data.product_id', null)
            ->assertJsonPath('data.product', null);
    }

    public function test_structurally_valid_product_reference_is_accepted(): void
    {
        $product = Product::factory()->madeToOrder()->create();

        $this->submit([...self::BASE, 'product_id' => ProductIdentifier::encode($product)])
            ->assertStatus(201)
            ->assertJsonPath('data.product_id', ProductIdentifier::encode($product));

        $this->assertSame($product->id, FurnitureRequest::query()->sole()->product_id);
    }

    public function test_missing_product_reference_is_rejected_without_a_server_error(): void
    {
        $response = $this->submit([...self::BASE, 'product_id' => ProductIdentifier::encodeId(999999)])
            ->assertStatus(404);

        $response->assertJsonPath('errors.0.code', 'RESOURCE_NOT_FOUND');
        $response->assertJsonPath('errors.0.field', 'product_id');
        $this->assertDatabaseCount('furniture_requests', 0);
    }

    public function test_soft_deleted_product_reference_is_rejected(): void
    {
        $product = Product::factory()->madeToOrder()->create();
        $product->delete();

        $this->submit([...self::BASE, 'product_id' => ProductIdentifier::encode($product)])
            ->assertStatus(404)
            ->assertJsonPath('errors.0.code', 'RESOURCE_NOT_FOUND');

        $this->assertDatabaseCount('furniture_requests', 0);
    }

    public function test_authenticated_customer_gets_no_contact_fallback(): void
    {
        $customer = User::factory()->customer()->create([
            'clerk_user_id' => 'req_validation_customer',
            'name' => 'Profile Name',
            'phone' => '+255700000099',
            'email' => 'profile@example.com',
        ]);

        $this->submit(['name' => 'Explicit Name'], $this->authenticateAs($customer))
            ->assertStatus(422)
            ->assertJsonPath('errors.0.code', 'MISSING_REQUIRED_FIELD')
            ->assertJsonPath('errors.0.field', 'phone');

        $this->assertDatabaseCount('furniture_requests', 0);
    }

    public function test_invalid_bearer_is_rejected_before_validation(): void
    {
        $this->mock(ClerkTokenVerifier::class)
            ->shouldReceive('verify')
            ->andThrow(ClerkAuthenticationFailure::invalid());

        $this->submit(['quantity' => 'not-an-integer'], ['Authorization' => 'Bearer invalid-token'])
            ->assertStatus(401)
            ->assertJsonPath('errors.0.code', 'INVALID_AUTHENTICATION');
    }

    public function test_staff_and_admin_are_forbidden(): void
    {
        $staff = User::factory()->staff()->create(['clerk_user_id' => 'req_validation_staff']);
        $admin = User::factory()->admin()->create(['clerk_user_id' => 'req_validation_admin']);

        foreach ([$staff, $admin] as $actor) {
            $this->submit(self::BASE, $this->authenticateAs($actor))
                ->assertStatus(403)
                ->assertJsonPath('errors.0.code', 'FORBIDDEN');
        }
    }

    public function test_validation_failure_has_zero_persistence_and_commerce_side_effects(): void
    {
        $this->submit(['name' => 'Asha', 'quantity' => 'nope'])->assertStatus(422);

        $this->assertSame(0, FurnitureRequest::query()->count());
        $this->assertSame(0, Order::query()->count());
        $this->assertSame(0, Payment::query()->count());
    }

    public function test_duplicate_valid_submissions_are_both_accepted(): void
    {
        $this->submit(self::BASE)->assertStatus(201);
        $this->submit(self::BASE)->assertStatus(201);

        $this->assertSame(2, FurnitureRequest::query()->count());
    }

    public function test_no_idempotency_key_is_required(): void
    {
        $this->submit(self::BASE)->assertStatus(201);

        $this->assertDatabaseCount('furniture_requests', 1);
    }

    public function test_normalized_response_excludes_internal_fields(): void
    {
        $response = $this->submit([
            'name' => '  Asha   Mwangi ',
            'email' => ' ASHA@Example.COM ',
            'notes' => '  Custom request  ',
        ])->assertStatus(201);

        $response->assertJsonPath('data.name', 'Asha Mwangi')
            ->assertJsonPath('data.email', 'asha@example.com')
            ->assertJsonPath('data.notes', 'Custom request')
            ->assertJsonMissingPath('data.message')
            ->assertJsonMissingPath('data.style')
            ->assertJsonMissingPath('data.product_details')
            ->assertJsonMissingPath('data.staff_internal_notes')
            ->assertJsonMissingPath('data.user_id');
    }

    public function test_route_is_still_gated_by_default(): void
    {
        config(['requests.route_enabled' => false]);

        $this->submit(self::BASE)->assertStatus(501)->assertJsonPath('status', 'not_implemented');
        $this->assertDatabaseCount('furniture_requests', 0);
    }

    private function submit(array $payload, array $headers = []): TestResponse
    {
        RateLimiter::clear('ip:127.0.0.1');

        return $this->withHeaders($headers)->postJson(self::URL, $payload);
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
