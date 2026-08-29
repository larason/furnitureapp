# Logical Model Risk Review — Version 1 (Phase 1.5)

## 1. Purpose

This document records risks identified in the logical data model. Each risk notes the mitigating design decision already in the model and any residual risk carried forward to later phases. It is an input to the physical design and implementation phases, not a blocker.

---

## 2. Risk Register

### R-01 — Historical data corruption

- **Description:** Current catalog changes (rename, repricing, deactivation) must never rewrite historical orders.
- **Mitigation in model:** Order Item stores purchase-time snapshots (name, SKU, unit price, quantity, subtotal); delivery fee, totals, and addresses are snapshots (`historical-data-rules.md`); referenced products/variants must not be hard-deleted (archival/tombstone).
- **Residual risk:** Physical design must enforce immutability of snapshot columns and archive rather than delete referenced catalog rows.
- **Owner phase:** Phase 1.6 (physical design), Phase Group I (orders).

### R-02 — Overselling / negative stock

- **Description:** Concurrent checkout could allocate more units than exist.
- **Mitigation in model:** Inventory distinguishes physical/reserved/available; reservation invariant (reject above `physical − reserved`; `0 <= reserved <= physical`); concurrency requirements CONC-001, INV-002/003; atomic order+reservation (DATA-004).
- **Residual risk:** Real enforcement requires transactional/concurrency controls in the checkout/inventory implementation.
- **Owner phase:** Phase Group G (checkout), Phase Group E (inventory), Phase 1.6 (indexes/constraints).

### R-03 — Ambiguous ownership

- **Description:** A business fact owned by two domains could drift.
- **Mitigation in model:** `data-ownership.md` gives each fact one authoritative owner (price → Catalog, stock → Inventory, historical price → Order Item, status → Order, payment confirmation → Payment, etc.).
- **Residual risk:** Future phases must not re-introduce duplicate mutable copies without a documented snapshot purpose.
- **Owner phase:** All downstream data/API phases.

### R-04 — Missing anonymous workflow support

- **Description:** Anonymous browsing, requests, and enquiries must not require a User.
- **Mitigation in model:** PUBLIC catalog entities have no User dependency; Request/Enquiry have optional User with required contact info; guest cart has guest identity (DM-DEC-04).
- **Residual risk:** API contract must keep public reads and anonymous submissions on unauthenticated routes.
- **Owner phase:** Phase 1.11/1.17/1.18 (contracts), Phase Group E (catalog).

### R-05 — Excessive duplication

- **Description:** Unnecessary duplicate data can cause drift.
- **Mitigation in model:** Available quantity is derived (not stored); totals are derived/snapshot at finalization; cart price is non-authoritative; no generic tables.
- **Residual risk:** Physical design must not introduce premature denormalization without a documented purpose.
- **Owner phase:** Phase 1.6 (physical design).

### R-06 — Privacy leakage

- **Description:** Private/sensitive data could be exposed through a public serializer.
- **Mitigation in model:** `data-classification.md` (PUBLIC/INTERNAL/PRIVATE/SENSITIVE) and `api-exposure-classification.md` (PUBLIC/AUTHENTICATED/GUEST_TOKEN/STAFF/ADMIN/INTERNAL ONLY); order reference PRIVATE; owner timeline excludes staff Operational note.
- **Residual risk:** API resources must be built against the exposure matrix; authz enforced in Group D.
- **Owner phase:** Phase 1.7/1.8 (response/error contract), Phase Group D.

### R-07 — Premature complexity

- **Description:** Enterprise-scale structures could bloat Version 1.
- **Mitigation in model:** Complexity review rejected warehouse/ERP/marketplace/tax/multi-currency/fleet/supplier/loyalty concepts; flat categories; lightweight notifications; no full inventory-history subsystem (DM-DEC-05).
- **Residual risk:** Future phases should resist scope creep; change-control rule (§49 of Phase 1.5) applies after freeze.
- **Owner phase:** All future phases.

### R-08 — Payment/order state conflation

- **Description:** Collapsing payment state and order state corrupts finance vs fulfilment.
- **Mitigation in model:** Payment entity separate from Order status (PAY-001); payment state transitions backend-driven (PAY-004); webhook idempotency (IDEMP-003).
- **Residual risk:** Physical design and payment service must keep the two state machines distinct.
- **Owner phase:** Phase 3.12 (payment schema), Phase Group H (payments).

### R-09 — Reservation strand (stock leak)

- **Description:** Reserved inventory might never be released, stranding sellable stock.
- **Mitigation in model:** Reservation invariant includes release on cancellation, reservation timeout/expiry, and permanent payment failure; payment failure leaves `PENDING_PAYMENT` with reservation intact pending release.
- **Residual risk:** The exact timeout policy is a later checkout/payment decision; must be implemented with the reservation.
- **Owner phase:** Phase Group G/H (checkout/payments).

### R-10 — Guest-cart authorization confusion

- **Description:** Guest bearer-token access could be implemented as a user session or overly broad authenticated check.
- **Mitigation in model:** `GUEST_TOKEN` (holder) audience defined distinct from `AUTHENTICATED` (owner); holder-owner checks required.
- **Residual risk:** Cart-phase implementation must implement holder-scoped identity, not login-required.
- **Owner phase:** Phase 1.12 (cart contract), Phase Group F (cart API).

### R-11 — Delivery-fee/address snapshot vs rule drift

- **Description:** If delivery fee rules or addresses change, historical orders must keep the charged fee and the used address; Order and Delivery must not diverge.
- **Mitigation in model:** Order.Delivery fee is the canonical snapshot (in the authoritative total); Order Address (delivery) is the canonical address/recipient/phone snapshot. Delivery carries non-authoritative projections copied once at creation and never edited independently, so no divergence path exists. The staff-managed rule is configuration, not part of the order.
- **Residual risk:** Checkout must resolve the fee from staff rules and persist the canonical Order snapshot (and the Delivery projection) atomically with the order.
- **Owner phase:** Phase 1.13 (checkout contract), Phase Group G.

### R-12 — Input file location (documentation note)

- **Description:** Phase instructions reference `VISION.md` and `agent.md`; the actual files are `docs/VISION.md` and `AGENTS.md` (project root). `docs/VISION.md` was reviewed and is consistent with the model.
- **Mitigation:** Both inputs located and reviewed; AGENTS.md is the governing project guide.
- **Residual risk:** None for the model.
- **Owner phase:** — (documentation).

---

## 3. Risk Summary

| Category | Risks |
|---|---|
| Historical/data integrity | R-01, R-02, R-09 |
| Ownership/duplication | R-03, R-05 |
| Anonymous workflows | R-04 |
| Privacy/security | R-06, R-10 |
| State conflation | R-08 |
| Complexity/scope | R-07, R-12 |
| Money/fee integrity | R-11 |

All risks have an in-model mitigation or an explicit deferral with an owning later phase. **No risk blocks freezing the logical model.** Residual risks are implementation-phase responsibilities and must be revisited in Phase 1.6 and the relevant Phase Groups.