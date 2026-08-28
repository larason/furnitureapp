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

Public catalog reads do not require a session, access token, or account record. Authentication is required only for capabilities that genuinely require an identified customer or protected data (cart, checkout, orders, profile, requests, enquiries).

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

---

## 7. Fulfilment Scope

Version 1 supports exactly two fulfilment types:

```text
PICKUP       → free
DELIVERY     → backend-controlled fee
```

Scope support includes:

- Choosing a fulfilment type at checkout
- Backend calculation/application of the delivery fee (flat rate in Version 1; no zone/distance/size pricing)
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
- At this scope phase, who may trigger each transition and the precise valid-transition rules are **open questions** (see section 18) to be finalized in the order-contract phase.
- Cancellation rules (who can cancel, at which statuses, refund behavior) are **open questions**.

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
| View cart | AUTHENTICATED_CUSTOMER | No |
| Modify cart | AUTHENTICATED_CUSTOMER | Yes |
| Checkout | AUTHENTICATED_CUSTOMER | Yes |
| Pay | AUTHENTICATED_CUSTOMER | Yes |
| View orders | AUTHENTICATED_CUSTOMER (own orders only) | No |
| View order details | AUTHENTICATED_CUSTOMER (own orders only) | No |
| Track order | AUTHENTICATED_CUSTOMER (own orders only) | No |
| Submit made-to-order request | AUTHENTICATED_CUSTOMER (registered customers; see section 18 open question) | Yes |
| Submit enquiry | AUTHENTICATED_CUSTOMER or PUBLIC_ANONYMOUS (see open question) | Yes |
| View relevant notifications | AUTHENTICATED_CUSTOMER | No |

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
| Email delivery (password recovery, order emails) | IN (minimal) | Depends on email provider decision; see section 18 |
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
- Customer accounts: register, login, logout, password recovery, profile, addresses
- Cart for `IN_STOCK` products
- Checkout with pickup or delivery
- Backend-authoritative monetary totals (subtotal, delivery fee, total)
- Payment integration boundary with backend-verified payment (webhook pattern, idempotent)
- Orders with a backend-validated state machine and full status history
- Customer order list, order detail, tracking timeline
- Made-to-order furniture requests as leads, separate from orders
- General enquiries as leads, separate from orders
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
- Advanced delivery zones / dynamic delivery pricing
- Dynamic delivery fee by distance/size/item count
- Background jobs/queues (until volume requires them)
- GPS / real-time courier tracking
- Manufacturing workflow / production planning
- Email HTML template system beyond minimal transactional messages
- Payment provider selection details (decision required; see section 18)

## 17. Out of Scope

The API must **not**:

- Allow browser/Flutter/admin to connect directly to MySQL
- Allow frontend clients to determine authoritative order totals
- Allow frontend clients to declare payment success without backend verification
- Expose database credentials
- Allow customers to arbitrarily change order status
- Treat made-to-order requests as automatic orders
- Implement enterprise warehouse-management / ERP / route optimization
- Implement multi-vendor marketplace functionality
- Implement enterprise-scale logistics infrastructure
- Store raw passwords

---

## 18. Open Questions

Business decisions affecting later API design. Recorded rather than silently guessed. Smallest safe assumptions are noted in parentheses where a placeholder is needed to proceed.

1. **Guest checkout vs registration-required checkout.** May a visitor check out without an account, or must they register first? (Assumption: registration required before checkout, per VISION.md customer flow; to be confirmed.)
2. **Furniture request identity.** Must a customer be logged in to submit a made-to-order request, or can it be anonymous with name/phone/email fields? (Assumption: allow authenticated; anonymous option open.)
3. **Enquiry identity.** Same question as #2 for general enquiries.
4. **Delivery fee.** Confirmed flat rate (TZS 20,000 per VISION.md) for Version 1, or variable? (Assumption: flat rate.)
5. **Payment provider.** Which provider(s) in Version 1? Payment secrets live on the backend only. (Assumption: integration boundary defined; provider selected in Phase Group H.)
6. **Order cancellation rules.** Who may cancel (customer/staff), at which statuses, and is a refund required? (Assumption: customer may cancel only before `PAID`; staff/admin may cancel later; refunds handled as a separate payment operation. To be confirmed.)
7. **Order numbering format.** Human-readable order reference format (e.g., `ORD-XXXXX`). (Assumption: `ORD-` prefix + sequence.)
8. **Email/notification delivery.** Does Version 1 require real email sending for password recovery and order updates, or is in-app notification display sufficient initially? (Assumption: in-app first, email deferred to Phase Group R.)
9. **Password recovery / email verification.** Required for Version 1, and which delivery channel?
10. **Saved addresses.** Persistent address book on the profile, or capture address per order only? (Assumption: capture per order; saved address book deferred.)
11. **Staff vs admin split.** Exact role/permission granularity. (Assumption: `staff` and `admin` roles with policies defined in Phase Group D.)
12. **Request/enquiry attachments.** Is image/file attachment on requests/enquiries required for Version 1, and via which storage? (Assumption: optional attachment support in boundary; provider deferred.)

---

## 19. Phase 1.1 Acceptance Checklist

- [x] Every Version 1 business workflow has an identified API capability
- [x] Normal purchases, made-to-order requests, and enquiries are explicitly separated
- [x] Customer, staff/admin, and system capabilities are identified
- [x] Fulfilment supports pickup (free) and delivery (fee)
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