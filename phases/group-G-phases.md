# Phase 7.1 — Checkout Requirements

## Purpose

Define and freeze the backend implementation requirements for:

```text
CHK-001
POST /api/v1/checkout
```

before implementing Checkout behavior in later Group G phases.

This phase is primarily:

```text
contract review
domain-boundary review
dependency mapping
transaction planning
validation matrix reconciliation
implementation-gap classification
test-plan definition
```

Do not implement the full Checkout workflow yet.

Do not reserve inventory yet.

Do not create Orders yet unless a minimal compile-time/domain preparation change is absolutely required by a confirmed gap.

---

# 1. Group G Context

Group F is now:

```text
GROUP F — PASS / CLOSED
```

The Cart entering Checkout now provides:

```text
authenticated owner
ACTIVE own Cart
validated CartItem identities
server-authoritative live pricing projection
quantity-aware is_purchasable
live stock projection
guest→authenticated merge complete
zero Cart-stage inventory reservations
```

Phase 7.1 defines how that Cart becomes an Order in later phases.

---

# 2. Group G Roadmap

Keep the established roadmap:

```text
7.1 — Checkout requirements
7.2 — Address model review
7.3 — Pickup flow
7.4 — Delivery flow
7.5 — Delivery fee rules
7.6 — Order totals
7.7 — Transaction boundaries
7.8 — Checkout validation
7.9 — Checkout tests
```

Do not pull all of these phases into 7.1.

---

# 3. Phase 7.1 Objective

By the end of this phase the implementation agent must be able to answer, unambiguously:

```text
Who may checkout?

Which Cart is used?

What request fields are accepted?

What request fields are forbidden?

What Product/Variant/Cart conditions must hold?

How does PICKUP differ from DELIVERY?

When is delivery fee known?

What prices are authoritative?

What inventory state is authoritative?

When is inventory reserved?

What Order state is created?

What Cart mutation happens after success?

What must be atomic?

What does Idempotency-Key cover?

What happens on retries?

What happens on failure?

Which parts belong to later Group G phases?

Which parts belong to Group H?
```

---

# 4. Canonical Endpoint

The only Checkout endpoint is:

```http
POST /api/v1/checkout
```

Operation:

```text
CHK-001
```

Do not add:

```text
POST /checkout/preview
POST /checkout/start
POST /orders
POST /me/cart/checkout
POST /checkout/confirm
```

unless a future approved contract explicitly adds them.

---

# 5. Checkout Is a Dedicated Business Workflow

Checkout is not:

```text
PATCH /me/cart
```

and is not:

```text
POST /orders
```

with client-controlled financial/order fields.

Canonical conceptual workflow:

```text
authenticate Customer
→ derive own ACTIVE Cart
→ validate fulfillment request
→ validate Cart not empty
→ revalidate Product/Variant state
→ revalidate authoritative stock
→ recalculate current prices
→ snapshot fulfillment data
→ reserve inventory
→ create PENDING_PAYMENT Order
→ create OrderItem snapshots
→ create fulfillment/delivery state
→ clear Cart items
→ persist idempotent result
→ commit
→ return Checkout response
```

This later becomes one protected transaction boundary.

---

# 6. Actor

Checkout requires:

```text
authenticated CUSTOMER
```

Anonymous checkout is **prohibited**.

Guest credential alone is insufficient.

Expected:

```text
401 AUTHENTICATION_REQUIRED
```

---

# 7. Guest Handoff

A guest must:

```text
build guest Cart
→ authenticate
→ CART-005 merge
→ checkout authenticated Cart
```

CHK-001 must not merge guest Cart automatically.

---

# 8. STAFF / ADMIN

Review current role/self-commerce policy carefully.

Project-level rules already allow STAFF/ADMIN personal purchasing in Cart.

However the frozen Checkout contract currently says:

```text
CUSTOMER-only
```

Do not silently broaden CHK-001 based on Cart self-commerce alone.

Phase 7.1 must explicitly reconcile:

```text
Cart self-commerce for STAFF/ADMIN
vs
CHK-001 actor = CUSTOMER
```

Classify this as one of:

```text
NO GAP
DOC DRIFT
POLICY GAP
IMPLEMENTATION GAP
```

Do not guess.

If the frozen Checkout contract is authoritative, preserve CUSTOMER-only behavior until an approved decision changes it.

---

# 9. Identity Authority

Checkout ownership comes only from:

```text
verified Clerk principal
→ local Laravel User
→ server-derived active Cart
```

Never from request:

```text
user_id
customer_id
cart_id
guest_cart_id
owner_id
```

---

# 10. Cart Selection

Server derives:

```text
authenticated Customer
→ own ACTIVE Cart
```

No Cart selector is accepted.

---

# 11. Cart Ownership

Checkout must never allow Customer A to submit Customer B's Cart.

Because no Cart ID is supplied, self-context is the primary IDOR defense.

Do not introduce a Cart selector merely for implementation convenience.

---

# 12. No Active Cart

Determine exact frozen behavior when authenticated Customer has no ACTIVE Cart.

Likely conceptual outcome:

```text
422 CART_INVALID
```

rather than implicitly creating an empty Cart solely for checkout.

Verify current contract.

Do not reuse CART-001 lazy-create blindly if Checkout semantics require an existing non-empty Cart.

Record exact decision.

---

# 13. Empty Cart

Empty Cart checkout is prohibited.

Canonical:

```text
422 CART_INVALID
```

No separate public:

```text
CART_EMPTY
```

error exists.

No Order.

No reservation.

No Cart mutation.

---

# 14. Checkout Request Schema

Frozen request:

```json
{
  "fulfillment_type": "PICKUP"
}
```

or:

```json
{
  "fulfillment_type": "DELIVERY",
  "delivery_address": {
    "recipient_name": "...",
    "phone": "...",
    "address_line": "...",
    "city": "..."
  }
}
```

---

# 15. Strict Request Allow-List

Allowed:

```text
fulfillment_type
delivery_address
```

Nothing else.

`additionalProperties: false`.

---

# 16. Forbidden Client-Controlled Fields

Reject:

```text
cart_id
user_id
customer_id

price
unit_price
line_total
subtotal
total
currency
discount

delivery_fee
delivery_fee_status

available_quantity
reserved_quantity
stock

status
order_status
payment_status

order_reference
order_id

created_at
updated_at
```

Do not silently ignore authoritative financial/ownership fields.

---

# 17. FormRequest

Later implementation should use dedicated strict:

```text
CheckoutRequest
```

or repository equivalent.

Use:

```php
$request->validated()
```

Never:

```php
$request->all()
```

---

# 18. Fulfillment Type

Closed V1 values:

```text
PICKUP
DELIVERY
```

Exact uppercase enum semantics.

Reject invented values such as:

```text
pickup
shipping
courier
LOCAL_DELIVERY
EXPRESS
```

---

# 19. Invalid Fulfillment

Map according to frozen contract:

```text
INVALID_FULFILLMENT
```

or request-schema `INVALID_VALUE` where structural validation applies.

Phase 7.1 must document the exact mapping boundary.

---

# 20. PICKUP Request

For:

```text
fulfillment_type = PICKUP
```

`delivery_address` must be:

```text
absent
or
null
```

depending exact frozen schema.

A populated delivery address on PICKUP must be rejected.

---

# 21. DELIVERY Request

For:

```text
fulfillment_type = DELIVERY
```

`delivery_address` is mandatory.

---

# 22. Delivery Address Fields

Required DELIVERY snapshot inputs:

```text
recipient_name
phone
address_line
city
```

---

# 23. Delivery Address Validation

Review existing contract for:

```text
trim rules
lengths
phone normalization
nullability
empty strings
```

Do not invent saved-address behavior.

Saved addresses are deferred.

---

# 24. Delivery Address Is Snapshot Input

The Checkout address becomes historical Order fulfillment data.

It is not:

```text
live Customer profile reference
saved_address_id
```

unless later approved.

---

# 25. Model B Delivery-Fee Workflow

V1 uses:

```text
Model B — fee after Order creation
```

For DELIVERY:

```text
Checkout
→ create PENDING_PAYMENT Order
→ delivery_fee = null
→ delivery_fee_status = PENDING
→ Staff/Admin sets fee via ORD-014
→ delivery_fee_status = FINALIZED
→ total becomes final
→ payment permitted
```

---

# 26. DELIVERY Must Not Calculate Fee in CHK-001

Do not:

```text
calculate flat TZS 20,000
estimate fee from city
use frontend fee
```

inside Checkout.

The old flat fee is superseded.

---

# 27. Customer Cannot Supply Delivery Fee

Any:

```json
{
  "delivery_fee": ...
}
```

must fail validation.

---

# 28. PICKUP Delivery Fee

For PICKUP:

```text
delivery_fee = 0 TZS
delivery_fee_status = FINALIZED
```

according to the frozen Order model.

No Staff fee step is required.

---

# 29. DELIVERY Total Semantics

Because delivery fee is pending at initial DELIVERY checkout:

Phase 7.1 must inspect and record the exact current `CheckoutResponseData.total` semantics.

The OpenAPI requires:

```text
subtotal
total
currency
```

yet Model B makes final DELIVERY total provisional until ORD-014.

Do not invent behavior.

Explicitly reconcile:

```text
OpenAPI CheckoutResponseData.total required
vs
delivery_fee = null / PENDING
```

Determine whether current contract defines:

```text
total = subtotal while fee pending
```

or another provisional representation.

Record the authoritative result.

This is a high-priority Phase 7.1 contract check.

---

# 30. Pricing Authority

Checkout recalculates every Cart line from:

```text
current authoritative catalog price
```

Do not trust Cart display values as final transaction prices.

---

# 31. Cart Pricing Is Advisory

Even though Cart currently displays live price:

Checkout must re-read/recalculate it.

Cart data is not a transaction snapshot.

---

# 32. No Client Price

Client does not submit price.

No price comparison against client input is required because client price is forbidden.

---

# 33. Integer Minor Units

All monetary values remain:

```text
integer minor units
```

with:

```text
currency = TZS
```

No floats.

---

# 34. Current Price Drift

Example:

```text
Cart displayed: 1,000,000
Current checkout price: 1,100,000
```

Checkout authoritative Order item price:

```text
1,100,000
```

Do not preserve stale Cart price.

---

# 35. Order Item Snapshot

Checkout creates historical OrderItem snapshots.

Snapshot must not depend on future Product changes.

Review Group C order item schema for the exact snapshot fields.

Phase 7.1 should map:

```text
CartItem
Product
Variant
current unit price
quantity
line total
```

to OrderItem persistence requirements.

Do not implement yet.

---

# 36. Product Revalidation

Checkout must independently revalidate every line.

Required Product state includes:

```text
exists
active
published
not soft-deleted
IN_STOCK
```

plus Category/public-purchasability rules where already authoritative.

---

# 37. MADE_TO_ORDER

Any MADE_TO_ORDER Cart line:

```text
422 PRODUCT_NOT_PURCHASABLE
```

No partial checkout.

---

# 38. Variant Revalidation

Variant must:

```text
exist
belong to Product
be active
be the exact Variant referenced by CartItem
```

Do not substitute sibling Variant.

---

# 39. Cart Validation vs Checkout Validation

Group F:

```text
CartItemEligibility
CartStockRevalidator
```

are useful semantic authorities.

But Checkout cannot simply trust an earlier:

```text
is_purchasable = true
```

result.

Checkout must revalidate current state inside its own transaction.

---

# 40. Reuse Without Trusting Cached Result

Checkout may reuse:

```text
CartItemEligibility
CatalogAvailability semantics
Product/Variant rule components
```

but must invoke/re-evaluate them against transaction-time state.

Do not use stale precomputed Cart projection result as final authorization.

---

# 41. Stock Authority

Checkout stock is:

```text
authoritative current ProductStock state
```

not Cart projection.

---

# 42. Available Quantity

Continue:

```text
available =
quantity - reserved_quantity
```

aggregated per Variant across allowed locations according to Group E.

---

# 43. Inventory Check

For every line:

```text
requested quantity <= authoritative available quantity
```

must hold inside the eventual atomic transaction.

---

# 44. Checkout Is the First Reservation Point

Group F performs:

```text
reservations = NONE
```

Checkout changes that.

CHK-001 must eventually:

```text
reserved_quantity += ordered quantity
```

atomically with Order creation and Cart transition.

---

# 45. Reservation, Not Consumption

At Checkout:

```text
physical quantity stays unchanged
reserved_quantity increases
available decreases
```

This creates a hold for the `PENDING_PAYMENT` Order.

Do not immediately decrement physical inventory.

---

# 46. Consumption Is Later

Physical inventory is consumed later in the payment/fulfillment lifecycle.

Current contract says no later than:

```text
PAID → fulfillment
```

Group H owns payment-side behavior.

Do not implement consumption in CHK-001.

---

# 47. Existing Inventory Primitive

Phase 5.10 already created reservation primitives.

Phase 7.1 must map Checkout onto:

```text
reserve
release
consume
```

and determine which exact existing primitive CHK-001 should call later.

Expected:

```text
reserve
```

only.

---

# 48. Deterministic Inventory Locking

Phase 5.10 already established deterministic locking for multi-item reservation.

Phase 7.1 should verify that Checkout can reuse it.

Do not design a second locking mechanism.

---

# 49. Multi-Item Atomicity

Checkout with:

```text
A x2
B x3
C x1
```

must either:

```text
reserve all
create complete Order
clear Cart
```

or:

```text
do none
```

No partial reservation/order.

---

# 50. Overselling Scenario

Required later test:

```text
1 unit available

Customer A checkout
Customer B checkout
```

Exactly one may reserve successfully.

The other must fail safely.

No negative availability.

---

# 51. Stock Failure

Determine exact CHK-001 endpoint mapping for insufficient stock.

Global registry allows endpoint-specific:

```text
409 or 422
```

but Phase 7.1 must identify the frozen Checkout-specific choice.

Do not defer if the current contract already resolves it.

Record:

```text
INSUFFICIENT_STOCK → <exact status>
```

---

# 52. No Partial Order

Stock failure on one line means:

```text
no Order
no partial OrderItems
no reservation
no Cart clearance
```

---

# 53. Cart Preservation on Failure

Ordinary validation failures preserve Cart.

Includes:

```text
empty/invalid line
insufficient stock
invalid fulfillment
invalid delivery address
```

where safe.

---

# 54. Cart After Success

The latest accepted direction is:

```text
clear Cart items
keep Cart record ACTIVE
```

Same Cart ID remains available as empty active Cart.

Do not mark Cart INACTIVE if latest repository authority says clear-and-retain.

---

# 55. Checkout Cart Clear Timing

Cart items may be cleared only inside the successful atomic Checkout transaction.

Do not:

```text
clear Cart
then create Order
```

in separate commits.

---

# 56. Order Creation

Successful CHK-001 creates:

```text
Order status = PENDING_PAYMENT
```

---

# 57. Delivery Fee Status

PICKUP:

```text
delivery_fee = 0
delivery_fee_status = FINALIZED
```

DELIVERY:

```text
delivery_fee = null
delivery_fee_status = PENDING
```

---

# 58. Payment Is Not Part of Checkout

CHK-001 must not:

```text
contact provider
create provider payment
confirm payment
process webhook
set Order PAID
```

Group H owns payment.

---

# 59. Checkout Response

OpenAPI defines:

```text
CheckoutResponseData
```

with at least:

```text
order_id
order_reference
status
fulfillment_type
subtotal
total
currency
```

Review the complete schema and document exact response fields.

Do not implement a generic OrderResource response if Checkout has its own frozen response shape.

---

# 60. Order ID

Use existing opaque:

```text
ord_...
```

contract if applicable.

Do not expose DB IDs.

---

# 61. Order Reference

Generated server-side by:

```text
ReferenceGenerator
```

following the latest accepted format:

```text
OD- + 5
```

Verify current repository authority.

Do not use faker in production.

---

# 62. Reference Generation Timing

Order reference must be generated inside or safely coordinated with Order creation.

Do not accept it from client.

---

# 63. Idempotency Required

Header:

```http
Idempotency-Key: <uuid>
```

is mandatory.

---

# 64. Reuse Durable Idempotency Infrastructure

Group E / CART-005 already uses shared durable:

```text
IdempotencyService
```

Phase 7.1 must verify CHK-001 can reuse it.

No Checkout-specific duplicate subsystem.

---

# 65. Checkout Idempotency Scope

Use established:

```text
authenticated identity
+
action / endpoint
+
key
```

---

# 66. Checkout Fingerprint

Material logical input includes:

```text
fulfillment_type
delivery_address
```

and any server-derived identity needed to bind the operation safely.

Do not include volatile current price/stock in the client-intent fingerprint unless existing infrastructure explicitly requires it.

The purpose is:

```text
same logical request → replay
different client intent → conflict
```

---

# 67. Same-Key Replay

Same Customer:

```text
same Idempotency-Key
same logical checkout input
```

must return original:

```text
201
same order_id
same order_reference
same CheckoutResponseData
```

No second Order.

No second reservation.

No second Cart clear.

---

# 68. Same-Key Different Input

Example:

```text
first:
PICKUP

retry same key:
DELIVERY
```

or delivery address changes.

Expected:

```text
409 DUPLICATE_OPERATION
```

or exact frozen conflict mapping.

---

# 69. Replay After Cart Is Empty

This is critical.

After successful Checkout:

```text
Cart is now empty
```

A legitimate same-key retry must still replay original Checkout success.

Therefore idempotency replay must be resolved before normal:

```text
Cart not empty
```

validation would reject the request.

---

# 70. Correct Idempotency Ordering

Conceptually:

```text
authenticate Customer
→ validate Idempotency-Key
→ validate/normalize request shape
→ calculate request fingerprint
→ inspect durable idempotency record
→ matching completed replay?
      return original 201 result
→ otherwise execute Checkout transaction
```

Do not:

```text
load empty Cart
→ return CART_INVALID
→ only afterward inspect idempotency
```

---

# 71. Concurrent Same-Key Checkout

Two concurrent requests:

```text
same user
same key
same intent
```

must result in:

```text
one Order
one inventory reservation effect
one Cart clear
same logical response
```

---

# 72. Different-Key Concurrent Checkout

Two requests from same Customer:

```text
same Cart
different Idempotency-Key
```

must not both create Orders from the same Cart contents.

Transaction/cart locking must prevent duplicate checkout.

---

# 73. Cart Locking

Phase 7.1 must determine the target Cart locking strategy used later.

Likely:

```text
lock authenticated active Cart
```

before authoritative final Cart snapshot/clear.

Reuse repository concurrency conventions.

---

# 74. Lock Ordering

Checkout eventually touches:

```text
Cart
CartItems
ProductStock rows
Order
OrderItems
Delivery
Idempotency
```

Phase 7.1 should define deterministic lock ordering at the architecture level.

Do not implement it yet if Phase 7.7 owns transaction details.

At minimum record that lock-order consistency is mandatory.

---

# 75. Phase 7.7 Boundary

Detailed transaction implementation belongs to:

```text
Phase 7.7 — Transaction boundaries
```

Phase 7.1 should freeze requirements, not prematurely build final locking code.

---

# 76. Pricing Snapshot Timing

Current price used for Order snapshot must be re-read during the Checkout workflow.

Phase 7.1 must document whether price reads occur:

```text
inside same transaction
```

or under another guaranteed consistency mechanism.

Given financial authority, prefer eventual Phase 7.7 design that prevents inconsistent partial snapshotting.

---

# 77. Product/Variant Snapshot Consistency

OrderItem must represent:

```text
the Product/Variant actually validated and reserved
```

Do not validate Variant A then snapshot Variant B.

---

# 78. Fulfillment Snapshot Consistency

DELIVERY Order must snapshot:

```text
delivery_address
```

from validated request.

PICKUP must not persist a fake delivery address.

---

# 79. Order Status History

Review Group C/Order contract for whether initial:

```text
PENDING_PAYMENT
```

history entry is created atomically with Order.

Phase 7.1 should map this dependency.

Do not implement transitions beyond initial creation.

---

# 80. Delivery Model Dependency

Review Group C `deliveries` schema.

Determine whether CHK-001 creates:

```text
Delivery row for both PICKUP/DELIVERY
```

or only DELIVERY.

Follow repository authority.

Do not guess.

---

# 81. Order Financial Fields

Map exact initial state.

At minimum:

```text
subtotal
delivery_fee
delivery_fee_status
total
currency
```

Need precise PICKUP/DELIVERY semantics.

---

# 82. DELIVERY Provisional Total

Explicitly settle during this phase:

```text
what is stored in Order.total
while delivery_fee_status = PENDING?
```

The source documents must answer this.

Do not leave later implementation to invent it.

---

# 83. PICKUP Total

Expected:

```text
total = subtotal
delivery_fee = 0
delivery_fee_status = FINALIZED
```

Verify against Order contract.

---

# 84. DELIVERY Total After ORD-014

Later:

```text
total =
subtotal + delivery_fee
```

after Staff/Admin finalizes fee.

Phase 7.5/Order operation owns that mutation.

---

# 85. Payment Eligibility

PICKUP Order:

payment may proceed subject to Group H rules because fee is finalized.

DELIVERY Order:

PAY-001 blocked while:

```text
delivery_fee_status = PENDING
```

with:

```text
409 DELIVERY_FEE_PENDING
```

Do not implement payment here.

---

# 86. Cancellation/Reservation Release Dependency

Checkout creates reservation.

Later cancellation/payment failure/expiry releases it.

Phase 7.1 must record these future consumers so reservation lifecycle is not implemented as one-way.

Do not implement them yet.

---

# 87. Indefinite Hold Prohibited

PENDING_PAYMENT reservation cannot remain forever.

The current contract requires eventual cancellation/expiry and release.

Expiry mechanism is deferred.

Record dependency on Group H/system workflow.

---

# 88. Checkout Failure Categories

Classify expected failures:

```text
authentication
request schema
fulfillment
delivery information
Cart state
Product state
Variant state
stock
idempotency conflict
concurrency/internal conflict
unexpected internal failure
rate limit
```

---

# 89. Error Envelope

All errors use:

```text
errors[]
meta.request_id
```

---

# 90. Authentication Failure

Expected:

```text
401 AUTHENTICATION_REQUIRED
```

---

# 91. Empty / Invalid Cart

Expected:

```text
422 CART_INVALID
```

where no more-specific frozen error supersedes it.

---

# 92. MADE_TO_ORDER

Expected:

```text
422 PRODUCT_NOT_PURCHASABLE
```

---

# 93. Invalid Variant

Expected:

```text
INVALID_PRODUCT_VARIANT
```

with exact Checkout status from frozen contract.

---

# 94. Insufficient Stock

Determine and record exact:

```text
INSUFFICIENT_STOCK
HTTP ?
```

Do not leave a `409/422*` ambiguity if CHK-001 contract resolves it.

---

# 95. Invalid Fulfillment

Expected frozen mapping:

```text
INVALID_FULFILLMENT
```

---

# 96. Invalid Delivery Information

Expected:

```text
INVALID_DELIVERY_INFORMATION
```

where domain-level DELIVERY structure is syntactically valid but business-invalid.

Separate from generic request schema failures.

---

# 97. Idempotency Conflict

Expected:

```text
409 DUPLICATE_OPERATION
```

for same key + materially different request.

---

# 98. Rate Limit

CHK-001:

```text
5/min/user
```

according to accepted security decision.

429 must include:

```text
Retry-After
```

---

# 99. Cache

Checkout response is private financial/customer state.

Use:

```text
private
no-store
```

according to conventions.

Do not cache publicly.

---

# 100. No External Calls

Phase 7.1 should confirm CHK-001 requires no payment-provider call.

This helps define transaction boundaries.

---

# 101. Required Existing Components Review

Inspect and document reuse readiness for:

```text
Cart / CartItem
CartItemEligibility
CartStockRevalidator
CatalogAvailability

InventoryAllocator / reservation primitive
ConcurrentTransaction
IdempotencyService

Order
OrderItem
OrderStatusHistory
Delivery

ReferenceGenerator

Money/value objects/enums
FulfillmentType
OrderStatus
delivery_fee_status
```

---

# 102. Do Not Assume Component Names

Use actual repository classes.

Classify each dependency:

```text
READY
NEEDS SMALL EXTENSION
MISSING
DEFERRED TO LATER GROUP G
```

---

# 103. Cart Validation Reuse

Checkout should reuse existing semantic definitions where safe.

But avoid using HTTP Cart mutation actions like:

```text
AddCartItem
UpdateCartItemQuantity
```

inside Checkout.

Checkout needs its own domain workflow.

---

# 104. Inventory Reuse

Prefer reusing Phase 5.10 reservation primitives.

Do not reimplement raw stock locking inside Checkout if existing service already guarantees:

```text
deterministic locks
multi-item atomic reservation
overselling prevention
```

---

# 105. Idempotency Reuse

Prefer shared IdempotencyService already proven by:

```text
INV-003
CART-005
```

---

# 106. ReferenceGenerator Reuse

Checkout's Order creation must use:

```text
ReferenceGenerator
```

not factory/faker.

Verify latest reference format.

---

# 107. Order Schema Review

Phase 7.1 must review Group C Order schema and ensure it can represent:

```text
PENDING_PAYMENT
PICKUP
DELIVERY
subtotal
delivery fee pending/finalized
total
customer ownership
reference
historical snapshot
```

---

# 108. OrderItem Schema Review

Verify it can snapshot:

```text
Product
Variant
quantity
unit price
line total
names/SKU/etc. required by contract
```

---

# 109. Delivery Schema Review

Verify it supports both fulfillment modes and Model B.

---

# 110. Reservation Traceability Review

Determine how an Order later knows which reservation quantities to release/consume.

This is critical.

Review whether:

```text
OrderItems + Variant IDs
```

are sufficient to call release/consume primitives later or whether existing inventory reservation records are required.

Do not invent new schema before reviewing Group C.

---

# 111. Critical Reservation Question

Phase 7.1 must answer:

```text
How will cancellation/payment failure later know exactly what reserved quantities to release?
```

If Group C schema already solves this:

document it.

If not:

classify as:

```text
MODEL GAP
```

for later resolution before implementation.

---

# 112. Inventory Location Allocation

Phase 5.10 may reserve across specific ProductStock rows/locations.

Phase 7.1 must determine whether those allocations are persisted anywhere.

If release requires exact rows but Checkout has no durable allocation record:

this is a critical design gap.

Do not hide it.

---

# 113. Do Not Reserve Against Aggregate Only

If the reservation primitive allocates across locations:

Order lifecycle must later release/consume the same allocation correctly.

Review current implementation.

---

# 114. Transaction Requirements Matrix

Create a requirements matrix covering:

```text
Operation                      Atomic with CHK-001?
----------------------------------------------------
Cart ownership read            yes
Cart item final snapshot       yes
Product validation             yes
Variant validation             yes
price recalculation            yes
stock validation               yes
inventory reservation          yes
Order insert                   yes
OrderItem inserts              yes
initial Order status/history   yes
Delivery/fulfillment snapshot  yes if applicable
Cart item clear                yes
idempotency success record     yes
payment provider call          NO
```

Adjust only where repository authority specifies otherwise.

---

# 115. Failure Rollback Matrix

Define expected rollback:

```text
failure before reservation
→ nothing mutated

failure after partial reservation attempt
→ transaction rolls back reservation

failure after Order insert
→ transaction rolls back Order

failure during OrderItem snapshot
→ Order + reservation rolled back

failure during Cart clear
→ Order + reservation rolled back

unexpected transaction exception
→ no orphan reservation/order
```

---

# 116. Idempotency Failure State

Review how shared IdempotencyService handles:

```text
IN_PROGRESS
SUCCEEDED
FAILED
```

or equivalent.

Checkout must not leave a durable success record before transaction commits.

---

# 117. Response Replay Storage

The durable idempotency result must be sufficient to replay:

```text
201
CheckoutResponseData
```

after:

```text
Cart has been cleared
inventory is reserved
Order already exists
```

---

# 118. No Order Lookup Guessing on Retry

Do not reconstruct replay merely by:

```text
look up latest Order for customer
```

Use durable idempotency association.

---

# 119. Security Review

Phase 7.1 must explicitly verify Checkout prevents:

```text
guest checkout
Cart substitution
Customer substitution
price manipulation
subtotal/total manipulation
delivery fee manipulation
status injection
reference injection
stock input manipulation
currency override
variant substitution
MADE_TO_ORDER bypass
network duplicate Order
overselling
```

---

# 120. Input Tampering Test Plan

Define future tests for request attempts containing:

```text
cart_id
user_id
customer_id
unit_price
subtotal
total
delivery_fee
currency
status
order_reference
available_quantity
reserved_quantity
```

Every must fail strict request validation.

---

# 121. Cart Integrity Test Plan

Future Group G tests must include:

```text
no Cart
empty Cart
valid Cart
stale Product
unpublished Product
soft-deleted Product
inactive Category
MADE_TO_ORDER
invalid/inactive/wrong-parent Variant
quantity invalid in persistence defense
insufficient stock
```

---

# 122. Stock Concurrency Test Plan

Required MariaDB scenarios:

```text
last unit:
two Customers checkout concurrently
→ exactly one success

multi-line:
one shared constrained Variant
→ no partial reservation

same Customer / different keys:
same Cart checkout race
→ at most one Order

same Customer / same key:
→ one business effect, replay
```

---

# 123. Pricing Test Plan

Include:

```text
Cart price changes before Checkout
→ Order snapshots new price

multiple lines
→ subtotal exact

integer arithmetic only

client total tampering
→ rejected
```

---

# 124. Fulfillment Test Plan

PICKUP:

```text
no address
fee 0
fee finalized
total = subtotal
```

DELIVERY:

```text
valid address required
fee pending
no client fee
address snapshotted
```

---

# 125. Delivery Address Test Plan

Include:

```text
missing recipient_name
missing phone
missing address_line
missing city
blank values
whitespace
invalid phone
address on PICKUP
unknown address fields
```

according to exact frozen constraints.

---

# 126. Idempotency Test Plan

Include:

```text
missing key
malformed key
first success
same-key replay
same-key changed fulfillment
same-key changed address
concurrent same-key
network retry after commit
replay after Cart cleared
same key different Customer
```

---

# 127. Order Creation Test Plan

Verify:

```text
one Order
correct customer
correct reference
PENDING_PAYMENT
correct fulfillment
correct subtotal
correct fee state
correct total state
correct OrderItems
correct address snapshot
initial status history
```

---

# 128. Cart Success Test

After successful Checkout:

```text
same active Cart record
items = []
items_count = 0
subtotal = 0
```

if latest clear-and-retain decision remains authoritative.

---

# 129. Cart Failure Test

After failed Checkout:

Cart lines remain unchanged.

No timestamp/quantity mutation beyond anything explicitly allowed.

---

# 130. Reservation Success Test

After CHK-001 success:

```text
physical quantity unchanged
reserved_quantity increased by ordered quantity
available quantity decreased
```

---

# 131. Reservation Failure Test

After failed Checkout:

```text
quantity unchanged
reserved_quantity unchanged
```

---

# 132. Order/Reservation Atomicity Test

Force failure after reservation but before final transaction completion.

Expected:

```text
no Order
no reservation
Cart preserved
```

---

# 133. Model B Test

DELIVERY success:

```text
status = PENDING_PAYMENT
delivery_fee = null
delivery_fee_status = PENDING
```

No payment call.

---

# 134. PICKUP Model Test

PICKUP success:

```text
status = PENDING_PAYMENT
delivery_fee = 0
delivery_fee_status = FINALIZED
```

---

# 135. Checkout Response Contract Test

Compare response exactly with current:

```text
CheckoutResponseData
```

No extra raw persistence fields.

---

# 136. No Inventory Internals in Response

Do not expose:

```text
ProductStock IDs
warehouse allocation internals
reserved_quantity
physical quantity
lock state
```

---

# 137. No Payment Internals

Do not include provider/payment token because Group H has not run.

---

# 138. N+1 Review

Phase 7.1 should identify the expected read/write query shape.

Checkout will naturally issue multiple writes.

Do not misclassify necessary:

```text
OrderItem inserts
inventory allocation writes
```

as N+1 read problems.

But avoid:

```text
one Product query per CartItem
one Variant query per CartItem
one stock-read query per CartItem
```

where existing batch/eager mechanisms suffice.

---

# 139. Checkout Projection vs Cart Projection

Do not run full `CartResource` merely to validate Checkout.

Use domain data directly.

API Resources should not drive Checkout business logic.

---

# 140. Phase 7.1 Deliverable — Requirements Document

Create/update a repository phase record such as:

```text
phases/phase-7.1-checkout-requirements.md
```

if this matches project conventions.

Document:

```text
contract
actors
request
authority boundaries
validation order
pricing
inventory
reservation lifecycle
fulfillment
Model B
idempotency
transaction requirements
dependencies
gaps
future test matrix
```

---

# 141. Decision Record

Add a backend ADR only if Phase 7.1 resolves a real implementation-level ambiguity not already frozen.

Do not duplicate all CHK ADRs into a new ADR.

---

# 142. Gap Classification

Classify every finding as:

```text
NO GAP
DOC DRIFT
MODEL GAP
IMPLEMENTATION GAP
DEFERRED
```

---

# 143. Critical Gaps

Phase 7.1 must not return READY if any unresolved critical gap remains around:

```text
reservation traceability
DELIVERY provisional total
Order schema ability
OrderItem snapshot
idempotency durability
Cart clearing semantics
Checkout actor authorization
```

---

# 144. STAFF/ADMIN Checkout Ambiguity

Explicitly classify the tension:

```text
Cart supports personal self-commerce for Staff/Admin
Checkout contract says CUSTOMER-only
```

Do not silently decide based on convenience.

Use current frozen contract/AGENTS authority.

---

# 145. Cart Clear Wording Drift

Some older source wording may say:

```text
clear/inactivate
```

while latest Group F closure says:

```text
clear items, keep ACTIVE Cart
```

Resolve using latest accepted repository authority.

Document the final interpretation.

---

# 146. Delivery Total Drift

Resolve the `CheckoutResponseData.total` vs pending DELIVERY fee question from current sources.

This must be explicit before Phase 7.4/7.6.

---

# 147. Reservation Release Dependency

Even though release is outside CHK-001 implementation:

ensure chosen reservation representation supports later:

```text
ORD-004 cancellation
payment failure
system expiry
payment success consumption
```

---

# 148. Group H Boundary

Record:

Checkout ends with:

```text
Order PENDING_PAYMENT
reservation held
```

Group H later handles:

```text
payment initiation
provider communication
provider webhook
PAID/CANCELLED effects
release/consume
```

---

# 149. Group G Internal Boundary

Phase 7.1 requirements should map responsibilities:

```text
7.2 Address model
→ address persistence/snapshot compatibility

7.3 Pickup
→ PICKUP branch

7.4 Delivery
→ DELIVERY branch

7.5 Delivery fee
→ Model B rules / pending-finalized

7.6 Order totals
→ authoritative monetary calculations

7.7 Transaction boundaries
→ atomicity / locks / reservation

7.8 Checkout validation
→ final validation implementation

7.9 Tests
→ complete Group G gate
```

---

# 150. Do Not Implement Phases 7.2–7.9 Early

Small review helpers are acceptable.

Do not fully implement:

```text
CheckoutService
reservation transaction
Order creation workflow
delivery fee operation
checkout API activation
```

during 7.1 unless required solely to inspect/verify interfaces.

---

# 151. Current CHK-001 Route

Inspect current route/controller state.

If still 501:

leave it 501 during requirements phase unless project convention explicitly activates only after implementation.

Do not partially expose Checkout.

---

# 152. OpenAPI Must Remain Frozen

Do not edit CHK-001 merely because implementation has not begun.

Only fix actual contract contradiction supported by accepted decisions.

---

# 153. Schema Changes

Expected:

```text
NONE
```

for Phase 7.1.

If a true model gap is discovered:

document it.

Do not immediately migrate unless 7.1 scope explicitly permits a minimal required correction.

---

# 154. Dependencies

Expected:

```text
NONE
```

---

# 155. Frontend

Expected:

```text
NONE
```

---

# 156. Tests During Requirements Phase

Run existing tests relevant to assumptions:

```text
Cart Group F
Inventory allocator/reservation tests
Order schema tests
Delivery schema tests
Idempotency tests
ReferenceGenerator tests
```

Do not write a giant CHK-001 feature suite before implementation.

---

# 157. Add Requirement-Level Tests Only if Valuable

Acceptable examples:

```text
schema supports required enum/state
reservation primitive can release exactly what it reserves
Order models support Model B fields
ReferenceGenerator output matches latest format
```

Avoid testing nonexistent Checkout behavior.

---

# 158. Verify Shared Idempotency

Confirm:

```text
IdempotencyService
```

supports:

```text
201 replay
arbitrary response payload
same-key fingerprint conflict
identity/action scope
24h retention
concurrent claimant behavior
```

If not, classify gap for Phase 7.7/7.8.

---

# 159. Verify Reservation Primitive

Confirm existing Phase 5.10 service supports:

```text
multi-line reservation
deterministic locking
all-or-nothing
release
consume
```

and determine return type needed for persistence/reversal.

---

# 160. Verify Order Creation Models

Review actual models/factories:

```text
Order
OrderItem
OrderStatusHistory
Delivery
```

No assumptions.

---

# 161. Verify ReferenceGenerator

Confirm Order production reference:

```text
OD- + 5
```

or exact latest repository value.

Document any drift.

---

# 162. Verification Commands

Run relevant focused tests.

Then, if documentation/code was changed:

```bash
php artisan test
vendor/bin/pint --test
vendor/bin/phpstan analyse
composer audit
git diff --check
php artisan route:list
```

---

# 163. No MariaDB Checkout Race Yet

Do not invent checkout concurrency tests before the transaction implementation exists.

However rerun existing Phase 5.10 inventory concurrency tests if assumptions depend on those primitives.

---

# 164. Completion Report

Return:

## Phase 7.1 status

```text
PASS
```

or:

```text
BLOCKED
```

## CHK-001 contract

Confirm:

```text
POST /api/v1/checkout
```

and whether route remains stubbed.

## Actor

State exact Checkout role requirement.

Explicitly address Staff/Admin self-commerce tension.

## Cart authority

Confirm:

```text
authenticated principal
→ own ACTIVE Cart
```

and no Cart selector.

## Request schema

Report exact accepted fields.

## Fulfillment

Report:

```text
PICKUP
DELIVERY
```

and address conditionality.

## Pricing

Report current-price authority and minor-unit semantics.

## Delivery fee

Report Model B semantics.

## DELIVERY provisional total

State exact resolved rule.

This section is mandatory.

## Inventory

Report:

```text
stock authority
reservation timing
reservation primitive
lock requirement
```

## Reservation traceability

State exactly how future cancellation/payment failure will release the Checkout reservation.

This section is mandatory.

## Cart success behavior

State whether successful Checkout:

```text
clears items and retains ACTIVE Cart
```

or another current authoritative behavior.

## Order creation

Report:

```text
initial status
OrderItem snapshot
status history
fulfillment/delivery snapshot
reference generation
```

## Idempotency

Report:

```text
required header
fingerprint
same-key replay
different-input conflict
replay after Cart clear
shared service readiness
```

## Transaction requirements

List the exact operations that must be atomic.

## Payment boundary

Confirm provider/payment logic is Group H.

## Error mapping

Report exact known mappings, especially:

```text
empty cart
MADE_TO_ORDER
invalid variant
insufficient stock
invalid fulfillment
invalid delivery information
duplicate operation
```

## Gap classification

Provide:

```text
NO GAP
DOC DRIFT
MODEL GAP
IMPLEMENTATION GAP
DEFERRED
```

for each finding.

## Schema

Expected:

```text
NONE
```

## Dependencies

Expected:

```text
NONE
```

## Frontend

```text
NONE
```

## Tests

Report focused/canonical results.

## Quality

Report:

```text
Pint
PHPStan
Composer audit
git diff --check
```

if applicable.

## Phase 7.2 readiness

Return:

```text
READY
```

or:

```text
BLOCKED
```

with exact blocker.

---

# 165. Definition of Done

Phase 7.1 is complete when:

- CHK-001 actor is unambiguous;
- guest checkout is prohibited;
- Staff/Admin personal-checkout policy is explicitly reconciled;
- Checkout Cart selection is self-context only;
- no client Cart ID is accepted;
- empty Cart behavior is defined;
- request allow-list is frozen;
- forbidden server-controlled fields are listed;
- PICKUP/DELIVERY behavior is frozen;
- delivery address conditionality is frozen;
- saved-address behavior remains deferred;
- Model B delivery-fee timing is understood;
- DELIVERY provisional total semantics are explicitly resolved;
- current price is authoritative;
- client financial values are rejected;
- Order item price snapshot timing is defined;
- Product/Variant revalidation requirements are known;
- MADE_TO_ORDER rejection is known;
- transaction-time stock authority is known;
- reservation occurs only at Checkout;
- reservation is not physical consumption;
- existing inventory reservation primitive is mapped;
- reservation release/consume traceability is proven or identified as a model gap;
- multi-line reservation must be atomic;
- checkout overselling requirements are frozen;
- successful Checkout Cart behavior is explicit;
- failed Checkout Cart preservation is explicit;
- initial Order status is explicit;
- Model B delivery fee status is explicit for PICKUP and DELIVERY;
- payment provider behavior remains outside Checkout;
- Checkout response fields are known;
- ReferenceGenerator authority is known;
- Idempotency-Key is mandatory;
- idempotency replay ordering is specified;
- replay after Cart clear is accounted for;
- same-key changed intent conflict is specified;
- transaction operations requiring atomicity are enumerated;
- error mapping is reconciled;
- rate limit requirement is identified;
- no critical schema ambiguity remains;
- no critical reservation lifecycle ambiguity remains;
- later Group G phase ownership is clear;
- no frontend work is introduced;
- Phase 7.2 can begin without guessing Checkout fundamentals.

---

# 166. Out of Scope

Do not implement yet:

```text
full CheckoutService
inventory reservation transaction
Order creation transaction
OrderItem inserts
Delivery record creation
delivery fee finalization
payment provider
payment webhook
order cancellation
reservation release workflow
stock consumption workflow
system expiry
frontend checkout
```

---

# 167. STOP Condition

STOP when CHK-001 is fully specified as a backend workflow:

```text
authenticated CUSTOMER
→ own non-empty ACTIVE Cart
→ strict PICKUP/DELIVERY input
→ authoritative Product/Variant/price revalidation
→ authoritative stock validation
→ atomic reservation
→ historical Order snapshot
→ PENDING_PAYMENT
→ Cart cleared only on success
→ durable idempotent 201 result
→ no payment-provider call
```

and all model/contract ambiguities needed by Phase 7.2 are either:

```text
resolved
```

or explicitly:

```text
BLOCKING
```

Do not continue automatically to Phase 7.2.

DO NOT COMMIT OR PUSH.

The project owner handles all Git operations.