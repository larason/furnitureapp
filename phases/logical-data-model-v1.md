# Logical Data Model — Version 1.0 (FROZEN)

## 0. Baseline

- **Document:** Logical Data Model
- **Version:** 1.0
- **Status:** FROZEN (see `freeze-record.md`)
- **Derives from:** Phase 1.4 `logical-data-model.md` and supporting documents, reviewed and frozen in Phase 1.5.
- **Inputs reviewed:** `logical-data-model.md`, `entity-relationship-matrix.md`, `data-classification.md`, `data-ownership.md`, `historical-data-rules.md`, `data-integrity-requirements.md`, `data-model-decisions.md`, `data-model-open-issues.md`, `api-exposure-classification.md`.
- **Change control:** After freeze, changes require a change request per Phase 1.5 §49 (reason → affected business rule → affected entities → impact analysis → approval → version increment).

This document is the **authoritative frozen logical model**. Physical database design (Phase 1.6) must trace back to this baseline.

---

## 1. Classification Legend

- **REQUIRED** — must always exist.
- **OPTIONAL** — may exist.
- **CONDITIONAL** — required only when a business condition holds.
- **DERIVED** — calculated from source data; may be cached with documented purpose.
- **SNAPSHOT** — copied from a source at transaction time because the source may change.
- **EXTERNAL REF** — identifier/data originating outside the system.
- **SENSITIVE** — requires special handling (`data-classification.md`).

---

## 2. Identity

### User

- **Purpose:** Single system-level identity for customer, staff, and admin personas (Decision 1).
- **Owner:** Identity domain.
- **Relationships:** Role (CUSTOMER/STAFF/ADMIN); may own carts, orders, requests, enquiries, notifications.
- **Lifecycle:** Created at registration/admin creation; activation/deactivation; not deleted (retained for history).
- **Privacy:** PRIVATE (identity), SENSITIVE (credentials).

| Attribute | Class | Notes |
|---|---|---|
| Identity / display name | REQUIRED | Customer-facing display name. |
| Email | REQUIRED | Login identifier and contact channel. |
| Phone | OPTIONAL | Contact channel. |
| Authentication credentials | REQUIRED, SENSITIVE | Never stored raw. |
| Role | REQUIRED | CUSTOMER / STAFF / ADMIN; enforced authoritatively. |
| Account state (active/inactive/disabled) | REQUIRED | Controls access. |
| Verification state | OPTIONAL | Channel deferred (Group D/R). |
| Created / updated timestamps | REQUIRED | Auditability. |

### Role

- **Purpose:** High-level authorization classifier.
- **Owner:** Identity/Authorization.
- **Relationships:** Applies to many Users (many-to-one from User).
- **Lifecycle:** Static set in Version 1.
- **Privacy:** INTERNAL.

| Attribute | Class | Notes |
|---|---|---|
| Role value | REQUIRED | CUSTOMER / STAFF / ADMIN. |
| User association | REQUIRED | Each user carries a role. |

Note: detailed permission matrix deferred to Phase Group D.

---

## 3. Catalog

### Category

- **Purpose:** Named grouping for browsing/discovery.
- **Owner:** Catalog.
- **Lifecycle:** Active/inactive; retained historically.
- **Hierarchy:** Flat, single-level (DM-DEC-01).
- **Privacy:** PUBLIC.

| Attribute | Class | Notes |
|---|---|---|
| Name | REQUIRED | |
| Slug | REQUIRED, unique (global) | URL identity; SEO. |
| Description | OPTIONAL | |
| Display image reference | OPTIONAL | Provider deferred. |
| Active state | REQUIRED | Controls public visibility. |
| Ordering/display position | OPTIONAL | |
| Created / updated timestamps | REQUIRED | |

### Product

- **Purpose:** Furniture catalog item, `IN_STOCK` or `MADE_TO_ORDER`.
- **Owner:** Catalog.
- **Relationships:** Belongs to Category (required); owns Images, Variants; referenced by Inventory, Cart Items, Order Items (snapshot), Requests (optional).
- **Lifecycle:** Draft/active/inactive (CAT-004).
- **Privacy:** PUBLIC.

| Attribute | Class | Notes |
|---|---|---|
| Name | REQUIRED | |
| Slug | REQUIRED, unique (global) | SEO/URL. |
| Description | REQUIRED | Public rendering/SEO. |
| SKU / business reference | REQUIRED, unique (global) | |
| Product type | REQUIRED | `IN_STOCK` / `MADE_TO_ORDER` (CAT-002). |
| Category association | REQUIRED | |
| Base price | CONDITIONAL | Required for `IN_STOCK` purchasable items; variants may override (VAR-005). |
| Active state | REQUIRED | Purchasability gate (CAT-004). |
| Catalog visibility | REQUIRED | Whether publicly listed. |
| SEO fields | OPTIONAL | Later SEO implementation. |
| Created / updated timestamps | REQUIRED | |

### Product Image

- **Purpose:** Visual representation for listing/detail.
- **Owner:** Catalog.
- **Privacy:** PUBLIC.

| Attribute | Class | Notes |
|---|---|---|
| Product association | REQUIRED | |
| Image location/reference | REQUIRED, EXTERNAL REF | Provider deferred. |
| Display order | REQUIRED | |
| Primary image indicator | OPTIONAL | One primary per product where used. |
| Alt text / accessibility | OPTIONAL | |
| Active state | REQUIRED | |

### Product Variant

- **Purpose:** Purchasable variation (color, material, size, configuration); may carry own stock/price.
- **Owner:** Catalog.
- **Relationships:** Belongs to Product (many-to-one); referenced by Inventory, Cart Items, Order Items (snapshot), Requests.
- **Lifecycle:** Active/inactive (VAR-003).
- **Privacy:** PUBLIC.

| Attribute | Class | Notes |
|---|---|---|
| Product association | REQUIRED | |
| Variant name | REQUIRED | |
| SKU | REQUIRED, unique (global) | |
| Price override | OPTIONAL | When absent, base price applies (VAR-005). |
| Attributes | OPTIONAL | Free-form logical attributes; physical representation deferred. |
| Active state | REQUIRED | |

### Inventory

- **Purpose:** Authoritative current stock of a purchasable product/variant.
- **Owner:** Inventory.
- **Lifecycle:** Updated on stock operations; no full movement history (DM-DEC-05).
- **Privacy:** INTERNAL (quantities); derived availability is public signal.

| Attribute | Class | Notes |
|---|---|---|
| Stock quantity (physical) | REQUIRED, authoritative | |
| Reserved quantity | REQUIRED, authoritative | |
| Available quantity | DERIVED | From authoritative stock/reservation (DM-DEC-03). |
| Product/variant ownership | REQUIRED | |
| Updated time | REQUIRED | |

**Reservation invariant:** a reservation must atomically reject any quantity above `physical − reserved`; `0 <= reserved <= physical` at all times; no overselling. Reserved stock released on cancellation, reservation timeout/expiry, and permanent payment failure.

---

## 4. Commerce

### Cart

- **Purpose:** Temporary collection of items for checkout; guest or authenticated.
- **Owner:** Cart.
- **Relationships:** Owns Cart Items; associated with a User when authenticated, guest otherwise (IDENT-008, DM-DEC-04).
- **Privacy:** PRIVATE.

| Attribute | Class | Notes |
|---|---|---|
| Owner (authenticated customer) | CONDITIONAL | Present when authenticated. |
| Guest identity/token | CONDITIONAL | Present for guest carts; mechanism defined in cart phase. |
| Created / updated timestamps | REQUIRED | |

### Cart Item

- **Purpose:** A line referencing a product/variant and quantity.
- **Owner:** Cart.
- **Privacy:** PRIVATE.

| Attribute | Class | Notes |
|---|---|---|
| Product reference | REQUIRED | |
| Variant reference | CONDITIONAL | Required when the product offers variants. |
| Quantity | REQUIRED | Positive integer. |
| Cart ownership | REQUIRED | |

Note: displayed price may be cached non-authoritatively; final price backend-computed at checkout (CART-005).

### Order

- **Purpose:** Confirmed commerce record of a purchase.
- **Owner:** Order.
- **Relationships:** Owns Order Items, Status History, Addresses, Delivery; relates to Payment; belongs to Customer.
- **Lifecycle:** `PENDING_PAYMENT → PAID → ACCEPTED → PROCESSING → READY_FOR_PICKUP/SHIPPED → (DELIVERED) → COMPLETED`, or `→ CANCELLED`.
- **Privacy:** PRIVATE.
- **Retention:** historically retained.

| Attribute | Class | Notes |
|---|---|---|
| Order reference (`OD-…`) | REQUIRED, unique (global), immutable | Customer-facing; distinct from internal identity. PRIVATE. |
| Customer identity | REQUIRED | |
| Order status | REQUIRED | State machine in `order-state-rules.md`. |
| Fulfillment type | REQUIRED | `PICKUP` / `DELIVERY`. |
| Subtotal | DERIVED (persisted snapshot at finalization) | |
| Delivery fee | SNAPSHOT | Finalized fee; variable, staff-managed. **Canonical owner**: Order (monetary fact in the authoritative total). Delivery carries a non-authoritative projection of this value. |
| Discount | NOT IN V1 | Deferred. |
| Final total | DERIVED / SNAPSHOT (immutable after finalization) | |
| Currency | REQUIRED | TZS. |
| Customer note | OPTIONAL | |
| Created / updated timestamps | REQUIRED | Cancellation window base = creation time. |

### Order Item

- **Purpose:** Historical snapshot line of a purchased product/variant.
- **Owner:** Order.
- **Lifecycle:** Immutable once created.
- **Privacy:** PRIVATE.

| Attribute | Class | Notes |
|---|---|---|
| Product reference | REQUIRED | Traceability to catalog. |
| Variant reference | CONDITIONAL | Where applicable. |
| Product name snapshot | REQUIRED, SNAPSHOT | |
| SKU/reference snapshot | REQUIRED, SNAPSHOT | |
| Unit price snapshot | REQUIRED, SNAPSHOT | |
| Quantity | REQUIRED, SNAPSHOT | |
| Line subtotal | DERIVED / SNAPSHOT | |

Must remain intact if product renamed/repriced/deactivated/reclassified; referenced products/variants must not be hard-deleted — archival/tombstone keeps REQUIRED references valid; snapshots remain authoritative for display (ORD-005, DATA-001/002).

### Order Address

- **Purpose:** Transaction-time address data for an order.
- **Owner:** Order.
- **Privacy:** PRIVATE.
- **Canonical owner of the delivery address snapshot:** for a `DELIVERY` order, the delivery address, recipient name, and contact phone are authoritative here (immutable historical snapshot). The Delivery entity holds non-authoritative operational projections of these facts.

| Attribute | Class | Notes |
|---|---|---|
| Order association | REQUIRED | |
| Address type (billing / delivery) | REQUIRED | Distinct concepts (ADDR-003). |
| Recipient name | CONDITIONAL | Delivery needs it. |
| Contact phone | CONDITIONAL | Delivery needs it. |
| Address fields | CONDITIONAL | Delivery requires full info; billing as payment flow demands. |
| Saved-address marker | NOT APPLICABLE | Address book deferred (ADDR-001). |

### Order Status History

- **Purpose:** Append-only audit/timeline of status changes.
- **Owner:** Order.
- **Privacy:** INTERNAL (partly PRIVATE when exposed to customer tracking).

| Attribute | Class | Notes |
|---|---|---|
| Order reference | REQUIRED | |
| Previous status | REQUIRED | |
| New status | REQUIRED | |
| Timestamp | REQUIRED | Backend-authoritative. |
| Actor/source | REQUIRED | customer/staff/admin/system. |
| Operational note | OPTIONAL | STAFF/ADMIN only; excluded from owner timeline projection. |

### Payment

- **Purpose:** Financial transaction associated with an order; state distinct from order state.
- **Owner:** Payment.
- **Privacy:** SENSITIVE.

| Attribute | Class | Notes |
|---|---|---|
| Order association | REQUIRED | |
| Payment status | REQUIRED | Distinct from order status (PAY-001). |
| Amount | REQUIRED | |
| Currency | REQUIRED | TZS. |
| Provider | OPTIONAL / EXTERNAL | Provider identity; selection deferred to Phase Group H. |
| Provider transaction/reference ID | CONDITIONAL, EXTERNAL REF — unique on (provider, provider reference) | Absent at initiation; present after provider verification/callback. |
| Verification state | REQUIRED | Backend-verified. |
| Created / updated timestamps | REQUIRED | |

### Delivery

- **Purpose:** Fulfilment record for a delivery order.
- **Owner:** Order/Fulfilment.
- **Relationships:** Belongs to Order; conditional on `DELIVERY`.
- **Privacy:** PRIVATE.
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

## 5. Customer Communication

### Made-to-order Request

- **Purpose:** Lead requesting manufacture; not an order (REQ-004/005).
- **Owner:** Request.
- **Relationships:** Optional User, optional Product, optional Attachments.
- **Privacy:** PRIVATE.

| Attribute | Class | Notes |
|---|---|---|
| Requester identity (authenticated user) | CONDITIONAL | |
| Name | REQUIRED | |
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

No request→order relationship in Version 1 (Decision 20, REQ-005). No authoritative quotation price at request time (REQ-006).

### General Enquiry

- **Purpose:** General communication lead; distinct from requests (ENQ-004).
- **Owner:** Enquiry.
- **Privacy:** PRIVATE.

| Attribute | Class | Notes |
|---|---|---|
| Authenticated user reference | CONDITIONAL | |
| Name | REQUIRED | |
| Phone | CONDITIONAL | At least one of Phone/Email (ENQ-002). |
| Email | CONDITIONAL | At least one of Phone/Email (ENQ-002). |
| Subject | OPTIONAL | |
| Message | REQUIRED | |
| Status | REQUIRED | |
| Created / updated timestamps | REQUIRED | |

**Invariant:** An enquiry must have at least one of Phone or Email.

### Attachment

- **Purpose:** Optional file on a request or enquiry (ATTACH-001).
- **Owner:** Request/Enquiry.
- **Privacy:** PRIVATE.

| Attribute | Class | Notes |
|---|---|---|
| Owner record (request or enquiry) | REQUIRED | Unambiguous single owner. |
| File reference | REQUIRED, EXTERNAL REF | Provider deferred (DM-DEC-11). |
| Original file name | OPTIONAL | |
| Content type | REQUIRED | |
| Size | REQUIRED | |
| Upload timestamp | REQUIRED | |
| Security/validation state | REQUIRED | Type/size/security checks (SEC-002). |

---

## 6. Supporting

### Notification

- **Purpose:** Application-event alert record (NOTIF-001).
- **Owner:** Supporting/Notification.
- **Privacy:** PRIVATE.

| Attribute | Class | Notes |
|---|---|---|
| Recipient | REQUIRED | |
| Notification type | REQUIRED | |
| Related entity/reference | OPTIONAL | |
| Message/content | REQUIRED | |
| Read/unread state | REQUIRED | |
| Created time | REQUIRED | |

Email/push/SMS delivery deferred to Phase Group R (NOTIF-002/003).

---

## 7. Entity Relationship Summary (Logical)

| Entity | Belongs to / references | Cardinality | Required |
|---|---|---|---|
| User | Role | Many-to-one | Required |
| Category | Products | One-to-many | Required |
| Product | Category | Many-to-one | Required |
| Product Image | Product | Many-to-one | Required when images exist |
| Product Variant | Product | Many-to-one | Required when variants exist |
| Inventory | Product or Variant | One-to-one | Required for purchasable items |
| Cart | User or guest | Many-to-one (User) / one-to-one (guest) | Conditional |
| Cart Item | Cart | Many-to-one | Required |
| Cart Item | Product / Variant | Many-to-one | Required |
| Order | User (Customer) | Many-to-one | Required |
| Order Item | Order | Many-to-one | Required |
| Order Item | Product / Variant (traceability) | Many-to-one (optional) | Referential trace |
| Order Address | Order | 0..1 billing / 0..1 delivery | Conditional |
| Order Status History | Order | Many-to-one | Required |
| Payment | Order | One-to-many | Defined by payment lifecycle |
| Delivery | Order | One-to-one (when DELIVERY) | Conditional |
| Made-to-order Request | User / Product | Many-to-one (optional each) | Optional |
| General Enquiry | User | Many-to-one (optional) | Optional |
| Attachment | Request or Enquiry | Many-to-one (polymorphic owner) | Optional |
| Notification | User | Many-to-one | Required |

---

## 8. Key Business Rules Binding This Model

- **Backend authority:** money, inventory, purchasability, checkout, cancellation, order transitions, payment state are backend-authoritative (1.3-DEC-02).
- **Product types:** `IN_STOCK` purchasable; `MADE_TO_ORDER` request-only (CAT-002/003).
- **Checkout:** requires authenticated customer (IDENT-002, CHECKOUT-001).
- **Guest cart:** allowed; binds on authentication (IDENT-008, CART-002).
- **Anonymous requests/enquiries:** allowed with contact info (REQ-001, ENQ-001).
- **Inventory:** no negative available; no overselling; cart is not a reservation (INV-002..005).
- **Snapshots:** order items, delivery fee, addresses, totals preserved (ORD-005, DATA-002).
- **Order reference:** `OD-`, globally unique (ORD-006).
- **Status history:** append-only with actor and time (ORD-008, OS-026..029).
- **Cancellation:** 20-minute customer window, backend-evaluated (CANCEL-001/002).
- **Payment:** distinct from order state; provider-agnostic; backend-verified (PAY-001..004).
- **Fulfilment:** exactly PICKUP/DELIVERY; pickup free; delivery staff-managed fee (FUL-001..006).
- **Addresses:** transaction data, no address book (ADDR-001..004).
- **Roles:** CUSTOMER/STAFF/ADMIN; permissions deferred (AUTHZ-001..004).
- **Concurrency/idempotency:** inventory reservation, order creation, payment callbacks, status changes guarded (CONC-001..004, IDEMP-001..005).

---

## 9. Deferred Items (as frozen)

- Payment provider selection and provider-specific fields — Phase Group H.
- Email/push/SMS notification delivery — Phase Group R.
- Staff/admin permission matrix — Phase Group D.
- Saved address book — deferred.
- Attachment storage/upload implementation — later phase.
- Delivery Fee Rule / Region / Location physical representation — Phase 1.13 / 3.13 (fee value snapshot is in the model).
- Exhaustive order-transition matrix — later order-state contract phase.
- Inventory movement-history subsystem — deferred (DM-DEC-05).
- Business retention periods — pending policy definition.

---

## 10. Change Control

Any future change to this frozen model must follow the Phase 1.5 §49 procedure:

```text
Change request → Reason → Affected business rule → Affected entities
→ Impact analysis → Approval → Version increment
```

Compatible refinement → 1.1. Major business-model change → 2.0 per project versioning convention. No silent edits.