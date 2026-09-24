# Phase 6.6 — Cart Validation

## Purpose

Consolidate all Cart-domain validation into one coherent, reusable validation layer.

Phases 6.2–6.5 already implemented:

```text
CART-001 — create/get Cart
CART-002 — add item
CART-003 — update quantity
CART-004 — remove item
```

Phase 6.6 must now eliminate validation drift between those operations and formalize:

```text
Cart admission validation
existing-line validation
stale-state classification
purchasability
quantity validation
Product/Variant relationship validation
error mapping
validation ordering
mutation-vs-read semantics
```

Do not add new Cart endpoints.

Do not reserve inventory.

Do not implement Checkout.

---

# 1. Main Objective

There must be one authoritative answer to:

```text
Can this Product/Variant/quantity enter or remain purchasable in this Cart?
```

All relevant Cart workflows must use that answer consistently.

Avoid separate rules inside:

```text
AddCartItem
UpdateCartItemQuantity
CartItemResource
Cart read projection
future Checkout prevalidation
```

that can drift apart.

---

# 2. Existing Accepted Purchasability Definition

Phase 6.1 established:

```text
Product:
- exists
- is_active = true
- is_published = true
- not soft-deleted
- Category active
- product_type = IN_STOCK

Variant:
- referenced Variant exists where required
- belongs to Product
- is_active = true

Inventory:
- live availability = available
```

Then:

```text
is_purchasable = true
```

Otherwise:

```text
is_purchasable = false
```

for existing Cart lines.

Preserve this definition.

---

# 3. Distinguish Three Validation Contexts

Do not treat all Cart validation identically.

Explicitly separate:

```text
A. Admission validation
B. Mutation validation
C. Existing-line projection validation
```

These have different failure behavior.

---

# 4. Admission Validation

Used when:

```text
CART-002 adds a new Cart identity
```

Admission answers:

```text
May this item enter the Cart now?
```

Failure:

```text
reject request
do not create line
```

---

# 5. Mutation Validation

Used when:

```text
CART-003 changes quantity
```

Mutation answers:

```text
May this existing line be changed to this requested quantity now?
```

Failure:

```text
reject request
preserve existing line unchanged
```

---

# 6. Projection Validation

Used when:

```text
CART-001 reads existing Cart
```

and after successful mutations when CartResource is rendered.

Projection answers:

```text
Is this already-stored line currently purchasable?
```

Failure of purchasability does **not** fail the Cart GET.

Instead:

```text
retain CartItem
availability = unavailable where appropriate
is_purchasable = false
```

The accepted Cart ADR explicitly requires stale items to remain in the Cart instead of being silently removed.

---

# 7. Removal Is Special

CART-004 must not run admission/mutation purchasability validation.

Removal requires only:

```text
valid holder
ACTIVE holder Cart
CartItem belongs to that Cart
```

A stale item must always remain removable.

---

# 8. Recommended Architecture

Create or consolidate around one focused Cart-domain component.

Examples:

```text
CartItemValidator
CartItemPurchasability
CartItemEligibilityResolver
```

Choose repository naming conventions.

Avoid:

```text
CartValidationService
```

becoming a giant class containing transport/auth/DB/controller logic.

---

# 9. Suggested Responsibilities

The shared domain component may expose concepts such as:

```text
validateForAdmission(...)
validateForQuantityMutation(...)
evaluateExistingLine(...)
```

or equivalent value-object/result-based design.

It should centralize:

```text
Product state
Category state
Product type
Variant requirement
Variant ownership
Variant active state
quantity bounds
current stock sufficiency
Cart-specific purchasability
```

---

# 10. Do Not Duplicate Group E

Do not reimplement:

```text
availability
stock_indicator
available quantity aggregation
multi-location inventory aggregation
LOW_STOCK threshold
```

Use completed Group E components such as:

```text
CatalogAvailability
```

and established pricing logic.

---

# 11. No ProductStock Mutation

Validation may read ProductStock.

It must not:

```text
increment reserved_quantity
decrement quantity
lock stock for purchase
create reservations
```

Phase 5.10/Group G checkout remain authoritative for reservation.

---

# 12. Validation Ordering

Use a predictable domain sequence.

Recommended:

```text
1. Product existence
2. Product visible/purchasable state
3. Product type
4. Variant requirement
5. Variant existence
6. Variant belongs to Product
7. Variant active
8. Quantity validity
9. Current informational stock sufficiency
```

Do not perform inventory queries when an earlier Product/Variant rule already makes the request invalid.

---

# 13. Product Existence

For CART-002 admission:

unknown Product must map through the currently frozen Cart error contract.

Do not leak database/model exceptions.

---

# 14. Product Active

If:

```text
is_active = false
```

new admission/update requiring purchasability must fail.

Existing line read:

```text
preserve line
is_purchasable = false
```

---

# 15. Product Publication

If:

```text
is_published = false
```

new admission/update must fail.

Existing Cart line remains visible and non-purchasable.

---

# 16. Soft-Deleted Product

Soft-deleted Product cannot be newly admitted or quantity-mutated as purchasable.

Existing Cart line must remain representable.

Do not lose it because public Product scope hides soft-deleted records.

---

# 17. Category Activity

If Product's Category becomes inactive:

new admission/update fails according to existing Product-unavailable semantics.

Existing Cart line:

```text
is_purchasable = false
```

---

# 18. Product Type

Only:

```text
IN_STOCK
```

may enter or be quantity-mutated through normal Cart flow.

`MADE_TO_ORDER` remains rejected with:

```text
422 PRODUCT_NOT_PURCHASABLE
```

The accepted Cart decision explicitly keeps MADE_TO_ORDER outside normal Cart commerce.

---

# 19. Existing MADE_TO_ORDER Line

If historical/corrupt/test state contains such a line:

CART-001 must not fail.

Return:

```text
is_purchasable = false
stock_indicator = MADE_TO_ORDER
```

as appropriate.

CART-004 must still allow removal.

---

# 20. Variant Requirement

Formalize the result of Phase 6.1.

If a Product requires a sellable Variant:

```text
variant_id required
```

If the current model genuinely supports Product-without-Variant:

follow the frozen nullable Variant contract.

Do not create an ad hoc Product-level pricing fallback.

---

# 21. Variant Existence

Unknown Variant:

```text
422 INVALID_PRODUCT_VARIANT
```

for add/update validation.

Existing stale line should normally remain representable where FK/history permits.

---

# 22. Variant Ownership

Must satisfy:

```text
variant.product_id === product.id
```

No exceptions.

Wrong-parent Variant:

```text
422 INVALID_PRODUCT_VARIANT
```

---

# 23. Variant Active State

Inactive Variant:

Admission/mutation:

```text
reject
```

Projection:

```text
retain line
is_purchasable = false
```

---

# 24. Quantity Validation

Centralize:

```text
integer
minimum 1
maximum CartItem::MAX_QUANTITY
```

Use:

```text
CartItem::MAX_QUANTITY
```

as the only maximum source.

---

# 25. Quantity Types

Reject:

```text
0
negative
101+
float
numeric string
null
array
boolean
```

according to strict request validation.

---

# 26. Duplicate Add Quantity Rule

Do not change the now-implemented accepted semantics:

```text
resulting quantity =
min(existing + requested, CartItem::MAX_QUANTITY)
```

Duplicate additions merge and clamp rather than creating duplicate lines. The accepted ADR freezes this behavior.

---

# 27. Effective Quantity

Shared validation must distinguish:

```text
incoming quantity
```

from:

```text
effective resulting Cart quantity
```

For duplicate add:

```text
effective = min(existing + incoming, 100)
```

For PATCH:

```text
effective = requested quantity
```

Stock validation must use effective quantity.

---

# 28. Informational Stock Validation

For add/update:

```text
effective_quantity <= current available quantity
```

must hold.

If not:

```text
422 INSUFFICIENT_STOCK
```

according to current implemented Cart semantics.

---

# 29. Multi-Location Stock

Available quantity must use the established Group E aggregation.

Do not validate against:

```text
first ProductStock row
one warehouse
quantity without reservations
```

---

# 30. Reserved Stock Matters

Example:

```text
quantity = 10
reserved_quantity = 8
available = 2
```

Cart validation must treat:

```text
available = 2
```

not 10.

---

# 31. Cart Stock Validation Is Not a Guarantee

Document clearly:

```text
Cart validation = current informational admission check
Checkout = final authoritative locked validation
```

Successful add/update does not guarantee future Checkout success.

---

# 32. Do Not Hold Inventory Locks

Phase 6.6 must not introduce:

```text
ProductStock::lockForUpdate()
```

for Cart validation.

The Cart intentionally does not reserve stock.

---

# 33. Projection Availability

For existing line, expose Group E:

```text
availability
stock_indicator
```

from current state.

---

# 34. Quantity-Aware `is_purchasable`

Review the existing Phase 6.2 projection carefully.

A line with:

```text
availability = available
```

may still have:

```text
Cart quantity > current available quantity
```

Example:

```text
Cart quantity = 5
available stock = 2
```

That line should not be considered checkout-ready.

Therefore Cart-specific `is_purchasable` should consider the entire line quantity, not merely coarse Variant availability.

---

# 35. Canonical Purchasability Formula

Prefer:

```text
is_purchasable =
    product visible/purchasable
    AND product_type = IN_STOCK
    AND variant valid/active
    AND live available quantity >= CartItem.quantity
```

This is more precise than:

```text
availability == available
```

alone.

If current Phase 6.2 implementation only checks `availability == available`, Phase 6.6 should correct that as a genuine validation consolidation defect.

---

# 36. Important Example

Given:

```text
Cart line quantity = 8
current available quantity = 3
```

Group E coarse Variant availability may still be:

```text
available
```

because stock is greater than zero.

But Cart line must be:

```text
is_purchasable = false
```

because Checkout cannot currently satisfy the requested quantity.

---

# 37. `availability` vs `is_purchasable`

Do not conflate them.

`availability` answers:

```text
Does this Product/Variant currently have any sellable availability?
```

Cart `is_purchasable` answers:

```text
Can this specific Cart line at its current quantity proceed?
```

---

# 38. Out-of-Stock Line

If:

```text
available quantity = 0
```

then:

```text
availability = unavailable
is_purchasable = false
```

---

# 39. Partial Stock Line

If:

```text
available quantity > 0
but
available quantity < Cart quantity
```

then expected:

```text
availability = available
is_purchasable = false
```

unless the frozen Cart contract explicitly requires `availability` to become unavailable for quantity-specific semantics.

Do not redefine Group E `availability`.

---

# 40. LOW_STOCK

`LOW_STOCK` is informational.

It does not itself make a line unpurchasable.

Example:

```text
available = 4
Cart quantity = 2
```

may be:

```text
stock_indicator = LOW_STOCK
is_purchasable = true
```

---

# 41. Stale-State Classification

Centralize reasons internally.

Potential internal reasons:

```text
PRODUCT_INACTIVE
PRODUCT_UNPUBLISHED
PRODUCT_DELETED
CATEGORY_INACTIVE
MADE_TO_ORDER
VARIANT_MISSING
VARIANT_WRONG_PARENT
VARIANT_INACTIVE
OUT_OF_STOCK
INSUFFICIENT_FOR_CART_QUANTITY
```

These do not automatically become public API enums.

---

# 42. Do Not Expand Public Contract Accidentally

Internal validation reasons must not become new:

```text
error codes
response fields
CLOSED enums
```

unless already frozen.

Use them internally to derive existing API behavior.

---

# 43. Validation Result Object

Prefer a structured internal result over a pile of booleans.

Conceptually:

```text
CartItemValidationResult
```

with safe concepts such as:

```text
isPurchasable
availability
stockIndicator
effectiveAvailableQuantity (internal only)
reason
```

Do not expose internal inventory quantities from the Resource unless contracted.

---

# 44. Admission Errors vs Projection Reasons

The same internal validation reason may map differently depending on context.

Example:

```text
MADE_TO_ORDER
```

Admission:

```text
422 PRODUCT_NOT_PURCHASABLE
```

Projection:

```text
200 Cart
is_purchasable = false
```

This is why validation context must be explicit.

---

# 45. Error Mapping

Create one consistent mapping from validation failure to existing Cart errors.

Do not let controllers decide independently.

---

# 46. Product Type Error

Canonical:

```text
MADE_TO_ORDER
→ PRODUCT_NOT_PURCHASABLE
```

---

# 47. Other Product Visibility Failures

Based on the completed 6.3–6.5 behavior:

```text
inactive
unpublished
soft-deleted
inactive Category
→ PRODUCT_UNAVAILABLE
```

Preserve this established mapping unless the frozen API contract explicitly contradicts it.

---

# 48. Variant Errors

Canonical:

```text
missing required Variant
unknown Variant
wrong-parent Variant
inactive Variant
→ INVALID_PRODUCT_VARIANT
```

where current contract already maps them this way.

---

# 49. Quantity Errors

Structural/type/bounds errors remain:

```text
INVALID_TYPE
INVALID_VALUE
MISSING_REQUIRED_FIELD
```

as appropriate.

Do not convert schema errors into domain errors.

---

# 50. Stock Errors

When otherwise valid line cannot satisfy requested effective quantity:

```text
INSUFFICIENT_STOCK
```

---

# 51. Ownership Errors Stay Outside Domain Validator

Do not make CartItemValidator responsible for:

```text
Clerk verification
guest credential verification
Cart holder ownership
IDOR masking
```

Those remain Phase 6.2 holder/route concerns.

---

# 52. Validation Layering

Preserve project pipeline:

```text
Transport
→ Schema
→ Authentication
→ Holder/Authorization
→ Domain Cart Validation
→ Transaction
→ Persistence
```

Do not query Product records before malformed request input is rejected.

---

# 53. Add Workflow Refactor

Refactor CART-002 to use the shared validator.

Target flow:

```text
validated input
→ resolve holder Cart
→ resolve Product/Variant
→ calculate effective duplicate quantity
→ shared admission validation
→ transactional insert/increment
→ CartResource
```

---

# 54. Update Workflow Refactor

CART-003 target:

```text
validated quantity
→ resolve holder Cart
→ holder-scoped CartItem
→ shared mutation validation
→ transaction/lock item
→ update quantity
→ CartResource
```

---

# 55. Projection Refactor

CART-001 / CartItemResource should use the same validation/purchasability component.

Do not duplicate:

```text
is_active
is_published
product_type
Variant active
availability
```

checks inside Resource code.

---

# 56. Resource Must Not Own Business Rules

API Resources should format results.

Avoid:

```php
if ($product->is_active && ...)
```

business-rule chains directly inside `toArray()`.

Move them to the shared domain resolver.

---

# 57. Remove Workflow

CART-004 should intentionally bypass purchasability validation.

Only holder-scoped CartItem resolution is needed.

Document this explicitly so a future refactor does not accidentally prevent stale-line removal.

---

# 58. Do Not Validate Stock on DELETE

Removal must remain possible regardless of Product/Variant/inventory state.

---

# 59. Stale Line Persistence

Do not:

```text
delete
detach
replace
fix
```

a stale line automatically.

The accepted contract says stale lines remain and are flagged.

---

# 60. Stale Line Price

Reuse current Cart projection semantics.

If the Product/Variant still has a resolvable catalog price:

display current server-side price.

Do not persist a stale snapshot.

If a valid price genuinely cannot be resolved, follow current frozen resource behavior; do not invent zero price.

---

# 61. Category Visibility Regression

Ensure Category activity is part of Cart purchasability.

This was recorded in Phase 6.1 and must not be lost when validation is centralized.

---

# 62. Product Soft Delete Regression

Ensure:

```text
Product::withTrashed()
```

or equivalent context-specific loading remains available for Cart projection.

Do not alter public Product queries.

---

# 63. Variant Active Regression

Ensure inactive Variant remains visible enough in the private Cart projection to mark the line stale.

Do not globally apply active-only relation scope to stored CartItem projection.

---

# 64. Product Type Change Regression

If a Product already in Cart changes:

```text
IN_STOCK → MADE_TO_ORDER
```

Cart read must preserve line and mark it not purchasable.

---

# 65. Publication Change Regression

If:

```text
published → unpublished
```

same behavior:

```text
line retained
is_purchasable = false
```

---

# 66. Category Change Regression

If Product remains active/published but Category becomes inactive:

line remains but becomes not purchasable.

---

# 67. Quantity/Stock Drift Regression

If Cart had quantity:

```text
6
```

and inventory later drops to:

```text
4 available
```

Cart GET must show:

```text
line retained
is_purchasable = false
```

without changing quantity.

---

# 68. Stock Recovery

If inventory later increases again to satisfy quantity:

next Cart GET should automatically show:

```text
is_purchasable = true
```

without any Cart persistence mutation.

---

# 69. Product Recovery

If an unpublished/inactive Product is legitimately reactivated/published again:

next Cart read may become purchasable again if all other conditions pass.

No CartItem rewrite required.

---

# 70. Validation Is Live

Cart validation state is derived at request time.

Do not persist:

```text
validation_status
invalid_reason
is_purchasable
availability
```

into `cart_items`.

---

# 71. No Validation Timestamp

Do not add:

```text
validated_at
last_stock_check_at
```

unless explicitly required by a later contract.

---

# 72. No Cart Error State

Do not add Cart status values such as:

```text
INVALID
STALE
NEEDS_REVIEW
```

ACTIVE/INACTIVE remain sufficient.

---

# 73. No Automatic Quantity Reduction

If available quantity falls below Cart quantity:

do not silently reduce:

```text
8 → 3
```

Mark line not purchasable.

Customer may explicitly PATCH quantity.

---

# 74. PATCH Can Repair Stale Quantity

Example:

```text
Cart quantity = 8
available = 3
```

PATCH:

```text
quantity = 3
```

should succeed if all Product/Variant rules pass.

This is how customer can repair stock-related stale state.

---

# 75. PATCH Cannot Repair Product State

If Product is:

```text
unpublished
inactive
MADE_TO_ORDER
```

changing quantity alone must not make the line valid.

Return the established Product-domain error.

Customer can remove it.

---

# 76. Add Current Stock Boundary

Adding a new item with requested quantity exceeding availability continues to fail.

Do not loosen admission just because stale lines are allowed to remain.

---

# 77. Validation vs Stock Revalidation Phase 6.7

Keep the phase boundary clear.

Phase 6.6 owns:

```text
shared validation semantics
purchasability definition
error mapping
validation context
consolidation
```

Phase 6.7 should own deeper stock-revalidation workflow/hardening.

Do not pull checkout concurrency or reservation into 6.6.

---

# 78. What 6.7 May Build On

Expose clean interfaces so Phase 6.7 can efficiently:

```text
re-evaluate all Cart lines
identify insufficient stock
produce checkout preflight state
```

without rewriting Product/Variant validation.

---

# 79. No Checkout Preflight Endpoint

Do not invent:

```text
POST /me/cart/validate
GET /me/cart/validation
```

in Phase 6.6.

Validation remains internal to existing Cart operations.

---

# 80. No New Public Fields

Do not add fields such as:

```text
validation_errors
invalid_reason
required_quantity
available_quantity
```

to CartItem unless already in frozen API contract.

---

# 81. `is_purchasable` Remains Boolean

Do not change it to:

```text
status enum
string reason
object
```

within V1.

---

# 82. Preserve Public Inventory Privacy

Even if validation internally knows:

```text
available quantity
```

normal Cart response must not start exposing raw ProductStock quantities unless frozen contract explicitly permits it.

---

# 83. Safe `INSUFFICIENT_STOCK` Details

Inspect current contract before emitting quantity details in errors.

If safe details are already approved:

reuse them.

Otherwise do not introduce them.

---

# 84. Cart Pricing Validation

Validation must use the same Variant that determines:

```text
unit_price
```

Do not validate Variant A while pricing Variant B.

---

# 85. No Client Price Validation

Do not compare client-supplied price against server price because client price must not be accepted at all.

---

# 86. Money Is Not Purchasability

A price of zero or a valid price object does not by itself make Product purchasable.

Follow Product type/state rules.

---

# 87. Transaction Semantics

Validation used to decide a mutation must happen close enough to persistence to avoid avoidable state drift.

For Cart mutations, exact stock can still change immediately afterward and that is acceptable.

Do not turn Cart validation into checkout-grade locking.

---

# 88. Same-Line Lock Ordering

CART-002/003 concurrency mechanics from the completed combined phase should remain intact.

Do not weaken:

```text
CartItem lock
unique-line recovery
serialized quantity update
```

during refactor.

---

# 89. Refactor Must Preserve MariaDB Race Safety

After validation consolidation rerun:

```text
CartMutationConcurrencyMysqlTest
```

All existing races must stay green.

---

# 90. No Change to Duplicate Clamp

Validation refactor must not accidentally convert:

```text
99 + 2 → 100
```

into:

```text
422
```

The accepted duplicate-add ADR says merge/clamp.

---

# 91. Direct PATCH Still Strict

Contrast:

```text
POST duplicate add:
99 + 2 → 100
```

with:

```text
PATCH quantity: 101
→ 422
```

These are intentionally different semantics.

Document and test them.

---

# 92. Admission/Projection Matrix

Create a concise internal test matrix similar to:

```text
State                     Add    Update   GET
------------------------------------------------
valid                      OK     OK       purchasable
inactive Product           422    422      stale
unpublished Product        422    422      stale
deleted Product            422    422      stale
inactive Category          422    422      stale
MADE_TO_ORDER              422    422      stale
wrong Variant              422    422*     stale/corrupt
inactive Variant           422    422      stale
stock = 0                  422    422      stale
stock < cart quantity      422    422      stale
stock >= cart quantity     OK     OK       purchasable
```

Use exact existing error codes.

`*` Existing persisted wrong-parent Variant should normally be prevented by model/FK-domain invariants.

---

# 93. Removal Matrix

For all stale states above:

```text
DELETE
→ allowed
```

provided item belongs to holder's Cart.

---

# 94. Test — Shared Product Validation

Prove CART-002 and CART-003 map identical Product invalid states consistently.

---

# 95. Test — Shared Variant Validation

Same for:

```text
wrong parent
inactive
missing required Variant
```

---

# 96. Test — Shared Quantity Validation

Ensure same central bounds are used where appropriate.

---

# 97. Test — Cart GET Valid Line

Valid Product/Variant with enough quantity:

```text
is_purchasable = true
```

---

# 98. Test — Quantity Greater Than Available

Example:

```text
Cart quantity = 5
available quantity = 2
```

assert:

```text
availability = available
is_purchasable = false
```

if Group E coarse availability remains positive.

This is a critical Phase 6.6 regression test.

---

# 99. Test — Exact Stock Boundary

```text
Cart quantity = 5
available = 5
```

must be purchasable.

---

# 100. Test — One Less Stock

```text
Cart quantity = 5
available = 4
```

must not be purchasable.

---

# 101. Test — Reserved Stock

```text
physical = 10
reserved = 6
Cart quantity = 5
available = 4
```

must be non-purchasable.

---

# 102. Test — Multi-Location Aggregate

Use multiple ProductStock rows.

Validation must compare Cart quantity to Group E aggregate available quantity.

---

# 103. Test — Stale Product Read

For each:

```text
inactive
unpublished
soft-deleted
inactive Category
```

CART-001:

```text
200
line present
is_purchasable false
```

---

# 104. Test — Stale Variant Read

Inactive Variant:

same behavior.

---

# 105. Test — MADE_TO_ORDER Read

Historical line:

```text
line remains
is_purchasable false
```

---

# 106. Test — Stock Recovery

Cart line begins invalid due to stock.

Increase ProductStock.

GET again:

```text
is_purchasable true
```

without CartItem update.

---

# 107. Test — Product Reactivation

Where Product is legitimately restored:

Cart projection should reflect current state automatically.

---

# 108. Test — No Persistence From GET

Comparing DB before/after validation projection:

```text
Cart unchanged
CartItem unchanged
ProductStock unchanged
timestamps unchanged
```

---

# 109. Test — Update Repair

Invalid due only to quantity:

```text
qty 8
available 4
```

PATCH to:

```text
4
```

succeeds.

---

# 110. Test — Update Cannot Repair Inactive Product

PATCH quantity on inactive Product:

fails.

Line remains unchanged.

---

# 111. Test — Delete Always Cleans Stale Line

Repeat stale scenarios with DELETE.

Must succeed if owned.

---

# 112. Test — Duplicate Add Clamp Preserved

Validation consolidation must keep:

```text
99 + 2 → 100
```

---

# 113. Test — Direct 101 Rejected

PATCH:

```text
101
```

must remain invalid.

---

# 114. Test — Existing Mutation Suite

All:

```text
CartAddItemApiTest
CartUpdateItemApiTest
CartRemoveItemApiTest
```

must remain green.

---

# 115. Test — Phase 6.2 Read Suite

Cart read/create tests must remain green.

---

# 116. Test — MariaDB Concurrency

Rerun:

```text
CartMutationConcurrencyMysqlTest
```

because refactoring validation around mutation transactions could affect lock timing/flow.

---

# 117. No New MariaDB Algorithm

Do not alter the proven Cart concurrency algorithm unless consolidation requires it.

---

# 118. Performance

Validation consolidation should reduce duplicate queries.

Do not introduce:

```text
one Product query
one Variant query
multiple stock queries
```

per validation layer when existing eager-loading/resolvers can supply state efficiently.

---

# 119. Avoid N+1 on CART-001

Cart with multiple lines should still use bounded/eager queries.

Phase 6.6 should not make each CartItem invoke independent ProductStock queries if Group E supports batching.

---

# 120. Query Count Regression

Where current tests support stable query-count bounds:

retain or add a reasonable N+1 regression test for multi-item Cart projection.

Avoid brittle exact SQL counts.

---

# 121. Domain Result Reuse

The same resolved validation result should ideally feed:

```text
availability
stock_indicator
is_purchasable
```

for a CartItem response.

Do not calculate these three through unrelated code paths.

---

# 122. Do Not Expose Validation Reasons

Internal stale reason is for backend reasoning/tests.

Cart API remains frozen.

---

# 123. Error Message Consistency

The same failure should map to the same machine code across:

```text
CART-002
CART-003
```

Messages may be human-readable but clients branch on code.

---

# 124. Do Not Change HTTP Statuses

Keep existing:

```text
422 domain validation
404 ownership masked item
401 holder credential failure
429 rate limit
```

according to current endpoints.

---

# 125. No New Cart Endpoint

Routes remain:

```text
GET    /api/v1/me/cart
POST   /api/v1/me/cart/items
PATCH  /api/v1/me/cart/items/{item}
DELETE /api/v1/me/cart/items/{item}
```

CART-005 remains separate.

---

# 126. CART-005 Still Out of Scope

Do not implement:

```text
POST /api/v1/me/cart/merge
```

during Phase 6.6.

---

# 127. CART-005 Future Reuse

Design validation so merge can eventually reuse the same:

```text
Product/Variant state
quantity rules
purchasability projection
```

without prematurely implementing merge.

---

# 128. Checkout Future Reuse

Likewise, future checkout may reuse semantic validation concepts, but Checkout still must perform its own authoritative transaction-time stock validation.

Do not make Checkout simply trust:

```text
CartItem.is_purchasable
```

from an earlier read.

---

# 129. Cart Validation Is Advisory to Checkout

Even if:

```text
all Cart lines currently is_purchasable = true
```

Checkout must still lock/revalidate stock.

Document this boundary.

---

# 130. No Reservation

Explicit completion report must state:

```text
ProductStock quantity mutations: NONE
reserved_quantity mutations: NONE
ProductStock locks: NONE
inventory reservations: NONE
```

---

# 131. Schema Changes

Expected:

```text
NONE
```

No validation state belongs in database schema.

---

# 132. Dependencies

Expected:

```text
NONE
```

---

# 133. Frontend

Expected:

```text
NONE
```

Do not modify Next.js, Flutter, or design system.

---

# 134. Documentation

Update:

```text
docs/decisions.md
```

with a Phase 6.6 backend ADR if repository conventions continue that sequence.

Record:

```text
shared validator/resolver
three validation contexts
quantity-aware is_purchasable
error mappings
no-reservation boundary
```

---

# 135. OpenAPI

Expected wire contract change:

```text
NONE
```

unless Phase 6.6 discovers existing runtime/OpenAPI drift.

Do not add validation-reason fields.

---

# 136. Likely Implementation Areas

Expected:

```text
app/Services/Cart/
app/Domain/Cart/
shared Cart item validation/result object
AddCartItem
UpdateCartItemQuantity
CartItemResource/projection
tests/Feature/
tests/Unit/
docs/decisions.md
```

Use actual project naming conventions.

---

# 137. Code Quality

Use `.github/instructions/sonarqube_mcp.instructions.md` to analyze the code

And also

Maintain:

```text
cognitive complexity <= 15
<= 3 returns where practical
single Cart eligibility authority
no Product/Variant rule duplication
no stock arithmetic duplication
thin controllers/resources
```

---

# 138. Verification

Use sonarqube to analyze the codebase changes for code quality and security. Instructions are in `.github/instructions/sonarqube_mcp.instructions.md`.

Run focused Cart tests.

Then:

```bash
php artisan test
vendor/bin/pint --test
vendor/bin/phpstan analyse
composer audit
git diff --check
php artisan route:list
```

Rerun the existing MariaDB Cart mutation concurrency gate.

---

# 139. Completion Report

Return:

## Phase 6.6 status

```text
PASS
```

or:

```text
BLOCKED
```

## Codebase analysis

use `.github/instructions/sonarqube_mcp.instructions.md` to enforce and analyze the code for code quality and security

## Shared validation

State the final central component(s).

## Validation contexts

Report:

```text
admission
mutation
projection
```

and how their outcomes differ.

## Product rules

Report:

```text
active
published
not deleted
Category active
IN_STOCK
```

## Variant rules

Report:

```text
required semantics
exists
parent-owned
active
```

## Quantity

Report:

```text
1..100
duplicate-add clamp preserved
effective quantity handling
```

## Stock

Report:

```text
aggregate Group E available quantity
quantity-aware Cart purchasability
```

## `is_purchasable`

State the exact formula.

## Error mapping

Report exact mapping for:

```text
MADE_TO_ORDER
Product unavailable
Variant invalid
quantity invalid
insufficient stock
```

## Stale Cart behavior

Confirm:

```text
preserved
not silently mutated
removable
```

## Inventory boundary

Must state:

```text
ProductStock mutations: NONE
reserved_quantity mutations: NONE
ProductStock locks: NONE
reservations: NONE
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

Report focused/full counts.

## MariaDB

Report Cart mutation concurrency regression status.

## Quality

Report:

```text
Pint
PHPStan
Composer audit
git diff --check
```

## Phase 6.7 readiness

Return:

```text
READY
```

or:

```text
BLOCKED
```

---

# 140. Definition of Done

Phase 6.6 is complete when:

* Cart validation rules have one authoritative implementation;
* admission, mutation, and projection contexts are explicitly distinguished;
* CART-002 uses shared validation;
* CART-003 uses shared validation;
* CART-001 projection uses shared purchasability evaluation;
* CART-004 intentionally bypasses Product/stock purchasability checks;
* inactive Product behavior is consistent;
* unpublished Product behavior is consistent;
* soft-deleted Product behavior is consistent;
* inactive Category behavior is consistent;
* MADE_TO_ORDER behavior is consistent;
* Variant ownership validation is consistent;
* inactive Variant behavior is consistent;
* quantity validation is centralized;
* duplicate-add clamp semantics remain unchanged;
* direct PATCH quantity >100 remains rejected;
* stock validation uses Group E aggregate available quantity;
* reserved stock is accounted for;
* multi-location inventory remains correct;
* `is_purchasable` is quantity-aware;
* coarse `availability` is not incorrectly used as a substitute for line-level purchasability;
* stale lines remain visible;
* stale lines are never silently removed;
* stale quantity is never silently reduced;
* stale lines remain removable;
* stock recovery automatically makes eligible lines purchasable again;
* Product recovery automatically updates projection;
* Cart GET causes no persistence mutation;
* no ProductStock mutation occurs;
* no stock reservation occurs;
* no ProductStock lock is introduced;
* existing Cart mutation concurrency remains safe;
* MariaDB Cart concurrency tests remain green;
* no API schema expansion occurs;
* no Cart schema change occurs;
* no frontend changes occur;
* PHPStan reports zero new errors;
* Pint passes;
* Composer audit remains clean.

---

# 141. Out of Scope

Do not implement:

```text
CART-005 merge
merge idempotency
new Cart validation endpoint
checkout
inventory reservation
Order creation
payment
automatic stale-line cleanup
automatic quantity correction
frontend warnings/UI
```

---

# 142. STOP Condition

STOP when every Cart path answers Product/Variant/quantity validity through the same shared Cart-domain validation rules, while preserving the deliberate difference:

```text
new invalid item
→ reject

invalid quantity mutation
→ reject

existing stale line
→ retain + is_purchasable false

remove stale line
→ allow
```

and a Cart line is considered purchasable only when the **entire requested Cart quantity** can currently be satisfied.

Do not continue automatically to Phase 6.7.

DO NOT COMMIT OR PUSH.

The project owner handles all Git operations.
