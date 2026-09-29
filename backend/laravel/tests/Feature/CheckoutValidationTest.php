<?php

namespace Tests\Feature;

use App\Exceptions\DeliveryCheckoutUnsupportedException;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\OrderItemInventoryAllocation;
use App\Models\OrderStatusHistory;
use App\Models\User;
use App\Support\CartStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\CartTestSupport;
use Tests\TestCase;

class CheckoutValidationTest extends TestCase
{
    use CartTestSupport;
    use RefreshDatabase;

    private const URL = '/api/v1/checkout';

    protected function setUp(): void
    {
        parent::setUp();
        config(['checkout.route_enabled' => true]);
    }

    public function test_valid_pickup_checkout_returns_the_frozen_201_shape_and_commits(): void
    {
        [$product, $variant] = $this->stockedProduct(quantity: 10, price: 25_000);
        $customer = $this->cartCustomer();
        $cart = $this->activeCartFor($customer);
        $this->itemFor($cart, $product, $variant, 4);

        $response = $this->attempt($customer, ['fulfillment_type' => 'PICKUP'])->assertStatus(201);

        $response->assertJsonPath('data.status', 'PENDING_PAYMENT')
            ->assertJsonPath('data.fulfillment_type', 'PICKUP')
            ->assertJsonPath('data.delivery_address', null)
            ->assertJsonPath('data.subtotal.amount', 100_000)
            ->assertJsonPath('data.subtotal.currency', 'TZS')
            ->assertJsonPath('data.delivery_fee.amount', 0)
            ->assertJsonPath('data.delivery_fee_status', 'FINALIZED')
            ->assertJsonPath('data.total.amount', 100_000)
            ->assertJsonPath('data.currency', 'TZS')
            ->assertJsonPath('data.payment', null);

        $this->assertStringStartsWith('ord_', (string) $response->json('data.order_id'));
        $this->assertMatchesRegularExpression('/^OD-[A-Z0-9]{5}$/', (string) $response->json('data.order_reference'));

        $response->assertHeaderContains('Cache-Control', 'private');
        $response->assertHeaderContains('Cache-Control', 'no-store');
        $response->assertHeaderContains('Vary', 'Authorization');

        $this->assertSame(1, Order::query()->count());
        $this->assertSame(4, OrderItemInventoryAllocation::query()->sole()->quantity);
        $this->assertSame(1, OrderStatusHistory::query()->count());
        $this->assertSame(CartStatus::ACTIVE, $cart->fresh()->status);
        $this->assertSame(0, $cart->fresh()->items()->count());
    }

    public function test_route_is_gated_by_default_even_for_an_invalid_body(): void
    {
        config(['checkout.route_enabled' => false]);
        [$product, $variant] = $this->stockedProduct(quantity: 10);
        $customer = $this->cartCustomer();
        $cart = $this->activeCartFor($customer);
        $this->itemFor($cart, $product, $variant, 1);

        RateLimiter::clear($this->checkoutKey($customer));

        $this->withHeaders($this->authenticateAs($customer))
            ->postJson(self::URL, [])
            ->assertStatus(501);

        $this->assertSame(0, Order::query()->count());
    }

    public function test_anonymous_checkout_is_rejected_before_validation(): void
    {
        $this->postJson(self::URL, ['fulfillment_type' => 'PICKUP'])
            ->assertStatus(401)
            ->assertJsonPath('errors.0.code', 'AUTHENTICATION_REQUIRED');
    }

    public function test_staff_and_admin_are_forbidden(): void
    {
        $staff = User::factory()->staff()->create(['clerk_user_id' => 'checkout_staff']);
        $admin = User::factory()->admin()->create(['clerk_user_id' => 'checkout_admin']);

        foreach ([$staff, $admin] as $actor) {
            $this->attempt($actor, ['fulfillment_type' => 'PICKUP'])
                ->assertStatus(403)
                ->assertJsonPath('errors.0.code', 'FORBIDDEN');
        }
    }

    public function test_suspended_customer_is_rejected(): void
    {
        $customer = User::factory()->customer()->create([
            'clerk_user_id' => 'checkout_suspended',
            'account_state' => 'SUSPENDED',
        ]);

        $this->attempt($customer, ['fulfillment_type' => 'PICKUP'])->assertStatus(403);
    }

    public function test_missing_idempotency_key_is_reported_before_body_validation(): void
    {
        [$product, $variant] = $this->stockedProduct(quantity: 10);
        $customer = $this->cartCustomer();
        $cart = $this->activeCartFor($customer);
        $this->itemFor($cart, $product, $variant, 1);

        RateLimiter::clear($this->checkoutKey($customer));

        $this->withHeaders($this->authenticateAs($customer))
            ->postJson(self::URL, [])
            ->assertStatus(422)
            ->assertJsonPath('errors.0.code', 'MISSING_REQUIRED_FIELD')
            ->assertJsonPath('errors.0.field', 'Idempotency-Key');
    }

    public function test_missing_and_malformed_idempotency_key_are_rejected(): void
    {
        [$product, $variant] = $this->stockedProduct(quantity: 10);
        $customer = $this->cartCustomer();
        $cart = $this->activeCartFor($customer);
        $this->itemFor($cart, $product, $variant, 1);
        $headers = $this->authenticateAs($customer);

        RateLimiter::clear($this->checkoutKey($customer));

        $this->withHeaders($headers)->postJson(self::URL, ['fulfillment_type' => 'PICKUP'])
            ->assertStatus(422)
            ->assertJsonPath('errors.0.code', 'MISSING_REQUIRED_FIELD')
            ->assertJsonPath('errors.0.field', 'Idempotency-Key');

        RateLimiter::clear($this->checkoutKey($customer));

        $this->withHeaders($headers + ['Idempotency-Key' => 'not-a-uuid'])->postJson(self::URL, ['fulfillment_type' => 'PICKUP'])
            ->assertStatus(422)
            ->assertJsonPath('errors.0.code', 'INVALID_FORMAT')
            ->assertJsonPath('errors.0.field', 'Idempotency-Key');

        $this->assertSame(0, Order::query()->count());
    }

    public function test_fulfillment_type_is_required_valid_string_and_closed_enum(): void
    {
        [$product, $variant] = $this->stockedProduct(quantity: 10);
        $customer = $this->cartCustomer();
        $cart = $this->activeCartFor($customer);
        $this->itemFor($cart, $product, $variant, 1);

        $this->attempt($customer, [])->assertStatus(422)->assertJsonPath('errors.0.code', 'MISSING_REQUIRED_FIELD');

        $this->attempt($customer, ['fulfillment_type' => 12])->assertStatus(422)->assertJsonPath('errors.0.code', 'INVALID_TYPE');

        foreach (['pickup', 'delivery', 'SHIPPING', 'SELF_PICKUP'] as $value) {
            $this->attempt($customer, ['fulfillment_type' => $value])->assertStatus(422)->assertJsonPath('errors.0.code', 'INVALID_VALUE');
        }

        $this->assertSame(0, Order::query()->count());
    }

    public function test_pickup_address_rules(): void
    {
        [$product, $variant] = $this->stockedProduct(quantity: 10);
        $customer = $this->cartCustomer();
        $cart = $this->activeCartFor($customer);
        $this->itemFor($cart, $product, $variant, 1);

        $key = (string) Str::uuid();
        $this->attempt($customer, ['fulfillment_type' => 'PICKUP'], $key)->assertStatus(201);
        $this->attempt($customer, ['fulfillment_type' => 'PICKUP', 'delivery_address' => null], $key)->assertStatus(201);

        $this->assertSame(1, Order::query()->count());

        [$secondProduct, $secondVariant] = $this->stockedProduct(quantity: 10);
        $second = $this->cartCustomer('pickup_address');
        $secondCart = $this->activeCartFor($second);
        $this->itemFor($secondCart, $secondProduct, $secondVariant, 1);

        $this->attempt($second, [
            'fulfillment_type' => 'PICKUP',
            'delivery_address' => ['recipient_name' => 'Asha', 'phone' => '+255700000001', 'address_line' => 'Street 12', 'city' => 'Dar es Salaam'],
        ])->assertStatus(422)->assertJsonPath('errors.0.code', 'INVALID_FULFILLMENT');

        $this->attempt($second, ['fulfillment_type' => 'PICKUP', 'delivery_address' => []])
            ->assertStatus(422)->assertJsonPath('errors.0.code', 'INVALID_FULFILLMENT');

        $this->attempt($second, ['fulfillment_type' => 'PICKUP', 'delivery_address' => 'Street'])
            ->assertStatus(422)->assertJsonPath('errors.0.code', 'INVALID_TYPE');
    }

    public function test_delivery_address_container_is_required_and_must_be_an_object(): void
    {
        [$product, $variant] = $this->stockedProduct(quantity: 10);
        $customer = $this->cartCustomer();
        $cart = $this->activeCartFor($customer);
        $this->itemFor($cart, $product, $variant, 1);

        $this->attempt($customer, ['fulfillment_type' => 'DELIVERY'])
            ->assertStatus(422)->assertJsonPath('errors.0.code', 'MISSING_REQUIRED_FIELD');

        $this->attempt($customer, ['fulfillment_type' => 'DELIVERY', 'delivery_address' => null])
            ->assertStatus(422)->assertJsonPath('errors.0.code', 'MISSING_REQUIRED_FIELD');

        foreach (['Street', 12, true, [1, 2]] as $value) {
            $this->attempt($customer, ['fulfillment_type' => 'DELIVERY', 'delivery_address' => $value])
                ->assertStatus(422)->assertJsonPath('errors.0.code', 'INVALID_TYPE');
        }
    }

    public function test_delivery_required_fields_and_types_are_validated(): void
    {
        [$product, $variant] = $this->stockedProduct(quantity: 10);
        $customer = $this->cartCustomer();
        $cart = $this->activeCartFor($customer);
        $this->itemFor($cart, $product, $variant, 1);

        $address = ['recipient_name' => 'Asha Mwangi', 'phone' => '+255700000001', 'address_line' => 'Street 12', 'city' => 'Dar es Salaam'];

        foreach (array_keys($address) as $missing) {
            $partial = $address;
            unset($partial[$missing]);

            $this->attempt($customer, ['fulfillment_type' => 'DELIVERY', 'delivery_address' => $partial])
                ->assertStatus(422)->assertJsonPath('errors.0.code', 'INVALID_DELIVERY_INFORMATION');
        }

        foreach (['recipient_name', 'address_line', 'city'] as $field) {
            $this->attempt($customer, ['fulfillment_type' => 'DELIVERY', 'delivery_address' => [...$address, $field => 12]])
                ->assertStatus(422)->assertJsonPath('errors.0.code', 'INVALID_TYPE');
        }

        foreach (['recipient_name', 'address_line', 'city'] as $field) {
            $this->attempt($customer, ['fulfillment_type' => 'DELIVERY', 'delivery_address' => [...$address, $field => '   ']])
                ->assertStatus(422)->assertJsonPath('errors.0.code', 'INVALID_TYPE');
        }

        $this->attempt($customer, ['fulfillment_type' => 'DELIVERY', 'delivery_address' => [...$address, 'phone' => 'not-a-phone']])
            ->assertStatus(422)->assertJsonPath('errors.0.code', 'INVALID_DELIVERY_INFORMATION');

        $this->assertSame(0, Order::query()->count());
    }

    public function test_region_and_unknown_nested_fields_are_rejected(): void
    {
        [$product, $variant] = $this->stockedProduct(quantity: 10);
        $customer = $this->cartCustomer();
        $cart = $this->activeCartFor($customer);
        $this->itemFor($cart, $product, $variant, 1);

        $address = ['recipient_name' => 'Asha', 'phone' => '+255700000001', 'address_line' => 'Street', 'city' => 'Dar es Salaam'];

        foreach (['region', 'country', 'postal_code', 'district', 'ward', 'latitude', 'longitude', 'instructions', 'saved_address_id'] as $field) {
            $this->attempt($customer, ['fulfillment_type' => 'DELIVERY', 'delivery_address' => [...$address, $field => 'x']])
                ->assertStatus(422);
        }

        $this->assertSame(0, Order::query()->count());
    }

    public function test_unknown_top_level_and_server_controlled_fields_are_rejected(): void
    {
        [$product, $variant] = $this->stockedProduct(quantity: 10);
        $customer = $this->cartCustomer();
        $cart = $this->activeCartFor($customer);
        $this->itemFor($cart, $product, $variant, 1);

        foreach ([
            'cart_id' => 'cart_x', 'user_id' => 1, 'customer_id' => 1, 'guest_cart_id' => 'x',
            'subtotal' => 1, 'total' => 1, 'currency' => 'TZS', 'delivery_fee' => 1,
            'delivery_fee_status' => 'PENDING', 'status' => 'PAID', 'payment' => [],
            'billing_address' => [], 'saved_address_id' => 'x', 'order_reference' => 'OD-ABCDE',
        ] as $field => $value) {
            $this->attempt($customer, ['fulfillment_type' => 'PICKUP', $field => $value])
                ->assertStatus(422)->assertJsonPath('errors.0.code', 'INVALID_VALUE');
        }

        $this->assertSame(0, Order::query()->count());
    }

    public function test_replay_and_changed_intent_through_the_controller(): void
    {
        [$product, $variant] = $this->stockedProduct(quantity: 10);
        $customer = $this->cartCustomer();
        $cart = $this->activeCartFor($customer);
        $this->itemFor($cart, $product, $variant, 1);

        $key = (string) Str::uuid();

        $first = $this->attempt($customer, ['fulfillment_type' => 'PICKUP'], $key)->assertStatus(201);
        $second = $this->attempt($customer, ['fulfillment_type' => 'PICKUP'], $key)->assertStatus(201);

        $this->assertSame($first->json('data.order_reference'), $second->json('data.order_reference'));
        $this->assertSame(1, Order::query()->count());

        $this->attempt($customer, [
            'fulfillment_type' => 'DELIVERY',
            'delivery_address' => ['recipient_name' => 'Asha', 'phone' => '+255700000001', 'address_line' => 'Street', 'city' => 'Dar es Salaam'],
        ], $key)->assertStatus(409)->assertJsonPath('errors.0.code', 'DUPLICATE_OPERATION');
    }

    public function test_valid_delivery_reaches_the_internal_blocker_without_mutation(): void
    {
        [$product, $variant] = $this->stockedProduct(quantity: 10);
        $customer = $this->cartCustomer();
        $cart = $this->activeCartFor($customer);
        $this->itemFor($cart, $product, $variant, 1);

        $this->withoutExceptionHandling();

        try {
            $this->attempt($customer, [
                'fulfillment_type' => 'DELIVERY',
                'delivery_address' => ['recipient_name' => 'Asha', 'phone' => '+255700000001', 'address_line' => 'Street', 'city' => 'Dar es Salaam'],
            ]);
            $this->fail('Expected the DELIVERY persistence blocker.');
        } catch (DeliveryCheckoutUnsupportedException) {
            // expected
        }

        $this->assertSame(0, Order::query()->count());
        $this->assertSame(1, CartItem::query()->where('cart_id', $cart->id)->count());
        $this->assertSame(0, $variant->fresh()->stocks->first()->reserved_quantity);
    }

    public function test_query_parameters_cannot_supply_business_input(): void
    {
        [$product, $variant] = $this->stockedProduct(quantity: 10);
        $customer = $this->cartCustomer();
        $cart = $this->activeCartFor($customer);
        $this->itemFor($cart, $product, $variant, 1);

        RateLimiter::clear($this->checkoutKey($customer));

        $this->withHeaders($this->authenticateAs($customer) + ['Idempotency-Key' => (string) Str::uuid()])
            ->postJson(self::URL.'?fulfillment_type=PICKUP', [])
            ->assertStatus(422)
            ->assertJsonPath('errors.0.code', 'INVALID_VALUE')
            ->assertJsonPath('errors.0.field', 'fulfillment_type');

        $this->assertSame(0, Order::query()->count());
    }

    public function test_empty_property_name_does_not_bypass_the_allow_list(): void
    {
        [$product, $variant] = $this->stockedProduct(quantity: 10);
        $customer = $this->cartCustomer();
        $cart = $this->activeCartFor($customer);
        $this->itemFor($cart, $product, $variant, 1);

        $this->attempt($customer, ['' => 'x', 'fulfillment_type' => 'PICKUP'])
            ->assertStatus(422)->assertJsonPath('errors.0.code', 'INVALID_VALUE');

        $this->attempt($customer, ['' => 'x', 'total' => 1, 'fulfillment_type' => 'PICKUP'])
            ->assertStatus(422)->assertJsonPath('errors.0.code', 'INVALID_VALUE');

        $this->assertSame(0, Order::query()->count());
    }

    public function test_form_encoded_checkout_is_rejected(): void
    {
        [$product, $variant] = $this->stockedProduct(quantity: 10);
        $customer = $this->cartCustomer();
        $cart = $this->activeCartFor($customer);
        $this->itemFor($cart, $product, $variant, 1);

        RateLimiter::clear($this->checkoutKey($customer));

        $this->withHeaders($this->authenticateAs($customer) + ['Idempotency-Key' => (string) Str::uuid()])
            ->post(self::URL, ['fulfillment_type' => 'PICKUP'])
            ->assertStatus(415)
            ->assertJsonPath('errors.0.code', 'UNSUPPORTED_MEDIA_TYPE');

        $this->assertSame(0, Order::query()->count());
    }

    public function test_invalid_requests_have_no_side_effects(): void
    {
        [$product, $variant] = $this->stockedProduct(quantity: 10);
        $customer = $this->cartCustomer();
        $cart = $this->activeCartFor($customer);
        $this->itemFor($cart, $product, $variant, 2);

        $this->attempt($customer, ['fulfillment_type' => 'SHIPPING'])->assertStatus(422);

        $this->assertSame(0, Order::query()->count());
        $this->assertSame(1, $cart->fresh()->items()->count());
        $this->assertSame(0, $variant->fresh()->stocks->first()->reserved_quantity);
        $this->assertSame(0, OrderItemInventoryAllocation::query()->count());
        $this->assertSame(0, OrderStatusHistory::query()->count());
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function attempt(User $customer, array $payload, ?string $key = null): TestResponse
    {
        RateLimiter::clear($this->checkoutKey($customer));

        return $this->withHeaders($this->authenticateAs($customer) + ['Idempotency-Key' => $key ?? (string) Str::uuid()])
            ->postJson(self::URL, $payload);
    }

    private function checkoutKey(User $customer): string
    {
        return md5('checkoutuser:'.$customer->getAuthIdentifier());
    }
}
