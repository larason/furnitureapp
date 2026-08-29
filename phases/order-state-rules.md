# Order State Rules — Version 1

## 1. Purpose

This document defines the **order lifecycle** for Version 1 in business terms:

- Pickup lifecycle
- Delivery lifecycle
- Allowed conceptual transitions
- Invalid transition principles
- Cancellation rule
- Tracking milestones
- Status history requirements

The **exact technical state-machine implementation** (storage, enforcement mechanism, concurrency guards) is deferred to later phases. These rules are the contract those phases must satisfy.

The full, exhaustive transition matrix with every edge case is defined in the later order-State contract phase; this document fixes the principles and the primary permitted paths.

---

## 2. Order Statuses (Version 1)

| Status | Meaning |
|---|---|
| `PENDING_PAYMENT` | Order created; payment not yet confirmed. |
| `PAID` | Payment confirmed by the backend. |
| `ACCEPTED` | Business accepted the order. |
| `PROCESSING` | Business is preparing the order. |
| `READY_FOR_PICKUP` | Pickup order is ready for the customer to collect. |
| `SHIPPED` | Delivery order has left the business. |
| `DELIVERED` | Delivery order reached the customer. |
| `COMPLETED` | Order finished (collected or delivered). |
| `CANCELLED` | Order ended before completion. |

All state names and rules are governed by [ORD-007] and [ORD-008] in `domain-invariants.md`.

---

## 3. Pickup Lifecycle

```text
PENDING_PAYMENT
        ↓
PAID
        ↓
ACCEPTED
        ↓
PROCESSING
        ↓
READY_FOR_PICKUP
        ↓
COMPLETED
```

Rules:

- [OS-001] A pickup order follows the pickup path above.
- [OS-002] A pickup order must **never** enter `SHIPPED` or `DELIVERED`.
- [OS-003] In the pickup path, the customer-collection milestone is `READY_FOR_PICKUP`.
- [OS-004] A pickup order completes at `COMPLETED` (after collection).

## 4. Delivery Lifecycle

```text
PENDING_PAYMENT
        ↓
PAID
        ↓
ACCEPTED
        ↓
PROCESSING
        ↓
SHIPPED
        ↓
DELIVERED
        ↓
COMPLETED
```

Rules:

- [OS-005] A delivery order follows the delivery path above.
- [OS-006] A delivery order must **not** skip required operational stages (e.g., it cannot jump from `PENDING_PAYMENT` to `DELIVERED`).
- [OS-007] A delivery order reaches the customer at `DELIVERED`.
- [OS-008] A delivery order completes at `COMPLETED` (after confirmed delivery).

---

## 5. Allowed Conceptual Transitions

### Primary forward transitions

```text
PENDING_PAYMENT → PAID
→ ACCEPTED
→ PROCESSING
→ READY_FOR_PICKUP (pickup) | SHIPPED (delivery)
→ (pickup) COMPLETED   | (delivery) DELIVERED → COMPLETED
```

### Cancellation

```text
<eligible order states> → CANCELLED
```

- [OS-009] Cancellation to `CANCELLED` is only allowed by actors and within windows approved by the rules (customer: 20-minute window [CANCEL-001]; staff/admin: per later authorization, not 20-minute-bound [CANCEL-004]).
- [OS-010] Cancellation must be a valid transition from the order's current state; it is not permitted from arbitrary states ([CANCEL-005]).
- [OS-011] `PAID → REFUND...` pathways and any refund-related states are **not** defined at this stage; refunds are a later payment/order decision and are separate from order cancellation ([CANCEL-003]).

---

## 6. Invalid Transition Principles

- [OS-012] The system must not allow arbitrary forward jumps. Example (`INVALID`):

  ```text
  PENDING_PAYMENT
        ↓
  DELIVERED
  ```

  is rejected because the preceding business events/states did not occur.

- [OS-013] The system must not allow backward movement (e.g., `COMPLETED` → `PROCESSING`) unless a future approved rule explicitly allows it.
- [OS-014] Clients (website, app, admin UI) never invent or force a status change. Any transition is backend-validated ([ORD-007]).
- [OS-015] A transition that is not in the allowed set must be rejected, and the rejection must not corrupt the current order state.

---

## 7. Cancellation Rule

- [OS-016] Customer self-service cancellation is permitted only within **20 minutes of order creation** ([CANCEL-001]).
- [OS-017] The backend computes eligibility from its own clock. A client-supplied timestamp is never trusted ([CANCEL-002]).
- [OS-018] Cancellation and refund are separate: cancellation does not automatically refund ([CANCEL-003]).
- [OS-019] Staff/admin cancellation is a distinct operation; its authorization and conditions are defined in Phase Group D and later order operations ([CANCEL-004]).

---

## 8. Tracking Milestones

- [OS-020] The customer tracking view shows only milestones applicable to the order's fulfillment method ([FUL-006]).
- [OS-021] Pickup orders show: Paid, Accepted, Processing, **Ready for Pickup** (not Shipped/Delivered).
- [OS-022] Delivery orders show: Paid, Accepted, Processing, **Shipped**, **Delivered**.
- [OS-023] `COMPLETED` appears as the final milestone for both paths.
- [OS-024] A cancelled order represents its cancellation as its terminal outcome in tracking.
- [OS-025] Version 1 tracking is **status tracking**, not GPS/live courier tracking.

---

## 9. Status History Requirements

- [OS-026] Current status alone is insufficient; every significant status change is recorded ([ORD-008]).
- [OS-027] Each history record captures at least:

  ```text
  Previous status
  New status
  Time (backend-authoritative)
  Actor/source (customer, staff, admin, system)
  Optional note
  ```

- [OS-028] The history powers the customer tracking timeline and operational auditability.
- [OS-029] History is append-only for audit integrity: past records are not mutated/erased by later transitions.

---

## 10. Relationship to Other Rules

- Payment state is **distinct** from order state ([PAY-001]); this document describes order state only.
- The full exhaustive transition matrix (including all `CANCELLED` entry states and any staff-admin transitions) is finalized in the later order-state contract phase.
- These rules satisfy Phase 1.3 §15–§18 and are traceable to `domain-invariants.md` IDs [ORD-007], [ORD-008], [CANCEL-001..005], [FUL-002], [FUL-005], [FUL-006].