# Data Ownership — Version 1

## 1. Purpose

Central reference for **which domain owns each important business fact**. One source of truth per fact; avoid storing the same mutable business fact in multiple unrelated places unless it is a deliberate historical snapshot.

## 2. Ownership Matrix

| Business fact | Authoritative domain |
|---|---|
| Product description | Catalog |
| Product slug | Catalog |
| Current product price (base / variant) | Catalog |
| Product type (`IN_STOCK` / `MADE_TO_ORDER`) | Catalog |
| Category structure | Catalog |
| Product images | Catalog |
| Current inventory (physical/reserved/available) | Inventory |
| Customer identity / account | Identity |
| Role / authorization baseline | Identity / Authorization (Group D) |
| Cart contents | Cart |
| Order reference (`OD-…`) | Order |
| Current order state | Order |
| Order status history | Order |
| Historical purchase price | Order (order-item snapshot) |
| Order subtotal / final total | Order |
| Final delivery fee | Order (canonical snapshot in the authoritative total) |
| Fulfilment type (pickup/delivery) | Order / Fulfilment |
| Payment confirmation | Payment |
| Payment status | Payment |
| Delivery operational status | Fulfilment |
| Delivery address / recipient / phone (transaction snapshot) | Order Address (delivery) |
| Made-to-order request state | Request |
| General enquiry state | Enquiry |
| Notification records | Notification / Supporting |

## 3. Ownership Rules

- Product ≠ Inventory: current price and description live in Catalog; stock lives in Inventory (Phase 1.2 decision 4).
- Order-time price/fee snapshots live in Order data and are owned by Order, not Catalog (ORD-005).
- Payment status is distinct from order status and owned by Payment (PAY-001).
- Cart price is never authoritative; final pricing is owned by Order/backend at checkout (CART-005, CHECKOUT-003).
- Requests/Enquiries own their state and are separate domains; neither becomes an order (REQ-004/005, ENQ-003).
- **Single owner for delivery snapshots:** the delivery fee is owned by Order; the delivery address/recipient/phone snapshot is owned by Order Address (delivery). The Delivery entity carries only non-authoritative operational projections of these facts (copied once at Delivery creation, never edited independently) — see `logical-data-model.md`.

## 4. Derived Facts and Their Owners

| Derived fact | Source data (owner) | Computed/persisted where |
|---|---|---|
| Available stock | Physical − reserved (Inventory) | Inventory domain |
| Order subtotal | Σ line subtotals (Order) | Order at finalization |
| Line subtotal | unit price × quantity (Order) | Order at finalization |
| Order final total | subtotal + delivery fee (Order) | Order at finalization |

Derived values may be persisted as snapshots at transaction time where required for history; otherwise they may be recalculated (see `historical-data-rules.md`).