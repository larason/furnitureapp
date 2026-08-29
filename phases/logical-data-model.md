# Logical Data Model — Version 1

## 1. Purpose

This document defines the **logical data requirements** for every Version 1 entity: what information the system must persist, which domain owns each fact, how entities relate conceptually, and how each attribute is classified.

It is a **logical model**, not a physical MySQL schema. Column types, primary keys, foreign keys, indexes, and constraints are deliberately deferred to the physical database design phase.

## 2. Classification Legend

- **REQUIRED** — must always exist.
- **OPTIONAL** — may exist.
- **CONDITIONAL** — required only when a business condition holds.
- **DERIVED** — calculated from source data; may be cached with documented purpose.
- **SNAPSHOT** — copied from a source at transaction time because the source may change.
- **EXTERNAL REF** — identifier/data originating outside the system.
- **SENSITIVE** — requires special handling (see `data-classification.md`).

Entity attribute tables use these markers.

---

# Identity

## User

- **Purpose:** Single system-level identity for customer, staff, and admin personas (Phase 1.2 decision 1).
- **Business owner:** Identity domain.
- **Relationships:** Role (CUSTOMER/STAFF/ADMIN); may own carts, orders, requests, enquiries, notifications.
- **Lifecycle:** Created at registration/admin creation; activation/deactivation; not deleted (retained for history).
- **Privacy classification:** PRIVATE (identity data), SENSITIVE (credentials).

| Attribute | Class | Notes |
|---|---|---|
| Identity / display name | REQUIRED | Customer-facing display name. |
| Email | REQUIRED | Login identifier and contact channel. |
| Phone | OPTIONAL | Contact channel. |
| Authentication credentials (credential value/hash) | REQUIRED, SENSITIVE | Never stored raw; hashing algorithm is a later decision. |
| Role | REQUIRED | CUSTOMER / STAFF / ADMIN; enforced authoritatively. |
| Account state (active/inactive/disabled) | REQUIRED | Controls access. |
| Verification state (email verified etc.) | OPTIONAL | Exact channel deferred (Phase Group R/D). |
| Created / updated timestamps | REQUIRED | Auditability. |

## Role

- **Purpose:** High-level authorization classifier.
- **Business owner:** Identity/Authorization domain.
- **Relationships:** Applies to User.
- **Lifecycle:** Static set in Version 1.
- **Privacy classification:** INTERNAL.

| Attribute | Class | Notes |
|---|---|---|
| Role value | REQUIRED | CUSTOMER / STAFF / ADMIN. |
| User association | REQUIRED | Each user carries a role. |

Note: detailed permission matrix is deferred to Phase Group D.

---

# Catalog

## Category

- **Purpose:** Named grouping for product browsing/discovery.
- **Business owner:** Catalog domain.
- **Relationships:** Parents products (one-to-many).
- **Lifecycle:** Active/inactive; retained historically.
- **Privacy classification:** PUBLIC (non-sensitive).
- **Hierarchy decision:** Flat, single-level structure for Version 1 (see `data-model-decisions.md`). No parent-category recursion.

| Attribute | Class | Notes |
|---|---|---|
| Name | REQUIRED | Display name. |
| Slug | REQUIRED, unique (global) | URL identifier; SEO. |
| Description | OPTIONAL | |
| Display image reference | OPTIONAL | Image location; provider deferred. |
| Active state | REQUIRED | Controls public visibility. |
| Ordering/display position | OPTIONAL | Catalog ordering. |
| Created / updated timestamps | REQUIRED | |

## Product

- **Purpose:** Furniture catalog item, either `IN_STOCK` or `MADE_TO_ORDER`.
- **Business owner:** Catalog domain.
- **Relationships:** Belongs to Category (required); owns Product Images, Product Variants; referenced by Inventory, Cart Items, Order Items (snapshot), Requests (optional).
- **Lifecycle:** Draft/active/inactive concept (Phase 1.3 CAT-004).
- **Privacy classification:** PUBLIC (non-sensitive).
- **Product ≠ Inventory:** price/description live here; stock lives in Inventory.

| Attribute | Class | Notes |
|---|---|---|
| Name | REQUIRED | |
| Slug | REQUIRED, unique (global) | SEO/URL. |
| Description | REQUIRED | Needed for public rendering/SEO. |
| SKU / business reference | REQUIRED, unique (global) | Internal/catalog reference; distinct from order item snapshot. |
| Product type | REQUIRED | `IN_STOCK` / `MADE_TO_ORDER` (CAT-002). |
| Category association | REQUIRED | |
| Base price | CONDITIONAL | Required for `IN_STOCK` purchasable items; variants may override (VAR-005). |
| Active state | REQUIRED | Purchasability gate (CAT-004). |
| Catalog visibility | REQUIRED | Whether publicly listed. |
| SEO fields (meta title/description, canonical info) | OPTIONAL | Identified for later SEO implementation. |
| Created / updated timestamps | REQUIRED | |

## Product Image

- **Purpose:** Visual representation of a product for listing/detail.
- **Business owner:** Catalog domain.
- **Relationships:** Belongs to Product (many-to-one).
- **Lifecycle:** Active/inactive; may be removed from catalog (retained or deleted per later policy).
- **Privacy classification:** PUBLIC.

| Attribute | Class | Notes |
|---|---|---|
| Product association | REQUIRED | |
| Image location/reference | REQUIRED, EXTERNAL REF | Storage provider deferred. |
| Display order | REQUIRED | Ordering among images. |
| Primary image indicator | OPTIONAL | One primary per product where used. |
| Alt text / accessibility | OPTIONAL | |
| Active state | REQUIRED | |

## Product Variant

- **Purpose:** Purchasable variation (color, material, size, configuration) of a product; may carry its own stock and price.
- **Business owner:** Catalog domain.
- **Relationships:** Belongs to Product (many-to-one); referenced by Inventory, Cart Items, Order Items (snapshot), Requests.
- **Lifecycle:** Active/inactive (VAR-003).
- **Privacy classification:** PUBLIC.

| Attribute | Class | Notes |
|---|---|---|
| Product association | REQUIRED | |
| Variant name | REQUIRED | |
| SKU | REQUIRED, unique (global) | |
| Price override | OPTIONAL | When absent, base price applies (VAR-005). |
| Attributes (color/material/size) | OPTIONAL | Free-form logical attributes; physical representation (columns vs JSON) deferred. |
| Active state | REQUIRED | |

## Inventory

- **Purpose:** Authoritative current stock of a purchasable product/variant.
- **Business owner:** Inventory domain.
- **Relationships:** Belongs to a purchasable product or variant.
- **Lifecycle:** Updated on stock operations; history decision recorded in `data-model-decisions.md`.
- **Privacy classification:** INTERNAL (physical/reserved quantities not public; availability is derived public signal).

| Attribute | Class | Notes |
|---|---|---|
| Stock quantity (physical) | REQUIRED, authoritative | |
| Reserved quantity | REQUIRED, authoritative | |
| Available quantity | DERIVED | See decision in `data-model-decisions.md` (may be computed or cached). |
| Product/variant ownership | REQUIRED | |
| Updated time | REQUIRED | |

---

# Commerce

## Cart

- **Purpose:** Temporary collection of items for checkout; guest or authenticated.
- **Business owner:** Cart domain.
- **Relationships:** Owns Cart Items; associated with a User when authenticated (IDENT-008/CART-002), guest otherwise.
- **Lifecycle:** Created on first add; used until checkout or abandoned.
- **Privacy classification:** PRIVATE (owner/guest cart contents).

| Attribute | Class | Notes |
|---|---|---|
| Owner (authenticated customer) | CONDITIONAL | Present when authenticated. |
| Guest identity/token | CONDITIONAL | Present for guest carts; identity mechanism defined in cart phase. |
| Created / updated timestamps | REQUIRED | |

## Cart Item

- **Purpose:** A line in a cart referencing a product/variant and quantity.
- **Business owner:** Cart domain.
- **Relationships:** Belongs to Cart; references Product/Variant.
- **Lifecycle:** Add/update/remove; revalidated at checkout.
- **Privacy classification:** PRIVATE.

| Attribute | Class | Notes |
|---|---|---|
| Product reference | REQUIRED | |
| Variant reference | CONDITIONAL | Required when the product offers variants. |
| Quantity | REQUIRED | Positive integer. |
| Cart ownership | REQUIRED | |

Note: displayed/unit price may be cached only as non-authoritative; final price is backend-computed at checkout (CART-005, CHECKOUT-003).

## Order

- **Purpose:** Confirmed commerce record of a purchase.
- **Business owner:** Order domain.
- **Relationships:** Owns Order Items, Order Status History, Order Addresses, Delivery; relates to Payment; belongs to Customer.
- **Lifecycle:** `PENDING_PAYMENT → PAID → ACCEPTED → PROCESSING → READY_FOR_PICKUP/SHIPPED → (DELIVERED) → COMPLETED`, or `→ CANCELLED`.
- **Privacy classification:** PRIVATE (customer order data).
- **Retention:** historically retained.

| Attribute | Class | Notes |
|---|---|---|
| Order reference (`OD-…`) | REQUIRED, unique (global), immutable | Customer-facing identifier; distinct from internal identity. |
| Customer identity | REQUIRED | |
| Order status | REQUIRED | State machine in `order-state-rules.md`. |
| Fulfillment type | REQUIRED | `PICKUP` / `DELIVERY`. |
| Subtotal | DERIVED (persisted snapshot at finalization) | |
| Delivery fee | SNAPSHOT | Finalized fee for the order (variable, staff-managed). **Canonical owner**: Order (monetary fact in the authoritative total). Delivery carries a non-authoritative projection of this value. |
| Discount | OPTIONAL | Not in Version 1 (deferred). |
| Final total | DERIVED / SNAPSHOT (immutable after finalization) | |
| Currency | REQUIRED | TZS. |
| Customer note | OPTIONAL | |
| Created / updated timestamps | REQUIRED | |

## Order Item

- **Purpose:** Historical snapshot line of a purchased product/variant.
- **Business owner:** Order domain.
- **Relationships:** Belongs to Order; references Product/Variant (for traceability).
- **Lifecycle:** Immutable once created.
- **Privacy classification:** PRIVATE.

| Attribute | Class | Notes |
|---|---|---|
| Product reference | REQUIRED | Traceability to catalog. |
| Variant reference | CONDITIONAL | Where applicable. |
| Product name snapshot | REQUIRED, SNAPSHOT | |
| SKU/reference snapshot | REQUIRED, SNAPSHOT | |
| Unit price snapshot | REQUIRED, SNAPSHOT | |
| Quantity | REQUIRED, SNAPSHOT | |
| Line subtotal | DERIVED / SNAPSHOT | |

Must remain intact even if the product is renamed, repriced, deactivated, or reclassified; referenced products/variants must not be hard-deleted — use archival/soft-delete or tombstone retention so the REQUIRED Product/Variant reference never points to a missing entity. Snapshots remain authoritative for display if the catalog row is archived (ORD-005, DATA-001/002).

## Order Address

- **Purpose:** Transaction-time address data for an order.
- **Business owner:** Order domain.
- **Relationships:** Belongs to Order; typed as billing or delivery.
- **Privacy classification:** PRIVATE.
- **Canonical owner of the delivery address snapshot:** for a `DELIVERY` order, the delivery address, recipient name, and contact phone are authoritative here (immutable historical snapshot). The Delivery entity holds non-authoritative operational projections of these facts.

| Attribute | Class | Notes |
|---|---|---|
| Order association | REQUIRED | |
| Address type (billing / delivery) | REQUIRED | Distinct concepts (ADDR-003). |
| Recipient name | CONDITIONAL | Delivery needs it. |
| Contact phone | CONDITIONAL | Delivery needs it. |
| Address fields (line, area/location, region, postal details) | CONDITIONAL | Delivery requires full info; billing requires as payment flow demands. |
| Saved-address marker | NOT APPLICABLE | Address book deferred (ADDR-001). |

## Order Status History

- **Purpose:** Append-only audit/timeline of status changes.
- **Business owner:** Order domain.
- **Relationships:** Belongs to Order.
- **Lifecycle:** Append-only; never mutated.
- **Privacy classification:** INTERNAL (partly PRIVATE when exposed to customer tracking).

| Attribute | Class | Notes |
|---|---|---|
| Order reference | REQUIRED | |
| Previous status | REQUIRED | |
| New status | REQUIRED | |
| Timestamp | REQUIRED | Backend-authoritative. |
| Actor/source | REQUIRED | customer/staff/admin/system. |
| Operational note | OPTIONAL | |

## Payment

- **Purpose:** Financial transaction associated with an order; state distinct from order state.
- **Business owner:** Payment domain.
- **Relationships:** Belongs to Order (one order may have one or more payment records).
- **Lifecycle:** Created at payment initiation; verified on backend/provider callback.
- **Privacy classification:** SENSITIVE (transaction/financial).

| Attribute | Class | Notes |
|---|---|---|
| Order association | REQUIRED | |
| Payment status | REQUIRED | Distinct from order status (PAY-001). |
| Amount | REQUIRED | |
| Currency | REQUIRED | TZS. |
| Provider | OPTIONAL / EXTERNAL | Provider identity; selection deferred to Phase Group H. |
| Provider transaction/reference ID | CONDITIONAL, EXTERNAL REF — unique on (provider, provider transaction/reference ID) | Provider-side identifier; absent at initiation, present only after provider verification/callback; unique per provider (per `data-integrity-requirements.md`). |
| Verification state | REQUIRED | Backend-verified. |
| Created / updated timestamps | REQUIRED | |

Provider-specific fields/payloads are deferred to Phase Group H (see `data-model-decisions.md`).

## Delivery

- **Purpose:** Fulfilment record for a delivery order.
- **Business owner:** Order/Fulfilment domain.
- **Relationships:** Belongs to Order; conditional on `DELIVERY`.
- **Privacy classification:** PRIVATE (recipient/address).
- **Projection rule:** Delivery fee, recipient name, phone, and delivery address are **non-authoritative operational projections**, copied once from the Order delivery-fee snapshot and the Order Address (delivery) snapshot at Delivery creation; they must always equal the authoritative Order values and are never edited independently.

| Attribute | Class | Notes |
|---|---|---|
| Order association | REQUIRED | |
| Fulfillment type | REQUIRED | `DELIVERY`. |
| Delivery fee | REQUIRED, SNAPSHOT — non-authoritative projection | Projection of Order.Delivery fee (canonical owner: Order); must equal the Order value; never edited independently (PRICE-006). |
| Delivery status | REQUIRED | Operational status. |
| Recipient name | REQUIRED — non-authoritative projection | Projection of Order Address (delivery) recipient; never edited independently. |
| Phone | REQUIRED — non-authoritative projection | Projection of Order Address (delivery) phone; never edited independently. |
| Delivery address | REQUIRED, SNAPSHOT — non-authoritative projection | Projection of Order Address (delivery); canonical snapshot lives on Order Address; must equal it; never edited independently. |
| Delivery notes | OPTIONAL | |

No GPS/route/driver tracking (FUL-004).

---

# Customer Communication

## Made-to-order Request

- **Purpose:** Lead requesting manufacture; not an order (REQ-004/005).
- **Business owner:** Request domain.
- **Relationships:** Optional User, optional Product, optional Attachments.
- **Lifecycle:** New → Contacted → Quoted → … (operational status per later request phase).
- **Privacy classification:** PRIVATE.

| Attribute | Class | Notes |
|---|---|---|
| Requester identity (authenticated user) | CONDITIONAL | |
| Name | REQUIRED | Contact for follow-up. |
| Phone | CONDITIONAL | At least one contact channel required (REQ-001). |
| Email | CONDITIONAL | At least one contact channel required. |
| Referenced product | OPTIONAL | |
| Quantity | OPTIONAL | |
| Dimensions | OPTIONAL | |
| Preferred material | OPTIONAL | |
| Preferred color | OPTIONAL | |
| Notes/requirements | OPTIONAL | |
| Status | REQUIRED | Operational status. |
| Created / updated timestamps | REQUIRED | |

## General Enquiry

- **Purpose:** General communication lead; distinct from requests (ENQ-004).
- **Business owner:** Enquiry domain.
- **Relationships:** Optional User; optional Attachments.
- **Lifecycle:** Operational status; retained for staff history.
- **Privacy classification:** PRIVATE.

| Attribute | Class | Notes |
|---|---|---|
| Authenticated user reference | CONDITIONAL | |
| Name | REQUIRED | |
| Phone | CONDITIONAL | At least one contact channel required — at least one of Phone or Email must be present (ENQ-002). |
| Email | CONDITIONAL | At least one contact channel required — at least one of Phone or Email must be present (ENQ-002). |
| Subject | OPTIONAL | |
| Message | REQUIRED | |
| Status | REQUIRED | Operational status. |
| Created / updated timestamps | REQUIRED | |

> **Invariant:** A General Enquiry must have at least one of Phone or Email; otherwise it has no reply path. Both being absent is invalid.

## Attachment

- **Purpose:** Optional file on a request or enquiry (ATTACH-001).
- **Business owner:** Request/Enquiry domain.
- **Relationships:** Belongs to one request or one enquiry.
- **Lifecycle:** Created with the record; later validated/security-checked (ATTACH-003).
- **Privacy classification:** PRIVATE.

| Attribute | Class | Notes |
|---|---|---|
| Owner record (request or enquiry) | REQUIRED | |
| File reference | REQUIRED, EXTERNAL REF | Storage provider deferred. |
| Original file name | OPTIONAL | |
| Content type | REQUIRED | |
| Size | REQUIRED | |
| Upload timestamp | REQUIRED | |
| Security/validation state | REQUIRED | Type/size/security checks (SEC-002). |

---

# Supporting

## Notification

- **Purpose:** Application-event alert record (NOTIF-001).
- **Business owner:** Supporting/Notification domain.
- **Relationships:** Belongs to a recipient User; references a related entity/event.
- **Lifecycle:** Created on event; read/unread.
- **Privacy classification:** PRIVATE.

| Attribute | Class | Notes |
|---|---|---|
| Recipient | REQUIRED | |
| Notification type | REQUIRED | |
| Related entity/reference | OPTIONAL | |
| Message/content | REQUIRED | |
| Read/unread state | REQUIRED | |
| Created time | REQUIRED | |

Email/push/SMS delivery is deferred to Phase Group R (NOTIF-002/003).