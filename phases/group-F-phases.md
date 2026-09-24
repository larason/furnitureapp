# Combined Phase 6.3–6.5 — Cart Item Mutations

## Scope

Combine:

```text
Phase 6.3 — Add cart item
Phase 6.4 — Update cart item quantity
Phase 6.5 — Remove cart item
```

into one implementation phase.

Implement:

```text
CART-002
POST /api/v1/me/cart/items

CART-003
PATCH /api/v1/me/cart/items/{item}

CART-004
DELETE /api/v1/me/cart/items/{item}
```

These operations must work for:

```text
authenticated self-owned carts
guest credential-owned carts
```

using the holder-resolution infrastructure completed in Phase 6.2.

Do not implement Checkout.

Do not reserve inventory.

Do not implement CART-005 guest-cart merge in this combined phase unless the current AGENTS.md explicitly places merge inside Phase 6.5.

---

# 1. Reuse Phase 6.2 Infrastructure

Do not recreate:

```text
CartHolderResolver
active Cart resolution
guest credential validation
browser-vs-Flutter transport handling
opaque Cart IDs
opaque CartItem IDs
CartResource
CartItemResource
private cache behavior
optional Clerk authentication
```

All three mutation endpoints must use the same established holder boundary.

---

# 2. Core Mutation Pipeline

For every Cart item mutation follow:

```text
Transport
→ Schema validation
→ Authentication / guest credential validation
→ Holder resolution
→ Cart ownership
→ Item ownership where applicable
→ Domain validation
→ Current catalog / stock read
→ Transactional Cart mutation
→ Reload Cart projection
→ Response
```

Do not collapse everything into the controller.

---

# 3. Holder Rules Remain Unchanged

Authenticated request:

```text
authenticated local User
→ own ACTIVE Cart
```

Guest request:

```text
valid guest credential
→ bound ACTIVE guest Cart
```

Invalid attempted Clerk authentication must not downgrade to guest.

Authenticated identity continues to win over a simultaneously supplied guest credential.

No implicit merge.

---

# 4. No Client Cart Selection

The client must never submit:

```text
cart_id
user_id
guest_token_digest
owner_id
```

to select mutation ownership.

All mutations operate on the resolved holder's ACTIVE Cart.

---

# 5. CART-002 — Add Item

Implement:

```http
POST /api/v1/me/cart/items
```

Request body:

```json
{
  "product_id": "prod_...",
  "variant_id": "var_...",
  "quantity": 2
}
```

with conditional `variant_id` behavior according to the finalized Cart/Product model.

---

# 6. CART-002 Accepted Fields

Only:

```text
product_id
variant_id
quantity
```

may be accepted.

Use strict request validation.

Unknown fields must fail.

---

# 7. Reject Server-Controlled Fields

Reject attempts to send:

```text
price
unit_price
line_total
subtotal
total
currency
discount
delivery_fee
availability
stock_indicator
is_purchasable
reserved_quantity
available_quantity
stock
warehouse_location
cart_id
user_id
guest_token_digest
created_at
updated_at
```

Do not silently mass assign them.

---

# 8. FormRequest

Use a dedicated request such as:

```text
AddCartItemRequest
```

Use:

```php
$request->validated()
```

Never:

```php
$request->all()
```

---

# 9. Product Resolution

Resolve `product_id` through the canonical opaque-ID mechanism.

Do not accept raw DB ID if the public contract requires:

```text
prod_...
```

---

# 10. Product Must Exist

Unknown Product:

use the canonical Cart-domain validation/error mapping.

Do not leak database exceptions.

---

# 11. Product Must Be Cart-Purchasable

For new admission Product must satisfy the already-recorded Phase 6.1 rules:

```text
is_active = true
is_published = true
not soft-deleted
Category active
product_type = IN_STOCK
```

Do not use public availability alone as the complete admission rule.

---

# 12. MADE_TO_ORDER

If:

```text
product_type = MADE_TO_ORDER
```

reject:

```text
422 PRODUCT_NOT_PURCHASABLE
```

It belongs to the Request Furniture workflow.

Do not permit it because it has a display price.

---

# 13. Unpublished / Inactive Product

Use the finalized endpoint-specific Cart error mapping from the frozen contract.

Do not expose internal publication state unnecessarily.

Do not silently add the line.

---

# 14. Variant Ownership

When `variant_id` is supplied:

```text
Variant exists
Variant belongs to Product
Variant is active
```

All must hold.

Otherwise:

```text
422 INVALID_PRODUCT_VARIANT
```

according to the contract.

---

# 15. Variant Parent Validation

Never accept:

```text
Product A
Variant belonging to Product B
```

even if the Variant has a valid price and inventory.

---

# 16. Variant Is Server-Validated

Do not trust:

```text
variant_id
```

simply because it exists.

Validate parent ownership and activity.

---

# 17. Nullable Variant Semantics

Follow the result of Phase 6.1's model reconciliation.

If the current V1 implementation requires every purchasable Product to have a Variant because ProductVariant is the pricing/sellable boundary:

enforce that rule consistently.

Do not invent a Product-level price fallback.

---

# 18. Quantity

Quantity must be:

```text
integer
1..CartItem::MAX_QUANTITY
```

Current maximum:

```text
100
```

Use the domain constant.

Do not duplicate magic `100` throughout requests/services/tests.

---

# 19. Strict Quantity Input

Reject:

```text
0
negative
101+
1.5
"2"
null
array
```

using canonical validation errors.

---

# 20. Informational Stock Validation on Add

Cart mutation does not reserve stock.

However add-item admission should verify current availability sufficiently to avoid knowingly adding an impossible quantity.

Conceptually:

```text
current available stock >= resulting cart quantity
```

for IN_STOCK Products.

---

# 21. Available Stock

Use Group E authority:

```text
available_quantity
=
quantity - reserved_quantity
```

aggregated according to existing Variant inventory rules.

Do not recreate stock arithmetic.

---

# 22. No Stock Reservation

Successful CART-002 must not alter:

```text
ProductStock.quantity
ProductStock.reserved_quantity
```

Cart validation is informational only.

Checkout owns reservation.

---

# 23. Duplicate Add Behavior

If the same Cart already contains:

```text
(product_id, variant_id)
```

do not insert another row.

Merge quantities.

---

# 24. Resulting Quantity

Conceptually:

```text
resulting_quantity
=
existing_quantity + requested_quantity
```

---

# 25. Frozen Duplicate Behavior

The frozen decision says duplicate additions merge quantities rather than creating a duplicate CartItem.

Preserve this behavior.

---

# 26. Quantity Ceiling During Repeat Add

Do not let the stored quantity exceed:

```text
CartItem::MAX_QUANTITY
```

Review the frozen ADR wording carefully because it says duplicate additions are "clamped" to 100.

Implement exactly the accepted contract.

If accepted runtime semantics are:

```text
min(existing + requested, 100)
```

use that.

Do not silently change it into a 422 overflow rejection merely because another earlier planning document suggested rejection.

---

# 27. Stock Check Uses Resulting Quantity

Example:

```text
existing cart qty = 3
new add qty = 2
```

stock validation should evaluate:

```text
resulting cart qty = 5
```

not merely the incoming 2.

---

# 28. Add Item Transaction

Repeat-add must be concurrency-safe.

A naive:

```text
SELECT existing item
then INSERT/UPDATE
```

can race.

Use a DB transaction and existing uniqueness constraints as defensive protection.

---

# 29. Same-Line Concurrent Add Race

Example:

```text
current qty = 1

Request A adds 1
Request B adds 1
```

The implementation must not:

```text
create duplicate rows
lose one increment
break the max quantity
```

---

# 30. Concurrency Strategy

Use the simplest correct database-backed strategy.

Preferred:

```text
transaction
→ resolve target CartItem
→ lock existing line when present
→ calculate resulting quantity
→ validate
→ update
```

For absent-line races:

use the unique item identity constraint and clean recovery/retry.

Do not remove the DB uniqueness constraints.

---

# 31. Deterministic Item Identity

Identity remains:

```text
cart_id + product_id + variant_id
```

with the existing null-Variant duplicate guard.

---

# 32. No Inventory Row Lock for Cart Add

Do not use:

```text
ProductStock::lockForUpdate()
```

just to add to Cart.

Stock can change immediately after Cart mutation anyway.

Only checkout performs authoritative locked inventory reservation.

---

# 33. Stock Race Semantics

If stock changes between:

```text
Cart validation
and
Checkout
```

that is expected.

Checkout revalidates authoritatively.

Cart success is never a stock guarantee.

---

# 34. CART-002 Response

Return the updated Cart representation.

Preferred:

```text
200
```

with:

```json
{
  "data": {
    "...": "full current Cart"
  }
}
```

if that matches frozen OpenAPI.

Do not invent a different item-only response if the frozen contract returns Cart.

Check current OpenAPI and follow it exactly.

---

# 35. CART-003 — Update Quantity

Implement:

```http
PATCH /api/v1/me/cart/items/{item}
```

---

# 36. Update Request Body

Accept exactly:

```json
{
  "quantity": 4
}
```

Only `quantity` is mutable.

---

# 37. Immutable Fields

Reject attempts to change:

```text
product_id
variant_id
cart_id
price
availability
stock
```

through CART-003.

Changing Product/Variant means removing one line and adding another.

---

# 38. Quantity Zero Is Not Delete

Frozen rule:

```text
quantity = 0
```

is invalid.

Removal uses:

```text
DELETE /me/cart/items/{item}
```

Do not overload PATCH with deletion behavior.

---

# 39. Item Resolution

Decode the opaque:

```text
item_...
```

identifier.

Then resolve it only inside the current holder's ACTIVE Cart.

---

# 40. Object-Level IDOR Protection

Never:

```text
find CartItem globally by ID
→ authorize after
```

in a way that leaks existence.

Prefer holder-scoped resolution:

```text
current Cart
→ its items
→ matching item ID
```

---

# 41. Cross-Cart Item

If the item:

```text
does not exist
or
belongs to another Cart
```

return the same masked result:

```text
404 CART_ITEM_NOT_FOUND
```

or the exact frozen equivalent.

Do not return 403 for cross-holder probes.

The security ADR explicitly requires CART-003/004 ownership masking.

---

# 42. Update Revalidates Current Domain State

Before accepting a new quantity, re-evaluate current:

```text
Product
Product type
publication/activity
Variant validity/activity
current stock availability
```

Do not rely solely on state from when the item was originally added.

---

# 43. Stale Item Update

If an existing item has become unpurchasable:

do not silently repair it.

Follow the canonical Cart error mapping.

The customer may still remove it through CART-004.

---

# 44. Stock Check on Update

For an otherwise purchasable IN_STOCK item:

ensure current informational availability can satisfy the requested quantity.

If not:

return:

```text
422 INSUFFICIENT_STOCK
```

according to the frozen Cart contract.

Do not reserve stock.

---

# 45. Update to Same Quantity

Review frozen semantics.

Preferred standard behavior:

```text
PATCH quantity = current quantity
→ successful idempotent no-op
→ return current Cart
```

Do not create an error solely because the value is unchanged unless contract says otherwise.

---

# 46. Cart Timestamp

Successful quantity mutation must update:

```text
CartItem.updated_at
Cart.updated_at
```

according to the Phase 6.1/6.2 timestamp decision.

---

# 47. Failed Update Timestamp

Validation failure must not touch:

```text
CartItem.updated_at
Cart.updated_at
```

---

# 48. CART-004 — Remove Item

Implement:

```http
DELETE /api/v1/me/cart/items/{item}
```

---

# 49. Remove Request Body

Accept no request body.

Do not require:

```text
quantity
product_id
cart_id
```

---

# 50. Removal Ownership

Resolve item strictly through current holder's ACTIVE Cart.

Cross-Cart or unknown item:

```text
404 CART_ITEM_NOT_FOUND
```

with existence masking.

---

# 51. Removal Is Allowed for Stale Items

CART-004 must allow removing an item even if its:

```text
Product is inactive
Product is unpublished
Product is soft-deleted
Variant is inactive
stock = 0
Product type changed / is invalid for purchase
```

The customer must always be able to clean stale Cart lines.

---

# 52. Removal Requires No Stock Validation

Do not block deletion because:

```text
stock changed
Product inactive
Variant inactive
```

Removal only requires:

```text
valid holder
owned Cart
owned CartItem
```

---

# 53. Remove Does Not Touch Inventory

Deleting CartItem must not:

```text
release reservation
increase stock
decrease stock
```

because Cart never reserved inventory.

---

# 54. Cart Record Remains

Removing the last line:

```text
Cart remains ACTIVE
items = []
items_count = 0
subtotal = 0
```

Do not delete the Cart record.

---

# 55. Cart Status Remains ACTIVE

Ordinary add/update/remove does not set:

```text
INACTIVE
```

---

# 56. Removal Response

Follow frozen CART-004 wire contract exactly.

If it returns:

```text
updated Cart
```

return that.

If the frozen OpenAPI requires:

```text
204 No Content
```

use that.

Do not invent response semantics.

---

# 57. Cart Projection After Mutations

For any endpoint returning Cart:

reuse Phase 6.2:

```text
CartResource
CartItemResource
current pricing
availability
stock_indicator
is_purchasable
opaque IDs
deterministic item ordering
```

---

# 58. Server-Derived Pricing

After add/update/remove:

recalculate response values from current server state.

Never trust request price.

---

# 59. Line Total

For every **priceable** returned line (one whose saved active Variant gives a
resolvable current price):

```text
line_total.amount
=
unit_price.amount * quantity
```

using integer minor units.

For an **unpriceable** line (missing or inactive Variant), expose:

```text
unit_price = null
line_total = null
is_purchasable = false
```

Do not dereference a missing price and do not fabricate a price (no implicit
`amount = 0`). This matches the frozen OpenAPI `CartItem` nullability.

---

# 60. Subtotal

After every mutation:

```text
subtotal
=
SUM(current line totals of priceable lines)
```

Unpriceable (`null`) line totals are **excluded** from the sum, not treated as
zero. Subtotal remains a server-derived, non-persisted integer-minor-unit value.

Do not persist subtotal.

---

# 61. Price Changes

If another Product's Variant price changed while the Cart was open:

the next mutation response should reflect the current live price for that line too.

The Cart response is a current projection.

---

# 62. Items Count

Still:

```text
number of distinct CartItem rows
```

not sum of quantities.

---

# 63. Current Availability

Response must use Phase 5.7:

```text
availability
stock_indicator
```

not duplicated Cart calculations.

---

# 64. `is_purchasable`

Continue the Phase 6.1 definition.

Do not redefine it separately in add/update/remove code.

Use a shared Cart projection/domain component.

---

# 65. Stale Items Remain

Adding/updating/removing one line must not cause unrelated stale lines to disappear.

Cart mutation should affect only the intended line except recalculated derived response state.

---

# 66. No Silent Cleanup

Do not perform background cleanup such as:

```text
delete all unavailable items
delete unpublished items
remove MADE_TO_ORDER lines
```

during unrelated mutations.

---

# 67. Holder-Credential Transport

Guest mutation requests use the exact Phase 6.2 transport behavior.

Browser:

```text
guest_cart_id cookie
```

Flutter/non-browser:

```text
X-Guest-Cart-Id
```

---

# 68. Do Not Re-Issue Existing Guest Credential

Normal add/update/remove using an existing valid guest Cart should not rotate its guest credential.

---

# 69. Authenticated + Guest Credential

Authenticated principal still wins.

Ordinary item mutation targets authenticated Cart.

Do not mutate supplied guest Cart.

---

# 70. No Auto-Merge

Do not merge guest Cart as side effect of:

```text
POST item
PATCH item
DELETE item
```

---

# 71. CART-005 Merge Remains Separate

Unless current AGENTS.md explicitly says otherwise, do not implement:

```http
POST /api/v1/me/cart/merge
```

inside this combined add/update/remove phase.

Merge has additional concerns:

```text
authenticated + guest dual authority
source retirement
duplicate consolidation
quantity conflicts
idempotency
credential retirement
```

and deserves its own bounded implementation.

---

# 72. No Idempotency-Key for Normal Item Mutations Unless Frozen

Do not automatically impose Checkout-style durable Idempotency-Key behavior on CART-002/003/004 unless the frozen contract explicitly requires it.

The database mutation itself still needs concurrency correctness.

---

# 73. Retry Semantics for Add

Be careful:

```text
POST add
```

is not naturally idempotent.

A network retry may add quantity again unless the contract defines idempotency.

Do not silently claim retry-safe semantics that are not contracted.

---

# 74. Duplicate Add vs Network Retry

The frozen "duplicate item merges quantities" rule describes:

```text
adding the same identity
```

not necessarily HTTP request deduplication.

Do not confuse:

```text
line merging
```

with:

```text
idempotent request replay
```

---

# 75. Rate Limiting

Apply the existing Cart mutation limiter to:

```text
CART-002
CART-003
CART-004
```

Use current configured rate-limit category.

---

# 76. Retry-After

429 responses must include:

```text
Retry-After
```

according to global conventions.

---

# 77. Cache Control

All mutation responses remain:

```text
private
no-cache
no-store
must-revalidate
```

---

# 78. No Public Cache

Applies to guest and authenticated Carts.

---

# 79. Error Envelope

Use:

```text
errors[]
meta.request_id
```

for all failures.

---

# 80. Add Errors

Cover at least:

```text
missing product_id
invalid product_id
missing/invalid variant_id
variant wrong Product
inactive Variant
MADE_TO_ORDER
inactive/unpublished Product
invalid quantity
insufficient current stock
unknown input field
invalid holder credential
rate limit
```

using already-frozen codes.

---

# 81. Update Errors

Cover:

```text
item not found / not owned
quantity invalid
quantity > 100
Product/Variant currently invalid
insufficient stock
invalid holder
unknown fields
rate limit
```

---

# 82. Remove Errors

Cover:

```text
item not found / not owned
invalid holder
rate limit
```

Do not add Product/stock validation errors to removal.

---

# 83. No Data Leakage

Errors must never expose:

```text
another user's Cart ID
another user's item
raw DB IDs
guest digest
raw guest token
SQL
table names
ProductStock quantities beyond approved safe details
```

---

# 84. Safe Stock Error Details

If the frozen `INSUFFICIENT_STOCK` contract permits:

```text
details.available_quantity
```

for Cart mutations, use the approved safe representation.

Otherwise do not invent operational quantity leakage.

Review endpoint-specific contract.

---

# 85. Add Service

Use a focused action/service such as:

```text
AddCartItem
```

Responsibilities:

```text
validate resolved Product/Variant domain state
calculate resulting quantity
informational stock check
transactional create-or-increment
touch Cart
return result
```

---

# 86. Update Service

Use:

```text
UpdateCartItemQuantity
```

or equivalent.

Keep ownership resolution outside or clearly bounded.

---

# 87. Remove Service

Use:

```text
RemoveCartItem
```

or equivalent.

No stock/catalog validation beyond what is necessary for holder ownership.

---

# 88. Shared Cart Item Admission Rule

Do not duplicate Product/Variant admission logic in:

```text
AddCartItem
UpdateCartItemQuantity
```

Use one focused reusable domain validator/resolver.

---

# 89. Keep Controller Thin

Conceptually:

```text
FormRequest
→ holder/current Cart
→ action/service
→ CartResource / response
```

No large transaction/domain logic in controller.

---

# 90. Transaction Boundary — Add

The add operation should be atomic around:

```text
existing-line resolution
resulting quantity calculation
CartItem insert/update
Cart touch
```

---

# 91. Transaction Boundary — Update

Atomic:

```text
owned-item lock/read
validation against current state
quantity update
Cart touch
```

---

# 92. Transaction Boundary — Remove

Atomic:

```text
owned-item resolution
delete
Cart touch
```

No need for inventory transaction/lock.

---

# 93. CartItem Locking

For concurrent mutation of the same existing CartItem:

use:

```text
lockForUpdate()
```

or equivalent appropriate row serialization.

This prevents lost quantity updates.

---

# 94. Cart Locking

Avoid unnecessarily locking the entire Cart for every operation if locking the affected CartItem plus proper uniqueness is sufficient.

But ensure:

```text
Cart.updated_at
```

touch behavior remains consistent.

---

# 95. New-Line Race

Two concurrent adds for the same absent identity may both attempt insert.

Use the DB unique constraints as final defense.

Recover safely:

```text
one inserts
other resolves winner
then applies its quantity merge transactionally
```

Do not surface raw duplicate-key exceptions.

---

# 96. Different-Line Concurrency

Adding:

```text
Variant A
Variant B
```

to the same Cart concurrently should not unnecessarily block each other beyond what Cart timestamp updates require.

Prefer row-level contention over global serialization.

---

# 97. Update vs Remove Race

Same item concurrently receives:

```text
PATCH quantity
DELETE
```

The system must produce one valid serialized outcome.

Never resurrect a removed line unexpectedly.

---

# 98. Add vs Remove Race

If the same identity is removed while another request adds it:

result may depend on transaction ordering, but final state must:

```text
obey uniqueness
obey quantity bounds
remain owned by same Cart
```

No duplicate rows.

---

# 99. Cart Mutation Does Not Need ProductStock Locks

Repeat:

```text
no ProductStock lock
no reservation
```

for ordinary Cart operations.

---

# 100. Group E Concurrency Remains Checkout Authority

Do not weaken or duplicate:

```text
InventoryAllocator / reservation primitive
```

from Phase 5.10.

Group F only reads availability.

---

# 101. Add Test — New Line

Given empty Cart:

```text
POST Product A / Variant A / qty 2
```

assert:

```text
one CartItem
quantity 2
correct identity
```

---

# 102. Add Test — Duplicate Line

Existing qty:

```text
2
```

add:

```text
3
```

result:

```text
5
```

with one CartItem row.

---

# 103. Add Test — Quantity Ceiling

Test behavior at:

```text
99 + 1
99 + 2
100 + 1
```

according to frozen clamping semantics.

---

# 104. Add Test — Same Product Different Variant

Add:

```text
red
blue
```

Both remain separate lines.

---

# 105. Add Test — Wrong Parent Variant

Must fail without Cart mutation.

---

# 106. Add Test — MADE_TO_ORDER

Must return:

```text
422 PRODUCT_NOT_PURCHASABLE
```

and create no CartItem.

---

# 107. Add Test — Inactive Product

Must fail.

---

# 108. Add Test — Unpublished Product

Must fail.

---

# 109. Add Test — Inactive Category

Must fail according to Phase 6.1 purchasability definition.

---

# 110. Add Test — Inactive Variant

Must fail.

---

# 111. Add Test — Insufficient Stock

Example:

```text
available = 3
requested resulting quantity = 4
```

must fail without reservation or Cart mutation.

---

# 112. Add Test — Fully Reserved Inventory

Physical:

```text
10
```

Reserved:

```text
10
```

Available:

```text
0
```

add should fail informational stock admission.

---

# 113. Add Test — Multi-Location

Available stock aggregation must match Group E.

Do not inspect first stock row only.

---

# 114. Add Test — No Reservation

Compare ProductStock before/after successful add.

Must remain identical.

---

# 115. Add Test — Current Price

Response must use current Variant price.

Client cannot override it.

---

# 116. Add Test — Unknown Fields

Send:

```text
price
user_id
cart_id
reserved_quantity
```

Expect strict validation failure.

---

# 117. Add Test — Authenticated

Works against own active Cart.

---

# 118. Add Test — Guest Browser

Works with cookie credential.

---

# 119. Add Test — Guest Flutter

Works with header credential.

---

# 120. Add Test — Invalid Guest

401.

No Cart mutation.

---

# 121. Add Test — Concurrent Duplicate Addition

Use concurrent requests against same identity.

Assert:

```text
one CartItem
correct final quantity
no duplicate-key leak
quantity <= 100
```

Run real MariaDB if SQLite cannot prove the target race.

---

# 122. Update Test — Quantity Increase

Update from:

```text
2 → 5
```

assert exact result.

---

# 123. Update Test — Quantity Decrease

```text
5 → 2
```

valid.

---

# 124. Update Test — Quantity One

Boundary:

```text
1
```

valid.

---

# 125. Update Test — Quantity 100

Boundary:

```text
100
```

valid if stock permits current informational validation.

---

# 126. Update Test — Zero

Rejected.

No deletion.

---

# 127. Update Test — Over 100

Rejected.

---

# 128. Update Test — Strict Type

Reject numeric string/float/null.

---

# 129. Update Test — Stock Insufficient

Requested quantity > current available:

reject.

Original quantity remains unchanged.

---

# 130. Update Test — No Reservation

ProductStock unchanged.

---

# 131. Update Test — Cross-Holder Item

Customer/guest A attempts to mutate B's item.

Result:

```text
404
```

same as nonexistent item.

---

# 132. Update Test — Same Quantity

Verify finalized no-op behavior.

---

# 133. Update Test — Cart Timestamp

Successful mutation updates Cart.updated_at.

---

# 134. Update Test — Failed Mutation Timestamp

Failure leaves timestamps unchanged.

---

# 135. Update Test — Concurrent Updates

Two same-line updates must produce a valid "last successfully serialized mutation wins" result.

No malformed quantity or duplicate line.

---

# 136. Remove Test — Existing Item

Delete owned line successfully.

---

# 137. Remove Test — Last Item

Cart remains ACTIVE and becomes empty.

---

# 138. Remove Test — Stale Product

Can remove successfully.

---

# 139. Remove Test — Inactive Variant

Can remove successfully.

---

# 140. Remove Test — Out of Stock

Can remove successfully.

---

# 141. Remove Test — MADE_TO_ORDER Stale Line

If legacy/corrupt historical Cart contains such a line:

owner can remove it.

---

# 142. Remove Test — Cross-Holder

Masked 404.

---

# 143. Remove Test — Unknown Item

Same masked 404.

---

# 144. Remove Test — No Inventory Effect

ProductStock remains unchanged.

---

# 145. Remove Test — Cart Timestamp

Successful removal updates parent Cart.updated_at.

---

# 146. Projection Regression

After every mutation verify:

```text
items_count
subtotal
line totals
current prices
availability
stock_indicator
is_purchasable
```

are coherent.

---

# 147. Stale Unrelated Line Regression

Cart has:

```text
valid line A
stale line B
```

mutate A.

B must remain present.

---

# 148. No Raw Inventory Leakage

Mutation responses must not expose:

```text
quantity
reserved_quantity
available_quantity
warehouse_location
```

unless already part of an explicitly safe Cart error detail.

---

# 149. No Ownership Leakage

Never expose:

```text
user_id
guest_token_digest
active_user_guard
cart_id on item
```

outside approved opaque representation.

---

# 150. Opaque IDs

Requests use:

```text
prod_...
var_...
item_...
```

where frozen.

Responses keep:

```text
cart_...
item_...
```

stable.

---

# 151. Invalid Opaque ID

Malformed IDs should fail through canonical validation/not-found behavior.

Do not accidentally interpret raw integer `1` as a valid opaque item ID.

---

# 152. Guest Credential Security

Mutation endpoints must preserve Phase 6.2:

```text
browser cookie only
Flutter header only
```

credential transport.

Do not emit the raw credential in JSON.

---

# 153. Cookie Security

Browser Cart mutation response remains compatible with:

```text
HttpOnly
Secure
SameSite=None
```

No JavaScript-readable guest credential.

---

# 154. No Guest Credential Rotation

Ordinary item mutations do not rotate credential.

---

# 155. Authenticated Mutation Does Not Touch Guest Cart

Even if request carries a guest credential:

authenticated Cart remains target.

No merge/retirement.

---

# 156. Staff/Admin Self-Commerce

STAFF/ADMIN may mutate only their own personal Cart using the same self-context rule established in Phase 6.2.

Role must never permit mutation of somebody else's Cart.

---

# 157. No Operational Cart API

Do not add:

```text
/admin/carts
/carts/{user}
/staff/carts
```

---

# 158. No Cart Clearing Endpoint

Do not implement:

```text
DELETE /me/cart
POST /me/cart/clear
```

---

# 159. No Bulk Mutation

Do not implement:

```text
PATCH /me/cart
{
  "items": [...]
}
```

---

# 160. No Save-for-Later

Out of scope.

---

# 161. No Wishlist

Out of scope.

---

# 162. No Discounts/Coupons

Do not introduce discount fields or calculations.

---

# 163. No Delivery Fee

Cart subtotal remains Product merchandise only.

---

# 164. No Checkout

Do not:

```text
reserve inventory
create Order
create OrderItem snapshot
calculate delivery fee
```

---

# 165. No Order Mutation

Group F Cart mutation does not touch Orders.

---

# 166. No Audit Trail Required for Ordinary Cart Mutation

Do not create privileged audit records for normal customer Cart add/update/remove unless current general auditing contract explicitly requires them.

These are ordinary holder actions.

---

# 167. Logging

Normal application logs may record:

```text
request_id
operation
opaque resource IDs where safe
```

but not guest credentials.

---

# 168. Schema Changes

Expected:

```text
NONE
```

Existing Cart constraints already support these operations.

---

# 169. Dependencies

Expected:

```text
NONE
```

---

# 170. Frontend

Must remain:

```text
NONE
```

---

# 171. Documentation

Update OpenAPI/docs only if runtime implementation reveals genuine drift.

Do not redesign the frozen Cart contract during implementation.

---

# 172. Recommended Test Organization

Prefer focused suites such as:

```text
CartAddItemApiTest
CartUpdateItemApiTest
CartRemoveItemApiTest
CartMutationConcurrencyMysqlTest
```

or equivalent repository naming.

Do not create one enormous CartEverything test.

---

# 173. SQLite Tests

SQLite should prove:

```text
validation
ownership
IDOR masking
Product/Variant admission
quantity semantics
pricing
availability reads
no reservation
response shapes
stale item preservation
```

---

# 174. MariaDB Targeted Tests

Use real MariaDB where needed to prove:

```text
concurrent duplicate add
unique-line race recovery
lost quantity update prevention
update/remove serialization
```

Do not require full-suite MariaDB merely for these cases.

---

# 175. Concurrent Add Gate

At minimum test:

```text
existing line qty 1

two simultaneous add +1
```

Expected:

```text
one line
final qty 3
```

unless request ordering/frozen contract produces another explicitly approved result.

No lost increment.

---

# 176. Concurrent First Add Gate

Two simultaneous adds for the same absent Product/Variant must result in:

```text
one CartItem row
combined quantity
```

subject to max-quantity rule.

---

# 177. Concurrent Overflow

Example:

```text
current 99
two concurrent +1
```

final must never exceed 100.

Apply frozen clamping semantics.

---

# 178. Full Regression

After implementation run:

```bash
php artisan test
vendor/bin/pint --test
vendor/bin/phpstan analyse
composer audit
git diff --check
php artisan route:list
```

---

# 179. Required Routes

Verify active:

```text
GET    /api/v1/me/cart
POST   /api/v1/me/cart/items
PATCH  /api/v1/me/cart/items/{item}
DELETE /api/v1/me/cart/items/{item}
```

---

# 180. CART-005 Must Not Accidentally Activate

Unless explicitly included by current AGENTS.md:

do not activate:

```text
POST /api/v1/me/cart/merge
```

as part of this combined phase.

---

# 181. Code Quality

Maintain:

```text
cognitive complexity <= 15
<= 3 returns where practical
thin controllers
small actions
shared admission resolver
shared Cart projection
no duplicated price/availability logic
```

---

# 182. Completion Report

Return:

## Combined Phase 6.3–6.5 status

```text
PASS
```

or:

```text
BLOCKED
```

## CART-002 Add

Report:

```text
Product admission
Variant ownership
quantity rules
stock informational validation
duplicate merge
concurrency
```

## CART-003 Update

Report:

```text
ownership masking
quantity-only mutation
stock revalidation
timestamp behavior
concurrency
```

## CART-004 Remove

Report:

```text
ownership masking
stale-item removal
empty-cart behavior
no stock effect
```

## Holder behavior

Report authenticated/guest behavior and credential transport reuse.

## Pricing

Confirm all money remains server-derived and unpersisted.

## Inventory

Must explicitly state:

```text
reserved_quantity mutations: NONE
ProductStock locks: NONE
inventory reservations: NONE
```

## Concurrency

Report:

```text
duplicate first-add
repeat-add race
quantity lost-update protection
overflow boundary
```

and identify MariaDB-specific verification.

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

Must state:

```text
NONE
```

## Tests

Report focused and canonical test results.

## Quality

Report:

```text
Pint
PHPStan
Composer audit
git diff --check
```

## Phase 6.6 readiness

Return:

```text
READY
```

or:

```text
BLOCKED
```

---

# 183. Definition of Done

This combined phase is complete when:

* CART-002 is active;
* CART-003 is active;
* CART-004 is active;
* all use Phase 6.2 holder resolution;
* authenticated users mutate only their own Cart;
* guests mutate only their credential-bound Cart;
* Staff/Admin mutate only personal self-commerce Cart;
* invalid Clerk authentication never downgrades to guest;
* Cart ID is never client-selected;
* add accepts only Product/Variant/quantity;
* update accepts quantity only;
* remove accepts no mutation body;
* unknown fields are rejected;
* only IN_STOCK Products can be newly added;
* MADE_TO_ORDER returns PRODUCT_NOT_PURCHASABLE;
* Product active/public/category-active rules are enforced;
* Variant belongs to Product;
* Variant is active;
* quantity stays within 1..100;
* duplicate additions merge into one line;
* duplicate adds obey frozen maximum-quantity semantics;
* same Product/different Variants remain separate lines;
* informational stock checks use current Group E availability;
* no Cart operation reserves inventory;
* no Cart operation mutates ProductStock;
* ProductStock rows are not locked by ordinary Cart operations;
* repeat-add is concurrency-safe;
* first-add race cannot create duplicate CartItems;
* same-line updates do not lose data;
* update quantity 0 is rejected;
* removal uses DELETE;
* removal works even for stale/unavailable items;
* cross-Cart update/delete uses masked 404;
* removing last item leaves an empty ACTIVE Cart;
* mutations touch parent Cart.updated_at;
* failed mutations do not touch Cart timestamps;
* Cart money remains server-derived;
* Cart money is not persisted;
* Cart response continues using current prices;
* stale unrelated Cart lines remain present;
* opaque IDs remain enforced;
* private cache policy remains enforced;
* guest credential transport remains secure;
* no implicit guest merge occurs;
* no Checkout logic is introduced;
* no schema changes occur;
* no frontend code changes;
* focused tests pass;
* targeted MariaDB concurrency tests pass where required;
* full canonical suite remains green;
* no new PHPStan errors;
* Pint passes;
* Composer audit has no blocker.

---

# 184. Out of Scope

Do not implement:

```text
CART-005 guest→authenticated merge
merge idempotency
full stale Cart validation orchestration
Phase 6.6 validation consolidation
Phase 6.7 stock revalidation hardening
checkout
reservation
Order creation
payment
frontend Cart UI
```

---

# 185. STOP Condition

STOP when the holder can safely perform the complete ordinary Cart item lifecycle:

```text
add
update quantity
remove
```

while preserving:

```text
one CartItem per Product/Variant identity
quantity 1..100
holder ownership
stale-line visibility
live server pricing
live informational availability
zero inventory reservation
```

Do not continue automatically to Phase 6.6.

DO NOT COMMIT OR PUSH.

The project owner handles all Git operations.
