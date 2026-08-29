# Logical Model Consistency — Version 1

## 1. Purpose

This document shows consistency of the logical data model across the four source phases:

- Phase 1.1 — API scope and approved business decisions (`api-scope-v1.md`)
- Phase 1.2 — Domain nouns and boundaries (`domain-glossary.md`, `domain-boundaries.md`, `domain-decisions.md`)
- Phase 1.3 — Domain invariants and business rules (`domain-invariants.md`, `business-rules.md`, `order-state-rules.md`)
- Phase 1.4 — Logical data model (`logical-data-model.md` and supporting documents)

For each area the matrix records `CONSISTENT` or `CONFLICT`. There must be no unresolved conflict before freeze.

---

## 2. Contradiction Matrix

| Area | Phase 1.1 | Phase 1.2 | Phase 1.3 | Phase 1.4 | Status |
|---|---|---|---|---|---|
| **Authentication** | Guest browsing allowed; checkout requires account | User = single identity; guest visitor vs authenticated customer | IDENT-001..008; anonymous browse/request/enquiry; checkout auth | User entity; PUBLIC catalog; order requires User | **CONSISTENT** |
| **Product types** | `IN_STOCK` / `MADE_TO_ORDER` | Product type controls action | CAT-002/003; MTO not purchasable | Product.Product type | **CONSISTENT** |
| **Inventory** | Physical/reserved/available; no overselling | Product ≠ Inventory; variant-level possible | INV-001..007; no negative/oversell | Inventory entity; available derived; reservation invariant | **CONSISTENT** |
| **Checkout** | Account-required checkout | Cart not reservation; backend revalidates | CHECKOUT-001..006 | Checkout revalidation; order creation via purchase flow | **CONSISTENT** |
| **Fulfillment** | PICKUP free; DELIVERY fee | Two modes; pickup free; delivery fee | FUL-001..006 | Order.Fulfillment type; Delivery entity conditional | **CONSISTENT** |
| **Delivery fee** | Variable, staff-controlled | Region/location-based (Decision 19) | PRICE-006; FUL-003 | Order.Delivery fee snapshot; fee-rule config deferred to Phase 1.13/3.13 | **CONSISTENT** (deferral documented) |
| **Cancellation** | 20-minute window | Time-bounded, backend-evaluated | CANCEL-001..005; OS-016..019 | Order created timestamp; status history actor; window concept | **CONSISTENT** |
| **Orders** | Lifecycle states; tracking | Order = purchase record; snapshots | ORD-001..008; OS rules | Order, Order Item (snapshots), Order Status History | **CONSISTENT** |
| **Payments** | Provider-agnostic; deferred | Payment distinct from order state | PAY-001..004 | Payment entity generic; provider fields conditional | **CONSISTENT** (group-label note below) |
| **Requests** | MTO request anonymous; not an order | Lead, not order; may reference product/user | REQ-001..006 | Made-to-order Request entity (User/Product optional) | **CONSISTENT** |
| **Enquiries** | Enquiry anonymous; separate | Enquiry ≠ Request | ENQ-001..004 | General Enquiry entity (User optional) | **CONSISTENT** |
| **Attachments** | Optional on requests/enquiries | Belongs to request or enquiry; storage deferred | ATTACH-001..003 | Attachment entity (polymorphic owner, optional) | **CONSISTENT** |
| **Notifications** | Email delivery deferred to Group R | Notification = event record | NOTIF-001..003 | Notification entity (lightweight event record) | **CONSISTENT** |
| **Roles** | Staff/admin permission deferred to Group D | Roles CUSTOMER/STAFF/ADMIN on User | AUTHZ-001..004 | User.Role; authorization deferred | **CONSISTENT** |
| **Addresses** | Saved address book deferred | Billing/delivery distinct; transaction data | ADDR-001..004 | Order Address typed billing/delivery (0..1) | **CONSISTENT** |
| **Order reference** | `OD-` prefix | `OD-*****` format | ORD-006 globally unique | Order reference unique, immutable | **CONSISTENT** |
| **Currency** | — | Single currency TZS (Decision 16) | PRICE-003 | Currency on Order/Payment; DM-DEC-13 safe money | **CONSISTENT** |
| **Cart ownership** | Guest cart (Phase 1.2 Q1 answer) | Cart not strictly bound to User | IDENT-008, CART-002 | Cart guest identity or User owner (DM-DEC-04) | **CONSISTENT** |
| **Request→Order** | Never automatic | Decision 20: leads only | REQ-005 | No request→order link | **CONSISTENT** |

---

## 3. Result

**No unresolved logical-model conflicts found.**

Every area is `CONSISTENT`. Two documentation-level notes are recorded (both non-blocking, previously tracked):

### 3.1 Payment phase-group label (known, documented)

`phase-1.1-api-scope.md` decision 6 and `phase-1.3.md` §22.2 historically referenced "Phase Group G" for payment provider selection; AGENTS.md (governing roadmap) places payments in **Phase Group H**. `phase-1.4.md` has been aligned to "Phase Group H". This is a documentation-only label discrepancy; the business deferral is identical in all phases. Tracked in `domain-conflicts.md` §2.

### 3.2 Input file locations (documentation note)

The phase instructions reference `VISION.md` and `agent.md`. In this repository `VISION.md` is located at `docs/VISION.md` and the governing guide is `AGENTS.md` (project root; the instructions' `agent.md` refers to this file). `docs/VISION.md` was reviewed and is consistent with the frozen model (product types, order lifecycle, status history, three workflows, admin operations). No conflict results.

---

## 4. Cross-Phase Decision Continuity

The following decision chains are consistent end-to-end (Phase 1.1 → 1.4), confirming no drift:

- Guest browsing → IDENT-001/003 → PUBLIC catalog data (no User dependency).
- Guest cart → IDENT-008/CART-002/DM-DEC-04 → Cart with guest identity or User owner.
- Checkout requires account → IDENT-002/CHECKOUT-001/ORD-002 → Order requires User (customer).
- Variable staff delivery fee → PRICE-006/Decision 19 → Order delivery-fee snapshot; fee-rule configuration deferred.
- 20-minute cancellation → CANCEL-001/002 → order created timestamp + backend window.
- `OD-` reference → ORD-006 → globally unique, immutable order reference.
- Anonymous requests/enquiries → REQ-001/ENQ-001 → optional User on both entities.
- Payment provider deferred → PAY-003/DM-DEC-08 → provider-agnostic Payment entity (Group H).
- Email delivery deferred → NOTIF-002/003 → Notification event record only (Group R).
- Saved address book deferred → ADDR-001/002 → Order Address transaction data only.
- MTO request never an order → REQ-005/Decision 20 → no request→order relationship.
- Product snapshots → ORD-005/DATA-002 → Order Item snapshots.
- Safe money → PRICE-004/DM-DEC-13 → discrete money representation.
- Single currency → PRICE-003/Decision 16 → TZS on Order/Payment.

---

## 5. Conclusion

The logical data model is **consistent** across Phases 1.1–1.4. No area requires a business-rule change before freezing. The two notes in §3 are documentation-level and non-blocking.