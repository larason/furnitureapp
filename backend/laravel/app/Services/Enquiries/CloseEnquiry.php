<?php

namespace App\Services\Enquiries;

use App\Models\Enquiry;
use App\Models\User;
use App\Services\AuditRecorder;
use App\Support\AuditAction;
use App\Support\AuditResourceType;
use App\Support\ConcurrentTransaction;
use App\Support\EnquiryIdentifier;
use App\Support\EnquiryStatus;

final class CloseEnquiry
{
    public function __construct(private readonly AuditRecorder $audit) {}

    public function close(Enquiry $enquiry, User $actor, ?string $requestId): Enquiry
    {
        return ConcurrentTransaction::run(function () use ($enquiry, $actor, $requestId): Enquiry {
            $locked = Enquiry::query()->whereKey($enquiry->getKey())->lockForUpdate()->firstOrFail();
            $previousStatus = $locked->enquiry_status;

            if ($previousStatus === EnquiryStatus::CLOSED) {
                return $locked;
            }

            $locked->enquiry_status = EnquiryStatus::CLOSED;
            $locked->save();

            $this->audit->record(
                $actor,
                AuditAction::ENQUIRY_STATUS_CHANGED,
                AuditResourceType::ENQUIRY,
                EnquiryIdentifier::encode($locked),
                ['enquiry_status' => $previousStatus->value],
                ['enquiry_status' => $locked->enquiry_status->value],
                $requestId,
            );

            return $locked;
        }, true);
    }
}
