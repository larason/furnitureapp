# API Idempotency Candidates — Version 1

## 1. Purpose
Documents operations that require later idempotency design before implementation. No header names or mechanisms are implemented in this phase; only candidates are flagged for Phase 1.10 §58 and later idempotency phase.

## 2. Candidates Flagged

| Operation | Method / Type | Why idempotency is required |
|---|---|---|
| **Checkout / Order creation** (`Cart → Order`) | `POST` workflow/action | Client double-tap "Place Order" or mobile retry on apparent disconnect must not create duplicate orders. Requires strong duplicate-prevention. |
| **Payment initiation** (`Order → Payment`) | `POST` workflow | Retry must not create duplicate payment transactions. |
| **Payment callback handling** (provider → backend) | `POST` event (webhook) | Duplicate provider delivery (webhook redelivery) must not create duplicate business effects (orders/payments/status). Requires idempotent handling. |
| **Inventory-affecting admin operations** (e.g., stock adjustments where user may retry) | `POST`/`PATCH` admin operations | Duplicate increment must not double-adjust stock. Prefer absolute assignment or idempotency key where applicable. |
| **Cancel order** (`POST .../cancel`) | `POST` action | Retry must not cause multiple refund/payment effects. Needs deliberate idempotency design. |
| **Ship order** (`POST .../ship`) | `POST` action | Repeated request must not repeatedly trigger shipping side effects. |
| **Deliver order** (`POST .../deliver`) | `POST` action | Repeated request must not generate duplicate `DELIVERED` events. |
| **Accept / Process / Ready-for-pickup / Complete** | `POST` actions | Critical order transitions with side effects (history, notifications) — classify as `IDEMPOTENCY REQUIRED` where side effects exist. |
| **Create enquiry** (via `POST`) | `POST` create | Repeated submission may legitimately create two enquiries, but client retry behavior must be considered; evaluate per contract whether idempotency is desired vs allowing duplicates. |
| **Create request** (similar) | `POST` create | Same as enquiry — evaluate duplicate intent. |

## 3. Categories
- `IDEMPOTENCY REQUIRED` — Checkout, Payment initiation, Payment callbacks, Cancel, Ship, Deliver, other critical order actions.
- `IDEMPOTENCY NOT REQUIRED` — Idempotent-safe reads (`GET`), absolute `PATCH` assignments (`quantity = 3`), `PUT`/`DELETE` where HTTP semantics already idempotent and business outcome is defined.
- Non-idempotent by design if additive (e.g., `increment_quantity = 1` via `PATCH`) — avoid; prefer absolute assignment unless contract explicitly defines increment as action.

## 4. Design Considerations for Later Phase
- Exact header name (e.g., `Idempotency-Key`) and semantics belong to a later idempotency phase — not implemented here.
- Conditional request considerations (future): `ETag` / `If-Match` / version field / optimistic locking for `inventory`, `orders`, `admin updates` — recorded as future design consideration, not implemented.
- Client guidance: Clients may safely retry `GET`. Mutation retries for flagged operations must not be retried blindly without considering duplicate effects.

## 5. Payment Note
Payment idempotency (provider selection, webhook verification, retry handling) is assigned to **Phase Group H**. This document identifies the candidates; Group H will define provider-specific idempotency handling.
