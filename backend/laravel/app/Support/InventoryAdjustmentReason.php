<?php

namespace App\Support;

/**
 * CLOSED Version 1 inventory adjustment reasons (api-contract.md §30.5.2).
 */
enum InventoryAdjustmentReason: string
{
    case STOCK_RECEIPT = 'STOCK_RECEIPT';
    case CORRECTION = 'CORRECTION';
    case DAMAGE = 'DAMAGE';
    case RETURN = 'RETURN';
    case AUDIT_ADJUSTMENT = 'AUDIT_ADJUSTMENT';

    public function requiresPositiveDelta(): bool
    {
        return $this === self::STOCK_RECEIPT || $this === self::RETURN;
    }

    public function requiresNegativeDelta(): bool
    {
        return $this === self::DAMAGE;
    }
}
