# Phase 1.3 — Define Domain Invariants and Business Rules

## 1. Purpose

Phase 1.3 converts the approved domain concepts into explicit, testable business invariants and rules.

An **invariant** is a condition that must remain true regardless of which frontend, admin screen, API consumer, or future application performs an operation.

A **business rule** describes what the system must allow, prevent, calculate, or transition according to the approved business model.

This phase does **not** implement these rules. It defines them precisely so that later database, Laravel, API, Next.js, admin, and Flutter work can implement the same rules consistently.

---

# 2. Authoritative Inputs

Read and treat the following as authoritative:

* `VISION.md`
* `AGENTS.md`
* completed `phase-1.1-api-scope.md`
* completed `phase-1.2-domain-nouns.md`

Do not reopen questions that have already been answered.

Do not replace an approved business decision with a convenient technical assumption.

If a genuine contradiction exists between authoritative documents:

1. identify the contradiction;
2. record it explicitly;
3. do not silently resolve it;
4. stop before continuing.

---

# 3. Scope of This Phase

This phase defines rules for:

```text
Identity
Catalog
Inventory
Product variants
Cart
Checkout
Pricing
Fulfillment
Orders
Order status
Cancellation
Payments
Made-to-order requests
General enquiries
Addresses
Attachments
Notifications
Roles
Data integrity
Concurrency/idempotency
Error semantics
```

The output must be a **business/domain specification**, not implementation code.

---

# 4. Core Principle — Backend Authority

Business rules must ultimately be enforceable by the backend.

Never assume a rule is satisfied merely because it is enforced in:

* Next.js
* Flutter
* browser JavaScript
* admin UI
* client-side validation
* cached state

The frontend exists partly to improve user experience.

The backend must remain authoritative for:

* authentication requirements
* authorization
* product purchasability
* inventory
* prices
* delivery fee
* checkout
* order totals
* cancellation
* order transitions
* payment state
* request/enquiry ownership

Example:

```text
Flutter says:
"Stock = 2"

Laravel says:
"Stock = 0"

Result:
Purchase must fail.
```

The backend is authoritative.

---

# 5. Product Invariants

## 5.1 Product Identity

A Product represents a furniture item in the catalog.

Each catalog product must have an unambiguous business identity.

The later data-model phase will decide how uniqueness is physically enforced.

Do not define database keys here.

---

## 5.2 Product Type

Version 1 supports exactly:

```text
IN_STOCK
MADE_TO_ORDER
```

### `IN_STOCK`

A furniture product that can be purchased through normal checkout when it is active, purchasable, and sufficiently stocked.

### `MADE_TO_ORDER`

A furniture product that can be requested for manufacture but is not directly purchasable through normal checkout.

---

## 5.3 Product Type Controls Customer Action

The customer's primary action must correspond to product type.

```text
IN_STOCK
    ↓
Add to Cart / Buy
    ↓
Checkout

MADE_TO_ORDER
    ↓
Request This Furniture
```

The system must not allow an ordinary checkout for a `MADE_TO_ORDER` product.

---

# 6. Inventory Invariants

## 6.1 Inventory Is Backend Authority

Inventory displayed in the website or Flutter app is informational.

The backend must perform the final inventory check.

---

## 6.2 No Negative Available Stock

The system must never allow available inventory to become negative.

---

## 6.3 No Overselling

Two or more customers attempting to purchase the same remaining inventory concurrently must not cause the system to sell more units than exist.

Example:

```text
Available = 3

Customer A requests 2
Customer B requests 2
```

The system must not successfully allocate:

```text
A = 2
B = 2
```

because only 3 exist.

The implementation must eventually use appropriate transactional/concurrency controls.

---

## 6.4 Cart Is Not a Permanent Reservation

Adding an item to a cart does not automatically guarantee stock.

Unless a later approved business rule says otherwise:

```text
Cart
≠
Inventory reservation
```

The backend must re-check stock when the customer proceeds through checkout.

---

## 6.5 Stale Inventory

A product may become unavailable after being displayed.

Therefore:

```text
Displayed stock
≠
guaranteed purchasability
```

The final decision happens at the server.

---

# 7. Product Variant Rules

Where variants exist:

* a variant belongs to its product;
* a variant must be valid for that product;
* an inactive variant cannot be purchased;
* the selected variant must be checked by the backend;
* inventory may be maintained at variant level where appropriate.

The system must reject an attempt such as:

```text
Product A
+
Variant belonging to Product B
```

Do not define the physical database structure yet.

---

# 8. User and Identity Rules

## 8.1 Public Browsing

Authentication is not required for:

```text
Homepage
Categories
Product listing
Product search
Product detail
Product images
Product prices
Product availability
```

This public access is intentional and must not become an accidental login dependency.

---

## 8.2 Checkout Requires Authentication

Version 1 checkout requires an authenticated customer account.

This must be enforced on the backend.

A visitor attempting to complete checkout anonymously must be rejected.

---

## 8.3 Anonymous Made-to-Order Requests

A made-to-order request can be submitted without an account.

The anonymous requester must provide the necessary contact details, including the approved email/phone information.

---

## 8.4 Anonymous General Enquiries

A general enquiry can be submitted without an account.

The enquiry must contain sufficient contact information for follow-up.

---

## 8.5 Authenticated Customer Privacy

An authenticated customer may access their own private account-related data.

A customer must not be able to access another customer's:

* profile
* orders
* private requests
* private enquiries
* private addresses
* private payment-related information

---

# 9. Cart Invariants

## 9.1 Cart Item Validity

A cart item must reference a currently valid and purchasable product/variant.

---

## 9.2 Revalidate Before Checkout

At checkout, the backend must revalidate at minimum:

```text
Product exists
Product is active
Product type is purchasable
Variant is valid
Current price
Current inventory
Quantity
Fulfillment selection
Required address/contact information
```

---

## 9.3 Frontend Cannot Set Final Total

The frontend may display totals for UX.

The backend calculates the authoritative order total.

A malicious client must not be able to submit:

```json
{
  "total": 1000
}
```

and have the system accept that value simply because it was supplied by the client.

---

# 10. Pricing Invariants

The backend is authoritative for:

```text
Product price
Variant price
Subtotal
Delivery fee
Discounts when implemented
Final total
```

The client may request a product and quantity.

It may not dictate the final payable amount.

---

# 11. Fulfillment Rules

Version 1 supports:

```text
PICKUP
DELIVERY
```

---

## 11.1 Pickup

Pickup is free.

Therefore:

```text
Pickup delivery fee = 0
```

The final order should proceed toward:

```text
READY_FOR_PICKUP
```

---

## 11.2 Delivery

Delivery requires valid delivery information.

The delivery fee is variable.

The fee is added/controlled operationally by authorized staff/admin.

A customer cannot arbitrarily define the delivery fee.

Example:

```text
Subtotal = TZS 1,200,000
Delivery fee = TZS 35,000

Total = TZS 1,235,000
```

The specific fee value must come from the backend's approved operational value.

---

## 11.3 No Automatic Distance Pricing in Version 1

Do not assume:

* GPS distance pricing
* zones
* route calculation
* driver optimization

unless separately approved later.

---

# 12. Order Invariants

## 12.1 What an Order Represents

An Order is a commerce record representing a purchase through the checkout process.

It is not:

* a product
* a cart
* a made-to-order request
* a general enquiry
* a payment

---

## 12.2 Order Creation Requires Authentication

Only an authenticated customer may complete the Version 1 purchase flow and create an order.

---

## 12.3 Made-to-Order Request Does Not Create an Order

Submitting:

```text
Request This Furniture
```

must not automatically create:

```text
Order
Payment
Stock reservation
```

The business must first evaluate the request.

---

## 12.4 Enquiry Does Not Create an Order

A general enquiry is communication, not a commerce transaction.

---

# 13. Historical Order Invariants

An order must remain understandable even if the product changes later.

Suppose:

```text
Today's product name:
Modern 3-Seater Sofa

Today's price:
TZS 1,250,000
```

Six months later:

```text
Name:
Modern Luxury 3-Seater Sofa

Price:
TZS 1,450,000
```

The old order must still retain the historical purchase information.

The later implementation must preserve purchase-time facts such as:

```text
Product name at purchase
SKU/reference at purchase
Unit price
Quantity
Subtotal
```

Do not rely solely on the current product record to reconstruct historical orders.

---

# 14. Order Number Rule

Customer-facing references use:

```text
OD-*****
```

The exact suffix implementation is deferred.

The final generated reference must be sufficiently unique for customer and staff identification.

Do not decide the storage/generation algorithm in Phase 1.3.

---

# 15. Order Lifecycle Rules

The conceptual Version 1 lifecycle is:

### Pickup

```text
PENDING_PAYMENT
        ↓
PAID
        ↓
ACCEPTED
        ↓
PROCESSING
        ↓
READY_FOR_PICKUP
        ↓
COMPLETED
```

### Delivery

```text
PENDING_PAYMENT
        ↓
PAID
        ↓
ACCEPTED
        ↓
PROCESSING
        ↓
SHIPPED
        ↓
DELIVERED
        ↓
COMPLETED
```

These are business lifecycle rules.

The exact technical state-machine implementation comes later.

---

# 16. Invalid Order Transitions

The system must not allow arbitrary state jumps.

For example, a normal order should not simply go:

```text
PENDING_PAYMENT
        ↓
DELIVERED
```

without the appropriate preceding business events/states.

Likewise, a completed order should not casually move backward to:

```text
PROCESSING
```

unless a future approved rule explicitly allows it.

The later order-state phase will define the complete transition matrix.

---

# 17. Order Status History

Current status alone is insufficient.

Every significant order status change must eventually be traceable.

Conceptually record:

```text
Previous status
New status
Time
Actor/source
Optional note
```

Example:

```text
27 Aug 2026 14:20
PAID

27 Aug 2026 14:35
ACCEPTED
Staff: Order accepted

27 Aug 2026 17:10
PROCESSING

28 Aug 2026 09:00
SHIPPED
```

This history powers the customer tracking feature and operational auditability.

---

# 18. Customer Tracking Rules

The customer should be able to see the order lifecycle.

Version 1 is **status tracking**, not GPS/live courier tracking.

The system should communicate meaningful milestones such as:

```text
Paid
Accepted
Processing
Ready for Pickup
Shipped
Delivered
Completed
```

Only the applicable milestones for the selected fulfillment method should be shown.

For example:

```text
Pickup
→ Ready for Pickup
```

instead of pretending the order was shipped.

---

# 19. Cancellation Rules

The approved Version 1 customer rule is:

**20-minute cancellation window.**

The backend must determine whether the request is still inside the allowed cancellation period.

A customer must not be able to bypass this with a client-supplied timestamp.

Example:

```text
Order cancellation started at:
10:00

Valid cancellation window:
10:00–10:20
```

At 10:21, the client cannot force a cancellation simply by claiming it is still within the window.

---

# 20. Cancellation and Refund Separation

Cancellation and refund are separate business concepts.

Do not assume:

```text
Cancelled
=
Automatically refunded
```

Refund rules will be defined later alongside payment behavior.

---

# 21. Staff/Admin Cancellation

Staff/admin cancellation is different from customer self-service cancellation.

Do not assume that staff/admin have the same 20-minute restriction.

Their permissions and conditions will be defined in Phase Group D and later order operations phases.

---

# 22. Payment Invariants

Payment state must be distinct from order state.

For example:

```text
Payment:
PAID

Order:
ACCEPTED
```

are different facts.

Do not collapse them into one status.

---

## 22.1 Client Payment Success Is Not Final Authority

The frontend must not be able to declare an order paid simply by sending:

```text
payment_status = success
```

The backend must verify the payment using the approved payment-provider integration.

---

## 22.2 Provider Selection Deferred

Payment provider selection and provider-specific behavior are deferred to:

**Phase Group H**

Therefore Phase 1.3 must not decide:

* provider
* SDK
* webhook payload
* credentials
* payment URL
* provider-specific statuses

---

# 23. Made-to-Order Request Rules

A request:

* may be anonymous;
* may be associated with an authenticated customer;
* may optionally reference an existing product;
* may specify requirements;
* may contain optional attachments;
* is not an order;
* is not a payment;
* does not reserve inventory.

The business may later:

```text
Receive request
    ↓
Contact customer
    ↓
Discuss requirements
    ↓
Provide quotation
    ↓
Potentially convert to an order through a later approved workflow
```

Do not assume the final quotation price at request time.

---

# 24. General Enquiry Rules

An enquiry:

* may be anonymous;
* may be associated with an authenticated customer;
* must contain adequate contact information;
* contains a customer message;
* may contain an optional attachment;
* is not an order;
* is not payment;
* does not reserve inventory.

The enquiry can cover things outside a specific catalog purchase.

---

# 25. Attachment Rules

Attachments are optional for:

```text
Made-to-order request
General enquiry
```

The storage provider is not selected yet.

Later implementation must eventually enforce:

* allowed file types
* file size limits
* secure upload handling
* malware/security considerations
* storage access rules

Do not choose a storage provider in this phase.

---

# 26. Address Rules

Version 1 does not include a persistent customer address book.

Therefore:

```text
Customer account
≠
mandatory saved address list
```

Addresses are captured for the relevant checkout/order use case.

Billing and delivery addresses should remain conceptually distinct even when they happen to contain the same information.

---

# 27. Role Rules

The currently approved high-level roles are:

```text
CUSTOMER
STAFF
ADMIN
```

Detailed permissions are deferred to:

**Phase Group D**

Do not create an ad-hoc permission matrix here.

The only rule at this stage is:

> Every privileged business operation must eventually be restricted by explicit authorization.

---

# 28. Notification Rules

Real email delivery is deferred to:

**Phase Group R**

Therefore Version 1 does not make external email delivery a core dependency.

Do not yet implement:

* SMTP
* email provider
* email template infrastructure
* external notification provider

The future system may still have notification concepts, but delivery infrastructure is intentionally deferred.

---

# 29. Data Integrity Rules

## 29.1 Referential Integrity

A business record must not silently reference an entity that no longer exists where that relationship is mandatory.

---

## 29.2 Historical Integrity

Historical order records must remain explainable even when current catalog information changes.

---

## 29.3 Server-Side Calculation

Financial and inventory calculations must be performed using trusted backend data.

---

## 29.4 Atomicity

Operations involving multiple related changes must eventually be designed transactionally where failure between steps could corrupt the business state.

Important examples:

```text
Create order
Reserve stock
Record payment state
Change order status
```

The exact transaction boundaries are implementation work for later phases.

---

# 30. Concurrency Rules

Identify operations vulnerable to concurrent requests.

At minimum:

```text
Inventory reservation
Order creation
Payment callbacks
Order status changes
```

Example:

Two requests arrive almost simultaneously:

```text
Request A → buy last unit
Request B → buy last unit
```

The domain guarantees that only one successful operation may consume that final unit.

The implementation later decides how to enforce it.

---

# 31. Idempotency Requirements

Some operations may be safely retried only if duplicate execution does not create duplicate business effects.

Flag these as requiring idempotency design later:

```text
Checkout finalization
Order creation
Payment webhook/callback handling
Inventory reservation
Critical status-changing operations
```

Example:

A payment provider sends the same callback twice.

The system must not:

```text
Create two orders
Record two payments
Send two incompatible status transitions
```

merely because the same event was delivered twice.

Do not implement the mechanism yet.

---

# 32. Error Semantics

Define domain-level error meanings.

At minimum:

```text
AUTHENTICATION_REQUIRED
UNAUTHORIZED_OPERATION

PRODUCT_NOT_FOUND
PRODUCT_NOT_PURCHASABLE
INVALID_PRODUCT_VARIANT
INSUFFICIENT_STOCK

CHECKOUT_NOT_ALLOWED
INVALID_FULFILLMENT
INVALID_DELIVERY_INFORMATION

INVALID_ORDER_STATE
ORDER_NOT_CANCELLABLE

PAYMENT_NOT_CONFIRMED

REQUEST_INVALID
ENQUIRY_INVALID
```

These are conceptual business errors.

Do not decide their HTTP status codes or JSON structure yet.

---

# 33. Security Rules

Security-sensitive business rules include:

```text
Customer identity must be authenticated where required.
Private customer records require authorization.
Stock decisions happen server-side.
Price decisions happen server-side.
Delivery fee decisions happen server-side.
Cancellation eligibility happens server-side.
Payment verification happens server-side.
File uploads require validation.
Administrative actions require authorization.
```

Do not put secrets in:

* browser code
* Flutter application code
* public configuration
* Git repository

Backend secrets remain backend-only.

---

# 34. Rules That Must Never Be Implemented Solely in the Frontend

Create an explicit list:

```text
Checkout authentication
Product purchasability
Stock availability
Stock reservation
Product price
Delivery fee
Final total
Cancellation window
Order status transition
Payment confirmation
Authorization
Historical order preservation
```

A frontend implementation may duplicate a rule for UX, but it must not become the only enforcement point.

---

# 35. Testable Invariants

Create test scenarios for later implementation.

## Catalog

```text
IN_STOCK product can be purchased when valid and stocked.
MADE_TO_ORDER product cannot be purchased through normal checkout.
Inactive product cannot be newly purchased.
Invalid variant cannot be selected for another product.
```

## Inventory

```text
Available inventory cannot become negative.
Concurrent checkout cannot oversell stock.
Stale frontend inventory cannot override backend inventory.
```

## Authentication

```text
Anonymous user can browse.
Anonymous user can search.
Anonymous user can view product.
Anonymous user can submit a request.
Anonymous user can submit an enquiry.
Anonymous user cannot complete checkout.
Authenticated customer can complete checkout.
```

## Pricing

```text
Client cannot alter product price.
Client cannot arbitrarily choose delivery fee.
Backend calculates final total.
Pickup has zero delivery fee.
Delivery applies the approved operational fee.
```

## Orders

```text
Valid checkout creates an order.
Invalid cart cannot create an order.
Order preserves historical purchase information.
Invalid order transitions are rejected.
Status history is retained.
```

## Cancellation

```text
Cancellation inside 20 minutes can be eligible.
Cancellation after 20 minutes cannot be performed through customer self-service.
Client cannot manipulate cancellation timing.
```

## Fulfillment

```text
Pickup follows pickup lifecycle.
Delivery follows delivery lifecycle.
Pickup does not become SHIPPED.
Delivery does not skip required operational stages.
```

## Requests

```text
Anonymous made-to-order request works.
Authenticated made-to-order request works.
Request does not create order.
Request does not reserve stock.
```

## Enquiries

```text
Anonymous enquiry works.
Authenticated enquiry works.
Enquiry does not create order.
```

---

# 36. Domain Rule IDs

Assign stable IDs to rules.

Recommended format:

```text
IDENT-001
CAT-001
INV-001
VAR-001
CART-001
PRICE-001
CHECKOUT-001
FUL-001
ORD-001
PAY-001
CANCEL-001
REQ-001
ENQ-001
SEC-001
DATA-001
CONC-001
IDEMP-001
```

Example:

```text
INV-002

Name:
No Negative Available Stock

Rule:
The system must never permit available inventory to fall below zero.

Reason:
Prevents impossible inventory state and overselling.

Implementation:
Later backend/data layer.

Testing:
Concurrent purchase test must prove this invariant.
```

Use stable rule IDs because future API, database, Laravel, and automated-test documents can reference them.

---

# 37. Required Deliverables

At the end of Phase 1.3, produce these documents:

## A. `domain-invariants.md`

Contains every invariant grouped by domain.

Sections:

```text
Identity
Catalog
Inventory
Variants
Cart
Pricing
Checkout
Fulfillment
Orders
Cancellation
Payments
Requests
Enquiries
Addresses
Attachments
Notifications
Authorization
Security
Data integrity
Concurrency
Idempotency
```

---

## B. `business-rules.md`

A readable business-oriented list of the rules that product owners/staff could understand.

Avoid implementation terminology.

---

## C. `order-state-rules.md`

Contains:

```text
Pickup lifecycle
Delivery lifecycle
Allowed conceptual transitions
Invalid transition principles
Cancellation rule
Tracking milestones
Status history requirements
```

---

## D. `testable-invariants.md`

Convert each important invariant into at least one test scenario.

This document becomes an input to later test design.

---

## E. `domain-decisions.md`

Record any additional decisions made during this phase.

Each decision should contain:

```text
Decision ID
Decision
Reason
Affected domain
Future implementation phase
```

---

## F. `domain-conflicts.md`

If there are no conflicts:

```text
No unresolved domain conflicts found.
```

If there are conflicts, list them and STOP.

---

# 38. Validation Against Phase 1.1

Before completing this phase, verify that the rules still agree with every approved Phase 1.1 decision:

```text
Anonymous browsing
Authenticated checkout
Anonymous made-to-order requests
Anonymous enquiries
Variable delivery fees
Payment deferred to Group H
20-minute customer cancellation window
OD- order reference
Email deferred to Group R
Saved address book deferred
Roles/permissions deferred to Group D
Optional attachments
```

Any contradiction must be reported.

---

# 39. Validation Against Phase 1.2

Verify that the invariants respect the agreed domain boundaries:

```text
Product ≠ Inventory
Product ≠ Order Item
Product ≠ Made-to-order Request
Made-to-order Request ≠ Order
Enquiry ≠ Made-to-order Request
Cart ≠ Order
Payment ≠ Order Status
Delivery ≠ Order
```

Do not collapse separate concepts merely to simplify implementation.

---

# 40. Definition of Done

Phase 1.3 is complete when:

* all major Version 1 domain concepts have explicit rules;
* rules are written in business language;
* invariants have stable IDs;
* backend authority is clearly identified;
* anonymous/public behavior is explicit;
* checkout authentication is explicit;
* product purchasing rules are explicit;
* inventory safety rules are explicit;
* pricing authority is explicit;
* fulfillment rules are explicit;
* cancellation is explicit;
* order lifecycle principles are explicit;
* payment and order state are separate;
* historical order facts are protected;
* made-to-order requests remain distinct from orders;
* enquiries remain distinct from requests;
* concurrency-sensitive operations are identified;
* idempotency-sensitive operations are identified;
* rules can later be converted into automated tests;
* no API implementation has been performed;
* no physical database schema has been created;
* no Laravel implementation has been created;
* no Next.js implementation has been created;
* no Flutter implementation has been created.

---

# 41. Explicitly Out of Scope

Do NOT perform any of the following in Phase 1.3:

```text
MySQL table design
ERD physical schema
Database migrations
Laravel models
Laravel controllers
Laravel services
API endpoints
OpenAPI specification
Authentication implementation
Sanctum setup
Next.js project creation
MUI setup
Flutter project creation
Payment provider selection
Payment SDK integration
File-storage provider selection
Email provider selection
CI/CD
Deployment
```

Those belong to later phases.

---

# 42. STOP CONDITION — Mandatory

After completing all Phase 1.3 deliverables:

**STOP.**

Do not automatically begin database design or implementation.

Do not silently enter the next phase because a dependency appears obvious.

The next phase must be explicitly requested.

Recommended next phase:

**Phase 1.4 — Define Data Model Requirements Before Physical Database Design**

That phase should translate the stabilized domain into **data requirements and relationship rules**, while still stopping before actual MySQL migrations and Laravel models.
