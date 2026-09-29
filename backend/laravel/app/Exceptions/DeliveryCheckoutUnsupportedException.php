<?php

namespace App\Exceptions;

use DomainException;

/**
 * Internal Phase 7.4 blocker: DELIVERY checkout persistence (billing snapshot
 * model gap) is unresolved, so the Checkout transaction must refuse DELIVERY
 * before any mutation. Not a public V1 error code.
 */
final class DeliveryCheckoutUnsupportedException extends DomainException {}
