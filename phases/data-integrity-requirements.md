# Data Integrity Requirements — Version 1

## 1. Purpose

Logical integrity requirements for the Version 1 data model: uniqueness, mandatory/conditional data, ownership, referential expectations, and consistency. Physical enforcement (constraints, indexes) is deferred to the physical design phase.

## 2. Uniqueness Requirements

Business uniqueness (scope noted) — physical uniqueness enforcement is a later decision.

| Value | Uniqueness scope | Notes |
|---|---|---|
| Product slug | Global | SEO/URL identity. |
| Product SKU / business reference | Global | Catalog reference. |
| Variant SKU | Global | |
| Order reference (`OD-…`) | Global (system-wide) | Never duplicate; retry on collision (ORD-006). |
| User email | Global | Login identifier; where applicable. |
| Payment provider transaction reference | Global (per provider) | Provider-side identity. |
| Category slug | Global | SEO/URL identity. |

Not unique: product display names, category names, variant names, order item names (display text is not an identity).

## 3. Mandatory Data

| Entity | Mandatory attributes |
|---|---|
| User | Identity/display name, email, credentials, role, account state. |
| Category | Name, slug, active state. |
| Product | Name, slug, description, SKU, product type, category, active state, visibility. |
| Product Image | Product association, image reference, display order, active state. |
| Product Variant | Product association, variant name, SKU, active state. |
| Inventory | Purchasable unit ownership, stock quantity, reserved quantity, updated time. |
| Cart | Created/updated timestamps, guest token or owner. |
| Cart Item | Cart ownership, product reference, quantity. |
| Order | Customer, order reference, status, fulfillment type, currency, final total, timestamps. |
| Order Item | Order, product reference, name snapshot, SKU snapshot, unit price snapshot, quantity, line subtotal. |
| Order Status History | Order, previous status, new status, timestamp, actor/source. |
| Payment | Order association, payment status, amount, currency, verification state. |
| Delivery | Order, fulfillment type, delivery fee, status, recipient, phone, delivery address. |
| Made-to-order Request | Name, at least one contact channel (phone or email), status. |
| General Enquiry | Name, contact channel, message, status. |
| Attachment | Owner record, file reference, content type, size, security/validation state. |
| Notification | Recipient, notification type, message, read/unread state. |

## 4. Conditional Data

| Value | Condition |
|---|---|
| Delivery address / delivery contact | Required for `DELIVERY`; not required for `PICKUP`. |
| Delivery fee | Applies to `DELIVERY`; zero/none for `PICKUP`. |
| Billing address / payment information | Required for purchased orders per payment flow. |
| Variant reference (cart/order item) | Required when the product offers variants. |
| Variant price override | Only when a variant defines its own price. |
| Guest cart identity | Present for guest carts; replaced by User on authentication. |
| Requester user reference (request/enquiry) | Present when authenticated; absent for anonymous. |
| Product reference (request) | Optional — request may exist without a product. |
| Payment provider fields | Only after provider selection (deferred to Phase Group H). |

## 5. Referential Expectations

Logical references that must not silently point to missing entities:

- Product → Category: required.
- Variant/Image/Inventory → Product: required.
- Cart Item → Product/Variant: required; variant must belong to that product (VAR-002).
- Order Item → Order: required; Product/Variant references retained for traceability but display facts are snapshots.
- Order Status History / Addresses / Delivery → Order: required.
- Payment → Order: required.
- Request → Product (optional); → User (optional).
- Enquiry → User (optional).
- Attachment → one request or one enquiry: required owner.
- Notification → User: required recipient.

Physical referential enforcement is a later decision; the logical expectation is documented here (DATA-001).

## 6. Consistency Requirements

- **Product ≠ Inventory:** stock facts live in Inventory only.
- **Order-time facts are snapshots:** order items, delivery fee, addresses, totals preserve purchase-time values.
- **Single owner for delivery snapshots:** the delivery fee snapshot is canonical on **Order**; the delivery address/recipient/phone snapshot is canonical on **Order Address (delivery)**. Delivery holds only non-authoritative operational projections copied once at Delivery creation and never edited independently; projections must always equal the authoritative Order values (no divergence path, since both sides are immutable after finalization).
- **Client-controlled financial data is never authoritative** (CART-005, PRICE-001).
- **Payment status ≠ order status:** distinct facts, never collapsed (PAY-001).
- **Request ≠ Order; Enquiry ≠ Request:** separate entities (REQ-004, ENQ-004).
- **Guest cart ≠ authenticated cart:** guest cart binds to User on authentication (CART-002).
- **Concurrency-sensitive consistency:** inventory reservation, order creation, payment callbacks, and status changes must be guarded so duplicate/concurrent effects do not corrupt state (CONC-001..004, IDEMP-001..005).
- **Inventory reservation invariant and release:** a reservation must atomically reject any quantity above `physical - reserved` and must preserve `0 <= reserved <= physical` at all times; concurrent checkout must not oversell. Reserved stock is released on order cancellation, reservation timeout/expiry, and permanent payment failure, so stock does not strand.
- **Atomicity:** order creation + inventory reservation succeed or fail together; payment confirmation failure leaves the order in `PENDING_PAYMENT` with reservation intact (DATA-004) until released via cancellation, timeout, or payment failure handling.