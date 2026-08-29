# Data Model Decisions — Phase 1.4

## 1. Purpose

Records the decisions made during Phase 1.4 (logical data model). Each decision includes decision ID, decision, reason, affected entity, and future implementation phase.

Decisions from Phase 1.1–1.3 remain authoritative and are not re-opened.

---

## DM-DEC-01 — Single-level (flat) category structure

- **Decision:** Categories use a flat, single-level structure in Version 1; no parent/child hierarchy.
- **Reason:** The Version 1 catalog does not require nested categories; keep the smallest structure that supports browsing (Phase 1.4 §10).
- **Affected entity:** Category.
- **Future implementation phase:** Phase 3.3 (categories schema), Phase Group E (catalog API).

## DM-DEC-02 — Variant is a first-class purchasable unit

- **Decision:** When a product offers variants, the variant is the purchasable unit and may carry its own SKU, price override, and inventory. A product without variants is purchasable directly.
- **Reason:** Phase 1.2 decision 3; variants may carry their own stock/pricing when required.
- **Affected entity:** Product, Product Variant, Inventory, Cart Item, Order Item.
- **Future implementation phase:** Phase 3.5 (variants), Phase 3.7 (inventory), Phase Group E.

## DM-DEC-03 — Available quantity is derived, not separately stored

- **Decision:** Available quantity is a **derived** value computed from authoritative stock and reserved quantities; it is not independently stored (no separate mutable `available_quantity` unless a later performance case justifies a cache).
- **Reason:** Avoids duplication of a derived mutable fact and prevents drift (Phase 1.4 §14); the logical requirement is that the authoritative available quantity can always be determined.
- **Affected entity:** Inventory.
- **Future implementation phase:** Phase 3.7 (inventory schema), Phase Group E (inventory read model).

## DM-DEC-04 — Guest carts are represented with guest identity

- **Decision:** Carts are not strictly bound to a User. Guest carts carry a guest identity (mechanism defined in the cart phase) and are associated with the User upon authentication; checkout requires an authenticated customer.
- **Reason:** Phase 1.2 decision 18 (owner answer to open question 1).
- **Affected entity:** Cart, Cart Item, User.
- **Future implementation phase:** Phase 1.12 (cart contract), Phase Group F (cart API), Phase 3.8 (cart schema).

## DM-DEC-05 — No full inventory movement history in Version 1

- **Decision:** Version 1 does not build a full inventory movement-history subsystem. Stock is tracked as current authoritative quantities (physical/reserved/available). A lightweight audit trail for stock-affecting operations is deferred unless later investigation requirements justify it.
- **Reason:** The business needs reliable stock operations, not a warehouse-management subsystem (Phase 1.4 §15). Avoid over-engineering.
- **Affected entity:** Inventory.
- **Future implementation phase:** Later, if required, as a separate feature (post-launch improvement per AGENTS.md).

## DM-DEC-06 — Order-time facts are persisted snapshots

- **Decision:** Order items, delivery fee, order addresses, and monetary totals are persisted as transaction-time snapshots (name, SKU, unit price, quantity, line subtotal, final fee, addresses). They do not rely on live catalog values.
- **Reason:** Historical integrity (ORD-005, DATA-002); a historical order must remain explainable even if the catalog changes.
- **Affected entity:** Order, Order Item, Order Address, Delivery, Payment.
- **Future implementation phase:** Phase 3.9/3.10/3.13 (orders/order-items/delivery schema).

## DM-DEC-07 — Order reference is customer-facing and globally unique

- **Decision:** The order reference (`OD-…`) is a customer-facing identifier distinct from the internal order identity. It is globally unique across all orders and never duplicated (retry on collision).
- **Reason:** Phase 1.1 decision 8; ORD-006 (testable uniqueness scope).
- **Affected entity:** Order.
- **Future implementation phase:** Phase 1.15 (order contract), Phase Group I (order creation).

## DM-DEC-08 — Payment is generic and provider-agnostic

- **Decision:** The payment model captures generic data (order association, status, amount, currency, provider, provider transaction reference, verification state). Provider-specific fields/payloads are deferred to Phase Group H.
- **Reason:** Phase 1.1 decision 6; provider selection deferred (see `domain-conflicts.md` for the group-label note).
- **Affected entity:** Payment.
- **Future implementation phase:** Phase 3.12 (payment schema), Phase Group H.

## DM-DEC-09 — Orders, requests, and enquiries are separate entities

- **Decision:** Order, Made-to-order Request, and General Enquiry are distinct persisted entities with separate state. No generic "communications" abstraction; no automatic request→order linkage.
- **Reason:** Phase 1.2 decisions 17/20; REQ-004/005, ENQ-004; avoid generic "everything" tables (Phase 1.4 §46).
- **Affected entity:** Order, Made-to-order Request, General Enquiry.
- **Future implementation phase:** Phase 3.9/3.14/3.15.

## DM-DEC-10 — Addresses are order transaction data

- **Decision:** Billing and delivery addresses are persisted as order transaction data, typed (billing/delivery), with no saved address book in Version 1.
- **Reason:** Phase 1.1 decision 11; ADDR-001/003.
- **Affected entity:** Order Address.
- **Future implementation phase:** Phase 3.9/3.13, Phase Group G (checkout).

## DM-DEC-11 — Attachments are logical file references only

- **Decision:** Attachments persist logical metadata (owner record, file reference, original name, content type, size, security state); no storage provider or binary data is chosen/stored in the logical model.
- **Reason:** Phase 1.1 decision 13; ATTACH-002/003; storage deferred.
- **Affected entity:** Attachment.
- **Future implementation phase:** Phase 3.14/3.15 (request/enquiry schema), Phase 10.6 (attachment handling).

## DM-DEC-12 — Notifications are lightweight event records

- **Decision:** Notification is a lightweight record (recipient, type, related entity, message, read state). External email/push/SMS delivery is deferred to Phase Group R.
- **Reason:** Phase 1.2 decision 13; NOTIF-002/003.
- **Affected entity:** Notification.
- **Future implementation phase:** Phase 3.16 (notification schema), Phase Group R.

## DM-DEC-13 — Money uses safe discrete representation (decision recorded)

- **Decision:** Monetary values (prices, fees, totals) are represented using safe discrete values in TZS; unreliable floating-point arithmetic is not used for financial calculations. The exact physical representation (e.g., minor units) is a physical-design decision.
- **Reason:** PRICE-004; AGENTS.md money-safety rule.
- **Affected entity:** Product, Variant, Order, Payment, Delivery.
- **Future implementation phase:** Physical database design, Phase Group G (totals).

## DM-DEC-14 — Concurrency-sensitive operations identified

- **Decision:** The following operations require atomic/concurrency-safe handling (mechanism later): checkout/order creation + inventory reservation, payment confirmation/callbacks, and critical order status changes. These are transaction-boundary candidates; no transactions are implemented here.
- **Reason:** Phase 1.4 §48; DATA-004, CONC-001..004, IDEMP-001..005.
- **Affected entity:** Order, Inventory, Payment, Order Status History.
- **Future implementation phase:** Phase Group G (checkout), H (payments), I (orders).