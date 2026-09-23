# Phase 6.2 — Create / Get Cart

## Purpose

Implement:

```text
CART-001
GET /api/v1/me/cart
```

as the canonical holder-scoped Cart retrieval endpoint for both:

```text
authenticated users
guest holders
```

with lazy empty-Cart creation where no active Cart exists.

This phase establishes the foundational Cart access path that Phases 6.3–6.7 will reuse.

Do not implement add/update/remove/merge behavior yet.

---

# 1. Canonical Endpoint

Implement exactly:

```http
GET /api/v1/me/cart
```

Do not add:

```text
GET /api/v1/carts/{cart}
GET /api/v1/guest/cart
POST /api/v1/carts
GET /api/v1/my-cart
```

The Cart is always resolved from holder context.

---

# 2. CART-001 Is Create-or-Get

CART-001 has lazy create/get semantics.

It does not require a separate Cart creation endpoint.

The canonical behavior is:

```text
resolve holder
→ find holder's ACTIVE Cart
→ if found, return it
→ if absent, create empty ACTIVE Cart
→ return it
```

---

# 3. Authenticated Caller

For a valid Clerk-authenticated Laravel user:

```text
find ACTIVE Cart where user_id = authenticated local user ID
```

If found:

```text
return existing Cart
```

If not found:

```text
create new ACTIVE user-owned Cart
```

Ownership:

```text
user_id = authenticated local user ID
guest_token_digest = null
status = ACTIVE
```

---

# 4. Authenticated Identity Is Server-Derived

Never accept:

```text
user_id
cart_id
owner_id
```

from request input.

Authenticated Cart ownership comes only from:

```text
verified Clerk session
→ local Laravel User
```

---

# 5. One Active Cart Invariant

Phase 6.2 must preserve:

```text
at most one ACTIVE Cart per authenticated user
```

The existing database `active_user_guard` remains the defensive constraint.

---

# 6. Concurrent Lazy Creation

Two simultaneous first-time CART-001 requests for the same authenticated user must not create two active Carts.

Example:

```text
Request A → no cart
Request B → no cart
```

must end with:

```text
exactly one ACTIVE cart
```

---

# 7. Do Not Rely Only on "Find Then Insert"

Naive:

```text
SELECT active cart
if none:
    INSERT cart
```

may race.

Use an implementation that safely handles the unique active-cart constraint.

Examples include:

```text
transaction + retry after unique violation
```

or another existing repository pattern.

Do not weaken/remove the DB uniqueness guard.

---

# 8. Authenticated Race Outcome

If concurrent creation races:

both requests should ultimately resolve and return the same single ACTIVE Cart.

Do not expose an internal unique-constraint exception.

---

# 9. Guest Caller

Anonymous Cart access is supported.

Guest identity comes from the approved guest Cart credential.

A guest request may be:

```text
browser cookie holder
Flutter/header holder
first-time anonymous holder with no credential
```

---

# 10. Existing Guest Credential

If an anonymous request provides a valid guest credential:

```text
digest raw credential
→ lookup ACTIVE Cart by guest_token_digest
```

If found:

return that Cart.

Do not create another Cart.

---

# 11. Guest Digest Lookup

Use the existing:

```text
GuestCartCredential
```

HMAC mechanism.

Conceptually:

```text
digest = HMAC-SHA-256(raw_token, GUEST_CART_TOKEN_KEY)
```

Search by persisted digest.

Never search by raw token.

---

# 12. First-Time Guest

If the request is anonymous and provides no existing guest credential:

create:

```text
new raw UUIDv4 guest credential
new digest
new ACTIVE guest Cart
```

Persist only:

```text
guest_token_digest
```

Return the Cart and issue the raw credential through the correct client transport.

---

# 13. Empty Guest Cart Is Valid

A newly created guest Cart is:

```text
ACTIVE
items = []
items_count = 0
subtotal = 0 TZS
```

It is not a 404.

---

# 14. Missing Cart Is Not an Error

For a valid holder context:

```text
no active Cart
```

means:

```text
create one
```

not:

```text
CART_NOT_FOUND
```

CART-001 is intentionally create/get.

---

# 15. Invalid Guest Credential

A malformed, unknown, or retired guest credential must not be treated as authority.

Do not bind it to another Cart.

Do not expose whether a digest exists historically.

---

# 16. Invalid / Retired Guest Credential Behavior

Follow the accepted Phase 6.1 rule:

```text
invalid or retired credential
→ no ACTIVE guest cart
```

Where a provided credential is expected to authorize an existing guest Cart:

return:

```text
401 AUTHENTICATION_REQUIRED
```

rather than silently treating a bad credential as a valid existing holder.

---

# 17. Do Not Auto-Recycle Retired Token

A guest Cart retired through CART-005 later becomes:

```text
INACTIVE
```

Its credential must not become valid again.

CART-001 lookup for guest credential must therefore require:

```text
status = ACTIVE
```

---

# 18. Do Not Create New Cart Under Invalid Supplied Credential

If a caller explicitly sends a guest credential that is invalid/retired:

do not silently create a fresh Cart under a new token in the same request.

That can hide lost/expired credential state and complicate client recovery.

Return the canonical auth failure.

---

# 19. Anonymous Request With No Credential

This is different from an invalid supplied credential.

No credential:

```text
new guest holder
→ create empty ACTIVE guest Cart
→ issue new credential
```

Invalid supplied credential:

```text
401
```

Keep these distinct.

---

# 20. Authenticated + Guest Credential

If request is authenticated and also carries a guest credential:

for CART-001:

```text
authenticated principal wins
```

Return the authenticated user's ACTIVE Cart.

Do not automatically merge.

---

# 21. Merge Is Explicit

Guest Cart handoff happens only through:

```text
CART-005
POST /api/v1/me/cart/merge
```

Do not trigger merge as a side effect of:

```text
GET /me/cart
login
token verification
```

---

# 22. Ignore Guest Credential for Authenticated CART-001 Ownership

Authenticated CART-001 must not switch to the guest Cart merely because a guest credential is present.

Holder authority is:

```text
authenticated principal
```

for this endpoint.

---

# 23. Do Not Retire Guest Token Yet

If an authenticated CART-001 request includes a guest credential but does not call CART-005:

do not:

```text
invalidate token
mark guest Cart inactive
merge items
delete guest Cart
```

No mutation beyond create/get of the authenticated Cart.

---

# 24. Staff/Admin Personal Commerce

Phase 6.1 deferred this policy to 6.2.

Project-level rule:

```text
Staff/Admin may purchase for themselves
```

Therefore self-owned Cart access must not require:

```text
role == CUSTOMER
```

if the authenticated local User is otherwise permitted to use personal commerce.

---

# 25. Staff/Admin Must Not Gain Operational Cart Access

Support:

```text
STAFF own personal Cart
ADMIN own personal Cart
```

only.

Do not support:

```text
STAFF → another customer's Cart
ADMIN → another customer's Cart
```

through CART-001.

---

# 26. Cart Ownership Is Self-Context

Authenticated CART-001 always resolves:

```text
current local user
```

Never another user's Cart.

Role does not change this.

---

# 27. Suspended / Inactive Account Policy

Reuse the existing Group D account-state authorization boundary.

Do not create a Cart-specific alternate account-state policy.

If current middleware blocks commerce for an ineligible local account:

CART-001 should respect that existing rule.

---

# 28. Guest Route Authentication Middleware

Current Cart routes were previously auth-only.

CART-001 must now support:

```text
optional Clerk authentication
```

rather than mandatory authentication.

Use the existing optional-auth middleware pattern if available.

Do not duplicate Clerk token parsing.

---

# 29. Optional Authentication Semantics

If:

```text
valid Bearer token present
```

resolve authenticated user.

If:

```text
no Bearer token
```

continue to guest holder resolution.

If:

```text
Bearer token present but invalid
```

do not silently downgrade to guest.

Return the canonical authentication error.

---

# 30. No Auth Downgrade

This is security-critical.

Request:

```text
Authorization: Bearer invalid-token
```

must not become:

```text
anonymous guest request
```

Invalid attempted authentication remains authentication failure.

---

# 31. Holder Resolver

Introduce a focused reusable holder-resolution abstraction.

Example concept:

```text
CartHolderResolver
```

Responsibilities:

```text
authenticated holder detection
guest credential resolution
client transport detection
holder type
```

Do not make it responsible for:

```text
pricing
availability
item mutation
merge
checkout
```

---

# 32. Suggested Holder Result

Conceptually:

```text
AuthenticatedCartHolder(User $user)
```

or:

```text
GuestCartHolder(raw credential / digest context)
```

Use repository conventions.

Do not expose raw guest credential beyond the narrow layer that needs to issue transport.

---

# 33. Cart Finder / Creator

Use a separate focused service/action such as:

```text
ResolveActiveCart
GetOrCreateActiveCart
```

Responsibilities:

```text
find active Cart
create when absent
handle creation race
return authoritative Cart
```

---

# 34. Avoid Giant Cart Service

Do not create one class that already contains:

```text
create
get
add
update
remove
merge
pricing
availability
checkout
```

Keep Phase 6.2 responsibilities narrow.

---

# 35. Guest Cart Creation

When creating guest Cart:

```text
user_id = null
guest_token_digest = digest(new raw UUIDv4)
status = ACTIVE
```

Persist no raw token.

---

# 36. Authenticated Cart Creation

When creating user Cart:

```text
user_id = current local user id
guest_token_digest = null
status = ACTIVE
```

---

# 37. No Ownership Mutation

CART-001 must not convert:

```text
guest Cart → customer Cart
```

or:

```text
customer Cart → guest Cart
```

Ownership transfer belongs only to explicit merge/handoff logic.

---

# 38. No Cart Status Transition Except Required Creation

CART-001 may create:

```text
ACTIVE
```

Cart.

It must not mark existing carts:

```text
INACTIVE
```

except if a narrowly required race-recovery mechanism uses an already-approved domain rule.

Normal status transitions belong to later workflows.

---

# 39. Opaque Cart IDs

Phase 6.1 identified opaque IDs as a Phase 6.2 implementation gap.

Cart response IDs must follow:

```text
cart_...
```

rather than exposing raw DB integer IDs.

---

# 40. Opaque CartItem IDs

Even though item mutation is not implemented yet, Cart representation contains CartItem IDs.

They must follow:

```text
item_...
```

not raw database IDs.

---

# 41. Reuse Existing Opaque-ID Infrastructure

Inspect repository for the existing public ID/opaque ID mechanism used by:

```text
products
variants
inventory
orders
```

Reuse it.

Do not invent a second incompatible encoding library.

---

# 42. Stable IDs

Opaque Cart/CartItem IDs must be:

```text
stable
non-sequential externally where existing policy requires
non-guess-authority
```

But remember:

```text
ID is not authorization
```

Even opaque IDs must still undergo holder checks later.

---

# 43. Do Not Add Public ID Columns Without Need

If the existing application already derives opaque IDs from DB IDs through a codec:

reuse that.

Do not add:

```text
public_id
uuid
external_id
```

columns merely because the wire contract uses prefixes.

---

# 44. Cart Representation

Return:

```text
{
  "data": {
    "id": "cart_...",
    "items_count": 0,
    "items": [],
    "subtotal": {
      "amount": 0,
      "currency": "TZS"
    },
    "updated_at": "..."
  }
}
```

for a new empty Cart.

---

# 45. Successful Response Envelope

Use canonical:

```text
data
```

single-resource envelope.

Do not use:

```text
cart
result
payload
```

at the top level.

---

# 46. No Pagination

Cart item list is embedded.

Do not return:

```text
meta.pagination
```

for CART-001.

---

# 47. Empty Items

Empty Cart:

```text
items: []
```

never:

```text
items: null
items omitted
404
```

---

# 48. Items Count

For CART-001:

```text
items_count
=
number of distinct CartItem rows
```

not sum of quantities.

---

# 49. Empty Subtotal

Use:

```json
{
  "amount": 0,
  "currency": "TZS"
}
```

not:

```text
null
0
"TZS 0"
```

---

# 50. Current Cart Projection

For an existing non-empty Cart, CART-001 must eventually return current:

```text
Product/Variant display data
unit_price
line_total
availability
stock_indicator
is_purchasable
```

according to the frozen representation.

However this phase must be careful not to implement Phase 6.6/6.7 prematurely.

---

# 51. Phase 6.2 Projection Boundary

Implement only the amount of Cart projection required to make CART-001 conform to its response contract.

Reuse existing Group E:

```text
price
availability
stock_indicator
```

resolvers.

Do not build new stock validation algorithms.

---

# 52. Existing Cart Items Must Be Renderable

CART-001 cannot assume every Cart is empty.

Therefore existing CartItems must serialize correctly.

Use current Product/Variant relations and Group E projection.

---

# 53. Stale Cart Items

CART-001 must not silently drop existing stale lines.

If Product/Variant has become stale:

preserve line in `items`.

Detailed stale-validation hardening remains Phase 6.6/6.7, but Phase 6.2 must not make stale items disappear due to using the public Product scope.

---

# 54. Do Not Query Through Public Product Scope Only

Public scope excludes inactive/unpublished Products.

Cart read must still be able to resolve stored lines and represent them as stale.

Use context-appropriate relationships.

---

# 55. Category Visibility in Purchasability

Phase 6.1 recorded `is_purchasable` as requiring:

```text
Product active
Product published
Product not deleted
Category active
product_type = IN_STOCK
active parent-owned Variant
live availability available
```

Do not weaken this definition.

---

# 56. MADE_TO_ORDER Existing Line

Although future admission rejects MADE_TO_ORDER, historical/test data might contain one.

CART-001 must not crash.

Return:

```text
is_purchasable = false
```

according to current Cart semantics.

Do not reserve or mutate anything.

---

# 57. Price Projection

Use current active-Variant catalog pricing.

Do not persist:

```text
unit_price
line_total
subtotal
```

during CART-001.

---

# 58. Cart GET Is Read-Only Except Lazy Creation

For existing Cart:

GET must not update:

```text
Cart.updated_at
CartItem.updated_at
prices
availability fields
inventory
```

because current display data changed.

---

# 59. Creating a New Cart Is the Only Normal CART-001 Mutation

For an existing holder with existing ACTIVE Cart:

GET remains observational.

---

# 60. No Inventory Reservation

CART-001 must not modify:

```text
ProductStock.quantity
ProductStock.reserved_quantity
```

under any circumstance.

---

# 61. No Product Locks

Do not acquire:

```text
lockForUpdate()
```

on ProductStock merely to display Cart.

Final stock locking remains Group G checkout.

---

# 62. Guest Credential Transport

Implement the accepted split exactly.

Browser:

```text
Set-Cookie: guest_cart_id=<raw UUIDv4>
HttpOnly
Secure
SameSite=None
```

Non-browser:

```text
X-Guest-Cart-Id: <raw UUIDv4>
```

Never both.

---

# 63. Browser Must Never Receive Guest Token Header

For browser requests:

do not emit:

```text
X-Guest-Cart-Id
```

even if convenient for frontend JavaScript.

That would defeat HttpOnly protection.

---

# 64. Flutter Must Not Receive Guest Cookie

For non-browser/Flutter transport:

do not emit:

```text
Set-Cookie: guest_cart_id=...
```

Return only the header.

---

# 65. Guest Token Input

Browser:

read guest credential from approved cookie.

Flutter/non-browser:

read guest credential from:

```text
X-Guest-Cart-Id
```

Do not accept it in JSON body.

---

# 66. No Query Token

Do not accept:

```text
?guest_cart_id=...
```

URLs may be logged/bookmarked.

---

# 67. No Guest Token in Response Body

Do not serialize:

```json
{
  "guest_cart_id": "..."
}
```

in Cart JSON.

Credential transport stays cookie/header only.

---

# 68. Client-Type Detection

Inspect the existing:

```text
api-conventions.md §22.6
```

and current middleware/request metadata strategy.

Reuse the frozen client-classification mechanism if already implemented.

Do not invent a new public query parameter such as:

```text
?client=flutter
```

unless already contracted.

---

# 69. Browser/Non-Browser Classification Must Be Centralized

Do not repeat transport-selection logic in every Cart controller.

Use a focused helper/middleware/service.

Future CART-002..005 must use the same rules.

---

# 70. Mutually Exclusive Guest Transport

Reject or deterministically handle requests that improperly provide both:

```text
guest_cart_id cookie
X-Guest-Cart-Id header
```

according to existing security conventions.

Preferred behavior if contract defines mutual exclusion:

```text
reject ambiguous credential input
```

rather than guessing.

---

# 71. Never Log Guest Credential

Ensure application/request logging does not record:

```text
X-Guest-Cart-Id
guest_cart_id cookie
raw token
```

---

# 72. Redaction

If the current HTTP logging layer has sensitive-header/cookie redaction:

add these names there if not already covered.

Do not introduce a second logging system.

---

# 73. Cookie Lifetime

Use the exact Cart guest credential cookie lifetime from current API conventions/configuration.

If current contract does not define one:

do not invent business expiry semantics.

A browser cookie lifetime is transport persistence, not Cart business expiration.

---

# 74. Secure Cookie

Production behavior must use:

```text
Secure
HttpOnly
SameSite=None
```

as frozen.

Do not weaken `Secure` merely for local convenience in production config.

---

# 75. Local Development

If local HTTP development needs cookie accommodation:

use environment-aware framework configuration without changing production contract.

Do not hardcode insecure production cookies.

---

# 76. CORS Dependency

Browser cross-origin credentialed requests require:

```text
credentials: include
Access-Control-Allow-Credentials: true
strict allowed Origin
```

Verify existing CORS configuration supports this.

Do not broaden:

```text
Access-Control-Allow-Origin: *
```

with credentials.

---

# 77. Do Not Build Frontend

Only verify the backend contract.

Do not modify Next.js or Flutter clients.

---

# 78. Cart Resource

Create/use an explicit:

```text
CartResource
```

Do not return:

```php
$cart->toArray()
```

---

# 79. CartItem Resource

Use explicit:

```text
CartItemResource
```

or equivalent projection object.

No mass serialization.

---

# 80. Cart Resource Sensitive Fields

Never expose:

```text
user_id
guest_token_digest
active_user_guard
status
internal DB IDs
```

unless status is explicitly part of frozen Cart representation.

Current Cart response does not require those fields.

---

# 81. Item Sensitive Fields

Never expose:

```text
cart_id
raw ProductStock data
cost_price
warehouse_location
reserved_quantity
internal FK fields
```

outside the contracted representation.

---

# 82. Current Item Ordering

Return CartItems in deterministic insertion order.

Preferred existing rule:

```text
created_at ASC
id ASC
```

unless current contract/relation already specifies equivalent ordering.

Do not rely on implicit database order.

---

# 83. `updated_at`

Return Cart's own mutation timestamp.

Do not calculate it from:

```text
Product price change
inventory change
```

---

# 84. Parent Touch Behavior

CART-001 itself should not touch the Cart.

Later mutations must update Cart.updated_at.

Do not implement those mutation flows now.

---

# 85. Private Cache Policy

Every CART-001 response must be:

```text
Cache-Control:
private,
no-cache,
no-store,
must-revalidate
```

or exact shared middleware equivalent.

---

# 86. Never Public Cache Cart

This applies to:

```text
authenticated Cart
guest Cart
```

Both are holder-private.

---

# 87. Vary / Credential Safety

Review whether responses need appropriate:

```text
Vary
```

headers under existing CORS/caching middleware.

Do not create a shared cache identity based on guest token.

---

# 88. Authentication Middleware Pipeline

Desired conceptual pipeline:

```text
request
→ optional Clerk authentication
→ if auth attempted and invalid: 401
→ determine authenticated vs guest holder
→ resolve/create ACTIVE Cart
→ project Cart
→ set private cache
→ issue guest credential transport if newly created
→ response
```

---

# 89. Do Not Hit Clerk for Pure Guest Request

If no Authorization header is present:

do not make unnecessary Clerk Backend API calls.

Proceed through guest resolution.

---

# 90. Authenticated Existing Local User

Use the already-proven Group D local-user resolution.

Do not duplicate JIT provisioning in Cart code.

---

# 91. JIT Provisioning

If the existing authentication middleware provisions the local User on first valid authenticated request:

CART-001 should naturally work after that.

Do not add a Cart-specific provisioner.

---

# 92. Authenticated Cart Creation Race

Add focused test with two separate requests/processes or simulated DB conflict where practical.

Expected:

```text
one ACTIVE cart
same Cart returned
```

---

# 93. Guest Cart Creation Race

A first guest request has no credential, so two independent requests are two independent guest holders.

They may each receive separate guest Carts.

This is correct.

Do not deduplicate anonymous users by IP/browser fingerprint.

---

# 94. Guest Existing Cart Race

Two requests carrying the same valid guest token must resolve the same Cart.

GET does not create a second one.

---

# 95. No IP Ownership

Never bind guest Cart ownership to:

```text
IP address
User-Agent
device fingerprint
```

Guest token alone is the guest holder credential.

---

# 96. Opaque Cart Route Isolation

CART-001 has no Cart ID parameter.

Therefore client cannot select another Cart through URL manipulation.

Preserve self-context architecture.

---

# 97. Cart Not Found Code

CART_NOT_FOUND remains useful in later holder-specific failure cases.

But CART-001 valid-holder/no-cart should lazily create instead of returning it.

---

# 98. Empty Authenticated Cart

Authenticated user with no existing Cart receives:

```text
200
empty active Cart
```

not 201.

CART-001 is a GET create/get endpoint whose wire response remains the Cart resource.

Do not introduce a separate creation status solely because lazy persistence occurred.

---

# 99. Empty Guest Cart

Likewise:

```text
200
empty Cart
+
credential transport
```

unless the frozen API contract explicitly says otherwise.

Use current OpenAPI as final authority.

---

# 100. GET Idempotency

Repeated CART-001 for the same holder:

```text
same ACTIVE Cart
same Cart ID
```

No new Cart on each GET.

---

# 101. Guest Credential Re-Issuance

For an existing valid guest Cart:

do not rotate raw credential automatically unless current security contract says so.

Use the credential supplied by holder.

---

# 102. New Guest Credential Response

Only newly created anonymous guest Cart requires new credential issuance.

---

# 103. Authenticated Response Must Not Issue Guest Credential

For authenticated CART-001:

do not emit:

```text
guest_cart_id cookie
X-Guest-Cart-Id
```

even if a guest credential was also sent.

---

# 104. Product/Variant Loading

Avoid N+1.

For a non-empty Cart preload only data needed for:

```text
CartItem representation
price
availability
purchasability
```

---

# 105. Inventory Query Efficiency

Do not issue one ProductStock query per line where the Phase 5.7 resolver already supports aggregate loading.

Reuse existing Group E query patterns.

---

# 106. No Full Catalog Query

Do not resolve Cart items by running:

```text
CAT-001 search/filter
```

for each Product.

Use direct relationships/domain services.

---

# 107. Stale Soft-Deleted Products

Because Products may be soft-deleted:

Cart projection may need:

```text
withTrashed()
```

or equivalent context-specific relation.

Use it only within the holder-private Cart projection where needed.

Do not alter public catalog scope.

---

# 108. Inactive Variant Loading

Cart projection may need inactive Variant records to render stale lines.

Do not globally remove active scopes from public Variant APIs.

Use context-specific lookup.

---

# 109. Item Product Summary

Use the Cart contract's embedded Product summary.

Do not assume it is byte-for-byte identical to CAT-001 ProductSummary if the frozen Cart contract defines a subset.

---

# 110. Item Variant Summary

Likewise use the Cart-specific Variant representation.

---

# 111. Pricing Failure

If an existing stale CartItem cannot determine a valid current price:

follow the frozen Cart representation/error rules.

Do not silently invent:

```text
amount = 0
```

unless current contract explicitly requires it.

---

# 112. Do Not Break Entire Cart for Normal Staleness

Normal cases such as:

```text
out of stock
inactive
unpublished
```

should result in stale line signaling, not entire CART-001 failure.

---

# 113. Truly Corrupt Cart Data

If database invariants are broken unexpectedly:

fail safely through existing internal error handling.

Do not expose SQL/model details.

Do not automatically delete corrupt rows during GET.

---

# 114. No Cart Repair Side Effects

GET must not silently:

```text
delete stale item
change quantity
change Product/Variant
merge duplicates
```

except lazy Cart creation itself.

---

# 115. Rate Limiting

Attach the existing holder/private read limiter appropriate to CART-001.

Do not use the public catalog limiter.

---

# 116. Guest Rate-Limit Key

Use the existing rate-limit security conventions.

Do not put raw guest token into limiter keys/logs.

If guest Cart requests need credential-derived throttling:

use safe server-side digest/keying according to current limiter conventions.

---

# 117. Authenticated Rate-Limit Key

Use local:

```text
users.id
```

as existing conventions require.

Do not use Clerk `sub` or bearer token.

---

# 118. Error Envelope

All failures use canonical:

```text
errors[]
meta.request_id
```

---

# 119. Invalid Authentication

Invalid attempted Clerk auth:

```text
401 INVALID_AUTHENTICATION
```

or exact current Group D mapping.

Do not downgrade to guest.

---

# 120. Invalid Guest Credential

Use the canonical holder-auth failure agreed during Phase 6.1:

```text
401 AUTHENTICATION_REQUIRED
```

where applicable.

Do not expose:

```text
guest cart exists but inactive
digest not found
token mismatch
```

---

# 121. No 403 for Unknown Guest Token

Anonymous guest token failure is credential failure, not an authenticated authorization failure.

---

# 122. Internal Unique Conflict

If active-cart creation hits unique constraint from a race:

resolve/retry internally.

Do not return database conflict to normal caller if the Cart now exists and can safely be returned.

---

# 123. Schema Changes

Expected:

```text
NONE
```

Do not alter `carts` or `cart_items`.

---

# 124. No New Cart Ownership Columns

Do not add:

```text
session_id
device_id
client_type
token
guest_id
owner_type
```

---

# 125. No New Status

Do not add:

```text
MERGED
ABANDONED
EXPIRED
```

---

# 126. Dependencies

Expected:

```text
NONE
```

Use Laravel + existing application abstractions.

---

# 127. No Idempotency-Key

CART-001 does not need the mutation Idempotency-Key contract.

GET holder resolution is naturally idempotent.

Do not introduce idempotency records for GET.

---

# 128. Do Not Implement CART-005 Idempotency

Merge idempotency belongs to its own later phase.

---

# 129. Tests — Authenticated Existing Cart

Given a local authenticated User with ACTIVE Cart:

```text
GET /me/cart
→ 200
→ same Cart ID
→ no new Cart
```

---

# 130. Tests — Authenticated No Cart

Given authenticated User with none:

```text
GET
→ 200
→ creates one ACTIVE user Cart
→ empty representation
```

---

# 131. Tests — Repeated Authenticated GET

Call twice.

Assert:

```text
one Cart row
same ID
```

---

# 132. Tests — Concurrent Authenticated Creation

Two concurrent first CART-001 calls:

assert:

```text
one ACTIVE cart
no unhandled unique error
both callers resolve same Cart
```

Use real MariaDB integration only if SQLite cannot faithfully prove generated-column uniqueness/concurrency behavior.

---

# 133. Tests — Guest No Credential

Anonymous request without credential:

```text
→ 200
→ creates ACTIVE guest Cart
→ emits raw UUIDv4 through correct transport
→ stores only digest
```

---

# 134. Tests — Raw Credential Never Persisted

Search DB representation.

Raw token must not appear in:

```text
carts
logs where testable
JSON
```

---

# 135. Tests — Browser Token Issuance

Browser-classified request:

assert:

```text
Set-Cookie guest_cart_id
HttpOnly
Secure
SameSite=None
```

and:

```text
no X-Guest-Cart-Id response header
```

---

# 136. Tests — Flutter Token Issuance

Non-browser request:

assert:

```text
X-Guest-Cart-Id present
```

and:

```text
no guest_cart_id Set-Cookie
```

---

# 137. Tests — UUIDv4

New guest credential must satisfy:

```text
valid UUID
version 4
```

---

# 138. Tests — Guest Existing Cart

Use previously issued raw token.

Next GET:

```text
→ same Cart
→ no duplicate Cart
```

---

# 139. Tests — Guest Digest

Assert persisted digest matches:

```text
GuestCartCredential::digest(raw)
```

and is not raw.

---

# 140. Tests — Unknown Guest Token

Valid UUID format but no matching active digest:

```text
→ 401
```

according to accepted semantics.

---

# 141. Tests — Retired Guest Token

Create guest Cart:

```text
status = INACTIVE
```

with valid digest.

Request with its raw token:

```text
→ 401
```

Do not reactivate it.

---

# 142. Tests — Malformed Guest Token

Reject malformed credential through canonical validation/auth error.

Do not hash arbitrary huge unbounded input without normal validation limits.

---

# 143. Tests — Authenticated + Guest

Authenticated User has own Cart.

Also provide valid guest token.

CART-001 returns:

```text
authenticated Cart
```

Guest Cart:

```text
remains unchanged
remains ACTIVE
```

No merge.

---

# 144. Tests — Authenticated + Invalid Guest Token

If authenticated identity is authoritative for CART-001:

the invalid guest token should not redirect ownership.

Follow the frozen precedence rule exactly.

Ensure it cannot cause access to another Cart.

---

# 145. Tests — Invalid Bearer + Valid Guest

If Authorization header is present but invalid:

```text
401
```

Do not fall back to valid guest token.

This protects authentication downgrade behavior.

---

# 146. Tests — Staff Personal Cart

Authenticated STAFF:

```text
GET /me/cart
→ own Cart
```

if current personal-commerce policy permits.

Must not require CUSTOMER role merely to maintain a self-owned Cart.

---

# 147. Tests — Admin Personal Cart

Same self-context behavior for ADMIN where approved.

---

# 148. Tests — No Cross-User Access

Because endpoint has no Cart selector:

verify attempts to supply:

```text
cart_id
user_id
```

through query/body cannot alter holder selection.

---

# 149. Tests — Empty Cart Shape

Assert exact:

```text
id
items_count = 0
items = []
subtotal.amount = 0
subtotal.currency = TZS
updated_at
```

---

# 150. Tests — Existing Cart Shape

Seed at least one CartItem and verify response fields match frozen Cart representation.

Do not wait until 6.8 to verify basic CART-001 shape.

---

# 151. Tests — Items Count

Cart:

```text
Product A x4
Product B x2
```

returns:

```text
items_count = 2
```

---

# 152. Tests — Current Price

Create CartItem, then modify Variant price.

Next GET must show updated price.

Cart row remains unchanged.

---

# 153. Tests — Line Total

Verify:

```text
unit_price * quantity
```

using integer minor units.

---

# 154. Tests — Subtotal

Verify sum of current line totals.

---

# 155. Tests — GET Does Not Persist Price

Ensure no price-related Cart columns/state are created or updated.

---

# 156. Tests — Availability Read Does Not Reserve

Compare ProductStock before and after GET:

```text
quantity unchanged
reserved_quantity unchanged
```

---

# 157. Tests — Stale Product Preserved

Stored CartItem Product becomes inactive/unpublished/soft-deleted.

GET must retain the CartItem.

It must not silently disappear.

---

# 158. Tests — Stale Variant Preserved

Stored Variant becomes inactive.

GET retains the line.

---

# 159. Tests — Out-of-Stock Preserved

Available stock becomes 0.

GET retains the line.

---

# 160. Tests — Private Cache

Assert expected Cart cache headers.

---

# 161. Tests — No Credential Leakage

Cart JSON must not contain:

```text
guest_token_digest
raw guest credential
user_id
active_user_guard
```

---

# 162. Tests — Browser Header Leakage

For browser request:

assert raw token does not appear in any custom response header other than the cookie transport permitted by contract.

---

# 163. Tests — Flutter Cookie Leakage

For Flutter:

assert no guest token cookie is emitted.

---

# 164. Tests — Database Ownership

New authenticated Cart:

```text
user_id set
digest null
```

New guest Cart:

```text
user_id null
digest set
```

---

# 165. Tests — One Active Cart

Lazy creation must continue to satisfy DB and model invariant.

---

# 166. Tests — No Schema Changes

Existing CartSchemaTest remains green.

---

# 167. SQLite Coverage

SQLite may validate:

```text
holder resolution
resource shape
credential hashing
guest/auth precedence
empty Cart behavior
serialization
price projection
availability projection
cache headers
```

---

# 168. MariaDB Coverage

MariaDB-specific integration is only required where Phase 6.2 behavior depends on engine-specific concurrency/generated-column uniqueness behavior.

Do not unnecessarily move the full Group F suite to MariaDB.

---

# 169. Creation Race Gate

If the authenticated lazy-create race cannot be proven correctly under SQLite:

add a focused disposable MariaDB integration test.

Do not claim race safety without testing the actual production uniqueness behavior.

---

# 170. No Group E Regression

Run relevant catalog/availability tests because CART-001 projection consumes those services.

Do not modify Group E semantics.

---

# 171. No Phase 6.3 Work

Do not implement:

```text
POST /me/cart/items
```

yet.

---

# 172. No Phase 6.4 Work

Do not implement:

```text
PATCH /me/cart/items/{item}
```

yet.

---

# 173. No Phase 6.5 Work

Do not implement:

```text
DELETE /me/cart/items/{item}
POST /me/cart/merge
```

yet.

---

# 174. No Checkout Work

Do not implement:

```text
POST /checkout
```

or invoke stock reservation primitives from Cart GET.

---

# 175. No Frontend

Do not modify:

```text
frontend/web/
frontend/app/
frontend/design-system/
```

---

# 176. Likely Implementation Areas

Expected:

```text
routes/api.php
CartController or MeCartController
CartHolderResolver
GetOrCreateActiveCart action/service
GuestCartCredential transport middleware/helper
CartResource
CartItemResource/projection
optional auth middleware wiring
opaque ID serialization support
tests/Feature/CartReadApiTest.php
tests/Integration/... only if creation race needs MariaDB
docs/api/openapi.yaml if implementation drift exists
docs/decisions.md closure ADR
```

Use actual repository naming conventions.

---

# 177. Route Review

Final route for this phase:

```text
GET /api/v1/me/cart
```

This phase adds only `CART-001`.

Existing Cart mutation stubs (`CART-002..005`: add/update/remove/merge) are
registered by the Phase 2.6 routing foundation but remain unimplemented
(`501`) and are outside Phase 6.2 scope.

Do not implement or enable additional Cart behavior in this phase.

---

# 178. OpenAPI

Verify CART-001 accurately documents:

```text
optional authenticated bearer
guest cookie/header behavior
200 Cart response
private response
guest credential issuance transport
401 invalid attempted credentials
```

Do not expose token in response schema body.

---

# 179. Documentation

Update docs only for genuine runtime reconciliation.

Do not rewrite the already accepted Cart architecture.

---

# 180. Code Quality

Maintain:

```text
cognitive complexity <= 15
<= 3 returns where practical
thin controller
small holder resolver
small get/create service
explicit resources
no duplicated credential logic
```

---

# 181. Verification

Run focused tests first.

Then:

```bash
php artisan test
vendor/bin/pint --test
vendor/bin/phpstan analyse
composer audit
git diff --check
php artisan route:list
```

If MariaDB race verification is required:

run it against the approved disposable database only.

---

# 182. Destructive DB Safety

If using the MariaDB disposable harness:

require existing repository guard:

```text
APP_ENV != production
AND
DB_DATABASE == approved disposable DB
```

Never touch normal development/staging/production database.

---

# 183. Completion Report

Return:

## Phase 6.2 status

```text
PASS
```

or:

```text
BLOCKED
```

## CART-001

Confirm:

```text
GET /api/v1/me/cart
```

and lazy create/get semantics.

## Authenticated holder

Report:

```text
existing Cart
lazy creation
one-active invariant
race handling
```

## Guest holder

Report:

```text
new guest creation
existing credential lookup
invalid credential behavior
retired credential behavior
```

## Transport

Report separately:

```text
Browser cookie behavior
Flutter/header behavior
mutual exclusivity
```

## Identity precedence

Confirm:

```text
authenticated identity wins
CART-001 does not auto-merge
```

## Personal commerce

State Staff/Admin self-owned Cart behavior.

## Representation

Report:

```text
opaque cart_/item_ IDs
empty Cart representation
items_count
subtotal
```

## Projection

Report reuse of:

```text
current price
availability
stock_indicator
is_purchasable
```

without persistence/reservation.

## Cache

Confirm private/no-store behavior.

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

Report exact focused/full results.

## Database-specific tests

Report any MariaDB creation-race gate separately.

## Quality

Report:

```text
Pint
PHPStan
Composer audit
git diff --check
```

## Phase 6.3 readiness

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

# 184. Definition of Done

Phase 6.2 is complete when:

* `GET /api/v1/me/cart` is active;
* no separate Cart-create endpoint exists;
* authenticated callers resolve only their own ACTIVE Cart;
* authenticated caller with no Cart receives a lazily created empty ACTIVE Cart;
* repeated GET returns the same Cart;
* concurrent authenticated lazy creation cannot create duplicate active Carts;
* guests can retrieve their Cart with a valid `guest_cart_id` cookie or `X-Guest-Cart-Id` header;
* anonymous caller with no credential gets a new empty guest Cart;
* only guest-token digest is persisted;
* raw guest token never enters DB/log/JSON;
* new guest token is UUIDv4;
* browser receives token only via HttpOnly Secure SameSite=None cookie;
* browser never receives guest token response header;
* Flutter/non-browser receives token only via `X-Guest-Cart-Id`;
* Flutter does not receive guest cookie;
* invalid guest token does not authorize/create under that token;
* retired guest token cannot reactivate its Cart;
* invalid attempted Clerk authentication does not downgrade to guest;
* authenticated identity wins when both auth and guest credential are present;
* CART-001 never auto-merges;
* Staff/Admin may resolve only their own personal Cart under approved self-commerce policy;
* Cart ID is not accepted as ownership input;
* Cart response uses opaque `cart_...` ID;
* item IDs use opaque `item_...` representation;
* empty Cart returns `items: []`;
* empty Cart returns `items_count: 0`;
* empty subtotal is zero TZS money object;
* existing Cart displays current server-derived price;
* existing Cart displays current availability/stock indicator;
* stale lines are not silently deleted;
* GET does not reserve inventory;
* GET does not mutate Cart timestamps for ordinary reads;
* Cart response is private/no-store;
* no sensitive ownership/digest fields leak;
* no schema changes are required;
* no external dependencies are added;
* no frontend code is modified;
* existing Group E and Cart schema tests remain green;
* no new PHPStan errors exist;
* Pint passes;
* Composer audit has no blocker.

---

# 185. Out of Scope

Do not implement:

```text
add item
repeat-add merge
update quantity
remove item
guest→customer merge
CART-005 idempotency
full stale-cart validation workflow
checkout
inventory reservation
Order creation
frontend Cart UI
```

---

# 186. STOP Condition

STOP when CART-001 reliably establishes the holder's canonical active Cart:

```text
authenticated user
    → own ACTIVE Cart
    → create empty one if absent

anonymous + valid guest credential
    → bound ACTIVE guest Cart

anonymous + no credential
    → create ACTIVE guest Cart
    → issue secure credential

authenticated + guest credential
    → authenticated Cart only
    → no implicit merge
```

and returns the private Cart representation without persisting pricing/availability or reserving inventory.

Do not continue automatically to Phase 6.3.

DO NOT COMMIT, STAGE OR PUSH.

The project owner handles all Git operations.
