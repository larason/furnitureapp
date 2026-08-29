# API Scope — Version 1

## 1. Purpose

This document defines the **scope boundary** of the furniture e-commerce API for Version 1.

It answers the question:

> What business capabilities must the backend expose for Version 1?

It is a scope-control document, not an API reference. It deliberately does **not** define endpoint URLs, request bodies, response schemas, validation rules, or database tables. Those are covered by later Phase 1 micro-phases (contract details) and the Laravel/database phases.

The API is the single authority for all business rules. Website (Next.js + MUI), mobile (Flutter + Material 3), and admin (Next.js + MUI) consume the same backend. No client connects to MySQL directly.

---

## 2. Business Capabilities

Version 1 exposes the following business capability areas:

1. Authentication
2. Customer profile/account
3. Categories
4. Products
5. Product variants
6. Inventory availability
7. Cart
8. Checkout
9. Payments
10. Orders
11. Order status/tracking
12. Pickup/delivery (fulfilment)
13. Furniture requests (made-to-order)
14. General enquiries
15. Notifications
16. Admin/staff management capabilities

These are expressed as business capabilities and resource families, not frontend screens.

### Mandatory anonymous browsing rule

Public product discovery must not require authentication. A visitor who has never registered or logged in must be able to:

- Open the public catalog
- Browse categories
- Search and filter publicly available products
- Open product-detail pages
- View product images, descriptions, prices, variants, and public availability
- View whether a product is `IN_STOCK` or `MADE_TO_ORDER`

Public catalog reads do not require a session, access token, or account record. Authentication is required only for capabilities that genuinely require an identified customer or protected data (checkout, orders, profile, requests, enquiries). A guest may maintain a cart without an account; the cart binds to the customer's account on authentication.

---

## 3. Capability Ownership Matrix

| Capability | Customer Web | Flutter | Admin | State-changing | Sensitive | Depends on |
|---|---|---|---|---|---|---|
| Public catalog/category discovery | Yes | Yes | Yes | No | No | Categories, products |
| Product detail / search / filter | Yes | Yes | Yes | No | No | Products, variants, inventory |
| Authentication | Yes | Yes | Yes | Yes | High | User model |
| Customer profile | Yes | Yes | Yes (view) | Yes | High | Authentication |
| Categories (read) | Yes | Yes | Yes | No | No | — |
| Products (read) | Yes | Yes | Yes | No | No | Categories |
| Products (manage) | No | No | Yes | Yes | Medium | Authentication, categories |
| Product variants | Yes | Yes | Yes | Yes (admin) | Low | Products |
| Inventory availability (read) | Yes | Yes | Yes | No | Low | Products, variants |
| Inventory management | No | No | Yes | Yes | Medium | Inventory model |
| Cart | Yes | Yes | No | Yes | Medium | Products, inventory, customer |
| Checkout | Yes | Yes | No | Yes | High | Cart, customer, fulfilment |
| Payments | Yes | Yes | Yes (view) | Yes | High | Checkout, order |
| Orders | Yes | Yes | Yes | Yes | High | Cart, checkout, payments |
| Order status / tracking | Yes | Yes | Yes | Yes (admin) | Medium | Orders, status history |
| Pickup/delivery | Yes | Yes | Yes | Yes | Medium | Checkout, orders |
| Furniture requests | Yes | Yes | Yes | Yes | Medium | Products (optional), customer |
| General enquiries | Yes | Yes | Yes | Yes | Medium | Customer (optional) |
| Notifications | Yes | Yes | No | Yes (system) | Medium | Orders, requests, enquiries |

Access-level classification follows in sections 9–11.

---

## 4. Normal Purchase Workflow

The API supports the complete in-stock purchase flow:

```text
Browse
  ↓
Product (IN_STOCK)
  ↓
Cart
  ↓
Checkout (choose pickup/delivery)
  ↓
Payment
  ↓
Order
  ↓
Fulfilment
  ↓
Tracking
```

Business rules owned by the backend for this flow:

- Only `IN_STOCK` products are purchasable.
- Final availability is checked by Laravel against inventory, never trusted from client input.
- Monetary values (subtotal, delivery fee, total) are calculated/verified by Laravel; client totals are never authoritative.
- Payment success is verified by backend (provider callback/webhook); frontends never declare payment success.
- Order status transitions are validated by the backend state machine.

---

## 5. Made-to-Order Workflow

```text
Browse
  ↓
Product (MADE_TO_ORDER)
  ↓
Request furniture
  ↓
Admin/staff follow-up
  ↓
Possible quotation / business agreement
```

Rules:

- A made-to-order request is **not** automatically an order.
- A request becomes an order only through an explicit business process (admin/staff agreement on commercial terms), which is defined in a later phase.
- `MADE_TO_ORDER` products are not added to cart and not purchased directly.
- Requests may be submitted **anonymously**; authentication is not required. Anonymous requests must collect sufficient contact information (email and/or phone). An anonymous request is never silently converted into an order.

---

## 6. General Enquiry Workflow

```text
Customer
  ↓
General enquiry
  ↓
Admin/staff follow-up
```

Rules:

- Enquiries are a separate workflow from normal orders and made-to-order requests.
- An enquiry may or may not reference a product; it is not required to.
- Enquiries are leads for the sales/admin team, not orders.
- Enquiries may be submitted **anonymously**; authentication is not required. Anonymous enquiries must collect contact information such as email and/or phone. An anonymous enquiry is never silently converted into an order.

---

## 7. Fulfilment Scope

Version 1 supports exactly two fulfilment types:

```text
PICKUP       → free
DELIVERY     → backend-controlled fee
```

Scope support includes:

- Choosing a fulfilment type at checkout
- Delivery fee is **variable and staff-controlled**: the fee is determined and entered directly by authorized admin/staff as part of order fulfilment/checkout operations, rather than a fixed Version 1 rate. The exact point at which the fee is set and validated is defined in a later phase.
- Capturing delivery details (customer name, phone, address)
- Representing `READY_FOR_PICKUP` and `SHIPPED` states
- Exposing fulfilment status to the customer in the tracking timeline

Out of scope: GPS tracking, route planning, courier/driver management, delivery-agent functionality, dynamic delivery pricing zones.

---

## 8. Order Lifecycle Scope

Version 1 order states:

```text
PENDING_PAYMENT
PAID
ACCEPTED
PROCESSING
READY_FOR_PICKUP
SHIPPED
DELIVERED
COMPLETED
CANCELLED
```

Pickup path:

```text
PAID → ACCEPTED → PROCESSING → READY_FOR_PICKUP → COMPLETED
```

Delivery path:

```text
PAID → ACCEPTED → PROCESSING → SHIPPED → DELIVERED → COMPLETED
```

Scope decisions:

- Not every order uses every status.
- Statuses are validated by the backend. Clients never invent or force status changes.
- Every significant transition is recorded in an order status history (used as the customer tracking timeline and for operational accountability).
- Order references use the human-readable `OD-` prefix. Exact sequence generation and collision-safe implementation are defined in the later order-contract phase.
- At this scope phase, who may trigger each transition and the precise valid-transition rules are **open questions** (see section 18, Resolved Business Decisions) to be finalized in the order-contract phase.
- **Customer cancellation window:** a customer may cancel an order only within 20 minutes of order creation. Exact transition and refund behavior are defined in later order/payment contract phases.

---

## 9. Customer Capabilities

All are `AUTHENTICATED_CUSTOMER` unless marked otherwise.

| Capability | Access | State-changing |
|---|---|---|
| Register | PUBLIC_ANONYMOUS | Yes |
| Login | PUBLIC_ANONYMOUS | Yes |
| Logout | AUTHENTICATED_CUSTOMER | Yes |
| Password recovery | PUBLIC_ANONYMOUS | Yes |
| Profile view/update | AUTHENTICATED_CUSTOMER | Yes |
| Addresses/contact information | AUTHENTICATED_CUSTOMER | Yes |
| Browse categories | PUBLIC_ANONYMOUS | No |
| Browse products | PUBLIC_ANONYMOUS | No |
| View product details | PUBLIC_ANONYMOUS | No |
| Search/filter/sort products | PUBLIC_ANONYMOUS | No |
| View cart | PUBLIC_ANONYMOUS or AUTHENTICATED_CUSTOMER (guest cart) | No |
| Modify cart | PUBLIC_ANONYMOUS or AUTHENTICATED_CUSTOMER (guest cart) | Yes |
| Checkout | AUTHENTICATED_CUSTOMER (account required) | Yes |
| Pay | AUTHENTICATED_CUSTOMER | Yes |
| View orders | AUTHENTICATED_CUSTOMER (own orders only) | No |
| View order details | AUTHENTICATED_CUSTOMER (own orders only) | No |
| Track order | AUTHENTICATED_CUSTOMER (own orders only) | No |
| Submit made-to-order request | PUBLIC_ANONYMOUS (contact info required) | Yes |
| Submit enquiry | PUBLIC_ANONYMOUS (contact info required) | Yes |
| View relevant notifications | AUTHENTICATED_CUSTOMER | No |

Checkout requires an authenticated customer account and collects payment information and billing address. Addresses are captured per checkout/order; there is no persistent saved-address book in Version 1.

Not in Version 1: wishlist, reviews/ratings, coupons, loyalty points, referrals, subscriptions, gift cards.

---

## 10. Staff/Admin Capabilities

Capabilities required by the admin client. All are `STAFF_ONLY` or `ADMIN_ONLY`; precise staff/admin split is defined by authorization policies in a later phase (Phase Group D).

| Capability | Access | State-changing |
|---|---|---|
| Manage categories | STAFF_ONLY / ADMIN_ONLY | Yes |
| Manage products | STAFF_ONLY / ADMIN_ONLY | Yes |
| Manage product images | STAFF_ONLY / ADMIN_ONLY | Yes |
| Manage product variants | STAFF_ONLY / ADMIN_ONLY | Yes |
| Manage inventory | STAFF_ONLY / ADMIN_ONLY | Yes |
| Review orders | STAFF_ONLY / ADMIN_ONLY | No |
| Update order status | STAFF_ONLY / ADMIN_ONLY | Yes |
| Review customers | ADMIN_ONLY | No |
| Review made-to-order requests | STAFF_ONLY / ADMIN_ONLY | No |
| Update request status | STAFF_ONLY / ADMIN_ONLY | Yes |
| Review enquiries | STAFF_ONLY / ADMIN_ONLY | No |
| Update enquiry status | STAFF_ONLY / ADMIN_ONLY | Yes |
| View payment status | STAFF_ONLY / ADMIN_ONLY | No |
| Manage fulfilment information | STAFF_ONLY / ADMIN_ONLY | Yes |

The admin UI is not defined in this phase; only the backend capabilities it requires.

---

## 11. System Capabilities

Backend/system capabilities not initiated directly by a screen.

| Capability | Status | Reason |
|---|---|---|
| Payment webhook handling | IN | Required for trustworthy payment verification |
| Order status history recording | IN | Required for tracking timeline and accountability |
| Inventory reservation/release | IN | Required to prevent overselling |
| Notification dispatch | IN (minimal) | Required for completed customer workflows; scope defined in Phase Group R |
| Audit logging | IN (minimal) | Operational accountability for status/payment changes |
| File/image handling boundary | IN (boundary only) | Product images and optional request/enquiry attachments; provider/storage chosen later |
| Background jobs | DEFERRED | Only needed when volume or async work requires them |
| Email delivery | DEFERRED | External email/notification sending is deferred to Phase Group R |
| Push notifications | DEFERRED | Push architecture is a later phase (Phase Group R) |

---

## 12. Resource Families

Names only. Full endpoint contracts are finalized in later Phase 1 micro-phases.

```text
/auth
/users
/profile
/categories
/products
/product-variants
/inventory
/cart
/checkout
/payments
/orders
/order-status-history
/deliveries
/furniture-requests
/enquiries
/notifications
/admin
```

API namespace uses `/api/v1`.

---

## 13. Dependency Map

Main commerce chain:

```text
Users/Auth
   ↓
Categories
   ↓
Products
   ↓
Inventory
   ↓
Cart
   ↓
Checkout
   ↓
Payments
   ↓
Orders
   ↓
Fulfilment
   ↓
Tracking/Notifications
```

Request/enquiry chains:

```text
Users/Auth ──→ Furniture Requests ──→ Staff/Admin
Users/Auth ──→ Enquiries ────────────→ Staff/Admin
```

Conceptual only. Not a redesign of the system.

---

## 14. Security/Sensitivity Classification

Scope-level only; implementation belongs to later phases.

| Capability | Sensitivity |
|---|---|
| Authentication | HIGH |
| Customer profile | HIGH |
| Addresses | HIGH |
| Payments | HIGH |
| Orders | HIGH |
| Cart | MEDIUM |
| Enquiries | MEDIUM |
| Furniture requests | MEDIUM |
| Notifications | MEDIUM |
| Products | LOW |
| Categories | LOW |
| Inventory availability | LOW (read) / MEDIUM (manage) |

---

## 15. In Scope

- Anonymous public catalog: categories, products, product detail, search/filter/sort, availability (`IN_STOCK` / `MADE_TO_ORDER`), prices
- Customer accounts: register, login, logout, password recovery, profile
- Cart for `IN_STOCK` products
- Checkout with pickup or delivery, requiring an authenticated customer account and billing/payment information
- Backend-authoritative monetary totals (subtotal, delivery fee, total); delivery fee variable and staff-controlled
- Payment integration boundary with backend-verified payment (webhook pattern, idempotent), provider-agnostic
- Orders with a backend-validated state machine, full status history, `OD-` references, and a 20-minute customer cancellation window
- Customer order list, order detail, tracking timeline
- Anonymous made-to-order furniture requests (with contact info) as leads, separate from orders
- Anonymous general enquiries (with contact info) as leads, separate from orders
- Optional attachments on requests/enquiries
- Minimal notifications tied to completed workflows
- Staff/admin operational capabilities (products, categories, inventory, orders, customers, requests, enquiries, payments, fulfilment)
- Inventory distinction: physical / reserved / available; overselling protection
- Historical order snapshots preserving purchased product info and price

## 16. Deferred

- Wishlist
- Reviews/ratings
- Coupons / promotions
- Loyalty points / customer segmentation
- Push notification infrastructure
- Payment provider selection (belongs to Phase Group G)
- Advanced delivery zones / dynamic delivery pricing
- Dynamic delivery fee by distance/size/item count (fee is staff-controlled in Version 1)
- Background jobs/queues (until volume requires them)
- GPS / real-time courier tracking
- Manufacturing workflow / production planning
- Email/notification delivery (Phase Group R)
- Password recovery / email verification delivery mechanism (auth/security phases; secure backend-controlled approach)
- Saved address books (addresses captured per checkout/order)
- Staff/admin permission matrix (Phase Group D)
- Request/enquiry attachment storage/upload implementation (boundary in scope, provider later)

## 17. Out of Scope

The API must **not**:

- Allow browser/Flutter/admin to connect directly to MySQL
- Allow frontend clients to determine authoritative order totals
- Allow frontend clients to declare payment success without backend verification
- Expose database credentials
- Allow customers to arbitrarily change order status
- Treat made-to-order requests as automatic orders
- Silently convert an anonymous request/enquiry into an order
- Expose password-reset secrets to frontend applications or use insecure client-side reset
- Implement enterprise warehouse-management / ERP / route optimization
- Implement multi-vendor marketplace functionality
- Implement enterprise-scale logistics infrastructure
- Store raw passwords

---

## 18. Resolved Business Decisions

The following decisions were confirmed by the project owner and are authoritative for later phases unless explicitly changed.

1. **Guest browsing is allowed.** Visitors may browse the public catalog, categories, search/filter products, and view product details without registration or login.
2. **Checkout requires an account.** A customer must be authenticated before checkout. Checkout collects the required payment information and billing address.
3. **Made-to-order requests may be anonymous.** Authentication is not required; anonymous requests collect sufficient contact information (email and/or phone).
4. **General enquiries may be anonymous.** Same identity rule as requests; contact information such as email and/or phone is collected.
5. **Delivery fee is variable and staff-controlled.** No fixed Version 1 fee is assumed. Authorized admin/staff determine and enter the fee as part of fulfilment/checkout. The exact set/validate point is defined later.
6. **Payment provider selection belongs to Phase Group G.** The API keeps a provider-agnostic payment boundary.
7. **Customer cancellation has a 20-minute window.** A customer may cancel within 20 minutes of order creation; exact transition/refund behavior is defined later.
8. **Order reference format is `OD-`.** Sequence generation and collision-safe implementation are defined in the order-contract phase.
9. **Email/notification sending is deferred to Phase Group R.** No real email/external notification delivery is assumed for Version 1.
10. **Password recovery/email verification use a secure backend-controlled approach.** Implementation deferred to authentication/security phases; no insecure client-side reset.
11. **Saved address books are deferred.** Addresses are captured per checkout/order.
12. **Staff/admin permission granularity is deferred to Phase Group D.** Separate staff/admin access is recognized but the final permission matrix is not defined here.
13. **Request/enquiry attachments are optional.** Scope allows optional file/image attachments; storage/upload implementation is deferred.
14. **Anonymous requests/enquiries remain separate from orders.** A later business action can create an order only through the approved purchase/quotation workflow.
15. **Small-business scope remains mandatory.** No enterprise logistics, ERP, route optimization, manufacturing planning, or marketplace features unless separately approved.

---

## 19. Phase 1.1 Acceptance Checklist

Confirmed decision checklist (per section 18 of the phase instructions):

- [x] Anonymous users can browse categories and products without login
- [x] Anonymous users can search/filter and view product details without login
- [x] Checkout requires an authenticated customer account
- [x] Checkout requires payment information and billing address
- [x] Made-to-order furniture requests can be submitted anonymously with contact information
- [x] General enquiries can be submitted anonymously with contact information
- [x] Delivery pricing is variable and entered/controlled by authorized admin/staff rather than a fixed Version 1 fee
- [x] Payment provider selection is explicitly deferred to Phase Group G
- [x] Customer order cancellation is limited to a 20-minute window, with detailed refund/status rules deferred to later phases
- [x] Order references use the `OD-` prefix
- [x] Real email/notification delivery is deferred to Phase Group R
- [x] Password recovery/email verification will use a secure backend-controlled approach, with implementation deferred to the authentication/security phases
- [x] Saved address books are deferred
- [x] Staff/admin permission granularity is deferred to Phase Group D
- [x] Attachments for requests/enquiries are optional
- [x] No Laravel, database, frontend, Flutter, or payment implementation is introduced during Phase 1.1

Capability acceptance:

- [x] Every Version 1 business workflow has an identified API capability
- [x] Normal purchases, made-to-order requests, and enquiries are explicitly separated
- [x] Customer, staff/admin, and system capabilities are identified
- [x] Fulfilment supports pickup (free) and delivery (staff-controlled fee)
- [x] Order lifecycle scope is identified with all nine states and pickup/delivery paths
- [x] Major dependencies between capability areas are documented
- [x] In-scope, deferred, and out-of-scope functionality is explicitly separated
- [x] No frontend screen is used as a substitute for domain scope
- [x] No implementation code was added
- [x] No database migration was created
- [x] No Laravel controller/model/service was created
- [x] No Next.js or Flutter code was created
- [x] Open questions affecting later API design are recorded rather than silently guessed

### Anonymous-browsing acceptance (section 16.1 of phase instructions)

- [x] A customer can browse the product catalog without creating an account
- [x] A customer can open product-detail information without logging in
- [x] Public catalog reads do not require an access token
- [x] Authentication is separated from public discovery in the capability map
- [x] The API scope distinguishes public data from protected customer data
- [x] The document does not imply that SEO depends on authentication
- [x] Cart, checkout, order history, profile, and other account-specific operations may have their own authentication rules and are not used as a reason to block anonymous product browsing

---

**Stop condition reached.** Phase 1.1 is complete. Do not proceed to Phase 1.2 until the project owner requests it.