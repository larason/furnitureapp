# Phase 1.24 — Define Order Tracking and Fulfillment API Contract

## 1. Purpose

Phase 1.24 defines the **Version 1 Order Tracking and Fulfillment API contract** on top of the finalized Order contract from Phase 1.23.

This phase is intentionally narrower than the Order contract.

The Order contract defines:

```text
Order identity
Order ownership
Order items
Historical financial data
Order lifecycle
Cancellation
Payment relationship
```

This phase defines how the system represents and operates the **physical fulfillment progress** of that Order:

```text
PICKUP
DELIVERY
READY_FOR_PICKUP
SHIPPED
DELIVERED
tracking history
fulfillment information
customer-facing progress
staff fulfillment operations
```

The objective is to allow the customer to understand:

> **Where is my order and what happens next?**

while allowing Staff to execute normal ecommerce fulfillment securely and efficiently.

---

# 2. Documentation Strategy

Continue using the consolidated documentation model.

Update:

```text id="3f6y7j"
docs/api/api-contract.md
docs/api/api-resources.md
docs/api/api-conventions.md
docs/domain/business-rules.md
docs/decisions.md
```

Do **not** create:

```text id="r9m8ce"
tracking-api.md
fulfillment-api.md
tracking-contract.md
delivery-contract.md
```

unless the project later grows sufficiently to justify separating this domain.

---

# 3. Authoritative Project Paths

Use:

```text id="q5w4y0"
AGENTS.md
docs/VISION.md
```

These remain authoritative.

---

# 4. Payment Boundary

Payment remains assigned to:

**Phase Group H**

not Group G.

This phase may expose the fulfillment effects of payment/order state, but must not define:

```text id="bqp6s1"
payment provider
payment gateway integration
payment webhooks
provider transaction states
refund implementation
```

---

# 5. Version 1 Enum Policy

All Version 1 enums remain:

> **CLOSED by default.**

Fulfillment-related values must therefore use only the approved set.

Known:

```text id="u7n3zy"
PICKUP
DELIVERY
```

and the approved Order states from Phase 1.23.

Do not invent new lifecycle statuses simply to simplify the tracking UI.

---

# 6. Dependency Position

Current sequence:

```text id="5g7j4y"
1.20 Catalog API Contract
       ↓
1.21 Cart API Contract
       ↓
1.22 Checkout API Contract
       ↓
1.23 Order API Contract
       ↓
1.24 Order Tracking + Fulfillment API Contract
       ↓
1.25 Request API Contract
       ↓
...
```

This phase must use the finalized Order state/fulfillment rules.

---

# 7. Authoritative Inputs

Read:

```text id="e0yrpn"
AGENTS.md
docs/VISION.md

docs/api/api-contract.md
docs/api/api-resources.md
docs/api/api-conventions.md

docs/domain/business-rules.md
docs/decisions.md
```

Specifically review:

```text id="7s3qqk"
Phase 1.22 Checkout
Phase 1.23 Order
Phase 1.18 Authorization
Phase 1.16 Error Contract
Phase 1.13 Response Contract
Phase 1.12 Pagination
```

---

# 8. Core Principle

Tracking is a **customer-facing interpretation of fulfillment progress**.

Fulfillment is the **operational process by which Staff/Admin move the Order toward completion**.

Therefore:

```text id="9f5k0t"
Customer Tracking
        ↓
read-only view

Staff Fulfillment
        ↓
authorized state-changing operations
```

Do not give customers operational control simply because they can see the same progress.

---

# 9. Fulfillment vs Order

Keep the distinction:

```text id="4e70lu"
Order
→ commercial transaction

Fulfillment
→ how the purchased goods reach/are collected by the customer

Tracking
→ representation of fulfillment progress
```

They are related but should not become one giant undifferentiated object.

---

# 10. Fulfillment Types

Version 1 supports:

```text id="4n9g2x"
PICKUP
DELIVERY
```

This value is selected during Checkout and becomes part of the Order.

The customer cannot change it arbitrarily after the Order has entered processing.

---

# 11. Pickup Fulfillment Flow

For a Pickup Order, the conceptual flow is:

```text id="wj4r6n"
Order
 ↓
Processing
 ↓
Ready for Pickup
 ↓
Customer collects
 ↓
Completed
```

Use only the exact Order states approved in Phase 1.23.

---

# 12. Delivery Fulfillment Flow

For Delivery:

```text id="zq3c9n"
Order
 ↓
Processing
 ↓
Shipped
 ↓
Delivered
 ↓
Completed
```

Again, use only the approved state machine.

---

# 13. Do Not Force One Flow onto Both Types

A Pickup Order should not require:

```text id="q9z0h8"
SHIPPED
DELIVERED
```

A Delivery Order should not normally require:

```text id="3g8c0u"
READY_FOR_PICKUP
```

unless explicitly designed otherwise.

---

# 14. Tracking Endpoint

Define the canonical customer-facing tracking operation.

Canonical (per `api-contract.md §19.1 / §24.5 / §25.6`):

```text id="v9h2k4"
ORD-003

GET /api/v1/me/orders/{order}/tracking
```

`ORD-TRK-001` was the provisional Phase 1.24 alias — replaced by approved `ORD-003` (`GET /me/orders/{order}/tracking`, Customer `AUTHENTICATED_OWNER`).

Authentication:

```text id="o8j7z1"
Required
```

Authorization:

```text id="2w1m8a"
Order belongs to authenticated customer
```

If Phase 1.19/1.23 selected a different canonical Order path, preserve that path convention instead.

Do not create duplicate tracking paths.

---

# 15. Tracking Purpose

The tracking endpoint should answer:

```text id="1q5v7r"
What is the current fulfillment state?
What milestones have occurred?
What is the next expected step?
Is this Pickup or Delivery?
```

It should **not** expose internal workflow implementation.

---

# 16. Tracking Response

Conceptually:

```json id="gz8q1v"
{
  "data": {
    "order_reference": "OD-*****",
    "fulfillment_type": "DELIVERY",
    "status": "SHIPPED",
    "timeline": []
  }
}
```

The final field schema must use the global response conventions.

---

# 17. Tracking Timeline

A customer-friendly timeline may contain:

```text id="l8v6rb"
status
occurred_at
customer-visible label/meaning where appropriate
```

Example:

```text id="q0t7xh"
PAID
ACCEPTED
PROCESSING
SHIPPED
```

Do not expose internal-only events automatically.

---

# 18. Tracking Is Read-Only

Customers must never create/update/delete tracking entries.

Do not expose:

```text id="7y5w9v"
POST /orders/{order}/tracking
PATCH /orders/{order}/tracking
DELETE /orders/{order}/tracking
```

to customers.

Tracking entries originate from valid business actions.

---

# 19. Tracking Derived From Order State

The customer-facing current tracking state should remain consistent with the authoritative Order state.

Do not allow:

```text id="83jz5w"
Order = PROCESSING

Tracking = DELIVERED
```

These cannot contradict one another.

---

# 20. Tracking History Source

The implementation should conceptually derive tracking milestones from authoritative Order/Fulfillment state transitions.

Do not create an independent tracking state machine that can drift away from Order state.

---

# 21. Status History vs Tracking Timeline

Use the distinction established in Phase 1.23:

```text id="8c95p8"
Status History
→ authoritative historical events

Tracking Timeline
→ customer-friendly representation
```

They may use the same underlying records but need not expose identical fields.

---

# 22. Customer-Visible Timeline

Not every internal status or event must be displayed.

For example:

```text id="2m3b7z"
Customer sees:
Order accepted
Being prepared
Ready for pickup
```

Staff may see additional operational information.

---

# 23. Staff Fulfillment Endpoint Inventory

Define the controlled fulfillment operations required by the approved Order state machine.

Canonical (per `api-contract.md §19.1 / §24.5 / §25.10`):

```text id="4k7v62"
ORD-009  POST /api/v1/orders/{order}/ready-for-pickup  (PICKUP, PROCESSING→READY_FOR_PICKUP)
ORD-010  POST /api/v1/orders/{order}/ship            (DELIVERY, PROCESSING→SHIPPED)
ORD-011  POST /api/v1/orders/{order}/deliver         (DELIVERY, SHIPPED→DELIVERED)
```

(`ORD-FUL-001–003` were provisional Phase 1.24 aliases — replaced by approved `ORD-009`/`ORD-010`/`ORD-011`. `ORD-013` `complete` remains explicit per `§24.13`).

Do not create endpoints for actions that do not exist in the finalized state machine.

---

# 24. Accept/Process Relationship

Acceptance/Processing actions were part of the Order contract.

Do not duplicate those endpoints here unless Phase 1.23 intentionally delegated them to this fulfillment contract.

This phase should focus on the **physical fulfillment branch**.

---

# 25. Ready-for-Pickup

For Pickup Orders:

```text id="u1qp5k"
POST /orders/{order}/ready-for-pickup
```

conceptually represents:

> Staff has completed preparation and the Order is available for customer collection.

Authorization:

```text id="r7m8js"
authorized STAFF/ADMIN
```

Preconditions:

```text id="2c5z8u"
Order is PICKUP
current state permits transition
```

---

# 26. Ship

For Delivery Orders:

```text id="44t7d3"
POST /orders/{order}/ship
```

means:

> The Order has left the business and entered delivery.

Authorization:

```text id="d4y8yy"
authorized STAFF/ADMIN
```

Preconditions:

```text id="eq6qvo"
Order is DELIVERY
current state permits SHIPPED
```

---

# 27. Deliver

For Delivery Orders:

```text id="q8b8x3"
POST /orders/{order}/deliver
```

means:

> The delivery has been completed.

Authorization:

```text id="8w3e15"
authorized STAFF/ADMIN
```

Preconditions:

```text id="f8x3y8"
Order is DELIVERY
current state permits DELIVERED
```

---

# 28. Complete

`COMPLETED` requires an explicit Staff/Admin action via the canonical `ORD-013` transition (not automatic).

For Pickup:

```text id="6l7w9j"
READY_FOR_PICKUP
  → POST /api/v1/orders/{order}/complete (ORD-013, Staff/Admin orders.complete)
  → COMPLETED
```

For Delivery:

```text id="f3xg8k"
DELIVERED
  → POST /api/v1/orders/{order}/complete (ORD-013, Staff/Admin orders.complete)
  → COMPLETED
```

Automatic `DELIVERED→COMPLETED` or `READY_FOR_PICKUP→COMPLETED` without `ORD-013` is prohibited. This aligns with the authoritative Order contract `docs/api/api-contract.md §24.13` / `§25.10` (`READY_FOR_PICKUP→COMPLETED` for `PICKUP`, `DELIVERED→COMPLETED` for `DELIVERY`, both via `ORD-013`, `Idempotency-Key` Required, concurrency Critical).

---

# 29. Customer Cannot Mark Delivered

A customer must never submit:

```json id="lg2xpx"
{
  "status": "DELIVERED"
}
```

to mark their Order completed.

Delivery is an operational event.

---

# 30. Customer Cannot Mark Ready for Pickup

Likewise, the customer cannot claim:

```text id="8l0k7w"
ready for pickup
```

The business establishes readiness.

---

# 31. Customer Tracking Access

For:

```text id="y30n6k"
GET /me/orders/{order}/tracking
```

authorization is:

```text id="1l7v4y"
authenticated customer
+
owns Order
```

---

# 32. Staff Tracking Access

Staff may access operational tracking for Orders they are authorized to process.

Do not expose unrelated customer private data.

---

# 33. Admin Tracking Access

Admins may access tracking and operational history according to administrative authorization.

---

# 34. Delivery Information

For Delivery Orders, tracking may include customer-safe information such as:

```text id="rm50r9"
delivery address summary
recipient
delivery status
shipping milestone
```

Do not expose unnecessary internal delivery data.

---

# 35. Pickup Information

For Pickup Orders, tracking should provide appropriate pickup information.

Potential:

```text id="qpnj8u"
pickup status
pickup readiness
pickup location
collection instructions
```

Use only information already approved by the project's business model.

Do not invent a multi-location warehouse system if it does not exist.

---

# 36. Pickup Location

Determine whether Version 1 has:

```text id="8o94bk"
one pickup location
multiple locations
configurable pickup locations
```

Use existing business documentation.

If this is not yet defined, record it as a dependency rather than inventing it.

---

# 37. Delivery Address Privacy

Customer delivery address is private.

It must only be visible to:

```text id="k7d1le"
Customer who owns Order
authorized Staff
authorized Admin
```

Do not expose it through public tracking.

---

# 38. Delivery Fee in Tracking

Tracking does not need to recalculate the delivery fee.

The authoritative fee belongs to Order financial data.

Tracking may display it only if useful to the customer.

Do not create a second delivery-fee source.

---

# 39. Delivery Fee Changes

If the delivery fee can be added/changed by Staff/Admin after Order creation, tracking should not independently modify it.

The Order contract remains authoritative.

---

# 40. Delivery Fee + Payment

If the final payment amount depends on the delivery fee, the tracking API must not show the Order as financially settled until the approved Order/Payment workflow says it is.

Payment details remain in Group H.

---

# 41. Tracking Timestamps

Timeline events should use the global timestamp convention:

```text id="1b2fxo"
ISO 8601/RFC 3339-compatible UTC
```

Example:

```text id="h1f1l6"
2026-09-01T09:45:00Z
```

Do not return mixed timestamp formats.

---

# 42. Timeline Ordering

Timeline must be deterministic.

Recommended:

```text id="0j4z2r"
chronological ascending
```

for customer timeline display.

If the Order API elsewhere uses reverse chronological history, keep each convention explicit rather than relying on client assumptions.

---

# 43. Timeline Event Identity

Determine whether each tracking event needs a stable identifier.

Recommended:

> Use a stable event ID if the underlying status-history model provides one.

Do not expose an internal database ID purely because it exists.

---

# 44. Timeline Event Immutability

Historical fulfillment events should be append-only in normal operation.

Do not allow:

```text id="q7j3a1"
customer
→ edit history

staff
→ rewrite past status
```

Corrections, if necessary, require an explicit administrative workflow.

---

# 45. Tracking Event Actor

Determine whether customer-facing tracking should expose who performed the action.

Recommended:

> Usually expose the business event, not the Staff person's identity.

For example:

```text id="k1ygjm"
"Your order has been shipped."
```

rather than:

```text id="5hp19n"
"John Smith shipped your order."
```

unless there is a real business requirement.

---

# 46. Staff Operational History

Staff/Admin may need richer event information than customers.

This can include:

```text id="8ixplf"
actor
internal note
operational timestamp
```

subject to the authorization contract.

---

# 47. Customer-Facing Notes

If tracking allows customer-visible notes, they must be explicitly distinguished from internal Staff notes.

Do not serialize internal notes to customers.

---

# 48. Tracking and Notifications

Fulfillment state transitions may later trigger notifications:

```text id="9vv6d3"
ready for pickup
shipped
delivered
```

This phase defines the source event.

Notification delivery remains a separate domain.

Do not implement notifications here.

---

# 49. Tracking and Email

Email delivery remains deferred to:

**Phase Group R**

Tracking API responses should not depend on email delivery succeeding.

---

# 50. Tracking and In-App Notifications

Later notification logic may consume fulfillment events.

Do not make tracking depend on notification persistence to determine Order state.

---

# 51. Tracking and Webhooks

Do not expose internal webhook/provider events through the customer tracking API.

Payment provider events remain internal until mapped into approved business state.

---

# 52. Tracking and Real-Time Updates

Evaluate whether Version 1 requires:

```text id="fx6b98"
WebSockets
Server-Sent Events
push notifications
polling
```

For a small-scale commerce system, a simple approach such as:

```text id="6a9f0y"
GET tracking
```

plus normal client refresh/polling may be sufficient.

Do not introduce real-time infrastructure without a business requirement.

---

# 53. Recommended Version 1 Tracking Model

Prefer:

```text id="j8v3o1"
REST GET tracking
+
client refresh/polling when needed
```

rather than introducing real-time architecture in the initial API contract.

---

# 54. Tracking Frequency

Do not require aggressive polling.

Later frontend implementation can determine reasonable refresh behavior.

The API contract only establishes the authoritative read mechanism.

---

# 55. Delivery Driver Tracking

Do not assume live GPS tracking.

Version 1 tracking means:

```text id="7d0k4l"
fulfillment/order milestones
```

not:

```text id="f1a8f4"
real-time vehicle location
```

unless explicitly added to scope.

---

# 56. Shipping Provider Integration

Do not introduce courier integration automatically.

If the business manually handles delivery, the API should represent the business process without pretending there is an external carrier system.

---

# 57. Tracking Number

Evaluate whether Version 1 needs an external tracking number.

If there is no external carrier integration, do not invent:

```text id="h8c7g0"
tracking_number
carrier
tracking_url
```

merely because normal ecommerce platforms often have them.

---

# 58. Internal Delivery Reference

If the business uses internal delivery references, distinguish them from customer-facing tracking numbers.

Do not expose internal IDs without need.

---

# 59. Fulfillment Assignment

Evaluate whether Staff assignments are part of Version 1.

Potential future:

```text id="6m3fqs"
assigned_staff
assigned_delivery
```

Do not add assignment complexity unless the business actually needs it.

---

# 60. Operational Fulfillment Model

For the current small-scale business, favor:

```text id="6j2h5k"
Order
 ↓
Staff receives order
 ↓
Staff processes
 ↓
Staff fulfills
```

rather than building a full logistics-management platform.

---

# 61. Staff Operational Efficiency

Once authorized, Staff should be able to:

```text id="h5y4wk"
open Order
→ choose appropriate fulfillment action
→ update state
→ continue next order
```

without unnecessary Admin approval.

---

# 62. Staff Authorization

Every fulfillment action must require:

```text id="9y4a1x"
authenticated Staff/Admin
+
appropriate operational permission
+
correct Order
+
valid current state
+
correct fulfillment type
```

---

# 63. Admin Authorization

Admin may perform authorized fulfillment actions without needing Staff permission.

However, Admin still must respect the business state machine unless an explicitly approved correction workflow exists.

---

# 64. Customer Authorization

Customer gets:

```text id="9m6xg8"
read own tracking
```

not:

```text id="cb9e5m"
write fulfillment state
```

---

# 65. Fulfillment Action Idempotency

Actions such as:

```text id="o9r5f6"
ship
deliver
ready-for-pickup
```

are critical mutations.

They must be designed so retries do not create duplicate business effects.

Mark them as:

```text id="c7z4ry"
idempotency-sensitive
```

---

# 66. Fulfillment Concurrency

Important race conditions include:

```text id="7x4o8j"
Staff A ships
+
Staff B ships

Staff A delivers
+
Admin changes state

Customer cancels
+
Staff accepts/ships
```

These must be handled using the domain state machine and later transaction/concurrency controls.

---

# 67. Invalid Fulfillment Action

If a Pickup Order receives:

```text id="x0y79v"
ship
```

the request must fail.

If a Delivery Order receives:

```text id="q1h0n4"
ready-for-pickup
```

the request must fail unless explicitly supported.

Use the standard error contract.

---

# 68. Fulfillment Errors

Potential codes:

```text id="6q2f79"
INVALID_ORDER_TRANSITION
FULFILLMENT_ACTION_NOT_ALLOWED
ORDER_NOT_FOUND
FORBIDDEN
ORDER_STATE_CONFLICT
```

Only add dedicated codes if the client benefits from distinguishing them.

---

# 69. Tracking Errors

Customer tracking must return:

```text id="u8sx7p"
401 AUTHENTICATION_REQUIRED — missing/invalid authentication
404 ORDER_NOT_FOUND (masked 404 RESOURCE_NOT_FOUND) — ownership mismatch (another customer's Order)
```

`FORBIDDEN (403)` must not be used for ownership mismatch — returning `403` reveals that the Order exists. The canonical Order contract uses masked `404 ORDER_NOT_FOUND` for `Customer A → Customer B Order` and `Customer A → Customer B tracking` (`docs/api/api-contract.md §24.3/§25.13`, `docs/decisions.md ORD-002/FUL-001`). Unauthenticated requests receive `401`; ownership mismatch receives masked `404`.

Do not reveal another customer's tracking information.

---

# 70. Tracking Response Security

Do not expose:

```text id="qqh2d5"
staff notes
internal delivery routes
customer-service notes
payment provider details
internal database IDs
```

unless explicitly required by the authorized representation.

---

# 71. Customer Privacy

A customer should see:

```text id="l1x4y2"
their own order progress
their own pickup/delivery information
their own status history
```

and nothing belonging to another customer.

---

# 72. Staff Privacy

Staff should see only customer information needed for fulfillment.

Do not expose unrelated customer history.

---

# 73. Admin Privacy

Admin can have broader access but should still receive information according to data minimization rules.

---

# 74. Tracking Cache

Customer tracking is private.

Do not public-cache:

```text id="b7p3e4"
/me/orders/{order}/tracking
```

---

# 75. Public Catalog Independence

Tracking must remain completely separate from public Catalog caching.

Do not allow a tracking request to influence public Product responses.

---

# 76. Pagination

A normal customer tracking response is not necessarily paginated.

A timeline may become long over time, but Version 1 should avoid unnecessary pagination unless actual history volume warrants it.

If the status history is paginated, use the global pagination convention.

Do not invent a tracking-specific pagination grammar.

---

# 77. Tracking Response Size

Keep customer tracking lightweight.

Do not embed:

```text id="x6d9az"
entire Order object
full customer profile
full payment history
internal staff records
```

inside Tracking unless explicitly needed.

---

# 78. Recommended Tracking Representation

Conceptually:

```json id="b2j3n6"
{
  "data": {
    "order_reference": "OD-*****",
    "fulfillment_type": "DELIVERY",
    "current_status": "SHIPPED",
    "timeline": [
      {
        "status": "PAID",
        "occurred_at": "..."
      },
      {
        "status": "ACCEPTED",
        "occurred_at": "..."
      },
      {
        "status": "PROCESSING",
        "occurred_at": "..."
      },
      {
        "status": "SHIPPED",
        "occurred_at": "..."
      }
    ]
  }
}
```

This is illustrative and must be adapted to the finalized Order representation.

---

# 79. Fulfillment Representation

Order detail may contain:

```text id="f7p7k1"
fulfillment_type
delivery information
pickup information
```

Tracking should avoid duplicating everything.

Define which information is authoritative in Order vs Tracking.

---

# 80. Do Not Duplicate Financial Data

Tracking should not become another financial source.

For example, if `delivery_fee` is shown, it must derive from the Order's authoritative financial representation.

---

# 81. Tracking Endpoint Scope

The endpoint:

```text id="e9o6j4"
GET /me/orders/{order}/tracking
```

should focus on progress.

Do not turn it into:

```text id="0ktm1h"
GET /me/orders/{order}/everything
```

---

# 82. Fulfillment Action Scope

Action endpoints should only change fulfillment-related business state.

Do not let:

```text id="7j1nq3"
POST /orders/{order}/ship
```

change:

```text price
delivery fee
customer
payment status
```

as side effects unless explicitly required and documented.

---

# 83. Side Effects

Fulfillment actions may legitimately produce:

```text id="3o4fdv"
status history
notifications
audit records
```

but those should occur as controlled backend side effects.

---

# 84. Customer Cancellation Interaction

Fulfillment actions must respect the 20-minute customer cancellation window.

Example:

```text id="j86cv4"
Customer cancellation
+
Staff acceptance
```

race must resolve according to the authoritative Order state/transaction rules.

---

# 85. Fulfillment and Cancellation

Do not assume:

```text id="9jd8e0"
Staff starts processing
→ cancellation automatically allowed
```

or:

```text id="s3x4q0"
Staff starts processing
→ cancellation automatically prohibited
```

unless the approved business state machine explicitly says so.

---

# 86. Fulfillment and Payment

Fulfillment should normally begin only when the Order reaches the appropriate payment/business state.

The exact dependency is governed by the Checkout/Payment contract.

Do not create a contradictory rule.

---

# 87. Payment Status Is Not Fulfillment Status

Do not use:

```text id="n1a1b2"
PAID = SHIPPED
```

or equivalent.

Payment and fulfillment are separate concerns.

---

# 88. Fulfillment and Inventory

A fulfilled Order should correspond to appropriate inventory handling.

However, the Tracking API does not expose inventory-management actions.

---

# 89. Fulfillment and Historical Order

Fulfillment changes the current operational state but must not rewrite historical transaction fields.

For example:

```text id="tq9p8e"
shipping
```

must not change:

```text id="8xv3v0"
historical unit price
```

---

# 90. Fulfillment and Delivery Fee

If delivery fee is changed by Staff/Admin, the change must follow the financial workflow established in Phase 1.22/1.23.

Tracking does not own the fee.

---

# 91. Required State/Fulfillment Matrix

Add to:

```text id="e7x1m6"
docs/api/api-contract.md
```

| Fulfillment | State            | Customer | Staff/Admin         |
| ----------- | ---------------- | -------- | ------------------- |
| PICKUP      | PENDING_PAYMENT  | Track    | View                |
| PICKUP      | PROCESSING       | Track    | Process             |
| PICKUP      | READY_FOR_PICKUP | Track    | Set ready           |
| PICKUP      | COMPLETED        | Track    | Complete (ORD-013)  |
| DELIVERY    | PENDING_PAYMENT  | Track    | View                |
| DELIVERY    | PROCESSING       | Track    | Process             |
| DELIVERY    | SHIPPED          | Track    | Ship                |
| DELIVERY    | DELIVERED        | Track    | Mark delivered      |
| DELIVERY    | COMPLETED        | Track    | Complete (ORD-013)  |

Use only approved states/actions.

---

# 92. Required Tracking Endpoint Matrix

Add:

| ID      | Method | Path                                          | Actor       | Auth | Authorization                                   | Purpose             |
| ------- | ------ | --------------------------------------------- | ----------- | ---- | --------------------------------------------- | ------------------- |
| ORD-003 | GET    | `/api/v1/me/orders/{order}/tracking`          | Customer    | Yes  | `AUTHENTICATED_OWNER` owns order (404 masked) | View tracking (timeline, milestones)       |
| ORD-009 | POST   | `/api/v1/orders/{order}/ready-for-pickup`     | Staff/Admin | Yes  | `OPERATIONAL orders.ready_for_pickup + PICKUP + PROCESSING` | Mark pickup ready   |
| ORD-010 | POST   | `/api/v1/orders/{order}/ship`                 | Staff/Admin | Yes  | `OPERATIONAL orders.ship + DELIVERY + PROCESSING` | Ship delivery order |
| ORD-011 | POST   | `/api/v1/orders/{order}/deliver`              | Staff/Admin | Yes  | `OPERATIONAL orders.deliver + DELIVERY + SHIPPED` | Mark delivered      |
| ORD-013 | POST   | `/api/v1/orders/{order}/complete`             | Staff/Admin | Yes  | `OPERATIONAL orders.complete + COMPLETED (READY_FOR_PICKUP\|DELIVERED)` | Complete order `READY_FOR_PICKUP→COMPLETED` / `DELIVERED→COMPLETED` |

Canonical IDs per `api-contract.md §19.1 / §24.5 / §25.10` (`ORD-003` customer tracking, `ORD-009` ready-for-pickup, `ORD-010` ship, `ORD-011` deliver, `ORD-013` complete). Provisional `ORD-TRK-001` / `ORD-FUL-001–003` replaced. `ORD-013` `complete` (`DELIVERED→COMPLETED` / `READY_FOR_PICKUP→COMPLETED`) is explicit Staff/Admin action `§24.13/§25.11`, not automatic.

---

# 93. Required Endpoint Contract Details

For every tracking/fulfillment endpoint document:

```text id="1wzvtd"
Endpoint ID
Method
Path
Purpose
Actor
Authentication
Authorization
Input
Validation
Business preconditions
State transition
Response
Errors
Idempotency
Concurrency
Privacy classification
```

---

# 94. Documentation Updates

## `docs/api/api-contract.md`

Add:

```text id="s5e0t4"
## Order Tracking and Fulfillment Contract

### Tracking Model
### Fulfillment Types
### Pickup Flow
### Delivery Flow
### Tracking Endpoint
### Fulfillment Actions
### State/Action Matrix
### Timeline Representation
### Customer Visibility
### Staff Visibility
### Admin Visibility
### Errors
### Idempotency
### Concurrency
### Privacy
```

---

## `docs/api/api-resources.md`

Update:

```text id="qg7sc4"
Order
Tracking
Fulfillment
Status History
```

with:

```text relationships
representations
access levels
read/write boundaries
```

---

## `docs/api/api-conventions.md`

Add reusable rules for:

```text id="3o5n6h"
timeline ordering
fulfillment actions
state-aware actions
tracking privacy
append-only history
```

---

## `docs/domain/business-rules.md`

Confirm the fulfillment rules:

```text id="3g9cd9"
Pickup and Delivery follow distinct operational paths.
Pickup uses READY_FOR_PICKUP before completion.
Delivery uses SHIPPED then DELIVERED before completion.
Customer can track own Order.
Staff operate fulfillment.
Customer cannot modify fulfillment state.
Fulfillment events do not rewrite historical financial data.
```

Only include states already approved in the Order model.

---

## `docs/decisions.md`

Record important decisions such as:

```text id="5q3m01"
### FUL-001 — Tracking Is Read-Only for Customers

### FUL-002 — Fulfillment Actions Are Controlled Staff/Admin Operations

### FUL-003 — Pickup and Delivery Use Distinct Fulfillment Paths

### FUL-004 — Tracking Is Derived From Authoritative Order/Fulfillment State

### FUL-005 — Fulfillment History Is Not Customer-Mutable

### FUL-006 — No Live GPS Tracking in Version 1
```

Only record the decisions actually approved.

---

# 95. Security Review

Explicitly test:

```text id="5wz63y"
Customer A → Customer B tracking
Customer → mark shipped
Customer → mark delivered
Customer → mark ready for pickup
Staff → invalid fulfillment action
Staff → access unrelated order
Staff → modify historical tracking
Staff → modify customer account
Staff → access credentials
Admin → bypass state machine
```

---

# 96. Operational Review

Confirm Staff can complete normal workflows without unnecessary friction:

```text id="1n2t7c"
Receive Order
 ↓
Process
 ↓
Pickup → Ready
or
Delivery → Ship → Deliver
 ↓
Complete
```

Once Staff is authorized, ordinary fulfillment should not require repetitive Admin approval.

---

# 97. Customer Experience Review

Confirm a customer can:

```text id="4zq3i6"
open own Order
 ↓
see current state
 ↓
understand progress
 ↓
see pickup/delivery mode
 ↓
see relevant fulfillment information
```

without contacting Staff simply to know the current Order state.

---

# 98. Next.js Requirements

Next.js should be able to display:

```text id="js3nj4"
Order timeline
Current status
Pickup readiness
Shipping state
Delivery state
```

using the same API.

---

# 99. Flutter Requirements

Flutter should be able to:

```text id="h7w1m6"
refresh tracking
show timeline
handle new state
show fulfillment instructions
```

without a Flutter-specific tracking API.

---

# 100. No Real-Time Requirement

Unless the project explicitly decides otherwise, Version 1 does not need WebSockets or live GPS tracking.

Polling/refresh against the REST tracking endpoint is sufficient as a starting architecture.

---

# 101. Required Validation Checklist

### Fulfillment

* [ ] `PICKUP` and `DELIVERY` are the approved Version 1 fulfillment types.
* [ ] Fulfillment type is closed.
* [ ] Pickup and Delivery have distinct workflows.
* [ ] Invalid fulfillment/state combinations are rejected.

### Customer

* [ ] Customer can track own Order.
* [ ] Customer cannot track another customer's Order.
* [ ] Customer cannot modify fulfillment state.
* [ ] Customer cannot mark Order shipped/delivered.
* [ ] Customer cannot mark pickup ready.

### Staff

* [ ] Staff can perform normal approved fulfillment operations.
* [ ] Staff actions are state-aware.
* [ ] Staff actions are fulfillment-type-aware.
* [ ] Staff cannot rewrite historical status history.
* [ ] Staff cannot obtain customer credentials.
* [ ] Staff cannot arbitrarily restrict customer ordering.

### Admin

* [ ] Admin can perform authorized fulfillment operations.
* [ ] Admin actions still respect business-state rules.
* [ ] Administrative corrections are separate from normal fulfillment actions.

### Tracking

* [ ] Tracking is read-only for customers.
* [ ] Timeline is deterministic.
* [ ] Timeline uses standard timestamps.
* [ ] Tracking reflects authoritative Order state.
* [ ] Internal events are not exposed unnecessarily.
* [ ] Tracking does not become a duplicate financial source.

### Security

* [ ] IDOR protection is explicit.
* [ ] Object-level authorization is explicit.
* [ ] Function-level authorization is explicit.
* [ ] Sensitive delivery data is protected.
* [ ] Internal delivery information is protected.
* [ ] Public caching is prohibited for private tracking.

### Reliability

* [ ] Fulfillment actions are idempotency-sensitive.
* [ ] Concurrency conflicts are identified.
* [ ] Invalid transitions are rejected.
* [ ] History is append-only in normal operation.

### Payment

* [ ] Fulfillment does not implement payment.
* [ ] Payment remains assigned to **Phase Group H**.
* [ ] Fulfillment/payment state boundaries are consistent.

### Documentation

* [ ] Consolidated documents updated.
* [ ] Domain rules updated where necessary.
* [ ] Decisions recorded centrally.
* [ ] No unnecessary permanent Markdown files created.

---

# 102. Explicitly Out of Scope

Do NOT:

```text id="i0a6w3"
Implement Laravel routes
Implement Laravel controllers
Implement Policies
Implement state-machine code
Implement tracking database tables
Implement GPS tracking
Implement courier integration
Implement WebSockets
Implement push notifications
Implement email notifications
Implement payment provider
Implement payment webhooks
Implement inventory fulfillment logic
Build tracking UI
Build Staff fulfillment UI
Build Admin UI
Generate final OpenAPI schemas
```

---

# 103. Definition of Done

Phase 1.24 is complete when:

1. Tracking is defined as a customer-facing read capability.
2. Fulfillment is defined as an operational capability.
3. Pickup and Delivery are explicitly separated.
4. Pickup follows the approved Ready-for-Pickup workflow.
5. Delivery follows the approved Ship/Deliver workflow.
6. Customer tracking ownership is explicit.
7. Staff operational fulfillment authority is explicit.
8. Admin fulfillment authority is explicit.
9. Customer cannot modify fulfillment state.
10. Fulfillment actions are state-aware.
11. Fulfillment actions are fulfillment-type-aware.
12. Tracking is derived from authoritative Order/Fulfillment state.
13. Tracking history is not customer-mutable.
14. Customer-visible and internal operational information are distinguished.
15. Delivery/pickup information is appropriately protected.
16. Fulfillment does not rewrite historical Order financial data.
17. Fulfillment/payment boundaries are consistent.
18. Idempotency-sensitive actions are identified.
19. Concurrency-sensitive actions are identified.
20. IDOR and privilege-escalation cases are addressed.
21. No unnecessary real-time logistics infrastructure is introduced.
22. Next.js and Flutter requirements are covered.
23. Version 1 enums remain CLOSED.
24. Payment remains assigned to **Phase Group H**.
25. Consolidated documentation is updated.
26. No implementation code has been written.

---

# 104. STOP CONDITION — Mandatory

After updating:

```text id="s7dm8h"
docs/api/api-contract.md
docs/api/api-resources.md
docs/api/api-conventions.md
docs/domain/business-rules.md
docs/decisions.md
```

and completing the security/state-transition/fulfillment review:

**STOP.**

Do not implement tracking.

Do not implement fulfillment controllers.

Do not build a logistics system.

Do not implement GPS tracking.

Do not implement notifications.

Do not implement payment.

The next phase must be explicitly requested.

### Recommended next phase

# Phase 1.25 — Define Made-to-Order Request API Contract

This should define the second major customer acquisition path alongside normal purchasing:

```text id="5g7a2m"
Public Product
   ↓
MADE_TO_ORDER
   ↓
Customer chooses "Request"
   ↓
Anonymous OR authenticated submission
   ↓
Contact information
   ↓
Optional furniture specifications
   ↓
Optional attachment
   ↓
Staff receives request
   ↓
Staff processes/communicates
   ↓
Admin oversight
```

It should preserve the important rule that **Made-to-Order furniture is requested rather than placed through normal Cart → Checkout → Payment**, while anonymous submission remains supported.
