# Testable Invariants — Version 1

## 1. Purpose

This document converts the important invariants from `domain-invariants.md` into **test scenarios**. It is an input to later automated test design (unit, feature/API, frontend, and end-to-end tests in Phase Group S).

Each scenario states business behavior in a recognizable format. Later test implementations translate these into actual tests.

Legend:

- **Given** — starting condition
- **When** — action performed
- **Then** — expected, unavoidable outcome

---

## 2. Catalog

### CAT-001 — Product identity
- **Given** a catalog containing products
- **When** a product is referenced by a cart, order, or request
- **Then** the reference resolves to exactly one unambiguous product

### CAT-002 — Product type
- **Given** the catalog
- **When** any product is classified
- **Then** its type is exactly `IN_STOCK` or `MADE_TO_ORDER`, with no other value

### CAT-003 — Product type controls action
- **Given** an active `IN_STOCK` product with sufficient stock
- **When** a customer views it
- **Then** the purchasable path (cart/buy) is available
- **Given** a `MADE_TO_ORDER` product
- **When** a customer attempts normal checkout on it
- **Then** the attempt is rejected (Product not purchasable, ERR-004)

### CAT-004 — Inactive product not purchasable
- **Given** an inactive product
- **When** a customer attempts to purchase it
- **Then** the purchase is rejected

### CAT-005 — Category is not a product
- **Given** a category
- **When** a customer attempts to purchase the category as an item
- **Then** the attempt is rejected

### CAT-006 — Catalog concepts stay distinct
- **Given** a product, its inventory, an order item, and a made-to-order request
- **When** any rule or operation references them
- **Then** the distinct concepts never collapse into one another (Product ≠ Inventory ≠ Order Item ≠ Request)

## 3. Inventory

### INV-001 — Backend authority
- **Given** the frontend displays "In Stock: 3"
- **When** the backend reports available stock of 0 at checkout
- **Then** the purchase fails

### INV-002 — No negative stock
- **Given** available stock of 0
- **When** a sale attempt is made
- **Then** the attempt is rejected and available stock remains at 0

### INV-003 — No overselling
- **Given** 3 units available
- **When** customer A requests 2 and customer B requests 2 concurrently
- **Then** no more than 3 total units are allocated (e.g., A=2 and B=1, or one buyer fails)

### INV-004 — Cart is not a reservation
- **Given** a customer adds the last unit to a cart
- **When** another customer buys it before the first checks out
- **Then** the first customer's checkout fails for insufficient stock

### INV-005 — Stale display
- **Given** a product was displayed as available
- **When** the product becomes unavailable before checkout
- **Then** checkout is rejected based on the authoritative, current stock

### INV-006 — Quantity model
- **Given** an inventory record for a purchasable item
- **Then** the system distinguishes physical, reserved, and available quantities and uses available for selling

### INV-007 — Variant-level inventory
- **Given** a product with variants
- **Then** inventory is tracked per variant where configured

## 4. Variants

### VAR-001/002 — Variant validity
- **Given** Product A and a variant belonging to Product B
- **When** a cart/checkout pairs them
- **Then** the operation is rejected (Invalid product variant, ERR-005)

### VAR-003 — Inactive variant
- **Given** an inactive variant
- **When** a customer tries to buy it
- **Then** the purchase is rejected

### VAR-004 — Backend checks variant
- **Given** a client-supplied variant selection
- **When** checkout runs
- **Then** the backend independently verifies the variant against the product

### VAR-005 — Variant price/stock
- **Given** a variant with its own price and stock
- **When** it is ordered
- **Then** its own price and stock rule the transaction

## 5. Cart

### CART-001 — Valid items only
- **Given** a cart
- **When** an item is added that no longer exists, is inactive, or is not purchasable
- **Then** the item is not permitted in the cart

### CART-002 — Guest cart binding
- **Given** a guest adds items to a guest cart
- **When** the guest logs in
- **Then** the guest cart is associated with the customer's account (items remain)

### CART-003 — Cart ≠ Order
- **Given** a cart with items
- **When** no checkout completes
- **Then** no order exists and no stock is reserved

### CART-004 — Revalidation
- **Given** a cart created earlier
- **When** checkout runs after product/variant/price/stock changes
- **Then** the backend revalidates all listed facts and fails the checkout on any invalid one (covers CHECKOUT-002)

### CART-005 — Client cannot set total
- **Given** a client submits a cart request containing `{"total": 1000}`
- **When** checkout attempts to use that total
- **Then** the backend ignores/rejects the client total and computes its own (backend total is authoritative — CART-005, PRICE-001, CHECKOUT-003)

## 6. Pricing

### PRICE-001/002 — Pricing authority
- **Given** a cart of products
- **When** totals are computed
- **Then** subtotal, delivery fee, and total come only from backend pricing

### PRICE-003 — Currency
- **Given** any price, fee, or total
- **Then** it is expressed in TZS

### PRICE-004 — Safe money arithmetic
- **Given** financial calculations (e.g., 0.1-like fractional cases)
- **Then** the implementation uses representative/discrete money values, never unreliable float math producing rounding loss

### PRICE-005 — Pickup fee zero
- **Given** a pickup order
- **Then** delivery fee = 0 and total = subtotal

### PRICE-006 — Delivery fee by region/location
- **Given** staff configured delivery fee rules by location (e.g., Kinondoni free, Ubungo TZS 5,000)
- **When** a customer chooses Delivery and provides a location/address at checkout
- **Then** the applicable fee is resolved from the staff rules, displayed before payment, and cannot be altered by the customer; the total includes it

## 7. Checkout & Authentication

### IDENT-002 / CHECKOUT-001 — Anonymous checkout rejected
- **Given** an unauthenticated visitor with a cart
- **When** the visitor attempts checkout
- **Then** the operation is rejected (Checkout not allowed, ERR-007)

### IDENT-001/003 — Anonymous browsing
- **Given** a guest on the website/app
- **When** browsing catalog, categories, search, product pages, images, prices, availability
- **Then** all succeed without an account or token

### CHECKOUT-004 — Fulfillment validation
- **Given** a customer selects Delivery
- **When** the delivery information is missing/invalid
- **Then** checkout is rejected (Invalid delivery information, ERR-009)

### CHECKOUT-005 — Invalid cart
- **Given** a cart with a stale/insufficient item
- **When** checkout runs
- **Then** no order is created

### CHECKOUT-003 — Backend computes authoritative total
- **Given** a cart with items and a customer-submitted total
- **When** checkout computes the order total
- **Then** the backend-computed total is authoritative and the client-submitted total is ignored (alias covers PRICE-001/002, CART-005)

### CHECKOUT-006 / DATA-004 — Atomicity
- **Given** order creation touches order and inventory reservation; payment confirmation follows afterwards and `PENDING_PAYMENT` is a valid state
- **When** order creation or inventory reservation fails
- **Then** no order is persisted and no stock is reserved (atomic rollback)
- **When** payment confirmation fails or remains pending after order and inventory reservation succeed
- **Then** the order remains in `PENDING_PAYMENT` with its inventory reservation intact (released only via later expiry or cancellation); the state is not partially corrupted and no unconditional rollback occurs

### IDENT-007 — One shared User identity
- **Given** customer, staff, and admin personas
- **When** any identity operation is performed
- **Then** all personas share a single User identity model (not separate, incompatible systems)

### IDENT-008 / CART-002 — Guest cart identity (alias)
- **Given** a guest adds items to a guest cart
- **When** the guest logs in
- **Then** the guest cart identity is preserved and associated with the customer's account (see CART-002)

## 8. Fulfillment

### FUL-001 — Two modes
- **Given** checkout
- **Then** exactly `PICKUP` and `DELIVERY` are offered

### FUL-002 — Pickup free + path
- **Given** a pickup order
- **Then** delivery fee = 0 and the lifecycle includes `READY_FOR_PICKUP`

### FUL-003 — Delivery info + fee
- **Given** a delivery order
- **Then** valid delivery information is required and a staff-controlled fee applies

### FUL-004 — No automatic distance pricing
- **Given** delivery pricing in Version 1
- **Then** no GPS/zoning/route algorithm exists; fees come from staff-managed region/location rules

### FUL-005 — Pickup never shipped
- **Given** a pickup order reaches later lifecycle stages
- **Then** it never shows `SHIPPED` (Invalid order state, ERR-010)

### FUL-006 — Milestones by method
- **Given** a pickup order and a delivery order
- **Then** tracking shows Ready for Pickup for the former and Shipped/Delivered for the latter, never the wrong set

## 9. Orders

### ORD-001 — Only via purchase flow
- **Given** no checkout completed
- **When** any operation tries to create an order
- **Then** no order is created except through the approved purchase flow

### ORD-002 — Authenticated customer
- **Given** an anonymous visitor
- **When** they try to create an order
- **Then** it is rejected

### ORD-003 — Request no order
- **Given** a customer submits "Request This Furniture"
- **Then** no order, payment, or stock reservation is created

### ORD-004 — Enquiry no order
- **Given** a customer submits an enquiry
- **Then** no order is created

### ORD-005 — Historical preservation
- **Given** an order placed at time T
- **When** the catalog product name/price changes later
- **Then** the order still shows the name, reference, unit price, quantity, and subtotal from time T

### ORD-006 — OD- reference
- **Given** created orders
- **Then** each has a reference beginning with `OD-`, is globally unique across all orders (system-wide uniqueness scope), and no two orders share the same reference; on collision or concurrent creation the system retries generation and never persists a duplicate

### ORD-007 — Backend-validated transitions
- **Given** a client attempts an invalid status change
- **Then** the backend rejects it (Invalid order state, ERR-010); the order state is unchanged

### ORD-008 — History recorded
- **Given** an order transitions through several statuses
- **Then** each significant transition is recorded with previous status, new status, time, actor, and optional note

## 10. Cancellation

### CANCEL-001 — Within window
- **Given** an order created at 10:00
- **When** the customer cancels at 10:15
- **Then** cancellation is eligible (allowed)

### CANCEL-001b — After window (rejected)
- **Given** an order created at 10:00
- **When** the customer cancels at 10:21
- **Then** customer self-service cancellation is rejected (Order not cancellable, ERR-011) — negative case for CANCEL-001

### CANCEL-002 — Time manipulation
- **Given** a client claims a fabricated timestamp inside the window
- **When** the real server clock says otherwise
- **Then** the window check uses the server clock and the cancellation is rejected

### CANCEL-003 — No automatic refund
- **Given** an order is cancelled
- **Then** no refund is assumed; refund is a separate later operation

### CANCEL-004 — Staff/admin separate
- **Given** staff/admin request a cancellation
- **Then** it is not governed by the customer's 20-minute rule, but by later-defined authorization

### CANCEL-005 — Valid state only
- **Given** an order in a state where cancellation is not allowed
- **When** a cancellation is attempted
- **Then** it is rejected

## 11. Payments

### PAY-001 — Distinct states
- **Given** an order with Payment = PAID and Order = ACCEPTED
- **Then** the two facts are stored/read as separate state concepts

### PAY-002 — Client cannot confirm payment
- **Given** a client submits `payment_status = success`
- **When** the backend has not verified the payment with the provider
- **Then** the order is not treated as paid (Payment not confirmed, ERR-012)

### PAY-003 — Provider-agnostic boundary
- **Given** the payment boundary as designed in this phase
- **Then** no provider, SDK, webhook payload, credentials, or URL is hard-coded

### PAY-004 — Backend-driven payment state
- **Given** a provider callback arrives
- **Then** payment state changes only after backend verification

## 12. Requests & Enquiries

### REQ-001 / IDENT-004 — Anonymous request
- **Given** a guest submits a made-to-order request with contact info
- **Then** the request is accepted

### REQ-001 — Authenticated request
- **Given** an authenticated customer submits a made-to-order request
- **Then** the request is accepted

### REQ-002 — Optional product reference
- **Given** a request with and without a product reference
- **Then** both forms are valid

### REQ-004/005 — Request ≠ order, no reservation
- **Given** a made-to-order request is submitted in Version 1
- **Then** no order is created, no payment occurs, no stock is reserved, and the request remains a lead

### REQ-006 — No quotation at request time
- **Given** a request is submitted
- **Then** no authoritative quotation price is assumed/stored

### ENQ-001 / IDENT-005 — Anonymous enquiry
- **Given** a guest submits an enquiry with contact info and message
- **Then** the enquiry is accepted

### ENQ-002 — Contact info + message required
- **Given** an enquiry missing contact info or message
- **Then** it is rejected (Enquiry invalid, ERR-014)

### ENQ-003 — Enquiry ≠ order
- **Given** an enquiry is submitted
- **Then** no order or payment is created

### ENQ-004 — Enquiry ≠ request
- **Given** an enquiry and a made-to-order request
- **Then** they are stored/handled as separate concepts

## 13. Addresses & Attachments

### ADDR-001/002 — No saved address book
- **Given** a customer account
- **Then** the system does not require or maintain a saved address book

### ADDR-003 — Billing vs delivery distinct
- **Given** a billing address and a delivery address with identical content
- **Then** they are still treated as two distinct facts

### ADDR-004 — Delivery info required
- **Given** a delivery order without valid delivery address/contact
- **Then** checkout is rejected (Invalid delivery information, ERR-009)

### ATTACH-001 — Optional attachments
- **Given** requests/enquiries
- **Then** attachments are optional and belong only to requests/enquiries

### ATTACH-002 — Storage provider not selected (deferred)
- **Given** any attachment handling in Version 1
- **Then** no file-storage provider is hard-coded or assumed (S3/Cloudinary/local/CDN choice remains deferred)

### ATTACH-003 — File safety (future)
- **Given** an uploaded file (when attachments are implemented)
- **Then** allowed types, size limits, and security checks are enforced

### NOTIF-001 — Notification is event record
- **Given** an order-state change occurs
- **When** a notification is generated
- **Then** it references the application event and does not drive the order state machine

### NOTIF-002 — External delivery deferred
- **Given** a notification is created in Version 1
- **Then** no external email/push/SMS delivery occurs (deferred to Phase Group R)

### NOTIF-003 — No email infrastructure
- **Given** the system in Version 1
- **Then** no SMTP, email provider, or email template infrastructure is assumed or required

## 14. Authorization & Security

### IDENT-006 / AUTHZ-003 — Data isolation
- **Given** two customers A and B
- **When** A tries to read B's orders/profile/requests/payments
- **Then** the access is denied

### AUTHZ-001 — Roles
- **Given** any user
- **Then** their role is one of CUSTOMER, STAFF, ADMIN

### AUTHZ-002 — Protected operations
- **Given** a privileged operation (manage products, review orders, manage inventory, manage requests/enquiries, view payments)
- **Then** it is restricted by explicit authorization (later Group D); unprivileged actors are denied

### AUTHZ-004 / SEC-001 — Backend-only mutations
- **Given** any attempt to set totals, change order status, confirm payment, or alter stock from a frontend
- **Then** it is rejected unless performed through backend-authorized logic

### SEC-002 — File upload validation (alias)
- **Given** an uploaded file is submitted (see ATTACH-003)
- **When** the backend processes it
- **Then** type, size, and security validation are enforced (alias covers ATTACH-003)

### SEC-003 — Admin authorization
- **Given** an administrative action
- **Then** only authorized staff/admin can perform it

### SEC-004 — No secrets exposed
- **Given** the codebase and client builds
- **Then** no backend secrets (payment/db credentials) are present in clients, public config, or the repository

## 15. Concurrency & Idempotency

### CONC-001 — Last-unit race
- **Given** 1 unit available
- **When** two customers try to buy it simultaneously
- **Then** exactly one purchase succeeds; available stock does not go below 0

### CONC-002 — Order creation race
- **Given** a single checkout intent re-triggered concurrently
- **Then** only one order results

### CONC-004 — Order status changes concurrency-safe
- **Given** two concurrent attempts to transition the same order's status
- **When** both are processed
- **Then** only one valid, serialized transition applies and the other is rejected

### CONC-003 / IDEMP-003 — Duplicate payment callback
- **Given** a payment provider sends the same success callback twice
- **Then** no duplicate order, payment record, or incompatible status transition occurs

### IDEMP-001/002 — Retried checkout
- **Given** checkout finalization retried for the same intent
- **Then** only one order is created

### IDEMP-004 — Repeated reservation
- **Given** an inventory reservation retried for the same intent
- **Then** stock is not double-reserved

### IDEMP-005 — Repeated critical transition
- **Given** a critical status-changing operation repeated
- **Then** duplicate execution does not produce duplicate effects

---

## 16. Mapping to Test Bands (Phase Group S)

| Scenario group | Intended test level (later) |
|---|---|
| Catalog, Pricing, Cancellation, Order history | Unit tests |
| Cart, Checkout, Orders, Requests, Enquiries, Authorization | API/feature tests |
| Anonymous/authenticated journeys, tracking timeline | End-to-end tests |
| Admin product/inventory/order operations | API + frontend tests |
| Concurrency, idempotency, payment callbacks | API/integration tests |