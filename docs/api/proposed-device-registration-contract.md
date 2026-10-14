# Proposed Device Registration Contract — Phase 18.4 (for review and approval)

> **SUPERSEDED — this document is a historical proposal, not the contract.**
>
> This contract was **approved and implemented on 2026-10-12**, with amendments. The
> authoritative, implemented contract is:
>
> - `docs/api/api-contract.md §36` — Device Registration & Deactivation Contract
> - `docs/api/api-resources.md §16` — DeviceInstallation resource
> - `docs/api/api-conventions.md §33` — Device registration conventions
> - `docs/api/openapi.yaml` — `POST /me/devices`, `DELETE /me/devices/{device}`
> - `docs/decisions.md` — `ADR/BACKEND-056`
>
> Several statements below were changed during approval, so do not implement from this
> document. The material amendments: `DELETE /me/devices/current` was replaced by
> `DELETE /me/devices/{device}` using a server-issued opaque identifier; single-owner
> enforcement moved from an application check to a database unique constraint on a keyed
> fingerprint; `DEVICE_TOKEN_CONFLICT` was **not** introduced, so the frozen `CONFLICT` code
> is reused; no separate token-refresh endpoint exists, because rotation reuses
> `POST /me/devices`; registration is gated by an independent
> `NOTIFICATION_PUSH_REGISTRATION_ENABLED` flag; and tokens are stored encrypted.
>
> The original proposal is retained below unchanged for traceability. See
> `phases/phase-18.4-completion.md` for the implemented paths, schema, security findings,
> test results, and remaining limitations.

**Original status at time of writing: DRAFT — NOT IMPLEMENTED.**

This is a minimal contract proposal only. It is intentionally additive and opt-in: it does
not alter any existing path, schema, error code, or behavior in the frozen Version 1 contract.
It must be approved under API governance before any code is written.

Real Firebase delivery is already proven (see `phases/phase-18.4-completion.md`). The only
reason Phase 18.4 remains PARTIAL is that tokens currently cannot be stored server-side,
because storing them requires a public contract that does not exist yet.

## 1. Scope and intent

A customer installation registers the FCM registration token for its app instance so the
backend can address push messages to that device.

Explicit non-goals, matching Phase 18.4 constraints:

- No public or anonymous device registration.
- No staff/admin token registration, and no route that lets staff address a customer device.
- No customer-selected user ID, role, or ownership. Ownership is server-derived from Clerk.
- No business-event push fan-out. This contract only persists and retires tokens; it does not
  decide who receives what.
- No changes to orders, payments, fulfilment, requests, or enquiries.

## 2. Proposed endpoints

Both are customer-scoped and sit behind the existing authenticated customer middleware.

### 2.1 `POST /api/v1/me/devices`

Idempotent upsert of the current app installation's token.

Request body:

```json
{
  "token": "<fcm_registration_token>",
  "platform": "ANDROID",
  "app_instance_id": "<opaque firebase_installation_id>",
  "app_version": "0.1.0",
  "app_build": "1"
}
```

Rules:

- `token` — required, string, 1–4096 chars, pattern-restricted to the FCM token alphabet.
- `platform` — required, closed enum, currently only `ANDROID`.
- `app_instance_id` — required, opaque Firebase Installation ID used to detect rotation and
  to make repeated calls from the same installation converge on one row.
- `app_version` / `app_build` — optional informational strings, max 64 chars each.

Ownership rules:

- The user is taken **only** from the authenticated Clerk principal. A `user_id` in the body
  is rejected with `422 INVALID_VALUE`, consistent with `api-contract.md §29.2` / `§29.6`.
- Re-registering a token that already belongs to the authenticated user updates that row.
- Re-registering a token owned by a *different* user must not be silently reassigned. It is
  rejected with `409 CONFLICT` (`code: DEVICE_TOKEN_CONFLICT`) and must be resolved by an
  explicit sign-out deactivation first. This prevents token theft by a compromised client.

Response `201` (new) or `200` (existing row updated):

```json
{
  "data": {
    "id": "uuid",
    "platform": "ANDROID",
    "app_instance_id": "opaque-installation-id",
    "app_version": "0.1.0",
    "status": "ACTIVE",
    "created_at": "2026-10-12T00:00:00Z",
    "updated_at": "2026-10-12T00:00:00Z",
    "last_registered_at": "2026-10-12T00:00:00Z"
  },
  "meta": { "request_id": "..." }
}
```

The registration token is **never** echoed in the response.

### 2.2 `DELETE /api/v1/me/devices/current`

Idempotent deactivation of the caller's current app installation.

- No body required.
- Sets the row to `INACTIVE` rather than deleting, so a stale in-flight message cannot
  resurrect it.
- Deactivating an unknown or already-inactive installation returns `204` (idempotent, and does
  not leak whether a token existed).
- A row belonging to another user is `404 RESOURCE_NOT_FOUND`, not `403`, to avoid confirming
  the existence of another customer's device.

### 2.3 Optional: `POST /api/v1/me/devices/refresh`

Optional. Rotates the `last_registered_at`/token for the current installation after an
`onTokenRefresh`. If omitted, `POST /api/v1/me/devices` already handles rotation idempotently
and this endpoint is unnecessary. Recommended: **omit it initially.**

## 3. Proposed resource shape

```
DeviceInstallation
  id                  uuid, primary key
  user_id             FK -> users.id, ON DELETE CASCADE
  platform            enum(ANDROID)                       -- closed
  app_instance_id     string, unique per user
  token               string, indexed, never selected into read models
  status              enum(ACTIVE, INACTIVE)
  app_version         string nullable
  app_build           string nullable
  created_at          timestamp
  updated_at          timestamp
  last_registered_at  timestamp
  deactivated_at      timestamp nullable
```

Unique constraints:

- `(user_id, app_instance_id)` — one row per installation per user.
- `token` — unique, so a token cannot be registered to two users.

Indexing: unique on `token`, index on `(user_id, status)`, index on `status, updated_at` for
stale-token sweeps.

## 4. Authorization and security rules

| Concern | Rule |
|---|---|
| Principal | Authenticated `CUSTOMER` via Clerk; `STAFF`/`ADMIN` cannot register customer devices |
| Ownership | Derived server-side from the Clerk principal only; client-supplied `user_id` rejected |
| Denial behavior | Other users' installations are masked as `404 RESOURCE_NOT_FOUND` |
| Anonymity | No anonymous registration; `401 UNAUTHENTICATED` for unauthenticated calls |
| Storage | Token stored server-side only; never logged, never returned, never exposed in admin reads |
| Abuse control | Reuse the existing `throttle:authenticated-write` limiter or a tighter dedicated one |
| Transport | HTTPS only; no token in URL, query string, or logs |

## 5. Token lifecycle and invalidation

- `UNREGISTERED` from FCM → mark the row `INACTIVE`. This is the only automatic deactivation.
- `INVALID_ARGUMENT` → do **not** deactivate; it indicates a malformed message, not a dead token.
- Transient failures (`408`, `429`, `5xx`) → retry with backoff; do not change row status.
- Explicit sign-out → `DELETE /api/v1/me/devices/current`.
- A scheduled sweep may mark rows `INACTIVE` after a stale window; this needs no contract change.

## 6. Error codes proposed

All reuse the existing envelope in `api-contract.md §15`. New codes:

| Code | Status | Meaning |
|---|---|---|
| `DEVICE_TOKEN_CONFLICT` | 409 | Token already registered to another user |
| `INVALID_VALUE` (`field: token`) | 422 | Malformed or over-length token |
| `RESOURCE_NOT_FOUND` | 404 | Installation not owned by the caller |

No new HTTP status codes are introduced.

## 7. Delivery

Adding these routes is necessary but not sufficient. Before any customer-facing push:

- Declare a default notification channel in `AndroidManifest.xml` and create it in the app.
  The diagnostic used the platform fallback channel `fcm_fallback_notification_channel`
  because no channel metadata is declared.
- Decide a buffering/replay strategy for terminated/opened messages, since `signals` is a
  broadcast stream and the initial message is emitted before consumers subscribe.
- Approve a notification event and recipient policy separately. Persisting a token grants the
  ability to address a device, not permission to message a customer.

## 8. Files this would touch (not yet changed)

- `docs/api/api-contract.md`, `api-resources.md`, `api-conventions.md`, `openapi.yaml`
- `backend/laravel/routes/api.php` (customer `me` group)
- `backend/laravel/app/Http/Controllers/Api/V1/…DeviceInstallationController.php`
- `backend/laravel/app/Http/Requests/…RegisterDeviceRequest.php`
- `backend/laravel/app/Models/DeviceInstallation.php` + factory
- a new migration adding `device_installations`
- `frontend/app/lib/core/notifications/` registration wiring, invoked from the existing
  `PushMessagingService` token-refresh path
- feature tests covering ownership, conflict, idempotency, and masking

## 9. Decision requested

1. Approve or reject the two endpoints in §2.
2. Approve or reject the resource shape in §3, in particular the unique-on-`token` rule and the
   `409` on cross-user re-registration.
3. Decide whether §2.3 is needed.
4. Confirm that persisting tokens stays opt-in behind `NOTIFICATION_PUSH_ENABLED`, which
   remains `false` until customer fan-out is separately approved.