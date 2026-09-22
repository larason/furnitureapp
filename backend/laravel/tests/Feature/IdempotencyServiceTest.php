<?php

namespace Tests\Feature;

use App\Models\ProductStock;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\IdempotencyService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class IdempotencyServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_operation_unique_violation_is_not_treated_as_an_idempotency_conflict(): void
    {
        $actor = User::factory()->staff()->create(['clerk_user_id' => 'idempotency_staff']);
        $variant = ProductVariant::factory()->create();
        ProductStock::factory()->forVariant($variant)->atLocation('main')->create();

        $this->expectException(UniqueConstraintViolationException::class);

        app(IdempotencyService::class)->execute(
            $actor,
            'INV-003',
            (string) Str::uuid(),
            ['probe' => true],
            function () use ($variant): array {
                ProductStock::factory()->forVariant($variant)->atLocation('main')->create();

                return [];
            },
        );
    }
}
