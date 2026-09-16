# Clerk Authentication Architecture

> **Status:** Accepted (Phase 4.1) and **implemented** (Phase 4.2–4.4). This document now reflects the built authentication boundary — Clerk owns credentials/sessions/security; Laravel owns the local application identity projection, RBAC, and authorization. It no longer describes a not-yet-implemented design.

## Decision and Scope

Clerk is the external authentication authority for credentials, sign-up/sign-in, sessions, authentication factors, password recovery, email verification, and Clerk-account security changes. Laravel remains the local application identity projection and the authority for roles, permissions, account business state, ownership, policies, commerce state, and all authorization decisions.

```text
Next.js / Flutter / Admin client
        |
        | Clerk-managed authentication
        v
Clerk session token (verified identity)
        |
        | Authorization: Bearer <Clerk session token>
        v
Laravel Clerk authentication middleware
        |
        | verify signature and required claims
        v
users.clerk_user_id -> local users.id
        |
        v
Laravel authorization middleware / policy
        |
        v
Commerce controller and domain service
```

Laravel must not exchange a Clerk token for a Laravel Sanctum token, Laravel session, or other independent customer credential. Authentication answers who the caller is; policies and domain validation separately decide whether that local user may perform the requested action.

## Local Identity Mapping and Provisioning

The local `users` table remains the application principal. All existing foreign keys continue to point to `users.id`; Clerk IDs are never substituted into orders, carts, RBAC assignments, profiles, notifications, requests, enquiries, or audit records.

| Topic | Approved design |
|---|---|
| External identity key | Verified Clerk session-token `sub` only |
| Local mapping | `users.clerk_user_id` uniquely identifies the external Clerk user and resolves a local `users.id` |
| Email | Contact/security snapshot, never an account-linking key |
| First authenticated request | Verify token, find by `clerk_user_id`, and provision only when the mapping is absent |
| Initial role | `CUSTOMER` only; public sign-up cannot create or elevate `STAFF`/`ADMIN` |
| Provisioning data | Minimum trusted Clerk user information retrieved through the Clerk Backend API only after token verification; no client-supplied identity fields |
| Concurrency | New unique index on `clerk_user_id`, transaction/upsert-safe resolver, and duplicate-key recovery resolve simultaneous first requests to one local user |
| Existing unmapped users | Do not silently attach by matching email. A separately approved, authenticated, auditable account-linking migration is required before any mapping is made |
| Mapping mutation | Server-controlled and immutable after secure linking; never accepted in `/me` input or used as an authorization shortcut |

The Phase 4.2 migration plan is to add a nullable, unique, indexed `users.clerk_user_id` through a new Group D migration. It must not edit Group C migration history. Phase 4.2 will remove Laravel credential use, then use a later new migration to drop/deprecate `password`, `remember_token`, password-reset storage, and Laravel session authentication only after Clerk-authenticated request handling is proven and deployment migration data is accounted for.

## Token Verification and Laravel Principal

Phase 4.2 will introduce authentication middleware and focused collaborators such as `ClerkTokenVerifier`, `ClerkUserResolver`, and `LocalUserProvisioner`. The middleware will attach the resolved local `User` to Laravel's auth/request context. It will not decide roles, ownership, permissions, or business state.

For every protected request, the middleware must reject absent, malformed, expired, not-yet-valid, incorrectly signed, incorrectly algorithmed, or otherwise invalid Clerk session tokens. Verification must cryptographically validate the signing key from Clerk JWKS, the expected algorithm, `exp`, `nbf`, `iss`, `sub`, `azp`, configured audience where applicable, and Clerk-required token semantics. Decoding a JWT without verification is forbidden.

For mapped users, verification must use local JWKS/key-cache validation without a Clerk Backend API call on every request. The Backend API is limited to first provisioning and explicit/bounded reconciliation. JWKS retrieval must use configured issuer/JWKS endpoints, key rotation-aware caching, bounded refresh on unknown key IDs, timeouts, and fail-closed behavior. Exact issuer, audience, authorized-party, token template, JWKS URL, supported PHP package, and Clerk sign-in methods remain implementation-time configuration that must be confirmed through the selected Clerk application and official Clerk tooling without exposing secrets.

## Field Ownership and Synchronization

| Field or concern | Authority | Laravel storage/use | Synchronization and write rule |
|---|---|---|---|
| Clerk user ID | Clerk | `users.clerk_user_id` mapping | Derived from verified `sub`; immutable/server-controlled |
| Credential, password, MFA, recovery | Clerk | No usable Laravel credential after migration | Clerk-only flows; Laravel does not receive or issue passwords/reset tokens |
| Session lifecycle and logout | Clerk | No parallel Laravel customer session | Client signs out through Clerk; a rejected/revoked Clerk token is unauthenticated at Laravel |
| Email and verification | Clerk | Local email and `email_verified_at` are snapshots for application communication/display | Initial trusted retrieval, verified Clerk webhook reconciliation, and bounded reconciliation on first provision; never client-written through `/me` |
| Name | Laravel profile | Local `users.name` | `PATCH /me` remains local application-profile authority; Clerk profile is not synchronized back from Laravel |
| Phone | Laravel profile | Local `users.phone` | `PATCH /me` remains local application-profile authority; no dual-write Clerk profile behavior |
| Roles and permissions | Laravel | Local RBAC tables | Server/admin-controlled only; Clerk metadata and Organizations are not authorization inputs |
| Account business state | Laravel | `users.account_state` and local business rules | Server/admin-controlled; distinct from Clerk account/session state |
| Domain ownership | Laravel | Local FKs and policies | Derived from local authenticated principal, never a Clerk ID in request input |

The selected Clerk application's enabled factors and profile fields were not available through the current tooling. Phase 4.2 must inspect and document its configured email, name, phone, password, username, OAuth, and factor settings before implementation. No additional Clerk methods are approved by this ADR.

## Synchronization, Webhooks, and Account Lifecycle

JIT provisioning is the primary path and must not depend on webhook arrival. Clerk webhooks are a secondary, signed, idempotent reconciliation channel. Future webhook processing must verify Clerk's webhook signature before parsing or acting, use an event identifier/idempotency record, update only Clerk-owned local snapshots, and audit result metadata without retaining credentials, raw token contents, or secrets.

Expected reconciliation events include user-created/updated/deleted and session/security changes only where the selected Clerk application and official integration documentation support them. A Clerk user deletion must not hard-delete local users or historical orders, payments, audit records, requests, or enquiries. It must result in a retained local historical projection with login and new customer activity blocked. The exact local account-state/retention mechanism and cart disposition are assigned to Phase 4.6 and Group K; no record is auto-claimed or reassigned by matching email.

Authenticated request/enquiry creation continues to derive local `user_id` from the resolved Laravel principal. Anonymous submissions continue with `user_id = null`; an authenticated Clerk identity never auto-claims older anonymous submissions merely because their email matches.

## Trust Boundary Matrix

| Boundary | Trusted input | Required enforcement | Explicitly untrusted |
|---|---|---|---|
| Client -> Clerk | User credential/factor entered into Clerk UI | Clerk-owned authentication controls | Client assertion that it is authenticated, role claims supplied by UI |
| Client -> Laravel | Bearer token as a transport container | Laravel cryptographically verifies Clerk token before creating a principal | Decoded-but-unverified token contents, body/query `user_id`, `role`, `permissions`, `account_state`, `email_verified`, `clerk_user_id` |
| Clerk token -> Laravel | Verified `sub` and validated required claims | Resolve only local mapping; then apply local authorization/policies | Clerk metadata/Organizations as app role/permission authority |
| Laravel -> Clerk Backend API | Server credentials held outside source control | Call only after verified identity for provisioning/reconciliation; timeouts, allow-listed data, audit-safe logs | Email-only linking, unbounded per-request API dependency |
| Clerk -> Laravel webhook | Raw delivery plus signature | Verify signature, idempotency, event type, and payload before snapshot updates | Unsigned payload, replayed event, client-triggered webhook body |
| Local user -> commerce domain | Local `users.id` attached after authentication | Policy + ownership + business state + transaction checks | Any direct Clerk ID or client-selected customer identity |

## Frozen V1 Endpoint Impact

This is a documented post-freeze authentication change, intentionally limited to credential/session ownership. Existing commerce paths, `/me` response representation, local authorization behavior, error envelope, and roles remain stable.

| Operation | Current path | Clerk impact | V1 disposition |
|---|---|---|---|
| `AUTH-001` | `POST /auth/register` | Clerk owns sign-up and credentials | Retired from Laravel API; client uses Clerk sign-up, then calls Laravel with Clerk token |
| `AUTH-002` | `POST /auth/login` | Clerk owns sign-in and session issuance | Retired from Laravel API; client gets Clerk session token; guest-cart merge moves to authenticated Laravel cart flow after token verification |
| `AUTH-003` | `POST /auth/logout` | Clerk owns session revocation/logout | Retired from Laravel API; client performs Clerk sign-out; Laravel never invalidates a separate token |
| `AUTH-004` | `POST /auth/password/forgot` | Clerk owns password recovery | Retired from Laravel API; client uses Clerk recovery |
| `AUTH-005` | `POST /auth/password/reset` | Clerk owns password reset | Retired from Laravel API; client uses Clerk reset flow |
| `AUTH-006` | `POST /email/verify/resend` | Clerk owns verification delivery | Retired from Laravel API; client uses Clerk verification flow |
| `AUTH-007` | `POST /email/verify` | Clerk owns verification decision | Retired from Laravel API; Laravel consumes synchronized verified status only |
| `AUTH-008` | `POST /auth/change-password` | Clerk owns password change | Retired from Laravel API; client uses Clerk security flow |
| `USER-001` | `GET /me` | Requires Clerk-authenticated Laravel principal | Retained unchanged in representation and local ownership semantics |
| `USER-002` | `PATCH /me` | Laravel still owns application name/phone profile fields | Retained; email, verification, credentials, roles, account state, and Clerk ID remain rejected |

The old Laravel `AUTH-*` routes and OpenAPI schemas remain present until Phase 4.2 changes are implemented. They are documented as retired target behavior, not removed by this design phase.

## Phase 4.12 Test Matrix

| Area | Required future coverage |
|---|---|
| Token verification | Valid Clerk token authenticates; malformed, expired, `nbf`, wrong issuer, audience, `azp`, algorithm, signature, and missing `sub` reject with contract-safe `401` |
| JWKS handling | Cached key verification, known key rotation refresh, unavailable JWKS fail closed, no Backend API call for normal mapped request |
| Principal resolution | Mapping resolves correct local user; local ID not Clerk ID is attached to request; unmapped identity provisions one customer |
| Provisioning races | Parallel first requests create exactly one user/mapping and both resolve it |
| Identity safety | Email match alone never links legacy user; client role/account/identity fields cannot affect mapping or role |
| RBAC and ownership | Clerk-authenticated customer can access only own resources; staff/admin remain local policy decisions; Clerk metadata/Organizations cannot elevate access |
| Profile ownership | `/me` only updates local `name`/`phone`; rejects email, verification, role, permissions, account state, password, and Clerk ID writes |
| Webhooks | Valid signed create/update/delete reconciliation is idempotent; invalid signature/replay fails; deletion retains historical local records and blocks future activity according to final lifecycle rule |
| Session lifecycle | Clerk logout/revocation/expiry makes protected Laravel access unauthenticated; no Laravel-issued second credential exists |
| Guest handoff | Authenticated local principal can merge its guest cart; token or email does not permit another user's cart/account access |
| Regression | Public catalog and anonymous request/enquiry remain public; checkout/private resources require valid Clerk-authenticated local customer |

## Phase 4.2 Entry Criteria

Phase 4.2 may begin only after the project owner provides/authorizes the selected Clerk application's non-secret configuration values and verified official PHP/Laravel integration path. The implementation must confirm the direct dependency version before adding any package, use a new Group D migration, update Laravel route/middleware configuration, replace the placeholder Laravel auth endpoints as documented, and add the Phase 4.12 tests before declaring the authentication boundary complete.

## Phase 4.4 Security Boundary (implemented)

Clerk is the sole password-recovery/change, compromised-password, MFA, and session-lifecycle authority. Laravel reintroduces no credential path:

- **Password recovery / change:** `AUTH-004/005/008` remain RETIRED (retired in Phase 4.1; route removal targeted in Phase 4.2; per current state described in `api-contract.md §17.1` these paths return `410 GONE`). Laravel never receives or stores passwords, reset tokens, or recovery OTPs, and never runs password hashing or mutates `users.password`. The legacy `password_reset_tokens` table is dropped (Phase 4.4 migration).
- **Pending security-task sessions:** Clerk session tokens carry an `sts` claim. A `pending` session (outstanding required task such as `reset-password`, `setup-mfa`, `choose-organization`) is treated as signed-out by Clerk and is rejected by Laravel with `SESSION_EXPIRED` (401) before any local user is provisioned or resolved — no protected business API accepts it. No local `force_password_reset`/`mfa_enabled` flags are added; Clerk remains the security-state authority.
- **Session revocation:** `ClerkSessionGateway::revoke($sessionId)` (wrapping the Clerk Backend `sessions/revoke`) revokes a single session only; other active sessions stay valid. Revoke-all remains a future explicit security operation. Ordinary logout and security-incident revocation stay separate.
- **Account-state separation:** password recovery never reactivates a suspended local account and never alters local RBAC. No Staff capability for customer credential/MFA administration exists; any future Admin security operation is a dedicated audited workflow (deferred).
- **Enumeration / logging:** recovery stays generic ("Request received."); no account-lookup endpoint exists; raw `Authorization` values, session JWTs, and Clerk secrets are never logged or serialized. Laravel accepts the Clerk session token **only** via `Authorization: Bearer`; cookies are never treated as Laravel credentials (bearer-only, no cookie-CSRF model).
