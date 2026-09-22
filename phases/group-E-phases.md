# Phase 5.9 — Inventory Mutation Rules

## Purpose

Implement the controlled inventory adjustment action for V1.

Canonical target after contract reconciliation:

```text
INV-003
POST /api/v1/inventory/{inventory}/adjust
```

This phase introduces the **business mutation rules** for changing physical inventory quantity.

It must preserve the existing Group C inventory model:

```text
Product
→ ProductVariant
→ ProductStock
```

and the Phase 5.8 read model:

```text
one Inventory resource
=
one ProductStock row
=
one Variant at one warehouse/location
```

Do not implement checkout reservation or the full overselling/concurrency strategy yet.

Those remain Phase 5.10.

---

# 1. Critical Boundary

Inventory mutation means changing:

```text
ProductStock.quantity
```

through one controlled action.

It does not mean arbitrary mutation of:

```text
reserved_quantity
available_quantity
warehouse_location
product_variant_id
product_id
```

The only accepted client intent is:

```text
quantity_delta
reason
```

---

# 2. Read Authoritative Repository State First

Before changing code, inspect:

```text
AGENTS.md
docs/VISION.md
docs/api/api-contract.md
docs/api/api-resources.md
docs/api/api-conventions.md
docs/api/openapi.yaml
docs/domain/business-rules.md
docs/decisions.md
```

Then inspect current implementations from:

```text
Phase 5.7 — Product availability
Phase 5.8 — Inventory read model
```

and:

```text
ProductStock
InventoryController
InventoryResource
inventory policies/gates
idempotency infrastructure
audit infrastructure
rate limiter definitions
```

Use existing project abstractions where they exist.

Do not create duplicates.

---

# 3. Canonical Route Reconciliation

Current documentation has drift:

```text
/api/v1/inventory/{product}/adjust
```

versus:

```text
/api/v1/inventory/{inventory}/adjust
```

Phase 5.8 established `{inventory}` as the Inventory/ProductStock resource identifier.

Therefore Phase 5.9 should standardize the mutation route as:

```text
POST /api/v1/inventory/{inventory}/adjust
```

unless a newer authoritative repository decision explicitly supersedes Phase 5.8.

---

# 4. Why `{inventory}` Is Preferred

A Product may have:

```text
multiple Variants
×
multiple warehouse locations
```

Therefore:

```text
{product}
```

does not identify one quantity row.

An Inventory ID does.

Do not force the request body to invent Variant/location targeting when the resource ID already identifies both.

---

# 5. Update Stale Documentation

If the runtime is standardized on:

```text
/inventory/{inventory}/adjust
```

update:

```text
api-contract.md
api-resources.md
api-conventions.md
openapi.yaml
decisions.md
```

only where needed to eliminate route drift.

Do not leave both routes active.

---

# 6. Do Not Add Alias Route

Do not keep both:

```text
/inventory/{product}/adjust
/inventory/{inventory}/adjust
```

as aliases.

One mutation path only.

Duplicate privileged paths increase attack surface and future maintenance risk.

---

# 7. Authentication

INV-003 requires authentication.

Anonymous request:

```text
401 AUTHENTICATION_REQUIRED
```

No guest inventory mutation.

---

# 8. Authorization

Require:

```text
inventory.manage
```

through the existing policy/gate layer.

Allowed operational actors:

```text
STAFF
ADMIN
```

only when granted the permission.

---

# 9. Role Alone Is Not Authority

Do not implement:

```php
if ($user->isStaff()) {
    allow();
}
```

Permission remains authoritative.

Admin should also pass through the established permission system.

---

# 10. CUSTOMER Must Never Mutate Inventory

Authenticated CUSTOMER:

```text
POST /inventory/{inventory}/adjust
```

must fail authorization.

No customer inventory-control path exists.

---

# 11. Request Body

Accept exactly:

```json
{
  "quantity_delta": 10,
  "reason": "STOCK_RECEIPT"
}
```

No other fields.

---

# 12. Strict FormRequest

Use a dedicated request such as:

```text
AdjustInventoryRequest
```

or the established project equivalent.

Use:

```php
$request->validated()
```

Never:

```php
$request->all()
```

---

# 13. Reject Unknown Fields

The request schema is:

```text
additionalProperties: false
```

Reject attempts such as:

```json
{
  "quantity_delta": 10,
  "quantity": 999
}
```

or:

```json
{
  "quantity_delta": 10,
  "reserved_quantity": 0
}
```

with the canonical validation error.

---

# 14. Quantity Delta

`quantity_delta` must be:

```text
integer
required
non-zero
```

unless the latest frozen contract explicitly permits zero.

Preferred V1 mutation semantics:

```text
0
→ INVALID_VALUE
```

because an adjustment that changes nothing is not a meaningful privileged action.

Document this clarification if the current contract is silent.

---

# 15. Positive Delta

Positive delta means:

```text
increase physical quantity
```

Example:

```text
current quantity = 10
delta = +4

new quantity = 14
```

---

# 16. Negative Delta

Negative delta means:

```text
decrease physical quantity
```

Example:

```text
current quantity = 10
delta = -3

new quantity = 7
```

Negative values are permitted only when the resulting state remains valid.

---

# 17. Server Calculates New Quantity

Canonical formula:

```text
new_quantity
=
current_quantity + quantity_delta
```

The client must never send:

```text
new_quantity
absolute_quantity
final_quantity
```

as authority.

---

# 18. Physical Quantity Cannot Become Negative

Required invariant:

```text
new_quantity >= 0
```

If:

```text
current = 3
delta = -4
```

reject.

Do not clamp to zero.

---

# 19. Reserved Quantity Invariant Is Stronger

Group C invariant:

```text
0 <= reserved_quantity <= quantity
```

Therefore after adjustment:

```text
new_quantity >= reserved_quantity
```

must also hold.

This is mandatory.

---

# 20. Critical Example

Current:

```text
quantity = 10
reserved_quantity = 7
```

Request:

```text
quantity_delta = -5
```

would result:

```text
new_quantity = 5
reserved_quantity = 7
```

This is invalid.

Reject the adjustment.

Do not silently reduce reservations.

---

# 21. Mutation Must Never Change Reservations

Manual inventory adjustment changes:

```text
quantity
```

only.

It must not modify:

```text
reserved_quantity
```

to make an invalid delta succeed.

Reservation lifecycle belongs to checkout/order workflows.

---

# 22. Available Quantity Follows Automatically

After successful mutation:

```text
available_quantity
=
new_quantity - reserved_quantity
```

Do not persist it.

Do not manually update another column.

---

# 23. Adjustment Reasons

Use one CLOSED enum with exactly:

```text
STOCK_RECEIPT
CORRECTION
DAMAGE
RETURN
AUDIT_ADJUSTMENT
```

No aliases.

---

# 24. Reason Support Type

Centralize reason values using the project's enum/support convention.

For example:

```text
InventoryAdjustmentReason
```

Do not scatter raw strings.

---

# 25. Unknown Reason

Example:

```json
{
  "quantity_delta": 5,
  "reason": "PURCHASE"
}
```

must return:

```text
422 INVALID_VALUE
field: reason
```

---

# 26. Do Not Add New Reasons

Do not invent:

```text
SALE
ORDER
TRANSFER
THEFT
EXPIRED
MANUAL
OTHER
```

during Phase 5.9.

Adding a CLOSED enum value is a compatibility decision.

---

# 27. Reason Direction Semantics

The existing source material defines the reasons but does not fully define which signs are valid for each reason.

Do not silently invent a complex semantic matrix.

At minimum:

```text
STOCK_RECEIPT
→ normally positive

DAMAGE
→ normally negative

RETURN
→ normally positive

CORRECTION
→ may be positive or negative

AUDIT_ADJUSTMENT
→ may be positive or negative
```

---

# 28. Minimal Direction Validation

If the latest authoritative docs remain silent, apply only obvious safe semantics:

```text
STOCK_RECEIPT requires delta > 0

DAMAGE requires delta < 0

RETURN requires delta > 0

CORRECTION may be +/- non-zero

AUDIT_ADJUSTMENT may be +/- non-zero
```

Record this as the Phase 5.9 clarification.

Do not permit semantically contradictory adjustments such as:

```text
DAMAGE +10
```

---

# 29. No Automatic Reason Rewriting

Do not convert:

```text
DAMAGE +10
```

into:

```text
RETURN +10
```

Reject invalid intent.

---

# 30. Resource Must Exist

Unknown Inventory ID:

```text
404 RESOURCE_NOT_FOUND
```

Do not create a ProductStock row automatically.

---

# 31. No Upsert Through Adjust

INV-003 adjusts an existing Inventory resource.

It does not:

```text
create stock location
create missing Variant stock
create ProductStock row
```

Inventory creation requires a future explicit workflow if needed.

---

# 32. Location Is Immutable Here

Do not accept:

```text
warehouse_location
```

in the adjustment body.

An Inventory resource already identifies its location.

---

# 33. Variant Ownership Is Immutable

Do not accept:

```text
variant_id
product_id
```

in the mutation body.

The Inventory resource already has its Variant/Product ownership.

---

# 34. Product State Does Not Block Operational Adjustment Automatically

Do not require public Product visibility.

Authorized operations may need to reconcile inventory for:

```text
inactive Product
unpublished Product
inactive Variant
```

unless a newer business rule explicitly forbids it.

Operational state is not the same as public catalog visibility.

---

# 35. MADE_TO_ORDER Inventory

If a ProductStock row exists for a MADE_TO_ORDER Product:

an authorized operator may adjust that physical stock record unless current business rules explicitly prohibit it.

Do not derive public MADE_TO_ORDER behavior from this mutation.

Phase 5.7 catalog availability still ignores physical stock for MADE_TO_ORDER.

---

# 36. Explicit Action Only

Do not implement:

```text
PATCH /inventory/{inventory}
PUT /inventory/{inventory}
```

for quantity mutation.

The only V1 inventory mutation is:

```text
POST /inventory/{inventory}/adjust
```

---

# 37. No Absolute Quantity API

Do not accept:

```json
{
  "quantity": 42
}
```

Use delta-based mutation.

This makes intent auditable and safer under concurrency.

---

# 38. No Reserved Quantity API

Do not expose a manual endpoint such as:

```text
POST /inventory/{inventory}/reserve
PATCH reserved_quantity
```

Phase 5.9 is physical inventory adjustment only.

---

# 39. Idempotency Required

Every INV-003 request must include:

```text
Idempotency-Key
```

No key:

return the canonical idempotency/validation error already established by project conventions.

Do not execute the mutation.

---

# 40. Idempotency Key Is Not Authentication

The key does not identify the user.

Authentication and authorization still run for every request/retry.

---

# 41. Idempotency Scope

Use the accepted scope:

```text
authenticated identity
+
endpoint/action scope
+
Idempotency-Key
```

Do not use a global key uniqueness model.

---

# 42. Idempotency Retention

Existing decision:

```text
24 hours
```

Use current shared infrastructure if already implemented.

Do not create a second incompatible idempotency system.

---

# 43. Same Key + Same Logical Request

Example:

```text
key = abc
inventory = inv_1
delta = +10
reason = STOCK_RECEIPT
```

First call:

```text
quantity 10 → 20
```

Retry with same logical request:

```text
must replay original 200
must not change 20 → 30
```

---

# 44. Same Key + Different Body

Same scoped key but:

```text
delta changes
or
reason changes
```

must return:

```text
409 DUPLICATE_OPERATION
```

Do not execute the second mutation.

---

# 45. Resource Must Be Part of Idempotency Intent

The logical operation fingerprint must include the target Inventory resource.

Same key:

```text
inv_A +10
```

versus:

```text
inv_B +10
```

must not accidentally replay across resources.

---

# 46. Actor Scope

Same key used by another Staff/Admin identity is not a replay of the first actor's operation.

Use the established identity-scoped key design.

---

# 47. Store Request Fingerprint Safely

Fingerprint canonical mutation intent such as:

```text
inventory ID
quantity_delta
reason
endpoint/action
actor scope
```

Do not rely on raw JSON byte ordering.

---

# 48. Durable Idempotency

Idempotency state must survive process restarts.

Do not store successful keys only in:

```text
request memory
static array
local controller state
```

Use existing durable project infrastructure if available.

---

# 49. Do Not Build Generic Idempotency Framework Twice

If earlier phases already implemented a shared idempotency store/service:

reuse it.

Do not create:

```text
InventoryIdempotencyService
```

with conflicting semantics.

---

# 50. Transaction Required

A successful adjustment must execute inside a DB transaction.

At minimum the atomic business boundary includes:

```text
resolve/lock inventory state as required
validate current state
calculate new quantity
validate invariants
persist quantity
create audit record
persist idempotency success/result
```

Exact locking strategy is completed in Phase 5.10.

---

# 51. Phase 5.9 vs Phase 5.10 Boundary

Phase 5.9 defines:

```text
what a valid mutation is
what must be atomic
idempotency behavior
audit behavior
invariants
errors
```

Phase 5.10 owns the hardened concurrency mechanism for:

```text
adjust vs adjust
adjust vs checkout/reservation
overselling
lost updates
```

---

# 52. Do Not Pretend Concurrency Is Solved

A basic transaction alone does not prove lost-update safety.

If Phase 5.10 has not yet implemented row locking/versioning:

report:

```text
mutation rules implemented
concurrency hardening pending Phase 5.10
```

Do not claim overselling protection complete.

---

# 53. No Unsafe Read-Modify-Write

Even before Phase 5.10, do not deliberately implement:

```text
SELECT quantity
then later UPDATE quantity
```

outside a transaction.

Structure the service so Phase 5.10 can add or finalize:

```text
lockForUpdate()
atomic update
version check
```

without rewriting the domain rules.

---

# 54. Resulting State Validation Must Be Transaction-Time

Do not validate:

```text
new_quantity >= reserved_quantity
```

only before entering the mutation boundary.

The authoritative current row must be validated in/around the transaction.

---

# 55. Audit Is Mandatory

Every successful inventory adjustment creates an audit event.

No successful mutation without audit.

---

# 56. Audit Actor

Actor is server-derived from authenticated identity.

Never accept:

```json
{
  "actor_id": "...",
  "staff_id": "...",
  "performed_by": "..."
}
```

---

# 57. Audit Minimum Content

Use existing audit model/infrastructure.

At minimum capture:

```text
actor_id
actor_role
action
resource_type
resource_id
previous_state
resulting_state
timestamp
request_id
```

according to the existing audit convention.

---

# 58. Inventory Audit Detail

For inventory adjustment, previous/resulting state should safely record operational mutation context such as:

```text
quantity
reserved_quantity
available_quantity
warehouse_location
quantity_delta
reason
```

according to the audit schema.

Do not include secrets.

---

# 59. Audit Action

Use a stable action name such as the established:

```text
INVENTORY_ADJUSTED
```

or current audit naming convention.

Do not create several semantically identical strings.

---

# 60. Audit Must Be in Atomic Boundary

If inventory quantity changes but audit creation fails:

the entire adjustment should roll back.

Do not leave unaudited privileged state changes.

---

# 61. Idempotent Replay Must Not Duplicate Audit

Same-key replay:

```text
must not create another inventory adjustment
must not create another audit event
```

Return the original successful result.

---

# 62. Failed Adjustment Audit

Follow the existing audit policy for failed privileged operations.

Do not invent a new failure-audit system solely here.

At minimum successful privileged mutation is mandatory.

---

# 63. Response

Successful response:

```text
200
```

using the canonical data envelope.

Return the updated Inventory resource.

---

# 64. Response Shape

Reuse Phase 5.8 InventoryResource.

Conceptually:

```json
{
  "data": {
    "id": "inv_...",
    "product_id": "prod_...",
    "variant_id": "var_...",
    "warehouse_location": "main",
    "quantity": 20,
    "reserved_quantity": 4,
    "available_quantity": 16,
    "updated_at": "..."
  }
}
```

Use the exact reconciled Phase 5.8 shape.

---

# 65. Do Not Add Mutation Metadata to Inventory Resource

Do not permanently add:

```text
quantity_delta
reason
performed_by
audit_id
```

to InventoryResource unless the frozen contract explicitly requires it.

Those describe the action, not current inventory state.

---

# 66. Cache Control

Mutation response:

```text
private
no-store
```

Never public/cacheable.

---

# 67. Rate Limiting

Apply the approved privileged mutation rate limit.

Current security decision specifies approximately:

```text
20/min/staff
```

for inventory adjustments.

Use the existing Phase 4.11 limiter infrastructure.

---

# 68. Retry-After

On 429:

return the standard:

```text
Retry-After
```

header.

Do not invent a custom JSON-only retry field.

---

# 69. Error — Missing Authentication

```text
401 AUTHENTICATION_REQUIRED
```

---

# 70. Error — Missing Permission

```text
403 FORBIDDEN
```

Do not disclose extra operational details.

---

# 71. Error — Inventory Missing

```text
404 RESOURCE_NOT_FOUND
```

---

# 72. Error — Missing Delta

```text
422 MISSING_REQUIRED_FIELD
field: quantity_delta
```

according to global error conventions.

---

# 73. Error — Invalid Delta Type

Examples:

```text
"10"
10.5
null
[]
```

must follow the strict integer input policy.

Do not broad-cast arbitrary values.

---

# 74. Error — Zero Delta

If zero is rejected under the Phase 5.9 clarification:

```text
422 INVALID_VALUE
field: quantity_delta
```

---

# 75. Error — Negative Result

If:

```text
new_quantity < 0
```

return:

```text
422 INVALID_VALUE
```

or the exact currently approved business error code if one exists.

Do not persist anything.

---

# 76. Error — Below Reserved Quantity

If:

```text
new_quantity < reserved_quantity
```

reject.

Prefer the existing:

```text
INVALID_VALUE
```

or canonical inventory-specific error if already frozen.

Do not invent a new V1 error code without reviewing the CLOSED error registry.

---

# 77. Error — Invalid Reason

```text
422 INVALID_VALUE
field: reason
```

---

# 78. Error — Duplicate Key Conflict

Same scoped key + different logical request:

```text
409 DUPLICATE_OPERATION
```

---

# 79. Concurrency Conflict

When Phase 5.10 detects stale/concurrent modification:

use:

```text
409 CONFLICT
```

with:

```text
RESOURCE_VERSION_CONFLICT
```

where already defined.

Phase 5.9 should preserve that error contract.

---

# 80. No Partial Mutation on Error

Any failure must preserve:

```text
quantity
reserved_quantity
available_quantity
audit state
idempotency state
```

appropriately.

No partially committed stock change.

---

# 81. Public Availability Must Update Naturally

After a successful adjustment of an IN_STOCK Product:

Phase 5.7 public availability should reflect the updated ProductStock state on subsequent reads.

Do not manually update:

```text
products.availability
products.stock_indicator
```

because those fields are derived.

---

# 82. LOW_STOCK Must Update Naturally

If an adjustment crosses the Phase 5.7 threshold:

```text
6 → 5
```

the next catalog read should reflect:

```text
LOW_STOCK
```

without another persisted status mutation.

---

# 83. Out-of-Stock Must Update Naturally

If:

```text
available_quantity
→ 0
```

public:

```text
availability
→ unavailable
```

for IN_STOCK catalog Products.

No separate availability update required.

---

# 84. MADE_TO_ORDER Public Availability Unchanged

Inventory adjustment on a MADE_TO_ORDER stock row must not change:

```text
stock_indicator = MADE_TO_ORDER
```

or its requestable catalog availability.

---

# 85. Do Not Trigger Product Publication Changes

Inventory adjustment must never automatically:

```text
publish Product
unpublish Product
activate Product
deactivate Product
```

These are independent business states.

---

# 86. Do Not Trigger Variant Activation Changes

Zero quantity must not automatically set:

```text
variant.is_active = false
```

Inventory and catalog activation are separate.

---

# 87. Do Not Delete Zero Stock Row

If quantity becomes:

```text
0
```

retain the ProductStock row.

Do not delete it.

Operational zero stock remains meaningful.

---

# 88. No Automatic Location Creation

Stock receipt into a location that does not yet have an Inventory row cannot be handled by guessing.

INV-003 adjusts existing resources only.

---

# 89. No Transfer Logic

Do not model:

```text
main -5
arusha +5
```

as one transfer workflow.

Warehouse transfer is not part of V1 Phase 5.9.

---

# 90. No Bulk Adjustments

Do not add:

```text
POST /inventory/bulk-adjust
```

One request changes one Inventory resource.

---

# 91. No CSV Inventory Import

Out of scope.

---

# 92. No Direct DB Admin Workflow

The API service must enforce the mutation rules.

Do not rely on staff manually editing database rows.

---

# 93. Service Layer

Use a focused domain/application service such as:

```text
AdjustInventory
InventoryAdjustmentService
```

or established project naming.

Responsibilities:

```text
validated mutation intent
current Inventory resource
business invariants
transaction
idempotency collaboration
audit collaboration
updated result
```

---

# 94. Controller Must Stay Thin

Conceptually:

```text
FormRequest
→ authorization
→ adjustment service
→ InventoryResource
```

Do not place transactional inventory arithmetic directly in the controller.

---

# 95. ProductStock Model

The existing model invariant:

```text
reserved_quantity <= quantity
```

remains a defensive last line.

The mutation service should validate the domain rule explicitly before persistence.

Do not rely on DB exceptions as normal API validation.

---

# 96. No `saveQuietly()` to Bypass Invariants

Do not bypass ProductStock validation hooks.

---

# 97. No Raw SQL Update That Bypasses Domain Accidentally

If Phase 5.10 later uses an atomic SQL update, it must still preserve all invariants.

Phase 5.9 should not trade correctness for premature optimization.

---

# 98. Idempotency Persistence Review

Inspect the existing schema first.

If the project already has a generic idempotency table/store:

reuse it.

---

# 99. If Idempotency Persistence Is Missing

Implement the **smallest shared durable mechanism** consistent with the frozen cross-domain contract.

Do not make it inventory-specific if other approved endpoints also require the same semantics.

But do not expand Phase 5.9 into implementation of every future idempotent endpoint.

---

# 100. Idempotency Record Minimum

A shared record may need concepts such as:

```text
identity scope
endpoint/action scope
key digest
request fingerprint
response status
response payload/reference
expiry
created_at
```

Use actual existing architecture.

Do not store raw authorization tokens.

---

# 101. Key Security

Do not log the full `Idempotency-Key` alongside PII.

Use masking/digest according to current security decision.

---

# 102. No Token Reuse

Idempotency keys do not replace Clerk credentials.

Every retry still requires valid authenticated identity and permission.

---

# 103. Audit Infrastructure Review

If an audit model/table already exists:

use it.

If audit persistence is explicitly deferred and absent, Phase 5.9 must not pretend the mandatory audit requirement is satisfied.

Implement the minimum shared audit persistence needed for this privileged mutation, or mark the phase BLOCKED if roadmap authority forbids doing so here.

---

# 104. Do Not Use Application Logs as Audit Substitute

A normal log line is not sufficient for the required durable privileged-action audit trail.

---

# 105. Test — Authentication

Anonymous adjustment:

```text
401
```

---

# 106. Test — CUSTOMER Forbidden

Authenticated CUSTOMER:

```text
403
```

---

# 107. Test — Staff Permission

STAFF with:

```text
inventory.manage
```

can adjust.

---

# 108. Test — Staff Without Permission

STAFF without permission:

```text
403
```

---

# 109. Test — Admin

ADMIN with appropriate permission can adjust.

---

# 110. Test — Positive Receipt

Given:

```text
quantity = 10
reserved = 2
```

request:

```text
+5 STOCK_RECEIPT
```

result:

```text
quantity = 15
reserved = 2
available = 13
```

---

# 111. Test — Damage

Given:

```text
quantity = 10
reserved = 2
```

request:

```text
-3 DAMAGE
```

result:

```text
quantity = 7
reserved = 2
available = 5
```

---

# 112. Test — Return

Positive:

```text
RETURN
```

increases physical quantity.

---

# 113. Test — Correction Positive

Verify allowed.

---

# 114. Test — Correction Negative

Verify allowed while invariants remain valid.

---

# 115. Test — Audit Adjustment Both Directions

Test positive and negative if the clarified reason semantics permit both.

---

# 116. Test — Invalid Reason Direction

Examples:

```text
DAMAGE +5
STOCK_RECEIPT -5
RETURN -2
```

should be rejected if Phase 5.9 adopts the minimal direction rules.

---

# 117. Test — Zero Delta

Verify chosen canonical behavior.

Preferred:

```text
422
```

---

# 118. Test — Negative Quantity Prevention

Current:

```text
quantity = 3
reserved = 0
```

delta:

```text
-4
```

reject.

State unchanged.

---

# 119. Test — Reserved Boundary

Current:

```text
quantity = 10
reserved = 7
```

delta:

```text
-3
```

result:

```text
quantity = 7
reserved = 7
available = 0
```

valid.

---

# 120. Test — Below Reserved Boundary

Same current state:

```text
delta = -4
```

must fail.

---

# 121. Test — Reserved Quantity Is Untouched

After every ordinary physical adjustment:

```text
reserved_quantity_before
=
reserved_quantity_after
```

---

# 122. Test — Zero Quantity Row Retained

Valid case with no reservations:

```text
quantity 4
delta -4
```

row remains with:

```text
0 / 0 / 0
```

---

# 123. Test — Unknown Inventory

```text
404
```

---

# 124. Test — Unknown Fields

Reject:

```text
quantity
available_quantity
reserved_quantity
product_id
variant_id
warehouse_location
actor_id
```

---

# 125. Test — Exact Response

Verify updated InventoryResource shape from Phase 5.8.

---

# 126. Test — Idempotent Replay

First request:

```text
+10
```

Retry same key/body:

quantity changes once only.

Audit created once only.

---

# 127. Test — Same Key Different Delta

Return:

```text
409 DUPLICATE_OPERATION
```

No second mutation.

---

# 128. Test — Same Key Different Reason

Return:

```text
409 DUPLICATE_OPERATION
```

---

# 129. Test — Same Key Different Inventory

Must not accidentally replay another Inventory mutation.

Follow canonical scoped-key semantics.

---

# 130. Test — Same Key Different Actor

Should not expose/replay another actor's operation.

---

# 131. Test — Missing Idempotency Key

Request fails before mutation.

---

# 132. Test — Audit

Assert successful mutation creates exactly one audit event containing the correct:

```text
actor
resource
previous quantity
resulting quantity
delta
reason
```

within actual audit schema capabilities.

---

# 133. Test — Audit Actor Cannot Be Spoofed

Send:

```text
actor_id
```

as unknown field.

Reject it.

Audit still uses authenticated actor.

---

# 134. Test — Rollback on Audit Failure

Where practical:

simulate audit persistence failure.

Verify stock change rolls back.

---

# 135. Test — Public Availability Regression

For IN_STOCK Product:

adjust inventory across:

```text
available
LOW_STOCK
unavailable
```

boundaries.

Subsequent CAT-001/CAT-002 should reflect Phase 5.7 derived state.

---

# 136. Test — Public Raw Stock Still Hidden

Mutation implementation must not cause CAT APIs to start exposing:

```text
quantity
reserved_quantity
available_quantity
```

---

# 137. Test — Read Model Reflects Mutation

After success:

```text
INV-001
INV-002
```

show the new physical quantity.

---

# 138. Test — No Publication Mutation

Ensure:

```text
is_active
is_published
product_type
variant.is_active
```

remain unchanged.

---

# 139. Test — Rate Limit

Verify the existing operational mutation limiter is attached.

Where rate-limit test conventions permit:

ensure 429 includes:

```text
Retry-After
```

---

# 140. Phase 5.10 Handoff

Explicitly document unresolved concurrency guarantees requiring Phase 5.10.

At minimum:

```text
adjust vs adjust race
adjust vs checkout reservation race
lost update prevention
overselling prevention
row locking / atomic update strategy
deadlock/retry behavior
```

---

# 141. Do Not Overclaim

Phase 5.9 completion does **not** mean:

```text
concurrency-safe inventory complete
overselling impossible
checkout reservations complete
```

Those are Phase 5.10 and later checkout responsibilities.

---

# 142. Schema Changes

Expected ProductStock schema changes:

```text
NONE
```

Potential shared infrastructure migrations may be needed only for:

```text
idempotency persistence
audit persistence
```

if those capabilities do not already exist.

Do not alter inventory normalization.

---

# 143. No ProductStock Migration

Do not add:

```text
version
last_adjusted_by
last_adjustment_reason
available_quantity
```

to ProductStock merely for this phase unless Phase 5.10 explicitly chooses versioning later.

---

# 144. Do Not Pull Version Column Forward

If optimistic locking is chosen in Phase 5.10, let Phase 5.10 own that decision.

Phase 5.9 should not prematurely add:

```text
version
lock_version
```

without the concurrency design.

---

# 145. No New External Dependencies

Expected external Composer dependencies:

```text
NONE
```

Use Laravel/database/project infrastructure.

---

# 146. No Frontend Work

Do not modify:

```text
frontend/web/
frontend/app/
frontend/design-system/
```

No stock-management UI belongs here.

---

# 147. PHPStan

Phase 5.9 must introduce:

```text
0 new PHPStan errors
```

If the previously known global baseline remains unresolved:

report that separately.

---

# 148. Phase 5.5 Blocker Remains Independent

Do not mark Phase 5.5 PASS unless its separate:

```text
MySQL/MariaDB FULLTEXT integration
PHPStan baseline
```

gates have actually been resolved.

---

# 149. Code Quality

Maintain:

```text
cognitive complexity <= 15
<= 3 returns where practical
small mutation service
central enum constants
no duplicate arithmetic
no raw client authority
```

---

# 150. Likely Implementation Areas

Expected:

```text
routes/api.php
AdjustInventoryRequest
InventoryController
InventoryAdjustmentService / action
InventoryAdjustmentReason
Inventory policy/gate
idempotency integration
audit integration
tests/Feature/
tests/Unit/
docs/api/
docs/decisions.md
openapi.yaml
```

Modify only what Phase 5.9 requires.

---

# 151. Verification

Run focused Inventory mutation tests.

Then:

```bash
php artisan test
vendor/bin/pint --test
vendor/bin/phpstan analyse
composer audit
git diff --check
php artisan route:list
```

---

# 152. Migration Safety

If shared idempotency/audit infrastructure requires new migrations:

test only against disposable databases.

Never use destructive commands against the normal application DB.

Use the existing:

```text
APP_ENV check
+
explicit DB-name check
```

before `migrate:fresh`.

---

# 153. Completion Report

Return:

## Phase 5.9 status

```text
PASS
```

or:

```text
BLOCKED
```

## Route

State the canonical route used:

```text
POST /api/v1/inventory/{inventory}/adjust
```

and confirm stale `{product}` wording was reconciled if necessary.

## Authorization

Report:

```text
authentication required
inventory.manage required
Customer forbidden
```

## Input

Report exact accepted fields:

```text
quantity_delta
reason
```

## Reasons

Report CLOSED enum and direction semantics.

## Quantity rules

State:

```text
new = current + delta
new >= 0
new >= reserved_quantity
reserved quantity unchanged
```

## Idempotency

State:

```text
scope
retention
same-request replay
different-request conflict
```

## Audit

State exact durable audit behavior.

## Availability

Confirm successful mutations feed Phase 5.7 derived availability naturally.

## Concurrency

Explicitly list what remains for Phase 5.10.

## Inventory schema

Expected:

```text
NONE
```

## Infrastructure migrations

List any idempotency/audit migration separately.

## Frontend

Must state:

```text
NONE
```

## Tests

Report exact focused/full results.

## Quality

Report:

```text
Pint
PHPStan
Composer audit
git diff --check
```

---

# 154. Definition of Done

Phase 5.9 is complete when:

* INV-003 exists as one controlled action;
* route identity is unambiguous;
* authentication is required;
* `inventory.manage` is required;
* Customers cannot mutate inventory;
* only `quantity_delta` and `reason` are accepted;
* unknown fields are rejected;
* quantity delta is an integer;
* zero delta behavior is explicitly defined;
* reason enum is CLOSED;
* reason/delta direction semantics are explicit;
* server calculates `new_quantity`;
* physical quantity can never become negative;
* physical quantity can never become less than reserved quantity;
* reserved quantity is never silently changed;
* available quantity remains derived;
* location/Variant/Product ownership cannot be reassigned;
* no missing stock row is auto-created;
* zero-stock rows are retained;
* Idempotency-Key is mandatory;
* same-key/same-intent replay does not double-adjust;
* same-key/different-intent returns 409;
* idempotency is scoped by authenticated identity/action/resource;
* every successful mutation is durably audited;
* audit actor is server-derived;
* audit failure cannot leave a committed inventory adjustment;
* rate limiting is attached;
* `Retry-After` remains compliant;
* updated InventoryResource is returned;
* Phase 5.7 availability updates through derived stock state;
* no public raw inventory exposure is introduced;
* no generic PATCH is introduced;
* no absolute quantity mutation API is introduced;
* no reservation API is introduced;
* no checkout logic is introduced;
* no inventory normalization/schema redesign occurs;
* no frontend code changes;
* concurrency limitations are explicitly handed to Phase 5.10;
* focused tests pass;
* existing inventory-read/catalog tests remain green;
* no new PHPStan failures are introduced;
* Pint passes;
* Composer audit has no new blocker.

---

# 155. Out of Scope

Do not implement:

```text
checkout reservations
reserved_quantity mutation API
order inventory consumption
reservation release
overselling algorithm
row-locking final strategy
optimistic versioning
warehouse transfers
bulk adjustments
inventory imports
stock ledger
inventory history UI
warehouse management
frontend inventory administration
```

Phase 5.10 owns concurrency/overselling protection.

---

# 156. STOP Condition

STOP when an authorized Staff/Admin actor can perform exactly one safe, explicit inventory adjustment:

```text
authenticated actor
→ inventory.manage
→ Inventory resource
→ validated delta + CLOSED reason
→ idempotency check
→ transactional invariant validation
→ quantity mutation
→ durable audit
→ updated InventoryResource
```

while:

```text
reserved quantity
location
Variant ownership
Product ownership
catalog activation/publication
```

remain untouched.

Do not continue automatically to Phase 5.10.

DO NOT COMMIT OR PUSH.

The project owner handles all Git operations.
