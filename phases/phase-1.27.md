# Phase 1.27 — Define Notification API Contract

## 1. Purpose

Phase 1.27 defines the **Version 1 Notification API Contract**.

Notifications connect business events to the people who need to know about them:

```text id="v0l3q7"
CUSTOMER
    ↓
customer-facing notifications

STAFF
    ↓
operational notifications

ADMIN
    ↓
administrative notifications
```

The central architecture rule is:

> **Notifications communicate business state; they do not create, authorize, or become the source of truth for that state.**

For example:

```text id="9u4c2x"
Order becomes SHIPPED
        ↓
Order is authoritative
        ↓
Notification may be generated
        ↓
Customer sees "Order shipped"
```

Not:

```text
Notification = SHIPPED
```

This distinction is critical for reliability.

---

# 2. Notification Channels in Version 1

The project has already established:

> **Real email delivery is deferred to Phase Group R.**

Therefore Phase 1.27 must distinguish:

```text id="e1v7s0"
Notification record
        ≠
Notification delivery channel
```

Version 1 can support an in-app notification model without requiring email infrastructure.

Potential future channels:

```text id="q6l8y3"
IN_APP
EMAIL
SMS
PUSH
```

Do not automatically introduce all of them into Version 1.

For now:

> **IN_APP is the primary notification channel.**

Email remains deferred to Group R.

---

# 3. Documentation Strategy

Continue the consolidated documentation model.

Update:

```text id="c7n8p2"
docs/api/api-contract.md
docs/api/api-resources.md
docs/api/api-conventions.md
docs/domain/business-rules.md
docs/decisions.md
```

Do not create:

```text id="r8j4k0"
notification-api.md
notifications.md
notification-events.md
notification-decisions.md
```

as permanent project documents.

---

# 4. Authoritative Project Paths

Use:

```text id="q2m9x1"
AGENTS.md
docs/VISION.md
```

These remain authoritative.

---

# 5. Payment Assignment

Payment-related notifications may exist conceptually, for example:

```text id="g5d2s8"
payment successful
payment failed
```

but payment-specific implementation remains in:

**Phase Group H**.

Do not define provider-specific notification events here.

---

# 6. Version 1 Enum Policy

All notification enums are:

> **CLOSED by default.**

If the Notification contract contains enums such as:

```text id="z4b6k7"
recipient type
channel
notification type
read state
```

their Version 1 values must be explicitly documented.

Do not allow arbitrary values.

---

# 7. Dependency Position

The current sequence is:

```text id="c4m8z7"
1.20 Catalog API Contract
       ↓
1.21 Cart API Contract
       ↓
1.22 Checkout API Contract
       ↓
1.23 Order API Contract
       ↓
1.24 Tracking + Fulfillment
       ↓
1.25 Made-to-Order Request
       ↓
1.26 General Enquiry
       ↓
1.27 Notification API Contract
       ↓
1.28 User/Profile API Contract
       ↓
...
```

Notifications depend on the business resources already defined.

They should **not** redefine those resources.

---

# 8. Authoritative Inputs

Read:

```text id="h8n5w1"
AGENTS.md
docs/VISION.md

docs/api/api-contract.md
docs/api/api-resources.md
docs/api/api-conventions.md

docs/domain/business-rules.md
docs/decisions.md
```

Review particularly:

```text id="m6v2d4"
Phase 1.16 Error Contract
Phase 1.17 Authentication
Phase 1.18 Authorization
Phase 1.19 Endpoint Inventory
Phase 1.23 Order
Phase 1.24 Tracking/Fulfillment
Phase 1.25 Request
Phase 1.26 Enquiry
```

---

# 9. Core Notification Principle

A Notification is:

> **A user-facing or operational message derived from an authoritative business event.**

Examples:

```text id="5d8kq0"
Order accepted
Order processing
Ready for pickup
Order shipped
Order delivered
New made-to-order request
New enquiry
```

The Notification itself does not modify the Order/Request/Enquiry.

---

# 10. Notification vs Event

Keep the distinction:

```text id="n1j4y7"
Business Event
→ something happened

Notification
→ communication generated from that event
```

Example:

```text id="3h2x8n"
Order transitioned to SHIPPED
        ↓
Business event
        ↓
Customer notification created
```

Do not make the Notification record itself the business event.

---

# 11. Source of Truth

Authoritative data remains:

```text id="m8k4c1"
Order
Request
Enquiry
Payment
Fulfillment
User
```

Notification is downstream communication.

If a Notification says:

```text "Your order has shipped"
```

but the Order says:

```text PROCESSING
```

the Order wins.

This must never be inverted.

---

# 12. Notification Resource

Define a Notification as an independent API resource.

Conceptually:

```text id="e7z6v4"
Notification
├── id
├── recipient
├── type
├── title/message
├── read state
├── created time
└── optional source reference
```

The exact fields must follow the established data model.

---

# 13. Customer Ownership

A customer Notification belongs to the receiving Customer.

Conceptually:

```text id="y4x8m2"
Customer
   ↓ receives
Notifications
```

Customers may access only their own Notifications.

---

# 14. Staff Notifications

Staff receive operational Notifications.

Examples:

```text id="v5n2q8"
New Order
New Made-to-Order Request
New Enquiry
Operational issue
```

Staff visibility is controlled by operational authorization.

---

# 15. Admin Notifications

Admins may receive administrative notifications such as:

```text id="h4k7y2"
Staff approval event
staff/security issue
critical operational issue
```

Do not automatically send every Staff notification to every Admin unless useful.

---

# 16. Notification Recipient

A Notification must have a clear recipient.

Do not create:

```text id="w1q9s4"
global notification
```

and rely on clients to determine who should see it.

The backend decides recipients.

---

# 17. Customer Notification Privacy

A customer must not be able to request:

```text id="c0x7f3"
another customer's notification
```

by changing a Notification ID.

Object-level authorization is mandatory.

---

# 18. Staff Notification Privacy

Staff should receive only Notifications relevant to their operational scope.

Do not expose:

```text id="s9m2r1"
another Staff member's private notifications
```

unless operational policy explicitly permits shared queues.

---

# 19. Shared Operational Notifications

For Staff, determine whether the system uses:

```text id="f6v4g8"
personal notifications
```

or:

```text id="n8k3d1"
shared operational notifications/queue
```

For a small ecommerce system, a shared operational queue may often be simpler.

Do not build both models unnecessarily.

---

# 20. Recommended Staff Model

For normal operations, consider:

```text id="k4m7a5"
Operational event
    ↓
Staff notification/queue
    ↓
Any authorized Staff can handle it
```

This avoids depending on one Staff member being online.

---

# 21. Notification Types

Create a controlled Version 1 notification taxonomy.

Potential customer types:

```text id="x8t2v6"
ORDER_RECEIVED
ORDER_ACCEPTED
ORDER_PROCESSING
ORDER_READY_FOR_PICKUP
ORDER_SHIPPED
ORDER_DELIVERED
ORDER_COMPLETED
ORDER_CANCELLED
```

Potential operational types:

```text id="r5k7m2"
NEW_ORDER
NEW_MADE_TO_ORDER_REQUEST
NEW_ENQUIRY
```

Only include events actually required by the approved business workflows.

---

# 22. Avoid Notification Type Explosion

Do not create a unique notification type for every wording variation.

For example, avoid:

```text id="a8q3w4"
SOFA_SHIPPED
TABLE_SHIPPED
CHAIR_SHIPPED
```

Use:

```text id="d6k2p7"
ORDER_SHIPPED
```

with Order-specific context.

---

# 23. Closed Notification Types

Because Version 1 is closed:

```text id="j5n8r2"
ORDER_SHIPPED
```

must be one canonical value.

Clients must not receive arbitrary:

```text id="f3v6x8"
order.shipped.v2
shipping_complete
shipped_customer
```

instead.

---

# 24. Notification Payload Context

A Notification may reference its source resource.

For example:

```json id="w1p8f6"
{
  "type": "ORDER_SHIPPED",
  "data": {
    "order_id": "...",
    "order_reference": "OD-*****"
  }
}
```

The exact schema should remain consistent with the global response design.

Do not duplicate an entire Order inside every Notification.

---

# 25. Notification Deep Link

A Notification may contain a safe target/reference allowing the client to navigate to:

```text id="u5c7k2"
Order detail
Order tracking
Request
Enquiry
```

For example:

```text id="j8m2q4"
target_type
target_id
```

or an approved route/reference format.

The target itself must still be authorization-checked.

A notification must not grant access to a private resource.

---

# 26. Notification as a Capability Token

Important rule:

> **A Notification reference is not an authorization credential.**

Example:

```text id="y3q7s1"
Customer receives notification for Order A
```

That does not mean the client may access:

```text id="r8f2m5"
Order B
```

nor does it bypass Order ownership validation.

---

# 27. Read/Unread State

A customer notification should generally support:

```text id="q6y1p3"
UNREAD
READ
```

if this is the chosen model.

Keep the representation simple.

---

# 28. Read State Semantics

The read state describes:

> Has this recipient viewed/acknowledged this notification?

It does **not** mean:

```text id="m9z5x7"
the Order was processed
the payment succeeded
the request was handled
```

Never overload read state with business status.

---

# 29. Mark Notification Read

Define:

```text id="s1k4r8"
PATCH /api/v1/me/notifications/{notification}
```

or a dedicated action:

```text id="g8y2m6"
POST /api/v1/me/notifications/{notification}/read
```

Choose one canonical convention.

For a simple boolean state change, PATCH may be sufficient.

---

# 30. Mark All Read

Evaluate:

```text id="x7c3v5"
POST /api/v1/me/notifications/read-all
```

or equivalent.

This is useful for mobile/app UX.

Do not add it unless the UI actually needs it.

---

# 31. Delete Notification

Evaluate whether customers need to delete notifications.

For Version 1, consider:

> Notifications are retained but can be marked read.

This preserves useful history and simplifies synchronization across website and Flutter.

Do not create DELETE merely because notifications are user-visible.

---

# 32. Recommended Notification Lifecycle

For Version 1:

```text id="p9r6k2"
Created
  ↓
Unread
  ↓
Read
```

No need for a complex workflow.

---

# 33. Notification Creation

Clients must not create their own business Notifications.

Do not allow:

```text id="s7j3x1"
POST /notifications
```

for normal customers.

Notifications are generated by trusted backend events.

---

# 34. Staff Notification Creation

Staff also should not manually create arbitrary customer notifications through the standard Notification resource.

If Staff need to communicate with Customers, that is a separate messaging/communication feature.

---

# 35. Admin Notification Creation

Admin should not create arbitrary system Notifications through a generic endpoint unless a later explicit broadcast feature is approved.

---

# 36. Notification Read Endpoints

Recommended customer endpoint:

```text id="o6t2n8"
GET /api/v1/me/notifications
```

Authentication:

```text Required
```

Authorization:

```text Own notifications
```

---

# 37. Notification Detail

Evaluate whether:

```text id="i4p7q9"
GET /api/v1/me/notifications/{notification}
```

is useful.

It may be unnecessary if the collection representation already contains the required data.

Do not create it automatically.

---

# 38. Notification Collection

The Customer collection should support:

```text id="r5y8k1"
unread filter
pagination
ordering
```

Potential:

```text id="v4n2m6"
/me/notifications?unread=true
```

Use global query conventions.

---

# 39. Notification Sorting

Recommended:

> Newest Notifications first.

Use deterministic ordering.

A stable secondary key may be required for pagination.

---

# 40. Notification Pagination

Notifications should be paginated.

Use the global pagination contract.

Do not create notification-specific pagination metadata.

---

# 41. Staff Notification Queue

If Staff uses a shared operational queue, define:

```text id="h3w8x5"
GET /api/v1/notifications/operations
```

only if this is truly necessary.

Alternatively, operational Notifications can use the same collection with role-based filtering.

Choose one canonical approach.

---

# 42. Do Not Build Two Notification Systems

Avoid:

```text id="p5k8m2"
CustomerNotification
StaffNotification
AdminNotification
```

as three unrelated API resources.

Use one Notification concept with authorization/recipient scope.

---

# 43. Notification Representation by Actor

Customer representation may include:

```text id="m9q2x4"
title
message
type
read status
created time
target reference
```

Staff representation may additionally contain:

```text id="k1v4p8"
operational metadata
```

Admin may receive:

```text id="r7c3y2"
administrative context
```

Do not expose internal metadata unnecessarily.

---

# 44. Notification Message

Notification text should be readable by the recipient.

But machine logic must depend on:

```text id="u8m4j1"
type
```

not the message.

Example:

```text id="b6q2w9"
type = ORDER_SHIPPED

message =
"Your order OD-12345 has been shipped."
```

---

# 45. Dynamic Notification Content

Dynamic values may include:

```text id="g2v8n5"
order reference
product name
pickup status
```

but should come from authoritative backend data.

Do not allow clients to submit arbitrary content and turn it into a system Notification.

---

# 46. Localization

Determine whether notification messages are:

```text id="m4x7v2"
server-generated
```

or:

```text id="r8p1c3"
localized client-side from type/context
```

For a cross-platform system, a useful design is:

```text id="w2j6s9"
type + structured context
```

with frontend-friendly rendering.

Do not make Flutter/Next.js parse English notification messages.

---

# 47. Recommended Notification Contract

Prefer:

```text id="7m5q1h"
type
context
created_at
read_at
```

plus safe display fields.

This allows consistent behavior across:

```text id="x8c3n4"
Next.js
Flutter
Admin
```

---

# 48. Read Timestamp

If read/unread is supported, prefer:

```text id="r4v9k2"
read_at
```

over relying only on:

```text id="f6q2y8"
is_read = true
```

because the timestamp provides useful synchronization/history.

The final representation can contain both if useful, but avoid redundant state.

---

# 49. Recommended Read Model

Consider:

```text id="n5w8j3"
read_at = null
→ unread

read_at = timestamp
→ read
```

This avoids maintaining two sources of truth.

---

# 50. Mark Read Authorization

Only the recipient may mark their own Notification as read.

Staff cannot mark Customer notifications as read through their own Staff credentials.

Admin may have visibility without necessarily changing recipient read state.

---

# 51. Staff Operational Notifications

Determine whether Staff notifications have:

```text id="e5t9m3"
read state
```

or:

```text id="y2c7p4"
acknowledged/handled state
```

These are different concepts.

Do not use `read` to mean `handled`.

---

# 52. Operational Notification vs Task

For Staff:

```text id="p6m8x1"
Notification
→ tells Staff something happened

Task/queue item
→ identifies work that must be completed
```

For Version 1, do not build a separate task-management system unless necessary.

A notification can point Staff toward the operational resource.

---

# 53. Staff Queue Simplicity

Recommended small-business model:

```text id="z4q1y7"
New Order
→ operational notification
→ Staff opens Order
→ Order state determines work
```

The Order remains the source of truth.

---

# 54. Notification Event Sources

Define which approved business events can create Notifications.

### Orders

```text id="n3k7c8"
Order created
Order accepted
Order processing
Ready for pickup
Shipped
Delivered
Completed
Cancelled
```

Only include statuses/events actually approved by Phase 1.23.

### Requests

```text id="v8m2p5"
New Made-to-Order Request
```

### Enquiries

```text id="q9w4r1"
New Enquiry
```

### Payment

```text id="j6x8m3"
Payment event
```

but detailed payment event mapping belongs to Group H.

---

# 55. Customer Notification Event Matrix

Create (explicit V1 — CLOSED, authoritative for `type` registry `§28.4`/`§28.20` and endpoint `§28.11`/`§28.21`):

| Business Event                 | Customer notified? |
| ------------------------------ | -----------------: |
| Order created                  |                Yes |
| Payment confirmed              |                 No |
| Order accepted                 |                Yes |
| Order processing               |                Yes |
| Ready for pickup               |                Yes |
| Shipped                        |                Yes |
| Delivered                      |                Yes |
| Completed                      |                Yes |
| Order cancelled                |                Yes |
| Made-to-Order Request received |                 No |
| Enquiry received               |                 No |

Do not send a notification for every internal event.

---

# 56. Staff Notification Event Matrix

Create:

| Business Event            |               Staff notified? |
| ------------------------- | ----------------------------: |
| New Order                 |                           Yes |
| New Made-to-Order Request |                           Yes |
| New Enquiry               |                           Yes |
| Payment issue             |          According to Group H |
| Delivery issue            |     If operationally required |
| Customer cancellation     | Yes if operationally relevant |

---

# 57. Admin Notification Event Matrix

Potential:

```text id="o1r4t8"
staff approval/security event
critical operational issue
system issue
```

Do not send every order notification to Admin automatically.

---

# 58. Notification Deduplication

A business event must not unintentionally create multiple identical notifications for the same recipient.

Example:

```text id="h7j3n4"
Order shipped
→ one customer
→ one logical notification
```

unless deliberate multiple-channel delivery later requires separate delivery records.

---

# 59. Notification Idempotency

Notification generation should be idempotent at the event-processing boundary.

For example:

```text id="c5m8x2"
Order SHIPPED event processed twice
```

must not create two identical customer notifications accidentally.

Do not implement event infrastructure here.

---

# 60. Notification vs Delivery Record

Keep future extensibility:

```text id="y8r1m6"
Notification
   ↓
Delivery attempt(s)
```

Do not turn Notification into:

```text id="s7x9p2"
SMTP log
```

because Email is later.

---

# 61. In-App Notification Storage

The Notification resource should represent the logical message available in-app.

It does not need to represent every transport attempt.

---

# 62. Email Deferral

When Group R introduces email:

```text id="j4n7p1"
Business event
 ↓
Notification
 ↓
Email delivery
```

The existing Notification contract should remain valid.

Do not make Notification itself depend on email.

---

# 63. Push Notification Future

If Flutter later uses push:

```text id="o5w8r3"
Business event
 ↓
Notification
 ↓
Push delivery
```

Again, Notification remains the logical communication object.

---

# 64. Channel Enumeration

Do not expose:

```text id="d6v2k4"
EMAIL
SMS
PUSH
```

in Version 1 Notification responses unless those delivery channels actually exist.

The API should not claim capabilities that are not implemented.

---

# 65. Notification Preferences

Evaluate whether Customers need:

```text id="c8m3w7"
notification settings
```

such as:

```text order_updates
marketing
```

For Version 1, keep this minimal.

Do not build marketing-notification preferences unless marketing is in scope.

---

# 66. Transactional Notifications

Order/request/enquiry notifications are transactional.

They should not be confused with:

```text id="r7n6k2"
marketing campaigns
promotional messages
```

Do not include marketing systems in this phase.

---

# 67. Customer Opt-Out

Do not automatically let Customers disable critical transactional notifications.

For example:

```text id="q2f6m8"
Order status
```

may be necessary to the customer experience.

Any preference model must distinguish mandatory transactional communication from optional messaging.

---

# 68. Staff Notification Preferences

Do not over-engineer Staff preferences in Version 1.

Operational notifications should remain reliably visible.

---

# 69. Notification Retention

Do not define an aggressive automatic deletion policy unless required.

For a small app, retaining a manageable notification history is simpler.

Retention specifics can be addressed later.

---

# 70. Notification Ordering

Default:

```text id="y8x4n6"
newest first
```

Use:

```text created_at
+
stable ID
```

for deterministic ordering.

---

# 71. Notification Pagination

Use standard pagination.

Recommended:

```text id="c7m5p2"
page
per_page
```

according to Phase 1.12.

Do not invent cursor pagination unless needed.

---

# 72. Unread Count

The UI may need an unread count.

Evaluate whether to expose:

```text id="u3q8m1"
GET /api/v1/me/notifications/unread-count
```

or return:

```text id="k5n2x7"
unread_count
```

in notification collection metadata.

For a small API, prefer using collection metadata if it can be done efficiently.

Do not add both without a reason.

---

# 73. Notification Query Parameters

Potential:

```text id="m8v4r6"
unread=true
page
per_page
```

Use only approved global query conventions.

---

# 74. Staff Notification Filters

Potential:

```text id="n4x7c3"
unread
type
created date
```

Only if operationally useful.

---

# 75. Notification Search

Do not add free-text Notification search in Version 1.

The user generally needs chronological notification history, not a search engine.

---

# 76. Notification Detail

Determine whether a separate detail endpoint is necessary.

If the collection already contains enough information, prefer fewer endpoints.

---

# 77. Notification Response Example

Conceptual:

```json id="v2m8q5"
{
  "data": [
    {
      "id": "...",
      "type": "ORDER_SHIPPED",
      "title": "Order shipped",
      "message": "Your order OD-***** has been shipped.",
      "read_at": null,
      "created_at": "...",
      "target": {
        "type": "ORDER",
        "id": "..."
      }
    }
  ],
  "meta": {
    "unread_count": 3
  }
}
```

This is illustrative.

Use the actual response conventions from Phase 1.13.

---

# 78. Notification Read Request

Conceptual:

```json id="f4k7m1"
{
  "read": true
}
```

If PATCH is selected.

Or use a dedicated action if the project wants stronger action semantics.

Do not support arbitrary notification fields.

---

# 79. Customer Cannot Change Notification Content

The customer may change:

```text id="m2q5p7"
read state
```

but not:

```text id="g7x3n8"
type
title
message
recipient
target
created_at
```

---

# 80. Staff Cannot Change Customer Notification Content

Staff operational access does not give permission to rewrite a customer's notification.

If correction is required, the notification should be regenerated/handled through the source event.

---

# 81. Admin Notification Management

Do not expose generic:

```text id="y3k7p5"
PATCH /notifications/{id}
```

to Admin merely because Admin has high authority.

Admin visibility and mutation are separate decisions.

---

# 82. Source Event Reference

A Notification may store a reference to its source.

Example:

```text id="r4c8m1"
source_type = ORDER
source_id = ...
```

This is useful for traceability.

Do not make source references grant access.

---

# 83. Source Resource Authorization

When a client follows a Notification target:

```text id="p2m7x9"
GET /orders/{order}
```

the normal Order authorization rules still apply.

---

# 84. Notification and Order State

If a customer receives:

```text id="h8m3q2"
ORDER_SHIPPED
```

and opens the Order:

```text id="v5r7k1"
Order state must independently confirm the status.
```

Do not use Notification to populate authoritative Order state.

---

# 85. Notification and Request

If a Staff member receives:

```text id="q8n4y2"
NEW_MADE_TO_ORDER_REQUEST
```

Staff must still fetch the Request resource and pass Request authorization.

The notification does not contain unrestricted Request content.

---

# 86. Notification and Enquiry

Same rule:

```text id="k5x9m7"
NEW_ENQUIRY
→ notification
→ authorized Enquiry access
```

---

# 87. Notification and Payment

Payment events may produce notifications, but Payment status remains authoritative.

Payment implementation is Group H.

---

# 88. Notification Creation Failure

A critical principle:

> Failure to create a notification must not roll back the underlying business transaction unless the business explicitly requires transactional notification creation.

Example:

```text id="m9f4x2"
Order shipped successfully
↓
Notification generation temporarily fails
```

The Order must still remain:

```text SHIPPED
```

Notification delivery is downstream.

---

# 89. Notification Retry

Later infrastructure should be able to retry notification creation/delivery safely.

Do not implement queues/jobs in this phase.

---

# 90. Notification Eventual Consistency

It is acceptable for:

```text id="b4p7m2"
Order state updates immediately
Notification appears shortly afterward
```

provided the system eventually reconciles it.

Do not make the customer-facing business operation dependent on notification delivery.

---

# 91. Notification Ordering vs Event Ordering

If multiple Order events occur quickly:

```text id="r8c3n6"
ACCEPTED
PROCESSING
SHIPPED
```

the notification timeline must not misleadingly reorder them.

Use authoritative event timestamps/order.

---

# 92. Duplicate Event Protection

Later event processing should use event IDs or another idempotency mechanism.

Do not create duplicate notifications when the same source event is delivered twice.

---

# 93. Notification Security Classification

Define:

```text id="f2m6q1"
Customer notifications
→ PRIVATE

Staff operational notifications
→ PRIVATE/INTERNAL

Admin notifications
→ ADMINISTRATIVE
```

Never public.

---

# 94. Notification Cache Policy

Do not public-cache notification responses.

Customer:

```text id="g7n4x8"
/me/notifications
→ private
```

Staff:

```text id="q1r6m9"
operational notifications
→ private/internal
```

---

# 95. Notification Authorization Matrix

Add to:

```text id="e7x4m2"
docs/api/api-contract.md
```

| Operation                             | Anonymous | Customer |                  Staff |                       Admin |
| ------------------------------------- | --------: | -------: | ---------------------: | --------------------------: |
| View own notifications                |        No |      Yes |   Yes where applicable |        Yes where applicable |
| Mark own notification read            |        No |      Yes | Yes for own/read scope |                  Authorized |
| Create notification                   |        No |       No |                     No |                         No* |
| View operational notifications        |        No |       No |                    Yes |                         Yes |
| Manage notification templates         |        No |       No |                     No |         Deferred/authorized |
| Read another customer's notifications |        No |       No |                     No | Only if explicitly approved |

`*` Admin-created/broadcast notifications are intentionally not part of this Version 1 contract unless separately approved.

---

# 96. Customer Notification Permissions

Customers should have a minimal authorization surface:

```text id="5x2m8"
notifications.read_own
notifications.mark_read_own
```

No customer notification creation permission.

---

# 97. Staff Notification Permissions

Potential:

```text id="c7n4v2"
notifications.read_operational
notifications.mark_read_own
```

The exact names must match the authorization vocabulary from Phase 1.18.

---

# 98. Admin Notification Permissions

Admin may have:

```text id="k8r3m5"
notifications.read_operational
```

and administrative visibility where required.

Do not create unrestricted notification mutation permissions.

---

# 99. Notification API Endpoint Inventory

A likely Version 1 set:

```text id="w5j2y7"
NOT-001
GET /api/v1/me/notifications

NOT-002
PATCH /api/v1/me/notifications/{notification}
```

Potential:

```text id="g8m4x1"
NOT-003
POST /api/v1/me/notifications/read-all
```

Only include `NOT-003` if the frontend genuinely needs it.

---

# 100. Staff Operational Endpoint

If operational Staff notifications are modeled as a queue:

```text id="m3x8q6"
NOT-004
GET /api/v1/notifications/operations
```

However, first check whether the existing notification collection can serve this through role-based filtering.

Prefer one resource model.

---

# 101. Notification Detail Endpoint

Do not automatically add:

```text id="x4p7n8"
GET /me/notifications/{notification}
```

unless the UI needs deep-linking to the notification itself.

A collection with embedded detail may be sufficient.

---

# 102. Mark Read Semantics

If PATCH is used:

```json id="j4k8m2"
{
  "read": true
}
```

the backend must derive:

```text recipient
```

from authentication.

The customer cannot change:

```text id="b8m3x5"
recipient
```

or read another user's notification.

---

# 103. Read-All Semantics

If a Read-All operation exists:

> It applies only to the authenticated actor's accessible Notifications.

Do not allow:

```json id="m6q2v1"
{
  "user_id": "another-user"
}
```

---

# 104. Notification Error Contract

Use Phase 1.16.

Potential:

```text id="r7x3m9"
AUTHENTICATION_REQUIRED
RESOURCE_NOT_FOUND
FORBIDDEN
INVALID_VALUE
```

Do not expose another user's Notification existence.

---

# 105. Notification Not Found

For:

```text id="g2m8k6"
GET /me/notifications/{notification}
```

use the same private-resource handling principles as Orders.

A customer's request for another customer's Notification should not reveal the resource.

---

# 106. Notification Validation

For marking read:

```text id="p5v8n3"
read
→ boolean
```

Only.

Do not allow arbitrary notification updates.

---

# 107. Notification Type Validation

If filtering:

```text id="c1x6m4"
?type=ORDER_SHIPPED
```

validate against the closed Version 1 notification-type registry.

---

# 108. Notification Recipient Validation

Recipient is always derived by server-side event logic.

Never accept:

```json id="n7y2q5"
{
  "recipient_user_id": "..."
}
```

from a normal customer notification API.

---

# 109. Notification Target Validation

Targets are server-generated.

Do not allow a customer to construct:

```json id="h5r3m8"
{
  "target": {
    "type": "ORDER",
    "id": "another-order"
  }
}
```

to create an unauthorized navigation/reference.

---

# 110. Notification Data Minimization

A notification should contain only enough contextual data to tell the recipient what happened.

Do not embed:

```text id="z6m1q8"
entire Order
full customer profile
full delivery address
internal notes
payment secrets
```

---

# 111. Notification Message Security

Any dynamic data included in notification text must be safely encoded by the eventual rendering layers.

Do not allow customer-supplied text to become executable HTML/JS through Notifications.

---

# 112. XSS Consideration

Potentially dangerous:

```text id="p4x8m2"
Product name
customer name
request text
enquiry subject
```

may appear in notifications.

Treat all such values as untrusted.

The backend/frontend must render them safely.

---

# 113. Notification and Anonymous Users

Anonymous visitors do not have an in-app Notification account.

Therefore:

```text id="n6r2y8"
anonymous request/enquiry
```

may later trigger:

```text email
```

in Group R, but does not automatically create an in-app Customer Notification because there is no authenticated recipient.

Do not create anonymous persistent notifications without a secure identity model.

---

# 114. Anonymous Request Confirmation

For anonymous Request/Enquiry submission, the API may return immediate submission confirmation.

Do not create a Notification requiring authentication for an anonymous user.

---

# 115. Notification and Customer Registration

If an anonymous visitor later registers, do not automatically attach previous anonymous Requests/Enquiries/Notifications based solely on email matching.

That would create an account-claim vulnerability.

---

# 116. Notification and Account Security

Customer authentication notifications, such as:

```text id="j4x8q2"
password changed
new login
```

may be valuable later.

For Version 1, evaluate them but do not invent a security-event notification system unless required.

---

# 117. Staff Security Notifications

Potential future:

```text id="p8m3x6"
new staff approved
credential issue
```

Do not add unnecessary security notifications to Version 1.

---

# 118. Admin Security Notifications

Critical security events may eventually notify Admin.

This is a later operational/security concern.

---

# 119. Notification Read Synchronization

Next.js and Flutter must see consistent read state.

Example:

```text id="y5r8m2"
Customer reads notification on Flutter
        ↓
server read_at updated
        ↓
website later shows it as read
```

This is why Notification read state must be server-side authoritative.

---

# 120. Client Cannot Keep Read State as Authority

Local device state can optimize UX.

But backend remains authoritative.

Do not let:

```text id="r6x2k9"
Flutter local "read"
```

be the only source of truth.

---

# 121. Multi-Device Notifications

A Customer may use:

```text id="m3q8x1"
website
+
Flutter
```

simultaneously.

Therefore Notification read state should be synchronized centrally.

---

# 122. Staff Shared Queue

If Staff notifications are shared operational items:

```text id="f2m7n8"
one Staff reads
```

does not necessarily mean:

```text all Staff should stop seeing the item
```

This is why a shared queue should not automatically use ordinary personal `read_at`.

If shared operational behavior is required, it may need separate acknowledgement/handled semantics.

Do not conflate the two.

---

# 123. Recommended Small-Business Operational Model

For simplicity:

```text id="k7x4p2"
Customer:
personal notification/read state

Staff:
operational queue
```

This keeps customer notification semantics simple while letting Staff see shared work.

---

# 124. Notification vs Operational Queue

If Staff need:

```text id="n8c2m5"
"new order waiting"
```

the better long-term source of truth is still:

```text Order collection
```

Notification is an alert.

Staff should be able to refresh the Order queue independently.

---

# 125. Notification Failure Must Not Block Operations

If a Staff notification fails:

```text id="q4m9x7"
Order creation still succeeds
```

Staff can still discover the Order through the operational Order queue.

This makes the notification system resilient rather than critical-path fragile.

---

# 126. Notification Failure Must Not Block Customer Checkout

Absolutely:

```text id="t6n3y8"
Checkout
→ Order
```

must not fail merely because an in-app notification cannot be persisted/delivered.

---

# 127. Notification Generation Timing

Notifications may be generated:

```text id="v5r2x9"
synchronously
or
asynchronously
```

depending on later implementation.

This phase should not require one mechanism.

---

# 128. Notification Event Consistency

If asynchronous:

```text id="m7k1q3"
business event
→ durable event/outbox
→ notification
```

may be used later.

Do not implement the Outbox pattern here, but identify the architectural fit.

---

# 129. Outbox Consideration

Because this project contains important state changes:

```text id="x4p7m1"
Order
Payment
Request
Enquiry
```

a later Outbox/event strategy may help guarantee notification generation without losing events.

Document as an implementation consideration.

---

# 130. Notification Contract and Background Jobs

Notification dispatch is a strong future candidate for queued/background processing.

Do not make Laravel controller response depend on completing every notification delivery.

---

# 131. Notification Contract and Email Group R

When Group R introduces email:

```text id="q8m5v2"
Notification
→ Email delivery attempt
```

The API Notification contract should not need to change simply because a second channel becomes available.

---

# 132. Notification Contract and Payment Group H

When Group H introduces payment:

```text id="m3r7y6"
Payment confirmed
→ Order/payment business state
→ customer notification
```

The notification system consumes the resulting business event.

It does not confirm payment itself.

---

# 133. Required Documentation Updates

## `docs/api/api-contract.md`

Add:

```text id="w6x8m1"
## Notification API Contract

### Notification Resource
### Recipients
### Notification Types
### Customer Notifications
### Staff Operational Notifications
### Admin Notifications
### Read/Unread State
### Notification Targets
### Collection
### Pagination
### Filtering
### Event Sources
### Notification Creation Rules
### Privacy
### Caching
### Failure/Retry Principles
### Channel Architecture
### Email Deferral
```

---

## `docs/api/api-resources.md`

Update:

```text id="p7m4x9"
Notification
```

with:

```text id="b5q8c2"
recipient
relationships
representation
read state
target reference
source reference
privacy classification
```

---

## `docs/api/api-conventions.md`

Add reusable conventions for:

```text id="g1k4y7"
notification ownership
read-state semantics
target references
event-derived communication
private caching
machine-readable notification types
```

---

## `docs/domain/business-rules.md`

Confirm:

```text id="n8r3x1"
Notifications communicate business events.
Notifications are not authoritative business state.
Customers receive their own notifications.
Staff receive operational notifications.
Admins receive authorized administrative notifications.
Notification failure does not invalidate the underlying business operation.
Email delivery is deferred to Group R.
```

---

## `docs/decisions.md`

Record decisions such as:

```text id="v5j2x8"
### NOT-001 — Notification Is Downstream of Business State

### NOT-002 — Customers Have Private Notifications

### NOT-003 — Notification Does Not Grant Resource Access

### NOT-004 — Notification Read State Is Recipient-Scoped

### NOT-005 — Notification Is Not the Source of Truth

### NOT-006 — In-App Notifications Are Version 1

### NOT-007 — Email Delivery Is Deferred to Group R

### NOT-008 — Notification Failure Does Not Roll Back Business Transactions
```

Only record decisions actually approved.

---

# 134. Required Endpoint Matrix

Add:

| ID      | Method | Path                                      | Auth     | Actor       | Authorization | Purpose                              | Status |
| ------- | ------ | ----------------------------------------- | -------- | ----------- | ------------- | ------------------------------------ | ------ |
| NOT-001 | GET    | `/api/v1/me/notifications`                | Required | Customer    | Own           | List notifications                   | Required |
| NOT-002 | PATCH  | `/api/v1/me/notifications/{notification}` | Required | Customer    | Own           | Update read state                    | Required |
| NOT-003 | POST   | `/api/v1/me/notifications/read-all`       | Required | Customer    | Own           | Mark all read                        | **DEFERRED** — not required in V1, matching two-endpoint inventory (`NOT-001/002` only) and `api-resources.md §14` mapping |
| NOT-004 | GET    | Operational notification path             | Required | Staff/Admin | Operational   | Staff alerts                         | **DEFERRED** — not required in V1; use `NOT-001` with role-based filtering, matching two-endpoint inventory |

Only `NOT-001` and `NOT-002` are required in V1; `NOT-003`/`NOT-004` are explicitly **DEFERRED**, not required conditional endpoints.

---

# 135. Required Notification Type Registry

Add the following controlled registry as the exact Version 1 allowlist of notification types, recipients, and source events:

| Type                        | Recipient | Source      |
| --------------------------- | --------- | ----------- |
| `ORDER_RECEIVED`            | Customer  | Order       |
| `ORDER_ACCEPTED`            | Customer  | Order       |
| `ORDER_PROCESSING`          | Customer  | Order       |
| `ORDER_READY_FOR_PICKUP`    | Customer  | Fulfillment |
| `ORDER_SHIPPED`             | Customer  | Fulfillment |
| `ORDER_DELIVERED`           | Customer  | Fulfillment |
| `ORDER_COMPLETED`           | Customer  | Order       |
| `ORDER_CANCELLED`           | Customer  | Order       |
| `NEW_ORDER`                 | Staff     | Order       |
| `NEW_MADE_TO_ORDER_REQUEST` | Staff     | Request     |
| `NEW_ENQUIRY`               | Staff     | Enquiry     |

This table is the **exact Version 1 allowlist** — only these types, recipients, and source events are valid in V1. Payment notification types (`PAYMENT_*`) are deferred to Group H and all other unapproved event types are excluded and must be rejected with `422 INVALID_VALUE` (`field: type`). Do not add event types that are not listed.

---

# 136. Required Read-State Contract

Document:

```text id="n6c2x9"
read_at = null
→ unread

read_at != null
→ read
```

if this model is approved.

The server owns the timestamp.

---

# 137. Required Notification Authorization Matrix

| Operation                            | Anonymous | Customer |                Staff |                       Admin |
| ------------------------------------ | --------: | -------: | -------------------: | --------------------------: |
| List own notifications               |        No |      Yes | Yes where applicable |        Yes where applicable |
| Mark own notification read           |        No |      Yes | Yes where applicable |        Yes where applicable |
| Create notification                  |        No |       No |                   No |                         No* |
| View another customer's notification |        No |       No |                   No | Only if explicitly approved |
| Change notification content          |        No |       No |                   No |                         No* |

`*` means not part of the normal Version 1 Notification API.

---

# 138. Required Event-to-Notification Matrix

Create the authoritative mapping:

```text id="o4y8m7"
Order created
→ Customer + Staff where applicable

Order accepted
→ Customer

Order processing
→ Customer if approved

Ready for pickup
→ Customer

Shipped
→ Customer

Delivered
→ Customer

New Request
→ Staff

New Enquiry
→ Staff
```

This helps prevent random notification creation throughout later implementation.

---

# 139. Notification Security Review

Explicitly verify:

```text id="b8x2m5"
Customer A cannot read Customer B notifications
Customer cannot create notification
Customer cannot change recipient
Customer cannot change target
Customer cannot access another customer's Order through notification
Staff cannot read unrelated private customer notifications
Notification cannot expose internal notes
Notification cannot expose credentials
Notification cannot expose provider secrets
```

---

# 140. Notification Reliability Review

Verify:

```text id="q3m8v2"
Order creation does not depend on notification success.
Order state does not depend on notification state.
Payment state does not depend on notification state.
Request creation does not depend on notification delivery.
Enquiry creation does not depend on notification delivery.
Duplicate source event does not create uncontrolled duplicate notifications.
```

---

# 141. Client Compatibility Review

### Next.js

Must support:

```text id="x7m2p4"
notification list
unread state
mark read
deep-link to Order/Request/Enquiry
```

### Flutter

Must support the same contract.

No mobile-specific Notification endpoints.

### Staff/Admin

Must support operational notifications/queue if approved.

---

# 142. API Compatibility

Within Version 1, do not casually change:

```text id="m5v8x1"
notification type
read-state semantics
target semantics
recipient semantics
```

because clients may depend on them.

---

# 143. Closed Enum Compatibility

Adding:

```text id="j2r6m4"
ORDER_SOMETHING_NEW
```

is a contract change.

Review all client behavior before introducing new Version 1 notification types.

---

# 144. Notification Tests

Later automated tests must verify:

### Customer

```text id="z8x3m1"
own notifications visible
other customer notifications invisible
mark own notification read
cannot modify type/message
```

### Staff

```text id="k4q7m2"
operational notification visible
customer private notification not exposed
```

### System behavior

```text id="p5n8x3"
Order state remains correct if notification fails
duplicate event does not create uncontrolled duplicates
notification target still requires normal authorization
```

---

# 145. Example Security Test

A customer receives:

```text id="r2v6k8"
ORDER_SHIPPED
target_order_id = B
```

Customer attempts to change:

```text id="x4m7p9"
target_order_id = A
```

The client must have no such ability.

Target is server-generated.

---

# 146. Example IDOR Test

Customer A knows:

```text id="q7m3x2"
Notification B's identifier
```

and calls:

```text id="s8r4n1"
GET /me/notifications/B
```

The API must deny access without revealing private information.

---

# 147. Example State-Source Test

Database:

```text id="j8k2p4"
Order = PROCESSING
```

Notification:

```text id="f5m9x3"
ORDER_SHIPPED
```

If such a mismatch occurs, the Order remains authoritative.

The implementation must have reconciliation/diagnostic capability later.

---

# 148. Example Retry Test

Business event:

```text id="g3v6m8"
Order SHIPPED
```

is processed twice.

Expected:

```text id="r7x1p5"
not two uncontrolled duplicate notifications
```

---

# 149. Example Multi-Device Test

Customer:

```text id="m8q4y1"
reads on Flutter
```

then:

```text id="p2x7c9"
opens website
```

The same Notification must reflect the server-side read state.

---

# 150. Explicitly Out of Scope

Do NOT:

```text id="v7n4m2"
Implement Laravel notification classes
Create notification database migrations
Implement queues
Implement events/outbox
Implement WebSockets
Implement push notifications
Implement email
Implement SMS
Implement notification templates
Implement marketing campaigns
Implement CRM messaging
Implement chat
Implement staff task management
Implement payment notifications
Build Next.js notification UI
Build Flutter notification UI
Build Staff notification UI
```

---

# 151. Definition of Done

Phase 1.27 is complete when:

1. Notification is defined as a first-class Version 1 resource.
2. Notification is explicitly downstream of authoritative business state.
3. Customer notifications are private and recipient-scoped.
4. Staff notifications are operationally scoped.
5. Admin notifications are administratively scoped.
6. Notification does not grant access to its target resource.
7. Notification read state is clearly defined.
8. Read state is separate from business state.
9. Customer notification retrieval is defined.
10. Customer mark-read behavior is defined.
11. Mark-all behavior is either approved or deliberately deferred.
12. Notification types are defined and closed.
13. Event-to-notification mappings are defined.
14. Notification targets/references are defined.
15. Notification payloads do not duplicate entire business resources unnecessarily.
16. Customer cannot create or alter system Notifications.
17. Staff cannot arbitrarily alter customer Notifications.
18. Anonymous users do not receive persistent in-app notifications without an identity model.
19. Public caching is prohibited for private Notifications.
20. Notification failure does not invalidate the underlying business transaction.
21. Duplicate-event handling is identified.
22. Multi-device read synchronization is defined.
23. Staff shared operational-notification behavior is considered.
24. Payment notifications remain assigned to **Phase Group H**.
25. Real email delivery remains deferred to **Phase Group R**.
26. Next.js and Flutter requirements are covered.
27. Security/IDOR requirements are covered.
28. Consolidated documentation is updated.
29. No implementation code has been written.

---

# 152. STOP CONDITION — Mandatory

After updating:

```text id="c9m2x7"
docs/api/api-contract.md
docs/api/api-resources.md
docs/api/api-conventions.md
docs/domain/business-rules.md
docs/decisions.md
```

and completing the security/reliability/cross-platform review:

**STOP.**

Do not implement Notifications.

Do not create Laravel Notification classes.

Do not implement events/queues/outbox.

Do not implement push/email/SMS.

Do not build notification UIs.

Do not implement payment notifications.

The next phase must be explicitly requested.

## Recommended next phase

# Phase 1.28 — Define User/Profile API Contract

This phase should define the authenticated Customer-facing account contract:

```text id="8m5x2p"
GET own profile
update own profile
customer identity
contact information
account ownership
security-sensitive profile changes
credential-change boundaries
profile/privacy fields
cross-platform consistency
```

while preserving the key rule established in Phase 1.17/1.18:

> **Customers own their accounts. Staff do not control or restrict customer accounts as part of normal ecommerce operations.**
