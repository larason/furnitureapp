# API Exposure Classification — Version 1

## 1. Purpose

This document classifies future API visibility per `phases/phase-1.4.md:1174-1184` (`PUBLIC`, `AUTHENTICATED`, `STAFF`, `ADMIN`, `INTERNAL ONLY`). It is **separate** from `data-classification.md`, which classifies privacy/sensitivity (`PUBLIC`, `INTERNAL`, `PRIVATE`, `SENSITIVE`).

Sensitivity does not define API audience: e.g., an order's `OD-` reference is `PRIVATE` (owner-scoped) but `AUTHENTICATED`-visible to its owner and `STAFF`/`ADMIN`-visible for operations; payment provider secrets are `SENSITIVE` and `INTERNAL ONLY`.

## 2. Entity / Attribute Exposure Matrix

| Entity / Attribute group | API exposure | Notes |
|---|---|---|
| Category (name, slug, description, image) | **PUBLIC** | Catalog browsing. |
| Product (name, slug, description, SKU, type, price, visibility, SEO fields) | **PUBLIC** | Catalog listing/detail. |
| Product Variant (name, SKU, price override, attributes) | **PUBLIC** | When product has variants. |
| Product Image (location/reference) | **PUBLIC** | |
| Inventory — derived availability signal | **PUBLIC** | Derived from internal quantities. |
| Inventory — physical / reserved quantities | **INTERNAL ONLY** | Not exposed; used to compute availability. |
| Cart / Cart Item | **GUEST_TOKEN** (holder, anonymous persistent cart) / **AUTHENTICATED** (owner, authenticated cart) | Anonymous persistent carts approved per `data-model-decisions.md:DM-DEC-04` / `domain-decisions.md:Decision 18` (`IDENT-008`/`CART-002`); guest-token (bearer token) is distinct from `AUTHENTICATED` user-session and requires holder-owner check. Owner-scoped, not cross-customer. |
| Order (reference `OD-`, status, subtotal, delivery fee, total, currency, fulfillment type) | **AUTHENTICATED** (owner) / **STAFF**, **ADMIN** (all orders) | Owner sees own orders; staff/admin see all per Group D. |
| Order Item (snapshots) | **AUTHENTICATED** (owner) / **STAFF**, **ADMIN** | |
| Order Address — billing | **AUTHENTICATED** (owner) / **STAFF**, **ADMIN** | Transaction-time snapshot. |
| Order Address — delivery | **AUTHENTICATED** (owner) / **STAFF**, **ADMIN** | Only for `DELIVERY`; absent for `PICKUP`. |
| Order Status History (timeline) — status, timestamps, actor type | **AUTHENTICATED** (owner projection, excludes `Operational note`) / **STAFF**, **ADMIN** (full, includes `Operational note`) | Append-only audit; owner projection excludes staff `Operational note` (`INTERNAL`/`PRIVATE` per `data-classification.md` and `phase-1.4.md:589`/`818`) — `STAFF`/`ADMIN` only. |
| Payment — status/amount visible to owner | **AUTHENTICATED** (owner) / **STAFF**, **ADMIN** | |
| Payment — provider secrets / verification internals | **INTERNAL ONLY** | Never in public/authenticated responses; backend-only (SEC-004). |
| Delivery (fulfilment record, fee, status, recipient) | **AUTHENTICATED** (owner) / **STAFF**, **ADMIN** | |
| Made-to-order Request — create | **PUBLIC** | Anonymous + authenticated may create. |
| Made-to-order Request — read/update | **AUTHENTICATED** (owner) / **STAFF**, **ADMIN** | Owner sees own; staff/admin see all. |
| General Enquiry — create | **PUBLIC** | Anonymous + authenticated may create. |
| General Enquiry — read/update | **AUTHENTICATED** (owner) / **STAFF**, **ADMIN** | |
| Attachment (file reference) | **AUTHENTICATED** (owner) / **STAFF**, **ADMIN** | Served with owner/role check, not public URL. |
| Notification | **AUTHENTICATED** (recipient) | |
| User — own profile | **AUTHENTICATED** | |
| User — all users / role management | **ADMIN** | Per Group D. |
| Category/Product/Variant management | **STAFF**, **ADMIN** | Per Group D. |

## 3. Mapping to Sensitivity

| Sensitivity (`data-classification.md`) | Typical API exposure |
|---|---|
| `PUBLIC` | Often `PUBLIC`, but catalog-internal fields remain `INTERNAL ONLY`. |
| `INTERNAL` | `INTERNAL ONLY` or `STAFF`/`ADMIN`. |
| `PRIVATE` | `AUTHENTICATED` (owner) + `STAFF`/`ADMIN` where operational. |
| `SENSITIVE` | `INTERNAL ONLY` or strictly-scoped `STAFF`/`ADMIN`; never `PUBLIC`/`AUTHENTICATED` broadly. |

Example: `physical / reserved stock` is `INTERNAL` and `INTERNAL ONLY`; `order totals` are `PRIVATE` and `AUTHENTICATED` (owner) / `STAFF`, `ADMIN`.

## 4. Notes

- Cart visibility is **holder-scoped**: `GUEST_TOKEN` holder (anonymous persistent cart, `DM-DEC-04` / `IDENT-008`/`CART-002`) or `AUTHENTICATED` owner (authenticated cart). Guest-token access is a distinct bearer-token mechanism, not a user session; it requires holder-owner checks and must not be implemented as requiring login or as a broad `AUTHENTICATED` check.
- Order status history `Operational note` is `STAFF`/`ADMIN`-only; the `AUTHENTICATED` owner timeline projection excludes it to prevent staff-note leakage.
- All `STAFF`/`ADMIN` exposure is subject to Phase Group D authorization; this matrix does not grant permissions, it states intended audience.
- No `PUBLIC` exposure leaks `PRIVATE` or `SENSITIVE` data.
