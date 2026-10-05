<?php

namespace Tests\Integration;

use App\Exceptions\InvalidRequestStatusTransition;
use App\Models\AuditEvent;
use App\Models\FurnitureRequest;
use App\Models\User;
use App\Services\Requests\FurnitureRequestOperationalUpdate;
use App\Services\Requests\UpdateFurnitureRequestOperationalFields;
use App\Support\FurnitureRequestIdentifier;
use App\Support\RequestStatus;
use Database\Seeders\RbacSeeder;
use Tests\Support\RunsConcurrentWorkers;
use Tests\Support\UsesDisposableMysqlDatabase;
use Tests\TestCase;

/**
 * Real MariaDB/MySQL concurrency verification for the REQ-006 status race.
 * Not part of the default suite; run explicitly against the disposable DB:
 *
 *   REQUEST_STATUS_MYSQL_TEST_DATABASE=furnitureapp_test_disposable \
 *   vendor/bin/phpunit tests/Integration/FurnitureRequestStatusConcurrencyMysqlTest.php
 */
class FurnitureRequestStatusConcurrencyMysqlTest extends TestCase
{
    use RunsConcurrentWorkers;
    use UsesDisposableMysqlDatabase;

    private const RACES = 10;

    protected function databaseConnectionName(): string
    {
        return 'mysql_request_status';
    }

    protected function databaseEnvironmentVariable(): string
    {
        return 'REQUEST_STATUS_MYSQL_TEST_DATABASE';
    }

    protected function seedDisposableMysqlDatabase(): void
    {
        $this->seed(RbacSeeder::class);
    }

    public function test_concurrent_close_and_review_can_never_reopen_a_closed_request(): void
    {
        for ($iteration = 0; $iteration < self::RACES; $iteration++) {
            $request = FurnitureRequest::factory()->create();
            $actor = User::factory()->staff()->create();

            $results = $this->runConcurrentWorkers(
                fn (): string => $this->attempt((int) $request->id, (int) $actor->id, RequestStatus::CLOSED),
                fn (): string => $this->attempt((int) $request->id, (int) $actor->id, RequestStatus::IN_REVIEW),
            );

            $fresh = $request->fresh();

            $this->assertSame(RequestStatus::CLOSED, $fresh->request_status, "iteration {$iteration}");
            $this->assertContains(RequestStatus::CLOSED->value, $results, "iteration {$iteration}");
            $committedTransitions = count(array_filter($results, fn (string $result): bool => $result !== 'conflict'));
            $this->assertSame($committedTransitions, AuditEvent::query()->where('resource_id', FurnitureRequestIdentifier::encode($request))->count(), "iteration {$iteration}");
        }
    }

    public function test_concurrent_same_target_transitions_apply_once(): void
    {
        for ($iteration = 0; $iteration < self::RACES; $iteration++) {
            $request = FurnitureRequest::factory()->create();
            $actor = User::factory()->staff()->create();

            $results = $this->runConcurrentWorkers(
                fn (): string => $this->attempt((int) $request->id, (int) $actor->id, RequestStatus::IN_REVIEW),
                fn (): string => $this->attempt((int) $request->id, (int) $actor->id, RequestStatus::IN_REVIEW),
            );

            $this->assertSame(RequestStatus::IN_REVIEW, $request->fresh()->request_status, "iteration {$iteration}");
            $this->assertSame([RequestStatus::IN_REVIEW->value, RequestStatus::IN_REVIEW->value], $results, "iteration {$iteration}");
            $this->assertSame(1, AuditEvent::query()->where('resource_id', FurnitureRequestIdentifier::encode($request))->count(), "iteration {$iteration}");
        }
    }

    private function attempt(int $requestId, int $actorId, RequestStatus $target): string
    {
        try {
            $result = app(UpdateFurnitureRequestOperationalFields::class)->update(
                FurnitureRequest::query()->findOrFail($requestId),
                new FurnitureRequestOperationalUpdate(true, $target, false, null),
                User::query()->findOrFail($actorId),
                'request-status-concurrency',
            );

            return $result->request_status->value;
        } catch (InvalidRequestStatusTransition) {
            return 'conflict';
        }
    }
}
