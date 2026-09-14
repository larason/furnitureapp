# Phase 3.16 — Notification Schema

## Purpose

Implement the persistence model for **transactional/system Notifications**.

Notifications exist to inform users about important events concerning their:

* Orders
* Payments
* Delivery, when the Order uses Delivery
* approved system actions associated with their commerce activity

Notifications are **not** an email-marketing, advertising, promotional, campaign, or customer-segmentation system.

The notification domain must remain focused on operational/customer-service information generated from authoritative business events.

Examples:

```text
Order received
Order accepted
Order being processed
Order ready for pickup
Order shipped
Order delivered
Order completed
Order cancelled

Payment successful
Payment failed
Payment requires action
Payment expired

Delivery-related order update
```

The exact Payment notification types will be defined with the payment contract in Group H. Do not invent public `PAYMENT_*` enum values in Phase 3.16.

---

# 1. Notification vs Marketing — Explicit Boundary

The application must maintain a strict conceptual separation:

### Transactional/System Notification

Triggered because something happened to the customer's transaction or requested service.

Examples:

```text
ORDER_ACCEPTED
ORDER_SHIPPED
ORDER_DELIVERED
Payment successful (exact type deferred to Group H)
Payment failed (exact type deferred to Group H)
```

### Marketing/Promotional Communication

Examples:

```text
20% OFF THIS WEEK
NEW SOFAS AVAILABLE
CHRISTMAS SALE
SPECIAL CUSTOMER OFFER
```

Marketing/promotional communications are **not part of the Notification model created here**.

Do not add:

* campaign
* promotion
* coupon
* marketing_segment
* promotional_opt_in
* advertising
* marketing_campaign_id

to `notifications`.

Future marketing communication, if ever approved, must be a separate domain with its own consent/preferences, audience selection, delivery, suppression, and compliance rules.

This phase creates no such functionality.

---

# 2. Core Design Rule

A Notification is a **downstream communication record derived from authoritative business state**.

The Notification is never the source of truth.

Authoritative sources include:

* Order
* Payment
* Delivery/Fulfillment
* other explicitly approved business events

The existing contract states that if a Notification says an Order is shipped while the Order says it is still processing, the authoritative Order state wins.

Therefore:

```text
Business Event
      ↓
Notification
```

not:

```text
Notification
      ↓
Order state
```

Do not allow clients to manufacture business notifications.

---

# 3. Create `notifications` Table

Create a Laravel migration for:

`notifications`

Recommended schema:

| Column              | Type                               | Rules                                                                               |
| ------------------- | ---------------------------------- | ----------------------------------------------------------------------------------- |
| `id`                | big integer / standard primary key | Internal primary key                                                                |
| `recipient_user_id` | foreign key                        | Required; references users.id; restrict/null behavior according to retention policy |
| `type`              | string                             | Required; controlled notification type                                              |
| `title`             | string                             | Required; server-generated                                                          |
| `message`           | text                               | Required; server-generated                                                          |
| `target`            | JSON nullable                      | Optional structured navigation target                                               |
| `source_type`       | string nullable                    | Optional authoritative source type                                                  |
| `source_id`         | string nullable                    | Optional source identifier                                                          |
| `read_at`           | timestamp nullable                 | Read marker                                                                         |
| `created_at`        | timestamp                          | Required                                                                            |
| `updated_at`        | timestamp                          | Required                                                                            |

Keep the structure intentionally small.

Do not store complete Order, Payment, User, Delivery, or Product objects inside a Notification.

---

# 4. Recipient Ownership

Use:

`recipient_user_id`

as the notification owner.

It must always be server-derived.

For a Customer:

```text
authenticated customer
        ↓
recipient_user_id
```

Never accept:

```json
{
  "recipient_user_id": "another-user"
}
```

from the client.

The existing contract explicitly requires recipient identity to be server-derived and customer notifications to be scoped to the authenticated recipient.

---

# 5. No Anonymous In-App Notifications

Do not support:

```text
recipient_user_id = null
```

for ordinary in-app Notifications.

A Guest may submit Requests and Enquiries, but there is no authenticated recipient account for normal in-app notification ownership.

The existing notification contract explicitly states that anonymous in-app notifications are not supported.

Do not create:

* guest_notification_token
* email_as_recipient
* phone_as_recipient
* anonymous notification inbox

in this phase.

Guest communication delivery belongs to future explicitly approved channels.

---

# 6. Staff/Admin Notifications

The schema must be capable of supporting operational Staff/Admin notifications without turning notifications into a global unrestricted queue.

The existing contract permits operational notification access under explicit permission, while customer notifications remain recipient-scoped.

Do not add:

```text
is_staff_notification
is_admin_notification
```

booleans.

The recipient remains a User, while authorization determines the allowed operational scope.

Do not implement Staff/Admin notification workflows in this phase unless already required by the existing API contract.

---

# 7. Notification Type

Store:

`type`

as the machine-readable notification type.

The frontend must use `type` for behavior, not parse the English `message`.

The existing contract explicitly requires machine-readable types and controlled notification messages.

For the currently frozen V1 Order-related registry, supported types include:

```text
ORDER_RECEIVED
ORDER_ACCEPTED
ORDER_PROCESSING
ORDER_READY_FOR_PICKUP
ORDER_SHIPPED
ORDER_DELIVERED
ORDER_COMPLETED
ORDER_CANCELLED
```

It also contains existing operational Request/Enquiry types:

```text
NEW_MADE_TO_ORDER_REQUEST
NEW_ENQUIRY
NEW_ORDER
```

These existing values must not be removed or casually renamed.

---

# 8. Payment Notification Preparation

Payment notifications are required by the application's business goal, but exact `PAYMENT_*` public notification types were intentionally deferred to Group H in the frozen contract.

Therefore:

* do not hard-code unapproved `PAYMENT_*` values into the frozen API contract
* do not invent customer-facing Payment notification types in this phase
* make the database/model capable of storing controlled future payment notification types
* Group H must define the exact Payment notification registry before those values become externally observable

This avoids creating a database/API mismatch.

---

# 9. Delivery Notification Scope

Delivery-related notifications are applicable only when relevant to the Order's fulfillment path.

For example:

### Pickup

Potential notifications:

```text
ORDER_READY_FOR_PICKUP
ORDER_COMPLETED
```

### Delivery

Potential notifications:

```text
ORDER_SHIPPED
ORDER_DELIVERED
ORDER_COMPLETED
```

The notification itself should reference the authoritative Order/event rather than duplicating a second Delivery lifecycle.

The existing contract defines Pickup and Delivery as different fulfillment branches and derives tracking from `order_status_history`.

Do not add a second `DELIVERY_STATUS` state machine here.

---

# 10. `title`

`title` is server-generated presentation text.

It should be generated from:

* controlled notification type
* safe authoritative context

Example:

```text
Your order is ready
```

Do not allow clients to submit arbitrary notification titles.

Do not treat `title` as machine-readable business state.

Frontend logic must use `type`.

---

# 11. `message`

`message` is server-generated human-readable notification content.

Example:

```text
Your order OD-12345 is ready for pickup.
```

It may contain safe business context such as:

* Order reference
* approved display data

It must not include:

* passwords
* payment secrets
* internal notes
* authentication tokens
* provider credentials
* complete private records

The contract explicitly requires only minimal safe context in notification messages.

---

# 12. XSS and Message Safety

Notification titles/messages are generated by the server but may contain dynamic data.

Ensure dynamic values are safely encoded/escaped according to the output context.

Do not treat Order references or other dynamic fields as trusted HTML.

Do not allow:

```text
<script>
```

or equivalent executable content to reach notification presentation.

Do not permit customer-submitted HTML to become notification markup.

---

# 13. Target Structure

Support an optional structured:

`target`

field.

Recommended conceptual shape:

```json
{
  "type": "ORDER",
  "id": "..."
}
```

This lets clients deep-link to the relevant business resource.

The existing contract defines target references as server-generated and explicitly says they are **not capability tokens**.

Do not allow the client to submit:

```json
{
  "target": {
    "type": "ORDER",
    "id": "another-customers-order"
  }
}
```

to create or authorize a notification.

---

# 14. Target Is Not Authorization

Even if a Notification contains:

```text
target.type = ORDER
target.id = ...
```

the target must not grant access to that Order.

When a customer follows a notification:

```text
Notification
    ↓
Target Order
    ↓
Normal Order authorization
```

must still occur.

The existing contract explicitly states that target references do not grant access to the referenced resource.

---

# 15. Source Traceability

Support optional:

* `source_type`
* `source_id`

to trace a Notification back to the authoritative source event/entity.

Example:

```text
source_type = ORDER_STATUS_HISTORY
source_id   = event identifier
```

or later:

```text
source_type = PAYMENT
source_id   = payment identifier
```

The source values are internal/server-generated.

Do not accept arbitrary client-supplied source identities.

---

# 16. Source vs Target

Keep these concepts distinct:

### Source

> What authoritative business record/event caused this notification?

### Target

> What resource should the client navigate to?

Example:

```text
source:
  ORDER_STATUS_HISTORY / evt_xxx

target:
  ORDER / ord_xxx
```

Do not assume they must always be the same entity.

---

# 17. Notification Deduplication

The same source event must not produce duplicate logical notifications for the same recipient.

The frozen contract explicitly requires notification deduplication/idempotency at the processing boundary.

Do not use:

```text
title + message
```

as a duplicate detector.

Do not use timestamps as the only duplicate key.

Use the authoritative event/source identity in the later notification-generation workflow.

---

# 18. Durable Uniqueness Strategy

When a Notification corresponds one-to-one with a specific source event for a recipient, the later implementation should be able to enforce a durable uniqueness boundary equivalent to:

```text
recipient + source_type + source_id + type
```

However, do not assume every future notification must use exactly that combination.

Some system notifications may be generated without a single persisted source event.

Therefore:

* schema must support source references
* source-based notification generation must use a durable idempotency strategy
* do not create a universal uniqueness rule that blocks legitimate system notifications

The exact uniqueness strategy can be finalized when the notification-generation workflow is implemented.

---

# 19. Read State

Use a single field:

`read_at`

as the source of truth.

Semantics:

```text
read_at = null
→ unread

read_at = timestamp
→ read
```

Do not store both:

```text
is_read
read_at
```

because that creates two competing sources of truth.

The existing contract explicitly defines `read_at` as the canonical state and `is_read` as a derived value.

---

# 20. Read State Is Not Business State

Do not interpret:

```text
read_at != null
```

as:

```text
Order completed
Payment succeeded
Enquiry handled
Delivery completed
```

Read state means only:

> The recipient has marked the notification as read.

The business entity remains authoritative.

The contract explicitly prohibits overloading notification read state with domain state.

---

# 21. `read_at` Authority

`read_at` is server-controlled.

A customer later may request:

```text
mark notification as read
```

but the server determines which notification belongs to that customer.

The client must not directly submit:

```text
read_at = arbitrary timestamp
```

as authoritative data.

Later API semantics will use the approved `read: true` operation, with the server setting the timestamp.

Do not implement the endpoint in this phase.

---

# 22. Notification Ordering

The primary customer inbox order should be:

```text
created_at DESC, id ASC
```

This provides newest-first behavior with deterministic tie-breaking.

Use an index supporting that access pattern.

Do not make notifications dependent on arbitrary client sorting.

---

# 23. Indexes

At minimum add:

### Recipient access

```text
recipient_user_id
```

### Inbox ordering

```text
(recipient_user_id, created_at)
```

### Unread queries

An appropriate index strategy for:

```text
recipient_user_id
read_at
created_at
```

may be used if the actual database/query plan benefits from it.

### Source lookup

Indexes on:

```text
(source_type, source_id)
```

are useful for deduplication/reconciliation.

Do not create an index on every field.

---

# 24. Notification Privacy

Notifications are private.

They must never be:

* public catalog data
* public SEO data
* CDN-cacheable
* available through an unauthenticated listing
* globally queryable by arbitrary `recipient_user_id`

The existing contract requires private/no-store customer notification access.

The server must authorize the recipient before returning notification data.

---

# 25. Notification Ownership and IDOR

Customer A must never retrieve Customer B's Notification.

Do not trust:

```text
recipient_user_id
```

from:

* query parameters
* body
* URL
* hidden client state

Use authenticated self-context.

The existing contract explicitly requires recipient-scoped access and 404 masking for cross-user access.

---

# 26. No Public Notification Creation

Do not implement generic:

```text
POST /notifications
```

for customers, Staff, or Admin.

Notifications are system-generated.

The contract explicitly prohibits normal client notification creation.

Do not allow a client to choose:

* recipient
* type
* title
* message
* target
* source

for a normal Notification.

---

# 27. No Notification Editing

Do not expose generic Notification editing.

Customers must not modify:

* type
* title
* message
* recipient
* target
* source
* created_at

The supported customer mutation is limited to the notification read state later.

The existing contract explicitly restricts the mark-read operation to the `read` value.

---

# 28. No Notification Deletion Workflow

Do not introduce customer deletion semantics in Phase 3.16 unless the frozen contract explicitly requires them.

Notification retention/deletion is a separate lifecycle decision.

Do not make `DELETE /notifications/{notification}` a default capability merely because the database exists.

A later retention strategy may include archival or cleanup, but that should be deliberately designed.

---

# 29. No Marketing Preferences

Do not add fields such as:

```text
marketing_opt_in
promotional_enabled
newsletter_enabled
campaign_preferences
```

to `notifications`.

Notification read state is not marketing consent.

If marketing is introduced later, it requires a separate preference/consent domain.

---

# 30. No Email Delivery Fields

Do not add:

```text
email_sent
email_sent_at
email_error
email_delivery_status
```

to the logical Notification record.

A Notification represents the logical business communication.

Future channel delivery should be modeled separately.

The existing contract explicitly describes:

```text
Notification → Delivery attempt(s)
```

as the appropriate future model for multi-channel delivery.

Group R will handle actual notification delivery.

---

# 31. No SMS/Push Delivery Fields

Similarly do not add:

```text
push_sent
sms_sent
device_token
fcm_token
```

to the Notification table.

A device token is authentication/delivery infrastructure, not Notification domain state.

Push/email/SMS channel implementation belongs later.

---

# 32. IN_APP as the Primary V1 Notification

The existing notification architecture defines **IN_APP as the primary notification mechanism** for the initial design.

Therefore Phase 3.16 should create the logical Notification entity without coupling it to:

* FCM
* email provider
* SMS provider
* webhook delivery

Those integrations belong to Group R.

Do not add a provider SDK dependency to the notification model.

---

# 33. Order Notifications

The schema must support the existing Order notification types:

```text
ORDER_RECEIVED
ORDER_ACCEPTED
ORDER_PROCESSING
ORDER_READY_FOR_PICKUP
ORDER_SHIPPED
ORDER_DELIVERED
ORDER_COMPLETED
ORDER_CANCELLED
NEW_ORDER
```

The customer's Order-related notifications are generated downstream from authoritative Order events.

The existing contract explicitly establishes this event-derived model.

For a delivery Order, delivery-related information is represented through the applicable Order notification types such as `ORDER_SHIPPED` and `ORDER_DELIVERED`.

Do not invent duplicate types such as:

```text
DELIVERY_SHIPPED
DELIVERY_DELIVERED
```

unless the frozen contract is intentionally changed.

---

# 34. Request/Enquiry Notification Compatibility

The frozen registry already contains:

```text
NEW_MADE_TO_ORDER_REQUEST
NEW_ENQUIRY
```

and:

```text
NEW_ORDER
```

These are operational notification types.

Do not delete them from the model simply because the primary focus of this phase is Order/Payment/Delivery.

They should remain supported by the data model because they are part of the existing V1 contract.

---

# 35. Payment Notifications and Group H Boundary

Payment notifications require special treatment because Payment integration is the security-sensitive boundary.

Group H must establish:

* exact Payment notification type names
* when they are generated
* which payment events are authoritative
* what payment information may appear in the message
* what information remains private
* duplicate event behavior

Phase 3.16 must not preempt those decisions.

The schema should remain generic enough to support future controlled `PAYMENT_*` types without adding provider-specific notification structures.

---

# 36. Delivery Notifications and Fulfillment Boundary

Delivery notifications should derive from authoritative fulfillment events.

For example:

```text
PROCESSING
    ↓
SHIPPED
    ↓
DELIVERED
    ↓
COMPLETED
```

The authoritative Order Status History records those transitions.

The Notification is downstream communication.

Do not make the Delivery row itself responsible for creating or owning Notification state.

Do not add notification foreign keys to Delivery.

---

# 37. Failure Isolation

Core commerce operations must not fail merely because Notification persistence fails.

For example:

```text
Order transition → SHIPPED
```

must remain successfully `SHIPPED` even if notification generation is temporarily unavailable.

The existing contract explicitly requires this failure isolation and permits eventual consistency.

Do not put notification persistence in a transaction boundary in a way that makes business-state success depend on notification success.

The later outbox/background approach can address reliable delivery.

---

# 38. Eventual Consistency

Allow:

```text
Business state changes immediately
        ↓
Notification appears shortly afterward
```

Do not require Notification existence before:

* Order state update
* payment state update
* delivery transition

This is particularly important for reliability.

The Notification domain is downstream communication, not business-state authority.

---

# 39. Target Data Minimization

Keep target references small.

Preferred:

```json
{
  "type": "ORDER",
  "id": "..."
}
```

Do not embed:

```text
entire Order
entire Customer profile
payment history
delivery address
Staff profile
internal notes
```

inside a Notification.

The existing contract explicitly requires lightweight notifications and minimal target context.

---

# 40. Notification Title/Message Data Minimization

A message may include:

```text
order_reference
safe display information
```

but must not expose:

* full delivery address unless explicitly necessary
* payment provider details
* provider transaction identifiers
* internal Staff notes
* private audit information
* internal database identifiers
* secrets

The customer should follow the notification into the normal authorized resource rather than receiving an entire resource inside the notification.

---

# 41. Model Design

Create:

`Notification`

Eloquent model.

Recommended relationships:

```text
Notification belongsTo User as recipient
```

Do not create generic relationships to every possible source entity.

The `source_type/source_id` pair is intentionally generic and can be resolved by the application layer when needed.

Avoid Laravel polymorphic relations unless the implementation genuinely requires them.

Do not introduce a giant polymorphic abstraction simply because several domains can generate Notifications.

---

# 42. Notification ID

The database primary key may remain internal.

If the API later exposes Notification IDs, follow the established opaque-ID policy.

Do not make the numeric auto-increment ID an authorization mechanism.

A customer must still be authorized against `recipient_user_id`.

---

# 43. Status Enum Centralization

Use an appropriate enum/value object/constant strategy for notification types.

Do not scatter strings such as:

```text
ORDER_SHIPPED
ORDER_DELIVERED
ORDER_CANCELLED
```

throughout controllers/services.

At the same time, do not create one enormous global string constant class.

Keep the Notification type registry close to the Notification domain and align it with the frozen API contract.

---

# 44. Mass Assignment

Notification fields are server-controlled.

Never:

```text
$request->all() → Notification::create()
```

Never accept arbitrary client-provided:

* recipient
* type
* title
* message
* source
* target

The global API conventions require:

```text
validated input
→ DTO/command
→ domain workflow
→ persistence
```

and explicitly prohibit uncontrolled mass assignment.

---

# 45. Read Mutation Security

When the future customer endpoint marks a Notification as read:

1. authenticate user
2. resolve notification
3. verify `recipient_user_id = authenticated principal`
4. update only `read_at`
5. do not update any business notification fields

Customer A must not be able to mark Customer B's notification as read.

Staff credentials must not mark Customer notifications as read.

The existing contract explicitly defines this ownership behavior.

---

# 46. Query Strategy

Customer notification retrieval should always start from the authorized recipient:

```text
WHERE recipient_user_id = authenticated_user
```

not:

```text
GET all notifications
→ filter in frontend
```

The API must query only the authorized dataset.

This follows the project's general authorization-aware query requirement.

---

# 47. Notification Counts

Do not add a persisted:

```text
unread_count
```

column.

Unread count is derived from:

```text
read_at IS NULL
```

for the authorized recipient.

A cached count can be introduced later as an optimization if actual performance requires it, but it must not become a second source of truth.

---

# 48. No `is_read` Column

Do not create:

```text
is_read
```

in the database.

The canonical representation is:

```text
read_at = null
```

for unread and a server timestamp for read.

The existing contract explicitly rejects two competing read-state fields.

---

# 49. Notification Retention

Do not implement a hard-coded automatic retention/deletion period in Phase 3.16.

The project has not established a V1 notification retention policy.

Do not silently delete old notifications after:

```text
30 days
60 days
90 days
```

without an explicit decision.

Retention can be addressed during production operations/data-retention planning.

---

# 50. Security and Privacy Review

Before completion verify:

* recipient identity is server-derived
* guest users do not receive in-app notifications
* customer notifications are recipient-scoped
* notification reads are private
* notification data is not public-cacheable
* notification source/target IDs do not grant resource access
* notification messages contain only safe minimal context
* internal notes and secrets are excluded
* clients cannot create arbitrary notifications
* clients cannot change Notification type/message/recipient
* `read_at` is the single read-state source
* Marketing/Promotional communication is not represented
* Payment provider secrets are not represented
* no FCM/email/SMS provider dependency exists in the persistence model

---

# 51. Tests

Add automated tests for the schema and notification invariants.

## Migration/schema tests

Verify:

* `notifications` table exists
* primary key exists
* `recipient_user_id` is required
* `type` is required
* `title` is required
* `message` is required
* `target` is nullable
* `source_type` is nullable
* `source_id` is nullable
* `read_at` is nullable
* timestamps exist

Verify that the table does **not** contain:

* marketing fields
* promotional fields
* email-delivery state
* SMS-delivery state
* push-token fields
* password/token fields
* payment secrets

## Recipient tests

Verify:

* notification belongs to a User
* recipient cannot be null
* recipient identity is represented separately from message content

## Read-state tests

Verify:

```text
read_at = null → unread
read_at != null → read
```

Verify no second persisted `is_read` source exists.

## Ordering tests

Verify customer notification queries support:

```text
created_at DESC, id ASC
```

deterministic ordering.

## Target tests

Verify:

* target may be null
* valid structured target can be persisted
* arbitrary unvalidated target structure is rejected by the application/domain layer

## Source tests

Verify:

* source fields may be null
* source traceability can be persisted
* source identity is not treated as user authorization

## Privacy tests

Verify customer-safe representation excludes:

* internal data
* secrets
* provider credentials
* unrelated complete domain objects

## Notification-type tests

Verify currently approved V1 notification types can be represented.

Do not add unapproved Payment notification values merely to satisfy this test.

Create an explicit test ensuring future `PAYMENT_*` registry additions remain a deliberate Group H contract decision.

## Deduplication tests

Verify the notification infrastructure can identify the same source event deterministically.

Do not implement a universal unique constraint that incorrectly rejects unrelated notifications.

---

# 52. Factories

Create:

`NotificationFactory`

Support fixtures for:

* Order notification
* operational notification
* unread notification
* read notification
* target-linked notification
* source-linked notification

Use existing approved Notification types.

Do not seed promotional messages.

Do not generate marketing-style fake notifications in normal seed data.

---

# 53. Documentation Updates

Update the authoritative documentation where necessary to make the boundary explicit:

> Notifications in the V1 Notification domain are transactional/system communications related to business activity such as Orders, Payments, and applicable Delivery/fulfillment events. They are not marketing or promotional communications.

Also preserve:

* recipient-scoped ownership
* server-derived recipient
* in-app-first architecture
* private/no-store access
* `read_at` as canonical read state
* event-derived communication
* target references are not authorization
* failure isolation
* notification deduplication
* closed type registry

Do not create a separate marketing-preferences design inside this phase.

---

# 54. Maintainability Requirements

For all new or refactored functions:

* cognitive complexity must be **15 or lower**
* no function may have more than **3 return statements**
* meaningful repeated string literals should be centralized using constants or enums where appropriate

Do not create a giant global constants class.

Prefer Notification-specific enums/value objects.

Do not suppress static-analysis findings or increase thresholds.

Keep migrations, models, factories, and tests small and cohesive.

---

# 55. Definition of Done

Phase 3.16 is complete when:

* `notifications` migration exists
* notifications are recipient-scoped to authenticated Users
* `recipient_user_id` is server-derived
* notifications are private
* `type`, `title`, and `message` are server-controlled
* `target` supports safe structured navigation references
* `source_type/source_id` support business-event traceability
* `read_at` is the single read-state source
* notifications support deterministic newest-first retrieval
* current approved Order notification types are supported
* the schema is structurally ready for future controlled Payment notification types
* Delivery-related notifications can be represented through existing Order lifecycle notification types
* no duplicate Delivery state machine is introduced
* no marketing/promotional fields are introduced
* no email/SMS/push provider logic is embedded in the model
* no notification can become an authorization token
* Eloquent relationships are implemented
* factories support valid notification fixtures
* schema, ownership, read-state, ordering, target, privacy, and deduplication-support tests pass
* maintainability requirements are satisfied
* no business-state transition depends on notification persistence

# Out of Scope

Do not implement in Phase 3.16:

* Notification API endpoints
* customer notification inbox API
* Staff notification inbox API
* mark-as-read endpoint
* FCM integration
* push notifications
* email notifications
* SMS notifications
* WhatsApp notifications
* notification delivery attempts
* notification queues/jobs
* outbox implementation
* retry workers
* notification preferences
* marketing preferences
* promotional campaigns
* newsletters
* advertising
* customer segmentation
* payment notification type definitions
* payment notification generation
* Order notification generation
* Delivery notification generation
* notification templates system
* localization system
* notification analytics
* notification retention jobs

# STOP CONDITION

Stop after the Notification persistence model, relationships, constraints, factories, tests, and migration verification are complete.

Do not implement actual notification generation or delivery yet.

**Important:** Keep Payment notification types deferred until Group H defines the exact payment-event and public notification contract. The schema must support them structurally without silently expanding the frozen V1 notification enum.

The next phase is:

**Phase 3.17 — Foreign Keys / Indexes / Constraints Review**
