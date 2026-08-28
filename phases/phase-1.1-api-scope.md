# Phase 1.1 — Define API Scope

## Purpose

This phase establishes the **scope boundary of the API** before any Laravel implementation, database migrations, frontend pages, Flutter screens, or OpenAPI implementation begins.

The output is a concise API scope document that answers:

> What business capabilities must the backend expose for version 1 of the furniture e-commerce platform?

This is a planning/contract-definition phase, not a coding phase.

---

## Governing Project Context

Project: small-scale furniture e-commerce platform.

Frontend clients:
- Public website: Next.js + MUI
- Mobile app: Flutter + Material 3
- Admin: Next.js + MUI

Backend:
- Laravel API
- MySQL

Architecture:

```text
Next.js Website ──┐
                  ├── HTTPS API ── Laravel ── MySQL
Flutter App ──────┤
Admin App ────────┘
```

The API owns business rules. Clients do not connect directly to MySQL.

Version 1 business capabilities include:
- Anonymous/public product and category browsing without registration or login
- Customer accounts
- Product/category discovery
- In-stock purchases
- Cart
- Checkout
- Pickup or paid delivery
- Payment integration boundary
- Order lifecycle and tracking
- Made-to-order furniture requests
- General enquiries
- Admin/staff operations
- Notifications where required by completed workflows

Version 1 intentionally excludes premature enterprise functionality such as multi-vendor marketplace support, warehouse-management ERP, route optimization, complex manufacturing planning, or real-time courier GPS tracking unless a later approved phase adds them.

---

# 1. Objective

Define the **API capability map** for Version 1 without designing implementation details yet.

The API scope must be expressed in terms of business capabilities and resources, not frontend screens.

Good:

```text
Products
Orders
Inventory
Payments
Furniture Requests
Enquiries
```

Avoid defining scope as:

```text
Homepage API
Flutter home screen API
Sofa page API
Admin dashboard API
```

Frontend screens consume API capabilities; they do not define the domain model.

---

# 2. What This Phase Must Produce

Create exactly these planning artifacts:

## 2.1 API Scope Map

A list of all Version 1 API capability areas.

### Mandatory anonymous browsing rule

Public product discovery must not require authentication. A visitor who has never registered or logged in must be able to:

- Open the homepage and public catalog
- Browse categories
- Search and filter publicly available products
- Open product-detail pages
- View product images, descriptions, prices, variants, and public availability
- View whether a product is `IN_STOCK` or `MADE_TO_ORDER`
- Access public furniture information needed to decide whether to purchase or submit a request

The agent must not design the API so that public catalog reads depend on a user session, access token, or account record. Authentication is required only for capabilities that genuinely require an identified customer or protected data.

This rule exists independently of the Next.js SEO implementation: the public API capability itself must support anonymous access.


Recommended initial areas:

```text
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
12. Pickup/delivery
13. Furniture requests
14. General enquiries
15. Notifications
16. Admin/staff management capabilities
```

Do not define endpoint URLs yet beyond noting the capability.

---

## 2.2 Capability Ownership Matrix

For every capability, identify:

- What business responsibility it represents
- Which client(s) consume it
- Whether it is customer-facing or internal
- Whether it is read-only or state-changing
- Whether it contains sensitive data
- Which later phase depends on it

Use a table like:

| Capability | Customer Web | Flutter | Admin | State-changing | Sensitive | Depends on |
|---|---|---|---|---|---|---|
| Public product/catalog discovery | Yes | Yes | Yes | No | No | Categories, products |
| Authentication | Optional for public browsing; required for protected account operations | Yes | Yes | Yes | Yes | User model |
| Products | Yes | Yes | Yes | Yes (admin) | No | Categories |
| Cart | Yes | Yes | No | Yes | Medium | Products, inventory |
| Orders | Yes | Yes | Yes | Yes | High | Cart, checkout |

The exact final rows must be determined during this phase.

---

# 3. Define Version 1 API Boundaries

For every proposed capability, answer:

### In scope?

Use:

```text
IN
OUT
DEFERRED
```

### Access level

For each capability, explicitly classify access as:

```text
PUBLIC_ANONYMOUS
AUTHENTICATED_CUSTOMER
STAFF_ONLY
ADMIN_ONLY
MIXED
```

The default for catalog discovery is `PUBLIC_ANONYMOUS`. Do not mark product/category reads as authenticated merely because carts, orders, or wishlists may later require authentication.

### Why?

Give one business reason.

### Owner

Identify whether it is primarily:

```text
Customer
Staff
Admin
System
```

### Dependency

Identify what must exist before the capability can work.

Example:

```text
Orders
depends on:
- authenticated customer
- purchasable product
- cart
- inventory validation
- checkout
```

Do not allow vague dependencies such as “frontend ready”.

Dependencies must be domain or infrastructure dependencies.

---

# 4. Define the Three Commerce Workflows

The API scope must explicitly distinguish these three flows.

## 4.1 Normal Purchase

```text
Browse
  ↓
Product
  ↓
Cart
  ↓
Checkout
  ↓
Payment
  ↓
Order
  ↓
Fulfilment
  ↓
Tracking
```

The API must support this complete flow.

## 4.2 Made-to-Order Request

```text
Browse
  ↓
Made-to-order product
  ↓
Request furniture
  ↓
Admin/staff follow-up
  ↓
Possible quotation/business agreement
```

This must NOT automatically become a normal order.

## 4.3 General Enquiry

```text
Customer
  ↓
General enquiry
  ↓
Admin/staff follow-up
```

This is also separate from normal orders.

---

# 5. Define the Fulfilment Boundary

The scope must explicitly support:

```text
PICKUP
DELIVERY
```

Pickup is free.

Delivery has a backend-controlled fee.

Do not define sophisticated logistics at this stage.

The API scope should support enough information to:

- choose fulfilment type
- calculate/apply delivery fee
- store delivery details where needed
- represent shipment/ready-for-pickup state
- expose fulfilment status to the customer

Do not design GPS tracking, route planning, or driver management in this phase.

---

# 6. Define Order-State Scope

The API scope must recognize an explicit order lifecycle.

Initial candidate states:

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

At this phase:

- Confirm which states are required for Version 1.
- Identify who may trigger each state transition.
- Identify which transitions are valid.
- Record any open business-rule questions.

Do NOT implement the state machine yet.

The actual status transition rules belong to a later detailed contract phase.

---

# 7. Define Customer API Scope

At minimum, evaluate these customer capabilities:

```text
Register
Login
Logout
Password recovery
Profile
Addresses/contact information
Browse categories
Browse products
View product details
Search/filter/sort
View cart
Modify cart
Checkout
Pay
View orders
View order details
Track order
Submit made-to-order request
Submit enquiry
View relevant notifications
```

Do not add wishlist, reviews, coupons, loyalty points, referrals, subscriptions, or other features unless the business explicitly approves them for Version 1.

Those can be listed under `DEFERRED`.

---

# 8. Define Admin/Staff API Scope

At minimum, evaluate:

```text
Manage categories
Manage products
Manage product images
Manage product variants
Manage inventory
Review orders
Update order status
Review customers
Review made-to-order requests
Update request status
Review enquiries
Update enquiry status
View payment status
Manage fulfilment information
```

Do not yet define the exact admin UI.

This phase only defines backend capabilities required by the admin client.

---

# 9. Define System Capabilities

Identify backend/system capabilities that are not directly initiated by a screen.

Candidates:

```text
Payment webhook handling
Order status history recording
Inventory reservation/release
Notification dispatch
Audit logging
File/image handling boundary
Background jobs where necessary
```

Mark each as:

```text
IN
DEFERRED
OUT
```

and explain why.

---

# 10. Define API Resource Families — Names Only

At this phase, identify resource families but do not define full endpoint contracts.

Possible initial families:

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

The actual resource naming and endpoint standards will be finalized in later Phase 1 micro-phases.

Do not invent endpoint methods, request bodies, or response schemas unless needed to explain scope.

---

# 11. Scope by Dependency, Not by Convenience

For each capability, identify its dependencies.

A rough dependency graph should look similar to:

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

And separately:

```text
Users/Auth ──→ Furniture Requests ──→ Staff/Admin
Users/Auth ──→ Enquiries ────────────→ Staff/Admin
```

The graph is conceptual only. Do not redesign the entire system during this phase.

---

# 12. Identify Data Sensitivity at Scope Level

Mark capabilities that handle sensitive information.

Likely examples:

```text
Authentication       HIGH
Customer profile     HIGH
Addresses            HIGH
Payments             HIGH
Orders               HIGH
Enquiries            MEDIUM
Products             LOW
Categories           LOW
```

This is only a scope-level security classification.

Detailed authorization, validation, encryption, and privacy implementation belongs to later phases.

---

# 13. Identify What the API Must NOT Do

Explicitly record non-responsibilities.

Examples:

```text
The API must not:
- connect the browser directly to MySQL
- allow frontend clients to determine authoritative order totals
- allow frontend clients to declare payment success without backend verification
- expose database credentials
- allow customers to arbitrarily change order status
- treat made-to-order requests as automatic orders
- implement enterprise logistics unless later approved
```

This section is mandatory because negative boundaries prevent scope drift.

---

# 14. API Scope Acceptance Criteria

Phase 1.1 is complete only when all of the following are true:

- Every Version 1 business workflow has an identified API capability.
- Normal purchases, made-to-order requests, and enquiries are explicitly separated.
- Customer, staff/admin, and system capabilities are identified.
- Fulfilment supports pickup and delivery.
- Order lifecycle scope is identified.
- Major dependencies between capability areas are documented.
- In-scope, deferred, and out-of-scope functionality is explicitly separated.
- No frontend screen is being used as a substitute for domain scope.
- No implementation code was added.
- No database migration was created.
- No Laravel controller/model/service was created.
- No Next.js or Flutter code was created.
- Open questions that affect later API design are recorded rather than silently guessed.

---

# 15. Required Output Format

Produce one document named:

```text
api-scope-v1.md
```

Use this structure:

```text
# API Scope — Version 1

## 1. Purpose

## 2. Business Capabilities

## 3. Capability Ownership Matrix

## 4. Normal Purchase Workflow

## 5. Made-to-Order Workflow

## 6. General Enquiry Workflow

## 7. Fulfilment Scope

## 8. Order Lifecycle Scope

## 9. Customer Capabilities

## 10. Staff/Admin Capabilities

## 11. System Capabilities

## 12. Resource Families

## 13. Dependency Map

## 14. Security/Sensitivity Classification

## 15. In Scope

## 16. Deferred

## 17. Out of Scope

## 18. Open Questions

## 19. Phase 1.1 Acceptance Checklist
```

Keep it concise enough to review easily. This is a scope-control document, not a complete API reference.

---

# 16. Agent Behavior Rules During This Phase

The AI agent must:

1. Read the root `AGENTS.md` before working.
2. Treat `AGENTS.md` as the governing project instruction.
3. Work only on Phase 1.1.
4. Prefer clarification through explicit `Open Questions` rather than silently inventing business rules.
5. Preserve the small-business scope.
6. Avoid enterprise abstractions.
7. Avoid implementation code.
8. Avoid database schema design.
9. Avoid endpoint-level detail that belongs to later micro-phases.
10. Avoid frontend implementation.
11. Avoid choosing libraries/packages unless they are already fixed by the project architecture.
12. Do not modify unrelated project files.
13. Do not start Phase 1.2 automatically.
14. At completion, summarize exactly what was established and list unresolved questions.

---

# 16.1 Acceptance Criteria for Anonymous Browsing

Phase 1.1 is incomplete unless the scope document explicitly confirms all of the following:

- A customer can browse the product catalog without creating an account.
- A customer can open product-detail information without logging in.
- Public catalog reads do not require an access token.
- Authentication is separated from public discovery in the capability map.
- The API scope distinguishes public data from protected customer data.
- The document does not imply that SEO depends on authentication.
- Cart, checkout, order history, wishlist, profile, and other account-specific operations may have their own authentication rules and are not used as a reason to block anonymous product browsing.

---

# 17. Stop Condition

When `api-scope-v1.md` satisfies the acceptance criteria:

**STOP.**

Do not proceed to Phase 1.2.

The next phase must be requested explicitly by the project owner.
