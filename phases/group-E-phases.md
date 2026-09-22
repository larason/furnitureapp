# Phase 5.10 — Concurrency / Overselling Protection

## Purpose

Harden the inventory domain so concurrent requests cannot:

```text
oversell stock
lose inventory adjustments
double-reserve units
release the same reservation twice
consume the same reservation twice
violate reserved_quantity <= quantity
```

This phase establishes the authoritative concurrency primitives that later Cart/Checkout/Order/Payment workflows must reuse.

It must build on:

```text
Phase 5.7 — Product availability
Phase 5.8 — Inventory read model
Phase 5.9 — Inventory mutation rules
```

Do not redesign Group C inventory.

---

# 1. Core Correctness Requirement

The system must guarantee:

```text
0 <= reserved_quantity <= quantity
```

under concurrency.

And:

```text
available_quantity
=
quantity - reserved_quantity
```

must never become negative.

---

# 2. Overselling Definition

Overselling occurs when concurrent operations successfully reserve or consume more units than are physically available.

Example:

```text
quantity = 1
reserved_quantity = 0
available = 1

Customer A wants 1
Customer B wants 1
```

Exactly one reservation may succeed.

The other must fail safely.

Final state must never become:

```text
reserved_quantity = 2
```

or:

```text
quantity < 0
```

---

# 3. Read Current Authoritative State First

Before implementation inspect:

```text
AGENTS.md
docs/api/api-contract.md
docs/api/api-resources.md
docs/api/api-conventions.md
docs/domain/business-rules.md
docs/decisions.md
docs/api/openapi.yaml
```

Then inspect current implementations of:

```text
ProductStock
InventoryAdjustmentService
idempotency infrastructure
audit infrastructure
Product/Variant availability services
cart/order schemas
```

Do not implement from assumptions.

---

# 4. Preserve Group C Model

Inventory remains:

```text
ProductStock
(
    product_variant_id,
    warehouse_location,
    quantity,
    reserved_quantity
)
```

Do not introduce:

```text
products.stock
product_variants.stock
available_quantity column
reservation_count column
```

---

# 5. Concurrency Strategy

For V1, use **database transactions with pessimistic row locking** for authoritative inventory mutations.

Preferred Laravel mechanism:

```text
DB::transaction(...)
+
lockForUpdate()
```

on the affected ProductStock rows.

Do not build a distributed lock service.

Do not introduce Redis locks for inventory correctness.

The database is the inventory authority.

---

# 6. Why Pessimistic Locking

The operations require authoritative read-modify-write semantics:

```text
read current quantity/reserved
validate
calculate
persist
```

Pessimistic row locking is simple and appropriate for the expected V1 transaction volume.

Do not introduce optimistic version columns unless a concrete need appears.

---

# 7. No `SELECT then UPDATE` Outside Lock

Forbidden:

```text
SELECT quantity, reserved_quantity
COMMIT / leave transaction

if available >= requested:
    UPDATE reserved_quantity
```

Two requests can both observe the same available stock.

All state-dependent validation must use the locked authoritative row.

---

# 8. Locked Read Pattern

Conceptually:

```text
DB::transaction(function () {
    $stock = ProductStock::query()
        ->whereKey(...)
        ->lockForUpdate()
        ->firstOrFail();

    // validate against locked state
    // mutate
    // persist
});
```

Use actual project abstractions.

Do not put this directly in controllers.

---

# 9. Inventory Adjustment Concurrency

Phase 5.9 `INV-003` must now be hardened.

Concurrent adjustments to the same Inventory row must serialize through row locking.

Example:

```text
quantity = 10

Request A: +5
Request B: -3
```

Final quantity must reflect both successful operations exactly once:

```text
12
```

not:

```text
15
7
```

from a lost update.

---

# 10. Adjustment Uses Locked State

Inside the lock:

```text
new_quantity
=
locked_current_quantity + quantity_delta
```

Do not calculate from a stale model instance loaded before the transaction.

---

# 11. Revalidate Reserved Boundary Under Lock

The rule:

```text
new_quantity >= reserved_quantity
```

must use the locked `reserved_quantity`.

Example:

```text
operator loads quantity=10 reserved=2
checkout reserves 6
operator later tries delta=-5
```

At mutation time the locked state may be:

```text
quantity=10 reserved=8
```

Therefore:

```text
new_quantity=5
```

must fail.

Do not trust earlier reads.

---

# 12. Checkout Reservation Primitive

Create a reusable inventory-domain primitive for reservation.

Conceptually:

```text
reserve(
    variant,
    quantity
)
```

or:

```text
InventoryReservationService
```

This primitive is infrastructure for later Group G checkout.

Do not implement the entire `/checkout` endpoint here.

---

# 13. Reservation Formula

For each locked Inventory row:

```text
available
=
quantity - reserved_quantity
```

Reservation succeeds only if:

```text
requested <= available
```

Then:

```text
reserved_quantity
=
reserved_quantity + requested
```

Physical:

```text
quantity
```

remains unchanged.

---

# 14. Reservation Failure

If:

```text
requested > available
```

fail with:

```text
422 INSUFFICIENT_STOCK
```

or the exact frozen checkout/domain mapping.

Do not partially reserve.

---

# 15. Multi-Location Inventory

A Variant may have multiple ProductStock rows.

Phase 5.10 must define deterministic reservation behavior across them.

Do not treat aggregate availability as sufficient without deciding which rows receive reservations.

---

# 16. No Client Location Selection

Customer must never choose:

```text
warehouse_location
```

during checkout.

Location selection is server-controlled.

---

# 17. Deterministic Location Allocation

If no newer repository rule exists, use a deterministic V1 strategy.

Preferred:

```text
warehouse_location ASC
then inventory row id ASC
```

Reserve from rows in that stable order.

Do not use arbitrary database order.

---

# 18. Reservation May Span Locations

If:

```text
main available = 2
dar-es-salaam available = 3
requested = 4
```

V1 may reserve:

```text
main = 2
dar-es-salaam = 2
```

if the current checkout contract permits aggregate multi-location fulfillment.

Before implementing this, inspect existing business rules.

If the repository requires fulfillment from one location only:

follow that instead.

Do not invent cross-location fulfillment silently.

---

# 19. If Multi-Location Allocation Is Undefined

Do not guess.

Record a minimal Group E decision before implementing checkout-facing reservation.

Preferred operationally simple rule for V1:

```text
reserve deterministically across existing inventory rows
until requested quantity is satisfied
```

only if no fulfillment-location contract contradicts it.

---

# 20. Lock All Candidate Rows Before Allocation

When reservation may span several locations:

load the candidate ProductStock rows inside one transaction using:

```text
lockForUpdate()
```

before calculating the allocation.

Do not lock one row, release logic, then discover another row later.

---

# 21. Lock Ordering

Always lock multiple ProductStock rows in deterministic order:

```text
product_variant_id ASC
warehouse_location ASC
id ASC
```

or the most appropriate stable order.

This reduces deadlock risk.

---

# 22. Multi-Item Checkout Deadlock Prevention

A checkout may contain multiple Variants.

Do not lock stock rows in cart insertion order.

Normalize target Variant IDs and lock in deterministic order.

Example:

```text
Variant 2
Variant 8
Variant 15
```

every concurrent checkout should acquire locks in the same order.

---

# 23. All-or-Nothing Reservation

For checkout containing:

```text
Variant A x2
Variant B x3
```

if A can reserve but B cannot:

rollback A.

Final result:

```text
no reservation
no Order
cart unchanged
```

No partial checkout.

---

# 24. Reservation Transaction Boundary

Future checkout transaction must encompass:

```text
validate Product state
validate Variant state
lock inventory
validate stock
reserve stock
recalculate price
create Order
create OrderItems
create status history
clear cart
persist idempotency success
```

as required by the checkout contract.

Phase 5.10 implements/reuses the inventory portion, not full checkout orchestration.

---

# 25. Reservation Is Not Consumption

At successful checkout:

```text
quantity
```

does not decrease.

Instead:

```text
reserved_quantity += requested
```

This preserves:

```text
physical stock
```

until payment success.

---

# 26. Reservation Lifecycle

Canonical lifecycle:

```text
checkout
→ reserve

payment success
→ consume reservation

cancellation/failure/expiry
→ release reservation
```

Do not invent a second stock lifecycle.

---

# 27. Release Primitive

Provide a reusable inventory-domain primitive for:

```text
release reservation
```

Later callers include:

```text
customer cancellation
admin cancellation
payment failure
system expiry
```

---

# 28. Release Formula

For each reserved quantity:

```text
reserved_quantity
=
reserved_quantity - release_quantity
```

Physical quantity remains unchanged.

---

# 29. Prevent Double Release

Release must never allow:

```text
reserved_quantity < 0
```

If the same business event retries:

idempotency/state validation must prevent a second release.

---

# 30. Release Must Be Atomic with Business Transition

Future cancellation/failure workflow must execute:

```text
order state change
+
reservation release
+
status history
+
audit/idempotency
```

atomically.

Do not provide a public free-floating release endpoint.

---

# 31. Consumption Primitive

Provide a reusable inventory-domain primitive for payment success.

Consumption converts held units into sold units.

---

# 32. Consumption Formula

For reservation quantity `q`:

```text
quantity
=
quantity - q

reserved_quantity
=
reserved_quantity - q
```

Therefore:

```text
available_quantity
```

remains unchanged by the conversion.

---

# 33. Example Consumption

Before payment:

```text
quantity = 10
reserved = 3
available = 7
```

Consume 3:

```text
quantity = 7
reserved = 0
available = 7
```

Correct.

---

# 34. Prevent Double Consumption

Repeated payment-success event must not consume twice.

Idempotent webhook/payment processing in Group H must cooperate with inventory state transition.

Phase 5.10 should make the inventory primitive safe to call inside such an idempotent transaction.

---

# 35. Reservation Ownership Problem

Do not decrement arbitrary `reserved_quantity` without knowing which Order owns the reservation.

Inspect current schema/contracts carefully.

If reservations are only represented as an aggregate integer today:

document the limitation.

---

# 36. Do Not Invent a Reservation Table Without Review

Group C intentionally deferred a reservation table.

Do not automatically introduce:

```text
inventory_reservations
```

unless correctness of release/consume cannot be guaranteed otherwise.

---

# 37. Reservation Traceability

Check whether existing OrderItems + order state are sufficient to determine:

```text
which Variant
how many units
```

for release/consumption.

If yes, reuse OrderItems as the business reservation record and `ProductStock.reserved_quantity` as the aggregate lock state.

---

# 38. Location Allocation Traceability

If reservations span multiple locations, OrderItems alone may not record:

```text
which location supplied which units
```

This is a real correctness issue for later release/consume.

Do not ignore it.

---

# 39. Minimal Location Allocation Persistence

If multi-location reservation is allowed and there is no existing way to reconstruct allocation:

introduce the smallest persistence needed to record reservation allocation.

For example conceptually:

```text
order_item_inventory_allocations
```

with:

```text
order_item_id
product_stock_id
quantity
```

only if necessary.

Do not add a generic warehouse-management subsystem.

---

# 40. Prefer No New Table If One-Location Rule Exists

If current business rules guarantee each Variant is reserved from exactly one location:

a new allocation table may be unnecessary.

Use the existing rule.

Do not create infrastructure before checking.

---

# 41. Allocation Record Requirements

If a reservation-allocation table is genuinely required:

it must be:

```text
server-created
immutable through ordinary APIs
foreign-keyed
integer quantity > 0
```

and must not be exposed to customers unless later required.

---

# 42. Allocation Is Not Stock Ledger

Do not turn allocation persistence into:

```text
stock movement history
warehouse transfer system
generic ledger
```

Keep it narrowly tied to reservation correctness.

---

# 43. Concurrency and Inventory Adjustment Interaction

Inventory adjustment and checkout reservation operate on the same ProductStock rows.

Both must acquire the same row locks.

This guarantees:

```text
adjust vs reserve
```

cannot independently validate stale state.

---

# 44. Race Example — Damage vs Checkout

Initial:

```text
quantity = 5
reserved = 0
```

Concurrent:

```text
checkout wants 5
operator DAMAGE -2
```

Valid outcomes are serialized.

Either:

```text
damage first:
quantity=3
checkout fails insufficient stock
```

or:

```text
checkout first:
reserved=5
damage -2 fails because new quantity 3 < reserved 5
```

Never:

```text
quantity=3
reserved=5
```

---

# 45. Race Example — Two Adjustments

Initial:

```text
quantity=10
```

Concurrent:

```text
+5
-3
```

Both may succeed sequentially.

Final:

```text
12
```

No lost update.

---

# 46. Race Example — Two Checkouts

Initial:

```text
quantity=1
reserved=0
```

Two checkout reservation attempts each request one.

Expected:

```text
one succeeds
one fails INSUFFICIENT_STOCK
```

Final:

```text
quantity=1
reserved=1
available=0
```

---

# 47. Race Example — Release vs Payment Success

Order reservation exists:

```text
quantity=10
reserved=2
```

Concurrent:

```text
payment success consume
cancellation release
```

Only the valid Order transition may win.

Inventory mutation must be coupled to authoritative Order state.

Do not allow both inventory actions to succeed independently.

---

# 48. Order Locking Dependency

When future workflows mutate Order state and inventory together:

lock the Order and relevant ProductStock rows in one deterministic transaction strategy.

Do not mutate stock independently of Order state validation.

---

# 49. Lock Ordering Across Domains

Choose and document a consistent lock order.

Example:

```text
Order
→ ProductStock rows sorted by ID
→ Cart if required
```

or another repository-approved ordering.

The key requirement is consistency across competing workflows.

---

# 50. Do Not Mix Lock Orders Arbitrarily

If checkout locks:

```text
Cart → Stock → Order
```

while cancellation locks:

```text
Order → Stock → Cart
```

deadlock risk increases.

Document one shared ordering before Group G/H implementations use it.

---

# 51. Deadlock Handling

Database deadlocks can still occur.

Use bounded transaction retry where Laravel/project conventions support it.

Conceptually:

```text
DB::transaction($callback, retryCount)
```

for deadlock retries.

Do not create an unbounded retry loop.

---

# 52. Retry Safety

Retries must only rerun operations that are safe under:

```text
transaction rollback
+
idempotency
```

Never retry after an external side effect already escaped the transaction.

---

# 53. No External Calls Inside Inventory Lock

Do not call:

```text
payment provider
email service
Clerk API
Cloudinary
```

while holding ProductStock row locks.

Keep lock duration short.

---

# 54. Transaction Duration

Inside the lock do only necessary:

```text
DB reads
business validation
DB mutation
audit/idempotency persistence
```

Move non-authoritative side effects outside after commit.

---

# 55. Isolation Level

Use the database's normal supported transaction isolation unless a demonstrated correctness gap requires changing it.

Do not globally change MySQL isolation level for this phase.

Explicit row locks are sufficient for the target V1 behavior.

---

# 56. SQLite Limitation

SQLite does not faithfully reproduce MySQL/InnoDB row-level concurrency or `SELECT ... FOR UPDATE` behavior.

This must be explicitly acknowledged.

---

# 57. Canonical SQLite Tests

SQLite may verify:

```text
inventory formulas
transaction rollback
business invariants
service orchestration
single-threaded reservation/release/consume semantics
```

But SQLite cannot prove:

```text
InnoDB row locking
deadlock behavior
true parallel overselling protection
```

---

# 58. MySQL/MariaDB Concurrency Verification Is Mandatory

Use a disposable real MySQL/MariaDB database for concurrency tests.

Phase 5.10 should not be marked fully PASS solely from SQLite tests.

---

# 59. Disposable Database

Use only:

```text
furnitureapp_test_disposable
```

or the repository's exact approved disposable DB name.

Verify:

```text
APP_ENV != production
```

and exact DB name before destructive setup.

---

# 60. Separate Connections

True concurrency tests must use independent database connections/processes.

Do not simulate concurrency by sequential calls on one transaction/connection.

---

# 61. Parallel Execution

Tests should start competing transactions close enough that they actually contend for the same stock rows.

Use the project's available process/concurrency mechanism.

Do not mock `lockForUpdate()` and claim concurrency coverage.

---

# 62. Repeat Race Tests

Concurrency tests can be nondeterministic.

Repeat core scenarios:

```text
10–20 iterations
```

or another reasonable stable count.

Every iteration must preserve invariants.

---

# 63. Test — Concurrent Reservation Last Unit

Setup:

```text
quantity=1
reserved=0
```

Run two independent reservation attempts for `1`.

Assert:

```text
success count = 1
failure count = 1
final quantity = 1
final reserved = 1
final available = 0
```

---

# 64. Test — Concurrent Reservation Capacity

Setup:

```text
quantity=10
reserved=0
```

Run multiple reservation attempts totaling more than 10.

Assert successful total reserved:

```text
<= 10
```

and final invariant holds.

---

# 65. Test — Concurrent Adjustments

Run:

```text
+5
-3
```

against quantity 10.

Assert final:

```text
12
```

and both successful operations are audited exactly once.

---

# 66. Test — Concurrent Negative Adjustments

Setup:

```text
quantity=5
reserved=0
```

two simultaneous:

```text
-4
-4
```

Only one can succeed if the second would make quantity negative.

---

# 67. Test — Adjustment vs Reservation

Use the damage/checkout race example.

Assert no outcome violates:

```text
reserved <= quantity
```

---

# 68. Test — Reservation vs Reservation Multi-Location

If multi-location allocation is supported:

concurrent reservations must not oversubscribe aggregate stock or any row.

---

# 69. Test — Rollback Across Multiple Rows

Request needs inventory from multiple rows.

Force failure after one row would be updated.

Transaction rollback must restore all rows.

---

# 70. Test — Multi-Item Atomicity

Reserve Product A successfully, Product B insufficient.

Assert no stock remains reserved for A.

---

# 71. Test — Release

Create a known reservation.

Release once.

Assert:

```text
reserved decreases correctly
quantity unchanged
```

---

# 72. Test — Double Release Protection

Attempt the same business release twice through the appropriate idempotent/state-aware path.

Final reservation must not go negative.

---

# 73. Test — Consume

Known reservation:

```text
quantity=10
reserved=3
```

consume 3:

```text
quantity=7
reserved=0
```

---

# 74. Test — Double Consume Protection

Repeated success event must not:

```text
quantity=4
```

after already consuming the same 3.

Inventory action must cooperate with business idempotency/state.

---

# 75. Test — Consume vs Release Race

One payment-success and one cancellation path contend for the same Order/reservation.

Exactly one valid business transition succeeds.

Inventory remains consistent.

---

# 76. Test — Lock Timeout / Deadlock Mapping

If the DB reports a concurrency failure after bounded retries:

map to existing:

```text
409 CONFLICT
RESOURCE_VERSION_CONFLICT
```

where appropriate.

Do not leak SQLSTATE/database internals.

---

# 77. Do Not Map Stock Shortage to Generic Conflict

If locked authoritative state proves:

```text
requested > available
```

return:

```text
422 INSUFFICIENT_STOCK
```

This is a business condition, not necessarily a system conflict.

---

# 78. Stale Mutation Conflict

Use:

```text
409 RESOURCE_VERSION_CONFLICT
```

only for genuine stale/concurrency semantics defined by the project.

Do not use 409 for every inventory validation failure.

---

# 79. Idempotency Still Applies

Concurrency protection does not replace idempotency.

For INV-003:

```text
same key same request
→ one mutation
```

even if requests arrive simultaneously.

---

# 80. Atomic Idempotency Claim

Concurrent requests with the same Idempotency-Key must not both enter the business mutation.

Use the shared idempotency store's unique constraint/claim mechanism inside the correct boundary.

---

# 81. Same-Key Race Test

Send two simultaneous identical INV-003 requests with the same key.

Assert:

```text
quantity adjusted once
one durable idempotency result
one audit event
both callers receive compatible replay/success semantics
```

---

# 82. Different-Key Race Test

Same adjustment intent with two distinct keys represents two operations.

Both may execute if invariants permit.

Do not deduplicate by body alone.

---

# 83. Audit Concurrency

Audit records must correspond exactly to committed privileged mutations.

Rolled-back attempts must not appear as successful state-change audits.

---

# 84. Lock Scope Must Be Minimal

Do not lock:

```text
all inventory rows
entire products table
all variants
```

for one mutation.

Lock only the rows necessary for the business operation.

---

# 85. No Table Locks

Do not use explicit table-level locks for V1 inventory.

That would unnecessarily serialize the whole store.

---

# 86. Index Support

Ensure lock lookup uses indexed identifiers:

```text
ProductStock primary key
product_variant_id
unique variant/location
```

Do not scan inventory table before locking.

---

# 87. Reservation Query Efficiency

When locking stock for one Variant, query only rows for that Variant.

Use deterministic indexed ordering.

---

# 88. Public Availability During Reservations

Phase 5.7 availability derives:

```text
quantity - reserved_quantity
```

Therefore a successful checkout reservation must immediately reduce public available inventory on subsequent reads.

No separate availability mutation.

---

# 89. Reservation Threshold Example

Before:

```text
quantity=6
reserved=0
available=6
stock_indicator=IN_STOCK
```

Reserve 1:

```text
quantity=6
reserved=1
available=5
```

Subsequent catalog read may become:

```text
LOW_STOCK
```

according to Phase 5.7.

---

# 90. Consumption Does Not Change Available Count

Before consumption:

```text
quantity=6
reserved=1
available=5
```

Consume 1:

```text
quantity=5
reserved=0
available=5
```

Public availability remains unchanged.

This is correct.

---

# 91. Release Increases Available Count

Before:

```text
quantity=6
reserved=1
available=5
```

Release 1:

```text
quantity=6
reserved=0
available=6
```

Public availability increases.

---

# 92. Adjustment Availability Interaction

INV-003 must use the same locked rows and cannot reduce physical quantity below reservations.

This protects active checkout holds.

---

# 93. Cart Does Not Reserve

Group F cart operations should not reserve inventory.

Do not introduce reservation when adding an item to cart.

Final reservation happens at checkout.

---

# 94. Availability in Cart Is Informational

Cart may validate/display current availability, but inventory can change.

Checkout must revalidate under lock.

---

# 95. No Long-Lived Cart Locks

Never hold DB locks while a user browses or sits on a checkout screen.

Locks exist only for short server transactions.

---

# 96. Pending-Payment Reservation

The current contract intentionally holds reservation after successful checkout while Order is:

```text
PENDING_PAYMENT
```

Do not release it simply because payment has not happened yet.

---

# 97. Delivery Fee Pending Does Not Release

For DELIVERY:

```text
checkout
→ PENDING_PAYMENT
→ delivery_fee_status=PENDING
```

reservation remains held while Staff/Admin finalizes fee.

ORD-014 success/failure does not by itself release inventory.

---

# 98. Terminal Failure Releases

Release occurs when the Order transitions to a terminal non-fulfilled state such as:

```text
CANCELLED
```

through approved customer/admin/system/payment failure workflows.

---

# 99. Do Not Add EXPIRED Inventory State

The current contract maps timeout/expiry behavior to:

```text
CANCELLED
```

not a new inventory/order state.

Do not invent:

```text
EXPIRED
```

as inventory state.

---

# 100. Payment Success Consumption

Group H payment webhook will eventually invoke consumption within the same protected order/payment transition.

Phase 5.10 should expose the primitive but not implement payment provider behavior.

---

# 101. No Payment Integration

Do not:

```text
call provider
verify webhook signatures
create Payment records
```

in Phase 5.10.

Group H owns that.

---

# 102. No Checkout Endpoint Implementation

Do not implement full:

```text
POST /api/v1/checkout
```

unless the current roadmap explicitly moved it forward.

Create concurrency-safe inventory primitives and tests that Group G can consume.

---

# 103. No Cart Endpoint Implementation

Do not implement Group F cart logic.

---

# 104. No Order Transition Controllers

Do not implement cancellation/payment actions solely to test inventory primitives.

Use domain/service-level test fixtures where possible.

---

# 105. Transaction Service Design

Prefer one focused inventory concurrency service/domain layer.

Conceptually:

```text
InventoryAllocator
```

with operations such as:

```text
reserve()
release()
consume()
adjust()
```

or use existing naming conventions.

---

# 106. Avoid God Service

Do not mix:

```text
pricing
payments
orders
emails
catalog serialization
```

into InventoryAllocator.

It owns stock concurrency only.

---

# 107. Domain Result

Return explicit domain results/errors.

Do not expose raw DB lock objects or SQL exceptions to controllers.

---

# 108. Central Invariant Helper

Centralize checks such as:

```text
available = quantity - reserved
quantity >= reserved
requested <= available
```

Do not duplicate arithmetic across four workflows.

---

# 109. Keep `available_quantity` Derived

Still do not persist it.

Locks operate on:

```text
quantity
reserved_quantity
```

only.

---

# 110. Schema Changes

Preferred:

```text
NONE
```

for ProductStock itself.

Pessimistic locking needs no version column.

---

# 111. Conditional Schema Addition

Only add a reservation-allocation table if required by the already-approved multi-location reservation semantics and no existing schema can reconstruct exact allocation.

Document why it is necessary.

Do not add speculative persistence.

---

# 112. No `lock_version`

Do not add optimistic locking fields if pessimistic locking is the chosen V1 approach.

---

# 113. MySQL / MariaDB Is Concurrency Authority

Production correctness relies on InnoDB row locking.

Use a storage engine/configuration that actually supports transactions and row locks.

Verify the test database tables use the expected engine where applicable.

---

# 114. SQLite Is Not Concurrency Proof

Repeat in documentation/completion report:

```text
SQLite tests prove business semantics.
They do not prove InnoDB row-lock concurrency.
```

---

# 115. MySQL Test Harness

Use a disposable MySQL/MariaDB integration test harness.

Do not reuse the normal development DB.

---

# 116. No Production Test Hooks

Do not add:

```text
X-Test-Delay
X-Test-User
sleep query parameter
```

to production controllers to create races.

Concurrency tests should orchestrate processes/connections from test code.

---

# 117. Test Synchronization

Use test-only barriers/process synchronization where necessary so transactions actually overlap.

Keep test instrumentation outside production request semantics.

---

# 118. Real Transactions

Concurrency test workers must commit/rollback real independent transactions.

Mocking the DB transaction facade is insufficient.

---

# 119. Verify Database State After Every Race

Do not assert only HTTP status.

Always inspect final:

```text
quantity
reserved_quantity
available_quantity
audit count
idempotency count
```

as relevant.

---

# 120. Invariant Sweep

After each concurrency test assert globally for affected rows:

```text
quantity >= 0
reserved_quantity >= 0
reserved_quantity <= quantity
```

---

# 121. No Partial Order Reservation

Where test scaffolding includes Order creation:

failed stock allocation must leave:

```text
no partial order
no partial OrderItems
no partial status history
no stock reservation
```

---

# 122. Error Stability

Reuse the frozen codes:

```text
INSUFFICIENT_STOCK
CONFLICT
RESOURCE_VERSION_CONFLICT
DUPLICATE_OPERATION
```

Do not invent:

```text
STOCK_LOCKED
RACE_DETECTED
DEADLOCK_ERROR
```

in V1.

---

# 123. Database Exceptions

Translate expected concurrency/database failures into domain/API errors.

Do not leak:

```text
SQLSTATE
table name
lock wait SQL
deadlock trace
```

to clients.

---

# 124. Logging

Log unexpected concurrency failures with:

```text
request_id
operation
resource identifiers
safe error context
```

Do not log secrets/idempotency keys in full.

---

# 125. Observability

Where existing logging conventions support it, distinguish:

```text
insufficient stock
deadlock retry
deadlock exhausted
idempotency replay
inventory adjustment conflict
```

without creating a new monitoring system.

---

# 126. Rate Limiting

Keep Phase 5.9 rate limiting for INV-003.

Concurrency handling does not replace abuse controls.

---

# 127. Audit

Keep the Phase 5.9 rule:

successful INV-003 adjustment produces exactly one durable audit entry.

Reservation/consumption audit behavior should follow the owning checkout/order/payment workflow contracts later.

Do not create duplicate audit events merely for internal helper calls.

---

# 128. Performance

Row locking should be short-lived.

Do not optimize away correctness for throughput.

Expected V1 furniture-store transaction volume does not justify distributed inventory infrastructure.

---

# 129. No Redis Inventory Authority

Redis may later cache read data, but must not become authoritative stock state.

Do not implement Redis counters for available stock.

---

# 130. No Eventual-Consistency Inventory

Checkout cannot accept an eventually consistent search/cache value as final stock authority.

Final state comes from MySQL/MariaDB transaction.

---

# 131. No Queue-Based Reservation

Do not enqueue checkout inventory reservation and respond before it commits.

Customer checkout requires synchronous success/failure.

---

# 132. No Global Mutex

Do not serialize every checkout through one application-wide mutex.

Lock only affected rows.

---

# 133. Multiple Variant Lock Ordering

For cart variants:

```text
sort unique Variant IDs
```

before resolving/locking their stock rows.

Ensure every checkout follows the same order.

---

# 134. Duplicate Cart Variant Handling

If the same Variant somehow appears more than once in checkout input/cart representation:

aggregate required quantity before locking/reservation.

Do not reserve it in separate passes.

---

# 135. Integer Arithmetic Only

Inventory quantities remain integers.

No decimal quantities.

No floating-point arithmetic.

---

# 136. Upper Bounds

Use existing quantity bounds where contracts define them.

Do not invent huge-unbounded requested quantities that could overflow integer operations.

---

# 137. Transaction Callback Exceptions

Domain failure inside transaction must throw/return in a way that triggers rollback.

Do not catch an invariant exception inside the transaction and then commit partial state.

---

# 138. After-Commit Side Effects

If later workflows need notifications:

dispatch them after successful commit.

Do not notify customer of reservation/order success before transaction commits.

---

# 139. Product/Variant State Revalidation

The inventory primitive itself should not duplicate every catalog business rule unless required.

The checkout orchestration later must validate:

```text
Product exists
active
published
IN_STOCK
Variant belongs
Variant active
```

before/inside the same business transaction.

Inventory service owns stock state, not catalog eligibility.

---

# 140. Lock State Close to Mutation

Where product/variant state can affect validity and can change concurrently in future admin APIs, Group G may need to lock or otherwise revalidate those rows too.

Document the dependency.

Do not prematurely lock all Product rows in Phase 5.10 without a mutation path requiring it.

---

# 141. Inventory Adjustment Target

INV-003 continues to target one Inventory resource ID established in Phase 5.9.

The locked row must be that exact ProductStock record.

---

# 142. Adjustment Idempotency + Row Lock

Recommended ordering:

```text
authenticate
authorize
validate request
claim/check idempotency
begin/participate transaction
lock ProductStock
revalidate invariants
mutate
audit
store idempotent success
commit
```

Fit this to the shared idempotency infrastructure.

---

# 143. Do Not Hold Row Lock While Waiting on Idempotency Conflict Externally

Idempotency claiming must be designed so duplicate callers do not perform duplicate mutations.

Keep lock ordering deterministic between idempotency and inventory resources.

---

# 144. Idempotency Deadlock Review

If idempotency records are themselves locked:

document a consistent ordering such as:

```text
idempotency record
→ domain aggregate
→ ProductStock rows
```

and reuse it across operations where applicable.

---

# 145. Checkout Idempotency

Future CHK-001 uses Idempotency-Key too.

A retry must return the same Order and must not create additional reservation.

Phase 5.10's reservation primitive must support that transactional behavior.

---

# 146. Reservation Record Idempotency

Do not rely on:

```text
reserved_quantity already > 0
```

to guess whether a retry previously reserved stock.

The business operation/idempotency record determines that.

---

# 147. Release Record Idempotency

Likewise, `reserved_quantity` alone cannot identify whether one Order's reservation was already released.

Order state/idempotent transition owns that decision.

---

# 148. Consume Record Idempotency

Payment event/order state owns whether reservation consumption has already occurred.

Inventory helper should be called only within that protected transition.

---

# 149. Completion Status Rule

Phase 5.10 should not be marked PASS unless true MySQL/MariaDB concurrent integration tests run successfully.

SQLite-only verification is insufficient for this phase.

---

# 150. If MySQL Harness Is Unavailable

Report:

```text
Implementation complete
SQLite semantic tests PASS
Phase 5.10 BLOCKED on real concurrency verification
```

Do not weaken the exit gate.

---

# 151. Relationship to Phase 5.5 Blocker

Phase 5.5's MySQL FULLTEXT verification remains independent.

If the disposable MySQL harness now exists, both targeted suites may be run.

Do not conflate their results.

---

# 152. PHPStan

Phase 5.10 must introduce:

```text
0 new PHPStan errors
```

If the known project-wide baseline remains unresolved:

report it separately.

Do not falsely mark global quality PASS.

---

# 153. Schema Migration Safety

If any narrowly justified allocation migration is needed:

use a new migration.

Never edit Group C history.

---

# 154. Destructive Test Safety

Before:

```text
migrate:fresh --seed --force
```

require:

```text
APP_ENV != production
AND
DB_DATABASE == approved disposable test DB
```

Use the existing repository guard exactly.

---

# 155. No Frontend Changes

Do not modify:

```text
frontend/web/
frontend/app/
frontend/design-system/
```

No concurrency behavior belongs in the clients.

---

# 156. No New External Dependencies

Expected:

```text
NONE
```

Laravel transactions and MySQL/InnoDB are sufficient.

---

# 157. Code Quality

Maintain:

```text
cognitive complexity <= 15
<= 3 returns where practical
small transaction services
single inventory arithmetic authority
deterministic lock ordering
no duplicated reservation formulas
```

---

# 158. Likely Implementation Areas

Expected:

```text
app/Services/Inventory/
app/Actions/Inventory/
ProductStock model/helpers
InventoryAdjustmentService
shared transaction/idempotency integration
tests/Integration/
tests/Feature/
docs/api/
docs/domain/
docs/decisions.md
```

Modify only what is required.

---

# 159. Verification — Canonical Suite

Run:

```bash
php artisan test
vendor/bin/pint --test
vendor/bin/phpstan analyse
composer audit
git diff --check
```

---

# 160. Verification — MySQL Concurrency

Against the approved disposable MySQL/MariaDB DB, run targeted tests for:

```text
reserve vs reserve
adjust vs adjust
adjust vs reserve
multi-item rollback
same-key idempotency race
release vs consume where scaffolded
```

Use independent connections/processes.

---

# 161. Completion Report

Return:

## Phase 5.10 status

```text
PASS
```

or:

```text
BLOCKED
```

## Concurrency strategy

State:

```text
DB transaction
pessimistic row locking
deterministic lock order
bounded deadlock retries
```

as actually implemented.

## Inventory adjustment

Confirm lost updates are prevented.

## Reservation

State exact atomic reservation semantics.

## Multi-location

State exact allocation rule and whether allocation persistence was required.

## Release

State exact reservation-release behavior.

## Consumption

State exact reserved→sold conversion.

## Overselling

Report real concurrent last-unit test result.

## MySQL verification

Report:

```text
PASS
NOT RUN
BLOCKED
```

with exact reason.

## SQLite

Explicitly state what it does and does not verify.

## Schema

State:

```text
NONE
```

unless a narrowly required allocation table was introduced.

## Phase 5.5

Report its independent MySQL FULLTEXT/PHPStan state separately.

## Frontend

Must state:

```text
NONE
```

## Tests

Report exact counts and race repetitions.

## Quality

Report:

```text
Pint
PHPStan
Composer audit
git diff --check
```

---

# 162. Definition of Done

Phase 5.10 is complete only when:

* ProductStock remains authoritative;
* `available_quantity` remains derived;
* `reserved_quantity <= quantity` remains true under concurrency;
* inventory adjustments lock authoritative rows;
* concurrent adjustments do not lose updates;
* checkout-facing reservation primitive exists;
* stock validation happens against locked current state;
* two concurrent buyers cannot reserve the same last unit;
* reservation increments only `reserved_quantity`;
* reservation does not decrement physical quantity;
* multi-item reservation is all-or-nothing;
* multiple stock rows are locked deterministically;
* multiple Variants are locked deterministically;
* adjustment vs reservation races preserve invariants;
* release decrements reserved quantity only;
* consumption decrements both physical and reserved quantity;
* release/consume cannot be safely double-applied by retrying the owning business operation;
* no public reservation/release endpoint is introduced;
* cart does not reserve inventory;
* public availability reflects reservations automatically;
* row locks are held only for short DB work;
* no external service calls happen inside stock locks;
* expected concurrency errors map to stable API/domain codes;
* SQL/database internals do not leak;
* true MySQL/MariaDB concurrent tests use separate connections/processes;
* last-unit overselling race passes repeatedly;
* SQLite is not treated as concurrency proof;
* no Product/Variant inventory duplication is introduced;
* no Redis/distributed lock system is introduced;
* no frontend changes occur;
* no new PHPStan errors are introduced;
* Pint passes;
* Composer audit has no new blocker.

---

# 163. Out of Scope

Do not implement:

```text
full checkout controller/workflow
cart APIs
payment provider/webhooks
customer cancellation endpoint
admin cancellation endpoint
system TTL worker
warehouse transfer
inventory forecasting
stock replenishment automation
distributed locking
Redis inventory counters
inventory analytics
frontend concurrency UX
```

Only provide the safe inventory concurrency primitives required by those later workflows.

---

# 164. STOP Condition

STOP when MySQL/MariaDB is proven to preserve this invariant under real concurrent operations:

```text
quantity >= 0
reserved_quantity >= 0
reserved_quantity <= quantity
available_quantity = quantity - reserved_quantity
```

and the canonical race:

```text
1 physical unit
2 concurrent buyers
```

results in:

```text
exactly 1 successful reservation
exactly 1 INSUFFICIENT_STOCK failure
0 oversold units
```

while concurrent Staff/Admin inventory adjustments cannot invalidate existing reservations or lose updates.

Do not continue automatically to Phase 5.11.

DO NOT COMMIT OR PUSH.

The project owner handles all Git operations.
