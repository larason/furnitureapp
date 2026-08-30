# API Method Security Rules — Version 1

## 1. Purpose
Documents security implications of HTTP methods, including safe vs unsafe, authorization, logging, retries, and CSRF/caching considerations. No authentication/authorization implementation is defined here.

## 2. Safe vs Unsafe
- **Safe:** `GET`, `HEAD`, `OPTIONS` — no intentional business-state change; safe to cache (where appropriate) and retry. Method safety concerns business-state changes only: a safe request can still carry PII or credentials (query strings, headers, response data), so logging any method requires appropriate redaction, access controls, and retention rules (see §5).
- **Unsafe:** `POST`, `PATCH`, `PUT`, `DELETE` — may change business state, require audit/logging, and have retry/idempotency implications.

Never design `GET` mutations (`GET /orders/{order}/cancel`) as a shortcut. Safe methods must remain safe (§48).

## 3. Authorization Is Not Method-Based
Do **not** assume `GET = public`, `POST = authenticated`, `PATCH = admin`. Authorization depends on resource/action and actor:
- `GET /api/v1/products` → anonymous (public catalog)
- `GET /api/v1/me/orders/{order}` → authenticated customer (own order)
- `PATCH /api/v1/products/{product}` → authorized staff/admin
- `POST /api/v1/requests` → anonymous (with contact) or authenticated
- `POST /api/v1/me/cart/checkout` → authenticated customer
- `POST /api/v1/orders/{order}/ship` → staff/admin

Method does not determine authorization; resource/action and actor do.

## 4. Safe/Idempotent Matrix (HTTP Semantics)

| Method / operation | Safe | Idempotent (HTTP) | Business operation idempotency must be evaluated |
|---|---|---|---|
| `GET` | Yes | Yes | No business effects from repeat |
| `PUT` | No | Yes | Replace complete resource; business outcome defined |
| `DELETE` | No | Generally yes | First removes, second already removed (response contract later) |
| `PATCH` | No | Depends — contract must specify | Prefer absolute assignment for idempotent PATCH |
| `POST` create | No | No by default | Requires idempotency where flagged |
| `POST` business action | No | No by default | Requires idempotency where flagged |

Business operation idempotency (e.g., `Cancel order` as `POST`) is separate from HTTP method idempotency.

## 5. Logging and Audit
- Mutation methods (`POST`, `PATCH`, `PUT`, `DELETE`) for privileged operations should be visible in application logs/audit systems (especially `POST` actions for orders, payments, inventory, request/enquiry status).
- Do not log sensitive payloads blindly (credentials, payment secrets, personal contact where unnecessary). Use structured, redacted logging.

## 6. Retry Behavior
- Clients may safely retry read operations (`GET`).
- Mutation retries require operation-specific analysis: `checkout`, `payment`, `stock-affecting actions`, `order transitions` must not be retried blindly without considering duplicate effects (see `api-idempotency-candidates.md`). Frontend button disabling alone is not sufficient.

## 7. CSRF Considerations
- CSRF requirements depend on the later authentication mechanism (session/cookie vs token/bearer for API). Safe methods must remain safe regardless.
- Do not rely on framework-specific method spoofing (e.g., `_method=DELETE`) as public API design. The public contract describes real HTTP semantics (`GET`, `POST`, `PATCH`, `DELETE`).

## 8. Caching
- `GET` is the primary method eligible for caching (public product catalog, category pages, Next.js server rendering). Mutation methods should not be casually cached as if they were reads. No caching is configured in this phase.

## 9. Sensitive Data Handling
- `PATCH`/`POST` to `User/Profile` must not allow arbitrary modification of protected identity/security information (role, credentials) via generic update. Such changes are controlled operations with appropriate authorization.
- Authentication operations (`login`, `logout`, `password reset`, `email verification`) are not generic CRUD (`PATCH /users`) but explicit authentication contract operations (deferred).

## 10. Payment and Webhook Security
- Payment creation/initiation may use `POST`; payment confirmation is **not** trusted from the client; provider callbacks/webhooks are event-driven `POST` operations (Group H) with idempotency and signature verification. Duplicate provider delivery must not create duplicate business effects.
- Webhook handling and payment provider secrets belong to **Phase Group H**; no provider is selected here.

## 11. Version 1 Enum Policy
All Version 1 enums (`Product Type`, `Order Status`, `Fulfillment Type`, `Roles`, `Request Status`, `Enquiry Status`, `Payment Status`) are **CLOSED** by default per `phase-1.10.md` §3. Method conventions do not depend on clients accepting unspecified future values; any future additional value requires explicit compatibility review and, if breaking, a new major version `v2`. No `OPEN/EXTENSIBLE` interim rule remains.
