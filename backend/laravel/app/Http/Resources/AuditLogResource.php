<?php

namespace App\Http\Resources;

use App\Models\AuditEvent;
use App\Support\AuditAction;
use App\Support\AuditLogIdentifier;
use App\Support\AuditResourceType;
use App\Support\UserIdentifier;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @property-read AuditEvent $resource */
final class AuditLogResource extends JsonResource
{
    private const SNAPSHOT_FIELDS = [
        AuditAction::INVENTORY_ADJUSTED->value.':'.AuditResourceType::INVENTORY->value => [
            'quantity', 'reserved_quantity', 'available_quantity', 'warehouse_location', 'quantity_delta', 'reason',
        ],
        AuditAction::DELIVERY_FEE_FINALIZED->value.':'.AuditResourceType::ORDER->value => [
            'delivery_fee_status', 'delivery_fee_amount', 'total_amount',
        ],
        AuditAction::REQUEST_STATUS_CHANGED->value.':'.AuditResourceType::REQUEST->value => ['request_status'],
        AuditAction::ENQUIRY_STATUS_CHANGED->value.':'.AuditResourceType::ENQUIRY->value => ['enquiry_status'],
        AuditAction::CUSTOMER_LIST_VIEWED->value.':'.AuditResourceType::USER->value => ['result', 'returned_count'],
        AuditAction::CUSTOMER_VIEWED->value.':'.AuditResourceType::USER->value => ['result'],
    ];

    public function toArray(Request $request): array
    {
        $audit = $this->resource;

        return [
            'id' => AuditLogIdentifier::encodeId((int) $audit->getKey()),
            'actor_id' => UserIdentifier::encodeId($audit->actor_id),
            'actor_role' => $audit->actor_role,
            'action' => $audit->action,
            'resource_type' => $audit->resource_type,
            'resource_id' => $audit->resource_id,
            'previous_state' => $this->snapshot($audit->previous_state, $audit),
            'resulting_state' => $this->snapshot($audit->resulting_state, $audit),
            'timestamp' => $audit->occurred_at->toISOString(),
            'request_id' => $audit->request_id,
        ];
    }

    /** @param array<string, mixed>|null $state
     * @return array<string, mixed>|null
     */
    private function snapshot(?array $state, AuditEvent $audit): ?array
    {
        if ($state === null) {
            return null;
        }

        $fields = self::SNAPSHOT_FIELDS[$audit->action.':'.$audit->resource_type] ?? [];
        $snapshot = [];
        foreach ($fields as $field) {
            if (array_key_exists($field, $state)) {
                $snapshot[$field] = $state[$field];
            }
        }

        return $snapshot;
    }
}
