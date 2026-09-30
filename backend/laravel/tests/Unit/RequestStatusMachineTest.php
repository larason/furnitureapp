<?php

namespace Tests\Unit;

use App\Services\Requests\RequestStatusMachine;
use App\Services\Requests\RequestStatusTransitionOutcome;
use App\Support\RequestStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class RequestStatusMachineTest extends TestCase
{
    /**
     * @return array<string, array{0: RequestStatus, 1: RequestStatus, 2: RequestStatusTransitionOutcome}>
     */
    public static function transitions(): array
    {
        return [
            'submitted -> submitted' => [RequestStatus::SUBMITTED, RequestStatus::SUBMITTED, RequestStatusTransitionOutcome::Idempotent],
            'submitted -> in_review' => [RequestStatus::SUBMITTED, RequestStatus::IN_REVIEW, RequestStatusTransitionOutcome::Allowed],
            'submitted -> closed' => [RequestStatus::SUBMITTED, RequestStatus::CLOSED, RequestStatusTransitionOutcome::Allowed],
            'in_review -> submitted' => [RequestStatus::IN_REVIEW, RequestStatus::SUBMITTED, RequestStatusTransitionOutcome::Forbidden],
            'in_review -> in_review' => [RequestStatus::IN_REVIEW, RequestStatus::IN_REVIEW, RequestStatusTransitionOutcome::Idempotent],
            'in_review -> closed' => [RequestStatus::IN_REVIEW, RequestStatus::CLOSED, RequestStatusTransitionOutcome::Allowed],
            'closed -> submitted' => [RequestStatus::CLOSED, RequestStatus::SUBMITTED, RequestStatusTransitionOutcome::Forbidden],
            'closed -> in_review' => [RequestStatus::CLOSED, RequestStatus::IN_REVIEW, RequestStatusTransitionOutcome::Forbidden],
            'closed -> closed' => [RequestStatus::CLOSED, RequestStatus::CLOSED, RequestStatusTransitionOutcome::Idempotent],
        ];
    }

    #[DataProvider('transitions')]
    public function test_transition_matrix(RequestStatus $current, RequestStatus $target, RequestStatusTransitionOutcome $expected): void
    {
        $this->assertSame($expected, RequestStatusMachine::decide($current, $target));
    }

    public function test_closed_has_no_allowed_targets(): void
    {
        $this->assertSame([], RequestStatusMachine::allowedTargets(RequestStatus::CLOSED));
    }
}
