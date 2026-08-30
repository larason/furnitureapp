# HTTP Method Conventions — Version 1

## 1. Purpose
Defines the project's HTTP method conventions before the complete endpoint catalog is designed. Ensures every later endpoint maps a business operation to a predictable method, with clear semantics for safety, idempotency, and retry behavior.

**Authoritative inputs:** `AGENTS.md`, `docs/VISION.md`, `logical-data-model-v1.md` v1.0, `api-resource-inventory.md`, `api-resource-relationships.md`, `endpoint-naming-conventions.md`, `api-versioning-strategy.md` / `api-breaking-change-policy.md` (Version 1 enums are **CLOSED** by default — `phase-1.10.md` §61).

Payment-specific method semantics are deferred to **Phase Group H**.

## 2. Core Principle
HTTP methods describe the **type of operation**, not implementation. Use semantics, not convenience:
- `GET` → read
- `POST` → create or perform an action
- `PATCH` → partial modification (ordinary data)
- `PUT` → complete replacement (only where genuinely appropriate)
- `DELETE` → remove/delete according to resource semantics

Do not use `POST` for everything.

## 3. Safe vs Unsafe
- **Safe (no intentional business-state change):** `GET`, `HEAD`, `OPTIONS`.
- **Unsafe (may change business state, requires audit/logging):** `POST`, `PATCH`, `PUT`, `DELETE`.
Safe methods must remain safe — never use `GET /orders/{order}/cancel` or `GET /orders/{order}/ship` for mutations.

## 4. Idempotency Expectations (HTTP Semantics)
| Method / operation type | Expected HTTP idempotency | Notes |
|---|---|---|
| `GET` | Yes — repeatable, no business effects | Client may retry safely |
| `PUT` | Yes — complete replacement | Idempotent if used (V1: only where justified) |
| `DELETE` | Generally yes — first request removes, second is already removed (response defined later) | Business outcome subject to contract |
| `PATCH` | Depends on operation; contract must specify | Prefer absolute state assignment to keep idempotent |
| `POST` create | No by default | Requires idempotency design where duplicate creates duplicate effects |
| `POST` business action | No by default | Requires idempotency design where flagged |

Idempotency must be evaluated for the **actual business operation**, not only the HTTP method.

## 5. GET — Read Operations
- Use `GET` for retrieval: product collection, individual product, category, variants, public availability, customer order, tracking, notifications, request, enquiry.
- A successful `GET` must not intentionally change business state (no orders/payments/requests created, no inventory changes, no status transitions, no read-state changes).
- Examples: `GET /api/v1/products`, `GET /api/v1/categories/{category}`, `GET /api/v1/products/{product}/variants` (read), `GET /api/v1/me/orders/{order}/tracking`.

## 6. GET Safety and Caching
- `GET` is safe to retry and the primary method eligible for caching (public catalog, Next.js server rendering). Mutation methods must not be cached as reads. No caching is configured in this phase.

## 7. POST — Creation
- Use `POST` to a collection where the server assigns identity.
- Conceptual examples: `POST` to create cart item, order through checkout, made-to-order request, enquiry, payment transaction. Exact endpoints are future work.

## 8. POST — Business Actions
- Use `POST` for actions that cause a state transition, trigger side effects, or cannot be safely represented as simple field updates: `Checkout`, `Cancel order`, `Accept order`, `Ship order`, `Mark delivered`, `Submit request/enquiry`, `Initiate payment`.
- Do not model `PROCESSING → SHIPPED` as `PATCH order.status = SHIPPED` when the operation requires authorization, transition validation, inventory/delivery checks, audit history, and notifications. Such transitions must be explicit controlled actions (POST to action subresource, naming per `action-naming-policy.md`: `/orders/{order}/ship`).

## 9. POST Idempotency
`POST` is not inherently idempotent. Flagged operations require deliberate idempotency design for duplicate requests (client double-tap, network retry):
- Checkout / order creation
- Payment initiation
- Payment callback handling (provider side)
- Inventory-affecting admin operations
- Critical order actions (cancel, ship, deliver) where retry must not cause multiple refund/shipping events

Actual header/mechanism (e.g., `Idempotency-Key`) is later (Phase 1.10 §58), not implemented here.

## 10. PATCH — Partial Modification
- Use `PATCH` for ordinary partial modification of an existing resource.
- Examples: update profile information, update cart item quantity to absolute value `quantity = 3`, update product information in admin, update request/enquiry administrative metadata.
- **PATCH must respect domain invariants:** Do not allow arbitrary `{"status": "DELIVERED"}` via `PATCH` if moving to `DELIVERED` requires valid previous state, staff authorization, delivery conditions, and history. Such changes must use controlled actions, not `PATCH`.

## 11. PATCH Semantics for Quantity
- Prefer absolute state assignment: `quantity = 3` (idempotent) rather than `increment by 1` (non-idempotent) for ordinary `PATCH` fields. If increment semantics are required, model them as an explicit action, not a plain `PATCH`.

## 12. PUT — Use Sparingly
- **Policy:** `PUT` is permitted only for true complete replacement resources and should not be used by default. For Version 1, `PATCH` is the normal partial-update method.
- If no Version 1 resource requires genuine replacement semantics, the project documents: **No Version 1 endpoint currently requires `PUT`; `PATCH` is preferred for partial modification.**
- If `PUT` is ever used, it means *replace the complete resource representation at the target URI*, not *maybe partial update*. No ambiguous `PUT` behavior.

## 13. DELETE — True Deletion Only
- Use `DELETE` only where the business concept supports actual removal.
- Potentially: remove cart item, remove temporary resource, remove an attachment where permitted.
- Do **not** model order cancellation as `DELETE /api/v1/orders/{order}`. Cancellation is a controlled business action (`POST .../cancel`) with 20-minute window, status eligibility, payment implications, and audit requirements.
- Classify domain resources conceptually as `destructible` / `deactivatable` / `cancellable` / `historically retained` (e.g., `Order` → historically retained, `Product` → deactivatable/archived, `Order status history` → retained, `Cart item` → removable, `Request` → operationally retained). Implementation later.

## 14. Method vs Authorization, Status, and Logging
- Method does **not** determine authorization: `GET /products` (anonymous) vs `GET own order` (authenticated customer) vs `PATCH product` (staff/admin) vs `POST made-to-order request` (anonymous or authenticated) vs `POST checkout` (authenticated customer).
- Method does not communicate business success/failure; HTTP status conventions are later. `POST` remains the operation even when validation fails.
- Mutation methods (`POST`, `PATCH`, `PUT`, `DELETE`) for privileged operations should be visible in application logs/audit systems. Do not log sensitive payloads blindly.

## 15. CSRF, Caching, and Retry Principles
- CSRF requirements depend on the later authentication mechanism (session vs token). Do not design around `GET` mutations as a shortcut; safe methods must remain safe.
- `GET` is cache-eligible (catalog, Next.js server rendering); mutations are not casually cached.
- Retry principle: Clients may safely retry `GET`. Mutation retries (checkout, payment, stock-affecting actions, order transitions) must not be retried blindly without considering duplicate effects — require operation-specific analysis and, where flagged, idempotency.

## 16. Webhooks and Payment Callbacks
- Payment creation/initiation may use `POST`; payment confirmation is **not** trusted from the client; provider callbacks/webhooks are event-driven `POST` operations with idempotency (duplicate provider delivery must not create duplicate business effects). Provider-specific semantics are **Phase Group H**.

## 17. Version 1 Closed-Enum Policy Validation
Per `phase-1.10.md` §61, all Version 1 enums (`Product Type`, `Order Status`, `Fulfillment Type`, `Roles`, `Request Status`, `Enquiry Status`, `Payment Status`) are **CLOSED** by default. HTTP method conventions do not depend on clients accepting unspecified future enum values; any future additional value requires explicit compatibility review and, if breaking, a new major version `v2`. This supersedes any prior `OPEN/EXTENSIBLE` interim interpretation.

## 18. Out of Scope
No complete endpoint list, endpoint URLs, request/response payloads, status codes, authentication/authorization implementation, idempotency headers, Laravel routes/controllers/middleware, OpenAPI operations, or payment provider selection are defined in this phase.
