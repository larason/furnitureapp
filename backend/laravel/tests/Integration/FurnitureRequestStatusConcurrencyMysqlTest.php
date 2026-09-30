<?php

namespace Tests\Integration;

use App\Exceptions\InvalidRequestStatusTransition;
use App\Models\FurnitureRequest;
use App\Services\Requests\TransitionFurnitureRequestStatus;
use App\Support\RequestStatus;
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

    public function test_concurrent_close_and_review_can_never_reopen_a_closed_request(): void
    {
        for ($iteration = 0; $iteration < self::RACES; $iteration++) {
            $request = FurnitureRequest::factory()->create();

            $results = $this->runConcurrentWorkers(
                fn (): string => $this->attempt((int) $request->id, RequestStatus::CLOSED),
                fn (): string => $this->attempt((int) $request->id, RequestStatus::IN_REVIEW),
            );

            $fresh = $request->fresh();

            $this->assertSame(RequestStatus::CLOSED, $fresh->request_status, "iteration {$iteration}");
            $this->assertContains(RequestStatus::CLOSED->value, $results, "iteration {$iteration}");
        }
    }

    public function test_concurrent_same_target_transitions_apply_once(): void
    {
        for ($iteration = 0; $iteration < self::RACES; $iteration++) {
            $request = FurnitureRequest::factory()->create();

            $results = $this->runConcurrentWorkers(
                fn (): string => $this->attempt((int) $request->id, RequestStatus::IN_REVIEW),
                fn (): string => $this->attempt((int) $request->id, RequestStatus::IN_REVIEW),
            );

            $this->assertSame(RequestStatus::IN_REVIEW, $request->fresh()->request_status, "iteration {$iteration}");
            $this->assertSame([RequestStatus::IN_REVIEW->value, RequestStatus::IN_REVIEW->value], $results, "iteration {$iteration}");
        }
    }

    private function attempt(int $requestId, RequestStatus $target): string
    {
        try {
            $result = app(TransitionFurnitureRequestStatus::class)->transition(
                FurnitureRequest::query()->findOrFail($requestId),
                $target,
            );

            return $result->request_status->value;
        } catch (InvalidRequestStatusTransition) {
            return 'conflict';
        }
    }
}
