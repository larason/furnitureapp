# Phase 6.1 — Cart Model Review

## Purpose

Begin **Phase Group F — Cart** by reviewing and reconciling the existing Cart persistence/domain model against the frozen V1 Cart API contract and the completed Group E catalog/inventory behavior.

This is primarily a:

```text
model review
contract reconciliation
invariant review
ownership/security review
dependency review
implementation-gap inventory
```

phase.

Do not implement the Cart API workflows yet unless a very small correction is required to make the existing model conform to already-approved V1 rules.

Phase 6.2 will begin actual create/get cart behavior.

---

# 1. Group F Roadmap

Per `AGENTS.md`:

```text
Phase 6.1 — Cart model review
Phase 6.2 — Create/get cart
Phase 6.3 — Add item
Phase 6.4 — Update quantity
Phase 6.5 — Remove item
Phase 6.6 — Cart validation
Phase 6.7 — Stock revalidation
Phase 6.8 — Cart tests
```

Do not implement later phases during 6.1.

---

# 2. Primary Goal

Confirm that the existing Group C Cart model can support all approved V1 Cart behaviors without unnecessary schema redesign.

Answer:

```text
Can the current carts/cart_items model safely support:
- authenticated carts
- guest carts
- secure guest ownership
- active-cart lookup
- add/update/remove
- duplicate-line merging
- guest→customer merge
- live pricing
- live availability
- stale cart items
- checkout handoff
```

If yes:

document the mapping and proceed without schema changes.

If no:

identify the exact mismatch and make only the smallest necessary correction.

---

# 3. Read Authoritative Files First

Inspect:

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

Also inspect:

```text
Phase 3.8 Cart schema implementation
Phase 4.x authentication/authorization
Phase 5.2 Product pricing
Phase 5.4 Variant behavior
Phase 5.7 availability rules
Phase 5.10 concurrency/reservation boundary
Phase 5.11 Group E closure results
```

Do not rely only on historical phase instructions.

The current repository implementation and accepted ADRs are authoritative.

---

# 4. Existing Cart Persistence Model

Verify the actual schema still matches the Group C decision.

Expected:

```text
carts
cart_items
```

Do not redesign unless a real V1 mismatch is found.

---

# 5. Cart Ownership Model

A Cart has exactly one owner context:

```text
Authenticated:
user_id != null
guest_token_digest == null
```

or:

```text
Guest:
user_id == null
guest_token_digest != null
```

Never both.

Never neither.

This XOR invariant must remain.

---

# 6. Ownership Is Server-Controlled

Clients must never be allowed to set:

```text
user_id
guest_token_digest
```

directly.

Authenticated ownership comes from:

```text
Clerk-authenticated principal
→ Laravel local user
```

Guest ownership comes from:

```text
validated guest credential
```

---

# 7. Guest Cart Credential Model

Review:

```text
GuestCartCredential
config/cart.php
GUEST_CART_TOKEN_KEY
```

The raw guest credential must:

```text
be generated server-side
be cryptographically strong
never be stored in the database
never be logged
never appear in errors
never appear in analytics
```

Only the digest is persisted.

---

# 8. Guest Digest

Current persistence uses:

```text
HMAC-SHA-256(server secret, raw token)
```

stored as:

```text
guest_token_digest
```

Keep this design.

Do not switch to:

```text
raw token storage
bcrypt
Argon2
plain SHA256 without server key
```

without a security reason.

---

# 9. Guest Token Contract Reconciliation

The API contract describes:

```text
X-Guest-Cart-Id
guest_cart_id cookie
```

as the client-side guest bearer credential.

Review whether the current `GuestCartCredential` terminology and generated value align with this wire contract.

Do not expose the database digest as `X-Guest-Cart-Id`.

---

# 10. Guest Token Format

Current API security decision expects a high-entropy opaque credential, represented as UUIDv4 in the contract.

Current Group C ADR mentions an approximately 192+ bit random raw token.

Review actual implementation and frozen OpenAPI.

If there is a discrepancy:

choose the current frozen V1 wire contract and reconcile minimally.

Do not weaken entropy.

Do not change guest ownership semantics.

---

# 11. Guest Credential Is a Bearer Credential

Possession of a valid guest token authorizes access only to:

```text
that bound guest Cart
```

It must not authorize:

```text
/me profile
orders
checkout
authenticated merge by itself
another guest Cart
```

---

# 12. One Active Cart per Customer

Existing invariant:

```text
one ACTIVE Cart per authenticated user
```

must remain.

A customer may still have:

```text
multiple historical INACTIVE carts
```

---

# 13. Active Cart Guard

Review:

```text
active_user_guard
```

and its unique constraint.

Confirm it works on:

```text
SQLite
MariaDB/MySQL
```

according to existing schema tests.

Do not replace it with:

```text
UNIQUE(user_id)
```

because that would prevent historical carts.

---

# 14. Cart Status

V1 CLOSED status remains:

```text
ACTIVE
INACTIVE
```

Only.

Do not add:

```text
ABANDONED
EXPIRED
MERGED
CHECKED_OUT
ORDERED
```

during Group F unless the frozen contract is formally changed.

---

# 15. Meaning of ACTIVE

`ACTIVE` means:

```text
the current mutable cart for its holder
```

It is not an Order status.

---

# 16. Meaning of INACTIVE

`INACTIVE` means:

```text
cart is no longer the holder's current active cart
```

Do not infer another lifecycle state from it.

---

# 17. No Automatic Expiry

The current model has no approved guest/customer Cart TTL.

Do not invent:

```text
30-day expiry
90-day cleanup
abandoned-cart worker
```

in Phase 6.1.

---

# 18. Cart Item Persistence

Expected core stored fields:

```text
cart_id
product_id
variant_id nullable
quantity
timestamps
```

Review actual migration/model.

---

# 19. No Persisted Prices

Cart persistence must not contain authoritative:

```text
unit_price
line_total
subtotal
total
```

Those values are derived from current catalog state for display.

Do not add price snapshot fields.

---

# 20. Why No Cart Price Snapshot

Cart is:

```text
mutable purchase intent
```

not historical purchase evidence.

Checkout recalculates authoritative prices.

Historical price snapshot belongs to:

```text
OrderItem
```

after checkout.

---

# 21. No Persisted Availability

Do not add:

```text
availability
stock_indicator
is_purchasable
```

columns to `cart_items`.

These must be calculated from current Product/Variant/Inventory state.

---

# 22. No Inventory Columns

Do not add:

```text
stock_id
quantity_available
reserved_quantity
available_quantity
warehouse_location
```

to Cart or CartItem.

---

# 23. No Reservation Fields

Do not add:

```text
reservation_id
reserved_at
reservation_expires_at
```

to Cart.

Group E established:

```text
Cart does not reserve stock.
Checkout reserves stock.
```

This boundary is mandatory.

---

# 24. Group E Dependency

Cart must consume the authoritative Group E rules for:

```text
Product visibility
Product product_type
Variant validity
current price
availability
stock_indicator
```

Do not reimplement them independently.

---

# 25. Cart Admission vs Stale Cart State

These are different concepts.

At **add-item time**:

the requested item must satisfy cart admission rules.

After it is already in the Cart:

a Product/Variant may become unavailable later.

Do not silently remove stale items.

---

# 26. Cart Admission — Product

For a new Cart item, Product must be:

```text
exists
active
published
product_type = IN_STOCK
```

according to the frozen Cart contract.

---

# 27. MADE_TO_ORDER

A:

```text
product_type = MADE_TO_ORDER
```

Product must never be admitted to normal Cart.

Return:

```text
422 PRODUCT_NOT_PURCHASABLE
```

Made-to-order goes through the Request Furniture workflow.

---

# 28. Variant Requirement Review

The frozen Cart contract says:

```text
variant_id required if Product has variants
variant_id null/omitted if Product has no variants
```

Review whether the current Product domain permits Products with no Variant rows.

This is especially important because the current catalog pricing architecture treats ProductVariant as the canonical pricing/sellable boundary.

---

# 29. Potential Variant Contract Tension

Group C/Group E architecture establishes Variant as:

```text
SKU
pricing
sellable configuration
```

while the Cart contract retains:

```text
variant_id nullable when product has no variants
```

Do not silently resolve this by changing the contract.

Review actual Product data/model and determine whether V1 genuinely supports:

```text
Product without any ProductVariant
```

If every purchasable Product necessarily has a Variant, record that fact and identify the nullable Cart field as compatibility/general-contract allowance rather than removing it casually.

---

# 30. Variant Ownership

When `variant_id` is present:

```text
variant.product_id
must equal
product_id
```

Keep existing:

```text
CartItem::assertVariantBelongsToProduct()
```

or equivalent.

---

# 31. Variant Active State

New Cart admission requires:

```text
Variant.is_active = true
```

Inactive Variant:

```text
422 INVALID_PRODUCT_VARIANT
```

according to the frozen error contract.

---

# 32. Duplicate Cart Item Identity

A Cart item identity is:

```text
product_id + variant_id
```

within one Cart.

Do not redefine uniqueness as SKU only.

---

# 33. Null-Variant Duplicate Handling

The current schema uses a special guard because SQL UNIQUE constraints permit multiple NULLs.

Review:

```text
null_variant_guard / identity guard
```

and preserve the existing defense.

---

# 34. Same Product, Different Variants

These should remain separate Cart lines.

Example:

```text
Chair / red
Chair / blue
```

must coexist as separate lines.

---

# 35. Repeat Add Behavior

The frozen Cart contract says repeated addition of the same line should:

```text
merge by increasing quantity
```

not create duplicate rows.

Phase 6.3 will implement this.

Phase 6.1 should verify the schema supports it safely.

---

# 36. Quantity Bounds

V1 Cart quantity remains:

```text
integer
1..100
```

Use:

```text
CartItem::MAX_QUANTITY
```

or the current centralized constant.

Do not duplicate magic `100`.

---

# 37. Quantity Must Remain Integer

Do not allow:

```text
1.5
"2"
null
negative
0
```

under strict request validation.

Actual API enforcement comes in later phases.

---

# 38. Repeated Add Quantity Overflow

Review the future invariant:

```text
existing quantity + requested quantity <= 100
```

Example:

```text
existing 80
add 30
```

must eventually fail rather than store 110.

Phase 6.3 owns implementation.

Phase 6.1 should record the requirement.

---

# 39. Items Count Semantics

Frozen Cart representation defines:

```text
items_count
=
number of distinct Cart item lines
```

not total quantity.

Example:

```text
Sofa x3
Lamp x2
```

means:

```text
items_count = 2
```

not 5.

Record this explicitly for Phase 6.2+ serialization.

---

# 40. Cart Subtotal

Cart response includes informational:

```text
subtotal
```

calculated from current catalog prices.

It must not be persisted as Cart authority.

---

# 41. Unit Price

Each CartItem exposes current:

```text
unit_price
```

resolved from the current Product/Variant pricing rule.

Do not use stale client price.

---

# 42. Line Total

Calculate:

```text
unit_price.amount * quantity
```

using integer minor units.

No floating point.

---

# 43. Subtotal

Calculate:

```text
SUM(line_total)
```

using current server-side prices.

Cart subtotal is informational.

Checkout recalculates again.

---

# 44. Price Changes While in Cart

If Variant price changes after an item was added:

next Cart read should reflect the current price.

Do not preserve the original add-time price.

---

# 45. Checkout Price Authority

Even if Cart read displays current price:

Checkout must recalculate it again.

Do not treat Cart representation as transaction authority.

---

# 46. Stale Product Handling

If an existing Cart item Product becomes:

```text
inactive
unpublished
```

do not delete the line.

Return it as stale/unpurchasable according to frozen Cart representation.

---

# 47. Stale Variant Handling

If an existing Cart item's Variant becomes inactive:

do not silently remove it.

Preserve line identity if the FK still exists.

Expose:

```text
is_purchasable = false
```

with the appropriate availability state.

---

# 48. Out-of-Stock Handling

If inventory depletes after an item was added:

the Cart line remains.

Do not remove it.

---

# 49. Cart Availability

Each CartItem exposes:

```text
availability
stock_indicator
```

from current Group E catalog/inventory derivation.

Do not persist them.

---

# 50. `is_purchasable`

This Cart-specific derived boolean answers:

```text
Can this Cart line currently proceed toward checkout?
```

It is not the same field as public Product `availability`.

---

# 51. Purchasability Inputs

Review the frozen definition against completed Group E behavior.

Conceptually `is_purchasable` should require:

```text
Product exists
Product active
Product published
Product type IN_STOCK
Variant valid
Variant active
current availability sufficient for intended Cart quantity
```

But distinguish:

```text
Cart informational validation
```

from:

```text
Checkout authoritative locked validation
```

---

# 52. Cart Quantity vs Available Stock

Frozen Cart errors include:

```text
INSUFFICIENT_STOCK
```

for add/update when current informational available stock is below the requested quantity.

Review whether:

```text
add/update should reject quantity > current availability
```

while existing stale items can later become unavailable.

This distinction should be documented clearly.

---

# 53. Cart Does Not Reserve

Even after a successful add/update:

```text
ProductStock.reserved_quantity
```

must not change.

This must remain one of Group F's strongest invariants.

---

# 54. Concurrency Boundary

Group E proved reservation concurrency under real MariaDB.

Do not introduce Cart-level row locks on ProductStock for normal add/update merely to "protect stock".

Cart availability can change after mutation.

Checkout owns final locked stock validation.

---

# 55. Cart Mutation Concurrency

The frozen contract describes Cart mutation as:

```text
Medium/Safe
duplicate additions merge
last valid mutation wins
```

Phase 6.1 should review how to implement this later without:

```text
duplicate lines
quantity lost updates
quantity > 100
```

---

# 56. Same-Line Concurrent Adds

Two simultaneous repeated adds could otherwise race:

```text
existing quantity = 1

A +1
B +1
```

Desired result should eventually be consistent with the chosen mutation semantics.

Review whether schema uniqueness + transaction/retry will be sufficient in Phase 6.3.

Do not implement it yet.

---

# 57. Guest Cart vs Authenticated Cart

Both Cart types use the same:

```text
Cart
CartItem
Cart representation
domain validation
pricing
availability
```

Do not build separate guest Cart models.

Only ownership/credential resolution differs.

---

# 58. `/me/cart` Naming

The contract uses:

```text
/api/v1/me/cart
```

for the holder's current Cart.

For guests, this remains conceptually holder-scoped through the guest credential.

Do not create:

```text
/api/v1/guest/cart
/api/v1/carts/{id}
```

as duplicate APIs.

---

# 59. Cart ID Is Not Authority

Knowledge of:

```text
cart_...
```

must never authorize access.

Ownership comes from:

```text
authenticated principal
```

or:

```text
validated guest credential
```

---

# 60. Item ID Is Not Authority

Knowledge of:

```text
item_...
```

must not allow cross-Cart mutation.

Future update/remove must verify:

```text
item.cart_id == caller active cart.id
```

---

# 61. Cross-Customer Masking

Attempt to access another holder's Cart item must return:

```text
404 CART_ITEM_NOT_FOUND
```

not a revealing permission response.

---

# 62. Staff/Admin Do Not Own Customer Carts

`STAFF` and `ADMIN` roles do not receive:

```text
cart.view_all
cart.manage_all
```

or ordinary Cart mutation rights.

Keep this boundary.

---

# 63. Staff Self-Purchase

Project rules permit Staff/Admin to purchase for themselves.

Review how this interacts with Cart ownership.

Expected:

```text
authenticated local user can own their own customer-commerce Cart
```

without needing a CUSTOMER role.

Do not require:

```text
role == CUSTOMER
```

if existing authz policy already allows Staff/Admin self-commerce.

Use ownership capability, not role-name shortcut.

---

# 64. Guest Cart Merge

CART-005 will later merge:

```text
guest Cart
→ authenticated user's active Cart
```

Phase 6.1 must verify the existing schema can support this.

---

# 65. Merge Requires Both Authorities

Merge must require:

```text
authenticated principal
+
valid guest credential
```

Guest token alone must not claim an authenticated Cart.

---

# 66. Merge Must Not Transfer Raw Ownership Fields from Client

Client cannot send:

```text
user_id
guest_token_digest
target_cart_id
```

as authority.

Server resolves both source and target.

---

# 67. Merge Outcome

Review the frozen merge semantics in the current Cart contract.

Expected concepts include:

```text
source guest items merged into authenticated active Cart
duplicate item identities consolidated
quantity bounds enforced
guest Cart retired/inactivated
guest credential retired
```

Do not invent exact algorithm if current docs already specify it.

---

# 68. Guest Credential Retirement

After successful merge:

the old guest credential must not continue authorizing the source Cart.

Phase 6.1 should verify the model supports this lifecycle.

---

# 69. Source Guest Cart Preservation

Do not hard-delete the source guest Cart unless the frozen contract explicitly requires it.

Given V1 status model:

preferred lifecycle is likely:

```text
ACTIVE → INACTIVE
```

Review current contract.

---

# 70. Authenticated Target Cart

If authenticated user already has an active Cart:

merge into it.

Do not create a second active customer Cart.

---

# 71. No Existing Active Cart

If authenticated user has none:

review whether the guest Cart can be converted/transferred or whether a new customer Cart must be created and merged.

Do not decide by convenience.

Use frozen CART-005 semantics where specified.

---

# 72. One-Active-Cart Constraint During Merge

Merge workflow must preserve:

```text
at most one ACTIVE Cart per authenticated user
```

under all paths.

---

# 73. Merge Quantity Conflict

Example:

```text
target Cart:
chair red x80

guest Cart:
chair red x30
```

Cannot become:

```text
x110
```

Review the frozen expected conflict behavior.

Phase 6.5/merge implementation must respect max 100.

---

# 74. Stale Guest Items During Merge

Guest Cart items may become:

```text
inactive
unpublished
out of stock
```

before login.

Review whether merge:

```text
preserves stale items
or rejects them
```

according to current frozen CART-005 contract.

Do not invent behavior.

---

# 75. Guest Header vs Cookie

Contract recognizes:

```text
X-Guest-Cart-Id
```

and browser:

```text
guest_cart_id cookie
```

Review exact precedence/mutual-exclusion rules.

Do not accept conflicting identities silently.

---

# 76. Browser Security

If cookie is used, ensure later implementation aligns with approved:

```text
HttpOnly
Secure
appropriate SameSite
```

policy.

Phase 6.1 only records the requirement.

---

# 77. Flutter

Flutter may send the opaque guest identifier through:

```text
X-Guest-Cart-Id
```

Do not create mobile-only Cart behavior.

Same backend rules serve Web and Flutter.

---

# 78. Cache Policy

Cart endpoints are private holder data.

Required:

```text
Cache-Control:
private,
no-cache,
no-store,
must-revalidate
```

or exact existing middleware convention.

Never public CDN-cache Cart responses.

---

# 79. Response Representation Review

Frozen Cart object:

```text
id
items_count
items[]
subtotal
updated_at
```

Verify no required field conflicts with the persistence design.

---

# 80. Cart Item Representation Review

Frozen CartItem includes:

```text
id
product_id
variant_id
product
variant
quantity
unit_price
line_total
availability
stock_indicator
is_purchasable
created_at
updated_at
```

All dynamic fields must be derived without changing Cart schema.

---

# 81. Embedded Product Summary

Cart does not necessarily embed the full public ProductSummary.

The Cart contract specifies the exact embedded fields.

Use explicit Cart-specific serialization if needed.

Do not automatically dump ProductResource.

---

# 82. Embedded Variant Summary

Likewise use the exact Cart contract representation.

Do not leak:

```text
cost_price
dimensions
internal attributes
inventory rows
```

unless explicitly contracted.

---

# 83. Missing Product/Variant via FK Rules

Current CartItem Product/Variant FKs use:

```text
RESTRICT
```

so normal hard deletion cannot orphan a Cart line.

Review how soft-deleted Products behave.

Product soft-delete may make a Cart line stale while relationship data still exists.

This should be supported.

---

# 84. Soft-Deleted Product in Cart

A Product soft-deleted after Cart addition should not be purchasable.

Review how the Cart read resolver can still provide enough data to signal stale state without violating public Product scope.

Do not silently drop the line.

---

# 85. Cart Resolver / Presenter Boundary

Phase 6.1 should identify a clean future architecture for Cart reads.

Prefer something like:

```text
Cart query/loader
→ Cart item domain projection
→ current pricing/availability resolver
→ CartResource
```

Avoid placing all logic in the controller.

---

# 86. Reuse Group E Pricing Logic

Use the same Product/Variant price authority from Group E.

Do not create:

```text
CartPriceResolver
```

with different pricing semantics unless it is only a thin reuse wrapper.

---

# 87. Reuse Group E Availability

Cart current:

```text
availability
stock_indicator
```

should use Group E's authoritative derivation.

Do not recreate stock aggregation formulas.

---

# 88. Cart-Specific Purchasability Resolver

A small Cart-domain resolver for:

```text
is_purchasable
```

may be appropriate because Cart rules include:

```text
Product type
visibility
Variant validity
requested Cart quantity
```

beyond public availability alone.

Do not overload the public availability resolver with Cart ownership/workflow concerns.

---

# 89. No Checkout Service Reuse Yet

Cart validation can share domain rules, but do not make Cart call the full Checkout service.

Checkout has:

```text
locking
reservation
Order creation
idempotency
```

which Cart must not trigger.

---

# 90. Error Contract Review

Ensure future Group F phases use existing frozen codes:

```text
CART_NOT_FOUND
CART_ITEM_NOT_FOUND
INVALID_VALUE
INVALID_TYPE
MISSING_REQUIRED_FIELD
INVALID_PRODUCT_VARIANT
PRODUCT_NOT_PURCHASABLE
PRODUCT_UNAVAILABLE
CART_ITEM_UNAVAILABLE
INSUFFICIENT_STOCK
RATE_LIMITED
```

Do not invent new Cart error codes casually.

---

# 91. Product Unpublished Error Reconciliation

The Cart resource contract says active draft/unpublished Product admission maps to:

```text
PRODUCT_NOT_PURCHASABLE
```

while another error table also mentions:

```text
PRODUCT_UNAVAILABLE
```

for failed Product purchasability flags.

Review current canonical endpoint-specific mapping carefully.

Do not implement contradictory mappings in later phases.

Record the final authoritative mapping in Phase 6.1 review.

---

# 92. Stale Read Is Not Admission Error

An already-stored stale line should generally be returned:

```text
200
is_purchasable = false
```

rather than causing entire Cart GET to fail.

Keep this distinction.

---

# 93. Checkout Rejects Invalid Cart

Future Checkout will reject Cart containing stale/unpurchasable lines.

Do not make Cart GET itself unusable because one line is stale.

Customer needs to see and remove it.

---

# 94. No Cart Pagination

Cart items are embedded in the Cart object.

Do not introduce:

```text
page
per_page
```

for Cart items in V1 unless frozen docs say otherwise.

---

# 95. Deterministic Cart Item Ordering

The Cart contract says:

```text
deterministic insertion order
```

Review how this maps to current schema.

Likely:

```text
created_at ASC
id ASC
```

or existing relation ordering.

Document one stable rule.

Do not rely on unspecified DB order.

---

# 96. Item Ordering Is Not User Sort

Do not add:

```text
sort
display_order
```

fields to CartItem.

Insertion order is enough.

---

# 97. Updated Timestamp

Cart's:

```text
updated_at
```

should represent last meaningful Cart mutation.

Review how item mutations will touch/update the parent Cart.

Do not assume Eloquent automatically updates parent Cart unless configured.

---

# 98. Cart Timestamp Touching

Decide whether future:

```text
add
quantity update
remove
merge
```

should explicitly update Cart.updated_at.

Given the response contract, they should.

Document mechanism for later phases.

---

# 99. Read Should Not Touch Cart

GET Cart must not update:

```text
updated_at
```

simply because live prices/availability were recalculated.

---

# 100. No Pricing Mutation of Cart

A catalog price change should alter the next serialized Cart subtotal without mutating Cart persistence or timestamps.

---

# 101. No Availability Mutation of Cart

A stock change should alter serialized availability without writing CartItem.

---

# 102. One Active Guest Cart?

Review whether schema enforces one ACTIVE Cart per guest credential.

Because:

```text
guest_token_digest is globally unique
```

one credential naturally identifies one Cart.

Confirm no additional active-guest guard is needed.

---

# 103. Guest Cart Creation

Phase 6.2 will need to define:

```text
GET /me/cart with no valid guest credential
```

behavior for anonymous users.

Review frozen CART-001 behavior now.

Does GET create a Cart or only return existing?

Do not guess if docs specify it.

---

# 104. Authenticated Cart Creation

Likewise review whether:

```text
GET /me/cart
```

must lazily create an active Cart if none exists.

This impacts Phase 6.2.

Document exact frozen behavior.

---

# 105. Create/Get Boundary

If the contract intentionally combines create/get through:

```text
CART-001
GET /me/cart
```

record the idempotent lazy-create semantics.

Do not add:

```text
POST /carts
```

unless already approved.

---

# 106. No User-Supplied Cart ID

Future Cart APIs remain self-context.

Do not accept:

```text
cart_id
user_id
```

in request bodies/query strings.

---

# 107. Cart Mutation Rate Limiting

Review Phase 4.11 limiter assignments.

Frozen security contract includes Cart mutation abuse controls.

Later phases should reuse the correct limiter.

Do not create one here unless missing and clearly required.

---

# 108. Cart Reads Rate Limit

Review current protected read limiter for:

```text
GET /me/cart
```

Do not apply public catalog limiter accidentally.

---

# 109. Authentication Optionality

Cart routes support two holder modes:

```text
authenticated local user
or
valid guest credential
```

Do not make the middleware require Clerk unconditionally before guest resolution.

---

# 110. Ambiguous Dual Identity

If a request has:

```text
authenticated user
+
guest credential
```

review canonical semantics.

Likely:

```text
normal authenticated Cart access
```

versus explicit:

```text
CART-005 merge
```

for guest handoff.

Do not silently auto-merge guest Cart on every authenticated Cart request unless the contract says so.

---

# 111. Merge Must Be Explicit

The V1 surface includes:

```text
POST /me/cart/merge
```

Therefore login/authentication alone should not accidentally perform hidden Cart mutation unless current auth architecture explicitly defines that workflow.

Preserve explicitness.

---

# 112. Checkout Cart Lifecycle Dependency

Future successful checkout clears Cart items so a new empty active Cart is available, according to the Checkout contract.

Review whether the existing Cart schema/status design supports:

```text
clear existing Cart items
keep Cart ACTIVE
```

versus:

```text
mark Cart INACTIVE
create new active Cart
```

The Checkout contract currently favors clearing items.

Do not change it in Phase 6.1.

---

# 113. Cart Historical Status vs Checkout Clear

Group C allows inactive historical Carts, but checkout may simply clear the active Cart.

These concepts are not contradictory.

Document when INACTIVE is actually intended to be used:

```text
merge/retirement
ownership lifecycle
other explicit workflow
```

rather than assuming checkout always inactivates.

---

# 114. No Order History in Cart

Clearing Cart after checkout is safe because historical purchase information is in:

```text
Order
OrderItem snapshots
```

Do not keep Cart prices/items as order history.

---

# 115. Cart FK Delete Policy

Review current:

```text
cart_items.product_id RESTRICT
cart_items.variant_id RESTRICT
```

and ensure this remains compatible with stale Cart preservation.

Do not switch to cascade.

---

# 116. User Delete Policy Variance

Known Group C variance:

```text
carts.user_id
RESTRICT on MySQL
RESTRICT-equivalent / null-related SQLite behavior
```

Review current repository state.

Do not change it during Cart model review unless account-retention policy has now been finalized.

---

# 117. No User Deletion Policy Invention

If account deletion remains deferred:

leave the FK variance documented.

Do not redesign Cart ownership for an unrelated future feature.

---

# 118. Cart Factory Review

Verify current:

```text
CartFactory
CartItemFactory
```

can generate:

```text
customer-owned active Cart
guest-owned active Cart
inactive Cart
valid Product/Variant line
null-Variant line if supported
quantity boundaries
```

without fabricating pricing/reservation state.

---

# 119. Group E Product Factory Compatibility

Ensure Cart factories/tests can explicitly create:

```text
published IN_STOCK Product
MADE_TO_ORDER Product
inactive Product
unpublished Product
active Variant
inactive Variant
stocked Variant
out-of-stock Variant
```

using the post-Group-E model.

---

# 120. Existing Cart Schema Tests

Review:

```text
tests/Feature/CartSchemaTest.php
```

Do not duplicate its schema/invariant tests in later API tests.

---

# 121. Existing Coverage to Preserve

At minimum preserve:

```text
ownership XOR
guest digest security
one active customer Cart
status CLOSED
quantity bounds
duplicate line identity
Variant belongs to Product
no pricing columns
no inventory columns
no reservation columns
```

---

# 122. New Review-Level Tests?

Phase 6.1 should generally not add large API tests.

Add only focused regression tests if the review discovers a genuine model defect or post-Group-E incompatibility.

Full Cart API coverage belongs to Phase 6.8.

---

# 123. Review Group E Integration

Verify Cart model assumptions still hold after Group E changes:

```text
products.product_type now exists
products.is_published now exists
availability derives from inventory
Variant active restriction exists
price resolver finalized
```

No Cart schema migration should be necessary just because these fields now exist.

---

# 124. Public vs Cart Product Resolution

Do not blindly use the public Product query scope for stale Cart lines.

Public scope hides inactive/unpublished Products.

Cart read may need to resolve an existing stored Product specifically so it can return the stale line as unavailable.

This is an important architectural distinction.

---

# 125. Stale Cart Lookup Strategy

Existing Cart item points to Product through FK.

Cart projection should be able to load that Product even when no longer public and then derive:

```text
is_purchasable = false
```

without exposing it elsewhere.

Do not call CAT-002 endpoint internally.

---

# 126. Variant Stale Lookup Strategy

Likewise, do not filter inactive Variants out before Cart can evaluate an existing line.

For admission:

```text
active Variant required
```

For existing-line presentation:

```text
inactive Variant may need to remain loadable
```

so Cart can signal staleness.

---

# 127. Avoid Public Scope on Cart Relations

Review model relations/scopes to ensure Cart item Product/Variant relations do not automatically apply public/active scopes that make stale lines disappear.

Use explicit context-specific querying.

---

# 128. Current Price for Stale Item

Review the frozen contract's expected behavior if Product/Variant becomes inactive but still has pricing data.

Likely Cart can still show current/last live database price informationally while marking item unpurchasable.

Do not invent null price if price contract is non-null.

Follow current docs.

---

# 129. Hard-Deleted Referents

FK RESTRICT should normally prevent Product/Variant hard delete while CartItem exists.

Confirm this remains true.

This simplifies stale Cart serialization.

---

# 130. Cart Domain Services Needed Later

Phase 6.1 may recommend, but not overbuild, focused boundaries such as:

```text
CartHolderResolver
CartFinder
CartItemPurchasabilityResolver
CartPricingProjection
GuestCartCredentialResolver
```

Only create them later as implementation demands.

Do not build speculative abstractions now.

---

# 131. Avoid One Giant `CartService`

Do not plan a monolithic class that handles:

```text
guest auth
cart lookup
pricing
availability
add
update
delete
merge
checkout
```

Prefer small responsibilities.

---

# 132. DTO / Request Boundary

Future Cart mutation requests must follow:

```text
FormRequest::validated()
→ DTO/command where useful
→ domain/service
```

No `$request->all()`.

---

# 133. Server-Controlled Fields

Future add/update requests must reject client-supplied:

```text
price
unit_price
line_total
subtotal
total
currency
stock
availability
stock_indicator
is_purchasable
discount
delivery_fee
user_id
guest_token_digest
cart_id
```

unless a field is explicitly part of the frozen request.

---

# 134. Add Item Request

Future CART-002 accepts only:

```text
product_id
variant_id conditional
quantity
```

Record this unchanged.

---

# 135. Update Item Request

Future CART-003 accepts only:

```text
quantity
```

Product and Variant identity are immutable for a line.

---

# 136. Remove Item Request

Future CART-004:

```text
DELETE
no body
```

Do not use:

```text
quantity = 0
```

as deletion.

---

# 137. Merge Request

Future CART-005 accepts only the approved guest identifier transport.

Do not accept arbitrary source/target Cart IDs.

---

# 138. No Cart Bulk Update

Do not add:

```text
PATCH /me/cart
```

with arbitrary items array in V1.

The frozen surface uses item-level operations.

---

# 139. No Clear-Cart Endpoint

Do not invent:

```text
DELETE /me/cart
POST /me/cart/clear
```

unless later explicitly approved.

Checkout clearing is internal workflow behavior.

---

# 140. No Save-for-Later

Out of scope.

---

# 141. No Wishlist Coupling

Out of scope.

---

# 142. No Promotions/Coupons

Cart schema should not be expanded for:

```text
coupon
promotion
discount
```

because V1 excludes those features.

---

# 143. No Delivery Fee in Cart

Delivery fee belongs to Order/fulfillment after checkout.

Do not add delivery fee to Cart totals.

Cart subtotal is Product line subtotal only.

---

# 144. Currency

All current purchasable catalog prices use:

```text
TZS
```

with integer minor units.

Cart should preserve standard money object shape.

No formatted currency strings.

---

# 145. Overflow Safety

Review multiplication:

```text
unit_price.amount * quantity
```

for integer safety with existing price/quantity bounds.

Do not use floats.

---

# 146. Cart Empty State

A valid active Cart with:

```text
items = []
```

is normal.

Do not treat empty Cart as missing Cart.

---

# 147. `items_count = 0`

Empty Cart representation should still return:

```text
items_count = 0
items = []
subtotal = 0 TZS
```

according to contract.

Review exact money representation.

---

# 148. Cart Not Found vs Lazy Create

Reconcile:

```text
CART_NOT_FOUND
```

with Phase 6.2 create/get behavior.

Determine exactly when CART-001 should:

```text
create an empty Cart
```

versus:

```text
return CART_NOT_FOUND
```

Do not leave this ambiguous before 6.2.

---

# 149. Guest Invalid Credential

Review frozen behavior for:

```text
malformed guest credential
unknown guest credential
retired merged credential
```

and record exact status/error mapping for Phase 6.2.

Do not leak whether arbitrary tokens map to stored Carts.

---

# 150. Guest Token Rotation

Do not implement token rotation unless current contract requires it.

---

# 151. Logging Security

Later Cart logging must never include:

```text
raw guest token
guest cookie value
guest header value
```

Review current request logging/redaction configuration.

---

# 152. Cart Representation Is Private

Even guest Cart is not "PUBLIC_READ".

It is holder-private via bearer guest credential.

Do not reuse public cache middleware.

---

# 153. No Raw Guest Digest in Serialization

Ensure:

```text
guest_token_digest
```

remains hidden in:

```text
models
resources
logs/errors
```

---

# 154. No Cart Ownership Fields in API

Cart response should not expose:

```text
user_id
guest_token_digest
active_user_guard
```

unless explicitly contracted.

Current frozen Cart object does not.

---

# 155. Database Schema Change Expectation

Expected:

```text
NONE
```

for Phase 6.1.

The existing Group C schema was intentionally designed for Group F.

---

# 156. Acceptable Schema Change

Only add a migration if the review proves a concrete contradiction between:

```text
existing Group C persistence
and
frozen V1 Cart contract
```

Do not modify historical migrations.

---

# 157. No New Dependency Expected

Expected:

```text
Composer dependencies: NONE
```

---

# 158. No Frontend Changes

Do not modify:

```text
frontend/web/
frontend/app/
frontend/design-system/
```

Group F is backend API/domain work.

---

# 159. PHPStan

Run static analysis if code changes occur.

Phase 6.1 must introduce:

```text
0 new PHPStan errors
```

---

# 160. Verification

At minimum run the existing focused Cart model/schema tests:

```bash
php artisan test --filter=CartSchemaTest
```

or actual relevant test command.

If code/docs change, also run:

```bash
php artisan test
vendor/bin/pint --test
vendor/bin/phpstan analyse
composer audit
git diff --check
```

---

# 161. Review Deliverable

Phase 6.1 should produce a concise Cart model review documenting:

```text
existing model
approved invariants
API mapping
Group E dependencies
auth/guest ownership mapping
identified gaps
required future services
schema-change decision
Phase 6.2 readiness
```

---

# 162. Gap Classification

For every discrepancy classify it as:

```text
NO GAP
DOC DRIFT
MODEL GAP
API IMPLEMENTATION GAP
DEFERRED TO LATER GROUP F PHASE
```

Do not implement every future gap during Phase 6.1.

---

# 163. Mandatory Review — Ownership

Confirm:

```text
customer ownership
guest ownership
ownership XOR
one active customer Cart
Cart ID not authority
Staff/Admin isolation
Staff/Admin self-commerce
```

---

# 164. Mandatory Review — Item Identity

Confirm:

```text
Product + Variant identity
nullable Variant semantics
duplicate prevention
same Product/different Variant coexistence
quantity bounds
```

---

# 165. Mandatory Review — Pricing

Confirm:

```text
no persisted price
live current pricing
line totals derived
subtotal derived
checkout recalculates
```

---

# 166. Mandatory Review — Inventory

Confirm:

```text
no Cart reservation
live informational availability
stale items preserved
checkout owns locked reservation
```

---

# 167. Mandatory Review — Security

Confirm:

```text
raw guest credential never persisted
digest only
credential high entropy
credential scope limited
private cache
IDOR protection design
```

---

# 168. Mandatory Review — Merge

Confirm model can support:

```text
guest source
authenticated target
duplicate consolidation
max quantity rules
source retirement
credential retirement
one active target
```

---

# 169. Mandatory Review — Stale Items

Confirm model can preserve:

```text
inactive Product
unpublished Product
inactive Variant
out-of-stock Variant
```

without silently deleting lines.

---

# 170. Mandatory Review — Checkout Handoff

Confirm model supports:

```text
cart remains mutable until checkout
checkout revalidates all state
checkout reserves inventory
checkout recalculates price
successful checkout clears Cart
failed checkout preserves Cart
```

without schema redesign.

---

# 171. Phase 6.2 Readiness Gate

Do not mark Phase 6.1 PASS until the agent can state exactly how CART-001 will resolve:

```text
authenticated active Cart
guest active Cart
no existing Cart
invalid guest credential
authenticated + guest credential
```

No implementation needed yet, but semantics must be unambiguous.

---

# 172. Completion Report

Return:

## Phase 6.1 status

```text
PASS
```

or:

```text
BLOCKED
```

## Existing schema

State whether:

```text
carts/cart_items
```

remain fit for purpose.

## Ownership

Report:

```text
customer
guest
XOR
active-cart rule
guest credential model
```

## Cart item model

Report:

```text
Product/Variant identity
quantity
duplicate rules
```

## Pricing

Confirm:

```text
no persisted prices
current catalog derivation
```

## Availability

Confirm:

```text
no reservation
current Group E availability
stale item preservation
```

## Merge readiness

State whether CART-005 can be implemented without schema change.

## Contract gaps

List each discovered mismatch with classification.

## Schema changes

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

Report relevant focused/full suite results.

## Quality

Report:

```text
Pint
PHPStan
Composer audit
git diff --check
```

if applicable.

## Phase 6.2 readiness

Explicit:

```text
READY
```

or:

```text
BLOCKED
```

with reasons.

---

# 173. Definition of Done

Phase 6.1 is complete when:

* the Group C Cart schema is reviewed against current V1 contracts;
* ownership XOR remains valid;
* guest credential security remains valid;
* raw guest credential is never persisted;
* one active Cart per authenticated user remains valid;
* ACTIVE/INACTIVE remains sufficient;
* Cart item identity remains Product + Variant;
* duplicate line prevention remains valid;
* quantity 1..100 remains authoritative;
* nullable Variant semantics are explicitly reconciled with current Product/Variant architecture;
* no Cart prices are persisted;
* no Cart totals are persisted;
* no Cart availability is persisted;
* no Cart reservation fields exist;
* current Product/Variant prices can be projected into Cart;
* Group E availability can be projected into Cart;
* stale Cart items can remain visible rather than disappearing;
* public Product scopes will not accidentally hide stale Cart relations;
* Cart-specific `is_purchasable` semantics are defined;
* MADE_TO_ORDER remains prohibited;
* Staff/Admin ordinary Cart access remains prohibited;
* Staff/Admin self-commerce remains supported according to authz policy;
* guest→authenticated merge is model-compatible;
* merge cannot produce multiple active customer Carts;
* successful checkout Cart-clearing behavior is model-compatible;
* failed checkout can preserve Cart;
* no schema redesign is performed without proven need;
* no frontend work occurs;
* Phase 6.2 holder/create/get semantics are unambiguous.

---

# 174. Out of Scope

Do not implement yet:

```text
CART-001 controller behavior
CART-002 add item
CART-003 update quantity
CART-004 remove item
CART-005 merge
full Cart resource projection
stock revalidation workflow
checkout
inventory reservation
Order creation
frontend Cart UI
```

Those belong to later Group F/G phases.

---

# 175. STOP Condition

STOP when the existing Cart model has been fully reviewed and reconciled against:

```text
Group C persistence
Group D ownership/authentication
Group E pricing/availability/inventory
V1 CART-001..005 contract
Checkout boundary
```

and the agent can confidently state whether Phase 6.2 can begin without schema redesign.

Do not continue automatically to Phase 6.2.

DO NOT COMMIT OR PUSH.

The project owner handles all Git operations.
