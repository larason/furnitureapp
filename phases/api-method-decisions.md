# API Method Decisions — Phase 1.10

## 1.10-DEC-01 — GET is read-only, safe to retry

- **Decision:** `GET` is read-only and safe; it must not intentionally change business state. It cannot cause purchases, order state changes, or mark notifications read, and is safe to retry.
- **Reason:** HTTP safety semantics; enables caching and retry for public catalog and Next.js server rendering without side effects.
- **Alternatives:** Allowing `GET` mutations for convenience (e.g., `GET .../cancel`). Rejected — violates safety and CSRF/caching expectations.
- **Affected resources:** Product, Category, Availability, Order, Tracking, Notifications (read), Requests, Enquiries (read), Cart (read).
- **Future phase:** Endpoint catalog, caching, web rendering.

## 1.10-DEC-02 — POST is used for creation where server assigns identity

- **Decision:** `POST` to a collection is used to create a new member where the server assigns identity (e.g., cart item, request, enquiry, order via checkout, payment transaction).
- **Reason:** Collection creation semantics; server-controlled identity per naming conventions.
- **Alternatives:** `PUT` to create with client-assigned ID. Rejected — server assigns identity per `endpoint-naming-conventions.md` placeholder convention.
- **Affected resources:** Cart Item, Made-to-order Request, Enquiry, Order (via checkout workflow), Payment.
- **Future phase:** 1.11+ resource contracts, Group H (payment).

## 1.10-DEC-03 — POST is used for business actions that are state transitions

- **Decision:** Business actions that cause state transitions or side effects are modeled as explicit `POST` actions to subresources (e.g., `.../cancel`, `.../ship`) rather than generic `PATCH` of a status field, per `action-naming-policy.md`.
- **Reason:** Order state transitions require authorization, transition validation, history, and side effects; `PATCH {"status":"SHIPPED"}` bypasses invariants.
- **Alternatives:** Generic `PATCH` status field for all transitions. Rejected — bypasses domain invariants from Phase 1.3.
- **Affected resources:** Order state machine (Accept, Process, Ready-for-pickup, Ship, Deliver, Cancel, Complete).
- **Future phase:** Order management (Group I), action endpoints.

## 1.10-DEC-04 — POST business actions are not automatically idempotent

- **Decision:** `POST` business actions are not inherently idempotent; important `POST` operations (checkout, payment initiation, cancel/ship/deliver, provider callbacks) require deliberate idempotency design when duplicate requests could cause duplicate effects.
- **Reason:** `POST` HTTP semantics are non-idempotent; business operations like checkout or shipping have side effects that must not duplicate on retry.
- **Alternatives:** Assuming `POST` actions are naturally idempotent. Rejected — would allow duplicate orders/payments on double-tap or network retry.
- **Affected resources:** Checkout, Payment, critical Order actions, webhooks.
- **Future phase:** Idempotency header design (later phase), Group H.

## 1.10-DEC-05 — PATCH is for genuine partial modification with absolute assignment

- **Decision:** `PATCH` is used for partial modification where the operation represents ordinary data modification; prefer absolute state assignment (`quantity = 3`) over increment (`increment by 1`) to keep `PATCH` idempotent where possible. `PATCH` must respect domain invariants and not bypass business actions.
- **Reason:** Absolute assignment is idempotent and predictable; increment is non-idempotent and should be modeled as action if required. `PATCH` bypass of status transitions must be prevented.
- **Alternatives:** `PATCH increment` for quantity, or allowing `PATCH status` for orders. Rejected — non-idempotent cargo and invariant bypass.
- **Affected resources:** Profile, Cart Item (quantity), Product admin fields, Request/Enquiry metadata.
- **Future phase:** Cart, profile, catalog admin contracts.

## 1.10-DEC-06 — PUT is permitted only for true complete replacement, not used by default

- **Decision:** `PUT` means *replace the complete resource representation* at the target URI, not *maybe partial update*. For Version 1, `PATCH` is the normal partial-update method; `PUT` requires explicit justification and is not used unless a resource genuinely requires replacement semantics.
- **Decision result for V1:** No Version 1 endpoint currently requires `PUT`; `PATCH` is preferred for partial modification.
- **Reason:** A small project can become inconsistent if `PUT`/`PATCH`/`POST` are used without clear distinction. `PUT` ambiguity is avoided by defaulting to `PATCH`.
- **Alternatives:** Using `PUT` for partial updates. Rejected — ambiguous semantics.
- **Affected resources:** All resources where update is partial.
- **Future phase:** If a true replacement resource is identified, contract must explicitly specify `PUT` semantics.

## 1.10-DEC-07 — DELETE is true deletion only

- **Decision:** `DELETE` is used only where the business concept supports actual removal. `Order` cancellation is not `DELETE`; `Product` is generally deactivated/archived, not destroyed; `Order history` is never deleted; `Cart item` removal is `DELETE`.
- **Reason:** Business concepts are `destructible` / `deactivatable` / `cancellable` / `historically retained` — method must match semantics, not generic removal.
- **Alternatives:** `DELETE /orders/{order}` for cancellation, `DELETE /products/{product}` for deactivation. Rejected — violates business semantics (cancellation window, archival).
- **Affected resources:** Cart Item (removable), Order (cancellable via action), Product (deactivatable), Order history (retained).
- **Future phase:** Resource-specific contracts, Group I (orders), catalog management.

## 1.10-DEC-08 — Business actions are distinguished from generic updates

- **Decision:** Formal distinction between generic `PATCH` updates and controlled business state transitions (`POST` actions) is maintained. Checkout is a workflow mutation, not a generic update; inventory-critical operations are controlled operations with authorization/audit.
- **Reason:** Foundational for order API correctness and to prevent method abuse from bypassing invariants.
- **Alternatives:** Modeling all state changes as generic updates. Rejected — loses domain control.
- **Affected resources:** Orders, Checkout, Inventory, Profile (partial).
- **Future phase:** Action endpoint design (Phase 1.7 `resource-actions.md` candidates), authorization (Group D).

## 1.10-DEC-09 — Checkout is a critical mutation workflow requiring idempotency

- **Decision:** Checkout (`Cart → Order` via workflow) is a `POST` action that must support validation, pricing, inventory, fulfillment, order creation and payment initiation when applicable (provider-specific behavior in Phase Group H), and requires idempotency design for client double submission and mobile retry.
- **Reason:** Prevents accidental duplicate business transactions where frontend button disabling alone is insufficient.
- **Alternatives:** `PATCH cart → create order` or generic update semantics. Rejected — checkout is business workflow, not field update.
- **Affected resources:** Cart, Order, Payment, Inventory.
- **Future phase:** Checkout contract (Phase 1.13), Group G (checkout), Group H (payment).

## 1.10-DEC-10 — Version 1 enums are CLOSED by default (supersedes interim OPEN)

- **Decision:** All Version 1 enums (`Product Type`, `Order Status`, `Fulfillment Type`, `Roles`, `Request Status`, `Enquiry Status`, `Payment Status`) are **CLOSED** by default. Clients may assume the documented set is complete for Version 1. No `OPEN/EXTENSIBLE` interim rule remains; any future additional value requires explicit compatibility review and, if breaking, a new major version `v2`.
- **Reason:** Minimizes client breakage across Next.js, Flutter (long-lived installs), Admin, and future consumers. Supersedes the interim rule that previously treated unclassified enums as effectively open.
- **Alternatives:** Interim `OPEN/EXTENSIBLE` rule where unclassified enums were treated as open. Superseded per `phase-1.10.md` §3 Important Enum Policy.
- **Affected resources:** All enum-bearing resources (Product, Order, Payment, Request, Enquiry, User).
- **Future phase:** If a later business requirement requires a new enum value in Version 1, treat as compatibility concern requiring formal review (not additive by default).

## 1.10-DEC-11 — Payment-specific method semantics are assigned to Phase Group H

- **Decision:** Payment-specific method semantics (provider callbacks, webhook idempotency, payment confirmation not trusted from client) are assigned to **Phase Group H**. No payment provider is selected, and no provider-specific methods are defined in this phase; general `POST` workflow and idempotency principles are established only.
- **Reason:** Maintains separation of concerns; payment design is deferred per `AGENTS.md` and Phase 1.10 §3.
- **Alternatives:** Defining provider-specific methods now. Rejected — provider not selected.
- **Affected resources:** Payment, provider webhooks.
- **Future phase:** Group H (8.x), payment contract (1.14).

## 1.10-DEC-12 — Method does not determine authorization; authorization governs resource/action and actor

- **Decision:** Authorization depends on resource/action and actor, not HTTP method. Examples: `GET /products` (anonymous) vs `GET own order` (authenticated customer) vs `PATCH product` (staff/admin) vs `POST made-to-order request` (anonymous or authenticated) vs `POST checkout` (authenticated customer).
- **Reason:** Prevents method-based access assumptions and aligns with `api-resource-inventory.md` access matrix.
- **Alternatives:** `GET = public`, `POST = authenticated`, etc. Rejected — conflates method with authorization.
- **Affected resources:** All.
- **Future phase:** Authorization policies (Group D).
