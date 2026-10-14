# Phase 19.3 — Clerk Authentication & Laravel Admin Authorization

## Status

**PASS — implementation, automated verification, and controlled live ADMIN/CUSTOMER/unavailable smoke tests complete.** Test C (STAFF) was not executed because no approved STAFF identity was available.

## Approved additive V1 identity amendment

Owner approval authorized the narrow, additive `USER-001/002` extension recorded in `docs/decisions.md` as `ADR/ADMIN-002`:

- Existing `/api/v1/me` fields retain their meaning.
- `account_state` is now serialized from the existing local Laravel field as `ACTIVE`, `SUSPENDED`, or `null`.
- `permissions` is the authenticated user's effective Laravel permission identifiers, sorted deterministically.
- The singular `role` remains unchanged.
- The response remains `Cache-Control: private, no-store` and `Vary: Authorization`.
- The array supports presentation only. Laravel remains the authority for every API authorization decision.

The amendment was applied to `docs/api/api-contract.md`, `docs/api/api-resources.md`, `docs/api/api-conventions.md`, and `docs/api/openapi.yaml` before backend implementation.

## Authentication and admission design

1. Clerk owns sign-in, session restoration, expiry, and sign-out at `/sign-in`.
2. `proxy.ts` redirects clearly signed-out requests to `/sign-in` when Clerk is configured. It does not evaluate roles or permissions.
3. `app/(protected)/layout.tsx` is dynamic and calls `requireOperationalIdentity()` before rendering protected children.
4. The server-only bridge obtains the current Clerk token per request and uses the existing `lib/api/client.ts` to call `GET /api/v1/me` with private `no-store` transport semantics.
5. Admission requires a validated response with exactly `ACTIVE` local `STAFF` or `ADMIN` role and a non-duplicated, sorted permission list.
6. CUSTOMER, suspended/inactive, missing, malformed, ambiguous, unavailable, expired, and unresolvable identities fail closed. No token, Clerk metadata, organization role, or client flag can admit an account.

The server-to-client identity provider contains only sanitized local user ID, name, email, role, active state, and effective permission names. It never receives a bearer token.

## Role and permission behavior

- Active `STAFF` and `ADMIN` identities are admitted only after Laravel `/me` confirmation.
- `CUSTOMER` receives **Access denied** and may safely sign out; there is no admin registration, promotion, or role-selection flow.
- Non-customer admission failures receive the generic **Account unavailable** state.
- A Laravel `401` under a still-active Clerk session (for example an authorized-party mismatch) receives a distinct **Session not accepted** state with an explicit Clerk sign-out. It does not redirect to `/sign-in`, because `SignIn` cannot render for an already signed-in user and that would strand the session without a recovery path. Only a genuinely signed-out visitor is redirected.
- An unreachable or erroring Laravel service receives **Operational access unavailable** with a sign-out action and never admits the user.
- All four failure states (denied, account unavailable, session not accepted, service unavailable) offer Clerk sign-out, so no authenticated user is stranded.
- `hasPermission()` / `requirePermission()` perform exact membership checks on Laravel-returned permissions. An `ADMIN` role alone does not bypass a missing permission.
- Future screens must repeat server/API authorization checks; client presentation state is not a security boundary.

## Laravel implementation

`MeController` now:

- rejects local identities with missing, multiple, or unknown roles;
- returns the unchanged profile fields plus `account_state` and deterministic effective `permissions`;
- performs the role-eligibility check **before** any profile write on `PATCH /me`, so a rejected request cannot persist `name`/`phone`;
- leaves `AuthenticateClerk` and `EnsureActiveAccount` intact, so suspended accounts are rejected before `/me` can serialize them.

No authentication mechanism, business endpoint, permission, role assignment path, or business workflow was added.

## Logout and account switching

The denial/account-unavailable actions use Clerk `signOut({ redirectUrl: "/sign-in" })`. The protected boundary resolves `/me` on every dynamic request and the application maintains no global identity or token cache. A different Clerk session therefore receives an independent Laravel identity check; prior roles, permissions, and data are not reused.

## Verification

### Automated

| Check | Result |
| --- | --- |
| `php artisan test --compact tests/Feature/ProfileOperationsTest.php tests/Feature/ClerkSecurityBoundaryTest.php` | PASS — 36 tests |
| `vendor/bin/pint --dirty --format agent` | PASS |
| `vendor/bin/phpstan analyse --error-format=table` | PASS — 0 errors |
| `npm run lint` | PASS |
| `npm run typecheck` | PASS |
| `npm run test` | PASS — 17 tests |
| `npm run build` | PASS |

Laravel coverage includes customer, active staff/admin, suspended staff, missing effective permissions, deterministic permission serialization, legacy profile fields, missing role, ambiguous role rejection, and rejection of an ineligible role **before** a profile write persists. Admin coverage includes active STAFF/ADMIN admission, CUSTOMER and malformed identity denial, pending-session rejection, a recoverable Laravel-401-under-live-session state, permission absence, unavailable Laravel behavior, and independent successive identity resolution.

### Browser and live smoke testing

Executed against a locally running Laravel API and `frontend/admin` on port 3001 using the existing bootstrapped Clerk ADMIN identity. No credentials, tokens, or privileged identities were created by the agent.

| Test | Result | Evidence |
| --- | --- | --- |
| Safe configuration state (no Clerk key) | PASS | Renders "Admin configuration required"; console clean. Confirmed the proxy guard prevents Clerk initialization and a 500. |
| Signed-out redirect | PASS | Unauthenticated request to `/` redirected to `/sign-in`. |
| Test A — ADMIN | PASS | Clerk sign-in succeeded; Laravel `/me` resolved the pre-existing bootstrapped ACTIVE ADMIN and protected admission rendered "Operational access confirmed". |
| Test B — CUSTOMER | PASS | Clerk authentication succeeded and Laravel JIT-provisioned a local CUSTOMER, but admin admission rendered "Access denied". No promotion, no registration path, no operational content. |
| Sign-up surface | PASS (defect fixed) | Clerk's hosted "Sign up" link was present on the admin origin. `footerAction` is hidden, `signUpUrl` is pinned to `/sign-in`, and `withSignUp={false}` / `transferable={false}` disable the sign-up flow itself. `/sign-up` returns 404, and an unregistered email dead-ends with "Couldn't find your account." rather than any registration offer. |
| Sign-out control | PASS (defect fixed) | No sign-out affordance existed. Added `SignOutButton`; verified it renders with the admitted identity and returns to `/sign-in`. |
| Test E — Laravel unavailable | PASS | With the API origin pointed at an unreachable port the page failed closed to "Operational access unavailable", proving the render is dynamic and Laravel-gated rather than a static success page. |

Not executed: **Test C (STAFF)** — no approved active STAFF Clerk identity was available, and none was created. Session transition (Test D) is covered by the sign-out control and automated tests but was not separately re-verified as a full manual cycle.

Browser-verified recovery paths: with a stubbed Laravel returning `401` while Clerk held a live session, the protected route stayed on `/` and rendered **Session not accepted**. With Laravel unreachable, the **Operational access unavailable** state offered **Sign out**, which was clicked and confirmed to end the session and return to `/sign-in`. Neither path strands an authenticated user or loops.

Browser-verified sign-up containment: a nonexistent email on the admin sign-in surface dead-ended with "Couldn't find your account." — no registration offer, no password prompt, no sign-up redirect. `footerAction` hiding and `signUpUrl` pinning alone were insufficient; `withSignUp={false}` and `transferable={false}` are what disable the flow. An OAuth identity that is not registered is covered by `transferable={false}`, which prevents opaque sign-ups; it was not separately exercised because the local Clerk instance has no OAuth providers configured.

Browser console was clean throughout apart from Clerk's own development-instance key advisory. No bearer token appeared in rendered output, page source, or console.

## Files changed

- API amendment: `docs/api/{api-contract.md,api-resources.md,api-conventions.md,openapi.yaml}`, `docs/decisions.md`
- Laravel: `backend/laravel/app/Http/Controllers/Api/V1/MeController.php`, `backend/laravel/tests/Feature/ProfileOperationsTest.php`
- Admin authentication: `frontend/admin/app/(auth)/sign-in/[[...sign-in]]/page.tsx`, `frontend/admin/app/(protected)/{layout.tsx,page.tsx}`, `frontend/admin/proxy.ts`, `frontend/admin/components/auth/*`, `frontend/admin/lib/auth/*`, and related tests/configuration/docs

## Remaining limitations and Phase 19.4 handoff

The owner/operations team must still configure `NEXT_PUBLIC_CLERK_PUBLISHABLE_KEY` and server-only `CLERK_SECRET_KEY` in `frontend/admin/.env.local`, plus `http://localhost:3001` as an exact Clerk allowed origin and Laravel authorized party, for any non-local environment. Production origins, CORS, CSP, and Clerk settings are intentionally not guessed or changed here.

The five high-severity development/transitive findings reported in the Phase 19.2 dependency audit remain a separate follow-up; this phase did not upgrade or suppress dependencies.

### Local test-data cleanup performed

A manually inserted test account (a SUSPENDED local STAFF record with no Clerk mapping) was removed at owner request so it can be recreated through the legitimate customer signup flow. Its single unread test notification and its email-delivery row were removed with it inside one transaction, because a foreign key prevented deleting the user alone. The bootstrapped ADMIN record was not modified.

Phase 19.4 may build the dashboard shell only after owner approval. Each new protected screen must use the established server admission boundary, exact Laravel permission checks for presentation, `no-store` authenticated calls, and Laravel endpoint-level authorization.
