<?php

namespace Tests\Feature;

use App\Exceptions\InvalidRequestStatusTransition;
use App\Models\Cart;
use App\Models\FurnitureRequest;
use App\Models\Notification;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\Requests\TransitionFurnitureRequestStatus;
use App\Support\ApiErrorCode;
use App\Support\RequestStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FurnitureRequestStatusTransitionTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_forward_transitions_persist_status(): void
    {
        $request = FurnitureRequest::factory()->create();

        $this->assertSame(
            RequestStatus::IN_REVIEW,
            $this->service()->transition($request, RequestStatus::IN_REVIEW)->request_status,
        );
        $this->assertSame(RequestStatus::IN_REVIEW, $request->fresh()->request_status);

        $this->assertSame(
            RequestStatus::CLOSED,
            $this->service()->transition($request->fresh(), RequestStatus::CLOSED)->request_status,
        );
        $this->assertSame(RequestStatus::CLOSED, $request->fresh()->request_status);
    }

    public function test_direct_close_from_submitted_is_allowed(): void
    {
        $request = FurnitureRequest::factory()->create();

        $result = $this->service()->transition($request, RequestStatus::CLOSED);

        $this->assertSame(RequestStatus::CLOSED, $result->request_status);
        $this->assertSame(RequestStatus::CLOSED, $request->fresh()->request_status);
    }

    public function test_real_transition_advances_updated_at(): void
    {
        $request = FurnitureRequest::factory()->create();
        $originalUpdatedAt = $request->updated_at;

        $this->travel(5)->minutes();

        $result = $this->service()->transition($request, RequestStatus::IN_REVIEW);

        $this->assertTrue($result->updated_at->greaterThan($originalUpdatedAt));
        $this->assertSame($request->created_at->toISOString(), $result->created_at->toISOString());
    }

    public function test_backward_transition_is_rejected_without_mutation(): void
    {
        $request = FurnitureRequest::factory()->inReview()->create();

        try {
            $this->service()->transition($request, RequestStatus::SUBMITTED);
            $this->fail('Expected InvalidRequestStatusTransition.');
        } catch (InvalidRequestStatusTransition $exception) {
            $this->assertSame(ApiErrorCode::CONFLICT, $exception->errorCode());
            $this->assertSame(409, $exception->status());
            $this->assertSame('request_status', $exception->field());
        }

        $this->assertSame(RequestStatus::IN_REVIEW, $request->fresh()->request_status);
    }

    public function test_closed_is_terminal_for_both_other_statuses(): void
    {
        foreach ([RequestStatus::SUBMITTED, RequestStatus::IN_REVIEW] as $target) {
            $request = FurnitureRequest::factory()->closed()->create();

            try {
                $this->service()->transition($request, $target);
                $this->fail("Expected CLOSED → {$target->value} to be rejected.");
            } catch (InvalidRequestStatusTransition) {
                // expected
            }

            $this->assertSame(RequestStatus::CLOSED, $request->fresh()->request_status);
        }
    }

    public function test_same_state_transition_is_an_idempotent_no_op(): void
    {
        foreach ([RequestStatus::SUBMITTED, RequestStatus::IN_REVIEW, RequestStatus::CLOSED] as $status) {
            $request = $this->requestInStatus($status)->fresh();
            $originalUpdatedAt = $request->updated_at;

            $this->travel(5)->minutes();

            $result = $this->service()->transition($request, $status);

            $this->assertSame($status, $result->request_status);
            $this->assertTrue($result->updated_at->equalTo($originalUpdatedAt));
            $this->assertTrue($request->fresh()->updated_at->equalTo($originalUpdatedAt));
        }
    }

    public function test_repeated_target_is_only_applied_once(): void
    {
        $request = FurnitureRequest::factory()->create();

        $first = $this->service()->transition($request, RequestStatus::IN_REVIEW);
        $firstUpdatedAt = $first->fresh()->updated_at;

        $this->travel(5)->minutes();

        $second = $this->service()->transition($request->fresh(), RequestStatus::IN_REVIEW);

        $this->assertSame(RequestStatus::IN_REVIEW, $second->request_status);
        $this->assertTrue($second->updated_at->equalTo($firstUpdatedAt));
        $this->assertSame(1, FurnitureRequest::query()->count());
    }

    public function test_transition_re_reads_current_database_state_not_a_stale_instance(): void
    {
        $request = FurnitureRequest::factory()->create();
        $stale = $request->fresh();

        $this->service()->transition($request, RequestStatus::CLOSED);

        try {
            $this->service()->transition($stale, RequestStatus::IN_REVIEW);
            $this->fail('Expected stale IN_REVIEW writer to conflict.');
        } catch (InvalidRequestStatusTransition) {
            // expected
        }

        $this->assertSame(RequestStatus::CLOSED, $request->fresh()->request_status);
    }

    public function test_transition_does_not_mutate_intake_data(): void
    {
        $product = Product::factory()->madeToOrder()->create();

        $request = FurnitureRequest::factory()->byUser($this->customer())->forProduct($product)->create([
            'quantity' => 3,
            'dimensions' => ['length' => 220, 'unit' => 'cm'],
            'material' => 'Oak',
            'color' => 'Natural',
            'message' => 'Custom request',
            'name' => 'Asha Mwangi',
            'email' => 'asha@example.com',
            'phone' => '+255700000001',
        ]);

        $snapshot = $request->only([
            'user_id',
            'request_reference',
            'product_id',
            'product_details',
            'style',
            'name',
            'email',
            'phone',
            'message',
            'quantity',
            'dimensions',
            'material',
            'color',
        ]);

        $this->service()->transition($request, RequestStatus::IN_REVIEW);

        $fresh = $request->fresh();

        foreach ($snapshot as $field => $value) {
            $this->assertSame($value, $fresh->{$field}, "Intake field {$field} must be immutable.");
        }
    }

    public function test_transition_has_no_commerce_or_notification_side_effects(): void
    {
        $product = Product::factory()->madeToOrder()->create();
        $variant = ProductVariant::factory()->create(['product_id' => $product->id]);
        $stock = ProductStock::factory()->forVariant($variant)->create(['quantity' => 5, 'reserved_quantity' => 1]);

        $request = FurnitureRequest::factory()->forProduct($product)->create();

        $before = [
            Order::query()->count(),
            Payment::query()->count(),
            Cart::query()->count(),
            Notification::query()->count(),
        ];

        $this->service()->transition($request, RequestStatus::CLOSED);

        $this->assertSame($before, [
            Order::query()->count(),
            Payment::query()->count(),
            Cart::query()->count(),
            Notification::query()->count(),
        ]);

        $freshStock = $stock->fresh();
        $this->assertSame(5, $freshStock->quantity);
        $this->assertSame(1, $freshStock->reserved_quantity);
    }

    public function test_created_request_still_defaults_to_submitted(): void
    {
        $request = FurnitureRequest::factory()->create();

        $this->assertSame(RequestStatus::SUBMITTED, $request->request_status);
    }

    public function test_status_transition_does_not_revalidate_live_product_visibility(): void
    {
        $product = Product::factory()->madeToOrder()->create();
        $request = FurnitureRequest::factory()->forProduct($product)->create();

        $product->forceFill(['is_published' => false])->save();
        $this->assertFalse($product->fresh()->is_published);

        $result = $this->service()->transition($request, RequestStatus::IN_REVIEW);

        $this->assertSame(RequestStatus::IN_REVIEW, $result->request_status);
    }

    private function service(): TransitionFurnitureRequestStatus
    {
        return app(TransitionFurnitureRequestStatus::class);
    }

    private function requestInStatus(RequestStatus $status): FurnitureRequest
    {
        return FurnitureRequest::factory()->create(['request_status' => $status]);
    }

    private function customer(): User
    {
        return User::factory()->customer()->create();
    }
}
