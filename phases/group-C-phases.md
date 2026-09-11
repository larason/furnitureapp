# Phase 3.11 — Order Status History Schema

## Purpose

Implement the database model for **historical Order status events**.

`order_status_history` is an append-only event history for the Order lifecycle.

It must preserve:

* the Order associated with the event
* the status reached
* who or what caused the transition
* when the transition occurred
* optional customer-visible context
* optional internal operational context

The history will later power customer tracking and staff operational views. The tracking timeline is a filtered presentation of this history, not a separate source of truth. The authoritative contract requires chronological ordering by `occurred_at ASC, id ASC`, opaque event identity, and immutable historical events.

This phase establishes persistence only.

Do not implement the Order state machine, transition endpoints, payment workflow, delivery workflow, notifications, or tracking APIs in this phase.

---

# Dependencies

Complete these phases first:

* Phase 3.9 — Orders Schema
* Phase 3.10 — Order Items Snapshot Model

Use the existing:

* `orders`
* `users`
* Order status definitions
* existing authorization/audit conventions

Do not redesign the Order schema in this phase.

---

# Authoritative Inputs

Treat these as authoritative:

* `docs/VISION.md`
* `docs/domain/business-rules.md`
* `docs/api/api-contract.md`
* `docs/api/api-resources.md`
* `docs/api/api-conventions.md`
* `AGENTS.md`
* completed Phase 3.9 — Orders Schema
* completed Phase 3.10 — Order Items Snapshot Model

The frozen Order lifecycle is:

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

These are closed V1 values. Not every Order uses every status. Pickup and Delivery follow different branches.
Do not introduce additional Order statuses in this phase.

---

# 1. Core Design Rule

`order_status_history` is an **append-only historical event table**.

Every authoritative Order status transition must eventually produce a corresponding history record.

The history must never be treated as an editable list.

Do not implement:

* editing a history event
* deleting an individual history event
* rewriting a previous status
* synchronizing old events with the current Order
* automatically recalculating past events
* replacing history with a single JSON field on `orders`

The existing contract explicitly prohibits customer or staff rewriting historical events. Corrections, when ever needed, require an explicit controlled administrative workflow with auditability.

This phase does not implement that correction workflow.

---

# 2. Create `order_status_history` Table

Create a Laravel migration for:

`order_status_history`

Recommended columns:

| Column          | Type                                       | Rules                                                                       |
| --------------- | ------------------------------------------ | --------------------------------------------------------------------------- |
| `id`            | big integer / Laravel standard primary key | Stable event identifier                                                     |
| `order_id`      | foreign key                                | Required; references `orders.id`; cascade delete                            |
| `from_status`   | string                                     | Nullable for initial event                                                  |
| `to_status`     | string                                     | Required; closed Order status                                               |
| `actor_type`    | string                                     | Required; identifies actor source                                           |
| `actor_id`      | nullable foreign key                       | Nullable for system-generated events; references `users.id`; null on delete |
| `customer_note` | nullable text/string                       | Optional customer-visible note                                              |
| `internal_note` | nullable text/string                       | Optional operational note                                                   |
| `occurred_at`   | timestamp                                  | Required; event time                                                        |
| `created_at`    | timestamp                                  | Required                                                                    |
| `updated_at`    | timestamp                                  | Do not create                                                               |

Use the project's normal Laravel/MySQL timestamp conventions.

Use a single event creation timestamp rather than maintaining mutable update timestamps.

`order_status_history` is historical data, so an event must not have a normal `updated_at` lifecycle.

---

# 3. Event Identity

The database primary key remains internal persistence identity.

The API later exposes the event through an **opaque event ID** such as:

```text
evt_...
```

Do not expose the raw auto-increment database ID directly as the public event identifier.

The API contract explicitly requires stable opaque event identity derived from the underlying history record.

Do not add a second randomly generated public-ID system to this migration unless the existing project implementation already requires one.

Keep the mapping deterministic and centralized when the serializer is implemented later.

---

# 4. Order Relationship

`order_status_history.order_id` belongs to `orders.id`.

Implement:

### Order

`Order hasMany OrderStatusHistory`

### OrderStatusHistory

`OrderStatusHistory belongsTo Order`

An Order Status History row cannot exist without its Order.

Deleting an Order may cascade to its history because the history is part of the Order aggregate and Orders themselves are not intended to be hard-deleted during normal operation.

Do not configure Product, Variant, Payment, or Delivery relationships in this phase.

---

# 5. Status Representation

Use the existing Order status values exactly:

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

Store status values using a string-compatible representation consistent with the existing Order schema.

Do not create a new independent status vocabulary for history.

The `to_status` value must always be one of the existing closed Order statuses.

`from_status` is nullable because the initial event may represent creation of the Order rather than a transition from a previous state.

For example:

```text
from_status = null
to_status   = PENDING_PAYMENT
```

Later transitions can be represented as:

```text
from_status = PENDING_PAYMENT
to_status   = PAID
```

or:

```text
from_status = PROCESSING
to_status   = SHIPPED
```

The actual transition validity remains a domain/state-machine responsibility for a later phase.

---

# 6. Do Not Implement the State Machine Here

This phase must not decide whether one status may transition to another.

Do not implement transition rules such as:

```text
PAID → ACCEPTED
ACCEPTED → PROCESSING
PROCESSING → READY_FOR_PICKUP
PROCESSING → SHIPPED
```

and do not implement forbidden transition handling here.

The existing contract requires every transition to validate:

* current state
* requested transition
* actor authorization
* business preconditions
* fulfillment type

atomically.

That belongs to the later Order action/state-machine implementation.

This phase only defines where the resulting event is stored.

---

# 7. `from_status`

`from_status` records the status that immediately preceded the event.

Rules:

* nullable for the initial Order history event
* otherwise must represent the authoritative prior Order status
* server-controlled
* never accepted from an ordinary client request
* never updated after the event is created

Do not infer `from_status` during historical reads by looking at the previous row.

Store it explicitly so each event retains its own historical transition context.

---

# 8. `to_status`

`to_status` is the status reached by the event.

Rules:

* required
* one of the closed V1 Order statuses
* server-generated from an authorized domain action
* immutable after creation

Do not allow:

```http
PATCH /orders/{order}
{
  "status": "SHIPPED"
}
```

or any equivalent generic status write.

The frozen API contract requires explicit action-based transitions and treats status as server authoritative.

---

# 9. Actor Model

A history event may be caused by:

* an authenticated Customer
* Staff
* Admin
* the system/backend

Use:

```text
actor_type
actor_id
```

rather than assuming every event has a human User actor.

## `actor_type`

Use a closed internal set:

```text
CUSTOMER
STAFF
ADMIN
SYSTEM
```

Centralize these values in an appropriate enum/value object/constant location.

Do not scatter raw actor-type string literals through the codebase.

## `actor_id`

When the event is caused by an authenticated User:

* store that User's ID
* never accept actor identity from client input

When the event is system-generated:

```text
actor_type = SYSTEM
actor_id   = null
```

Deleting a User must not destroy historical status events.

Therefore `actor_id` uses `nullOnDelete`.

The server derives actor identity from the authenticated execution context or trusted system workflow.

The existing audit conventions explicitly require server-derived actors and prohibit client-provided actor identity.

---

# 10. Actor Privacy

Do not make Staff/Admin identity part of the customer tracking representation merely because the history stores an actor.

Customer tracking later exposes a customer-friendly timeline rather than operational Staff identity.

The contract distinguishes:

* customer timeline information
* operational actor information

and explicitly states that Staff identity is not shown to customers unless required.

Therefore the persistence model may retain actor information for authorized operational use, while later serializers decide whether it is visible.

Do not duplicate Staff names, phone numbers, or profiles into history.

`actor_id` is sufficient for identity traceability.

---

# 11. Customer-Visible Notes

Support an optional `customer_note`.

This represents information that may later be intentionally exposed to the customer as part of the Order's tracking experience.

Examples may include an operational message associated with a transition.

Rules:

* optional
* server-controlled
* never implicitly public merely because it exists
* explicit allow-list required during serialization

Do not use `customer_note` as a substitute for the canonical tracking `label`.

The frozen tracking representation is:

```text
id
status
occurred_at
label
```

The human-readable `label` is a customer-facing presentation value and should be derived from the controlled status/transition semantics rather than stored as arbitrary duplicated text in this schema.

---

# 12. Internal Notes

Support an optional `internal_note`.

This is operational information intended only for authorized Staff/Admin contexts.

Rules:

* never expose to customer tracking
* never include in public responses
* never automatically serialize
* server-controlled
* subject to the project's reasonable maximum text length

The existing contract explicitly separates customer-visible notes from internal Staff notes and prohibits internal notes from appearing in customer tracking.

Do not use one generic `note` field if doing so would make privacy boundaries ambiguous.

---

# 13. Timestamp Semantics

Use `occurred_at` as the authoritative event timestamp.

It represents when the status event occurred according to backend-controlled execution.

Requirements:

* server-generated
* never supplied by the normal client request
* stored with timezone-safe database semantics
* serialized later as ISO 8601 / RFC 3339 UTC with `Z`

The tracking contract requires deterministic chronological ordering using:

```text
occurred_at ASC, id ASC
```

and requires UTC `Z` timestamps.

Do not use client timestamps for status history.

Do not create a second mutable `status_changed_at` field.

---

# 14. `created_at` vs `occurred_at`

Keep `occurred_at` because it is the domain timestamp consumed by tracking.

Keep `created_at` because it is useful persistence metadata.

They are normally expected to be extremely close in this V1 architecture, but they represent different concepts:

* `occurred_at` — when the Order status event occurred
* `created_at` — when the history record was persisted

Do not expose both automatically through the API.

The tracking API later uses `occurred_at` as its timeline timestamp.

---

# 15. No `updated_at`

Do not add `updated_at`.

Status history is append-only.

An event must not enter an ordinary update lifecycle.

If a future controlled correction workflow is ever introduced, it must be explicit and auditable rather than silently relying on generic model updates.

---

# 16. Ordering and Indexes

Add indexes needed for the primary history access pattern.

At minimum:

* foreign key/index on `order_id`
* composite index on `(order_id, occurred_at, id)`

The composite index supports the required deterministic timeline query:

```text
WHERE order_id = ?
ORDER BY occurred_at ASC, id ASC
```

Do not rely solely on `id` ordering.

Do not introduce pagination-specific indexes yet; V1 tracking is intentionally lightweight and not paginated unless history grows beyond the current assumption.

---

# 17. Duplicate Events

Do not add a simplistic unique constraint such as:

```text
(order_id, to_status)
```

A status may potentially need controlled historical representation more than once in future audited correction scenarios, and the event identity itself distinguishes records.

More importantly, duplicate transition protection is a workflow/idempotency concern, not a schema uniqueness problem.

The existing contract requires idempotency for critical Order actions so retries do not create duplicate business effects or duplicate status events.

That logic belongs to the later transition implementation.

---

# 18. Initial History Event

The Order creation workflow will eventually need to establish an initial history event representing:

```text
from_status = null
to_status   = PENDING_PAYMENT
```

Do not implement that workflow here.

Do, however, ensure the schema can represent it naturally.

Do not use a fake status such as:

```text
CREATED
NEW
INITIAL
```

because those are not part of the frozen Order status enum.

---

# 19. Cancellation History

Cancellation is represented as an Order status event:

```text
to_status = CANCELLED
```

The Order itself remains readable.

Do not create a separate cancellation-history table.

Do not add a `cancelled_at` field to `order_status_history` specifically for cancellation.

Do not implement customer cancellation behavior in this phase.

The existing rules require the cancellation workflow to preserve the Order and use backend time for the 20-minute eligibility window.

---

# 20. Fulfillment Awareness

The history table does not need a duplicated `fulfillment_type` column.

The Order already owns:

```text
fulfillment_type = PICKUP | DELIVERY
```

Later tracking logic filters or interprets the history in the context of the Order's fulfillment type.

The contract defines:

### Pickup

```text
PAID
→ ACCEPTED
→ PROCESSING
→ READY_FOR_PICKUP
→ COMPLETED
```

### Delivery

```text
PAID
→ ACCEPTED
→ PROCESSING
→ SHIPPED
→ DELIVERED
→ COMPLETED
```

Not every status is valid for every fulfillment branch.

Do not duplicate this branch information into every history row.

---

# 21. No GPS / Carrier Fields

Do not add:

* latitude
* longitude
* GPS history
* carrier
* tracking number
* tracking URL
* delivery route
* vehicle data
* live location
* WebSocket metadata

V1 tracking is a status timeline, not a logistics platform. `SHIPPED` means the order has left the business; `DELIVERED` represents completed delivery.

---

# 22. Audit vs Status History

Do not turn `order_status_history` into the general system audit-log table.

The status history answers:

> What Order status events occurred?

The separate audit mechanism answers broader privileged-operation questions such as:

* actor
* role
* action
* resource
* previous state
* resulting state
* request ID

The project conventions explicitly define a broader audit model for privileged state changes.

A status transition may later create both:

1. an Order Status History event
2. a broader Audit event

Do not merge those responsibilities in Phase 3.11.

---

# 23. Model Design

Create the corresponding:

`OrderStatusHistory`

Eloquent model.

Implement:

* `belongsTo(Order::class)`
* `belongsTo(User::class, 'actor_id')` as a nullable actor relationship

Use explicit casts for:

* `occurred_at`
* `created_at`

Use an enum/value representation for controlled `to_status`, `from_status`, and `actor_type` where consistent with the project's existing Laravel conventions.

Do not introduce a generic "history base model."

Do not add heavy transition logic to the model.

The model represents persisted history; the later Order transition service/action will own state-change orchestration.

---

# 24. Append-Only Model Behavior

Do not provide ordinary application methods such as:

```text
updateStatusHistory()
editHistory()
deleteHistory()
```

Do not register model observers that rewrite history.

Do not register catalog or Order observers that silently mutate existing history.

If the project uses model-level protections for immutable records, they may be used, but do not build an elaborate generic immutability framework for this single table.

The important invariant is that normal business flows only append.

---

# 25. Mass Assignment and Client Authority

All history fields are server-controlled.

Never accept from the customer:

* `order_id`
* `from_status`
* `to_status`
* `actor_type`
* `actor_id`
* `occurred_at`

Normal clients may not directly create Order Status History records.

A later Order action will create the history event as part of the trusted state-transition transaction.

Reject arbitrary history creation through generic CRUD APIs.

---

# 26. Transaction Boundary

This phase does not implement transitions, but the schema must be designed for the later transaction boundary.

A future critical Order transition must atomically:

1. load the current Order state
2. authorize the actor
3. validate the transition
4. update the Order
5. append the corresponding history event

The project's contract requires state validation and state mutation inside one transaction for concurrency-critical transitions.

Do not implement that transaction in Phase 3.11.

---

# 27. Maintainability Requirements

For all new or refactored functions:

* cognitive complexity must be **15 or lower**
* no function may have more than **3 return statements**
* meaningful repeated string literals should be centralized using constants or enums where appropriate

Do not create a giant global constant class merely to satisfy the rule.

Prefer concepts close to their domain.

Do not suppress static-analysis warnings or raise analyzer thresholds.

Keep migrations, models, factories, and tests small and cohesive.

---

# 28. Tests

Add automated tests for the schema and persistence invariants introduced by this phase.

## Migration/schema tests

Verify:

* `order_status_history` table exists
* primary key exists
* `order_id` is required
* `from_status` is nullable
* `to_status` is required
* `actor_type` is required
* `actor_id` is nullable
* `customer_note` is nullable
* `internal_note` is nullable
* `occurred_at` is required
* `created_at` exists
* `updated_at` does not exist
* foreign keys exist
* expected indexes exist

## Relationship tests

Verify:

* Order → Order Status History
* Order Status History → Order
* Order Status History → User actor

## Delete behavior tests

Verify:

* deleting an Order cascades to its history
* deleting an actor User nulls `actor_id`
* deleting an actor User does not delete the status history event

## Initial-event test

Verify that the schema can persist:

```text
from_status = null
to_status = PENDING_PAYMENT
actor_type = SYSTEM
actor_id = null
```

No fake initial status should be needed.

## Transition-event persistence test

Verify that a normal history record can persist:

```text
from_status = PROCESSING
to_status = SHIPPED
```

with a valid actor.

Do not yet test whether that transition is allowed by the state machine.

That belongs to the later Order workflow phase.

## Immutability tests

Verify that normal application behavior does not expose generic update/delete operations for historical events.

At minimum, verify the project's intended append-only model convention.

Do not build a large authorization test suite for future APIs here.

## Ordering test

Create multiple history events with deterministic `occurred_at` values, including two events with the same timestamp.

Verify that the intended query ordering is:

```text
occurred_at ASC
id ASC
```

This is required for deterministic customer timeline rendering.

## Privacy-field tests

Verify that:

* internal notes can exist without requiring customer notes
* actor identity is persisted separately from customer-facing presentation
* no Staff name/profile fields are duplicated into history

---

# 29. Factories

Add or extend factories so tests can create:

* an Order with a status-history event
* an initial `PENDING_PAYMENT` event
* a transition event
* a system-generated event
* a user-generated event
* an event with a customer-visible note
* an event with an internal note

Factory defaults must use valid closed statuses and valid actor relationships.

Do not seed random status histories into production-like seed data unless required by the development environment.

---

# 30. Documentation Updates

Update the appropriate authoritative documentation only where needed.

Ensure the Order Status History rules are explicitly represented:

* append-only
* server-generated
* immutable in normal operation
* chronological tracking order
* status values reuse the closed Order status vocabulary
* customer timeline is a filtered presentation of history
* internal actor/note data is not automatically customer-visible

Do not create a permanent phase-specific markdown file merely to document this implementation.

If the existing documentation conflicts with the schema, record the decision rather than silently changing the frozen contract.

---

# 31. Security and Data Integrity Review

Before completion, verify:

* clients cannot directly control history
* actor identity is always server-derived
* raw database IDs are not treated as public event identifiers
* internal notes are not designated as customer-visible by default
* historical events are not editable through generic CRUD behavior
* historical events are not individually deleted
* deleting a User cannot destroy an Order's status history
* deleting an Order removes its owned history consistently
* timestamps are server-controlled
* no GPS/carrier/logistics fields were introduced
* status values exactly reuse the closed V1 Order status vocabulary

---

# Definition of Done

Phase 3.11 is complete when:

* `order_status_history` migration exists
* each event belongs to an Order
* `from_status` and `to_status` model the historical transition
* the initial `null → PENDING_PAYMENT` event is representable
* actor source is represented without requiring a User for system events
* actor identity is server-side and nullable for system events
* customer and internal notes are explicitly separated
* `occurred_at` provides the domain event timestamp
* no `updated_at` exists
* history is append-only by design
* Order deletion cascades to history
* User deletion nulls `actor_id` without deleting history
* indexes support `order_id + occurred_at + id`
* Eloquent relationships are implemented
* factories support valid history fixtures
* migration, relationships, deletion, immutability, and ordering tests pass
* maintainability constraints are satisfied
* no state-transition workflow has leaked into this phase

# Out of Scope

Do not implement in Phase 3.11:

* Order state machine
* transition validation
* `accept`, `process`, `ready-for-pickup`, `ship`, `deliver`, or `complete` actions
* customer cancellation workflow
* delivery-fee finalization
* payment status workflow
* payment webhooks
* inventory mutation
* delivery records
* tracking endpoints
* customer order endpoints
* Staff order endpoints
* notification generation
* email/SMS/push delivery
* general audit-log implementation
* history correction workflow
* GPS/carrier tracking

# STOP CONDITION

Stop after the Order Status History persistence model, relationships, append-only design, tests, factories, and migration verification are complete.

Do not implement the Order transition state machine yet.

The next phase is:

**Phase 3.12 — Payment Schema**
