<?php

namespace App\Support;

/**
 * Lifecycle state of a registered device installation.
 *
 * A row is never deleted on deactivation so a late or replayed message cannot
 * resurrect an association. Re-registering an INACTIVE row reactivates it.
 */
enum DeviceRegistrationStatus: string
{
    case ACTIVE = 'ACTIVE';
    case INACTIVE = 'INACTIVE';
}
