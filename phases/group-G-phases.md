# Phase 7.7 — Transaction Boundaries

## Purpose

Implement the authoritative transactional boundary for CHK-001 Checkout.

This phase owns the atomicity, locking, rollback, idempotency-completion, Order aggregate persistence, inventory reservation coordination, and Cart-clear semantics required to safely turn a validated Cart snapshot into a pending Order.

The core invariant is:

```text
either Checkout commits completely

or

Checkout leaves no business side effect
```

A successful Checkout transaction must eventually commit together:

```text
idempotency success
Cart final locked snapshot
authoritative Product/Variant/price snapshot
Order
OrderItems
initial PENDING_PAYMENT history
fulfillment snapshot
inventory reservation
exact reservation allocations
Cart item deletion
```

A failed Checkout transaction must leave:

```text
no Order
no OrderItems
no history
no reservation
no allocation
no Cart clear
no successful idempotency result
```

No payment-provider call belongs inside this transaction.

---

# 1. Current Group G State

Treat the current repository status as:

```text
7.1  PASS — Checkout requirements
7.2  PASS — Address model review
7.3  PASS — Pickup flow
7.4  BLOCKED — DELIVERY billing snapshot persistence model gap
7.5  PASS — Delivery fee rules / ORD-014
7.6  PASS — Canonical Order totals
7.7  CURRENT — Transaction boundaries
```

Do not mark Phase 7.4 PASS.

Do not mark Group G closed.

---

# 2. Phase 7.4 Blocker Still Applies

ADR/BACKEND-038 remains authoritative:

```text
DELIVERY branch can project:
- delivery snapshot
- billing snapshot
- pending delivery fee
- provisional total

but cannot persist the frozen billing snapshot
```

because there is no currently approved persistence location for:

```text
billing_address
```

Therefore Phase 7.7 must NOT silently persist an incomplete DELIVERY Order.

---

# 3. What Phase 7.7 May Implement

Phase 7.7 may implement:

```text
shared Checkout transaction coordinator
PICKUP transaction path
Order + OrderItem persistence
inventory reservation orchestration
initial status history
Cart clearing
idempotency atomic completion
lock ordering
deadlock retry
rollback guarantees
transaction-focused tests
```

---

# 4. What Phase 7.7 Must Not Claim

Do not claim:

```text
DELIVERY Checkout persistence complete
CHK-001 fully production-ready
Group G complete
```

while Phase 7.4 remains blocked.

---

# 5. Route Activation Boundary

Do not activate a partially supported public CHK-001 route merely because the transaction service exists.

Phase 7.8 still owns:

```text
complete request validation
transport/schema orchestration
final route activation decision
```

The safest outcome for 7.7 is:

```text
internal transaction workflow implemented and tested
public Checkout route still not exposed as incomplete DELIVERY support
```

unless the existing roadmap explicitly says route activation occurs here.

---

# 6. Canonical Transaction Coordinator

Introduce or complete one Checkout application service.

Examples:

```text
CheckoutTransaction
CreateCheckoutOrder
CheckoutService
ExecuteCheckout
```

Use repository naming conventions.

It must own orchestration, not reimplement domain rules.

---

# 7. Service Responsibilities

The coordinator may own:

```text
transaction creation
lock ordering
Cart locking
final Cart snapshot
authoritative line resolution
calling OrderTotalsCalculator
creating Order aggregate
calling InventoryAllocator
creating initial history
clearing Cart
completing idempotency success
```

---

# 8. It Must Not Own

Do not duplicate:

```text
Cart eligibility rules
catalog visibility rules
money formulas
inventory allocation algorithm
address normalization
fee finalization
payment
```

Reuse existing authorities.

---

# 9. Reuse Canonical Components

Expected authorities include:

```text
CartItemEligibility / equivalent
OrderTotalsCalculator
PickupFulfillmentState
DeliveryFulfillmentState
InventoryAllocator
ReferenceGenerator
IdempotencyService
```

Use actual repository names.

---

# 10. Transaction Scope

The entire business mutation must run inside one DB transaction.

Conceptually:

```text
BEGIN

idempotency coordination
Cart lock
Cart snapshot
catalog/variant revalidation
authoritative pricing
totals calculation
fulfillment projection
Order insert
OrderItem inserts
inventory reserve
status history insert
Cart item clear
idempotency success/result write

COMMIT
```

---

# 11. No External Calls Inside Transaction

Do not call:

```text
Clerk API
payment provider
email
push
maps/geocoding
external shipping service
```

inside the transaction.

Authentication should already be resolved before entering the business transaction.

---

# 12. Keep Transaction Short

Do not perform:

```text
network calls
large serialization work
notification delivery
file operations
sleep/backoff while locks are held
```

inside a successful transaction body.

Retries should restart a rolled-back transaction.

---

# 13. Idempotency Must Participate Atomically

Phase 7.1 identified an implementation gap:

```text
successful idempotency result must commit atomically
with the Checkout business transaction
```

Close that gap here.

---

# 14. No Second Idempotency System

Reuse:

```text
IdempotencyService
```

Do not create:

```text
CheckoutIdempotencyService
CheckoutKeys
CheckoutReplayTable
```

---

# 15. Preserve HTTP Status

CHK-001 success is:

```text
201
```

Same-key replay must also return:

```text
201
```

not a hard-coded:

```text
200
```

---

# 16. Shared Idempotency Improvement

If the shared service currently assumes `200`, generalize it safely so stored outcomes can preserve:

```text
HTTP status
response payload
fingerprint
```

without breaking existing:

```text
INV-003
CART-005
ORD-014
```

behavior.

---

# 17. Regression Requirement for Shared Service

Any generic idempotency refactor must rerun existing idempotent operations.

Do not fix Checkout by breaking earlier phases.

---

# 18. Idempotency Replay Ordering

For CHK-001:

```text
authenticate CUSTOMER
validate Idempotency-Key
validate/normalize request
derive fingerprint
check matching completed result
```

must occur before treating an empty Cart as a new failure.

---

# 19. Replay After Cart Clear

This is mandatory.

First request:

```text
Checkout succeeds
Cart items cleared
```

Retry same key/same intent:

```text
must replay original 201
```

It must NOT fail:

```text
422 CART_INVALID
```

because the Cart is now empty.

---

# 20. Same Key Different Intent

If the same key is reused with changed:

```text
fulfillment_type
delivery_address
```

return:

```text
409 DUPLICATE_OPERATION
```

---

# 21. Fingerprint Inputs

Use normalized logical request data.

Include:

```text
authenticated identity scope
fulfillment_type
normalized delivery_address
```

Do not include volatile:

```text
stock
current price
Cart timestamp
Order reference
```

---

# 22. Identity Scope

Same key from another Customer is a separate scope.

No cross-user replay.

---

# 23. Cart Authority

Checkout derives the authenticated CUSTOMER's own:

```text
ACTIVE Cart
```

No client Cart selector.

---

# 24. Customer-Only Boundary

Preserve the current security baseline:

```text
CUSTOMER only
```

STAFF and ADMIN must not use customer Checkout.

Do not reopen the old self-commerce behavior.

---

# 25. Cart Must Be Locked

Inside the transaction:

```text
SELECT active Cart ... FOR UPDATE
```

or equivalent repository pattern.

---

# 26. Missing Cart

Missing ACTIVE Cart:

```text
422 CART_INVALID
```

for a new execution.

---

# 27. Empty Cart

Empty ACTIVE Cart:

```text
422 CART_INVALID
```

for a new execution.

---

# 28. Lock Cart Before Final Snapshot

Do not:

```text
load Cart
calculate
then lock
```

The locked Cart is the authoritative Checkout snapshot.

---

# 29. Lock Prevents Concurrent Checkout

Two different Idempotency-Keys against the same Cart must not both create Orders.

Cart row locking must ensure:

```text
at most one successful Checkout
```

---

# 30. Different-Key Race

Conceptually:

```text
request A locks Cart
request B waits

A succeeds + clears items + commits

B acquires Cart
B sees empty Cart
B fails CART_INVALID
```

No second Order.

---

# 31. Cart Mutations vs Checkout

Checkout must be safe against concurrent:

```text
add
update
remove
merge
```

operations.

The Cart ACTIVE-state and locking rules must serialize correctly.

---

# 32. Lock Compatibility With CART-005

CART-005 already has deterministic Cart locking.

Do not introduce a reversed lock order that creates avoidable deadlocks.

---

# 33. Cart Item Snapshot

After Cart lock:

load the authoritative CartItems.

Do not rely on earlier API projection.

---

# 34. Revalidate Product

Inside the Checkout transaction re-evaluate current Product state:

```text
exists
active
published
not soft-deleted
active category
IN_STOCK
```

---

# 35. MADE_TO_ORDER

Reject:

```text
422 PRODUCT_NOT_PURCHASABLE
```

---

# 36. Revalidate Variant

Variant must:

```text
exist
belong to Product
be active
match CartItem
```

---

# 37. Do Not Trust Cart `is_purchasable`

Cart projection is informational.

Recompute eligibility from authoritative records inside Checkout.

---

# 38. Current Price Authority

Use the current authoritative Variant price at the transaction point.

Do not use:

```text
Cart response unit_price
old client price
cached frontend value
```

---

# 39. OrderTotalsCalculator Authority

For each trusted line:

```text
unit price
quantity
```

use:

```text
OrderTotalsCalculator
```

for:

```text
line totals
subtotal
branch financial result
```

---

# 40. No Competing Arithmetic

Do not calculate:

```text
unit_price * quantity
SUM(...)
subtotal + fee
```

again inside Checkout orchestration except through the calculator's API.

---

# 41. Line Total Consistency

The calculator now rejects inconsistent supplied line totals.

Use this behavior rather than trusting a caller-provided line total.

---

# 42. Order Snapshot Fields

Each future persisted OrderItem must snapshot:

```text
product_id
variant_id
SKU
product name
variant name
quantity
unit price
line total
```

using current transaction-time data.

---

# 43. Historical Snapshot

Once stored:

```text
Product rename
Variant rename
price change
Product deletion
```

must not alter the OrderItem historical fields.

---

# 44. Reference Generation

Use:

```text
ReferenceGenerator
```

for:

```text
OD-*****
```

Do not use faker/random test code in production.

---

# 45. Opaque Order ID

Use existing:

```text
ord_...
```

identifier behavior.

Do not expose numeric DB IDs.

---

# 46. Initial Order State

Persist:

```text
PENDING_PAYMENT
```

only.

---

# 47. PICKUP Financial State

For PICKUP:

```text
delivery_fee = 0
delivery_fee_status = FINALIZED
total = subtotal
```

via canonical totals.

---

# 48. PICKUP Addresses

Persist:

```text
delivery_address = null
billing_address = null
```

where the current schema supports the existing PICKUP semantics.

---

# 49. DELIVERY Financial Projection

For DELIVERY:

```text
delivery_fee = null
delivery_fee_status = PENDING
total = subtotal
```

remains valid as a projection.

---

# 50. DELIVERY Persistence Block

Do not persist a complete DELIVERY Order until the billing snapshot model gap is resolved.

---

# 51. Explicit Guard

If the transaction service can receive DELIVERY input before the blocker is solved:

fail before any mutation.

Use an internal/domain blocker appropriate to the phase.

Do not expose a half-created Order.

---

# 52. Do Not Invent Public Error

Phase 7.7 should not invent a new V1 public error just for an internal unfinished implementation.

The route should remain unactivated until 7.8 if necessary.

---

# 53. No Fake Billing Persistence

Forbidden workarounds:

```text
billing_address omitted
billing snapshot dropped
delivery_address reused as billing storage without explicit schema
billing stored in notes
billing stored in Delivery row
billing stored in metadata
```

---

# 54. Order Insert Ordering

Because `InventoryAllocator::reserve(Order)` works from an Order and its items:

the Order aggregate may need to exist inside the open transaction before reservation.

That is acceptable.

---

# 55. Important Atomicity Rule

Creating:

```text
Order
OrderItems
```

before reservation inside the transaction does NOT create a partial business record if reservation failure rolls the transaction back.

---

# 56. Correct Internal Sequence

A likely safe sequence is:

```text
1. lock Cart
2. snapshot/revalidate lines
3. calculate totals
4. build fulfillment state
5. insert Order
6. insert OrderItems
7. reserve(Order)
8. insert initial history
9. clear Cart items
10. store idempotency success
11. commit
```

Adapt to actual repository requirements.

---

# 57. InventoryAllocator Lock Order

Do not change its established order.

It owns:

```text
Order row
→ ProductStock rows id ASC
```

with variant normalization and deterministic allocation.

---

# 58. Order Lock

If `reserve(Order)` locks the just-inserted Order row:

ensure this works correctly inside the same transaction.

Do not bypass its lock merely because the Order was just created.

---

# 59. ProductStock Locks

Use only:

```text
InventoryAllocator
```

to perform reservation.

Do not manually increment:

```text
reserved_quantity
```

inside Checkout.

---

# 60. Reservation Arithmetic

Existing authority remains:

```text
available = quantity - reserved_quantity
```

---

# 61. Reservation Effect

On Checkout success:

```text
reserved_quantity += ordered quantity
quantity unchanged
```

---

# 62. Physical Quantity

Do not decrement:

```text
quantity
```

during Checkout.

Consumption belongs later.

---

# 63. Exact Allocations

Reservation must persist:

```text
order_item_inventory_allocations
```

so later:

```text
release(Order)
consume(Order)
```

target the exact same stock rows.

---

# 64. Multi-Location

Do not add single-location assumptions.

Existing allocator may span multiple stock locations.

---

# 65. Customer Cannot Select Warehouse

No Checkout input for:

```text
warehouse
stock row
location
```

---

# 66. Reservation Failure

If any line cannot reserve fully:

rollback:

```text
all ProductStock reserved changes
all allocation rows
Order
OrderItems
history
Cart changes
idempotency success
```

---

# 67. No Partial Reservation

Never retain reservation for the first lines when a later line fails.

---

# 68. Insufficient Stock Mapping

Preserve the frozen Checkout mapping:

```text
422 INSUFFICIENT_STOCK
```

for authoritative failure under the existing allocator semantics.

If an established transaction-race mapping differs in current contract implementation, verify before changing.

---

# 69. Initial Status History

Create exactly one initial:

```text
SYSTEM → PENDING_PAYMENT
```

or equivalent initial event according to the existing history model.

---

# 70. History Timestamp

Server-generated.

---

# 71. History Actor

Use existing initial-event semantics.

Do not invent a fake Customer actor if schema defines initial event as SYSTEM.

---

# 72. History Atomicity

Initial history must commit with Order.

No Order without initial history after successful Checkout.

---

# 73. Cart Clear

On success:

```text
delete CartItems
```

but preserve the Cart row.

---

# 74. Cart Status

After successful Checkout:

```text
Cart remains ACTIVE
```

---

# 75. Do Not Inactivate Cart

Do not use:

```text
ACTIVE → INACTIVE
```

for successful Checkout.

That behavior belongs to guest merge source retirement, not Checkout.

---

# 76. Empty ACTIVE Cart After Success

The customer keeps the same Cart identity for future shopping.

---

# 77. Cart Updated Timestamp

Follow existing Cart timestamp conventions.

If clearing items should touch Cart `updated_at`, do so consistently.

Document exact behavior.

---

# 78. Failure Preserves Cart

Any ordinary failure must preserve:

```text
Cart items
quantities
Cart status
```

---

# 79. Reference Collision

If `OD-*****` collides with unique reference:

retry safely using existing ReferenceGenerator strategy.

Do not restart the entire external request manually if only reference generation needs bounded retry.

---

# 80. Transaction Retry

Use existing:

```text
ConcurrentTransaction
```

or equivalent bounded retry mechanism.

---

# 81. Retry Only Transient DB Conflicts

Examples already recognized by repository:

```text
deadlock
SQLSTATE 40001
lock wait timeout
1213
1205
ER_RECORD_CHANGED
```

---

# 82. Business Failures Are Not Retried

Do not retry:

```text
CART_INVALID
PRODUCT_NOT_PURCHASABLE
INVALID_PRODUCT_VARIANT
INSUFFICIENT_STOCK
INVALID_FULFILLMENT
```

as transient DB conflicts.

---

# 83. Retry Must Re-run Entire Transaction

A transient conflict retry must restart from:

```text
Cart lock
current state
current prices
current stock
```

Do not resume halfway.

---

# 84. No Side Effects Outside Transaction Before Success

Do not:

```text
clear Cart before transaction
reserve before transaction
write history after commit
write idempotency result after commit
```

---

# 85. Transaction Rollback Test Seams

Provide safe internal test seams where needed to simulate failure after:

```text
Order insert
OrderItem insert
reservation
history insert
Cart clear
```

without production branching.

---

# 86. Rollback After Order Insert

Force a failure after Order creation.

Assert:

```text
Order does not exist
OrderItems do not exist
Cart intact
stock unchanged
idempotency success absent
```

---

# 87. Rollback After Reservation

Force failure after successful reservation.

Assert:

```text
reserved_quantity restored
allocations removed
Order rolled back
Cart intact
```

---

# 88. Rollback After History

Assert everything rolls back.

---

# 89. Rollback After Cart Clear

Force failure before idempotency completion/commit.

Assert Cart items return because deletion rolled back.

---

# 90. Idempotency Success Last

Successful idempotency result should be recorded only once all business effects are ready inside the same transaction.

---

# 91. But Replay Lookup Comes First

Distinguish:

```text
lookup completed replay
```

from:

```text
write successful outcome
```

Replay lookup happens before Cart validation.

Success write happens near the end of the transaction.

---

# 92. Failed Request Idempotency

Do not store a successful replay result for failed validation/business attempts.

Follow current service behavior for claim cleanup/failure state.

---

# 93. Same-Key Concurrent Checkout

Two requests:

```text
same Customer
same Idempotency-Key
same input
```

must produce:

```text
one Order
one reservation
one Cart clear
one history
one success outcome
same logical 201 response
```

---

# 94. Different-Key Same Cart Race

Two requests:

```text
same Customer
same Cart
different keys
```

must produce at most:

```text
one Order
```

---

# 95. Last Unit Race

Two different customers attempt the same last unit.

Expected:

```text
one succeeds
one fails safely
```

No:

```text
negative availability
double reservation
oversell
```

---

# 96. Multi-Line Failure

Cart:

```text
line A enough stock
line B insufficient
```

Checkout result:

```text
whole transaction fails
```

No reservation for A.

---

# 97. Adjust vs Checkout

Existing InventoryAllocator/INV-003 locking must serialize:

```text
staff inventory adjustment
vs
Checkout reservation
```

without violating:

```text
reserved_quantity <= quantity
```

---

# 98. No New Inventory Locks

Do not invent a second locking pattern.

---

# 99. Catalog Read Locking

Do not indiscriminately lock all Product/Variant rows if immutable transaction-time reads plus inventory locks are sufficient.

However price/state consistency must be verified against actual current code.

---

# 100. Price Race Review

Explicitly inspect whether Product/Variant price can be concurrently changed by currently active admin APIs.

If such writes exist and can race Checkout:

define a deterministic consistency strategy.

Do not guess.

---

# 101. If Catalog Mutation Is Not Yet Active

Do not add speculative row locks solely for a future endpoint.

Document current assumption.

---

# 102. Snapshot Timing

The OrderItem price/name/SKU snapshot must reflect the authoritative values read during this transaction.

---

# 103. No Cart Projection Resource Reuse as Authority

Do not serialize Cart then rebuild Order from that public response.

Use domain/model data.

---

# 104. No Lazy Loading Under Critical Locks

Preload required relationships deliberately.

Avoid hidden N+1/lazy loads while holding transaction locks.

---

# 105. Query Count

Checkout query behavior should be bounded in Cart line count where reasonable.

Do not create one Product/Variant/stock query per item if existing batched patterns can be reused.

---

# 106. InventoryAllocator May Query Per Structured Batch

Preserve its tested behavior.

Do not optimize it casually in this phase.

---

# 107. Order Ownership

Set:

```text
customer_id
```

from authenticated principal.

Never client input.

---

# 108. Reference and IDs

Set:

```text
order_reference
opaque order id
timestamps
```

server-side.

---

# 109. Financial Fields

Set from:

```text
OrderTotalsCalculator
```

only.

---

# 110. PICKUP Persistence Attributes

Use existing `PickupFulfillmentState` mapping for branch fields.

Do not duplicate:

```text
fee=0
FINALIZED
addresses=null
```

manually in the transaction coordinator.

---

# 111. DELIVERY State

Use `DeliveryFulfillmentState` only for projection/preparation.

Do not persist an incomplete DELIVERY aggregate.

---

# 112. Blocker Check Before Mutation

If DELIVERY is unsupported due to the open persistence gap:

ensure that decision is made before:

```text
Order insert
OrderItem insert
reservation
Cart clear
```

---

# 113. Prefer Compile-Time/Domain Separation

If practical, structure transaction execution so the currently supported persisted branch is explicit rather than a late runtime surprise.

---

# 114. Do Not Change Frozen API

Do not remove:

```text
DELIVERY
```

from OpenAPI just because persistence is currently blocked.

This is an implementation blocker, not a contract deletion.

---

# 115. No Post-Freeze API Change

Phase 7.7 should not change request/response shapes.

Expected:

```text
OpenAPI changes = NONE
```

unless fixing an independently approved documentation bug.

---

# 116. No Schema Change Expected

Expected:

```text
Schema: NONE
```

for transaction-boundary implementation itself.

---

# 117. Billing Blocker Exception

Do not solve the Phase 7.4 billing schema here unless the project owner has explicitly approved that model decision as part of a separate remediation.

---

# 118. Dependencies

Expected:

```text
NONE
```

---

# 119. Frontend

Expected:

```text
NONE
```

---

# 120. Payment

Must remain:

```text
NONE
```

No Payment row.

No provider call.

---

# 121. Notifications

Do not send external notifications inside Checkout transaction.

If future in-app notification creation belongs to Checkout, verify contract ownership first.

Do not invent it here.

---

# 122. Audit

Customer Checkout itself does not need a privileged audit event unless current audit contract explicitly requires one.

Do not add admin-style audit noise.

---

# 123. Cache

Future CHK-001 response remains:

```text
private
no-store
```

Phase 7.7 need not activate response middleware.

---

# 124. Error Leakage

Transient DB failures must map to safe:

```text
409 CONFLICT
```

or current canonical mapping.

Do not expose:

```text
SQLSTATE
table
lock name
constraint name
stack trace
```

---

# 125. Idempotency Race Failure

An unresolved idempotency claim should use the existing:

```text
409 CONFLICT
```

behavior.

---

# 126. Database Driver Strategy

SQLite can prove:

```text
rollback
persistence relationships
business atomicity
model invariants
```

but not true row-lock concurrency.

---

# 127. MariaDB Is Mandatory for Concurrency

Use disposable MariaDB/MySQL for:

```text
same-key race
different-key same-cart race
last-unit race
multi-line reservation rollback under contention
adjust-vs-checkout lock interaction where practical
```

---

# 128. Do Not Treat SQLite as Lock Proof

Explicitly document the driver split.

---

# 129. Same-Key Race Test

Run repeated concurrent processes/connections.

Expected:

```text
one Order
one set of items
one reservation allocation set
one history event
one Cart clear
one idempotency success record
```

---

# 130. Same-Key Responses

Both logical callers should reconcile to the same:

```text
order_reference
response payload
201
```

---

# 131. Different-Key Same-Cart Test

Expected:

```text
1 success
1 CART_INVALID or defined conflict after lock/re-read
```

No duplicate Order.

---

# 132. Last-Unit Test

Two customers, one unit available.

Expected:

```text
1 successful reservation
1 INSUFFICIENT_STOCK
```

---

# 133. Allocation Test

Multi-location stock:

```text
location A = 2
location B = 3
order qty = 4
```

assert deterministic allocations consistent with InventoryAllocator.

---

# 134. Rollback Exactness

Failure after allocation creation must restore both:

```text
reserved_quantity
allocation rows
```

---

# 135. OrderItem Snapshot Test

Verify persisted line fields remain correct even if Product is modified after transaction.

---

# 136. Cart Retention Test

After successful PICKUP transaction:

```text
same Cart row exists
status ACTIVE
items = []
```

---

# 137. Cart Failure Test

After any forced business/transaction failure:

```text
same Cart
same items
same quantities
```

---

# 138. Idempotency Replay Test

After Cart cleared:

retry same key.

Expect exact original:

```text
201
Order response
```

---

# 139. Response Serialization

If Phase 7.7 needs to store an idempotent response body:

use the frozen Checkout response shape or an internal immutable representation that Phase 7.8 can serialize deterministically.

Do not store raw Eloquent serialization.

---

# 140. Avoid Resource Drift

If the public response Resource is not finalized until 7.8:

store a stable internal outcome capable of reproducing the exact response later.

---

# 141. Idempotency Outcome Must Be Durable

The stored successful result must survive:

```text
Cart clear
later retry
worker/process restart
```

---

# 142. Transaction Commit and Response

Never return 201 before transaction commit succeeds.

---

# 143. Commit Failure

If COMMIT fails:

do not return success.

The idempotency success record must not appear committed independently.

---

# 144. ReferenceGenerator Failure

A reference collision retry must not leave partial data.

---

# 145. OrderItem Creation Failure

Any DB/model validation failure must roll back Order.

---

# 146. History Failure

Must roll back the entire Checkout.

---

# 147. Cart Clear Failure

Must roll back:

```text
Order
reservation
history
```

---

# 148. Idempotency Completion Failure

Must roll back the whole business mutation.

This is the main Phase 7.1 gap to close.

---

# 149. Shared Service Transaction Ownership

Inspect current `IdempotencyService::execute()` transaction behavior.

Avoid nested transaction semantics that accidentally commit the business mutation before idempotency completion.

---

# 150. Prefer One Outer Transaction Owner

There should be one clearly documented owner of the Checkout transaction.

Do not layer:

```text
Checkout DB::transaction
inside IdempotencyService transaction
inside InventoryAllocator independent commit
```

if those scopes can commit independently.

---

# 151. Nested Laravel Transactions

Remember Laravel nested `DB::transaction()` uses savepoint/counter semantics depending driver.

Verify actual behavior.

Do not assume nested calls are independent commits.

---

# 152. InventoryAllocator Integration Review

If `InventoryAllocator::reserve()` starts its own transaction:

verify it correctly joins the existing connection/outer transaction.

If necessary, provide a repository-consistent method for "run within existing transaction" rather than duplicate commit boundaries.

---

# 153. Do Not Rewrite Proven Allocator Unnecessarily

Any change to allocator transaction handling must preserve:

```text
reserve
release
consume
INV-003 races
```

and rerun its existing tests.

---

# 154. Lock Ordering — Global Rule

Document exact Checkout lock order.

Target from accepted requirements:

```text
idempotency coordination
→ active Cart
→ Order / OrderItems
→ ProductStock rows through allocator
```

---

# 155. OrderItems Locks

New rows do not normally require explicit locks.

Do not add pointless locking unless current allocator/relationship needs it.

---

# 156. ProductStock Order

Follow allocator:

```text
variant ids normalized
ProductStock deterministic order
```

Do not acquire rows ad hoc.

---

# 157. Deadlock Prevention

Never acquire ProductStock before Cart in one path and Cart before ProductStock in another Checkout path.

---

# 158. Interaction With Inventory Adjustment

INV-003 locks ProductStock.

Checkout must use allocator's same stock lock discipline.

---

# 159. Interaction With Release/Consume

Later cancellation/payment flows use:

```text
Order → ProductStock
```

lock ordering.

Checkout must not create a conflicting reverse order.

---

# 160. Newly Inserted Order Caveat

Document how the new Order fits the global:

```text
Order → ProductStock
```

locking convention.

---

# 161. Transaction Input Object

Consider a normalized immutable Checkout command containing:

```text
customer
idempotency key/fingerprint
fulfillment state/input
```

but do not let it contain client-controlled financials.

---

# 162. No Request Object in Transaction Service

The transaction service should not receive:

```text
Illuminate\Http\Request
```

directly.

---

# 163. Validation Ownership

Phase 7.8 will build final validated request DTO/command.

Phase 7.7 may use direct domain inputs in tests.

---

# 164. Public Route May Remain Stub

That is acceptable for Phase 7.7.

The transaction service can be fully tested internally.

---

# 165. Functional Success Scope

Because DELIVERY persistence is blocked, Phase 7.7 may demonstrate full transactional success for:

```text
PICKUP
```

and transactional rollback/shared infrastructure for DELIVERY-independent pieces.

---

# 166. Do Not Create PICKUP-Only Public API Semantics

The contract exposes one CHK-001 with both fulfillment values.

Do not publicly advertise Checkout as PICKUP-only.

---

# 167. Internal Branch Support Is Different

Internally supporting PICKUP persistence while DELIVERY remains blocked is acceptable if the public route is not misleadingly activated.

---

# 168. Phase 7.4 Future Integration

Structure transaction code so once billing persistence is resolved, DELIVERY can plug into:

```text
same transaction
same idempotency
same reservation
same history
same Cart clear
```

without a second Checkout workflow.

---

# 169. No Duplicate DELIVERY Transaction Later

Build common transaction composition now.

---

# 170. Suggested Layering

Conceptually:

```text
Checkout command
    ↓
Checkout transaction coordinator
    ↓
locked Cart resolver
    ↓
authoritative line resolver
    ↓
OrderTotalsCalculator
    ↓
FulfillmentState
    ↓
Order aggregate persister
    ↓
InventoryAllocator
    ↓
History
    ↓
Cart clear
    ↓
IdempotentOutcome
```

---

# 171. Avoid God Service

Split focused internal helpers if complexity exceeds limits.

Possible helpers:

```text
CheckoutCartSnapshot
CheckoutLineResolver
OrderSnapshotFactory
CheckoutOrderPersister
```

Only introduce abstractions justified by complexity/reuse.

---

# 172. Cognitive Complexity

Keep:

```text
<= 15
```

---

# 173. Returns

Keep:

```text
<= 3 returns where practical
```

---

# 174. No Magic Strings

Reuse:

```text
FulfillmentType
OrderStatus
DeliveryFeeStatus
OrderActorType
```

---

# 175. Tests — Pure Transaction Success

Add focused feature/service tests for PICKUP:

```text
valid Cart
current price
reserve
Order
OrderItems
history
Cart clear
idempotency outcome
```

---

# 176. Test — Current Price Drift

Cart previously displayed price A.

Variant now price B.

Checkout OrderItem must snapshot B.

---

# 177. Test — Product Inactive After Cart Add

Checkout rejects.

No mutation.

---

# 178. Test — Variant Inactive

Checkout rejects.

---

# 179. Test — MADE_TO_ORDER

Reject.

---

# 180. Test — Insufficient Stock

Reject.

Assert:

```text
Cart intact
no Order
no allocations
reserved unchanged
```

---

# 181. Test — Multi-Line Rollback

One valid line + one failing line.

Everything rolls back.

---

# 182. Test — Order History

Exactly one initial PENDING_PAYMENT event.

---

# 183. Test — Reference

Server-generated valid:

```text
OD-*****
```

---

# 184. Test — Opaque Order ID

Response/internal projection uses:

```text
ord_...
```

not DB id.

---

# 185. Test — Order Ownership

Authenticated customer owns Order.

---

# 186. Test — Staff/Admin

Cannot execute Checkout transaction as customer.

---

# 187. Test — Cart Clear Retains Row

Required.

---

# 188. Test — Retry Same Key

Required.

---

# 189. Test — Retry Same Key After Empty Cart

Required.

---

# 190. Test — Same Key Changed Fulfillment

Expected:

```text
409 DUPLICATE_OPERATION
```

---

# 191. Test — Different Customer Same Key

Separate scope.

---

# 192. Test — Forced Failure Before Commit

No successful idempotency outcome.

---

# 193. Test — 201 Stored/Replayed

Explicitly test status code persistence if shared service now supports it.

---

# 194. Existing Idempotency Regressions

Run:

```text
InventoryAdjustmentApiTest
CartMergeApiTest
DeliveryFeeApiTest
```

or current equivalents.

---

# 195. Inventory Regressions

Run:

```text
InventoryReservationTest
InventoryConcurrencyMysqlTest
```

where shared allocator code changes.

---

# 196. Cart Regressions

Run Group F critical suites because Cart locking/clear behavior is now consumed by Checkout.

---

# 197. Totals Regressions

Run:

```text
OrderTotalsCalculatorTest
OrderTotalsCompatibilityTest
PickupFulfillmentStateTest
DeliveryFulfillmentStateTest
```

---

# 198. Order Model Regressions

Run:

```text
OrderSchemaTest
OrderItemSchemaTest
OrderStatusHistorySchemaTest
```

---

# 199. MariaDB Concurrency Suite

Add dedicated Checkout concurrency tests.

Possible name:

```text
CheckoutTransactionConcurrencyMysqlTest
```

Use repository conventions.

---

# 200. Concurrency Iterations

Use repeated barrier-synchronized iterations consistent with existing MariaDB tests.

Report exact count.

---

# 201. Disposable Database Only

Never run destructive/forked concurrency tests against dev or production DB.

---

# 202. SQLite Suite

Canonical PHPUnit suite remains SQLite where configured.

---

# 203. Driver Split Documentation

State:

```text
SQLite:
business atomicity / rollback / persistence

MariaDB:
row-lock concurrency / deadlock behavior / oversell proof
```

---

# 204. Performance

Do not hold locks while generating large API resources.

Build the minimum durable idempotency outcome needed.

---

# 205. Response Snapshot

If idempotency stores the whole response JSON:

ensure it contains no unstable:

```text
lazy relationships
current catalog values
```

It must represent the created Order snapshot.

---

# 206. Private Data

DELIVERY address data, when eventually supported, must not appear in logs.

---

# 207. Safe Errors

Rollback exceptions must pass through the safe exception renderer already hardened.

---

# 208. Scheduler

No new scheduler required in Phase 7.7.

Existing idempotency pruning remains operational.

---

# 209. Security Baseline

Do not undo:

```text
pre-auth throttling
CUSTOMER-only Cart
optional-auth account state enforcement
body-size limits
JSON mutation requirements
safe logging
Clerk fail-closed production settings
```

---

# 210. Production Security Work Is Closed

Do not reopen security-remediation scope unless a genuine regression is discovered while implementing transaction logic.

---

# 211. Security Finding Handling

If a new code-review finding appears:

verify it against current code before changing behavior.

Do not blindly implement review text.

---

# 212. Schema

Expected:

```text
NONE
```

unless Phase 7.4 billing persistence is separately approved and intentionally pulled forward.

Do not do that silently.

---

# 213. Dependencies

Expected:

```text
NONE
```

---

# 214. Frontend

Expected:

```text
NONE
```

---

# 215. OpenAPI

Expected:

```text
UNCHANGED
```

---

# 216. Documentation

Add:

```text
ADR/BACKEND-040 — Checkout Transaction Boundary
```

if 040 is the actual next available ADR.

Do not assume numbering; inspect current file.

---

# 217. ADR Content

Record:

```text
transaction owner
atomic operations
lock order
idempotency atomicity
201 replay support
InventoryAllocator integration
Cart clear-retain semantics
rollback guarantee
MariaDB concurrency proof
Phase 7.4 blocker preserved
```

---

# 218. Group G Phase File

Update current Group G tracking.

Expected after PASS:

```text
7.1 PASS
7.2 PASS
7.3 PASS
7.4 BLOCKED — billing snapshot persistence
7.5 PASS
7.6 PASS
7.7 PASS — transaction boundary infrastructure
```

Do not mark DELIVERY persistence complete.

---

# 219. Phase 7.8 Readiness

If Phase 7.7 succeeds:

```text
Phase 7.8 — Checkout validation: READY WITH DELIVERY PERSISTENCE BLOCKER
```

unless 7.8 explicitly requires 7.4 resolved before work can proceed.

---

# 220. Completion Report

Return:

## Phase 7.7 status

```text
PASS
```

or:

```text
BLOCKED
```

---

## Transaction owner

Report the class/service owning the outer Checkout transaction.

---

## Atomic boundary

List exactly what commits together:

```text
Cart lock/final snapshot
authoritative lines
totals
Order
OrderItems
reservation
allocation rows
initial history
Cart clear
idempotency success
```

---

## Lock order

Report actual order.

---

## Idempotency

Report:

```text
shared service reused
201 preserved
same-key replay after Cart clear
changed-intent conflict
success atomic with transaction
```

---

## Cart

Report:

```text
row locked
same ACTIVE Cart retained
items cleared only on commit
failure preserves items
```

---

## Pricing

Report:

```text
current server-side Variant price
OrderTotalsCalculator used
Cart display price not trusted
```

---

## Order

Report:

```text
PENDING_PAYMENT
server ownership
OD reference
opaque id
```

---

## OrderItems

Report snapshot fields.

---

## Inventory

Report:

```text
InventoryAllocator reused
reserved_quantity increments only
physical quantity unchanged
exact allocations persisted
```

---

## History

Report initial history behavior.

---

## Rollback

Report forced-failure tests and resulting zero side effects.

---

## PICKUP

Report whether a complete internal PICKUP transaction now works.

---

## DELIVERY

Must state explicitly:

```text
Phase 7.4 persistence blocker remains open
complete DELIVERY Checkout persistence = NOT IMPLEMENTED
```

---

## Billing snapshot

State:

```text
NOT RESOLVED IN PHASE 7.7
```

---

## Payment

State:

```text
Payment rows: NONE
Provider calls: NONE
```

---

## Schema

Expected:

```text
NONE
```

---

## Dependencies

Expected:

```text
NONE
```

---

## Frontend

```text
NONE
```

---

## OpenAPI

```text
UNCHANGED
```

---

## Tests

Report:

```text
transaction tests
rollback tests
idempotency regressions
Cart regressions
Inventory regressions
Order regressions
totals regressions
```

---

## MariaDB

Report:

```text
same-key race
different-key same-Cart race
last-unit race
adjust-vs-checkout race if implemented
iterations
assertions
```

---

## Quality

Report:

```text
PHPUnit
PHPStan
Pint
Composer audit
git diff --check
route:list
```

---

## Group G status

Return:

```text
7.1 PASS
7.2 PASS
7.3 PASS
7.4 BLOCKED — billing snapshot persistence
7.5 PASS
7.6 PASS
7.7 PASS/BLOCKED
```

---

## Phase 7.8 readiness

Return one:

```text
Phase 7.8 — READY WITH DELIVERY PERSISTENCE BLOCKER
```

or:

```text
Phase 7.8 — BLOCKED
```

with exact reason.

---

# 221. Definition of Done

Phase 7.7 is complete when:

- exactly one Checkout transaction owner exists;
- Cart row is locked before authoritative snapshot;
- different-key same-Cart Checkout cannot create two Orders;
- Products/Variants are revalidated inside the transaction;
- current server price is authoritative;
- OrderTotalsCalculator is reused;
- Order reference is server-generated;
- Order starts PENDING_PAYMENT;
- OrderItems snapshot current transaction facts;
- Order and items are created inside the same transaction;
- InventoryAllocator is reused;
- Checkout never manually updates reserved_quantity;
- reservation is all-or-nothing;
- exact allocation rows are persisted;
- physical stock is not decremented;
- initial status history is atomic with Order;
- Cart items clear only on success;
- Cart row remains ACTIVE;
- failure preserves Cart;
- failure leaves no Order;
- failure leaves no OrderItems;
- failure leaves no history;
- failure leaves no reservation;
- failure leaves no allocation;
- failure leaves no success idempotency record;
- same-key retry after Cart clear replays original 201;
- changed intent with same key conflicts;
- concurrent same-key execution has one business effect;
- stored idempotency outcome preserves 201;
- existing 200 idempotent operations still replay 200;
- transient DB conflicts use bounded retry;
- business errors are not blindly retried;
- MariaDB proves real concurrency behavior;
- PICKUP transaction behavior is correct;
- DELIVERY persistence remains blocked;
- billing snapshot persistence is not bypassed;
- no Payment logic is introduced;
- no external calls occur inside the transaction;
- no new schema is introduced;
- no dependency is introduced;
- no frontend changes occur;
- OpenAPI remains unchanged;
- full regression suite remains green;
- PHPStan reports zero errors;
- Pint passes;
- Composer audit is clean.

---

# 222. Out of Scope

Do not implement:

```text
Phase 7.4 billing snapshot persistence fix
Phase 7.8 full Checkout request validation
Phase 7.9 Group G closure
public partial PICKUP-only Checkout contract
Payment creation
PAY-001
payment provider calls
payment webhook
reservation release
reservation consumption
Order cancellation
Order lifecycle transitions
delivery fee recalculation
Delivery operational record creation
frontend
```

---

# 223. STOP Condition

STOP when the repository has one transaction boundary that can safely prove:

```text
lock Cart
→ revalidate current purchase facts
→ calculate authoritative totals
→ persist Order aggregate
→ reserve exact inventory
→ create initial history
→ clear Cart
→ persist successful idempotent 201 result
→ commit
```

with:

```text
any failure
→ total rollback
```

while still stating clearly:

```text
Phase 7.4 DELIVERY persistence = BLOCKED
billing snapshot persistence = unresolved
full DELIVERY CHK-001 persistence = not ready
```

Do not continue automatically to Phase 7.8.

DO NOT COMMIT, STAGE OR PUSH.

The project owner handles all Git operations.