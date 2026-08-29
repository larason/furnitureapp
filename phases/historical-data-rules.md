# Historical Data Rules — Version 1

## 1. Purpose

Defines how the model preserves historical meaning: snapshot data, immutable transaction facts, order history, and retention expectations. Historical records must remain explainable even when current catalog information changes (DATA-002, ORD-005).

## 2. Snapshot Data

Information that must be copied into a transaction because the source may change.

| Snapshot | Why it exists | Source |
|---|---|---|
| Order-item product name | Product may be renamed later. | Catalog (copied at purchase) |
| Order-item SKU/reference | May change or product may be removed. | Catalog |
| Order-item unit price | Price may change; preserve charged price. | Catalog pricing at purchase |
| Order-item quantity | Charged quantity. | Order |
| Order-item subtotal | Charged line amount. | Derived at purchase |
| Final delivery fee | Fee may change later; preserve charged fee. **Canonical snapshot on Order**; Delivery holds a non-authoritative projection. | Order at finalization |
| Transaction address information | Addresses are transaction data, not a saved book (ADDR-001). **Canonical snapshot on Order Address**; Delivery holds non-authoritative projections of recipient/phone/address. | Checkout input |

## 3. Immutable Transaction Facts

Effectively immutable once a transaction is finalized. Later modifications must not corrupt historical meaning.

- Order reference (`OD-…`)
- Order-item purchased price
- Order-item purchased name/reference
- Order-item quantity
- Final charged total
- Final delivery fee
- Transaction-time addresses
- Payment transaction reference (provider reference)

## 4. Order History

- **Order Status History** is append-only: each significant status change is recorded with previous status, new status, timestamp, actor/source, and optional note (ORD-008, OS-026..029).
- The history powers the customer tracking timeline and operational auditability.
- Past history records are never mutated/erased by later transitions.
- Status-history data must survive even if the current order status changes again.

## 5. Inventory History (Decision)

Version 1 does **not** build a full inventory movement history subsystem (see `data-model-decisions.md`). The minimum investigation data to consider later:

- Why did stock change?
- When did it change?
- What operation caused it?
- Who/what initiated it?

Decision and reason are recorded in `data-model-decisions.md` (DM-DEC-05).

## 6. Retention Expectations

| Entity | Retention expectation |
|---|---|
| Orders | Must remain historically available. |
| Order items | Must remain historically available. |
| Order status history | Must remain historically available. |
| Payments | Must remain historically available. |
| Product images | May become inactive/removed from catalog. |
| Enquiries | Should remain available for staff history per later retention policy. |
| Made-to-order requests | Should retain operational history. |

**Retention periods:** Business retention period pending policy definition (no legal retention period has been approved; not invented here).