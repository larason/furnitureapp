# Phase 10.4 — Product-Linked Furniture Requests

## 1. Objective

Implement the authoritative **domain eligibility boundary for `product_id` on REQ-001**:

```http
POST /api/v1/requests
```

Phase 10.4 answers one business question:

```text
If product_id is supplied,
is that Product currently eligible to be referenced by a Furniture Request?
```

The frozen V1 rule is:

```text
product_id omitted/null
→ valid custom furniture request

product_id supplied
→ Product must exist
→ Product must be publicly visible
→ Product must be MADE_TO_ORDER
→ otherwise reject
```

Do not create a second Furniture Request creation workflow.

Integrate the product-domain validation into the existing:

```text
CreateFurnitureRequestRequest
→ FurnitureRequestInput
→ CreateFurnitureRequestCommand
→ CreateFurnitureRequest
```

pipeline.

---

# 2. Current Group J Baseline

Treat the current repository state as:

```text
10.1 PASS — Furniture Request API foundation
10.2 PASS — Request validation
10.3 PASS — Request status lifecycle
10.4 CURRENT — Product-linked requests
10.5 NOT STARTED — General enquiries
10.6 NOT STARTED — Attachments
10.7 NOT STARTED — Staff/Admin request management
10.8 NOT STARTED — Request/enquiry closure tests
```

REQ-001 remains publicly gated.

Do not activate it automatically in this phase because Phase 10.6 attachment support is still outstanding.

---

# 3. Existing REQ-001 Architecture

Reuse:

```text
Validator:
App\Http\Requests\CreateFurnitureRequestRequest

Normalized input:
App\Services\Requests\FurnitureRequestInput

Command:
App\Services\Requests\CreateFurnitureRequestCommand

Creation service:
App\Services\Requests\CreateFurnitureRequest

Resource:
App\Http\Resources\FurnitureRequestResource
```

Do not replace any of these.

---

# 4. Validation Layering

Preserve the project's validation model:

```text
Transport
→ Schema
→ Optional Authentication
→ Authorization
→ Domain
→ Persistence
```

Phase 10.2 already owns schema validation.

Phase 10.4 now implements the Product **domain** check.

Therefore:

```text
CreateFurnitureRequestRequest
```

should continue validating only:

```text
product_id optional
product_id nullable
product_id string
product_id opaque prod_... format
```

It must not become the Product database/business-query authority.

---

# 5. Domain Rule

The frozen rule for a supplied `product_id` is:

```text
exists
AND is_active = true
AND is_published = true
AND not soft-deleted
AND parent Category is active
AND product_type = MADE_TO_ORDER
```

Only then may the Request link to it.

---

# 6. Reuse Public Catalog Visibility

Do not invent another definition of:

```text
publicly visible Product
```

Group E already defines public visibility as:

```text
Product.is_active = true
Product.is_published = true
Product.deleted_at IS NULL
Category.is_active = true
```

Reuse the existing Product/catalog scope/query authority if one exists.

Possible examples:

```php
Product::publiclyVisible()
PublicProductQuery
CatalogProductQuery
```

Use actual repository naming.

---

# 7. No Duplicate Visibility Logic

Do not write a second ad-hoc chain such as:

```php
Product::where('is_active', true)
    ->where('is_published', true)
    ...
```

if an authoritative visibility scope/service already exists.

The Request domain should consume the same truth as CAT-001/CAT-002.

---

# 8. Category Activity Matters

A Product that is itself:

```text
active
published
not deleted
```

but belongs to an inactive Category is not publicly requestable.

Reject it exactly as a non-public Product.

---

# 9. Soft-Deleted Product

A soft-deleted Product must not be requestable.

Do not use:

```php
withTrashed()
```

for REQ-001 product eligibility.

Operational inventory reads may intentionally see archived Products; public Request intake must not.

---

# 10. Product Type

The Product must be:

```text
MADE_TO_ORDER
```

Use the existing closed enum:

```text
ProductType::MADE_TO_ORDER
```

or repository equivalent.

Do not compare arbitrary strings where an enum already exists.

---

# 11. IN_STOCK Product

A currently public:

```text
IN_STOCK
```

Product supplied to REQ-001 must be rejected.

Frozen business error:

```text
409 PRODUCT_NOT_REQUESTABLE
```

This is intentionally different from:

```text
PRODUCT_NOT_PURCHASABLE
```

used by Cart/Checkout.

Do not reuse the Checkout error code.

---

# 12. Why the Error Is Different

These represent opposite domain mistakes:

```text
MADE_TO_ORDER → Cart
= PRODUCT_NOT_PURCHASABLE

IN_STOCK → Furniture Request product link
= PRODUCT_NOT_REQUESTABLE
```

Keep those semantics distinct.

---

# 13. MADE_TO_ORDER Inventory Is Irrelevant

A requestable MADE_TO_ORDER Product does **not** require:

```text
stock row
positive quantity
active Variant with stock
warehouse allocation
available_quantity > 0
```

Group E already defines MADE_TO_ORDER catalog availability independently from inventory.

Therefore do not query ProductStock for Request eligibility.

---

# 14. No Variant Requirement

REQ-001 links:

```text
Product
```

not:

```text
ProductVariant
```

Do not require:

```text
variant_id
SKU
variant availability
```

for Furniture Requests.

---

# 15. No Product Price Requirement

A linked Product may have a catalog price/starting estimate, but Request creation does not price-lock anything.

Do not copy Product price into:

```text
FurnitureRequest
```

and do not return an authoritative quote.

---

# 16. No Product Snapshot Invention

The existing schema contains:

```text
product_details
```

but it is not part of the frozen REQ-001 contract and Phase 10.1 deliberately kept it non-public.

Do not suddenly use it as:

```text
product snapshot
price snapshot
name snapshot
```

without a separately approved contract/schema decision.

Phase 10.4 should persist the valid:

```text
product_id
```

relationship only.

---

# 17. Historical Rule

The Request must preserve the originally submitted:

```text
product_id
```

relationship.

Do not silently relink it if Product data later changes.

Do not mutate the Request based on later catalog changes.

---

# 18. Custom Request Remains Valid

These remain valid:

```json
{
  "name": "Asha",
  "phone": "+255700000001",
  "notes": "Can you make a custom bookshelf?"
}
```

and:

```json
{
  "product_id": null,
  "name": "Asha",
  "phone": "+255700000001"
}
```

Do not make `product_id` required.

---

# 19. Omitted vs Null

Both:

```text
product_id omitted
```

and:

```text
product_id = null
```

must continue through the custom-request path without a Product database lookup.

---

# 20. Avoid Pointless Query

For:

```text
productId === null
```

do not query `products`.

Proceed directly with creation.

---

# 21. Introduce Focused Domain Resolver

Create or reuse a focused service/value boundary.

Recommended concept:

```php
App\Services\Requests\RequestableProductResolver
```

or:

```php
ResolveRequestableProduct
```

Use repository naming conventions.

Its job:

```text
input:
opaque product_id|null

output:
Product|null

rules:
null → null
valid ID + public MADE_TO_ORDER → Product
non-public/not found → not-found error
public IN_STOCK → PRODUCT_NOT_REQUESTABLE
```

---

# 22. Keep Creation Service Focused

`CreateFurnitureRequest` should coordinate:

```text
trusted command
→ resolve optional requestable Product
→ persist FurnitureRequest
```

Do not turn it into a large catalog-query implementation.

---

# 23. Suggested Composition

Conceptually:

```text
CreateFurnitureRequestCommand
            │
            ├── productId null
            │      ↓
            │    no Product
            │
            └── productId supplied
                   ↓
            RequestableProductResolver
                   ↓
             Product domain check
                   ↓
             CreateFurnitureRequest
```

---

# 24. Pass Internal Product Identity Safely

Phase 10.2 already validates the public opaque:

```text
prod_...
```

format.

Phase 10.4 must resolve that identifier through the existing:

```text
ProductIdentifier
```

or equivalent Product ID decoder.

Do not manually strip prefixes in multiple places.

---

# 25. Never Accept Numeric Product DB ID Publicly

This stays invalid:

```json
{
  "product_id": 42
}
```

Phase 10.2 should already reject it.

Phase 10.4 must not add a hidden numeric-ID fallback.

---

# 26. Do Not Accept Slug in REQ-001

CAT-002 may resolve Product by:

```text
slug
or opaque id
```

but REQ-001 `product_id` specifically means the machine identifier.

Do not reinterpret arbitrary strings/slugs as Product references.

---

# 27. Product Resolution Privacy

REQ-001 is public.

Do not expose internal publication/activity distinctions unnecessarily.

An anonymous user should not be able to probe:

```text
draft Product
inactive Product
soft-deleted Product
Product under inactive Category
```

and receive different sensitive state details.

---

# 28. Non-Public Product Mapping

Preferred domain behavior:

```text
unknown product_id
inactive Product
unpublished Product
soft-deleted Product
inactive Category
```

all resolve as:

```text
Product not publicly available / not found
```

Use the repository's canonical:

```text
PRODUCT_NOT_FOUND
```

or:

```text
RESOURCE_NOT_FOUND
```

mapping consistently.

---

# 29. Determine Exact Not-Found Code

Before implementation, inspect:

```text
ApiErrorCode
Product public lookup behavior
CAT-002 not-found mapping
api-contract.md §15
REQ-001 tests/conventions
```

Choose the existing canonical public Product-not-found response.

Do not invent a Request-specific not-found code.

---

# 30. Avoid `INVALID_REQUEST` for Invisible Product If Existing Not-Found Exists

The contract documents several historical possibilities for Product missing/inactive behavior.

Phase 10.4 must make the implementation deterministic.

Prefer existing Product public-resolution semantics rather than creating another interpretation.

Document the selected mapping.

---

# 31. Public IN_STOCK Is Different

A Product that **is publicly visible** but has:

```text
product_type = IN_STOCK
```

must not be hidden as "not found."

The client referenced a real visible Product but used the wrong business workflow.

Return:

```text
409 PRODUCT_NOT_REQUESTABLE
```

---

# 32. Correct Evaluation Order

Conceptually:

```text
1. decode product_id
2. resolve Product through public visibility authority
3. if not found → canonical Product not-found
4. inspect product_type
5. if not MADE_TO_ORDER → PRODUCT_NOT_REQUESTABLE
6. return eligible Product
```

---

# 33. Do Not Inspect Type Before Visibility

Avoid allowing users to distinguish hidden Product types.

Visibility should be established before returning a business-type error.

---

# 34. `PRODUCT_NOT_REQUESTABLE` Registry Check

Phase 10.2 identified an important contract inconsistency:

The frozen REQ contract explicitly uses:

```text
PRODUCT_NOT_REQUESTABLE
```

but the global surfaced OpenAPI error-code enum must be checked because it may not currently contain it.

Phase 10.4 owns this reconciliation.

---

# 35. If `ApiErrorCode` Lacks It

Add:

```text
PRODUCT_NOT_REQUESTABLE
```

to the backend closed error registry.

This is **not a new business contract**.

It is implementation of the already-approved frozen REQ-001 contract.

---

# 36. If OpenAPI Error Enum Lacks It

Add:

```text
PRODUCT_NOT_REQUESTABLE
```

to the existing global error-code enum in:

```text
docs/api/openapi.yaml
```

provided the normative contract already defines the code.

Classify this as:

```text
frozen-contract consistency correction
```

not API expansion.

---

# 37. Documentation Alignment

After correction, ensure these agree:

```text
api-contract.md
api-conventions.md
business-rules.md
openapi.yaml
ApiErrorCode
implementation
tests
```

Do not leave the API docs and implementation with different CLOSED code sets.

---

# 38. No Other OpenAPI Changes

Do not change:

```text
REQ-001 fields
requiredness
status codes
product_id nullability
request response shape
```

Only reconcile an already-approved missing error enum entry if confirmed.

---

# 39. Error Exception

Introduce/reuse a focused exception such as:

```php
ProductNotRequestable
```

extending the project's API/domain exception hierarchy.

It should map to:

```text
HTTP 409
code PRODUCT_NOT_REQUESTABLE
field product_id
```

---

# 40. Safe Error Message

Use a stable human-readable message.

Do not leak:

```text
product internal state
category state
database id
publication timestamps
```

---

# 41. Product Not Found Exception

Reuse established Product-not-found behavior.

Do not create:

```text
FurnitureRequestProductNotFound
```

unless the repository architecture requires one.

---

# 42. Domain Service Not HTTP-Aware

The resolver/service should not receive:

```php
Illuminate\Http\Request
```

It should receive:

```text
string product ID
```

or a typed identifier/value.

---

# 43. Domain Service Not Actor-Aware

Eligibility is the same for:

```text
Anonymous
CUSTOMER
```

Do not make requestability depend on actor identity.

---

# 44. Staff/Admin Irrelevant

REQ-001 already rejects Staff/Admin in the middleware boundary.

Do not add role branches to Product eligibility.

---

# 45. Product Relationship Persistence

After eligibility passes:

```text
FurnitureRequest.product_id
```

must store the resolved Product's internal FK.

Do not persist the opaque public string directly if the schema uses numeric FK.

---

# 46. Trust the Resolved Product

Prefer passing/resolving:

```text
Product model
```

to the persistence boundary rather than decoding the identifier twice.

Avoid TOCTOU-like mismatched lookups.

---

# 47. Low-Concurrency Domain

Request intake is low-concurrency.

No Product row lock is normally required merely to create the relation.

Do not add:

```text
FOR UPDATE
```

to public Product resolution unless there is a current writer that makes it necessary.

---

# 48. Current Product Mutation Reality

Inspect whether Product publication/type management endpoints are active or still stubs.

If no concurrent public Product mutation workflow currently exists:

do not invent speculative Product locking.

Record the assumption.

---

# 49. If Product Can Change Concurrently

If repository already has active admin Product mutation capable of changing:

```text
is_active
is_published
product_type
category_id
```

between validation and Request persistence:

define a minimal consistency strategy.

Do not guess.

Prefer a transaction-time domain recheck if required.

---

# 50. Do Not Over-Lock Category Graph

No category-tree lock is needed for Request creation.

Only current public visibility matters.

---

# 51. MADE_TO_ORDER Availability

For a public MADE_TO_ORDER Product:

```text
availability = available
stock_indicator = MADE_TO_ORDER
```

regardless of stock.

Do not require stock.

---

# 52. Zero Stock Is Still Requestable

Test explicitly:

```text
public MADE_TO_ORDER
no ProductStock rows
→ requestable
```

---

# 53. Reserved Stock Is Irrelevant

Test:

```text
public MADE_TO_ORDER
reserved quantities arbitrary
→ requestable
```

if ProductStock rows exist.

No stock reads should be necessary.

---

# 54. Variant State Is Irrelevant

A MADE_TO_ORDER Product must not become unrequestable simply because it has:

```text
no variants
inactive variant
zero-stock variant
```

unless another frozen Product-domain invariant explicitly requires variants for public Product visibility.

Use the established catalog authority.

---

# 55. Category Visibility Test

Test:

```text
MADE_TO_ORDER
product active
published
category inactive
→ not publicly requestable
```

---

# 56. Product Inactive Test

```text
MADE_TO_ORDER
is_active = false
→ not found/non-public
```

---

# 57. Product Unpublished Test

```text
MADE_TO_ORDER
is_published = false
→ not found/non-public
```

---

# 58. Soft-Deleted Test

```text
MADE_TO_ORDER
soft deleted
→ not found/non-public
```

---

# 59. IN_STOCK Test

```text
public IN_STOCK
→ 409 PRODUCT_NOT_REQUESTABLE
→ no Request persisted
```

Mandatory regression.

---

# 60. Public MADE_TO_ORDER Test

```text
public MADE_TO_ORDER
→ 201 internally when route enabled in test
→ product_id persisted
→ Product summary returned
```

assuming no attachment is required.

---

# 61. Custom Request Test

```text
product_id omitted
→ valid
→ product_id null
→ product null
```

---

# 62. Explicit Null Test

```text
product_id = null
→ same custom-request semantics
```

---

# 63. No Product Query Test for Null

Where reasonable, verify the resolver does not query Product for null input.

Do not make this a brittle SQL-count test if architecture makes it awkward.

---

# 64. Product Resource Summary

The Phase 10.1 resource already returns:

```text
product:
{
  id,
  name,
  slug
}
```

for linked requests.

Preserve that exact safe summary.

---

# 65. No Product Price in Request Resource

Do not expand the embedded summary with:

```text
price
availability
stock
description
variants
```

unless already frozen.

---

# 66. No Internal Product Fields

Never expose:

```text
is_active
is_published
deleted_at
category_id
numeric product id
inventory values
```

through `FurnitureRequestResource`.

---

# 67. Eager Loading

For a created linked Request, ensure the Product relation is available without accidental N+1 behavior.

One created resource does not justify complex collection optimization.

Use a simple bounded load.

---

# 68. Later Retrieval Compatibility

Do not design Phase 10.4 so later REQ-002/003/004/005 must rerun "is Product still public?" merely to display historical Request data.

Eligibility is an **intake-time** rule.

Historical Request retrieval should not disappear because Product is later unpublished.

---

# 69. Important Historical Principle

Once the Request was validly created:

```text
later Product deactivation
later unpublish
later Category deactivation
```

must not invalidate/delete the historical Request.

Do not cascade business state from Product into past Request validity.

---

# 70. Product Deletion FK

Inspect the existing FurnitureRequest→Product FK delete policy.

Do not change it in Phase 10.4 unless there is a genuine schema-contract defect.

Expected:

```text
Schema changes NONE
```

---

# 71. No Request Rewrite on Catalog Change

Do not schedule background updates to historical requests when Product changes.

---

# 72. Product Type Later Changes

If a Product later changes from:

```text
MADE_TO_ORDER → IN_STOCK
```

an already-created Request remains historical intake.

Do not invalidate it retroactively.

---

# 73. New Request Uses Current Type

A **new** Request after such a change must use current Product state and therefore reject the now-IN_STOCK Product.

---

# 74. Catalog Price Later Changes

No effect on the Request.

---

# 75. No Price Snapshot

Again, no price snapshot should be persisted.

Request is not a quote.

---

# 76. No Cart Interaction

Product-linked Request creation must not:

```text
create Cart
add CartItem
remove CartItem
merge Cart
```

---

# 77. No Checkout Interaction

No CHK-001 call.

---

# 78. No Order

No Order/OrderItem.

---

# 79. No Inventory Reservation

No:

```text
InventoryAllocator
reserved_quantity
allocation rows
```

---

# 80. No Payment

No Payment row.

No ClickPesa call.

---

# 81. No Delivery

No delivery fee or fulfillment choice.

---

# 82. No Quote

No:

```text
quoted_price
estimated_total
starting_total
```

as Request authority.

---

# 83. Creation Status

Linked Requests still begin:

```text
SUBMITTED
```

Phase 10.3 remains unchanged.

---

# 84. Lifecycle Independence

Product eligibility is checked only at creation.

Lifecycle transitions:

```text
SUBMITTED
IN_REVIEW
CLOSED
```

must not revalidate Product public visibility.

---

# 85. Example

Valid:

```text
Day 1:
Product public MADE_TO_ORDER
Request created SUBMITTED

Day 2:
Product unpublished

Day 3:
Staff transitions Request → IN_REVIEW
```

The transition must remain valid.

Do not couple Phase 10.3 to live catalog state.

---

# 86. Validation Failure Side Effects

If linked Product fails domain validation:

```text
FurnitureRequest inserts = 0
Order inserts = 0
Payment inserts = 0
Inventory mutations = 0
```

---

# 87. Contact Validation Still Runs

Do not bypass Phase 10.2 merely because Product is eligible.

A request with valid Product but invalid:

```text
name
phone/email
quantity
dimensions
```

must still fail normal schema validation.

---

# 88. Validation Ordering

Preserve:

```text
schema first
then Product domain lookup
```

Do not query Product for a body already invalid at schema level.

---

# 89. Avoid Enumeration Through Malformed IDs

Malformed:

```text
product_id
```

must fail Phase 10.2:

```text
422 INVALID_FORMAT
```

without querying Product.

---

# 90. Valid-Looking Unknown ID

A well-formed opaque ID that resolves to no public Product reaches domain resolution and returns canonical not-found.

---

# 91. Product Eligibility Component Tests

Create a focused unit/feature suite, e.g.:

```text
RequestableProductResolverTest
```

Use actual repository naming.

---

# 92. Resolver Test Matrix

At minimum:

```text
null                         → null/allowed
public MADE_TO_ORDER         → allowed
public IN_STOCK              → PRODUCT_NOT_REQUESTABLE
inactive MADE_TO_ORDER       → not found/non-public
unpublished MADE_TO_ORDER    → not found/non-public
soft-deleted MADE_TO_ORDER   → not found/non-public
inactive-category MTO        → not found/non-public
unknown opaque Product ID    → not found
```

---

# 93. No Inventory Dependency Test

Create a public MADE_TO_ORDER Product with:

```text
zero stock rows
```

It must still resolve successfully.

---

# 94. Request API Integration Test

With the gated route enabled in-process:

```text
public MADE_TO_ORDER
+ valid REQ-001 body
→ 201
```

Assert:

```text
FurnitureRequest.product_id = correct internal Product FK
resource product.id = original opaque Product ID
resource product.name correct
resource product.slug correct
```

---

# 95. Anonymous Linked Request

Test anonymous + public MADE_TO_ORDER.

Expected:

```text
201
user_id null
product linked
SUBMITTED
```

---

# 96. CUSTOMER Linked Request

Test authenticated CUSTOMER.

Expected:

```text
201
server-derived user_id
product linked
SUBMITTED
```

---

# 97. Staff/Admin Regression

They remain:

```text
403
```

for REQ-001.

Product eligibility must not make Staff/Admin submission possible.

---

# 98. Invalid Bearer Regression

Still:

```text
401 INVALID_AUTHENTICATION
```

not anonymous fallback.

---

# 99. IN_STOCK API Test

With route enabled in-process:

```text
valid public IN_STOCK product_id
→ 409 PRODUCT_NOT_REQUESTABLE
field: product_id
```

Assert no Request row.

---

# 100. Hidden Product API Tests

For each:

```text
inactive
unpublished
soft-deleted
inactive category
```

assert the chosen canonical public not-found response.

Do not expose which hidden state caused rejection.

---

# 101. Unknown Product API Test

Well-formed but nonexistent:

```text
prod_...
```

→ same not-found family as other non-public Product cases.

---

# 102. Product Type Error Does Not Mutate Anything

On `PRODUCT_NOT_REQUESTABLE`:

```text
no Request
no Cart
no Order
no Payment
no Inventory mutation
```

---

# 103. OpenAPI Error Enum Test

Add/extend a contract regression that asserts:

```text
PRODUCT_NOT_REQUESTABLE
```

is present in the frozen global error enum if reconciliation is required.

---

# 104. Backend Error Registry Test

Assert:

```text
ApiErrorCode::PRODUCT_NOT_REQUESTABLE
```

or equivalent is valid and serializes exactly.

---

# 105. HTTP Status

Frozen Request contract selects:

```text
PRODUCT_NOT_REQUESTABLE → 409
```

Do not map it to:

```text
422 PRODUCT_NOT_PURCHASABLE
```

---

# 106. Client Correction Semantics

`409 PRODUCT_NOT_REQUESTABLE` means:

```text
this visible Product does not belong in the made-to-order Request workflow
```

The client should use normal purchase flow instead.

Do not return internal routing advice in error details.

---

# 107. Not-Found Semantics

Hidden/missing Product should not tell the anonymous caller:

```text
this product exists but is unpublished
```

Keep safe public behavior.

---

# 108. Existing Product Visibility Tests

Run Group E public catalog regressions.

At minimum the tests proving:

```text
active + published + active category visible
inactive hidden
unpublished hidden
soft-deleted hidden
inactive category hidden
```

Do not alter Group E semantics.

---

# 109. Existing MADE_TO_ORDER Catalog Test

Run tests proving MADE_TO_ORDER remains:

```text
availability = available
stock_indicator = MADE_TO_ORDER
```

without inventory dependency.

---

# 110. Cart Regression

Run relevant Group F admission tests proving:

```text
MADE_TO_ORDER → PRODUCT_NOT_PURCHASABLE
```

Phase 10.4 must not accidentally make MADE_TO_ORDER Cart-purchasable.

---

# 111. Checkout Regression

Run the relevant Checkout Product eligibility test for MADE_TO_ORDER rejection if touched shared Product logic.

Keep:

```text
Requestable
≠
Checkout purchasable
```

---

# 112. Product Scope Reuse Regression

If modifying a shared Product scope/query:

run all CAT-001/CAT-002 tests.

Do not change public catalog ordering/filter behavior.

---

# 113. Phase 10.1 Regression

Run creation/resource tests.

---

# 114. Phase 10.2 Regression

Run:

```text
FurnitureRequestValidationApiTest
```

All 102+ validation cases remain green.

---

# 115. Phase 10.3 Regression

Run Request lifecycle tests.

Product integration must not alter status behavior.

---

# 116. No Product Revalidation During Status Changes

Add a regression if necessary:

```text
create linked MTO request
unpublish Product
transition Request → IN_REVIEW
→ success
```

This permanently protects historical independence.

---

# 117. Resource Historical Behavior

Do not make later Request serialization throw because Product becomes non-public.

If the current relationship/resource directly assumes a live public Product, identify the issue.

Do not solve unrelated later retrieval behavior prematurely unless needed to avoid a clear 500.

---

# 118. Soft-Deleted Relationship Caution

If Product soft deletion means the relation becomes `null` under ordinary Eloquent relationship loading:

do not automatically change it to `withTrashed()` in Phase 10.4 unless the frozen Request representation/history requires it and tests justify it.

Record the future retrieval concern for 10.7/10.8 if appropriate.

---

# 119. Product Summary Is Context, Not Authority

The linked Product summary is convenience context.

The Request's intake specifications/contact remain authoritative historical request data.

---

# 120. Do Not Auto-Copy Product Name Into Notes

No hidden transformations like:

```text
notes = "Request for {product.name}"
```

---

# 121. Do Not Auto-Fill Quantity

Linked Product still does not imply:

```text
quantity = 1
```

Omitted quantity remains null.

---

# 122. Do Not Auto-Fill Dimensions

Do not derive Product Variant dimensions into the request.

Request dimensions are explicit customer intent.

---

# 123. Do Not Auto-Fill Material/Color

Same.

---

# 124. No Variant Selection

Do not add `variant_id` to REQ-001.

That is a contract change.

---

# 125. No Product Slug Field

Do not add:

```text
product_slug
```

to input.

The embedded resource may contain slug, but input remains `product_id`.

---

# 126. No Product Type Field

Client must not submit:

```text
product_type
```

as authority.

Backend derives it from Product.

---

# 127. Tampering Test

A request body containing:

```json
{
  "product_id": "prod_...",
  "product_type": "MADE_TO_ORDER"
}
```

must reject unknown:

```text
product_type
```

through Phase 10.2.

Do not trust the client assertion.

---

# 128. No Availability Field

Reject client:

```text
availability
stock_indicator
```

if supplied.

They are not REQ-001 fields.

---

# 129. Domain Resolver Query Efficiency

One linked request should require a bounded Product query.

Do not load:

```text
variants
stocks
images
recommendations
materials
```

for eligibility.

Only load what is needed:

```text
Product
Category/public-visibility relation if required
```

---

# 130. Resource Load Separately If Needed

After creation, load only the fields/relation needed for:

```text
product {id,name,slug}
```

Do not reuse a huge catalog-detail eager-load graph.

---

# 131. Request Creation Transaction

If the current creation service already uses a transaction for:

```text
reference generation
Request persistence
```

integrate Product eligibility without creating nested independent commits.

---

# 132. Eligibility Timing

Domain eligibility should happen close enough to persistence that a Request is not knowingly linked to an invalid Product state.

Do not validate in middleware far away from creation.

---

# 133. No Product Lock by Default

Because Request intake does not reserve/purchase the Product, a normal visibility read is sufficient unless current active Product mutation introduces a proven race.

Do not turn low-concurrency intake into heavyweight transactional locking without evidence.

---

# 134. Error Logging

Do not log full Request contact because Product lookup failed.

Safe context might include:

```text
request_id
opaque product identifier
error code
```

only if current logging policy permits.

---

# 135. No Sensitive Catalog Internals in Error

Do not include:

```text
is_active=false
is_published=false
category inactive
deleted_at
```

in `details`.

---

# 136. Rate Limiting

Keep:

```text
throttle:anonymous-submit
```

unchanged.

Product lookup failures still consume request submission rate budget when route is test-enabled/public later.

Do not create a product-probing bypass.

---

# 137. Route Middleware

Preserve:

```text
api
→ clerk.optional
→ customer-submission
→ requests.enabled
→ throttle:anonymous-submit
```

unless the current actual ordering differs for a documented reason.

Do not reorder in 10.4.

---

# 138. Route Remains Gated

At Phase 10.4 completion:

```text
POST /api/v1/requests
```

should still normally be:

```text
STUB/GATED
```

because Phase 10.6 must implement frozen attachment support before public activation.

---

# 139. Why 10.6 Still Blocks Activation

The frozen REQ-001 contract supports:

```text
multipart/form-data
optional single attachment
```

A public endpoint that only supports JSON while advertising the frozen multipart contract would remain incomplete.

Therefore do not activate early.

---

# 140. Phase 10.5 Independence

General Enquiries are a different domain.

Do not reuse `RequestableProductResolver` blindly for Enquiries because Enquiries may reference any public Product under their own contract.

Phase 10.5 should implement its own appropriate Product association rule.

---

# 141. Do Not Generalize Resolver Too Far

Avoid a generic:

```text
PublicProductBusinessRuleEngine
```

just to serve future Enquiries.

Implement the smallest Request-specific eligibility service while reusing the shared public visibility scope.

---

# 142. Schema

Expected:

```text
Schema: NONE
```

No migration.

---

# 143. Dependencies

Expected:

```text
Dependencies: NONE
```

---

# 144. Frontend

Expected:

```text
Frontend: NONE
```

---

# 145. OpenAPI

Expected outcome:

```text
UNCHANGED
```

except, if confirmed necessary:

```text
add already-frozen PRODUCT_NOT_REQUESTABLE
to the global error-code enum
```

Classify that exact change as:

```text
contract consistency correction
```

not a new endpoint or behavior.

---

# 146. Documentation

Add the next backend ADR if current practice continues.

Likely:

```text
ADR/BACKEND-046 — Product-Linked Furniture Request Eligibility
```

but inspect the current latest ADR number after BACKEND-045.

Do not assume.

---

# 147. ADR Content

Record:

```text
product_id remains optional/nullable
custom request path requires no Product
linked Product uses existing public visibility authority
public visibility = active + published + not deleted + active Category
Product must be MADE_TO_ORDER
public IN_STOCK → 409 PRODUCT_NOT_REQUESTABLE
hidden/missing Product → canonical public not-found
MADE_TO_ORDER requestability independent of inventory/Variants
no price snapshot
no product_details snapshot
historical Request not invalidated by later Product changes
no Cart/Order/Payment/inventory effects
route remains gated for Phase 10.6
PRODUCT_NOT_REQUESTABLE enum reconciliation if needed
```

---

# 148. Group J Tracking

After PASS:

```text
10.1 PASS
10.2 PASS
10.3 PASS
10.4 PASS
10.5 READY
10.6 NOT STARTED
10.7 NOT STARTED
10.8 NOT STARTED
```

---

# 149. Phase 10.5 Readiness

Phase 10.5 — General Enquiries may proceed after 10.4.

Do not automatically start it.

---

# 150. Focused Verification

Run focused tests such as:

```bash
php artisan test --filter=RequestableProduct
php artisan test --filter=FurnitureRequest
```

then:

```bash
php artisan test
vendor/bin/phpstan analyse
vendor/bin/pint --test
composer audit
git diff --check
php artisan route:list
```

Verify OpenAPI parsing.

---

# 151. Catalog Regressions

Run relevant Product/Catalog tests explicitly.

Report them separately from Furniture Request tests.

---

# 152. Cart/Checkout Regressions

If any shared Product visibility/type helper was touched, run:

```text
Cart Product admission regressions
Checkout Product eligibility regressions
```

to prove:

```text
MADE_TO_ORDER
→ requestable
→ not Cart/Checkout purchasable
```

---

# 153. Completion Report

Return:

## Phase 10.4 status

```text
PASS
```

or:

```text
BLOCKED
```

---

## Product resolver

Report exact class/service.

---

## Public visibility authority

Report exact reused Product scope/service and rules:

```text
active
published
not soft-deleted
active Category
```

---

## Custom request

Report:

```text
product_id omitted → valid
product_id null → valid
Product lookup → none
```

---

## Linked request

Report:

```text
public MADE_TO_ORDER → allowed
```

---

## IN_STOCK behavior

Report exact:

```text
409 PRODUCT_NOT_REQUESTABLE
field: product_id
```

---

## Missing/non-public behavior

Report exact code/status selected for:

```text
unknown ID
inactive Product
unpublished Product
soft-deleted Product
inactive Category
```

Confirm these do not leak hidden Product state.

---

## Inventory

Confirm:

```text
ProductStock queries required for eligibility = NO
reservation = NONE
quantity mutation = NONE
allocation = NONE
```

---

## Variants

Confirm:

```text
variant required = NO
```

---

## Price

Confirm:

```text
price snapshot = NONE
quote = NONE
```

---

## Persistence

Confirm:

```text
FurnitureRequest.product_id
= resolved internal Product FK
```

for linked Requests.

---

## Product resource

Confirm embedded fields remain:

```text
id
name
slug
```

only.

---

## Historical behavior

Confirm later Product state changes do not retroactively invalidate Request lifecycle.

---

## Error registry

Report whether:

```text
PRODUCT_NOT_REQUESTABLE
```

already existed or was added.

---

## OpenAPI reconciliation

Report whether the global enum required correction.

If changed, classify:

```text
pre-existing frozen-contract consistency correction
```

---

## Route

Report:

```text
POST /api/v1/requests
STUB/GATED
```

and Phase 10.6 as remaining activation prerequisite.

---

## Orders

```text
NONE
```

---

## Payments

```text
NONE
```

---

## Inventory effects

```text
NONE
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

Expected:

```text
NONE
```

---

## Tests

Report:

```text
resolver matrix
custom request
anonymous linked MTO
CUSTOMER linked MTO
IN_STOCK rejection
hidden Product cases
inactive Category
zero-stock MTO
resource summary
historical independence
10.1 regression
10.2 regression
10.3 regression
catalog regressions
Cart/Checkout regressions if shared code touched
full suite
```

---

## Quality

Report:

```text
PHPUnit
PHPStan
Pint
composer audit
git diff --check
route:list
OpenAPI parse
```

---

## Group J status

Return:

```text
10.1 PASS
10.2 PASS
10.3 PASS
10.4 PASS/BLOCKED
10.5 READY/BLOCKED
10.6 NOT STARTED
10.7 NOT STARTED
10.8 NOT STARTED
```

---

# 154. Definition of Done

Phase 10.4 is complete when:

- `product_id` remains optional;
- `product_id=null` remains valid;
- custom requests do not query Product unnecessarily;
- supplied opaque Product ID is resolved server-side;
- numeric DB IDs are not accepted;
- Product slug is not accepted as `product_id`;
- the existing public Product visibility authority is reused;
- active Product is required;
- published Product is required;
- soft-deleted Product is rejected;
- active Category is required;
- hidden Product states are not disclosed to anonymous callers;
- Product type is derived server-side;
- public MADE_TO_ORDER Product is accepted;
- public IN_STOCK Product is rejected with `PRODUCT_NOT_REQUESTABLE`;
- the error uses HTTP 409 according to the frozen Request contract;
- MADE_TO_ORDER requestability does not depend on ProductStock;
- no Variant is required;
- zero-stock MADE_TO_ORDER remains requestable;
- no price is locked;
- no quote is produced;
- no `product_details` snapshot is invented;
- linked Request persists the correct Product FK;
- created resource exposes only safe `{id,name,slug}` Product summary;
- Product eligibility occurs after schema validation;
- malformed Product IDs do not hit the database;
- valid-looking missing Products use canonical public not-found behavior;
- Product eligibility failure creates no FurnitureRequest;
- Product eligibility failure creates no Order;
- Product eligibility failure creates no Payment;
- Product eligibility failure changes no inventory;
- successful Request still begins SUBMITTED;
- later Product changes do not prevent Request status lifecycle;
- MADE_TO_ORDER remains prohibited from Cart/Checkout;
- `PRODUCT_NOT_REQUESTABLE` is aligned across backend registry and frozen API documentation;
- no schema migration is added;
- no dependency is added;
- no frontend work occurs;
- REQ-001 remains gated until attachment support is complete;
- full regression suite remains green;
- PHPStan reports zero errors;
- Pint passes;
- Composer audit is clean;
- diff check passes.

---

# 155. Out of Scope

Do not implement:

```text
Phase 10.5 General Enquiries
Phase 10.6 attachment handling
Phase 10.7 staff/admin Request management
Phase 10.8 Group J closure tests

Product management CRUD
Product publication UI
Variant selection
inventory reservation
price quotation
Request→Order conversion
Cart
Checkout
ClickPesa
payments
delivery
notifications
frontend
```

---

# 156. STOP Condition

STOP when the Request domain can make this authoritative decision:

```text
product_id null/omitted
→ custom Request allowed

product_id supplied
→ resolve through current public Product visibility
    ├── missing/non-public → canonical not-found
    ├── public IN_STOCK → 409 PRODUCT_NOT_REQUESTABLE
    └── public MADE_TO_ORDER → linked Request allowed
```

with:

```text
no stock dependency
no Variant dependency
no price commitment
no commerce side effects
```

Do not continue automatically to Phase 10.5.

DO NOT COMMIT, STAGE OR PUSH.

The project owner handles all Git operations.
---

# Group J Tracking

```text
10.1 PASS — Furniture Request API foundation (ADR/BACKEND-043)
10.2 PASS — Request validation (ADR/BACKEND-044)
10.3 PASS — Request status lifecycle (ADR/BACKEND-045)
10.4 PASS — Product-linked requests (ADR/BACKEND-046)
10.5 READY — General enquiries
10.6 NOT STARTED — Attachment handling
10.7 NOT STARTED — Staff/admin request management
10.8 NOT STARTED — Request/enquiry tests
```

Group J is **not** complete. The public `POST /api/v1/requests` route remains
**STUB/GATED** (`config('requests.route_enabled') === false`) until Phase 10.6
attachment support is implemented.
