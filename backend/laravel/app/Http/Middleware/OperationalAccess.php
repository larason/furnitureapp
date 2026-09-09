<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route-level guard for Staff/operational authorization (deny by default).
 *
 * The frozen contract classifies `/orders`, `/inventory`, and the
 * operational handling of `/requests` and `/enquiries` as OPERATIONAL
 * (Staff/Admin). The role/permission model does not exist yet (Group D:
 * Phases 4.9-4.10) and the `users` schema has no role attribute, so NO
 * principal is authorized today.
 *
 * Every request is denied to honour AGENTS.md §18 deny-by-default: no
 * operational operation may become reachable merely because a request is
 * authenticated. When Group D lands the role/permission layer, replace this
 * blanket denial with the approved `staff.*` permission check - never open
 * the route without explicit authorization.
 *
 * The preceding `auth` middleware guarantees the request is authenticated
 * before this middleware runs, so failures surface as 403 (authenticated
 * identity, insufficient authority), never as 401. Phase 2.7 maps this to
 * the frozen `FORBIDDEN` error contract.
 */
class OperationalAccess
{
    public function handle(): Response
    {
        throw new AuthorizationException('The requested operational operation is not available.');
    }
}
