# Phase 6.7 — Stock Revalidation

## Purpose

Implement the Cart-wide stock revalidation layer that ensures every existing Cart line is evaluated against the **latest live inventory state** whenever the Cart is projected or otherwise requires current stock information.

Build directly on:

```text
Phase 5.7  — Catalog availability
Phase 5.10 — Inventory concurrency / overselling protection
Phase 6.2  — Create/get Cart
Phase 6.3–6.5 — Cart mutations
Phase 6.6  — Cart validation consolidation
```

Phase 6.6 already established:

```text
CartItemEligibility
CartItemValidationResult
CartItemInvalidReason
```

as the authoritative Cart eligibility system.

Phase 6.7 must **reuse those components**.

Do not create a second set of stock/purchasability rules.

---

# 1. Phase Boundary

Phase 6.7 is about:

```text
live Cart stock revalidation
cart-wide evaluation
efficient current-stock resolution
stock drift detection
consistent projection
pre-checkout readiness data internally
```

It is NOT:

```text
inventory reservation
checkout
order creation
stock mutation
Cart mutation
a new public validation endpoint
```

---

# 2. No New API Endpoint

The current OpenAPI Cart surface remains:

```text
GET    /api/v1/me/cart                 CART-001
POST   /api/v1/me/cart/items           CART-002
PATCH  /api/v1/me/cart/items/{item}    CART-003
DELETE /api/v1/me/cart/items/{item}    CART-004
POST   /api/v1/me/cart/merge           CART-005
```

Phase 6.7 must NOT add:

```text
GET  /api/v1/me/cart/validate
POST /api/v1/me/cart/validate
GET  /api/v1/me/cart/revalidate
POST /api/v1/me/cart/revalidate
```

Stock revalidation is an internal Cart-domain behavior.

---

# 3. Do Not Implement CART-005

`POST /api/v1/me/cart/merge` remains outside this phase.

Do not implement guest-cart merge merely because stock revalidation may eventually be reused by merge.

---

# 4. Core Question

The revalidation layer must answer:

```text
Given the Cart as it exists right now,
which lines can currently be satisfied by live inventory?
```

It must answer this without changing the Cart.

---

# 5. Stock Revalidation Is Live

Never trust stock state captured when the CartItem was:

```text
added
last updated
last read
```

Every revalidation must use current Group E inventory state.

---

# 6. Cart Does Not Own Stock State

Do not persist any of the following on:

```text
carts
cart_items
```

```text
available_quantity
reserved_quantity
stock_indicator
availability
is_purchasable
stock_checked_at
validated_at
inventory_version
```

All remain derived.

---

# 7. Existing Inventory Authority

Continue using:

```text
ProductStock.quantity
ProductStock.reserved_quantity
```

with:

```text
available_quantity
=
quantity - reserved_quantity
```

through the existing Group E abstraction.

Do not query or calculate availability independently if:

```text
CatalogAvailability
```

already provides the authoritative calculation.

---

# 8. Multi-Location Inventory

For a Variant with several ProductStock rows:

```text
available =
SUM(quantity - reserved_quantity)
```

according to the already-established Group E semantics.

Do not use:

```text
first warehouse
largest warehouse
one arbitrary ProductStock row
```

for Cart revalidation.

---

# 9. Reuse `CartItemEligibility`

Phase 6.6 made:

```text
CartItemEligibility
```

the sole Cart-domain eligibility authority.

Phase 6.7 must not introduce:

```text
CartStockValidator
```

with another independent definition of:

```text
is_purchasable
```

Instead, introduce only orchestration around the existing evaluator if needed.

---

# 10. Recommended New Boundary

A focused component such as:

```text
CartStockRevalidator
```

is appropriate.

Its responsibility should be:

```text
load the Cart's relevant inventory efficiently
evaluate every CartItem through CartItemEligibility
produce current validation results
```

It must not own Product/Variant business rules.

---

# 11. Possible Interface

Conceptually:

```text
CartStockRevalidator::revalidate(Cart $cart)
```

returning something like:

```text
CartStockRevalidationResult
```

containing per-item:

```text
CartItemValidationResult
```

Do not expose these internal result objects directly through the API.

---

# 12. Do Not Duplicate `CartItemValidationResult`

Phase 6.6 already introduced the immutable validation result.

Reuse it.

Do not create:

```text
StockValidationResult
CartLineStockStatus
PurchasabilityResultV2
```

carrying the same facts.

---

# 13. Cart-Wide Revalidation

The revalidator should evaluate all CartItems in one Cart invocation.

Conceptually:

```text
Cart
 ├── item A → current eligibility
 ├── item B → current eligibility
 └── item C → current eligibility
```

This allows consistent orchestration and efficient inventory loading.

---

# 14. Individual Result

For every Cart line the authoritative result remains equivalent to:

```text
isPurchasable
availability
stockIndicator
internal invalid reason
```

as finalized by Phase 6.6.

---

# 15. Quantity-Aware Revalidation

Revalidation must compare:

```text
CartItem.quantity
```

against:

```text
current aggregate available quantity
```

not simply check whether the Variant has at least one available unit.

---

# 16. Critical Example

Cart:

```text
quantity = 5
```

Current aggregate Variant inventory:

```text
available = 2
```

Expected:

```text
availability = available
is_purchasable = false
```

because Group E coarse availability remains positive while this particular Cart line cannot be fulfilled.

Do not change Group E `availability` semantics.

---

# 17. Exact Quantity Boundary

Given:

```text
Cart quantity = 5
available = 5
```

expected:

```text
is_purchasable = true
```

assuming all Product/Variant rules are valid.

---

# 18. One Below Boundary

Given:

```text
Cart quantity = 5
available = 4
```

expected:

```text
is_purchasable = false
```

---

# 19. Fully Reserved Inventory

Given:

```text
physical quantity = 10
reserved quantity = 10
available = 0
```

expected:

```text
availability = unavailable
is_purchasable = false
```

---

# 20. Partial Reservation

Given:

```text
physical quantity = 10
reserved quantity = 6
available = 4
Cart quantity = 5
```

expected:

```text
availability = available
is_purchasable = false
```

---

# 21. Reservation Awareness

Revalidation must account for inventory reserved by:

```text
other checkout/order transactions
```

through:

```text
quantity - reserved_quantity
```

Do not consider physical quantity alone.

---

# 22. LOW_STOCK

`LOW_STOCK` remains informational.

Example:

```text
available = 4
Cart quantity = 2
```

may correctly return:

```text
availability = available
stock_indicator = LOW_STOCK
is_purchasable = true
```

Do not treat LOW_STOCK itself as failure.

---

# 23. Out of Stock

If:

```text
available = 0
```

expect:

```text
availability = unavailable
is_purchasable = false
```

---

# 24. MADE_TO_ORDER

MADE_TO_ORDER must remain:

```text
is_purchasable = false
```

for a Cart line.

Do not consult physical stock to make it Cart-purchasable.

Even if ProductStock exists:

```text
product_type = MADE_TO_ORDER
```

still excludes normal Cart checkout.

---

# 25. Product Rules Still Come First

Use Phase 6.6 ordering:

```text
Product existence
→ visibility
→ Product type
→ Variant requirement
→ Variant ownership/activity
→ stock sufficiency
```

If an earlier rule fails:

do not perform unnecessary inventory queries for that line.

---

# 26. Inactive Product

An existing Cart line whose Product becomes inactive:

```text
line retained
is_purchasable = false
```

No stock query should be required merely to determine that.

---

# 27. Unpublished Product

Same:

```text
line retained
is_purchasable = false
```

---

# 28. Soft-Deleted Product

Same:

```text
line retained
is_purchasable = false
```

Do not make the line disappear because public Product scopes exclude it.

---

# 29. Inactive Category

Product otherwise valid but Category inactive:

```text
is_purchasable = false
```

Do not waste an inventory query afterward.

---

# 30. Inactive Variant

Existing line remains visible.

Expected:

```text
is_purchasable = false
```

No stock sufficiency check needed after Variant failure.

---

# 31. Wrong-Parent / Corrupt Variant

Existing persistence should normally prevent this.

If encountered:

fail safely through existing internal validation result/error handling.

Do not reinterpret another Product's inventory.

---

# 32. Stock Revalidation Never Removes Items

Never:

```text
DELETE cart_items
```

because stock changed.

The accepted Cart behavior preserves stale lines.

---

# 33. Stock Revalidation Never Reduces Quantity

Example:

```text
Cart quantity = 8
available = 3
```

do NOT mutate:

```text
8 → 3
```

Expected:

```text
quantity = 8
is_purchasable = false
```

The customer must explicitly PATCH the quantity.

---

# 34. Stock Recovery

If inventory later changes:

```text
available 3 → 8
```

the next revalidation should automatically produce:

```text
is_purchasable = true
```

without Cart persistence changes.

---

# 35. Reservation Drift

If another checkout reserves units:

```text
available 8 → 3
```

the next Cart read/revalidation must reflect that immediately.

Do not cache old available quantity in Cart persistence.

---

# 36. Release Drift

If reservations are released:

```text
available 3 → 8
```

Cart becomes purchasable again automatically where all other rules pass.

---

# 37. Inventory Adjustment Drift

If Staff/Admin inventory adjustment changes physical quantity:

Cart revalidation must reflect the new state.

No Cart mutation is required.

---

# 38. Revalidation Trigger — CART-001

Every:

```text
GET /api/v1/me/cart
```

must return CartItems based on live current stock.

Do not return stale cached eligibility from a previous request.

---

# 39. Revalidation Trigger — CART-002 Response

After successful add:

the returned Cart projection must reflect live eligibility.

Reuse the same revalidation/projection path.

---

# 40. Revalidation Trigger — CART-003 Response

After successful quantity update:

the returned Cart projection must reflect current stock.

---

# 41. CART-004

DELETE returns:

```text
204
```

and therefore requires no Cart projection response.

Do not perform a full stock revalidation merely to remove a Cart line.

---

# 42. No Scheduled Revalidation Worker

Do not create:

```text
queue job
cron
scheduler
stock-monitoring worker
```

to continually update Carts.

Cart stock state is revalidated when required by request workflows.

---

# 43. No Push Notifications

Do not notify users when stock changes.

That is outside this phase.

---

# 44. No Persistent Stale Marker

Do not write:

```text
cart_items.is_stale
cart_items.stock_valid
cart_items.needs_attention
```

---

# 45. No Revalidation Timestamp

Do not persist:

```text
last_stock_check_at
```

The derived result can become stale immediately anyway.

---

# 46. Avoid N+1

Phase 6.7 must explicitly review the Cart projection query shape.

A Cart containing N items should not trigger:

```text
N Product queries
N Variant queries
N inventory aggregation queries
```

when those facts can be resolved in bounded batches.

---

# 47. Main Performance Goal

Stock revalidation should scale approximately with:

```text
a bounded set of queries per Cart
```

rather than:

```text
queries × number of Cart lines
```

where practical with the existing Laravel architecture.

---

# 48. Batch Relevant Variants

Collect the valid Variant IDs required by the Cart.

Conceptually:

```text
variantIds = Cart items
    → eligible Product/Variant candidates
    → unique Variant IDs
```

Then resolve their stock aggregates efficiently.

---

# 49. Do Not Query Inventory for Invalid Lines

If Product or Variant validation already fails:

exclude that line from the stock-aggregation query.

This preserves the Phase 6.6 validation ordering.

---

# 50. Batch Multi-Location Availability

For all relevant Variant IDs, aggregate:

```text
SUM(quantity - reserved_quantity)
GROUP BY product_variant_id
```

or reuse the existing Group E equivalent.

Do not create a conflicting SQL definition if `CatalogAvailability` already provides a batch-capable API.

---

# 51. Extend Existing Group E Component Carefully

If `CatalogAvailability` currently only supports:

```text
availableQuantity(ProductVariant $variant)
```

and causes one query per item:

add the smallest batch-capable API.

For example conceptually:

```text
availableQuantities(iterable $variantIds)
```

returning:

```text
variant_id => available_quantity
```

Only if needed.

---

# 52. Single Availability Authority

Whether single-item or batched:

the formula must remain centralized under Group E.

Do not place raw:

```sql
SUM(quantity - reserved_quantity)
```

inside three separate Cart services.

---

# 53. Batch Availability Must Match Single Availability

For every Variant:

```text
batchAvailable[variant]
==
CatalogAvailability::availableQuantity(variant)
```

under the same database state.

Add regression tests.

---

# 54. Negative Available Defense

The database/domain invariant already requires:

```text
reserved_quantity <= quantity
```

Therefore available should never be negative.

If corrupt data somehow produces it:

do not expose a negative public availability.

Fail safely according to existing Group E invariant handling.

Do not normalize corrupt persistence silently unless existing Group E does so.

---

# 55. Revalidation Result Container

A Cart-level result can conceptually contain:

```text
cart
resultsByCartItemId
```

where each result is:

```text
CartItemValidationResult
```

Do not add public response fields solely for this container.

---

# 56. Stable Item Matching

Use internal CartItem identity to associate validation results.

Do not key business logic by:

```text
array position
Product name
SKU text
```

---

# 57. Resource Integration

`CartItemResource` should consume an already-computed:

```text
CartItemValidationResult
```

where practical.

Do not let the Resource independently hit ProductStock.

---

# 58. Resource Must Not Run Stock Queries

Phase 6.7 should move toward:

```text
query / projection layer
→ resolve live state
→ Resource formats it
```

instead of:

```text
Resource::toArray()
→ database query
```

API Resources should remain serialization-focused.

---

# 59. Cart Resource

`CartResource` should orchestrate or receive the validated/projection data through the established application layer.

Do not transform it into a domain service.

---

# 60. Pricing Remains Separate

Stock revalidation must not alter current pricing authority.

Cart response still resolves:

```text
unit_price
line_total
subtotal
```

from live catalog pricing.

Do not make inventory service responsible for pricing.

---

# 61. Missing/Inactive Variant Price

Preserve the accepted Phase 6.2 behavior:

where a stale/unpriceable line lacks an active own Variant price:

```text
unit_price = null
line_total = null
is_purchasable = false
```

and it contributes nothing to subtotal according to the current contract.

Do not borrow another Variant's price.

---

# 62. Stock Revalidation and Price Revalidation Are Distinct

Both are live in Cart projection, but Phase 6.7 is specifically about stock.

Do not redesign pricing.

---

# 63. No External Cache Authority

Do not use:

```text
Redis
application cache
CDN cache
session cache
```

as authoritative inventory state.

Live DB inventory remains authority.

---

# 64. Private Cart Response Cache

Continue:

```text
Cache-Control:
private,
no-cache,
no-store,
must-revalidate
```

Cart responses must never be served from public cache.

---

# 65. Do Not Cache Revalidation Across Users

Never cache:

```text
Cart A validation result
```

and reuse it as Cart B's line-level result.

The requested quantity may differ.

---

# 66. Variant Availability May Be Shared; Line Validation May Not

The current aggregate available quantity for a Variant can conceptually be reused within one request.

But:

```text
is_purchasable
```

must still be evaluated against each line's quantity.

---

# 67. Same Variant in One Cart

Current uniqueness rules should prevent duplicate same Product/Variant lines.

Do not rely solely on that for the stock service's correctness.

Use unique Variant IDs for aggregate lookup.

---

# 68. No ProductStock Locks

Stock revalidation is read-only.

Do NOT introduce:

```text
lockForUpdate()
```

for Cart stock reads.

---

# 69. Why No Locks

Even if revalidation locks stock:

the lock would be released before the user eventually presses Checkout.

Therefore it would not guarantee inventory.

Only Checkout reservation matters.

---

# 70. No Reservation

Explicitly forbidden:

```text
reserved_quantity += CartItem.quantity
```

during revalidation.

---

# 71. No Physical Quantity Mutation

Explicitly forbidden:

```text
quantity -= ...
```

during revalidation.

---

# 72. No Inventory Allocation

Do not allocate warehouse/location rows to CartItems.

Location allocation remains part of checkout reservation/fulfilment concerns.

---

# 73. No CartItem→ProductStock Relationship

Do not add:

```text
product_stock_id
warehouse_location
reservation_id
```

to CartItem.

---

# 74. Revalidation Is Not Checkout Validation

Phase 6.7 may conclude:

```text
all Cart lines currently purchasable
```

but this is not a checkout guarantee.

---

# 75. Checkout Still Revalidates

Group G must:

```text
lock current ProductStock
re-read authoritative state
validate again
reserve transactionally
```

It must never trust a Phase 6.7 result captured earlier.

---

# 76. No Revalidation Token

Do not create:

```text
validation_token
stock_version
cart_validation_id
```

for Checkout to trust later.

---

# 77. No "Validated Cart" State

Do not mark:

```text
Cart.status = VALIDATED
```

ACTIVE/INACTIVE remain the only Cart statuses.

---

# 78. No Pre-Reservation

Do not create temporary reservation because:

```text
all Cart lines validated
```

---

# 79. Existing Mutation Validation

CART-002 and CART-003 already validate stock against effective quantity.

Do not remove those checks just because Cart-wide revalidation exists.

They prevent knowingly invalid mutations.

---

# 80. Shared Stock Logic

However, the calculation used by:

```text
CART-002
CART-003
CART-001 projection
CartStockRevalidator
```

must ultimately use the same Group E stock authority.

---

# 81. No Divergence

Forbidden situation:

```text
CART-002 says available = 5
CART-001 says available = 3
```

under the same stable database state because different formulas were used.

---

# 82. Read Consistency

Within one CART-001 projection:

try to compute inventory facts from one coherent database read window.

Do not intentionally fetch the same Variant's available stock several times during one response.

---

# 83. Transaction Not Normally Required for GET

Do not wrap CART-001 in a long transaction merely for stock revalidation.

A point-in-time read is sufficient.

Stock remains advisory.

---

# 84. Snapshot Semantics

Do not promise serializable snapshot semantics for Cart GET.

Inventory may change immediately after the response.

That is expected.

---

# 85. API Representation Must Remain Frozen

Do not add:

```text
available_quantity
requested_quantity
stock_shortfall
stock_checked_at
validation_status
validation_reason
```

to `CartItem`.

The updated OpenAPI still defines:

```text
availability
stock_indicator
is_purchasable
```

as the Cart stock-facing fields.

---

# 86. `is_purchasable` Meaning

Preserve Phase 6.6:

```text
is_purchasable =
Product requirements valid
AND Variant requirements valid
AND current aggregate available quantity >= CartItem.quantity
```

---

# 87. `availability` Meaning

Preserve Group E coarse availability.

For an IN_STOCK Variant:

```text
available quantity > 0
→ availability = available

available quantity = 0
→ availability = unavailable
```

Do not make `availability` quantity-specific to Cart.

---

# 88. `stock_indicator`

Preserve:

```text
IN_STOCK
LOW_STOCK
MADE_TO_ORDER
```

according to Group E.

Do not introduce:

```text
PARTIALLY_AVAILABLE
INSUFFICIENT_FOR_CART
OUT_OF_STOCK
```

---

# 89. Internal Shortfall

The service may internally know:

```text
requested quantity
available quantity
shortfall
```

for decision-making.

Do not expose those values unless current frozen API already allows them.

---

# 90. Existing Stock Error Contract

CART-002/CART-003 continue returning:

```text
422 INSUFFICIENT_STOCK
```

where current effective quantity cannot be met.

Do not add a new error such as:

```text
CART_STOCK_CHANGED
CART_NEEDS_REVALIDATION
```

---

# 91. CART-001 Does Not Fail on Stock Shortage

If one or more lines lack sufficient stock:

```text
GET /me/cart
→ 200
```

The problematic lines are preserved with:

```text
is_purchasable = false
```

Do not return:

```text
422 INSUFFICIENT_STOCK
```

for the whole Cart read.

---

# 92. Multiple Invalid Lines

A Cart may contain:

```text
line A — insufficient stock
line B — inactive Product
line C — valid
```

CART-001 must still return all three.

Each line receives its own current projection.

---

# 93. Empty Cart

Revalidation of:

```text
items = []
```

should be cheap and valid.

Do not perform inventory queries for an empty Cart.

---

# 94. Empty Cart Result

Still:

```text
items_count = 0
items = []
subtotal = 0 TZS
```

---

# 95. Cart Record Must Not Be Touched

Stock revalidation must not change:

```text
Cart.updated_at
```

---

# 96. CartItems Must Not Be Touched

Stock revalidation must not change:

```text
CartItem.updated_at
```

---

# 97. ProductStock Must Not Be Touched

No inventory timestamps or quantities may change because a Cart was read.

---

# 98. No Audit Entry

Ordinary Cart stock revalidation is a read operation.

Do not create privileged inventory audit events.

---

# 99. Test — Empty Cart

CART-001 empty Cart:

assert no ProductStock queries if practical and no errors.

---

# 100. Test — Fully Available Cart

Several valid lines all have enough stock.

Every:

```text
is_purchasable = true
```

---

# 101. Test — One Insufficient Line

Cart has:

```text
A qty 2 / available 4
B qty 5 / available 3
```

expected:

```text
A is_purchasable = true
B is_purchasable = false
```

Cart response still 200.

---

# 102. Test — Zero Stock

```text
qty 1
available 0
```

expected:

```text
availability = unavailable
is_purchasable = false
```

---

# 103. Test — Partial Stock

```text
qty 5
available 2
```

expected:

```text
availability = available
is_purchasable = false
```

This remains a critical regression.

---

# 104. Test — Exact Boundary

```text
qty = 5
available = 5
```

purchasable.

---

# 105. Test — Reserved Quantity

Ensure current reservation reduces available stock.

---

# 106. Test — Multi-Location

Example:

```text
location A: quantity 3, reserved 1 → available 2
location B: quantity 4, reserved 1 → available 3

aggregate = 5
```

Cart quantity:

```text
5 → purchasable
6 → not purchasable
```

---

# 107. Test — Multi-Line Multi-Location

Several Variants across several locations.

Verify each Variant gets only its own aggregate inventory.

No cross-Variant stock mixing.

---

# 108. Test — Stock Drop Between Reads

First GET:

```text
available = 5
Cart qty = 5
is_purchasable = true
```

Change inventory:

```text
available = 4
```

Second GET:

```text
is_purchasable = false
```

without Cart mutation.

---

# 109. Test — Stock Recovery

Reverse the prior test.

Second GET becomes purchasable again.

---

# 110. Test — Reservation Created Elsewhere

Simulate another checkout reservation through existing inventory primitive.

Next Cart read must account for it.

Do not create Checkout endpoint merely for this test.

---

# 111. Test — Reservation Release

Release reservation through domain primitive/test fixture.

Next Cart read reflects increased availability.

---

# 112. Test — Inventory Adjustment

Use existing inventory adjustment service or fixture to reduce physical stock.

Next Cart read reflects it.

Do not call operational HTTP endpoint unnecessarily unless integration value requires it.

---

# 113. Test — Inactive Product Short-Circuit

Line Product is inactive.

Assert:

```text
is_purchasable = false
```

and where practical verify stock aggregation is not performed for that line.

---

# 114. Test — MADE_TO_ORDER Short-Circuit

Same.

Physical stock must not make the line purchasable.

---

# 115. Test — Inactive Variant Short-Circuit

Same.

---

# 116. Test — Current LOW_STOCK

Available stock within threshold but still enough for line quantity:

```text
stock_indicator = LOW_STOCK
is_purchasable = true
```

---

# 117. Test — LOW_STOCK Not Enough

Available still >0 but less than Cart quantity:

```text
stock_indicator = LOW_STOCK
availability = available
is_purchasable = false
```

---

# 118. Test — No Persistence Side Effects

Capture before:

```text
Cart.updated_at
CartItem.updated_at
ProductStock.updated_at
quantity
reserved_quantity
```

Run revalidation / CART-001.

Assert all unchanged.

---

# 119. Test — No Reservation

Explicitly assert:

```text
reserved_quantity before == after
```

for every relevant stock row.

---

# 120. Test — No Inventory Locking

Unit/integration architecture should show Cart revalidation does not call the reservation allocator or `lockForUpdate`.

Do not create brittle SQL-string tests if a service-level dependency test is clearer.

---

# 121. Test — Batch vs Single Calculation

For representative Variants:

```text
batch result
==
existing CatalogAvailability single result
```

---

# 122. Test — N+1 Regression

Create a Cart with multiple items.

Assert query growth remains bounded according to the chosen implementation.

Do not rely on an excessively brittle exact number if framework internals add harmless queries.

---

# 123. Query Scaling Test

Prefer a comparison such as:

```text
1 item
10 items
```

and ensure stock query count does not increase one-for-one if batch loading is implemented.

---

# 124. Existing Phase 6.6 Tests

Keep green:

```text
CartPurchasabilityApiTest
CartItemInvalidReasonTest
```

---

# 125. Existing Mutation Tests

Keep green:

```text
CartAddItemApiTest
CartUpdateItemApiTest
CartRemoveItemApiTest
```

---

# 126. Existing Read Tests

Keep green:

```text
CartReadApiTest
```

---

# 127. Existing MariaDB Cart Concurrency Gate

Rerun:

```text
CartMutationConcurrencyMysqlTest
```

Phase 6.7 should not alter mutation locking semantics.

---

# 128. Group E Regression

Run focused availability/inventory tests because Phase 6.7 depends directly on them.

At minimum ensure regressions around:

```text
multi-location aggregation
reserved quantity
LOW_STOCK
IN_STOCK availability
```

remain green.

---

# 129. No New Concurrency Algorithm

Phase 6.7 is read-only.

Do not introduce new:

```text
deadlock handling
inventory transaction retry
row-lock ordering
```

for revalidation.

Those already belong to Group E/Checkout.

---

# 130. Read vs Concurrent Inventory Mutation

It is acceptable for:

```text
Cart GET
```

to observe either state immediately before or after a concurrent committed inventory adjustment.

What must never happen is:

```text
invented quantity
negative derived availability
Cart persistence corruption
```

---

# 131. Do Not Promise Repeatable Read

A Cart response is a current observation, not a reservation certificate.

Document this internally.

---

# 132. Performance Scope

Optimize obvious N+1 inventory behavior only.

Do not turn Phase 6.7 into:

```text
Redis caching
materialized inventory views
read replicas
CQRS
```

---

# 133. No New Dependency

Expected:

```text
Dependencies: NONE
```

Use Laravel/database/application infrastructure.

---

# 134. Schema

Expected:

```text
Schema changes: NONE
```

---

# 135. No Migration

Do not add:

```text
cart stock validation columns
inventory cache tables
cart validity tables
```

---

# 136. OpenAPI

Expected:

```text
wire-contract changes: NONE
```

The existing CartItem fields are sufficient:

```text
availability
stock_indicator
is_purchasable
```

Only correct OpenAPI if a genuine implementation/documentation drift is discovered.

---

# 137. No New Public Error

Expected:

```text
new V1 error codes: NONE
```

---

# 138. Documentation

Add the normal backend ADR for Phase 6.7.

Record:

```text
cart-wide stock revalidation design
batch/current inventory resolution
CartItemEligibility reuse
quantity-aware behavior
no persistence/no locks/no reservation
performance/N+1 decision
Checkout authority boundary
```

---

# 139. Suggested ADR Name

For example:

```text
ADR/BACKEND-035 —
Cart-Wide Live Stock Revalidation
```

Use the actual next repository ADR number.

Do not assume `035` if another accepted ADR has already taken it.

---

# 140. Likely Implementation Areas

Expected:

```text
app/Services/Cart/CartStockRevalidator.php
app/Services/Cart/CartItemEligibility.php
app/Services/Cart/CartItemValidationResult.php
app/Support/CatalogAvailability.php
app/Http/Resources/CartResource.php
app/Http/Resources/CartItemResource.php
Cart query/projection service
tests/Feature/CartStockRevalidationTest.php
tests/Unit/... where useful
docs/decisions.md
```

Modify only what is actually necessary.

---

# 141. Avoid God Revalidator

`CartStockRevalidator` should not absorb:

```text
holder authentication
guest token handling
Cart mutation
price calculation
inventory reservation
checkout
```

Its concern is current Cart-level stock evaluation.

---

# 142. Controller Changes

Expected controller changes should be minimal or none.

Do not move stock logic into `CartController`.

---

# 143. Resource Changes

Resources should become simpler if stock evaluation previously happened lazily inside serialization.

Do not make them more business-heavy.

---

# 144. Existing `CartItemEligibility`

Do not weaken its invariant that it is the sole Cart rule source.

The revalidator orchestrates it; it does not compete with it.

---

# 145. Existing `CartItemInvalidReason`

Reuse internal reasons.

Do not add stock-revalidation-specific public states unnecessarily.

If an internal reason such as insufficient quantity already exists:

reuse it.

---

# 146. Effective Quantity

For existing Cart revalidation:

```text
effective quantity = CartItem.quantity
```

No duplicate-add calculation is involved.

---

# 147. CART-002 Effective Quantity

CART-002 still uses:

```text
min(existing + requested, 100)
```

for duplicate add before stock sufficiency evaluation.

Do not change this while integrating shared stock loading.

---

# 148. CART-003 Effective Quantity

CART-003 still uses:

```text
requested quantity
```

as effective quantity.

---

# 149. No Mutation Semantics Change

Phase 6.7 must not change:

```text
CART-002 status codes
CART-003 behavior
CART-004 204 behavior
quantity clamp semantics
timestamps
holder resolution
guest transport
```

---

# 150. Guest Carts

Stock revalidation works identically for:

```text
guest Cart
authenticated Cart
Staff/Admin personal Cart
```

once holder resolution has supplied the Cart.

Do not create different inventory semantics for guests.

---

# 151. Credential Handling

Do not touch:

```text
GuestCartCredential
GuestCartTransport
```

unless a genuine bug is discovered.

Stock revalidation must never see the raw guest credential.

---

# 152. Security

Cart revalidation must not expose:

```text
warehouse_location
physical quantity
reserved quantity
internal ProductStock IDs
supplier data
```

---

# 153. Internal Availability Map

If using a map such as:

```text
variant_id → aggregate available quantity
```

keep it internal to the request/service.

Do not serialize it.

---

# 154. Numeric Safety

Quantities remain integers.

No floating point.

Use database integer values and PHP integer arithmetic.

---

# 155. Empty/Missing Stock Rows

For IN_STOCK Variant with no ProductStock rows:

follow Group E authority.

Expected conceptual result:

```text
available quantity = 0
availability = unavailable
is_purchasable = false
```

Do not fabricate stock.

---

# 156. Zero Stock Rows

Persisted zero-stock rows remain meaningful.

Treat aggregate available as zero.

---

# 157. Inactive Variant Inventory

Inventory belonging to an inactive Variant must not make its Cart line purchasable.

Product/Variant requirement failure precedes stock.

---

# 158. Other Variant Inventory

Do not use stock from another active Variant of the same Product to satisfy a CartItem targeting a specific Variant.

Inventory is Variant-specific.

---

# 159. Product-Level Availability vs Cart Variant

Cart line validation should use the referenced sellable Variant's inventory.

Do not use Product-level aggregate availability across sibling Variants to satisfy a specific Variant line.

Example:

```text
Sofa red: 0
Sofa blue: 10
Cart contains red
```

Red remains unavailable.

---

# 160. Critical Variant Regression

Add a test for:

```text
same Product
Variant A available = 0
Variant B available = 10
Cart line references Variant A
```

Expected:

```text
Cart line unavailable / not purchasable
```

Do not borrow Variant B stock.

---

# 161. Multi-Location Is Within Same Variant

Aggregation is across:

```text
locations for one Variant
```

not across sibling Variants.

---

# 162. Stock Indicator Scope

Use the same Variant/Product scope already established by Cart projection.

Do not accidentally calculate a Variant line's stock indicator from sibling Variant stock.

---

# 163. Stale Product Price vs Stock

Do not perform stock aggregation solely to make an inactive Product display a stock indicator if Phase 6.6 already short-circuits it.

Preserve established projection behavior.

---

# 164. Failure Handling

If an unexpected inventory query failure occurs:

use existing application error handling.

Do not convert infrastructure failure into:

```text
INSUFFICIENT_STOCK
```

That error means authoritative business stock shortage, not database failure.

---

# 165. No Exception Swallowing

Do not silently treat:

```text
database unavailable
```

as:

```text
available = 0
```

That would falsely report business state.

---

# 166. Observability

Normal safe logs may identify:

```text
request_id
Cart operation
safe internal IDs
unexpected failure class
```

Never log guest bearer credential.

No new monitoring framework required.

---

# 167. Unit Tests

Useful unit coverage may include:

```text
quantity comparison
availability map integration
invalid-line short circuiting
Variant-specific lookup
```

Do not duplicate Phase 6.6 error-mapping tests unnecessarily.

---

# 168. Feature Tests

Feature tests should prove what a Cart client actually sees after inventory drift.

This is the main value of Phase 6.7.

---

# 169. MariaDB Requirement

The revalidation calculation itself does not require concurrency locks.

SQLite is sufficient for most semantic coverage.

Use MariaDB only where:

```text
production query shape
aggregation behavior
or an existing MySQL-specific regression
```

requires verification.

---

# 170. Do Not Add Unnecessary Parallel Tests

Phase 5.10 already proved reservation/overselling concurrency.

Phase 6.7 is not another overselling phase.

---

# 171. Still Run Existing MariaDB Gate

Run the existing Cart mutation concurrency gate as regression because shared services may have changed.

Do not create redundant stock race suites.

---

# 172. Query-Portability

Any new aggregate query must work on:

```text
SQLite
MySQL/MariaDB
```

unless cleanly isolated behind an explicit driver-specific implementation.

Prefer portable SQL/Eloquent.

---

# 173. No FULLTEXT Interaction

Phase 6.7 has nothing to do with search.

Do not touch ProductCatalogQuery FULLTEXT logic.

---

# 174. Code Quality

Maintain:

```text
cognitive complexity <= 15
<= 3 returns where practical
small orchestration service
single availability authority
single Cart eligibility authority
no queries in Resource where avoidable
no duplicated stock formulas
```

---

# 175. Focused Verification

Run focused suites including actual repository equivalents of:

```text
CartReadApiTest
CartPurchasabilityApiTest
CartStockRevalidationTest
CartAddItemApiTest
CartUpdateItemApiTest
CartRemoveItemApiTest
CartMutationConcurrencyMysqlTest
```

---

# 176. Canonical Verification

Then run:

```bash
php artisan test
vendor/bin/pint --test
vendor/bin/phpstan analyse
composer audit
git diff --check
php artisan route:list
```

---

# 177. Route Verification

Phase 6.7 must introduce:

```text
new routes = NONE
```

Verify the Cart route set remains unchanged.

---

# 178. OpenAPI Verification

Confirm Phase 6.7 did not accidentally alter:

```text
Cart
CartItem
CART-001
CART-002
CART-003
CART-004
```

wire shapes/statuses.

---

# 179. Completion Report

Return:

## Phase 6.7 status

```text
PASS
```

or:

```text
BLOCKED
```

## Revalidation architecture

Report:

```text
cart-wide orchestration component
CartItemEligibility reuse
CatalogAvailability reuse
```

## Stock authority

State exact source/formula.

## Quantity awareness

Confirm:

```text
available >= CartItem.quantity
```

is required for line-level purchasability.

## Variant scope

Confirm stock is Variant-specific and sibling Variant stock is never borrowed.

## Multi-location

Report aggregate semantics.

## Live drift

Report tests for:

```text
stock decrease
reservation increase
reservation release
inventory increase
```

and automatic Cart projection changes.

## Performance

Report:

```text
query strategy
N+1 result
batch availability behavior
```

## Persistence

Must state:

```text
Cart mutations: NONE
CartItem mutations: NONE
ProductStock mutations: NONE
reserved_quantity mutations: NONE
```

for revalidation.

## Locking

Must state:

```text
ProductStock locks: NONE
```

## Reservation

Must state:

```text
inventory reservation: NONE
```

## Public API

Must state:

```text
new endpoints: NONE
new fields: NONE
new error codes: NONE
```

## Schema

```text
NONE
```

## Dependencies

```text
NONE
```

## Frontend

```text
NONE
```

## Tests

Report exact focused and canonical results.

## MariaDB

Report existing mutation concurrency regression status.

## Quality

Report:

```text
Pint
PHPStan
Composer audit
git diff --check
```

## Phase 6.8 readiness

Return:

```text
READY
```

or:

```text
BLOCKED
```

with exact reason.

---

# 180. Definition of Done

Phase 6.7 is complete when:

* Cart stock is revalidated from live Group E inventory;
* revalidation is Cart-wide rather than ad hoc per Resource;
* `CartItemEligibility` remains the single Cart-domain eligibility authority;
* `CartItemValidationResult` is reused;
* Group E availability logic remains the single stock arithmetic authority;
* current reserved quantities reduce Cart-visible purchasability;
* multi-location stock aggregates correctly per Variant;
* sibling Variant stock cannot satisfy another Variant;
* a full Cart-line quantity must be satisfiable for `is_purchasable=true`;
* coarse `availability` remains independent of requested Cart quantity;
* LOW_STOCK remains informational;
* stock decrease is reflected on the next Cart read;
* reservation changes are reflected on the next Cart read;
* inventory increases/release can automatically restore purchasability;
* stale lines remain stored and visible;
* quantities are never silently reduced;
* unavailable lines are never silently deleted;
* revalidation performs no Cart persistence mutation;
* revalidation performs no CartItem persistence mutation;
* revalidation performs no ProductStock mutation;
* revalidation performs no `reserved_quantity` mutation;
* revalidation acquires no ProductStock write locks;
* revalidation creates no inventory reservation;
* empty Cart revalidation performs no unnecessary inventory work;
* inventory-invalid Product/Variant lines short-circuit before stock lookup;
* stock loading avoids N+1 behavior;
* Resources do not independently reinvent stock logic;
* no new Cart endpoint is added;
* no new response field is added;
* no new V1 error code is added;
* no schema migration is added;
* no dependency is added;
* no frontend code is changed;
* existing add/update/remove semantics remain unchanged;
* existing MariaDB Cart mutation concurrency remains green;
* Group E availability regressions remain green;
* full canonical test suite remains green;
* PHPStan reports zero errors;
* Pint passes;
* Composer audit remains clean.

---

# 181. Out of Scope

Do not implement:

```text
CART-005 guest-cart merge
merge idempotency
checkout
order creation
delivery selection
delivery fee
inventory reservation
payment
stock allocation persistence
warehouse selection
stock notifications
Cart validation endpoint
automatic stale-item cleanup
automatic quantity correction
frontend Cart warnings
```

---

# 182. STOP Condition

STOP when a Cart containing existing items can be read at any later time and accurately reflect the **current live stock situation** without changing the Cart itself:

```text
Cart quantity <= live available
→ is_purchasable true

Cart quantity > live available > 0
→ availability available
→ is_purchasable false

live available = 0
→ availability unavailable
→ is_purchasable false
```

while stock is calculated:

```text
per Variant
across that Variant's locations
after reserved quantity
```

and revalidation performs:

```text
no stock mutation
no reservation
no ProductStock lock
no Cart mutation
```

Do not continue automatically to Phase 6.8.

DO NOT COMMIT, STAGE OR PUSH.

The project owner handles all Git operations.
