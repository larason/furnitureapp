# Resource Access Matrix — Version 1

## 1. Purpose
Documents Anonymous / Customer / Staff / Admin access for every API resource. Derived from frozen logical model, capability matrix, domain invariants, and api-exposure-classification.md. No endpoint URLs or HTTP methods defined.

**Rule:** Staff/Admin columns are later refined by Group D authorization, but V1 boundaries are: public catalog is PUBLIC_READ, commerce is CUSTOMER_OWNED with backend authority, operational management is STAFF/ADMIN.

## 2. Full Matrix

| Resource | Anonymous | Customer (Authenticated) | Staff | Admin | Exposure Policy |
|---|---|---|---|---|---|
| **Category** | Read | Read | Manage | Manage | PUBLIC_READ / STAFF/ADMIN_OPERATIONAL |
| **Product** | Read | Read | Manage | Manage | PUBLIC_READ / STAFF/ADMIN_OPERATIONAL |
| **Product Image** | Read via product | Read via product | Manage (via product) | Manage (via product) | PUBLIC_READ via Product / STAFF/ADMIN_OPERATIONAL |
| **Product Variant** | Read via product | Read via product | Manage (via product) | Manage (via product) | PUBLIC_READ via Product / STAFF/ADMIN_OPERATIONAL |
| **Product Availability** (representation) | Limited Read (via product) | Limited Read (via product) | Manage (via Inventory) | Manage (via Inventory) | PUBLIC_READ (limited signal) / INTERNAL_ONLY (quantities) |
| **Inventory** | No | No | Manage | Manage | INTERNAL_ONLY + STAFF/ADMIN_OPERATIONAL |
| **Cart** | Own via GUEST_TOKEN (holder) | Own (AUTHENTICATED) — guest cart binds on login | No | No | CUSTOMER_OWNED (holder-scoped) |
| **Cart Item** | Own via Cart (guest) | Own via Cart | No | No | CUSTOMER_OWNED via Cart |
| **Checkout** (workflow) | No (rejected) | Own — initiate checkout (authenticated only) | No | No | CUSTOMER_OWNED (workflow) |
| **Order** | No | Own (read own orders/details/tracking; cancel own within window) | Operational (all orders: read, accept/process/ship/deliver) | Operational + Manage customers | CUSTOMER_OWNED + STAFF/ADMIN_OPERATIONAL |
| **Order Item** | No | Own via Order | Operational via Order | Operational via Order | CUSTOMER_OWNED via Order |
| **Order Address (billing/delivery)** | No | Own via Order (billing required; delivery when DELIVERY) | Operational via Order | Operational via Order | CUSTOMER_OWNED + STAFF/ADMIN |
| **Delivery / Fulfillment** | No | Own via Order (when DELIVERY) | Operational | Operational | CUSTOMER_OWNED + STAFF_OPERATIONAL |
| **Payment** | No | Limited own (status/amount, not secrets) | Operational (verify/status) | Operational (verify/status/admin) | CUSTOMER_OWNED (limited) + INTERNAL_ONLY (secrets) |
| **Order Tracking / Status History** | No | Own — read timeline (excludes Operational note) | Operational (full with notes) | Operational (full with notes) | CUSTOMER_OWNED (projection) + STAFF/ADMIN (full) |
| **Made-to-order Request** | Create | Own (if linked to account) — Create/Read own | Manage (all: read, update status) | Manage (all) | PUBLIC (create) + CUSTOMER_OWNED + STAFF/ADMIN_OPERATIONAL |
| **General Enquiry** | Create | Own (if linked) — Create/Read own | Manage (all) | Manage (all) | PUBLIC (create) + CUSTOMER_OWNED + STAFF/ADMIN_OPERATIONAL |
| **Attachment** | Via owner (create with Request/Enquiry) | Via owner (own request/enquiry) | Manage (via owner) | Manage (via owner) | CUSTOMER_OWNED via owner + STAFF/ADMIN (owner-scoped, not public URL) |
| **User / Profile** | No — except Register/Login | Own (read/update own profile) | Own/Restricted (own profile + permitted ops) | Manage (all users/roles per Group D) | CUSTOMER_OWNED + ADMIN_OPERATIONAL |
| **Notification** | No | Own (recipient only) | N/A | Administrative (system) | CUSTOMER_OWNED (recipient) |
| **Authentication** (register/login/logout/recovery/verification/session) | Register/Login/Recovery: Yes — public operations | Logout/Verification/Session: Authenticated | N/A | N/A | PUBLIC (register/login) + CUSTOMER_OWNED (session) + INTERNAL_ONLY (secrets) |

## 3. Notes

### 3.1 Guest Cart
Guest cart uses **GUEST_TOKEN** (bearer, anonymous persistent cart) distinct from **AUTHENTICATED** user session (DM-DEC-04, IDENT-008/CART-002, api-exposure-classification.md). Holder-scoped: guest holder or authenticated owner. Cart is owner-scoped, not cross-customer. `*` from Phase 1.6 §29 is resolved: anonymous persistent carts are approved per Decision 18.

### 3.2 Operational Scoping
- **Customer Own** = can access only own records (orders, cart, notifications, own requests/enquiries). Backend ownership checks required (IDENT-006, AUTHZ-003).
- **Staff Operational** = can read/update operational state of all orders/requests/enquiries/inventory/products per Group D policies; cannot manage users/roles beyond permitted scope.
- **Admin Operational** = broader (product/category/inventory/category management, customer management, role management per Group D).
- **N/A** for Notification Staff = staff do not have notifications inbox; admin has system view.

### 3.3 Public vs Customer-Owned vs Internal
- Public creation allowed only for **Request** and **Enquiry** (VISION.md: anyone can Request/Enquire without account).
- No anonymous access to Cart/Order/Payment/Profile/Notification/Tracking (AGENTS.md: checkout requires account).
- Inventory quantities and payment provider secrets are **INTERNAL_ONLY**; never in public/authenticated broad responses.

### 3.4 Anonymous Resource Principle (Phase 1.6 §39)
Only **Request** and **Enquiry** allow anonymous creation. Do not allow anonymous Order/Payment/Profile/Notification creation. Cart anonymous creation is via GUEST_TOKEN holder (approved guest cart), not as an anonymous Order.

## 4. Public vs Restricted Summary

| Class | Resources |
|---|---|
| PUBLIC_READ | Category, Product, Product Image (via product), Product Variant (via product), Availability (limited signal), Request Create, Enquiry Create, Register/Login |
| CUSTOMER_OWNED | Cart/CartItem (holder), Checkout, Order/OrderItem/Delivery/Payment(limited)/Tracking(projection)/Notification, own Requests/Enquiries, own Profile |
| STAFF_OPERATIONAL | Inventory manage, Product/Category manage (read+manage), Order operational (accept/process/ready/ship/deliver), Request/Enquiry manage, Payment verify |
| ADMIN_OPERATIONAL | All Staff + User/Customer management, role management (Group D) |
| INTERNAL_ONLY | Inventory physical/reserved quantities, Payment provider secrets/verification internals, password hashes/reset secrets |

## 5. Checklist Coverage
- Public catalog read without auth: Category, Product, Image, Variant, Availability — Anonymous Read ✓
- Customer commerce ownership: Cart/Order/Payment/Delivery/Tracking — Own only ✓
- Staff/Admin operational mgmt identified: Inventory, Products, Orders, Requests, Enquiries ✓
- Internal-only not accidentally exposed: Inventory quantities, payment secrets ✓
