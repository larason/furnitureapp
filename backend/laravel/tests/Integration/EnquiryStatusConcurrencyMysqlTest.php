<?php

namespace Tests\Integration;

use App\Models\AuditEvent;
use App\Models\Enquiry;
use App\Models\User;
use App\Services\Enquiries\CloseEnquiry;
use App\Support\AuditAction;
use App\Support\EnquiryIdentifier;
use App\Support\EnquiryStatus;
use Tests\Support\RunsConcurrentWorkers;
use Tests\Support\UsesDisposableMysqlDatabase;
use Tests\TestCase;

/**
 * Real MariaDB/MySQL concurrency verification for the ENQ-006 close race.
 * Not part of the default suite; run explicitly against the disposable DB:
 *
 *   ENQUIRY_STATUS_MYSQL_TEST_DATABASE=furnitureapp_test_disposable \
 *   vendor/bin/phpunit tests/Integration/EnquiryStatusConcurrencyMysqlTest.php
 */
class EnquiryStatusConcurrencyMysqlTest extends TestCase
{
    use RunsConcurrentWorkers;
    use UsesDisposableMysqlDatabase;

    private const RACES = 10;

    protected function databaseConnectionName(): string
    {
        return 'mysql_enquiry_status';
    }

    protected function databaseEnvironmentVariable(): string
    {
        return 'ENQUIRY_STATUS_MYSQL_TEST_DATABASE';
    }

    public function test_concurrent_close_transitions_and_audits_exactly_once(): void
    {
        for ($iteration = 0; $iteration < self::RACES; $iteration++) {
            $enquiry = Enquiry::factory()->create();
            $actor = User::factory()->staff()->create([
                'clerk_user_id' => 'enquiry_close_actor_'.$iteration.'_'.random_int(1, 1_000_000),
            ]);

            $results = $this->runConcurrentWorkers(
                fn (): string => $this->attempt((int) $enquiry->id, (int) $actor->id, 'race-a-'.$iteration),
                fn (): string => $this->attempt((int) $enquiry->id, (int) $actor->id, 'race-b-'.$iteration),
            );

            $fresh = $enquiry->fresh();

            $this->assertSame(EnquiryStatus::CLOSED, $fresh->enquiry_status, "iteration {$iteration}");
            $this->assertContains(EnquiryStatus::CLOSED->value, $results, "iteration {$iteration}");

            $audits = AuditEvent::query()
                ->where('action', AuditAction::ENQUIRY_STATUS_CHANGED->value)
                ->where('resource_id', EnquiryIdentifier::encode($fresh))
                ->get();

            $this->assertCount(1, $audits, "iteration {$iteration}");
            $this->assertSame('OPEN', $audits->first()->previous_state['enquiry_status'], "iteration {$iteration}");
            $this->assertSame('CLOSED', $audits->first()->resulting_state['enquiry_status'], "iteration {$iteration}");
        }
    }

    private function attempt(int $enquiryId, int $actorId, string $requestId): string
    {
        $enquiry = app(CloseEnquiry::class)->close(
            Enquiry::query()->findOrFail($enquiryId),
            User::query()->findOrFail($actorId),
            $requestId,
        );

        return $enquiry->enquiry_status->value;
    }
}
