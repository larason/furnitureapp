# Laravel JIT Provisioning — Next.js Session Bootstrap

## Integration point

`frontend/web/components/auth/laravel-session-bootstrap.tsx` renders once inside the existing
`ClerkProvider` through `app/providers.tsx`. It has no UI and does not change public routes,
server-rendered catalog pages, Clerk credential flows, or the frozen Laravel API contract.

## Session readiness and request contract

The bootstrap runs only when Clerk has loaded both authentication and user state, has an active
signed-in session, reports a verified primary email, and has either no `sts` claim or `sts=active`.
It obtains a current token only for the request and uses the existing API transport to call
`GET /api/v1/me` with `cache: "no-store"` and `Authorization: Bearer <Clerk session token>`.
No token or Laravel profile is logged or persisted.

Laravel remains the authority: its existing Clerk middleware verifies the token and pending-session
state, and `LocalUserProvisioner` verifies the Clerk email snapshot before atomically creating a
`CUSTOMER` user and profile. Existing local roles and account state are reused unchanged.

## Deduplication and failures

The in-memory bootstrapper keys work by Clerk session ID. It shares a single in-flight request
across duplicate mounts, records success once per session, and clears state on sign-out or session
switch. Temporary token, network, rate-limit, and 5xx failures retry twice with bounded backoff;
an unsuccessful temporary attempt is not recorded as complete, so a later lifecycle event can try
again. Clerk offline token acquisition also uses that bounded backoff. An
`INVALID_AUTHENTICATION` response triggers one `getToken({ skipCache: true })` retry; an identity
that remains rejected is blocked until its lifecycle resets. Up to two additional recovery attempts
may run when the browser reconnects or becomes visible. Reset invalidates in-flight work using a
session generation, so stale requests cannot alter a replacement session's state. Failures do not
sign out Clerk, redirect, or affect public browsing.

## Verification

- `node --import=tsx --test lib/auth/laravel-session-bootstrap.test.ts`
  - readiness gating, one successful request per session, concurrent deduplication, bounded retry,
    blocked invalid identity, and later retry after a temporary failure.
- `npm run test:auth`
- `npm run typecheck`

Laravel’s existing `LocalUserProvisionerTest`, `ConcurrentJitProvisioningTest`, and
`ClerkSecurityBoundaryTest` continue to cover verified-only provisioning, customer defaults,
profile creation, idempotency, concurrent first access, no email-based account linking, pending
security tasks, role preservation, and inactive-account rejection.

## Smoke-test status

No live Clerk/Laravel smoke test was run in this implementation environment because it does not
have a dedicated verified development identity or an isolated configured Laravel database. Perform
the manual smoke-test checklist in the implementation request before production rollout.
