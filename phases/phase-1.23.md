# Phase 1.23 — Define Order API Contract

## 1. Purpose

Phase 1.23 defines the **complete Version 1 Order API contract**.

The Order is the central persistent business record produced by Checkout. It represents the customer's purchase and becomes the authoritative historical record of:

* what was purchased;
* by whom;
* at what price;
* in what quantity;
* for which fulfillment method;
* with what delivery fee;
* for what final amount;
* at what point in the order lifecycle;
* and what operational progress has occurred.

The core relationship is:

```text id="w81f4c"
Customer
   ↓
Checkout
   ↓
Order
   ├── Items
   ├── Fulfillment
   ├── Payment relationship
   ├── Tracking
   └── Status history
```

The central principle is:

> **Once an Order exists, it becomes a historical business record. Current Catalog data must not silently rewrite its historical meaning.**

---

# 2. Documentation Strategy

Continue using the consolidated documentation approach.

Update:

```text id="b1yn0m"
docs/api/api-contract.md
docs/api/api-resources.md
docs/api/api-conventions.md
docs/domain/business-rules.md
docs/decisions.md
```

Do not create:

```text id="8gt7sm"
order-api.md
order-contract.md
order-decisions.md
order-status.md
```

unless the project later becomes large enough to justify splitting the Order domain.

---

# 3. Authoritative Project Paths

Use:

```text id="qbn8g6"
AGENTS.md
docs/VISION.md
```

Do not use obsolete filenames.

---

# 4. Payment Assignment

Payment-specific implementation remains assigned to:

**Phase Group H**

not Group G.

This phase must define the Order's **relationship to Payment**, but must not implement:

* payment provider integration;
* provider-specific payloads;
* provider credentials;
* provider webhooks;
* provider-specific reconciliation.

---

# 5. Version 1 Enum Policy

All Version 1 enums remain:

> **CLOSED by default.**

This is particularly important for Order status and fulfillment type.

Do not invent new statuses simply to make an implementation easier.

---

# 6. Dependency Position

Current sequence:

```text id="u6c34p"
1.20 Catalog API Contract
       ↓
1.21 Cart API Contract
       ↓
1.22 Checkout API Contract
       ↓
1.23 Order API Contract
       ↓
1.24 ...
```

Order must use the finalized:

```text id="fs4x35"
Catalog contract
Cart contract
Checkout contract
Authentication contract
Authorization contract
Validation contract
Error contract
Response conventions
```

---

# 7. Authoritative Inputs

Read:

```text id="m8t6k0"
AGENTS.md
docs/VISION.md

docs/api/api-contract.md
docs/api/api-resources.md
docs/api/api-conventions.md

docs/domain/business-rules.md
docs/decisions.md
```

Specifically review:

```text id="0g8n5k"
Phase 1.20 Catalog
Phase 1.21 Cart
Phase 1.22 Checkout
Phase 1.17 Authentication
Phase 1.18 Authorization
Phase 1.15 Validation
Phase 1.16 Error Contract
```

---

# 8. Core Order Principle

An Order is:

```text id="a2b7d9"
historical
customer-owned
operationally managed
financially significant
stateful
auditable
```

It is **not** simply a live copy of the Product catalog.

---

# 9. Order Identity

The Order must have a stable internal resource identity and a separate human-facing order reference where required.

The business-approved format is:

```text id="j5m04z"
OD-*****
```

The exact length/sequence mechanism should be finalized in implementation.

The customer-facing Order reference must be:

```text id="3x7h4k"
unique
human-readable
stable
non-editable
server-generated
```

---

# 10. Order Customer Ownership

Every normal customer Order belongs to exactly one authenticated Customer.

Conceptually:

```text id="s3l8u1"
Customer
   └── Orders
```

A customer can retrieve only their own Orders.

---

# 11. Customer Cannot Choose Order Owner

Never accept:

```json id="4rz0l0"
{
  "customer_id": "..."
}
```

from the customer to establish order ownership.

The authenticated identity determines ownership.

---

# 12. Order Creation Boundary

Normal customer Orders are created through Checkout.

Do not make the public customer API support arbitrary:

```text id="q0qmb7"
POST /orders
```

that lets the client construct an Order independently of Checkout.

This protects:

```text id="8fh9ty"
price
inventory
ownership
order status
totals
```

---

# 13. Order Resource

Define the primary Order resource:

```text id="4w0djh"
/api/v1/orders/{order}
```

for individual access.

The collection path should follow the self-context/ownership convention established in Phase 1.9 and Phase 1.19.

Potential:

```text id="h7q5c6"
/api/v1/me/orders
```

or another already-approved structure.

Do not create competing customer-order paths.

---

# 14. Order Endpoint Inventory

The Version 1 Order contract should evaluate at least:

```text id="9qk8t0"
ORD-001
Get own orders

ORD-002
Get own order

ORD-003
Cancel own eligible order

ORD-004
Get order tracking
```

Operational endpoints may include:

```text id="1i4n20"
ORD-005
Staff accept order

ORD-006
Staff process order

ORD-007
Staff mark ready for pickup

ORD-008
Staff ship order

ORD-009
Staff mark delivered

ORD-010
Staff/admin complete order
```

Only retain actions actually supported by the approved Order lifecycle.

---

# 15. Order Collection

Define the customer collection contract.

Likely:

```text id="7c1b7k"
GET /api/v1/me/orders
```

Authentication:

```text Required
```

Authorization:

```text Own Orders only
```

Pagination:

```text Required
```

Sorting/filtering:

```text Only approved query parameters
```

---

# 16. Customer Order Collection Queries

Potential filters:

```text id="z76j5x"
status
fulfillment_type
date range
```

Do not add arbitrary filtering.

Every filter must use the global query conventions.

---

# 17. Customer Order Detail

Define:

```text id="e1a85m"
GET /api/v1/me/orders/{order}
```

or the approved equivalent.

The server must verify:

```text id="9s4xl1"
authenticated customer
+
Order belongs to customer
```

---

# 18. Staff Order Collection

Staff need to find operational Orders.

The endpoint may use the same Order collection resource with different authorization or a clearly separated operational route if already approved.

Do not create duplicate Order resources without a strong reason.

---

# 19. Staff Order Detail

Staff need sufficient information to process an Order.

The representation may contain more operational information than the customer representation.

However:

```text id="h3z7m8"
password
authentication credentials
payment secrets
unrelated private account data
```

must never appear.

---

# 20. Admin Order Access

Admins may access Orders for administrative purposes.

Admin access does not mean:

```text id="u4p4x0"
historical data can be arbitrarily rewritten
```

The Order remains a historical business record.

---

# 21. Order Items

Order Items are owned by the Order.

Conceptually:

```text id="0f9ukn"
Order
   └── Items
```

They represent exactly what was purchased.

---

# 22. Order Item Historical Snapshot

Each Order Item must preserve the historical information necessary to understand the purchase.

At minimum consider:

```text id="qmw6f0"
product identity/reference
variant identity/reference where applicable
product name snapshot
variant display information snapshot
unit price at transaction time
quantity
line total
```

The exact field list must align with the approved logical model.

---

# 23. Why Historical Snapshots Matter

Suppose:

```text id="a7v1no"
Today:
Sofa = TZS 1,000,000
```

and later:

```text id="j6y6c4"
Catalog price changes to TZS 1,200,000
```

The old Order must still show:

```text id="2cn8p7"
1,000,000
```

not 1,200,000.

---

# 24. Product Deletion and Orders

A Product may later be deactivated or removed from the active Catalog.

Historical Orders must still remain understandable.

Therefore Order Items should not rely exclusively on the current Product representation.

---

# 25. Order Item Quantity

Quantity is historical transaction data.

Do not allow a customer to modify:

```text id="5s5x2a"
Order Item quantity
```

after Order creation.

If an operational correction is ever needed, that is a specific administrative workflow.

---

# 26. Order Item Price

Historical Order Item unit price is immutable through normal APIs.

Do not allow:

```text id="5d90fj"
PATCH /orders/{order}/items/{item}
```

to change transaction price.

---

# 27. Order Total

The Order must preserve its authoritative transaction total.

Do not recompute historical Order total from the current Product catalog.

---

# 28. Order Financial Components

The Order should conceptually distinguish:

```text id="1k7kny"
subtotal
delivery fee
total
currency
```

using the money representation from Phase 1.13.

---

# 29. Delivery Fee

The delivery fee is variable.

It is:

```text id="v4o1g4"
not customer-controlled
```

and may be assigned by:

```text id="m4ty8q"
Staff
or
Admin
```

according to the approved workflow.

---

# 30. Delivery Fee Historical Value

Once the fee becomes authoritative for the Order, that amount becomes part of the Order's historical financial record.

It must not later change merely because:

```text id="pn8b2n"
delivery policy changes
```

---

# 31. Delivery Fee Timing

Phase 1.22 required an explicit decision about when the variable delivery fee becomes authoritative.

The Order contract must reflect that decision.

Use the decision stored in:

```text id="k8t0e6"
docs/decisions.md
```

Do not contradict it.

If the fee is added after initial Order creation, the Order lifecycle must represent that explicitly before Payment is finalized.

---

# 32. Fulfillment Type

The Order stores the selected fulfillment type:

```text id="7d9y4h"
PICKUP
DELIVERY
```

This is a closed Version 1 enum.

---

# 33. Pickup Orders

For:

```text id="5x4d4g"
PICKUP
```

the Order must not require a delivery address.

The operational workflow should eventually move toward:

```text id="b7kr90"
READY_FOR_PICKUP
```

before customer collection.

---

# 34. Delivery Orders

For:

```text id="2jyf2r"
DELIVERY
```

the Order must preserve the delivery information required for fulfillment.

---

# 35. Delivery Address Snapshot

The Order should preserve the delivery address used for that transaction.

Do not make historical Orders depend only on:

```text id="xj4d9k"
current customer profile address
```

because the customer may change their address later.

---

# 36. Saved Address Book

Saved addresses remain deferred.

Do not make Order contract depend on a future address-book resource.

---

# 37. Order Customer Contact Snapshot

Where operationally necessary, preserve the contact information relevant to the transaction.

For example:

```text id="r8ox09"
recipient name
phone
delivery address
```

This allows Staff to fulfill the historical transaction even if the customer's current profile later changes.

---

# 38. Order Status

The Order has an authoritative lifecycle status.

The final Version 1 status values must come from the approved domain model.

Do not create additional statuses casually.

Known lifecycle concepts include:

```text id="p1f6z6"
PENDING_PAYMENT
PAID
ACCEPTED
PROCESSING
READY_FOR_PICKUP
SHIPPED
DELIVERED
COMPLETED
```

Use only statuses already approved in the project's domain model.

---

# 39. Cancellation

Do not assume `CANCELLED` exists as an ordinary status until verified against the frozen domain model.

If cancellation is represented through a status, it must be formally approved.

If cancellation is represented differently, preserve that model.

Do not invent a new enum merely to fit the endpoint.

---

# 40. Closed Status Enum

Because Version 1 statuses are closed:

```text id="g5mrp0"
clients may rely on the documented state set
```

A future new status must go through compatibility review.

---

# 41. Order State Is Server-Controlled

Customers and ordinary API clients must not directly set:

```text id="a7w2cv"
status
```

They request actions.

The server performs valid state transitions.

---

# 42. Customer Status Visibility

Customers may see the current status.

Example:

```json id="v6ud7a"
{
  "status": "PROCESSING"
}
```

Do not return internal workflow state that has no customer meaning.

---

# 43. Staff Status Operations

Staff should perform authorized state transitions through explicit actions.

Examples:

```text id="7gkzhl"
accept
process
ready-for-pickup
ship
deliver
complete
```

Only approved transitions may occur.

---

# 44. Admin Status Operations

Admin may perform authorized administrative Order operations.

But Admin should not be given generic:

```text id="8f1x5w"
PATCH status = anything
```

without lifecycle validation.

---

# 45. Order State Machine

Create the authoritative Version 1 state transition model.

Conceptually:

```text id="x2j8w1"
PENDING_PAYMENT
      ↓
PAID
      ↓
ACCEPTED
      ↓
PROCESSING
      ├──→ READY_FOR_PICKUP
      └──→ SHIPPED
                ↓
            DELIVERED
                ↓
            COMPLETED
```

The actual starting state and permitted transitions must be taken from the approved business model/Checkout decision.

Do not add transitions simply because they appear reasonable.

---

# 46. Pickup Branch

For Pickup:

```text id="0t5t2f"
PROCESSING
   ↓
READY_FOR_PICKUP
   ↓
COMPLETED
```

Do not require:

```text id="z1f3qp"
SHIPPED
DELIVERED
```

for pickup orders.

---

# 47. Delivery Branch

For Delivery:

```text id="a7t0ad"
PROCESSING
   ↓
SHIPPED
   ↓
DELIVERED
   ↓
COMPLETED
```

Use only the states approved by the domain model.

---

# 48. Invalid Transitions

Examples:

```text id="nbxk9f"
PAID → COMPLETED
```

may be invalid.

```text id="wn50i4"
COMPLETED → PROCESSING
```

should normally be invalid.

```text id="rw4h7d"
DELIVERED → SHIPPED
```

should normally be invalid.

The API must reject invalid transitions.

---

# 49. State Transition Authorization

For every transition evaluate:

```text id="f12p4v"
actor
+
permission
+
current state
+
fulfillment type
+
business preconditions
```

---

# 50. Customer Cancellation

Customer cancellation requires:

```text id="2x13zv"
authenticated customer
+
owns Order
+
within 20 minutes
+
Order is cancellable
```

The server determines the time.

---

# 51. Cancellation Window

The cancellation window is:

> **20 minutes from the authoritative Order creation/transaction point defined by the business model.**

Do not let the client submit a cancellation timestamp.

---

# 52. Cancellation Time Authority

Never trust:

```json id="g7ky32"
{
  "cancelled_at": "..."
}
```

from the customer.

The backend determines:

```text id="9f3n1c"
current_time
order_created_at
elapsed_time
```

---

# 53. Customer Cannot Cancel After Window

If:

```text id="i4m9bc"
elapsed time > 20 minutes
```

the customer cancellation action must fail according to the common error contract.

Likewise if the Order has entered a non-cancellable lifecycle state.

---

# 54. Staff Cancellation

Do not automatically give Staff the ability to cancel customer Orders.

If operational cancellation is required later, define it as a specific administrative/operational action with explicit authority.

Do not infer it from Staff's general Order access.

---

# 55. Admin Cancellation

Admin may have broader administrative capabilities, but any cancellation override must be explicitly defined.

Do not create:

```text id="j8v2hf"
ADMIN → DELETE order
```

as a generic solution.

---

# 56. Order History

Order status/history should be preserved as part of the historical record.

Conceptually:

```text id="kqf9cm"
Order
  └── Status History
```

---

# 57. Status History Is Read-Only to Customers

Customers can view relevant history.

They cannot create/edit/delete history events.

---

# 58. Status History Is Generated by Actions

When Staff performs:

```text id="if9th2"
ship
```

the system generates the appropriate historical event.

The client does not directly create:

```text id="g3z8l8"
status-history
```

records.

---

# 59. Status History Data

Potential history fields:

```text id="3v4ci9"
status
occurred_at
actor/context
note where appropriate
```

Only expose information relevant to the viewer.

---

# 60. Staff Status Notes

If operational notes are supported:

```text id="e6ox6r"
```

they may be visible to Staff/Admin but not necessarily to Customers.

Do not expose internal notes through the public customer representation.

---

# 61. Tracking

Tracking is the customer-friendly representation of Order progress.

It may use:

```text id="5f8or0"
GET /orders/{order}/tracking
```

or be embedded into Order.

Choose the approach consistent with Phase 1.19.

---

# 62. Tracking vs Status History

Distinguish:

```text id="9p8o8s"
Status History
→ audit-oriented internal/historical sequence

Tracking
→ customer-facing progress representation
```

They may derive from the same underlying state history without being identical representations.

---

# 63. Customer Tracking

Customers may view tracking for their own Orders.

No authentication bypass.

---

# 64. Staff Tracking

Staff may access operational Order progress as part of their order workflow.

---

# 65. Admin Tracking

Admin may access tracking/operational history as authorized.

---

# 66. Payment Relationship

Order should expose a limited Payment relationship.

Conceptually:

```text id="o1m5tm"
Order
  └── Payment
```

But Payment remains a separate domain/API contract.

---

# 67. Payment Data Visible to Customer

Customer may need:

```text id="9yvl32"
payment status
amount
currency
safe reference
```

but must not receive:

```text id="2m7kpo"
provider secret
private credentials
raw authorization data
```

---

# 68. Payment Data Visible to Staff

Staff may receive payment information necessary for operational Order processing, subject to the eventual payment contract.

Do not expose sensitive provider data unnecessarily.

---

# 69. Payment Data Visible to Admin

Admin may have broader visibility, but provider secrets remain internal.

---

# 70. Payment State vs Order State

Do not make:

```text id="w3p3s0"
Order.status = Payment.status
```

as a universal rule.

Payment and Order are related but distinct state machines.

Example:

```text id="7o6mgc"
Payment:
PAID

Order:
ACCEPTED
```

may both be true.

---

# 71. Payment Implementation Boundary

Payment-specific semantics belong to:

```text id="h8k2u9"
Phase Group H
```

The Order contract should expose only the stable relationship necessary for the client.

---

# 72. Delivery State vs Order State

Similarly, Delivery/fulfillment progress must not be confused with the entire Order state.

Example:

```text id="u7k7hi"
Order:
SHIPPED

Delivery:
in transit
```

The exact model belongs to later fulfillment/tracking work if required.

---

# 73. Order Collection Sorting

Customer Order history should have a deterministic default ordering.

Recommended:

```text id="f9j5y5"
newest first
```

using an explicit ordering field.

The final sort field must be documented.

---

# 74. Order Collection Pagination

Order history should use the global pagination convention.

Likely:

```text id="4t0k3e"
page
per_page
```

with:

```text current_page
last_page
total
```

according to Phase 1.12.

---

# 75. Order Collection Filters

Potential customer filters:

```text id="jx3u4n"
status
fulfillment_type
created_from
created_to
```

Use only if useful.

---

# 76. Staff Order Filters

Staff operational lists may need:

```text id="8fv31z"
status
fulfillment_type
date range
customer reference
order reference
```

Only expose filters necessary for normal operations.

---

# 77. Admin Order Filters

Admins may have broader operational filtering.

Do not create arbitrary database-query capabilities.

---

# 78. Customer Order Response

A customer-facing Order should contain enough data to understand:

```text id="c6j1p9"
order reference
status
items
prices
subtotal
delivery fee if applicable
total
fulfillment type
delivery information if applicable
tracking/progress
created time
payment summary as permitted
```

---

# 79. Customer Order Must Not Expose

Do not expose:

```text id="x9i4q0"
staff internal notes
inventory reservation identifiers
internal payment credentials
provider secrets
database IDs not needed
internal audit metadata
administrative-only information
```

---

# 80. Staff Order Response

Staff may receive operational fields such as:

```text id="5cxr5g"
customer contact needed for fulfillment
delivery information
operational status
inventory/fulfillment references where authorized
```

but not authentication secrets.

---

# 81. Admin Order Response

Admin may receive the broadest operational representation, subject to the field exposure policy.

Do not return secrets.

---

# 82. Order Mutability

After Order creation, most core transaction fields should be immutable.

Especially:

```text id="3i9zn1"
customer
order reference
historical item prices
historical quantities
original creation time
```

Do not make them generally PATCHable.

---

# 83. Mutable Order Fields

Only explicitly operational fields may be changed through approved workflows.

Potential:

```text id="dl1jjb"
delivery fee
fulfillment operational data
state through controlled action
```

Do not create generic mutable Order fields unless necessary.

---

# 84. Delivery Fee Mutation

Because Staff/Admin may add the delivery fee, define whether:

```text id="2l0s1b"
delivery fee
```

is mutable until payment/order finalization.

If changed after a payment, the later Payment Group H contract must address the financial consequences.

Do not allow unrestricted repeated fee changes.

---

# 85. Delivery Fee Audit

Every important fee change should eventually be traceable.

At minimum consider:

```text id="hy5zpl"
actor
time
old value
new value
reason
```

Do not implement audit logging in this phase.

---

# 86. Order Correction

Administrative correction of historical Order data should be a deliberate workflow.

Do not solve data corrections by allowing arbitrary:

```text id="o7rm9i"
PATCH /orders/{id}
```

for all fields.

---

# 87. Order Deletion

Do not provide normal customer Order deletion.

Orders are historical business records.

Avoid:

```text id="ub06gi"
DELETE /orders/{id}
```

for customer use.

---

# 88. Order Cancellation vs Deletion

Make the distinction explicit:

```text id="8d2vc4"
CANCEL
≠
DELETE
```

Cancellation is a business event.

Order history remains.

---

# 89. Completed Orders

Completed Orders should remain readable to the customer and authorized staff/admin.

They should not disappear simply because the lifecycle is complete.

---

# 90. Order Ownership After Completion

Customer ownership remains intact after completion.

A completed Order still belongs to the Customer who purchased it.

---

# 91. Historical Product Representation

Order Item snapshots remain authoritative even if:

```text id="v9ozm7"
Product name changes
Product image changes
Product price changes
Product is deactivated
Variant changes
```

---

# 92. Historical Delivery Representation

The Order preserves the transaction's fulfillment information.

Later profile changes must not rewrite historical Order data.

---

# 93. Historical Financial Representation

The Order preserves:

```text id="xg5prr"
unit prices
line totals
subtotal
delivery fee
total
currency
```

according to the approved money model.

---

# 94. Order Security — IDOR

Explicitly test:

```text id="p3f8ab"
Customer A
→ attempts Customer B's Order ID
```

Must fail.

Also:

```text id="8tgr0f"
Customer A
→ attempts Customer B's tracking endpoint
```

must fail.

---

# 95. Order Security — Status Tampering

Test:

```json id="vsy5l6"
{
  "status": "COMPLETED"
}
```

from a Customer.

Must not change the Order.

---

# 96. Order Security — Price Tampering

Test attempts to submit:

```json id="c3g1g2"
{
  "total": 1
}
```

or equivalent.

Must not alter the authoritative Order financial record.

---

# 97. Order Security — Delivery Fee Tampering

Test:

```json id="e0x28a"
{
  "delivery_fee": 0
}
```

from a Customer.

Must not override the authorized fee.

---

# 98. Order Security — Ownership Tampering

Test:

```json id="lwrj9v"
{
  "customer_id": "another-customer"
}
```

Must not transfer Order ownership.

---

# 99. Order Security — Cancellation Time Tampering

Test:

```json id="qg9yv1"
{
  "cancelled_at": "within-20-minutes"
}
```

must not bypass the server-time cancellation rule.

---

# 100. Order State Transition Security

Every state action must verify:

```text id="d2p65y"
authenticated actor
+
permission
+
current state
+
fulfillment type
+
business preconditions
```

---

# 101. Staff Order Processing

Normal Staff workflow should feel like:

```text id="x6s8s9"
New Order
 ↓
Accept
 ↓
Process
 ↓
Fulfill
```

The security model should not make Staff obtain Admin authorization for ordinary approved order-processing steps.

---

# 102. Staff Operational Efficiency

The goal is:

> **Strong authorization at the boundary, simple workflow after authorization.**

Once a Staff member is authorized for an operational action, the system should not repeatedly require unnecessary administrative approval.

---

# 103. Admin Staff Management

Admin retains authority over:

```text id="a9g3o9"
staff approval
staff management
role management
```

This remains separate from normal customer Order processing.

---

# 104. Customer Experience

A normal customer should experience:

```text id="0s5a4q"
Order created
 ↓
Order visible immediately when appropriate
 ↓
Status updates
 ↓
Tracking updates
 ↓
Completion
```

without Staff needing to approve every customer action.

---

# 105. Order Notifications

Order lifecycle events may produce notifications.

Examples:

```text id="v4z7l7"
order received
payment confirmed
order accepted
order processing
ready for pickup
shipped
delivered
completed
```

The exact notification contract is later.

Do not implement notifications here.

---

# 106. Notification Recipient

Customer Order notifications belong to the relevant Customer.

Staff operational notifications belong to relevant operational actors.

Admin may receive administrative notifications.

---

# 107. Order and Next.js

Next.js must be able to:

```text id="t2c1s7"
show order confirmation
display order history
show order detail
show tracking
show cancellation status
```

using the Order API.

---

# 108. Order and Flutter

Flutter must be able to:

```text id="j6lnzz"
display orders
refresh state
track status
cancel eligible orders
handle retries
```

using the same API contract.

---

# 109. Order and Admin/Staff

Operational clients must be able to:

```text id="0p4y4k"
find orders
view order details
perform authorized transitions
manage fulfillment
```

without using a separate incompatible Order data model.

---

# 110. Order API Errors

Use the common error contract.

Potential codes:

```text id="sk3o6w"
ORDER_NOT_FOUND
ORDER_NOT_CANCELLABLE
INVALID_ORDER_TRANSITION
ORDER_STATE_CONFLICT
FORBIDDEN
AUTHENTICATION_REQUIRED
```

Add only codes that are actually needed.

---

# 111. Cancellation Error

When the 20-minute window has expired:

```text id="j3w0v3"
ORDER_NOT_CANCELLABLE
```

is preferable to a generic:

```text id="l8m6t3"
BAD_REQUEST
```

provided the code is approved.

---

# 112. State Conflict Error

If the order changes between read and mutation:

```text id="f8h4ki"
ORDER_STATE_CONFLICT
```

may be appropriate.

The exact HTTP status follows Phase 1.16.

---

# 113. Order Response Compatibility

Within Version 1:

Do not change:

```text id="7r8k77"
status field meaning
order reference semantics
historical price semantics
fulfillment type meaning
money representation
```

without compatibility review.

---

# 114. Closed Order Statuses

Document the authoritative Version 1 status list in:

```text id="a6nt72"
docs/api/api-contract.md
```

using only already-approved values.

If a required status is not yet approved, **do not invent it here**.

Instead record the missing domain decision and stop before finalizing the Order state machine.

---

# 115. API Contract Matrix

Add:

| ID       | Method   | Path                                 | Actor       | Auth     | Authorization  | Purpose         |
| -------- | -------- | ------------------------------------ | ----------- | -------- | -------------- | --------------- |
| ORD-001  | GET      | `/api/v1/me/orders`                  | Customer    | Required | Own orders     | Order history   |
| ORD-002  | GET      | `/api/v1/me/orders/{order}`          | Customer    | Required | Own order      | Order detail    |
| ORD-003  | GET      | `/api/v1/me/orders/{order}/tracking` | Customer    | Required | Own order      | Tracking        |
| ORD-004  | POST     | `/api/v1/me/orders/{order}/cancel`   | Customer    | Required | Own + eligible | Cancel order    |
| ORD-005+ | GET/POST | Operational paths                    | Staff/Admin | Required | Operational    | Staff workflows |

Adjust paths to the final Phase 1.19 naming decisions.

---

# 116. Order Endpoint Record Format

For each endpoint document:

```text id="lr8v0x"
Endpoint ID
Method
Path
Purpose
Actors
Authentication
Authorization
Input
Validation
Business rules
Response
Errors
Idempotency
Concurrency
Caching
```

---

# 117. Customer Order Collection Contract

Document:

```text id="fa9t1c"
Authentication:
Required

Authorization:
Own Orders

Pagination:
Global pagination

Default sorting:
Newest first or approved equivalent

Filters:
Only approved filters

Response:
Paginated Order Summary collection
```

---

# 118. Customer Order Detail Contract

Document:

```text id="0bc5s8"
Authentication:
Required

Authorization:
Order belongs to authenticated customer

Response:
Customer Order Detail

Errors:
Authentication/Not Found/etc.
```

---

# 119. Customer Cancel Contract

Document:

```text id="7tq7q2"
Authentication:
Required

Authorization:
Own Order

Conditions:
Within 20-minute cancellation window
Order state permits cancellation

Input:
None or minimal action-specific input

Server-controlled:
Cancellation timestamp
resulting state
financial consequences
```

---

# 120. Staff Accept Contract

If approved by the state machine:

```text id="s8q8u9"
POST .../accept
```

Authorization:

```text id="j5o3c1"
Staff with order-processing permission
```

Precondition:

```text id="lyan1m"
current state permits ACCEPTED
```

---

# 121. Staff Process Contract

Likewise:

```text id="s3qnp0"
POST .../process
```

with:

```text id="xxykz4"
Staff authorized
+
valid current state
```

---

# 122. Staff Ready-for-Pickup Contract

Only for:

```text id="kq9j2y"
PICKUP
```

if that state is part of the approved lifecycle.

Do not expose it for Delivery orders.

---

# 123. Staff Ship Contract

Only for:

```text id="h72r5n"
DELIVERY
```

if `SHIPPED` is part of the approved lifecycle.

---

# 124. Staff Deliver Contract

Only for:

```text id="l1n7e3"
DELIVERY
```

and only from the appropriate previous state.

---

# 125. Staff/Admin Complete Contract

Only if `COMPLETED` is explicitly an operational terminal state.

The actor and exact action must be defined.

---

# 126. Fulfillment-Aware State Transitions

Order state authorization must account for:

```text id="ay5w6r"
PICKUP
vs
DELIVERY
```

Do not permit:

```text id="s2z4p0"
PICKUP → SHIPPED
```

or:

```text id="ux2p31"
DELIVERY → READY_FOR_PICKUP
```

unless deliberately supported.

---

# 127. Order Concurrency

Critical transitions must be protected against:

```text id="0t9d9r"
two Staff members processing simultaneously
Staff + Admin acting simultaneously
Customer cancellation racing with Staff acceptance
```

The eventual implementation must safely resolve such races.

---

# 128. Example Race

```text id="1y51j4"
Customer requests cancellation
        +
Staff accepts order
        ↓
same time
```

The server must not allow both incompatible transitions to silently commit.

This requires later transactional/concurrency design.

---

# 129. Order Idempotency

Critical Order actions should be designed so retries do not create duplicate effects.

Examples:

```text id="i9ki5t"
accept
ship
deliver
cancel
```

The exact idempotency approach is later.

---

# 130. Cancel Retry

If a customer sends Cancel twice:

```text id="v1tnj3"
first → successfully cancelled

second → deterministic outcome
```

The API must not accidentally:

```text id="c6g4l5"
create duplicate cancellation side effects
```

---

# 131. Staff Action Retry

Similarly:

```text id="jvv3m3"
ship order
```

must not result in duplicate shipping side effects if the client retries.

---

# 132. Payment Interaction

An Order may be created before or during Payment depending on the Checkout/Payment model already selected.

The Order contract must **not contradict** that decision.

If Payment requires an existing Order:

```text id="kh7j43"
Order
 ↓
Payment
```

must be available.

If Order cannot become fully final until Payment, its lifecycle must reflect the approved model.

---

# 133. Delivery Fee + Order + Payment

This is the most important integration review in Phase 1.23.

Verify:

```text id="sj2z1c"
delivery fee timing
+
Order total timing
+
Payment timing
```

are mutually consistent.

There must never be ambiguity about:

> What exact amount did the customer agree to/pay for this Order?

---

# 134. Order Financial Finality

Define the point at which:

```text id="2m1cv1"
subtotal
delivery fee
total
```

becomes the authoritative transaction amount.

If a fee can still change after payment, define how that is handled.

Do not implement ambiguous financial behavior.

---

# 135. Payment Reconciliation Boundary

Later Group H must be able to answer:

```text id="j4s1m8"
Payment amount
=
what authoritative Order amount?
```

This phase should establish the Order side of that relationship.

---

# 136. Order Status History and Payment

Do not automatically create a status history event for every provider-internal payment state.

Only business-relevant Order transitions belong in the Order lifecycle.

---

# 137. Order and Inventory

The Order represents what was purchased.

Inventory represents stock state.

Do not make Order Item quantity directly equal current inventory quantity.

---

# 138. Inventory Snapshot

Do not necessarily store:

```text id="l5p7r9"
current stock
```

inside Order Items.

Store historical purchase data required for audit and display.

Current inventory remains an operational resource.

---

# 139. Customer Order Privacy

A Customer Order response may contain personally sensitive information.

It should remain:

```text id="qnw5t0"
private
non-public
non-shared
```

and must not be publicly cached.

---

# 140. Order Cache Policy

Customer Order reads:

```text id="w1m5s0"
PRIVATE
```

Operational Staff/Admin Order data:

```text id="c5dj2v"
PRIVATE/INTERNAL
```

Do not publicly CDN-cache Order responses.

---

# 141. Order Search Security

Staff/Admin search must not accidentally expose customer data to unauthorized Staff.

Authorization must apply before result serialization.

---

# 142. Order List Performance

The Order list may eventually grow significantly.

Later implementation should consider:

```text id="h8e3sz"
indexes
pagination
efficient filtering
stable ordering
minimal joins
```

Do not design database indexes here.

---

# 143. Order Detail Performance

Order detail may require:

```text id="3d6xvb"
items
customer information
fulfillment
tracking
payment summary
```

The implementation should avoid uncontrolled N+1 queries.

Do not optimize in this phase.

---

# 144. Order Customer Representation vs Operational Representation

Define the conceptual distinction:

```text id="c6e8d2"
Customer Order
→ customer-facing

Operational Order
→ Staff-facing

Administrative Order
→ Admin-facing
```

Same domain resource, different authorized representations.

---

# 145. Field-Level Access

Add a matrix:

| Field/Data          | Customer |             Staff |      Admin |
| ------------------- | -------: | ----------------: | ---------: |
| Order reference     |      Yes |               Yes |        Yes |
| Items               | Yes, own |               Yes |        Yes |
| Historical prices   | Yes, own |               Yes |        Yes |
| Delivery address    |      Own |       Operational | Authorized |
| Payment summary     |  Limited |       Operational | Authorized |
| Internal notes      |       No | Yes if authorized |        Yes |
| Inventory internals |       No |       As required |        Yes |
| Credentials         |       No |                No |         No |

---

# 146. Order API and Customer Account Principle

Staff must not gain customer-account control merely because they can view an Order.

Staff access is operational.

Customer account ownership remains with the Customer.

---

# 147. Order API and Staff Principle

Staff should have enough access to process normal Orders efficiently.

Do not introduce unnecessary approval steps between:

```text id="5qj8s1"
Staff
→ valid Order action
```

when the Staff permission already authorizes it.

---

# 148. Order API and Admin Principle

Admin has broader operational authority but must still use explicit business actions.

Avoid generic "superuser bypass" endpoints.

---

# 149. Required Documentation Updates

## `docs/api/api-contract.md`

Add:

```text id="a6qf01"
## Order API Contract

### Order Resource
### Order Identity
### Customer Order Access
### Staff Operational Access
### Admin Access
### Order Items
### Historical Data
### Fulfillment
### Delivery Fee
### Payment Relationship
### Order Status
### State Transitions
### Cancellation
### Tracking
### Pagination
### Query Filters
### Representations
### Errors
### Idempotency
### Concurrency
### Security
```

---

## `docs/api/api-resources.md`

Update:

```text id="p3qy8p"
Order
Order Item
Fulfillment
Tracking
Status History
```

with:

```text access
relationships
historical fields
mutable/immutable fields
representation levels
```

---

## `docs/api/api-conventions.md`

Add reusable Order conventions:

```text id="8g8h3o"
state transitions
historical snapshots
immutable financial data
ownership checks
controlled actions
private caching
concurrency
```

---

## `docs/domain/business-rules.md`

Confirm:

```text id="g1wj41"
Order belongs to Customer.
Order created through Checkout.
Order reference is server-generated.
Order history is preserved.
Historical prices are immutable.
Customer cancellation is limited to 20 minutes.
Pickup and Delivery follow different fulfillment branches.
Delivery fee is variable and not customer-controlled.
Staff process Orders.
Admin has highest operational authority.
```

Do not invent statuses not already approved.

---

## `docs/decisions.md`

Record major Order decisions, for example:

```text id="3v2l9q"
### ORD-001 — Orders Are Customer-Owned Historical Records

### ORD-002 — Customer Order Ownership Cannot Be Client-Supplied

### ORD-003 — Order Items Preserve Historical Purchase Information

### ORD-004 — Order Cancellation Has a 20-Minute Customer Window

### ORD-005 — Order Status Transitions Are Controlled Actions

### ORD-006 — Delivery Fee Is Not Customer-Controlled

### ORD-007 — Pickup and Delivery Follow Distinct Order Paths

### ORD-008 — Customer Order Data Is Private

### ORD-009 — Payment Is Related to Order but Defined in Group H
```

Only record decisions actually accepted.

---

# 150. Required State Transition Matrix

Place the authoritative table in:

```text id="y9y2w1"
docs/api/api-contract.md
```

Example structure:

| Current State    | Action           | Actor                   | Fulfillment | Next State       |
| ---------------- | ---------------- | ----------------------- | ----------- | ---------------- |
| PENDING_PAYMENT  | payment success  | System/Payment workflow | Any         | PAID             |
| PAID             | accept           | Staff/Admin             | Any         | ACCEPTED         |
| ACCEPTED         | process          | Staff/Admin             | Any         | PROCESSING       |
| PROCESSING       | ready-for-pickup | Staff/Admin             | PICKUP      | READY_FOR_PICKUP |
| PROCESSING       | ship             | Staff/Admin             | DELIVERY    | SHIPPED          |
| SHIPPED          | deliver          | Staff/Admin             | DELIVERY    | DELIVERED        |
| READY_FOR_PICKUP | complete         | Authorized actor        | PICKUP      | COMPLETED        |
| DELIVERED        | complete         | Authorized actor        | DELIVERY    | COMPLETED        |

**Important:** Use only transitions that are actually approved by the frozen business model. This table is a template for the contract, not permission to invent missing statuses/transitions.

---

# 151. Required Authorization Matrix

Add:

| Operation                    | Customer |                Staff |                                 Admin |
| ---------------------------- | -------: | -------------------: | ------------------------------------: |
| View own Orders              |      Yes |          No as owner |                            Authorized |
| View operational Orders      |       No |                  Yes |                                   Yes |
| Cancel own eligible Order    |      Yes |                   No | Authorized only if explicitly defined |
| Accept Order                 |       No |                  Yes |                                   Yes |
| Process Order                |       No |                  Yes |                                   Yes |
| Ready for Pickup             |       No | Yes where applicable |                                   Yes |
| Ship                         |       No | Yes where applicable |                                   Yes |
| Deliver                      |       No | Yes where applicable |                                   Yes |
| Complete                     |       No |  According to policy |                                   Yes |
| Modify historical item price |       No |                   No |            Controlled correction only |
| Change Order owner           |       No |                   No |        Controlled admin workflow only |

---

# 152. Required Historical Data Matrix

Document:

| Order field           |                 Mutable after creation? | Customer |                   Staff |                      Admin |
| --------------------- | --------------------------------------: | -------: | ----------------------: | -------------------------: |
| Order reference       |                                      No |     Read |                    Read |                       Read |
| Customer owner        |                                      No |     Read |             Operational |                 Authorized |
| Item quantity         |                                      No |     Read |                    Read | Controlled correction only |
| Historical unit price |                                      No |     Read |                    Read | Controlled correction only |
| Order creation time   |                                      No |     Read |                    Read |                       Read |
| Fulfillment type      |                            Generally No |     Read |             Operational |                 Controlled |
| Delivery fee          |               According to fee workflow |     Read |              Authorized |                 Authorized |
| Total                 | Derived/finalized according to workflow |     Read |             Operational |                 Authorized |
| Status                |                       Action-controlled |     Read |                  Action |                     Action |
| Status history        |                             Append-only |     Read | Read/append via actions |    Read/append via actions |

Do not allow this table to become inconsistent with the actual fee/payment workflow decision.

---

# 153. Required Security Tests

Document future tests for:

```text id="k3fn8e"
Customer A → Customer B Order
Customer A → Customer B tracking
Customer → arbitrary status change
Customer → price manipulation
Customer → delivery-fee manipulation
Customer → customer-owner manipulation
Customer → cancellation after 20 minutes
Customer → cancellation of completed order
Staff → customer password
Staff → arbitrary customer account restriction
Staff → self-approval/Admin escalation
Staff → invalid order transition
Admin → invalid business transition
```

---

# 154. Required Financial Tests

Later automated tests must verify:

```text id="pbx8l1"
historical price remains stable
subtotal is correct
delivery fee is authoritative
total equals approved components
customer cannot override total
payment amount matches authoritative Order amount
```

The last item is especially important for Group H integration.

---

# 155. Required Concurrency Tests

Later tests must cover:

```text id="n6j6de"
Customer cancel + Staff accept race
Staff accept + Staff accept race
Staff ship + Staff ship retry
Delivery state transition race
Admin vs Staff state transition
Payment confirmation + Order transition race
```

---

# 156. Required Client Workflow Tests

### Customer

```text id="1psxkj"
Login
 ↓
View own order
 ↓
Track
 ↓
Cancel within 20 minutes
```

### Staff

```text id="6kk1f4"
Receive order
 ↓
Accept
 ↓
Process
 ↓
Fulfill
```

### Admin

```text id="d9j0m7"
Review/manage operational order
 ↓
perform authorized administrative operation
```

---

# 157. Recommended Order Response Example

Use only as a conceptual representation:

```json id="7h0f4m"
{
  "data": {
    "id": "...",
    "order_reference": "OD-*****",
    "status": "PROCESSING",
    "fulfillment_type": "DELIVERY",
    "items": [],
    "subtotal": {},
    "delivery_fee": {},
    "total": {},
    "currency": "TZS",
    "created_at": "...",
    "updated_at": "..."
  }
}
```

Do not finalize field details that have not been approved by the existing resource/data model.

---

# 158. Order API and Error Contract

Use:

```text id="j7m0r8"
data
```

for success.

Use:

```text id="wv7g0d"
errors
```

for failures.

Do not create a custom Order error envelope.

---

# 159. Order API and Version 1 Compatibility

Do not alter:

```text id="o6m8y0"
status meaning
order reference meaning
historical price meaning
fulfillment meaning
money structure
ownership semantics
```

within Version 1 without compatibility review.

---

# 160. Required Documentation Completion Gate

Before declaring Phase 1.23 complete, verify:

```text id="r81v6y"
Order resource
Order endpoints
Order state machine
Order authorization
Order historical data
Order fulfillment
Order delivery fee
Order tracking
Order/payment relationship
Order cancellation
Order security
Order concurrency
```

are all internally consistent.

---

# 161. Critical Cross-Phase Review

Compare Order against:

### Catalog

```text id="gr8t34"
Current Product
≠
Historical Order Item
```

### Cart

```text id="whx2o7"
Cart intent
→
Order transaction
```

### Checkout

```text id="f3n8ak"
Checkout
→
creates Order
```

### Authentication

```text id="rq8z17"
Order ownership
→
authenticated customer
```

### Authorization

```text id="6j7e2t"
Customer
→ own Order

Staff
→ operational Order

Admin
→ administrative Order
```

### Error contract

```text id="b4h2q4"
Order failures
→ common errors
```

---

# 162. Delivery Fee + Payment Critical Gate

Do not complete this phase without confirming the consistency of:

```text id="k4s0cb"
Checkout
→ Order creation
→ delivery fee assignment
→ final total
→ Payment
```

If the current decisions do not provide a coherent sequence, document the conflict in:

```text id="sd7l5z"
docs/decisions.md
```

and resolve it before proceeding.

Do not silently guess.

---

# 163. Recommended Version 1 Order Behavior

Subject to the already-approved state model:

```text id="b5q4ky"
Customer checks out
        ↓
Order created
        ↓
Order receives authoritative transaction identity
        ↓
Payment workflow
        ↓
Staff receives operational Order
        ↓
Staff processes
        ↓
Pickup or Delivery branch
        ↓
Completed
```

Customer cancellation remains:

```text id="ur1k9j"
within 20 minutes
+
eligible Order state
```

---

# 164. Explicitly Out of Scope

Do NOT:

```text id="fmx1aa"
Implement Laravel Order model
Implement Order migrations
Implement Order controllers
Implement Order Policies
Implement state-transition code
Implement database transactions
Implement inventory locking
Implement payment integration
Implement payment webhooks
Implement tracking infrastructure
Implement notification dispatch
Build customer Order UI
Build staff Order dashboard
Build admin Order UI
Generate final OpenAPI schemas
```

---

# 165. Definition of Done

Phase 1.23 is complete when:

1. Order is defined as a first-class Version 1 resource.
2. Order ownership is explicitly tied to Customer.
3. Order creation is tied to Checkout.
4. Customer cannot directly create arbitrary Orders.
5. Order reference format `OD-*****` is documented as server-generated.
6. Order Items preserve historical purchase information.
7. Historical prices are immutable through ordinary APIs.
8. Historical quantities are immutable through ordinary APIs.
9. Fulfillment type is defined as closed Version 1 values.
10. Pickup and Delivery paths are explicitly distinguished.
11. Delivery information is preserved appropriately.
12. Delivery fee behavior is explicitly consistent with Checkout.
13. Delivery fee is not customer-controlled.
14. Order totals are server-authoritative.
15. Order statuses are server-controlled.
16. Invalid state transitions are explicitly rejected.
17. Customer cancellation is limited to the approved 20-minute rule.
18. Customer can access only their own Orders.
19. Staff can perform approved operational Order actions.
20. Staff cannot arbitrarily control customer accounts.
21. Admin has the highest operational/administrative authority.
22. Order tracking is defined as a customer-facing capability.
23. Status history is protected and action-generated.
24. Payment is represented as a separate relationship.
25. Payment provider implementation remains in **Phase Group H**.
26. Order data is private and not publicly cacheable.
27. IDOR, privilege escalation, price tampering, status tampering, and ownership tampering are addressed.
28. Concurrency-sensitive operations are identified.
29. Idempotency-sensitive operations are identified.
30. Cross-phase consistency checks pass.
31. Consolidated documentation is updated.
32. No implementation code has been written.

---

# 166. STOP CONDITION — Mandatory

After updating:

```text id="x22w5n"
docs/api/api-contract.md
docs/api/api-resources.md
docs/api/api-conventions.md
docs/domain/business-rules.md
docs/decisions.md
```

and completing the security, financial, state-machine, and cross-phase review:

**STOP.**

Do not implement Orders.

Do not create Laravel models or migrations.

Do not create Order controllers or policies.

Do not implement state transitions.

Do not implement tracking.

Do not implement payment.

Do not proceed automatically to the Payment contract.

The next phase must be explicitly requested.

### Recommended next phase

# Phase 1.24 — Define Order Tracking and Fulfillment API Contract

This phase should take the Order contract and isolate the customer-facing and staff-facing **fulfillment/tracking behavior**, especially:

```text id="pj2o2f"
PICKUP vs DELIVERY
READY_FOR_PICKUP
SHIPPED
DELIVERED
tracking timeline
fulfillment information
delivery information
customer tracking visibility
staff fulfillment operations
status-history representation
```

while keeping **Payment in Phase Group H** and avoiding duplication of the core Order contract.
