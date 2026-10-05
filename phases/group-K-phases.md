# Phase 11.6 — Inventory Management

## Objective

Complete **Group K / Phase 11.6 — Inventory Management** by making the already-established Group E inventory capabilities fully usable and verified as the canonical Staff/Admin operational inventory surface.

This is primarily a:

```text
reuse
→ contract reconciliation
→ authorization verification
→ operational hardening
→ regression/concurrency verification
```

phase.

Do **not** create a parallel Admin inventory API.

The canonical V1 inventory endpoints remain:

```text
INV-001
GET /api/v1/inventory

INV-002
GET /api/v1/inventory/{inventory}

INV-003
POST /api/v1/inventory/{inventory}/adjust
```

Phase 11.6 must inspect the current implementation first and reuse it.

If Group E already fully implements a requirement below, do not rewrite it merely because this is Group K.

Instead:

1. verify it;
2. add missing regression coverage if necessary;
3. reconcile stale documentation/status;
4. fix only genuine contract/runtime gaps.

---

# 1. Architectural Boundary

Group K already decided that Admin operations reuse canonical domain APIs rather than creating UI/navigation-specific aliases.

Therefore:

```text
DO use:
GET  /api/v1/inventory
GET  /api/v1/inventory/{inventory}
POST /api/v1/inventory/{inventory}/adjust
```

Do NOT add:

```text
/api/v1/admin/inventory
/api/v1/admin/inventory/{inventory}
/api/v1/admin/inventory/{inventory}/adjust
/api/v1/staff/inventory
/api/v1/products/{product}/inventory
/api/v1/variants/{variant}/inventory
```

Do not create duplicate controllers/services/resources merely for Group K.

The eventual Admin frontend must consume the canonical Inventory API.

---

# 2. Current Production-Scope Boundary

The initial production release remains **request-first**.

Only `MADE_TO_ORDER` products are intended for the initial live storefront, while:

```text
Cart
Checkout
Payments
Paid-order processing
Delivery commerce lifecycle
```

remain deferred/inactive for production.

Phase 11.6 does **not** reactivate transactional commerce.

Completing Inventory Management means the backend operational capability is correct and ready.

It does NOT mean:

```text
enable checkout
publish IN_STOCK products
activate payment
activate order processing
activate delivery
```

Do not change the request-first launch decision.

---

# 3. Existing Inventory Authority

Preserve the existing domain model.

Inventory is represented by:

```text
ProductStock
```

with one row representing:

```text
one ProductVariant
+
one warehouse_location
```

Authoritative persisted fields include:

```text
product_variant_id
warehouse_location
quantity
reserved_quantity
```

The same:

```text
product_variant_id + warehouse_location
```

pair must remain unique.

Do not move inventory onto:

```text
products
product_variants
```

and do not introduce duplicate quantity columns there.

---

# 4. Quantity Semantics

Preserve the existing semantics exactly.

```text
quantity
= physical units owned at this location

reserved_quantity
= physical units currently reserved and unavailable to another allocation

available_quantity
= quantity - reserved_quantity
```

`available_quantity` is derived.

Do NOT persist it.

The invariant remains:

```text
0 <= reserved_quantity <= quantity
```

No operation may violate this invariant.

---

# 5. Warehouse / Location Model

V1 intentionally uses:

```text
warehouse_location
```

as a bounded stable machine-friendly string.

Examples may include:

```text
main
dar-es-salaam
arusha-store
```

Do NOT introduce during Phase 11.6:

```text
warehouses table
warehouse addresses
warehouse contacts
regions
delivery zones
stock transfer subsystem
warehouse CRUD
```

Those require separate product/business decisions.

Phase 11.6 works with existing `ProductStock` rows.

---

# 6. INV-001 — Operational Inventory Collection

Verify and, only if necessary, complete:

```text
GET /api/v1/inventory
```

Authorization:

```text
authentication required
permission: inventory.view
```

Expected actors:

```text
STAFF with inventory.view
ADMIN with inventory.view
```

Customer and anonymous access must fail.

Do not infer access merely from role name.

Use the existing centralized authorization/permission infrastructure.

## Representation

Each collection item must use the canonical Inventory resource:

```json
{
  "id": "inv_...",
  "product_id": "prod_...",
  "variant_id": "var_...",
  "warehouse_location": "main",
  "quantity": 10,
  "reserved_quantity": 2,
  "available_quantity": 8,
  "updated_at": "..."
}
```

Do not expose raw database IDs.

Do not expose:

```text
product_variant_id
internal foreign keys
lock/version internals
reservation allocation rows
```

## Collection behavior

The endpoint lists persisted `ProductStock` rows.

Do NOT fabricate zero-stock rows for Products or Variants with no `ProductStock`.

Do NOT aggregate multiple locations into one Inventory resource.

One row remains one Inventory resource.

## Filters

Preserve the frozen filters:

```text
product
variant
warehouse_location
page
per_page
```

Canonical behavior:

```text
product
→ accepted existing contract forms only

variant
→ opaque var_... identifier as already frozen

warehouse_location
→ exact match
```

Unknown query parameters/operators must continue to be rejected according to the API validation convention.

Pagination:

```text
default per_page = existing frozen value
maximum = 100
```

Ordering remains deterministic:

```text
updated_at DESC
id ASC
```

Do not invent arbitrary sort fields.

---

# 7. Operational Visibility Is Not Public Catalog Visibility

Inventory operations serve reconciliation and administration.

Therefore operational Inventory reads must continue to include legitimate stock rows even when their related catalog objects are:

```text
inactive
unpublished
soft-deleted Product
inactive Variant
zero stock
```

Do NOT apply the public Product query scope to INV-001 or INV-002.

A Product becoming unavailable publicly must not make its Inventory record impossible for Staff/Admin to reconcile.

This distinction must have regression coverage.

---

# 8. INV-002 — Inventory Detail

Verify and, only if necessary, complete:

```text
GET /api/v1/inventory/{inventory}
```

Authorization:

```text
inventory.view
```

Resolve the Inventory resource through its canonical opaque:

```text
inv_...
```

identifier.

Do not resolve inventory detail by:

```text
Product slug
Product ID
Variant SKU
warehouse_location
```

Those are not alternate V1 resource identifiers.

Unknown/malformed resources must use the existing canonical validation/not-found behavior.

Do not leak raw IDs.

---

# 9. INV-003 — Controlled Inventory Adjustment

The sole V1 stock quantity mutation endpoint remains:

```text
POST /api/v1/inventory/{inventory}/adjust
```

Permission:

```text
inventory.manage
```

Do NOT add:

```text
PATCH /inventory/{inventory}
PUT /inventory/{inventory}
PATCH { "quantity": 999 }
```

Inventory is modified by an explicit auditable business action, not arbitrary state replacement.

---

# 10. Strict Request Body

The accepted JSON body remains exactly:

```json
{
  "quantity_delta": 5,
  "reason": "STOCK_RECEIPT"
}
```

Allowed properties:

```text
quantity_delta
reason
```

Unknown fields must be rejected.

Explicitly reject attempts to submit:

```text
quantity
reserved_quantity
available_quantity
product_id
variant_id
warehouse_location
product_variant_id
actor_id
staff_id
performed_by
user_id
```

Do not use:

```php
$request->all()
```

Use the existing:

```text
FormRequest
validated input
DTO/command/service boundary
```

pattern.

---

# 11. quantity_delta Rules

`quantity_delta` must be:

```text
strict integer
non-zero
```

The server calculates:

```text
new_quantity = current_quantity + quantity_delta
```

inside the authoritative locked transaction.

The client must never submit `new_quantity`.

Mandatory invariants:

```text
new_quantity >= 0

new_quantity >= reserved_quantity
```

Do not clamp invalid values.

Example:

```text
quantity = 10
reserved_quantity = 4
delta = -7

new_quantity = 3
```

must be rejected because:

```text
3 < reserved_quantity 4
```

even though it is not negative.

---

# 12. CLOSED Adjustment Reasons

Preserve the CLOSED reason enum:

```text
STOCK_RECEIPT
CORRECTION
DAMAGE
RETURN
AUDIT_ADJUSTMENT
```

Do not add aliases or new reasons.

Direction rules remain:

```text
STOCK_RECEIPT
→ quantity_delta > 0

RETURN
→ quantity_delta > 0

DAMAGE
→ quantity_delta < 0

CORRECTION
→ either sign, but non-zero

AUDIT_ADJUSTMENT
→ either sign, but non-zero
```

Examples that must fail:

```text
DAMAGE +5
STOCK_RECEIPT -5
RETURN -2
quantity_delta 0
```

Never silently reverse or normalize the client's intended direction.

Unknown reason:

```text
422 INVALID_VALUE
field: reason
```

Use existing canonical errors.

Do not invent a new error family.

---

# 13. Existing-Resource-Only Rule

INV-003 adjusts an existing Inventory resource.

It must NOT:

```text
create ProductStock
upsert missing ProductStock
create warehouse_location
reassign Variant
reassign Product
change warehouse_location
publish Product
activate Product
activate Variant
delete zero-stock ProductStock
```

A missing stock row remains missing.

Do not interpret adjustment as inventory creation.

If future business requirements need creating a new Variant/location stock record, that must be a separately approved API contract.

---

# 14. Reserved Quantity Must Never Be Directly Adjusted

INV-003 changes:

```text
quantity
```

only.

It must NOT directly mutate:

```text
reserved_quantity
```

Reservation state belongs to the inventory reservation lifecycle.

After quantity adjustment:

```text
available_quantity =
new_quantity - existing_reserved_quantity
```

No persisted availability recalculation field should be introduced.

---

# 15. Idempotency-Key Is Mandatory

Preserve the existing INV-003 idempotency contract.

Header:

```http
Idempotency-Key: <opaque UUID>
```

Required.

Missing:

```text
422 MISSING_REQUIRED_FIELD
field: Idempotency-Key
```

Malformed:

```text
422 INVALID_FORMAT
field: Idempotency-Key
```

Preserve the existing durable identity/action/key scoping.

Conceptually:

```text
authenticated actor
+
endpoint/action
+
Idempotency-Key
```

Retention remains the existing contract value.

Do not weaken or remove durable idempotency.

---

# 16. Idempotent Replay Semantics

The intent fingerprint must continue to include the authoritative business intent, including:

```text
inventory resource
quantity_delta
reason
```

Same actor + same scoped key + same intent:

```text
return original successful 200 representation
do NOT apply quantity_delta again
do NOT write another audit event
```

Same scoped key + different intent:

```text
409 DUPLICATE_OPERATION
```

Same raw key used by a different actor remains a separate actor scope according to the existing infrastructure.

Do not allow idempotency to bypass current authentication or authorization.

Authorization must still be evaluated on retry.

---

# 17. Audit Is Mandatory

Every successful stock adjustment must produce the existing durable:

```text
INVENTORY_ADJUSTED
```

audit event.

Preserve at least the existing authoritative information:

```text
actor_id
actor_role
action
resource_type
resource_id
previous_state
resulting_state
occurred_at
request_id
```

The adjustment reason must remain represented wherever the existing audit design records it.

Actor identity and role must be server-derived.

Never trust:

```text
actor_id
role
performed_by
staff_id
```

from the request.

Audit and stock mutation belong to the same transaction.

If audit persistence fails:

```text
inventory quantity change must roll back
```

Do not allow unaudited successful adjustments.

---

# 18. Concurrency — Preserve Group E Authority

Do not replace the established concurrency design.

MariaDB/MySQL InnoDB remains the production concurrency authority.

Every Inventory mutation must use:

```text
DB transaction
+
SELECT ... FOR UPDATE / lockForUpdate()
```

on the authoritative `ProductStock`.

Do NOT introduce:

```text
Redis locks
distributed locks
table locks
global mutex
queue serialization
lock_version column
optimistic replacement logic
```

unless an existing frozen contract explicitly requires it.

---

# 19. Independent Concurrent Adjustments

Two independent INV-003 requests using different Idempotency Keys must serialize correctly.

Example:

```text
initial quantity = 100

request A: +10
request B: +10
```

Valid final result:

```text
120
```

Both operations should succeed after serialization.

Do NOT generate a spurious 409 merely because another legitimate adjustment committed first.

The locked current value is authoritative.

---

# 20. Negative Adjustment vs Active Reservation

The system must prevent a quantity adjustment from reducing physical stock beneath already-reserved stock.

Example:

```text
quantity = 10
reserved_quantity = 8

adjustment = -3
```

would produce:

```text
new_quantity = 7
```

and must fail because:

```text
7 < 8
```

This decision must be made against the **locked current row**.

Not against a stale pre-transaction value.

---

# 21. Adjustment vs Reservation Race

Preserve the Group E locking relationship between:

```text
InventoryAdjustmentService
and
InventoryAllocator
```

An adjustment and reservation racing on the same ProductStock row must not both validate stale state.

The final persisted state must always satisfy:

```text
reserved_quantity <= quantity
```

Do not modify or weaken the checkout-facing allocator merely because checkout is currently deferred from production.

Its concurrency correctness remains part of the domain.

---

# 22. Reservation/Release/Consume Remain Internal

Do NOT expose:

```text
POST /inventory/reserve
POST /inventory/release
POST /inventory/consume
```

These are internal commerce primitives.

Cart must still not reserve inventory.

Phase 11.6 exposes operational stock management only.

---

# 23. Multi-Location Semantics

A Variant may have multiple ProductStock rows.

Example:

```text
Variant VAR-A

main             5
dar-es-salaam    7
arusha-store     2
```

INV-001 returns three separate Inventory resources.

INV-003 adjusts one target Inventory row.

Do NOT automatically spread an operational adjustment across locations.

Do NOT aggregate location rows during adjustment.

The existing checkout reservation allocator may distribute reservations across locations according to its existing deterministic rules, but that is not INV-003 behavior.

---

# 24. Public Catalog Regression

Inventory mutation must continue to feed catalog availability through the existing derived queries.

Do NOT write fields such as:

```text
products.available_quantity
products.stock_indicator
products.availability
```

Successful stock changes should naturally alter public derived availability where applicable.

Preserve:

```text
public catalog
→ coarse availability only
```

Customers must never receive:

```text
quantity
reserved_quantity
per-location stock
internal Inventory IDs
```

unless a separately frozen public contract explicitly permits it.

---

# 25. MADE_TO_ORDER Boundary

Inventory records must not accidentally make a:

```text
MADE_TO_ORDER
```

Product purchasable.

The product type remains authoritative.

Even if a MADE_TO_ORDER Variant happens to have ProductStock rows due to historical/test data:

```text
MADE_TO_ORDER
```

must remain request-only according to existing catalog/cart business rules.

Do not alter that behavior in Phase 11.6.

---

# 26. Staff vs Admin Authorization

Do not hard-code:

```php
if ($user->role === 'ADMIN')
```

as the inventory authorization model.

Use existing permission authority:

```text
inventory.view
inventory.manage
```

Expected separation:

```text
inventory.view
→ INV-001
→ INV-002

inventory.manage
→ INV-003
```

A Staff user may access only capabilities assigned by the existing PermissionCatalog.

Admin receives explicit permissions, not wildcard authority.

CUSTOMER must have neither operational inventory permission.

Test permission separation explicitly.

---

# 27. Cache and Data Sensitivity

INV-001, INV-002 and successful INV-003 responses are operational/private.

Preserve:

```http
Cache-Control: private, no-store
Vary: Authorization
```

Do not CDN/public-cache Inventory resources.

Do not expose stock operations through public catalog caching behavior.

---

# 28. API Resource

Reuse the existing canonical `InventoryResource`.

Expected shape:

```json
{
  "id": "inv_...",
  "product_id": "prod_...",
  "variant_id": "var_...",
  "warehouse_location": "main",
  "quantity": 12,
  "reserved_quantity": 3,
  "available_quantity": 9,
  "updated_at": "..."
}
```

Do not add speculative fields such as:

```text
warehouse_name
low_stock_threshold
stock_value
cost_price
supplier
reorder_level
last_adjusted_by
created_at
product_variant_id
internal numeric IDs
```

unless already part of the frozen V1 schema.

---

# 29. No Stock Ledger Expansion

Phase 11.6 must not invent a general stock-movement/ledger subsystem.

Existing:

```text
audit_events
```

remain the authoritative audit mechanism for INV-003.

Do not add:

```text
stock_movements
inventory_transactions
inventory_history
inventory_snapshots
inventory_transfers
```

unless the current repository already contains an approved frozen model requiring use.

Do not confuse Audit visibility in Phase 11.13 with a new Inventory ledger.

---

# 30. No Destructive Inventory Operations

Do NOT add:

```text
DELETE /inventory/{inventory}
```

or generic inventory deletion.

Zero stock remains:

```text
quantity = 0
```

on an existing stock row.

Do not delete the row automatically.

---

# 31. Inspect Before Editing

Before implementation, inspect at minimum:

```text
routes/api.php

InventoryController
AdjustInventoryRequest
InventoryResource
InventoryAdjustmentService
IdempotencyService
AuditRecorder

ProductStock
ProductVariant
Product

InventoryAdjustmentReason
PermissionName
PermissionCatalog
ApiErrorCode

InventoryAllocator

existing Inventory feature tests
existing MariaDB concurrency tests

docs/api/api-contract.md
docs/api/api-resources.md
docs/api/api-conventions.md
docs/api/openapi.yaml
docs/domain/business-rules.md
docs/decisions.md
phases/group-K-phases.md
```

Determine what Phase 5.8–5.10 already implemented.

Do not duplicate working code.

---

# 32. Contract Status Reconciliation

The historical documentation may still label:

```text
INV-001
INV-002
INV-003
```

as `PROPOSED` even though Group E subsequently implemented and closed the behavior.

Inspect all authoritative docs.

If runtime and approved Group E closure prove these endpoints are implemented and accepted, reconcile stale documentation status consistently.

Do not alter endpoint IDs, methods, paths, body shape, or semantics merely to change status wording.

Record Phase 11.6 completion in:

```text
phases/group-K-phases.md
docs/decisions.md
```

using the project's established ADR style.

---

# 33. Required INV-001 Tests

Ensure permanent tests cover at least:

### Authorization

```text
anonymous → rejected
Customer → rejected

Staff with inventory.view → allowed
Admin with inventory.view → allowed

authenticated actor without inventory.view → 403
```

### Representation

Verify:

```text
opaque inventory ID
opaque Product ID
opaque Variant ID
warehouse_location
quantity
reserved_quantity
derived available_quantity
updated_at
```

and no internal ID leakage.

### Collection

Verify:

```text
pagination
product filter
variant filter
warehouse_location filter
unknown query rejection
deterministic ordering
zero-stock row visible
multiple locations remain separate
```

### Operational visibility

Verify rows remain visible for:

```text
unpublished Product
inactive Product
soft-deleted Product
inactive Variant
```

where existing relational behavior permits reconciliation.

---

# 34. Required INV-002 Tests

Cover:

```text
authorized detail retrieval
opaque inv_... resolution
unknown Inventory → canonical 404
malformed identifier behavior
Customer forbidden
Staff/Admin permission enforcement
private/no-store headers
no internal FK leakage
```

Also verify detail remains operationally accessible for non-public catalog state.

---

# 35. Required INV-003 Validation Tests

Cover at least:

```text
+STOCK_RECEIPT → success
+RETURN → success
-DAMAGE → success
+CORRECTION → success
-CORRECTION → success
+AUDIT_ADJUSTMENT → success
-AUDIT_ADJUSTMENT → success
```

Reject:

```text
0 delta
DAMAGE positive
STOCK_RECEIPT negative
RETURN negative
unknown reason
fraction
numeric string if strict integer contract rejects it
null
missing quantity_delta
missing reason
unknown body field
absolute quantity field
reserved_quantity field
warehouse_location field
actor identity field
```

---

# 36. Required Quantity Invariant Tests

Cover:

```text
quantity cannot become negative

quantity cannot become less than reserved_quantity

successful adjustment preserves reserved_quantity

available_quantity is recalculated from current state

zero resulting quantity allowed only when reserved_quantity == 0

inactive/unpublished Product stock remains adjustable operationally
```

---

# 37. Required Idempotency Tests

Cover:

```text
missing Idempotency-Key
malformed Idempotency-Key

same key + same intent
→ one delta only
→ one audit only
→ same successful result replayed

same key + changed quantity_delta
→ 409 DUPLICATE_OPERATION

same key + changed reason
→ 409 DUPLICATE_OPERATION

same raw key + different authenticated actor
→ independently scoped according to existing contract
```

Also verify authorization is not bypassed by replay.

---

# 38. Required Audit Tests

Prove:

```text
successful adjustment creates exactly one INVENTORY_ADJUSTED audit

actor is server-derived

previous state correct

resulting state correct

resource identity correct

request/correlation identity preserved

audit failure rolls back stock mutation

idempotent replay does not create second audit
```

Do not weaken append-only audit behavior.

---

# 39. MariaDB Concurrency Gate

SQLite is not acceptable proof of inventory concurrency.

Use the existing disposable database:

```text
furnitureapp_test_disposable
```

and the established forked-worker MariaDB concurrency infrastructure.

Maintain exact non-production/disposable-DB safety guards.

Do not point destructive concurrency tests at:

```text
furnitureapp
production
staging
```

---

# 40. MariaDB Race — Independent Adjustments

Run repeated barrier-synchronized concurrent requests against the same Inventory row.

Example:

```text
initial quantity = 100

worker A:
+10
unique Idempotency-Key A

worker B:
+20
unique Idempotency-Key B
```

Assert:

```text
both succeed
final quantity = 130
reserved_quantity unchanged
two audit events
no lost update
```

Repeat enough iterations to make race failures observable.

---

# 41. MariaDB Race — Negative Adjustment vs Negative Adjustment

Example:

```text
quantity = 10
reserved = 0

worker A = -7
worker B = -7
```

Both must not independently validate `10 - 7`.

Valid end behavior must reflect serialization.

For example:

```text
first succeeds → 3
second sees locked current 3
second rejects because 3 - 7 < 0
```

Final state:

```text
quantity = 3
```

not:

```text
-4
```

and not a lost update.

Assert audit count matches successful mutations only.

---

# 42. MariaDB Race — Adjustment vs Reservation

Reuse the existing `InventoryAllocator` concurrency path.

Construct a deterministic race where:

```text
quantity = N
reserved_quantity = existing value
```

and:

```text
worker A attempts negative physical-stock adjustment
worker B attempts reservation
```

Prove the row lock serializes authoritative validation.

Final invariant must always be:

```text
0 <= reserved_quantity <= quantity
```

No oversell.

No stale validation.

No database constraint error should become the normal business-control mechanism.

The application should reject invalid state before persistence after obtaining the lock.

---

# 43. MariaDB Race — Same Idempotency Key

Where not already permanently covered by existing Group E integration tests, prove:

```text
same actor
same INV-003 endpoint
same Idempotency-Key
same intent
```

under actual simultaneous execution causes:

```text
exactly one quantity mutation
exactly one audit event
both callers receive the same business outcome
```

Do not rely only on sequential replay testing.

If this precise race is already permanently covered and passing, reuse that test rather than duplicating it.

---

# 44. Preserve Existing Group E Regression Gates

Do not remove or weaken existing tests covering:

```text
last-unit race
adjust-vs-adjust
adjust-vs-reserve
same-key idempotency
reserved_quantity invariants
catalog derived availability
public inventory-field non-leakage
```

Phase 11.6 should add only missing Group K operational coverage.

---

# 45. OpenAPI Verification

Inspect the V1 OpenAPI definition for:

```text
INV-001
INV-002
INV-003
```

Verify it agrees with runtime for:

```text
methods
paths
security
permissions/descriptions
query filters
pagination
InventoryResource
Idempotency-Key
AdjustInventory request body
CLOSED adjustment reasons
success status
error statuses
additionalProperties: false
```

Correct genuine stale documentation/runtime consistency errors.

Do not broaden V1.

---

# 46. Route Surface Regression

Explicitly prove canonical routes exist:

```text
GET  /api/v1/inventory
GET  /api/v1/inventory/{inventory}
POST /api/v1/inventory/{inventory}/adjust
```

and rejected/nonexistent surfaces remain absent:

```text
PATCH  /api/v1/inventory/{inventory}
PUT    /api/v1/inventory/{inventory}
DELETE /api/v1/inventory/{inventory}

POST /api/v1/inventory/reserve
POST /api/v1/inventory/release
POST /api/v1/inventory/consume

GET  /api/v1/admin/inventory
POST /api/v1/admin/inventory/{inventory}/adjust
```

Do not add redirects or compatibility aliases.

---

# 47. Code Quality

Follow current project standards:

```text
thin controllers
FormRequest validation
validated() only
DTO/command/service boundaries where established
central authorization
explicit resources
domain enums/constants
short transactions
no external calls inside DB locks
cognitive complexity <= 15
<= 3 returns per function where practical
PHPStan clean
Pint clean
```

Do not refactor unrelated Group E code merely for style.

---

# 48. No New Dependencies by Default

Phase 11.6 should require no new Composer/package dependency.

If you believe a dependency is required:

STOP before adding it unless repository evidence proves the existing implementation cannot satisfy the frozen contract without it.

Do not introduce Redis or distributed locking infrastructure.

---

# 49. Out of Scope

Do NOT implement:

```text
Phase 11.7 Order Management

Phase 11.8 Customer Management

Phase 11.9 Request Management

Phase 11.10 Enquiry Management

Phase 11.11 Payment Visibility

Phase 11.12 Delivery Management

Phase 11.13 Audit Visibility

frontend Admin screens
Next.js inventory UI
Flutter inventory UI

warehouse CRUD
stock transfers
supplier management
purchase orders
reorder points
automatic restocking
inventory forecasting
inventory valuation
cost accounting
barcode management
stock ledger/history endpoint
bulk stock import
CSV import/export
inventory deletion
public raw stock counts
checkout activation
payment activation
order activation
```

Audit events required by INV-003 remain internal persistence; do not expose ADM-007 in this phase.

---

# 50. Verification Commands

Run the repository's canonical equivalents of:

```bash
cd backend/laravel

php artisan test
./vendor/bin/phpstan analyse
./vendor/bin/pint --test
composer audit
git diff --check
php artisan route:list
```

Also run:

```text
focused Inventory API tests
Group E inventory regression tests
RBAC tests
catalog availability regression tests
idempotency/audit tests
```

Then run the real MariaDB concurrency suite against only:

```text
furnitureapp_test_disposable
```

using the existing safety guards.

If the MariaDB gate is not executed successfully, do not claim concurrency closure from SQLite alone.

---

# 51. Completion Report

Return a concise evidence-based report containing:

```text
Phase 11.6 status:
PASS / BLOCKED

Existing Group E inventory implementation reused:
YES / NO

INV-001:
PASS / BLOCKED

INV-002:
PASS / BLOCKED

INV-003:
PASS / BLOCKED

Canonical routes only:
PASS / FAIL

Admin inventory aliases added:
NO

inventory.view authorization:
PASS / FAIL

inventory.manage authorization:
PASS / FAIL

Customer raw inventory access:
REJECTED / FAIL

Adjustment reasons preserved:
YES / NO

Idempotency:
PASS / FAIL

Audit atomicity:
PASS / FAIL

reserved_quantity direct mutation:
NO

available_quantity persisted:
NO

Legacy/public catalog visibility separation:
PASS / FAIL

MariaDB adjust-vs-adjust:
PASS / FAIL

MariaDB negative-adjust race:
PASS / FAIL

MariaDB adjust-vs-reserve:
PASS / FAIL

MariaDB same-key race:
PASS / FAIL / ALREADY COVERED

Full PHPUnit:
<x> passed, <y> skipped

PHPStan:
PASS / FAIL

Pint:
PASS / FAIL

Composer audit:
PASS / FAIL

OpenAPI:
PASS / FAIL

git diff --check:
PASS / FAIL

Phase 11.7:
DEFERRED

Phase 11.8:
READY / BLOCKED
```

List:

```text
files changed
genuine gaps found
contract/documentation reconciliations
tests added/changed
schema changes
dependency changes
```

Expected:

```text
schema changes = NONE
dependency changes = NONE
```

unless a pre-existing genuine defect proves otherwise.

---

# 52. STOP Condition

Phase 11.6 is PASS only when:

- INV-001/002 canonical operational reads are working;
- `inventory.view` is enforced;
- INV-003 remains the sole stock-adjustment operation;
- `inventory.manage` is enforced;
- strict delta/reason validation holds;
- reserved stock cannot be invalidated by adjustment;
- durable idempotency remains correct;
- every successful adjustment remains atomically audited;
- public users cannot access raw inventory;
- operational inventory remains visible independently of public catalog visibility;
- MariaDB concurrency tests prove serialization and invariants;
- no Admin inventory alias was introduced;
- no transaction-commerce phase was reactivated;
- full backend verification passes.

At that point:

```text
Phase 11.6 — PASS
Phase 11.7 — DEFERRED
Phase 11.8 — READY
```

Do not begin Phase 11.8 automatically.

---

## Phase 11.6 Completion (2026-10-05)

**Status:** PASS

- Reused the existing Group E `INV-001`, `INV-002`, and `INV-003` implementation; no Admin aliases, schema changes, dependencies, or transactional-commerce activation were added.
- `inventory.view` and `inventory.manage` permission separation, private operational representation, idempotency, audit atomicity, and public-catalog separation are covered by permanent Feature tests.
- Disposable MariaDB (`furnitureapp_test_disposable`) concurrency verification passed: last-unit reservation, independent adjustments, negative-adjustment serialization, adjustment-vs-reservation, and same-key idempotency.
- Contract status is reconciled: `INV-001..003` are APPROVED; OpenAPI requires `Inventory.updated_at`.
- Phase 11.7 remains DEFERRED. Phase 11.8 is READY.

**DO NOT COMMIT, STAGE OR PUSH.**
**The project owner handles all Git operations.**
