# Phase 6.8 — Cart Tests & Group F Closure

## Purpose

Complete **Phase Group F — Cart** by:

```text
1. Closing the remaining frozen CART-005 merge gap
2. Running comprehensive Cart regression tests
3. Verifying security, ownership, validation, stock, concurrency and contract behavior
4. Resolving any defects uncovered by the closure suite
5. Producing the final Group F PASS / BLOCKED decision
```

This is primarily a **closure and regression phase**.

Do not introduce unrelated Cart features.

---

# 1. Group F Exit Condition

`AGENTS.md` defines:

```text
PHASE GROUP F — CART

6.1 — Cart model review
6.2 — Create/get cart
6.3 — Add item
6.4 — Update quantity
6.5 — Remove item
6.6 — Cart validation
6.7 — Stock revalidation
6.8 — Cart tests
```

Exit condition:

```text
A customer can maintain a valid cart entirely through the API.
```

Group F must not be marked PASS until that condition is actually satisfied.

---

# 2. Current Group F State

Already completed:

```text
6.1       PASS — Cart model review
6.2       PASS — Create/get cart
6.3–6.5   PASS — Add/update/remove
6.6       PASS — Validation consolidation
6.7       PASS — Stock revalidation
```

Existing Cart routes:

```text
GET    /api/v1/me/cart
POST   /api/v1/me/cart/items
PATCH  /api/v1/me/cart/items/{item}
DELETE /api/v1/me/cart/items/{item}
POST   /api/v1/me/cart/merge
```

The first four are implemented.

`CART-005` currently remains:

```text
501 stub
```

That must be resolved before Group F can close.

---

# 3. Why CART-005 Is Part of Phase 6.8 Closure

The frozen V1 surface explicitly includes:

```text
CART-005
POST /api/v1/me/cart/merge
```

Guest Cart security also establishes:

```text
guest token alone cannot merge
merge requires authenticated principal + valid guest credential
token retired after successful merge
```

And Checkout explicitly prohibits guest checkout:

```text
guest
→ authenticate/register
→ merge guest Cart
→ checkout authenticated Cart
```

Therefore CART-005 is required to complete the Cart lifecycle and the Group F → Group G handoff.

Do not enter Group G with CART-005 still returning 501.

---

# 4. Phase 6.8 Rule

Phase 6.8 remains a test/closure phase.

Implementation changes are allowed only when:

```text
a closure test exposes a genuine defect
or
a frozen Group F operation remains unimplemented
```

CART-005 qualifies as the latter.

Implement the smallest contract-compliant merge workflow.

Do not redesign Cart architecture.

---

# 5. CART-005 Canonical Route

Implement:

```http
POST /api/v1/me/cart/merge
```

Do not add:

```text
POST /cart/claim
POST /cart/import
POST /guest/cart/merge
POST /me/cart/transfer
```

---

# 6. CART-005 Authentication

CART-005 requires:

```text
authenticated Clerk principal
+
valid guest Cart credential
```

Both are mandatory.

This differs from CART-001–004.

---

# 7. Bearer Authentication Is Required

Unlike ordinary guest-capable Cart routes:

```text
CART-005 must require bearerAuth
```

An anonymous caller with only a guest credential:

```text
401 AUTHENTICATION_REQUIRED
```

The guest token cannot authorize merge by itself.

---

# 8. Invalid Bearer

If an invalid Clerk bearer is supplied:

```text
401
```

Do not downgrade to guest.

Do not perform merge.

---

# 9. Guest Credential Is Also Required

Authenticated principal without a guest credential cannot perform a meaningful guest merge.

Follow the frozen error/validation conventions.

Do not invent a source Cart ID request body.

---

# 10. Guest Credential Transport

The accepted guest security model supports:

```text
Browser:
guest_cart_id HttpOnly cookie

Non-browser / Flutter:
X-Guest-Cart-Id header
```

CART-005 must support the same client-type split.

---

# 11. OpenAPI Closure Review

The current OpenAPI lists:

```text
X-Guest-Cart-Id
```

for CART-005.

Review this against the accepted browser transport rule.

Because browser JavaScript cannot read the HttpOnly `guest_cart_id` cookie, a browser merge must be able to authenticate the guest Cart through that cookie.

If the updated OpenAPI omits the cookie parameter on CART-005:

classify this as:

```text
CONTRACT DOCUMENTATION DRIFT
```

and minimally reconcile it.

Do not make browsers expose the HttpOnly credential through JavaScript.

---

# 12. Ambiguous Guest Credentials

If both:

```text
guest_cart_id cookie
X-Guest-Cart-Id header
```

are supplied:

reuse `GuestCartTransport` behavior.

Expected existing rule:

```text
422 INVALID_VALUE
```

Do not guess which credential should win.

---

# 13. Source Cart

The guest credential must resolve:

```text
one ACTIVE guest-owned Cart
```

with:

```text
user_id = null
guest_token_digest = digest(raw token)
status = ACTIVE
```

---

# 14. Unknown Guest Credential

Authenticated principal + unknown valid-format token:

follow current retired/unknown guest-token semantics.

Expected:

```text
401 AUTHENTICATION_REQUIRED
```

Do not reveal whether the digest exists historically.

---

# 15. Retired Guest Credential

If source guest Cart is already:

```text
INACTIVE
```

normal non-idempotent reuse must not merge again.

The credential is retired.

---

# 16. Target Cart

Target is always:

```text
authenticated principal's own ACTIVE Cart
```

Never client-selected.

No:

```text
target_cart_id
user_id
```

request input.

---

# 17. Target Cart Creation

If authenticated user has no active Cart:

reuse:

```text
GetOrCreateActiveCart
```

to obtain/create one.

Do not create a separate merge-specific active-cart implementation.

---

# 18. One Active Target Cart

The existing:

```text
active_user_guard
```

remains final DB protection.

Merge must never result in multiple ACTIVE authenticated Carts.

---

# 19. Staff/Admin Personal Commerce

Authenticated STAFF/ADMIN merge only into:

```text
their own personal Cart
```

under the self-commerce rule established in Phase 6.2.

No operational access to another customer's Cart.

---

# 20. Merge Identity

Cart line identity remains:

```text
(product_id, variant_id)
```

within the target Cart.

---

# 21. Non-Overlapping Source Line

If source guest Cart contains a line not present in target:

copy/create that intent in the authenticated Cart.

Example:

```text
Guest:
Chair/red x2

Target:
Table/oak x1
```

Result:

```text
Chair/red x2
Table/oak x1
```

---

# 22. Overlapping Source Line

If both Carts contain the same identity:

merge quantities.

Example:

```text
Guest:
Chair/red x3

Target:
Chair/red x2
```

Result:

```text
Chair/red x5
```

One target line only.

---

# 23. Quantity Cap

Preserve accepted duplicate merge behavior:

```text
result =
min(target quantity + guest quantity, CartItem::MAX_QUANTITY)
```

Maximum:

```text
100
```

Do not exceed it.

---

# 24. Merge Is Not CART-002 Admission

Guest items already represent existing Cart intent.

Do not run ordinary "new add" admission logic in a way that destroys or rejects stale guest intent.

A guest Cart can become stale between browsing and login.

---

# 25. Preserve Stale Guest Lines

If source contains an existing line whose Product is now:

```text
inactive
unpublished
soft-deleted
MADE_TO_ORDER
out of stock
insufficient stock
inactive Variant
```

do not silently discard it during merge.

Transfer/merge the line as Cart intent.

Then current Cart projection should mark it:

```text
is_purchasable = false
```

through Phase 6.6/6.7 logic.

---

# 26. Why Stale Lines Must Survive Merge

Accepted Cart semantics say:

```text
stale lines are retained and flagged
```

Merge must not become a hidden stale-item cleanup operation.

The authenticated customer should see the resulting stale item and remove/adjust it explicitly.

---

# 27. No Stock Requirement for Merge

Do NOT reject the whole merge merely because current inventory cannot satisfy a guest line.

Stock is live advisory Cart state.

After merge:

```text
CartStockRevalidator
```

will evaluate it.

---

# 28. No Inventory Reservation During Merge

CART-005 must not modify:

```text
ProductStock.quantity
ProductStock.reserved_quantity
```

---

# 29. No ProductStock Lock

Do not lock inventory during merge.

There is no stock guarantee.

---

# 30. No Pricing Persistence

Merge does not copy/persist:

```text
unit_price
line_total
subtotal
```

Target Cart projection uses current live pricing.

---

# 31. Source Guest Cart Retirement

After a successful merge:

```text
source guest Cart
ACTIVE → INACTIVE
```

Its guest digest remains persisted because ownership XOR requires the guest ownership context.

Do not clear the digest and break the invariant.

---

# 32. Retired Token

Once source becomes INACTIVE:

its raw credential must no longer resolve an ACTIVE guest Cart.

It may not be recycled.

It may not be used to merge a second time.

---

# 33. Source Cart Preservation

Do not hard-delete the source guest Cart.

The established V1 lifecycle uses:

```text
INACTIVE
```

for retirement.

---

# 34. Source Items

Prefer preservation of source Cart history unless current repository authority specifies otherwise.

The merge operation may copy/consolidate the source intent into the target while the source Cart becomes INACTIVE.

Do not hard-delete source Cart merely for convenience.

---

# 35. Target Cart Remains ACTIVE

Successful merge:

```text
authenticated target Cart remains ACTIVE
```

---

# 36. Merge Cart Timestamp

Successful merge should touch:

```text
target Cart.updated_at
source Cart.updated_at
```

and changed/new target CartItems as appropriate.

---

# 37. No-Op Guest Cart

If source guest Cart is ACTIVE but empty:

merge should still safely retire the guest Cart and return the authenticated target Cart.

Do not fail merely because source has zero items unless frozen contract explicitly says otherwise.

---

# 38. Target Empty / Source Non-Empty

Must work.

---

# 39. Target Non-Empty / Source Empty

Must work.

---

# 40. Both Empty

Must work safely.

---

# 41. Idempotency-Key

OpenAPI requires:

```http
Idempotency-Key: <uuid>
```

for CART-005.

Treat it as mandatory.

---

# 42. Reuse Shared Idempotency Infrastructure

Phase 5.9 already introduced shared durable idempotency infrastructure.

Reuse it.

Do not implement:

```text
cart_merge_idempotency
```

as an isolated second subsystem.

---

# 43. Idempotency Scope

Use the established V1 scope:

```text
authenticated identity
+
endpoint/action
+
idempotency key
```

Guest token is not authentication and does not replace identity scoping.

---

# 44. Merge Fingerprint

The logical input has little/no JSON body.

Therefore the durable fingerprint must include the material merge intent.

At minimum:

```text
source guest Cart identity / credential digest
target authenticated principal
endpoint/action
```

Do not fingerprint the raw guest token itself into logs.

Use its server-derived digest/Cart identity internally.

---

# 45. Same Key + Same Merge

Same authenticated principal:

```text
same Idempotency-Key
same source guest Cart
```

must replay the original:

```text
200 Cart response
```

without:

```text
re-merging quantities
creating duplicate items
retiring anything twice
creating duplicate idempotency effects
```

---

# 46. Important Retired-Token Replay Rule

After the first successful merge:

```text
source guest Cart = INACTIVE
```

but a legitimate idempotent retry still needs to replay the original result.

Therefore do not structure replay as:

```text
resolve ACTIVE guest Cart first
→ fail 401 because token retired
→ then check idempotency
```

That would break idempotency.

---

# 47. Correct Replay Ordering

Conceptually:

```text
authenticate principal
→ validate Idempotency-Key format
→ validate/normalize guest credential transport
→ derive safe guest credential fingerprint
→ consult idempotency record
→ if successful matching replay exists:
     return recorded result
→ otherwise require source ACTIVE Cart
→ perform merge
```

Exact integration should reuse existing idempotency infrastructure.

---

# 48. Same Key + Different Guest Cart

Same authenticated user + same Idempotency-Key but a materially different guest source:

```text
409 DUPLICATE_OPERATION
```

according to shared idempotency semantics.

---

# 49. Different User + Same Key

Idempotency keys are identity-scoped.

Another authenticated user using the same UUID is a separate key scope.

No cross-user replay.

---

# 50. Missing Idempotency-Key

Reject according to frozen idempotency validation.

Do not silently generate one server-side.

---

# 51. Malformed Idempotency-Key

Reject before mutation.

No merge effects.

---

# 52. Transaction Boundary

The entire merge business effect must be atomic.

At minimum transactionally cover:

```text
source Cart state validation
target Cart state
source items
target item inserts/updates
quantity consolidation
source ACTIVE → INACTIVE
idempotency success recording
```

---

# 53. Atomic Failure

Any failure means:

```text
no partial target merge
source remains ACTIVE
idempotency not recorded as successful
```

Do not leave half the guest lines copied.

---

# 54. Lock Source Cart

Lock the source guest Cart during first-time merge execution.

This prevents two different requests from successfully merging the same ACTIVE source concurrently.

---

# 55. Lock Target Cart

Lock the authenticated target Cart as required to protect target item consolidation.

---

# 56. Lock Ordering

Use one deterministic Cart locking order.

For example:

```text
resolve source + target internal IDs
lock Cart rows in deterministic ID order
```

then lock affected CartItem rows deterministically.

The exact implementation may follow an existing repository lock-order convention.

Avoid deadlocks.

---

# 57. Target Item Locks

For overlapping identities:

lock/update the existing target CartItem.

---

# 58. Missing Target-Line Race

If a target identity is absent and concurrent operations attempt creation:

preserve existing unique constraint + bounded retry strategy.

Do not expose duplicate-key SQL errors.

---

# 59. Concurrent Same-Key Merge

Two concurrent requests with:

```text
same user
same source token
same Idempotency-Key
```

must produce exactly one business effect.

Both should ultimately receive the same logical result.

---

# 60. Concurrent Different-Key Merge of Same Guest

Two requests:

```text
same authenticated user
same guest Cart
different Idempotency-Key
```

must not merge twice.

One may succeed.

The other must observe the source as retired / unavailable according to canonical guest credential semantics.

No quantity duplication.

---

# 61. Concurrent Merge vs Guest Mutation

A guest could theoretically mutate while login/merge occurs.

Ensure source Cart locking/status revalidation prevents:

```text
item mutation after retirement being silently merged/lost
```

The final serialized transaction ordering must leave a valid state.

Do not build a distributed lock.

---

# 62. Concurrent Merge vs Target CART-002

Target authenticated user may simultaneously add an item also present in guest source.

Final target line must:

```text
remain unique
quantity <= 100
contain a serialized valid result
```

No lost quantity update.

---

# 63. Reuse Existing Concurrency Infrastructure

Use:

```text
ConcurrentTransaction
```

or current repository equivalent where appropriate.

Do not create a second retry framework.

---

# 64. Merge Response

Frozen OpenAPI:

```text
200
{
  "data": Cart
}
```

Return the authenticated target Cart after merge.

---

# 65. Response Projection

Use existing:

```text
CartController::present()
CartStockRevalidator
CartResource
CartItemResource
```

or actual current projection path.

Do not create special merge serialization.

---

# 66. Result Uses Current Pricing

After merge:

```text
unit_price
line_total
subtotal
```

must reflect current live catalog state.

---

# 67. Result Uses Current Stock

After merge:

```text
availability
stock_indicator
is_purchasable
```

must run through Phase 6.7 Cart-wide stock revalidation.

---

# 68. Merge Does Not Make Stale Lines Valid

Transferred stale lines remain stale in the response.

---

# 69. Browser Credential Retirement

Review current guest transport contract for how a successfully retired browser credential should be cleared client-side.

If existing convention specifies cookie expiry/clearing:

implement it.

If not specified:

do not invent a breaking wire contract.

Regardless, server-side source retirement is authoritative.

---

# 70. Flutter Credential Retirement

The API must not reissue the retired guest token.

Flutter/client can discard its secure-storage value after successful merge.

Do not add a new guest credential to the merge response.

---

# 71. No Guest Credential in JSON

Never return:

```text
guest_cart_id
guest_token
guest_token_digest
```

in the Cart response.

---

# 72. Security Test — Guest Token Alone

Anonymous:

```text
POST /me/cart/merge
guest credential present
idempotency key present
```

must fail:

```text
401
```

No mutation.

---

# 73. Security Test — Auth Only

Authenticated but no guest credential:

must fail according to canonical validation/auth semantics.

No source guessed.

---

# 74. Security Test — Invalid Bearer

Invalid bearer + valid guest credential:

```text
401
```

No downgrade.

---

# 75. Security Test — Unknown Guest Token

Authenticated + unknown guest UUID:

```text
401
```

or exact frozen current guest-credential error.

No existence disclosure.

---

# 76. Security Test — Retired Guest Token

Normal new Idempotency-Key after successful prior merge:

must not merge again.

---

# 77. Security Test — Ambiguous Transport

Cookie + header:

```text
422 INVALID_VALUE
```

if current `GuestCartTransport` semantics apply.

---

# 78. Security Test — Browser Cookie Merge

Authenticated browser request using only the HttpOnly guest cookie:

merge succeeds.

This test is important because JS cannot read the credential to construct `X-Guest-Cart-Id`.

---

# 79. Security Test — Flutter Header Merge

Authenticated non-browser request with:

```text
X-Guest-Cart-Id
```

merge succeeds without a cookie.

---

# 80. Merge Test — Guest Only Items

Guest has multiple unique items.

Target empty.

All lines appear once in target.

Source becomes INACTIVE.

---

# 81. Merge Test — Target Only Items

Guest empty.

Target lines unchanged.

Guest source retired.

---

# 82. Merge Test — Overlap

Example:

```text
guest A x3
target A x4
```

result:

```text
A x7
```

one row.

---

# 83. Merge Test — Quantity Clamp

Example:

```text
guest A x40
target A x80
```

result:

```text
A x100
```

not 120.

---

# 84. Merge Test — Same Product Different Variants

Example:

```text
guest Chair/red
target Chair/blue
```

must remain separate lines.

---

# 85. Merge Test — Multiple Overlaps

Verify several source lines are consolidated correctly in one transaction.

---

# 86. Merge Test — Stale Guest Product

Guest line Product becomes inactive.

Merge succeeds.

Target response contains line with:

```text
is_purchasable = false
```

---

# 87. Merge Test — Unpublished Guest Product

Same preservation behavior.

---

# 88. Merge Test — Out-of-Stock Guest Line

Same.

Do not reject entire merge.

---

# 89. Merge Test — Partial Stock

Guest qty 5; live availability 2.

Merge succeeds.

Target projection:

```text
availability = available
is_purchasable = false
```

according to Phase 6.6/6.7 semantics.

---

# 90. Merge Test — MADE_TO_ORDER Historical Line

If guest persistence contains one:

merge preserves intent and projection marks it unpurchasable.

Do not route it automatically to furniture-request creation.

---

# 91. Merge Test — No Inventory Effect

Capture every relevant ProductStock:

```text
quantity
reserved_quantity
```

before/after.

Must be unchanged.

---

# 92. Merge Test — No ProductStock Lock Requirement

Merge should not depend on inventory locking/reservation.

---

# 93. Merge Test — Source Retirement

After success:

```text
source.status = INACTIVE
guest_token_digest unchanged
```

---

# 94. Merge Test — Token Cannot Resolve Guest Cart

After successful merge, ordinary guest access using the old token:

```text
401
```

according to current retired-token semantics.

---

# 95. Idempotency Test — First Request

Valid request:

```text
200
```

with merged target Cart.

One idempotency success record.

---

# 96. Idempotency Test — Same-Key Replay

Same user + same source token + same key:

```text
200
```

same logical Cart result.

No quantity duplication.

No second source retirement.

---

# 97. Idempotency Test — Different Source

Same user + same key + different guest source:

```text
409 DUPLICATE_OPERATION
```

---

# 98. Idempotency Test — Concurrent Same Key

Real concurrent requests:

assert:

```text
exactly one merge effect
same logical response
one durable idempotency completion
```

Use MariaDB if required by the shared idempotency/concurrency implementation.

---

# 99. Merge Rollback Test

Force a failure after at least one target-line mutation but before source retirement/idempotency completion.

Assert transaction rollback:

```text
target unchanged
source still ACTIVE
no success idempotency record
```

Use a safe test seam if repository patterns permit; no production failure hook.

---

# 100. CART-001 Closure Tests

Retain/verify:

```text
authenticated existing Cart
authenticated lazy create
guest lazy create
guest existing credential
invalid guest
retired guest
browser cookie
Flutter header
ambiguous credential
auth precedence
invalid bearer no downgrade
Staff personal Cart
Admin personal Cart
```

---

# 101. CART-001 Concurrency

Keep MariaDB proof:

```text
10 first-GET races
→ exactly one ACTIVE authenticated Cart
→ same Cart ID
```

---

# 102. CART-001 Representation

Verify exact Cart shape:

```text
id
items_count
items
subtotal
updated_at
```

No ownership leakage.

---

# 103. CART-002 Closure Tests

Verify:

```text
strict input
Product visibility
Category activity
IN_STOCK only
Variant required/valid/owned/active
quantity 1..100
duplicate merge
100 clamp
informational stock
multi-location
reserved-stock effect
current pricing
guest/auth behavior
no reservation
```

---

# 104. CART-002 Status Codes

Confirm:

```text
new line → 201
duplicate merged line → 200
```

as implemented/OpenAPI-defined.

---

# 105. CART-002 Concurrency

Real MariaDB:

```text
first-add race
repeat-add race
overflow race
```

must remain green.

---

# 106. CART-003 Closure Tests

Verify:

```text
quantity-only request
strict 1..100
zero rejected
101 rejected
same quantity no-op
current Product/Variant validation
current stock revalidation
masked item ownership
timestamps
```

---

# 107. CART-003 Concurrency

Same-line concurrent update remains serialized and valid.

---

# 108. CART-004 Closure Tests

Verify:

```text
owned item remove
cross-holder masked 404
unknown masked 404
stale item removable
out-of-stock removable
MADE_TO_ORDER stale line removable
last item leaves ACTIVE empty Cart
204 response
no stock effect
```

---

# 109. Validation Closure

Verify one authoritative:

```text
CartItemEligibility
```

still owns Product/Variant/quantity/stock eligibility.

No rule drift between:

```text
CART-001 projection
CART-002 admission
CART-003 mutation
```

---

# 110. Error Mapping Matrix

Verify exact established mapping:

```text
MADE_TO_ORDER / no sellable variant
→ 422 PRODUCT_NOT_PURCHASABLE

inactive / unpublished / soft-deleted / inactive Category / unknown Product
→ 422 PRODUCT_UNAVAILABLE

missing / inactive / wrong-parent Variant
→ 422 INVALID_PRODUCT_VARIANT

insufficient effective quantity
→ 422 INSUFFICIENT_STOCK
```

---

# 111. Projection Context

Those same invalid conditions on already-stored lines:

```text
must not fail CART-001
```

Instead:

```text
line retained
is_purchasable = false
```

---

# 112. Removal Context

Same invalid states:

```text
must not block CART-004
```

---

# 113. Quantity-Aware Purchasability

Critical regression:

```text
Cart qty = 5
available = 2
```

must remain:

```text
availability = available
is_purchasable = false
```

---

# 114. Exact Boundary

```text
qty 5
available 5
→ purchasable
```

---

# 115. Reserved Stock

Ensure:

```text
physical 10
reserved 6
available 4
qty 5
→ not purchasable
```

---

# 116. Multi-Location

Ensure aggregate:

```text
SUM(quantity - reserved_quantity)
```

for the same Variant.

---

# 117. Variant Isolation

Critical:

```text
Variant A = 0
Variant B = 10
Cart line A
```

must not borrow B's inventory.

---

# 118. Stock Drift

Verify Cart read reacts automatically to:

```text
stock decrease
stock increase
reservation increase
reservation release
inventory adjustment
```

without Cart mutation.

---

# 119. No N+1 Regression

Preserve Phase 6.7 guarantees:

```text
1-line Cart vs 10-line Cart
product_stocks query growth remains bounded
```

Empty Cart:

```text
0 inventory queries
```

where current test asserts it.

---

# 120. Resource Query Safety

Ensure normal production Cart presentation always passes precomputed validation results into:

```text
CartItemResource::withValidation()
```

or current equivalent.

Do not accidentally trigger lazy `CatalogAvailability` fallback per line.

---

# 121. Latent N+1 Review

The separate code review identified latent lazy-loading fallback risk in:

```text
CatalogAvailability::product()
CatalogAvailability::variant()
```

Current production callers are safe.

Phase 6.8 should add/retain tests ensuring Cart's normal production path does not depend on these fallbacks.

Do not redesign Group E merely to eliminate theoretical fallback capability unless a real regression is found.

---

# 122. Category Ancestor Query Note

Do not treat:

```text
Category::assertAcyclicUnderLock()
```

as a Group F N+1 blocker.

It is a bounded admin/write tree traversal, not a Cart collection read path.

---

# 123. InventoryAllocator Note

Do not classify one write per allocation/stock row as a read N+1.

Those writes are required reservation semantics.

No changes in Group F.

---

# 124. Guest Credential Security Matrix

Test all Cart routes for credential leakage.

Never expose:

```text
raw guest UUID
guest_token_digest
GUEST_CART_TOKEN_KEY
```

in JSON/log/error.

---

# 125. Browser Transport

For CART-001/CART-002 first guest creation:

```text
Set-Cookie
HttpOnly
Secure
SameSite=None
```

No `X-Guest-Cart-Id`.

---

# 126. Flutter Transport

Non-browser:

```text
X-Guest-Cart-Id
```

No guest cookie.

---

# 127. Existing Guest Credential

CART-001–004 must not unnecessarily rotate guest credential.

---

# 128. Merge Credential Retirement

CART-005 must not issue a replacement guest credential.

---

# 129. Authentication Precedence

For CART-001–004:

```text
valid authenticated identity wins
```

No automatic guest merge.

Only CART-005 explicitly consumes both identities.

---

# 130. Invalid Authentication Downgrade

Across all Cart routes:

```text
invalid Authorization header
```

must never become guest access.

---

# 131. IDOR Matrix

Test:

```text
Customer A item ID against Customer B Cart
Guest A item ID against Guest B Cart
Authenticated A targeting guest B item
```

where route surface allows attempts.

Expected:

```text
404 CART_ITEM_NOT_FOUND
```

for item mutation probes.

---

# 132. Cart ID Not Authority

No route should allow:

```text
cart_id
user_id
```

to override the current holder.

---

# 133. Opaque IDs

Verify:

```text
cart_...
item_...
prod_...
var_...
```

wire semantics where contracted.

Raw DB IDs must not leak.

---

# 134. Invalid Opaque IDs

Malformed item/Product/Variant IDs:

must produce canonical validation/not-found behavior.

Never be interpreted as raw integer IDs.

---

# 135. Server-Controlled Field Rejection

CART-002/003 reject:

```text
price
subtotal
total
availability
stock_indicator
reserved_quantity
user_id
cart_id
guest_token_digest
```

---

# 136. Pricing Closure

Verify:

```text
unit_price
line_total
subtotal
```

all use current server-side price.

No Cart monetary state persisted.

---

# 137. Price Drift

Change Variant price after item enters Cart.

Next Cart response must reflect current price.

Checkout will recalculate again later.

---

# 138. Stale Price Behavior

Preserve current stale/unpriceable rule:

```text
missing/inactive own Variant
→ unit_price = null
→ line_total = null
→ is_purchasable = false
```

Do not borrow sibling Variant price.

---

# 139. Subtotal

Only valid resolvable line totals contribute according to the accepted current resource behavior.

Test stale mixed Cart.

---

# 140. Items Count

Still:

```text
number of distinct CartItem lines
```

not total quantity.

---

# 141. Ordering

Cart items deterministic:

```text
created_at ASC
id ASC
```

or exact implemented accepted equivalent.

---

# 142. Cache

All Cart responses:

```text
private
no-cache
no-store
must-revalidate
```

No public caching.

---

# 143. GET Side Effects

Existing Cart GET must not mutate:

```text
Cart.updated_at
CartItem.updated_at
ProductStock
```

except lazy Cart creation when holder has none.

---

# 144. Cart Mutation Timestamps

Successful actual:

```text
add
quantity change
remove
merge
```

must update relevant Cart timestamp behavior.

Failed/no-op operations follow their established rules.

---

# 145. Inventory Boundary

Across ALL Group F tests confirm:

```text
ProductStock.quantity mutation: NONE
reserved_quantity mutation: NONE
inventory reservation: NONE
ProductStock write lock: NONE
```

except tests that deliberately mutate inventory externally to observe Cart revalidation.

---

# 146. Group F Must Never Reserve

This is a mandatory exit invariant.

Reservation begins only in Group G Checkout.

---

# 147. Checkout Boundary Test

Do not implement Checkout.

But confirm Group F leaves an authenticated Cart in a state Group G can consume:

```text
ACTIVE
holder-owned
current items
current projection
no reservation yet
```

---

# 148. Guest-to-Checkout Handoff

Closure should prove:

```text
guest builds Cart
→ user authenticates
→ CART-005 merges
→ authenticated ACTIVE Cart contains intent
→ guest source retired
```

STOP before actual checkout.

---

# 149. Full Cart Journey — Guest

Add an integration/feature journey:

```text
anonymous GET creates guest Cart
→ guest adds item A
→ guest adds item B
→ guest updates A
→ guest removes B
→ GET confirms state
```

No reservation anywhere.

---

# 150. Full Cart Journey — Authenticated

Test:

```text
authenticated GET
→ add
→ duplicate add
→ update
→ stock drift
→ GET revalidation
→ remove
```

---

# 151. Full Guest→Authenticated Journey

Test:

```text
guest Cart created
→ add items
→ authenticate user
→ user's existing/new target Cart
→ merge
→ target contains consolidated lines
→ guest source INACTIVE
→ retired token unusable
```

---

# 152. Target Existing Cart Journey

Important:

```text
guest items
+
authenticated target existing items
```

must consolidate correctly.

---

# 153. Target Lazy Creation Journey

Authenticated user with no Cart:

CART-005 can establish target active Cart via approved get/create mechanism and merge into it.

---

# 154. Idempotency Durability

Use existing durable persistence.

Do not satisfy idempotency only with:

```text
in-memory cache
process local state
```

---

# 155. No Idempotency on CART-001–004 Unless Already Contracted

Do not expand Idempotency-Key requirement to normal Cart mutations just because CART-005 requires it.

---

# 156. Rate Limits

Verify existing Cart rate-limit behavior remains attached.

CART-005 should use an appropriate authenticated mutation limiter.

Prefer existing limiter rather than inventing an arbitrary new threshold if contract is silent.

---

# 157. 429

Every limited Cart route:

```text
429
Retry-After
```

---

# 158. Error Envelope

All Cart errors:

```text
{
  "errors": [...],
  "meta": {
    "request_id": ...
  }
}
```

No envelope drift.

---

# 159. No Sensitive Error Details

No:

```text
SQL
table name
class name
stack trace
guest digest
other owner ID
inventory internals
```

---

# 160. OpenAPI Contract Tests

Compare actual routes/requests/responses against current:

```text
docs/api/openapi.yaml
```

At minimum check:

```text
CART-001
CART-002
CART-003
CART-004
CART-005
Cart
CartItem
AddCartItemRequest
UpdateCartItemRequest
GuestCartId
GuestCartCookie
IdempotencyKey
```

---

# 161. OpenAPI CART-005 Reconciliation

If CART-005 browser-cookie support is required by the accepted security decision but missing in OpenAPI:

fix that documentation drift.

Do not change the secure transport design to fit the omission.

---

# 162. No New Cart Endpoints

After Phase 6.8 expected Cart routes remain exactly:

```text
GET    /api/v1/me/cart
POST   /api/v1/me/cart/items
PATCH  /api/v1/me/cart/items/{item}
DELETE /api/v1/me/cart/items/{item}
POST   /api/v1/me/cart/merge
```

---

# 163. CART-005 Must No Longer Be 501

This is a Group F closure requirement.

---

# 164. Schema Expectations

Expected:

```text
carts schema changes: NONE
cart_items schema changes: NONE
```

---

# 165. Shared Idempotency Schema

If existing shared durable idempotency infrastructure already supports CART-005:

reuse it with no schema change.

Only add a migration if a concrete reusable-infrastructure limitation prevents the frozen merge contract.

Do not create Cart-specific idempotency schema casually.

---

# 166. Dependencies

Expected:

```text
NONE
```

---

# 167. Frontend

Expected:

```text
NONE
```

Do not implement Cart UI or login/merge frontend flow.

---

# 168. Code Quality

Maintain:

```text
cognitive complexity <= 15
<= 3 returns where practical
thin CartController
small MergeGuestCart service/action
reuse CartStockRevalidator
reuse CartItemEligibility
reuse GuestCartTransport
reuse GetOrCreateActiveCart
reuse shared idempotency
reuse ConcurrentTransaction
```

---

# 169. Suggested CART-005 Service

A focused component such as:

```text
MergeGuestCart
```

is appropriate.

Responsibilities:

```text
validate source guest Cart
resolve target active Cart
transactionally consolidate items
retire source
coordinate idempotency
return target
```

Do not put merge business logic directly into controller.

---

# 170. Do Not Create Giant Cart Service

Do not combine:

```text
read
add
update
remove
merge
checkout
inventory
```

into one monolithic class.

---

# 171. Tests — Existing Focused Suites

Rerun all existing Group F suites, including actual equivalents of:

```text
CartSchemaTest
CartReadApiTest
CartAddItemApiTest
CartUpdateItemApiTest
CartRemoveItemApiTest
CartPurchasabilityApiTest
CartStockRevalidationTest
CartStockRevalidationResultTest
CartItemInvalidReasonTest
CartConcurrencyMysqlTest
CartMutationConcurrencyMysqlTest
ApiRoutingSmokeTest
```

---

# 172. Add Merge Test Suite

Create a focused suite such as:

```text
CartMergeApiTest
```

and a MariaDB concurrency suite only where real database concurrency is required.

---

# 173. CART-005 MariaDB Concurrency Gate

Real MariaDB should prove at least:

```text
concurrent same-key merge = exactly once
concurrent different-key merge of same guest Cart ≠ duplicate intent
overlapping target-line merge does not violate uniqueness
```

Use disposable guarded DB.

---

# 174. Disposable DB Safety

Reuse existing guard:

```text
APP_ENV != production
approved disposable DB name
```

Never run destructive concurrency harness against normal dev/staging/prod DB.

---

# 175. Do Not Overclaim SQLite

SQLite proves:

```text
API behavior
validation
authorization
merge semantics
idempotency semantics
projection
rollback logic
```

It does not prove InnoDB row-lock/deadlock behavior.

---

# 176. MariaDB Proves

MariaDB targeted integration proves:

```text
active-cart uniqueness
CartItem uniqueness under races
transactional merge serialization
same-key exactly-once behavior
```

where implemented through real DB concurrency.

---

# 177. Full Canonical Suite

After focused fixes/tests:

```bash
php artisan test
```

must remain green apart from explicitly accepted existing skips.

---

# 178. Pint

Run:

```bash
vendor/bin/pint --test
```

Must pass.

---

# 179. PHPStan

Run:

```bash
vendor/bin/phpstan analyse
```

Expected:

```text
0 errors
```

Do not add baseline suppressions merely to close the phase.

---

# 180. Composer Audit

Run:

```bash
composer audit
```

Report advisories separately.

No unresolved blocker accepted silently.

---

# 181. Diff Check

Run:

```bash
git diff --check
```

Must be clean.

---

# 182. Routes

Run:

```bash
php artisan route:list
```

Confirm exactly five Cart route operations and CART-005 points to real implementation, not `notImplemented()`.

---

# 183. N+1 Final Gate

Repeat the query-bearing path review for any code changed during CART-005 implementation.

Especially ensure merge response uses:

```text
CartController::present()
→ CartStockRevalidator
→ precomputed validation
→ Resources
```

rather than per-item queries.

---

# 184. No Performance Rewrite

If query behavior stays bounded:

do not expand 6.8 into a general optimization project.

---

# 185. Rule for Defects Found During Closure

If a test finds a genuine defect:

```text
fix the smallest underlying defect
add/retain regression test
rerun relevant focused tests
rerun full suite
```

Do not weaken assertions to make tests green.

---

# 186. Contract Drift Rule

If implementation and updated OpenAPI disagree:

determine which side conflicts with an already accepted/frozen ADR.

Prefer the accepted frozen V1 decision.

Make the smallest documentation/runtime correction.

Record it.

---

# 187. Do Not Redesign Frozen Behavior

Do not use Phase 6.8 to revisit:

```text
duplicate add clamping
guest token format
private cache
quantity range
MADE_TO_ORDER exclusion
stale-line retention
stock formula
price authority
opaque IDs
```

unless an actual contradiction is discovered.

---

# 188. Group F Status Must Be Granular

Report each:

```text
6.1
6.2
6.3–6.5
6.6
6.7
6.8
```

separately.

---

# 189. Group F PASS Rule

Group F may be marked:

```text
PASS / CLOSED
```

only if:

```text
CART-001 PASS
CART-002 PASS
CART-003 PASS
CART-004 PASS
CART-005 PASS

all mandatory SQLite semantic tests PASS
all required MariaDB concurrency tests PASS
Pint PASS
PHPStan PASS
contract drift resolved
no blocking security defect
```

---

# 190. Group F BLOCKED Rule

Return:

```text
implementation complete; verification BLOCKED
```

if a mandatory DB-specific/concurrency gate cannot actually be run.

Do not claim PASS because an integration test was skipped.

---

# 191. Existing Accepted Skip

A pre-existing unrelated MySQL-disabled JIT harness skip may remain documented if it is outside Group F and already accepted.

Do not confuse that with skipping mandatory CART-005 concurrency verification.

---

# 192. Group F Exit Proof

Final closure report should demonstrate:

```text
anonymous guest
→ create/get Cart
→ add/update/remove
→ current stock/pricing projection
→ authenticate
→ merge guest Cart
→ authenticated Cart contains intent
```

All through the API.

That satisfies the Group F exit condition.

---

# 193. Checkout Handoff

After Group F closes:

```text
authenticated user
+
ACTIVE own Cart
+
Cart items
+
current live projection
+
zero reservations
```

is the expected starting state for Group G.

---

# 194. No Checkout Implementation

STOP before:

```text
POST /checkout
```

logic.

Group G begins with Phase 7.1.

---

# 195. Completion Report

Return:

## Phase 6.8 status

```text
PASS
```

or:

```text
BLOCKED
```

## CART-001

Report create/get coverage.

## CART-002

Report add coverage.

## CART-003

Report update coverage.

## CART-004

Report remove coverage.

## CART-005

Report:

```text
authentication
guest credential
browser cookie / Flutter header
target Cart resolution
line consolidation
quantity clamp
stale-line preservation
source retirement
idempotency
concurrency
```

## Guest lifecycle

Report:

```text
guest Cart created
guest Cart mutated
guest Cart merged
guest credential retired
```

## Validation

Report central `CartItemEligibility` status.

## Stock revalidation

Report `CartStockRevalidator` status and N+1 result.

## Pricing

Confirm live/current price authority.

## Inventory

Explicitly report:

```text
Cart inventory mutations: NONE
reserved_quantity mutations: NONE
Cart-stage reservation: NONE
ProductStock write locks: NONE
```

## Security

Report:

```text
IDOR masking
invalid auth downgrade protection
guest credential leakage
browser/Flutter transport
opaque IDs
private cache
```

## Idempotency

Report CART-005:

```text
same-key replay
different-source conflict
concurrent same-key behavior
durable store reuse
```

## OpenAPI

Report drift found/fixed, especially CART-005 cookie/header transport.

## Schema

Report actual result.

Expected:

```text
NONE
```

except any proven shared-idempotency infrastructure gap.

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

## SQLite

State exactly what it proves.

## MariaDB

State exactly what it proves.

## Tests

Provide:

```text
focused Cart test totals
canonical suite totals
assertions
skips
```

## N+1

Report query-growth status.

## Quality

Report:

```text
Pint
PHPStan
Composer audit
git diff --check
route:list
```

## Individual Phase Status

```text
6.1      PASS
6.2      PASS
6.3–6.5  PASS
6.6      PASS
6.7      PASS
6.8      PASS/BLOCKED
```

## Group F

Return exactly one:

```text
GROUP F — PASS / CLOSED
```

or:

```text
GROUP F — BLOCKED
```

with concrete blockers.

## Group G readiness

Only if Group F passes:

```text
Phase 7.1 — Checkout requirements: READY
```

---

# 196. Definition of Done

Phase 6.8 is complete when:

* all frozen CART-001..005 operations are implemented;
* CART-005 no longer returns 501;
* guest merge requires authenticated principal;
* guest token alone cannot merge;
* browser guest cookie can participate securely in merge;
* Flutter header can participate securely in merge;
* ambiguous guest credential transport is rejected;
* target Cart is authenticated self-context only;
* target active Cart is created/reused safely;
* guest source must be ACTIVE for a new merge;
* source becomes INACTIVE atomically on success;
* guest credential is permanently retired server-side;
* source Cart is not hard-deleted;
* source lines are consolidated into target;
* overlapping lines merge by Product+Variant identity;
* merged quantity never exceeds 100;
* stale guest items are preserved rather than silently dropped;
* merge performs no stock reservation;
* merge performs no ProductStock mutation;
* merge uses live Cart projection after success;
* CART-005 requires Idempotency-Key;
* same-key same-source replay does not merge twice;
* same-key different-source conflicts;
* concurrent same-key merge produces one business effect;
* concurrent duplicate source merge cannot duplicate target intent;
* CART-001 lazy creation race remains safe;
* CART-002 duplicate/first-add races remain safe;
* CART-003 update race remains safe;
* CART-004 ownership masking remains correct;
* CartItemEligibility remains the single validation authority;
* CartStockRevalidator remains the Cart-wide stock authority;
* line-level purchasability remains quantity-aware;
* multi-location stock remains Variant-specific;
* stale lines remain visible/removable;
* live pricing remains server-authoritative;
* Cart contains no persisted pricing;
* Cart contains no persisted availability;
* Cart contains no inventory reservation state;
* no ordinary Cart operation changes reserved_quantity;
* no normal Cart revalidation locks ProductStock;
* guest credential never leaks;
* customer-private item probes are 404-masked;
* Cart responses remain private/no-store;
* opaque IDs remain enforced;
* normal Cart read path remains N+1-safe;
* all focused tests pass;
* required MariaDB concurrency gates pass;
* full canonical suite passes except accepted unrelated skips;
* PHPStan has zero errors;
* Pint passes;
* Composer audit has no blocking advisory;
* git diff check is clean;
* route list matches frozen Cart surface;
* OpenAPI matches runtime behavior;
* Group F exit condition is demonstrably satisfied.

---

# 197. Out of Scope

Do not implement:

```text
checkout
Order creation
delivery address workflow
delivery fee calculation
inventory reservation at checkout
payment
notifications
frontend Cart UI
frontend auth/merge UI
wishlist
save-for-later
coupons
promotions
```

---

# 198. STOP Condition

STOP when Group F can prove this complete API lifecycle:

```text
guest
→ CART-001 create/get
→ CART-002 add
→ CART-003 update
→ CART-004 remove
→ live validation/revalidation
→ authenticate
→ CART-005 merge
→ authenticated ACTIVE Cart
→ guest Cart retired
```

with:

```text
no IDOR
no guest-token leakage
no duplicate merge
no quantity overflow
no N+1 regression
no inventory reservation
```

Then report the final Group F status.

Do not continue automatically to Group G.

DO NOT COMMIT OR PUSH.

The project owner handles all Git operations.
