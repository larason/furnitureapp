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

## 18. Resolved Business Decisions

The following decisions have been confirmed by the project owner and MUST be treated as authoritative for later phases unless explicitly changed by the project owner.

1. **Guest browsing is allowed.** Visitors may browse the public catalog, categories, search/filter products, and view product details without registration or login.

2. **Checkout requires an account.** A customer must be authenticated before checkout. Checkout must collect the required payment information and billing address.

3. **Made-to-order requests may be anonymous.** A visitor does not need an account to submit a furniture request. Anonymous requests must collect sufficient contact information, including email and/or phone as required by the form.

4. **General enquiries may be anonymous.** The same identity rule applies to general enquiries: authentication is not required, and the enquiry must collect contact information such as email and/or phone.

5. **Delivery fee is variable and staff-controlled.** Version 1 does not assume a fixed delivery price. The delivery fee is determined and entered directly by authorized admin/staff as part of order fulfilment/checkout operations. Later phases must define the exact point at which the fee is set and validated.

6. **Payment provider selection belongs to Phase Group G.** Phase 1.1 must not select or hard-code a payment provider. The API scope must preserve a provider-agnostic payment boundary so Phase Group G can define the implementation.

7. **Customer cancellation has a 20-minute window.** The customer may cancel an order only within 20 minutes of order creation, subject to later detailed status/payment rules. The exact transition and refund behavior must be defined in the later order/payment contract phases; it must not be guessed here.

8. **Order reference format is `OD-****`.** The later order-contract phase must define the exact sequence generation and collision-safe implementation while preserving the `OD-` human-readable prefix.

9. **Email/notification sending is deferred to Phase Group R.** Phase 1.1 must not assume real email delivery or external notification delivery for Version 1. The notification capability may be scoped, but external delivery implementation remains deferred until Phase Group R.

10. **Password recovery and email verification must use a secure, backend-controlled approach.** The exact delivery mechanism is intentionally deferred to the authentication/security phases. The system must not invent an insecure client-side password reset mechanism or expose password-reset secrets to frontend applications.

11. **Saved address books are deferred.** Customers will not have a persistent saved-address book in the initial scope. Address information is captured as needed for checkout/order fulfilment.

12. **Staff/admin permission granularity is deferred to Phase Group D.** Phase 1.1 recognizes separate staff/admin operational access but does not define the final permission matrix.

13. **Request/enquiry attachments are optional.** The scope must allow optional file/image attachments for made-to-order requests and general enquiries. Storage provider and upload implementation are deferred to the appropriate later phase.

14. **Anonymous requests/enquiries remain separate from orders.** An anonymous furniture request or enquiry must not be silently converted into an order. A later business action can create a normal order only through the approved purchase/quotation workflow.

15. **Small-business scope remains mandatory.** Do not introduce enterprise logistics, warehouse ERP, route optimization, manufacturing planning, marketplace/multi-vendor functionality, or similar complexity unless separately approved.

---

## 19. Phase 1.1 Acceptance Checklist

Phase 1.1 is accepted only when the resulting `api-scope-v1.md` explicitly reflects the following confirmed decisions:

- [ ] Anonymous users can browse categories and products without login.
- [ ] Anonymous users can search/filter and view product details without login.
- [ ] Checkout requires an authenticated customer account.
- [ ] Checkout requires payment information and billing address.
- [ ] Made-to-order furniture requests can be submitted anonymously with contact information.
- [ ] General enquiries can be submitted anonymously with contact information.
- [ ] Delivery pricing is variable and entered/controlled by authorized admin/staff rather than assumed to be a fixed Version 1 fee.
- [ ] Payment provider selection is explicitly deferred to Phase Group G.
- [ ] Customer order cancellation is limited to a 20-minute window, with detailed refund/status rules deferred to later phases.
- [ ] Order references use the `OD-` prefix.
- [ ] Real email/notification delivery is deferred to Phase Group R.
- [ ] Password recovery/email verification will use a secure backend-controlled approach, with implementation deferred to the authentication/security phases.
- [ ] Saved address books are deferred.
- [ ] Staff/admin permission granularity is deferred to Phase Group D.
- [ ] Attachments for requests/enquiries are optional.
- [ ] No Laravel, database, frontend, Flutter, or payment implementation is introduced during Phase 1.1.

---

## 20. Stop Condition

When `api-scope-v1.md` satisfies the acceptance criteria:

**STOP.**

Do not proceed to Phase 1.2.

The next phase must be requested explicitly by the project owner.
