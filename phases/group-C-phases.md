# Phase 3.13 — Delivery Schema

## Purpose

Implement the persistence model for **Order Delivery operations**.

Delivery is a separate operational entity associated with an Order whose:

```text
fulfillment_type = DELIVERY
```

It must store only information genuinely specific to delivery execution.

The Order remains authoritative for:

* customer ownership
* order reference
* order financials
* fulfillment type
* recipient snapshot
* delivery address snapshot
* overall Order status

Delivery must not become a second Order state machine.

The existing V1 contract defines:

```text
DELIVERY
PROCESSING → SHIPPED → DELIVERED → COMPLETED
```

with `SHIPPED` meaning the Order has left the business and `DELIVERED` meaning delivery has been completed. Tracking is derived from `order_status_history`; it is not GPS/carrier tracking.

This phase establishes the Delivery persistence model only.

Do not implement delivery scheduling, dispatch workflows, courier integrations, live tracking, notifications, or Order transition logic here.

---

# Dependencies

Complete these phases first:

* Phase 3.9 — Orders Schema
* Phase 3.10 — Order Items Snapshot Model
* Phase 3.11 — Order Status History Schema
* Phase 3.12 — Payment Schema

Use the existing `orders` table and its established fulfillment/financial fields.

Do not redesign the Order model.

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
* completed Phase 3.11 — Order Status History Schema
* completed Phase 3.12 — Payment Schema

The V1 API contract is frozen. Do not introduce new public Delivery states, tracking concepts, or API fields that are not already supported by the contract.

---

# 1. Core Design Rule

The `deliveries` table represents the **operational delivery record for a Delivery-type Order**.

It is not:

* a second Order table
* a second payment table
* a second status-history table
* a GPS tracking system
* a courier marketplace
* a carrier integration registry

The authoritative relationship is:

```text
Order
  └── Delivery
```

where:

```text
Order.fulfillment_type = DELIVERY
```

A Pickup Order must not have a Delivery record in normal V1 operation.

---

# 2. One Delivery per Delivery Order

V1 supports one operational Delivery record for each Delivery Order.

Create:

`deliveries`

with a unique `order_id`.

This means:

```text
DELIVERY Order A → exactly one Delivery record
PICKUP Order A   → no Delivery record
```

Do not allow multiple active Delivery records for the same Order in V1.

Do not introduce delivery-attempt history yet.

If future business requirements need multiple delivery attempts, create that as a deliberate future model rather than overloading the V1 Delivery row.

---

# 3. Create `deliveries` Table

Create a Laravel migration for:

`deliveries`

Recommended schema:

| Column                  | Type                                       | Rules                                                     |
| ----------------------- | ------------------------------------------ | --------------------------------------------------------- |
| `id`                    | big integer / Laravel standard primary key | Internal primary key                                      |
| `order_id`              | foreign key                                | Required; references `orders.id`; unique; restrict delete |
| `recipient_name`        | string                                     | Required delivery snapshot                                |
| `recipient_phone`       | string                                     | Required delivery snapshot                                |
| `delivery_address`      | JSON                                       | Required delivery snapshot                                |
| `delivery_instructions` | nullable text/string                       | Optional                                                  |
| `schedule_for`          | nullable timestamp                         | Optional server-controlled delivery appointment           |
| `created_at`            | timestamp                                  | Required                                                  |
| `updated_at`            | timestamp                                  | Required                                                  |

Do not add arbitrary extra delivery fields merely because they are common in larger logistics systems.

The schema must remain appropriate for the project's small-scale V1 architecture.

---

# 4. `order_id`

`deliveries.order_id` belongs to `orders.id`.

Implement:

### Order

`Order hasOne Delivery`

### Delivery

`Delivery belongsTo Order`

The `order_id` must be unique.

This expresses the V1 invariant:

> One Delivery Order has at most one Delivery record.

Do not use `cascadeOnDelete`.

Delivery data participates in a historical commercial workflow and should not disappear automatically because of an accidental or administrative Order deletion.

Use restrictive deletion semantics consistent with the project's historical Order and Payment models.

Normal business APIs do not hard-delete Orders.

---

# 5. Delivery-Type Eligibility

A Delivery record is valid only for:

```text
Order.fulfillment_type = DELIVERY
```

This is a domain invariant.

Do not attempt to enforce it through a redundant `fulfillment_type` column on `deliveries`.

The Delivery model should not contain:

```text
fulfillment_type
```

because the authoritative value already belongs to Order.

At creation time, application/domain logic must verify:

```text
delivery.order.fulfillment_type === DELIVERY
```

before creating the Delivery record.

Do not allow a Pickup Order to silently acquire a Delivery row.

---

# 6. Recipient Snapshot

Store:

* `recipient_name`
* `recipient_phone`

as Delivery-specific historical/operational snapshots.

The Order already stores its own recipient snapshot.

This duplication is intentional only because Delivery is an operational record and must retain the exact recipient data used for delivery execution without requiring joins to a mutable customer profile.

Do not read current User profile information and overwrite these values automatically after Delivery creation.

Later profile changes must not rewrite the delivery recipient.

This follows the same historical-snapshot principle already established for Orders. The Order itself is a historical record and preserves recipient/address information independently of later profile changes.

---

# 7. Delivery Address Snapshot

Store:

`delivery_address`

as structured JSON.

Use the same approved address structure established for Order checkout.

Do not introduce a second incompatible address schema.

The Delivery address must be a snapshot of the address actually associated with the Order delivery.

Do not store:

```text
saved_address_id
```

in Delivery.

Saved address-book functionality remains deferred.

The existing V1 contract explicitly treats the Order's delivery address as a per-order snapshot rather than a saved-address reference.

---

# 8. Address Immutability

Once the Delivery record has been created, its historical recipient/address snapshot must not be silently synchronized from:

* User profile
* customer address book
* future address changes
* current Order profile data

Do not implement automatic profile-to-delivery synchronization.

Do not implement address replacement in this phase.

Any future operational address-correction workflow must be an explicit controlled operation with appropriate authorization and auditability.

---

# 9. Delivery Instructions

Support:

`delivery_instructions`

as optional operational text.

This is intended for practical delivery information, such as:

* access instructions
* building/entrance guidance
* reasonable delivery notes

Rules:

* optional
* bounded in length
* plain text unless the approved API explicitly requires another representation
* server validated
* private
* never interpreted as executable content

Do not treat arbitrary HTML as trusted.

Do not store secrets or credentials in delivery instructions.

Do not automatically expose internal operational notes to customers.

---

# 10. No Generic `notes` Field

Do not create a vague:

```text
notes
```

column.

Use explicit semantic fields.

For V1:

```text
delivery_instructions
```

is sufficient for customer-supplied or delivery-specific instructions.

If future operations require private Staff notes, define that field and access policy explicitly rather than creating an ambiguous general-purpose notes column now.

---

# 11. Scheduled Delivery

Support an optional:

`schedule_for`

field, named:

`schedule_for`

only if the existing project conventions and actual implementation need delivery appointments.

The recommended column name for this phase is:

`schedule_for`

to make the semantics explicit: the intended delivery appointment/time.

However, because the current frozen V1 contract does not establish a customer-facing delivery scheduling workflow, the field must remain:

* nullable
* server-controlled
* operational
* non-authoritative for Order status

If the implementation does not have an approved scheduling requirement yet, omit the column rather than creating speculative functionality.

For the baseline V1 implementation, **do not add `schedule_for` unless the codebase already requires delivery appointment scheduling**.

The Delivery schema must not create an unsupported public feature.

---

# 12. No Delivery Status Column

Do **not** create:

```text
delivery_status
```

in V1.

The project's frozen Order lifecycle already owns:

```text
PROCESSING
SHIPPED
DELIVERED
COMPLETED
```

for Delivery Orders.

The contract explicitly describes Delivery as a fulfillment path while the Order retains the authoritative commercial lifecycle.

Adding a second Delivery status would create unnecessary synchronization problems such as:

```text
orders.status = DELIVERED
deliveries.status = SHIPPED
```

which is exactly the kind of conflicting source of truth this design should prevent.

The later Order transition workflow updates Order status and appends Order Status History.

Delivery remains an operational record associated with that Order.

---

# 13. No Delivery Status History

Do not create:

```text
delivery_status_history
```

in V1.

Use:

`order_status_history`

for the authoritative Order lifecycle timeline.

The frozen tracking contract states that the customer timeline is a filtered view of `order_status_history`.

Do not duplicate the same event stream in Delivery.

---

# 14. No Tracking Number

Do not add:

```text
tracking_number
```

The current V1 design is intentionally not a carrier/logistics platform.

The documented delivery flow specifically excludes carrier `tracking_number`, `tracking_url`, and related external tracking concepts.

Do not anticipate them through unused nullable columns.

---

# 15. No Carrier

Do not add:

```text
carrier
carrier_name
carrier_code
carrier_id
```

There is no approved V1 carrier domain.

If carrier integration becomes necessary later, introduce an explicit provider/integration model with its own security and operational rules.

Do not create speculative external-integration fields now.

---

# 16. No GPS / Live Tracking

Do not add:

* latitude
* longitude
* route
* live location
* driver coordinates
* ETA feed
* geofencing
* vehicle identity
* route history

V1 tracking is a customer-readable Order status timeline, not GPS tracking.

---

# 17. No Driver Assignment

Do not add:

```text
driver_id
assigned_staff_id
delivery_agent_id
```

The frozen contract intentionally keeps tracking lightweight and does not expose assigned staff unless a later business need requires it.

Staff identity is not part of the customer timeline.

Do not create a Driver/DeliveryAgent domain merely to populate this table.

---

# 18. Delivery Fee Remains on Order

Do not move:

* `delivery_fee_status`
* `delivery_fee_amount`
* `total_amount`

from the Order into Delivery.

The Order remains the authoritative commercial record.

The delivery fee is part of Order financials, not operational Delivery metadata.

The frozen model specifically defines delivery-fee finalization as an Order operation before payment.

Therefore:

```text
Order
 ├── subtotal
 ├── delivery_fee
 └── total
```

remains the financial source of truth.

---

# 19. Delivery Address vs Delivery Entity

The Order already contains:

```text
recipient_name
recipient_phone
delivery_address
```

as historical checkout snapshots.

Delivery may contain an operational copy of these exact values because it is the actual fulfillment record.

Rules:

* Order snapshot remains authoritative for historical Order representation
* Delivery snapshot supports delivery operations
* neither is automatically rewritten from the customer's current profile
* no saved-address reference replaces either snapshot

Do not introduce a third address source.

---

# 20. Delivery Creation Timing

This phase does not implement creation workflow, but the eventual workflow must create Delivery only for a Delivery-type Order.

The expected conceptual boundary is:

```text
Checkout
→ Order created
→ fulfillment_type = DELIVERY
→ Delivery operational record created
```

The exact transaction boundary is a later checkout/fulfillment concern.

Do not implement this orchestration in Phase 3.13.

For Pickup Orders, no Delivery row should be created.

---

# 21. Delivery and Order State

Do not update Order status from the Delivery model.

Do not create model observers such as:

```text
Delivery created → Order SHIPPED
Delivery updated → Order DELIVERED
```

Those would bypass the controlled Order state machine.

The eventual workflow must explicitly validate:

```text
actor
+ permission
+ current Order state
+ fulfillment type
+ business preconditions
```

inside a transaction.

Delivery persistence must remain subordinate to that workflow.

---

# 22. Delivery Timestamps

Use:

* `created_at`
* `updated_at`

for the Delivery record.

Do not add:

* `shipped_at`
* `delivered_at`
* `completed_at`

to Delivery in this phase.

Those dates are represented by the Order Status History's `occurred_at` events.

This avoids maintaining parallel lifecycle timestamps.

---

# 23. No Delivery Soft Delete

Do not add soft deletion.

A Delivery is an operational record tied to an Order.

Normal business operation must not delete it.

If future retention/privacy workflows require data deletion or anonymization, they should be explicitly designed and audited rather than introduced through ordinary Delivery CRUD.

---

# 24. Model Design

Create:

`Delivery`

Eloquent model.

Implement:

### Delivery

* `belongsTo(Order::class)`

### Order

* `hasOne(Delivery::class)`

Use explicit casts for:

* `delivery_address`
* timestamps

Keep the model lightweight.

Do not add:

* status-transition methods
* GPS methods
* courier SDK logic
* notification logic
* payment logic
* Order state mutation
* tracking serialization logic

Those belong to later application/API layers.

---

# 25. Address JSON Structure

Use the same structured address representation established by Checkout/Order.

Do not use arbitrary nested JSON.

The server must validate an approved allow-list of address fields.

The Delivery schema must not become a general-purpose JSON blob.

Do not permit:

```json
{
  "anything": "arbitrary",
  "internal_sql": "...",
  "secret": "..."
}
```

or similarly unconstrained structures.

The project's global validation conventions require explicit structures and reject arbitrary nested data.

---

# 26. Security and Privacy

Delivery data is private.

It can contain:

* recipient name
* recipient phone
* delivery address
* private delivery instructions

Therefore:

* never expose Delivery publicly
* never include it in public product/catalog responses
* never CDN-cache it
* use private/no-store behavior for protected delivery/order responses
* authorize access before serialization

The existing contract explicitly classifies delivery address as private and limits visibility to the owning customer and authorized Staff/Admin.

Staff access is operational, not customer ownership.

---

# 27. Customer Access Boundary

A Customer may see Delivery information only through their own Order context.

Do not build a public:

```text
GET /deliveries/{delivery}
```

resource merely because a table exists.

The Delivery entity is an internal domain resource for the Order workflow.

Customer-facing representation remains controlled by the existing Order/tracking contract.

This prevents direct object-reference enumeration from becoming an authorization bypass.

---

# 28. Staff Access Boundary

Staff may access Delivery information only through explicitly authorized operational Order workflows.

Do not assume:

```text
staff = unrestricted access
```

Use the established permission model.

Staff should receive only the delivery information necessary for order fulfillment.

Admin has broader operational access but must still follow authorization, auditability, and data-minimization rules.

---

# 29. Mass Assignment

Delivery fields are not generally customer-controlled model fields.

A later checkout/action workflow may accept explicit business input such as delivery instructions, but it must transform:

```text
validated input
→ DTO/command
→ domain workflow
→ trusted Delivery persistence
```

Never:

```text
$request->all()
→ Delivery::create()
```

The project's conventions explicitly prohibit uncontrolled mass assignment.

Client input must not control:

* `order_id`
* Delivery ownership
* internal timestamps
* Order relationships
* Order status
* financial fields

---

# 30. Validation Rules

At the domain level:

## Order

Must exist.

## Fulfillment

Order must be:

```text
DELIVERY
```

## Recipient

`recipient_name` and `recipient_phone` must satisfy the same approved validation bounds used by the Order checkout snapshot.

## Address

`delivery_address` must satisfy the established structured address schema.

## Instructions

`delivery_instructions` must respect the project's text length and content requirements.

Do not invent alternate Delivery-specific formats for fields that already have an established global definition.

---

# 31. Concurrency

Delivery creation/modification may later participate in critical checkout/fulfillment transactions.

Do not implement concurrent delivery workflow in this phase.

However, the schema must prevent duplicate Delivery records for the same Order with:

```text
UNIQUE(order_id)
```

The later workflow must perform authorization, state validation, and persistence atomically where the operation changes critical Order fulfillment state.

The project already marks order fulfillment operations as concurrency-sensitive.

---

# 32. Idempotency

Do not create a Delivery-specific idempotency mechanism in this schema phase.

The existing Order fulfillment actions use `Idempotency-Key`.

Retry semantics belong to the later action/application workflow.

The unique `order_id` constraint provides an additional database integrity guard against accidental duplicate Delivery rows.

Do not rely on that unique constraint alone as the API idempotency mechanism.

---

# 33. Indexes and Constraints

At minimum:

### `deliveries`

* primary key on `id`
* unique index on `order_id`

If recipient/search operations later require additional indexes, add them based on actual query requirements.

Do not index:

* full JSON delivery address
* delivery instructions
* recipient phone

merely because those fields exist.

Protected operational queries should use the Order relationship as their normal access path.

---

# 34. Foreign-Key Delete Behavior

Use restrictive semantics for:

`deliveries.order_id → orders.id`

Do not use cascade deletion.

The Delivery record is part of a historical operational workflow.

Orders are not normally hard-deleted, and Delivery must not be silently removed through a parent delete cascade.

If an exceptional administrative data-retention workflow is introduced later, it must be explicit and audited.

---

# 35. Historical Integrity

Delivery must preserve the delivery details associated with the actual Order.

After creation, later changes to:

* User name
* User phone
* saved address book
* customer profile

must not silently rewrite:

```text
recipient_name
recipient_phone
delivery_address
```

This is the same historical-integrity principle used for Order snapshots and financial data.

Do not register model observers for automatic synchronization.

---

# 36. No Payment Fields

Do not add:

* payment_id
* payment_status
* amount_paid
* payment_reference
* provider_transaction_id

to Delivery.

Payment is a separate domain.

Delivery may be operationally blocked until the Order is paid according to later workflows, but Payment remains the source of payment information.

---

# 37. No Inventory Fields

Do not add:

* reserved_quantity
* stock_quantity
* inventory_id
* warehouse_location
* allocation

to Delivery.

Inventory remains a separate domain.

Fulfillment does not become an inventory table.

---

# 38. No Product Fields

Do not add:

* product_id
* variant_id
* SKU
* product name

to Delivery.

Delivery belongs to an Order, and Order Items already hold the historical purchased items.

Do not create another snapshot layer inside Delivery.

---

# 39. No Delivery Address ID

Do not add:

```text
address_id
saved_address_id
customer_address_id
```

The V1 model explicitly treats the Order delivery address as a snapshot and defers an address book.

Delivery needs the actual snapshot, not a mutable customer-address reference.

---

# 40. Maintainability Requirements

For all new or refactored functions:

* cognitive complexity must be **15 or lower**
* no function may have more than **3 return statements**
* meaningful repeated string literals should be centralized using constants or enums where appropriate

Do not create a giant global constants class.

Prefer domain-local enums/constants.

Do not suppress static-analysis findings or raise analyzer thresholds.

Keep the Delivery model, migration, factories, and tests small and cohesive.

---

# 41. Tests

Add automated tests for the schema and Delivery invariants.

## Migration/schema tests

Verify:

* `deliveries` table exists
* primary key exists
* `order_id` exists
* `order_id` is required
* `order_id` is unique
* recipient fields exist
* address JSON exists
* delivery instructions are nullable
* timestamps exist
* no delivery status field exists
* no carrier/tracking-number field exists

## Relationship tests

Verify:

* Order → Delivery
* Delivery → Order

## Fulfillment eligibility tests

Verify:

* Delivery can be associated with a `DELIVERY` Order
* a Pickup Order cannot create a valid Delivery through domain/application validation

Do not enforce this through a duplicated `fulfillment_type` database column.

## One-to-one constraint tests

Verify that the database rejects multiple Delivery rows for the same Order.

## Snapshot tests

Create a Delivery with:

* recipient name
* recipient phone
* address

Change the related User profile and verify the Delivery snapshot remains unchanged.

## Address validation tests

Verify that valid approved address structures persist correctly.

Verify invalid/unexpected address structures are rejected by the appropriate validation/domain layer.

## Privacy-field tests

Verify no fields exist for:

* carrier credentials
* GPS
* tracking number
* payment secrets
* inventory quantities
* driver credentials

## Relationship deletion tests

Verify that deleting a User does not delete Delivery data indirectly.

Verify the Delivery-to-Order relationship uses restrictive deletion semantics.

## Order separation tests

Verify Delivery does not become a second source of:

* Order status
* Order total
* Delivery fee
* payment status

---

# 42. Factories

Create or extend:

`DeliveryFactory`

Support:

* a valid Delivery Order
* recipient snapshot
* structured address
* optional delivery instructions

Factory defaults must create:

```text
Order.fulfillment_type = DELIVERY
```

Do not make a Delivery factory silently create Pickup Orders.

Do not populate carrier/GPS/tracking data that the V1 design does not support.

---

# 43. Documentation Updates

Update the appropriate authoritative documentation if necessary.

Record:

* Delivery is a separate operational entity
* one Delivery per Delivery Order in V1
* Pickup Orders do not have Delivery records
* Order remains authoritative for fulfillment status
* Order Status History remains authoritative for tracking
* Delivery stores recipient/address operational snapshots
* V1 has no carrier/GPS/tracking-number model
* delivery fee remains on Order

Do not create a permanent phase-specific document solely for these facts.

If a future requirement conflicts with this design, record it as a deliberate architectural decision rather than silently changing the model.

---

# 44. Security and Data Integrity Review

Before completion, verify:

* Delivery cannot be created for a Pickup Order through normal domain validation
* `order_id` cannot be client-chosen to bypass ownership
* Delivery data is private
* customer access remains through authorized Order context
* Staff access is permission-based
* recipient/address snapshots are not silently rewritten
* no payment secrets are stored
* no GPS/carrier data was introduced
* no duplicate Delivery can exist for one Order
* Order remains the authoritative lifecycle source
* Order Status History remains the authoritative tracking event source
* no generic Delivery CRUD path bypasses Order authorization

---

# Definition of Done

Phase 3.13 is complete when:

* `deliveries` migration exists
* one Delivery can belong to one Order
* `order_id` is unique
* restrictive Order deletion behavior is configured
* recipient name and phone snapshots are persisted
* structured delivery address snapshot is persisted
* delivery instructions are optionally supported
* Pickup Orders cannot receive a valid Delivery record through the domain layer
* no duplicate Delivery exists for one Order
* Delivery has no independent status machine
* Delivery has no GPS/carrier/tracking-number fields
* Order remains authoritative for fulfillment state
* Order Status History remains authoritative for tracking
* Delivery model relationships are implemented
* factories support valid Delivery fixtures
* schema, relationship, eligibility, uniqueness, snapshot, privacy, and deletion tests pass
* maintainability requirements are satisfied
* no scheduling, courier integration, tracking, or Order transition workflow has leaked into this phase

# Out of Scope

Do not implement in Phase 3.13:

* delivery status state machine
* Order status transitions
* shipping/dispatch actions
* delivery completion workflow
* courier/provider integration
* carrier API integration
* driver model
* driver assignment
* GPS/live location
* tracking number
* tracking URL
* delivery route management
* ETA service
* delivery attempts/history
* proof of delivery
* signature capture
* delivery photos
* delivery notifications
* payment logic
* inventory allocation
* customer delivery API
* staff delivery API
* scheduling UI
* automated delivery scheduling jobs

# STOP CONDITION

Stop after the Delivery persistence model, relationships, one-to-one constraint, delivery eligibility rules, tests, factories, and migration verification are complete.

Do not implement delivery execution or Order status transitions yet.

The next phase is:

**Phase 3.14 — Furniture Request Schema**
