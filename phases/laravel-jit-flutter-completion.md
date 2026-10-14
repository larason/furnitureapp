# Laravel JIT Provisioning — Flutter Session Bootstrap

## Integration point

`AuthenticatedSessionBootstrapCoordinator` is started by `SLFurnituresApp` when
both the existing `ClerkAuthAdapter` and `ApiProfileRepository` are available.
It observes the app-level Clerk session and invokes the frozen authenticated
`GET /api/v1/me` contract in the background. Public routes and startup remain
nonblocking.

## Session safety

The existing Clerk adapter now exposes only the active Clerk session ID for
in-memory coordination and republishes an active-session change even when the
coarse status remains `signedIn`. The coordinator accepts only a signed-in,
active adapter session with an ID. It deduplicates work per session, remembers
success only for the current process, and invalidates all pending work through a
generation change on logout or account switch. Therefore, a completed request
for a former customer cannot mark the new customer as synchronized or trigger
its downstream callback.

Laravel remains authoritative for verified-email requirements, token validity,
JIT provisioning, roles, account state, and the `/me` representation.

## Failure and lifecycle handling

Network and timeout failures, 429 responses, and 5xx responses retry with the
two existing bounded delays. Other authentication, authorization, validation,
and malformed-response failures do not retry automatically. A foreground resume
starts a fresh bounded retry cycle after a transient cycle is exhausted. Timers,
auth listeners, and lifecycle observers are removed on disposal.

## FCM interaction

Push remains opt-in and its feature flags are unchanged. `PushMessagingService`
does not register a device for a signed-in session until the bootstrap callback
confirms that exact Clerk session is resolved by Laravel. Sign-out retirement and
fail-closed device ownership behavior remain unchanged; this change does not
enable FCM, fan-out, or new device endpoints.

## Verification

- `flutter test` — 677 passed.
- `flutter analyze` — no errors from this change; the command exits nonzero on
  the pre-existing inference warning in
  `test/core/notifications/serial_task_queue_test.dart`.
- Focused coordinator tests cover eligibility, deduplication, bounded transient
  retry, and stale Customer A completion after Customer B becomes current.
- Push token-refresh registration captures the ready session ID and rechecks that
  identity inside the queued task, preventing a rotated token from registering
  against a changed or no-longer-ready account.
- Existing Laravel provisioning/security coverage was reused; no Laravel source
  code or API contract changed.

## Live smoke test

No real Clerk/Laravel Flutter smoke test was run because this environment has no
dedicated verified Clerk test customer and isolated Laravel runtime configured.
