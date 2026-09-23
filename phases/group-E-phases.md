# Phase 5.11 — Catalog Tests

## Purpose

Close **Group E — Catalog and Inventory API** with comprehensive automated verification across:

```text id="y8av37"
Phase 5.1  Category Read API
Phase 5.2  Product Read API
Phase 5.3  Product Detail API
Phase 5.4  Variant API
Phase 5.5  Search / Filter API
Phase 5.6  Pagination / Sorting
Phase 5.7  Product Availability Rules
Phase 5.8  Inventory Read Model
Phase 5.9  Inventory Mutation Rules
Phase 5.10 Concurrency / Overselling Protection
```

This phase is primarily:

```text id="hjj6ao"
test consolidation
regression coverage
contract verification
cross-phase consistency checks
driver-specific verification
Group E exit assessment
```

Do not introduce new catalog or inventory features merely to make tests convenient.

---

# 1. Group E Exit Goal

Group E should prove that the backend can reliably:

```text id="kcf1q7"
browse categories
browse Products
retrieve Product detail
retrieve Variants
search/filter Products
sort/paginate Products
derive public availability
read operational inventory
adjust inventory safely
prevent overselling under concurrency
```

while preserving:

```text id="glw66m"
public/private data boundaries
authorization
database invariants
V1 API shape
deterministic behavior
```

---

# 2. Read Current Repository State First

Before adding or modifying tests, inspect:

```text id="xprlyi"
AGENTS.md
docs/VISION.md
docs/api/api-contract.md
docs/api/api-resources.md
docs/api/api-conventions.md
docs/api/openapi.yaml
docs/domain/business-rules.md
docs/decisions.md
```

Also inspect implementation and tests created during:

```text id="1sv0xy"
5.1
5.2
5.3
5.4
5.5
5.6
5.7
5.8
5.9
5.10
```

Do not recreate tests already providing good coverage.

Phase 5.11 should identify gaps and consolidate regression guarantees.

---

# 3. No Feature Expansion

Do not add:

```text id="mljs9i"
new Product filters
new sort modes
autocomplete
recommendation ranking
wishlists
reviews
warehouse management
bulk inventory
new inventory reasons
new catalog endpoints
new frontend features
```

because a test would otherwise be easier.

Test the agreed V1 system.

---

# 4. Test Layer Strategy

Use several appropriate levels:

```text id="xkf321"
Unit tests
Feature/API tests
Database/integration tests
MySQL-specific integration tests
Concurrency tests
```

Do not force every rule into one giant Feature test class.

---

# 5. Canonical Fast Suite

The normal repository test suite should continue to run primarily using:

```text id="p18sxw"
SQLite
```

where currently configured.

SQLite should verify:

```text id="2v9rwk"
request validation
API shapes
authorization
catalog visibility
filter composition
sorting
pagination
availability formulas
inventory invariants
mutation semantics
transaction rollback behavior
```

---

# 6. SQLite Must Not Be Overclaimed

SQLite does **not** prove:

```text id="563uix"
MySQL FULLTEXT behavior
InnoDB row locking
parallel last-unit reservation correctness
deadlock behavior
MySQL execution plans
```

The final Group E report must state this explicitly.

---

# 7. MySQL / MariaDB Supplementary Suite

Maintain a separate targeted integration suite for MySQL/MariaDB.

It should verify only behavior genuinely requiring the production database engine.

At minimum:

```text id="0v0dk4"
native FULLTEXT search
FULLTEXT migration/index existence
real concurrent ProductStock row locking
overselling race prevention
lost-update prevention
```

Do not run the entire repository through MySQL merely because these few behaviors require it unless existing CI already supports that reliably.

---

# 8. Existing MySQL Harness Risks

The repository has previously recorded MySQL test-harness failures caused by SQLite-specific assumptions and forked-connection behavior.

Do not interpret unrelated test harness failures as catalog-domain defects.

Classify:

```text id="agukvc"
application defect
database-specific test issue
environment/harness issue
known baseline
```

accurately.

---

# 9. CAT-003 — Category Collection Tests

Ensure coverage for:

```text id="ttj2rv"
GET /api/v1/categories
```

including:

```text id="xpgl7z"
anonymous access
active category visibility
inactive exclusion
correct level/root semantics
pagination
deterministic ordering
response envelope
Category Summary exact shape
image representation
no internal fields
```

---

# 10. CAT-004 — Category Detail Tests

Ensure coverage for:

```text id="i121kr"
GET /api/v1/categories/{category}
```

including:

```text id="eohdah"
lookup by slug
lookup by ID
same resource semantics
unknown category 404
inactive category 404
description
image
created_at
exact contracted representation
```

---

# 11. Category Public Access

Both CAT-003 and CAT-004 must work:

```text id="d67ht8"
without Clerk token
without local user
```

No auth side effects.

---

# 12. Category Internal Field Protection

Verify public responses do not leak fields such as:

```text id="t31gxf"
parent_id
space_type
display_order
is_active
internal graph relationships
```

unless any are explicitly part of the frozen response contract.

---

# 13. CAT-001 — Product Collection

Comprehensively test:

```text id="zph7kp"
GET /api/v1/products
```

as the canonical public Product discovery endpoint.

---

# 14. Product Summary Shape

Assert exact contracted Product Summary fields:

```text id="865udc"
id
name
slug
product_type
price
category
primary_image
availability
stock_indicator
```

Do not merely assert keys exist approximately.

---

# 15. Public Product Visibility

Cover:

```text id="utb96j"
active + published + not deleted
→ visible

inactive
→ hidden

unpublished
→ hidden

soft-deleted
→ hidden
```

---

# 16. Public Access by Actor

Verify CAT-001 representation remains equivalent for:

```text id="4fd5w1"
anonymous
CUSTOMER
STAFF
ADMIN
```

Public endpoints must not expose privileged fields merely because the caller happens to be authenticated.

---

# 17. Product Internal Field Protection

CAT-001 must not expose:

```text id="qsko36"
is_active
is_published
cost_price
raw stock
reserved_quantity
warehouse_location
internal file path
```

---

# 18. CAT-002 — Product Detail

Ensure:

```text id="t74hw0"
GET /api/v1/products/{product}
```

is covered by slug and ID.

---

# 19. Product Detail Shape

Verify:

```text id="c9hagg"
Product Summary fields
description
images[]
variants[]
created_at
updated_at
```

according to the finalized V1 shape.

---

# 20. CAT-001 / CAT-002 Consistency

For the same Product assert identical:

```text id="r8jlnc"
id
name
slug
product_type
price
category semantics
primary_image
availability
stock_indicator
```

between collection and detail.

---

# 21. Product Price Authority

Verify Product price:

```text id="7nzcn0"
comes from the authoritative active Variant pricing rule
```

and is consistent across:

```text id="swnnfk"
serialization
min/max filtering
price sorting
CAT-001
CAT-002
```

No `products.price` duplication.

---

# 22. Product Images

Verify:

```text id="nvthju"
ordered by sort_order ASC, id ASC
```

and public image fields only.

No:

```text id="3966y8"
file_path
storage secrets
```

may leak.

---

# 23. Primary Image Consistency

`primary_image` from Product Summary must agree with the corresponding primary gallery image in CAT-002.

---

# 24. Empty Product Images

A valid Product with no images should follow the finalized contract:

```text id="cykvtc"
images = []
```

where applicable.

Do not invent placeholder media in API tests.

---

# 25. CAT-005 — Variant Collection

Ensure:

```text id="d6xqwv"
GET /api/v1/products/{product}/variants
```

coverage includes:

```text id="gl9a5j"
anonymous access
parent by slug
parent by ID
active Variant inclusion
inactive Variant exclusion
deterministic ordering
empty collection
hidden parent masking
```

---

# 26. CAT-006 — Variant Detail

Ensure:

```text id="rxqntj"
GET /api/v1/products/{product}/variants/{variant}
```

coverage includes:

```text id="bve1zs"
correct parent
wrong parent
unknown Variant
inactive Variant
hidden Product
```

---

# 27. Variant Parent Masking

Mandatory regression:

```text id="abksrm"
Variant B belongs to Product B

GET /products/A/variants/B
→ 404
```

Never reveal cross-Product existence.

---

# 28. Variant Standalone Shape

Verify the finalized standalone representation exactly.

Expected core fields:

```text id="vvhybl"
id
product_id
sku
name
price
availability
stock_indicator
created_at
updated_at
```

subject to current reconciled docs.

---

# 29. Embedded Variant Shape

CAT-002 embedded Variant must remain narrower.

Verify it does not accidentally gain standalone/internal fields.

---

# 30. Variant Price Consistency

Same Variant price must be identical through:

```text id="hyvm1u"
CAT-002
CAT-005
CAT-006
```

---

# 31. Variant Availability Consistency

Same Variant must expose identical:

```text id="i3m5aa"
availability
stock_indicator
```

through:

```text id="p0or75"
CAT-002
CAT-005
CAT-006
```

---

# 32. Phase 5.5 Search Coverage

Keep comprehensive CAT-001 search regressions.

Search sources:

```text id="nodfqg"
Product.name
Product.description
active Variant SKU
approved active Variant attributes
```

---

# 33. Active Variant Search Regression

Mandatory:

```text id="g6d6yl"
active Product
inactive Variant
Variant SKU matches search
```

must **not** return the Product solely because of the inactive Variant.

This regression must remain permanently covered.

---

# 34. Active Attribute Search Regression

Same rule for approved Variant attributes:

inactive Variant attributes must not make a Product discoverable.

---

# 35. Product Name Search

Verify visible matching Product appears.

Hidden Product must not appear.

---

# 36. Product Description Search

Same visibility behavior.

---

# 37. SKU Search

Verify active Variant SKU returns its parent Product exactly once.

---

# 38. Variant Attribute Search

Verify only approved customer-meaningful attribute keys participate.

Do not implicitly make arbitrary JSON keys searchable.

---

# 39. Duplicate Search Suppression

If several active Variants match the same Product:

```text id="0lh0z8"
Product occurs once
pagination total counts once
```

---

# 40. Empty Search

Cover:

```text id="tfyg2a"
search=
search=whitespace
```

Both behave as unfiltered search where finalized in Phase 5.5.

---

# 41. Search Special Characters

Preserve regressions for literal:

```text id="8q52qq"
%
_
\
'
"
```

without SQL error or unintended wildcard semantics.

---

# 42. SQLite Search Fallback

SQLite tests should verify semantic inclusion/exclusion only.

Do not assert MySQL relevance behavior.

---

# 43. MySQL FULLTEXT Test

Real MySQL/MariaDB suite must verify:

```text id="yjwhve"
FULLTEXT index exists
name FULLTEXT match works
description FULLTEXT match works
non-match excluded
public visibility remains enforced
```

---

# 44. FULLTEXT Test Must Be Driver-Gated

Under SQLite:

```text id="2vszt0"
skip explicitly
```

Do not execute a LIKE fallback and label it the FULLTEXT test.

---

# 45. Product Filters

CAT-001 tests must cover:

```text id="gpdtfb"
category
product_type
availability
min_price
max_price
```

---

# 46. Filter Composition

Test realistic combinations such as:

```text id="macozv"
search + category
search + price
category + availability
product_type + availability
search + category + price + availability
```

All conditions combine using AND between filter groups.

---

# 47. Product Type

Verify exact CLOSED values:

```text id="c72rwg"
IN_STOCK
MADE_TO_ORDER
```

Reject invalid values.

---

# 48. Availability Filter

Verify:

```text id="rbaj0h"
available
unavailable
```

only.

Do not accept `LOW_STOCK` as the `availability` query.

---

# 49. Price Filter

Test:

```text id="ii3kvq"
min only
max only
range
boundary equality
invalid min > max
invalid negative
invalid float
```

against the same Product public price used in serialization.

---

# 50. Category Filter

Verify current canonical Category slug/ID behavior.

Do not add `/categories/{category}/products`.

---

# 51. Sorting Tests

Allowed:

```text id="2pnllf"
created_at
price
name
```

Reject everything else.

---

# 52. Default Sort

Verify:

```text id="exxl2j"
created_at DESC
id ASC
```

---

# 53. Sort Direction Defaults

Verify finalized defaults:

```text id="2wn3ak"
created_at → desc
name → asc
price → asc
```

unless current docs explicitly differ.

---

# 54. Tie-Breaker

For equal:

```text id="xmwo8z"
created_at
name
price
```

verify:

```text id="5knz86"
id ASC
```

---

# 55. Stable Pagination Across Ties

Populate enough tied records to span multiple pages.

Across stable DB state:

```text id="cfng8t"
no duplicate Product IDs
no missing Product IDs
```

---

# 56. Pagination Defaults

Verify:

```text id="d2lhcx"
page = 1
per_page = 20
```

---

# 57. Pagination Bounds

Verify:

```text id="soi60c"
per_page min 1
max 100
```

and invalid inputs fail canonically.

---

# 58. Pagination Metadata

Assert exact:

```text id="k68trz"
current_page
per_page
total
last_page
has_next
has_previous
```

No Laravel paginator internals.

---

# 59. Beyond Last Page

Preserve finalized Phase 5.6 behavior exactly.

If the implementation reports the clamped/actual current page differently than an earlier draft, tests must match the accepted Phase 5.6 ADR—not old assumptions.

Do not silently “fix” established behavior during Phase 5.11.

---

# 60. Empty Result Pagination

Test exact empty collection metadata.

---

# 61. Phase 5.7 Product Type Schema

Verify:

```text id="z104cm"
product_type exists
is_published exists
```

with finalized defaults/backfill behavior.

---

# 62. Product Type Casting

Verify canonical application values remain:

```text id="qk9bm2"
IN_STOCK
MADE_TO_ORDER
```

including MySQL's known case-insensitive enum storage caveat where relevant.

Application validation remains authoritative.

---

# 63. Publication Visibility

Regression coverage must ensure search/filter/detail cannot bypass:

```text id="rb9y7c"
is_published = true
```

for public Product APIs.

---

# 64. Availability — IN_STOCK Variant

Verify:

```text id="cqwk5q"
available_quantity > 0
→ available

available_quantity = 0
→ unavailable
```

---

# 65. Multi-Location Availability

Variant availability must aggregate all its relevant ProductStock rows correctly.

---

# 66. Reserved Quantity Matters

Example:

```text id="a5zd0r"
quantity = 10
reserved = 10
```

must be unavailable.

Do not treat physical quantity as sellable quantity.

---

# 67. Product Availability

IN_STOCK Product:

```text id="fh179j"
available if at least one active Variant has available stock
```

Inactive Variant inventory must be ignored.

---

# 68. MADE_TO_ORDER Availability

Verify finalized rule:

```text id="9ubodm"
availability = available
stock_indicator = MADE_TO_ORDER
```

independent of ProductStock quantity where otherwise public/valid.

---

# 69. LOW_STOCK Boundaries

Use the finalized centralized threshold.

If Phase 5.7 adopted:

```text id="9bx3p1"
5
```

test:

```text id="6zpqjk"
0
1
5
6
```

exactly.

Do not duplicate the threshold literal everywhere; tests may reference the domain constant when appropriate.

---

# 70. Product-Level LOW_STOCK

Verify aggregate active-Variant availability produces the finalized Product indicator.

---

# 71. No OUT_OF_STOCK Enum

Regression test that public API does not introduce:

```text id="d2ii4i"
OUT_OF_STOCK
```

if the frozen V1 enum remains:

```text id="xawfh3"
IN_STOCK
LOW_STOCK
MADE_TO_ORDER
```

---

# 72. Public Raw Inventory Protection

CAT-001 through CAT-006 must not expose:

```text id="vqj8fc"
quantity
reserved_quantity
available_quantity
warehouse_location
```

---

# 73. INV-001 Inventory Read Tests

Verify authenticated authorized operational inventory collection:

```text id="doikvr"
GET /api/v1/inventory
```

---

# 74. Inventory Authentication

Cover:

```text id="8i4avp"
anonymous → 401
CUSTOMER → forbidden
STAFF inventory.view → allowed
STAFF without permission → forbidden
ADMIN appropriate permission → allowed
```

---

# 75. Inventory Read Shape

Assert exact reconciled Inventory representation from Phase 5.8.

Do not use outdated pre-reconciliation OpenAPI assumptions.

---

# 76. Inventory Location

If Phase 5.8 finalized:

```text id="f0r2ub"
warehouse_location
```

as an operational field, verify it explicitly.

---

# 77. Inventory Product/Variant IDs

Verify:

```text id="wlym7l"
product_id
variant_id
```

map to the correct domain resources.

---

# 78. Inventory Quantity Formula

Verify:

```text id="9z4xzn"
available_quantity
=
quantity - reserved_quantity
```

---

# 79. Multi-Location Inventory Rows

Same Variant with two location rows must remain two Inventory resources.

No accidental aggregation in INV-001/002.

---

# 80. Zero Stock Operational Visibility

A persisted zero-stock ProductStock row should remain visible to authorized operations.

---

# 81. Hidden Product Operational Separation

If inventory exists for an inactive/unpublished Product:

authorized Inventory API should behave according to Phase 5.8 operational rules, not public Product scope.

---

# 82. INV-002 Detail Tests

Cover:

```text id="6l5ki0"
valid Inventory ID
unknown Inventory ID
wrong identifier type/format if validated
authorization
exact resource shape
```

---

# 83. Inventory Read Has No Side Effects

GET inventory endpoints must not alter:

```text id="j3qt1t"
quantity
reserved_quantity
timestamps
audit records
```

---

# 84. INV-003 Mutation Tests

Verify the full Phase 5.9 mutation contract.

---

# 85. Adjustment Authentication / Authorization

Same matrix as operational mutation:

```text id="hm33sm"
anonymous
CUSTOMER
Staff with permission
Staff without permission
Admin
```

---

# 86. Adjustment Input Surface

Only:

```text id="h2vjb6"
quantity_delta
reason
```

accepted.

Reject all server-controlled fields.

---

# 87. CLOSED Adjustment Reasons

Verify exactly:

```text id="nj7mfp"
STOCK_RECEIPT
CORRECTION
DAMAGE
RETURN
AUDIT_ADJUSTMENT
```

and no extra aliases.

---

# 88. Direction Rules

Test finalized Phase 5.9 direction semantics.

For example, if adopted:

```text id="c4ehdl"
STOCK_RECEIPT > 0
RETURN > 0
DAMAGE < 0
CORRECTION +/- non-zero
AUDIT_ADJUSTMENT +/- non-zero
```

Do not revert them here.

---

# 89. Quantity Invariants

Verify mutations can never produce:

```text id="y1rukl"
quantity < 0
quantity < reserved_quantity
```

---

# 90. Reserved Quantity Remains Untouched

Physical adjustments must never silently alter reservations.

---

# 91. Zero Quantity Row

Valid adjustment to physical quantity zero should leave the ProductStock row intact.

---

# 92. Mutation Response

Assert updated InventoryResource representation exactly.

---

# 93. Inventory Adjustment Audit

Every committed successful adjustment must create exactly one durable audit record.

---

# 94. Audit Actor Protection

Verify client cannot spoof audit actor.

---

# 95. Audit Rollback

If audit persistence is required in the same transaction and fails:

inventory mutation must roll back.

---

# 96. Idempotency Key Required

INV-003 without valid required key must not execute.

---

# 97. Same-Key Replay

Same actor/resource/key/body:

```text id="425iod"
one stock mutation
one audit event
same logical result
```

---

# 98. Same Key Different Intent

Different:

```text id="rhgm5t"
delta
reason
Inventory resource
```

under conflicting scope should follow finalized `DUPLICATE_OPERATION` semantics.

---

# 99. Rate-Limit Regression

Verify INV-003 remains attached to the approved operational mutation limiter.

Where the test setup supports it:

```text id="kdcexh"
429 includes Retry-After
```

---

# 100. Phase 5.10 Unit/Semantic Tests

Verify concurrency primitives in a deterministic single-process way first:

```text id="4gxsu8"
reserve
release
consume
adjust
multi-item rollback semantics
```

These tests are useful but are not concurrency proof.

---

# 101. Reservation Formula

Verify:

```text id="f3zwhn"
requested <= quantity - reserved
```

before reservation.

Then:

```text id="p4k28x"
reserved += requested
quantity unchanged
```

---

# 102. Insufficient Reservation

If requested exceeds authoritative available:

```text id="3swmc7"
INSUFFICIENT_STOCK
```

and no partial state mutation.

---

# 103. Cart Does Not Reserve

If Group F is not implemented yet:

retain domain-level protection and do not create Cart implementation just for this test.

Group E test should simply ensure no existing catalog read/cart-adjacent operation introduced reservation side effects.

---

# 104. Release Formula

Verify:

```text id="ijpeue"
reserved -= released
quantity unchanged
```

---

# 105. Consumption Formula

Verify:

```text id="m9g6w6"
quantity -= consumed
reserved -= consumed
```

and therefore available quantity stays unchanged.

---

# 106. Multi-Item Atomicity

If reservation primitive supports multiple Variants:

one insufficient line must roll back all reservations in that operation.

---

# 107. Allocation Persistence

If Phase 5.10 required a reservation allocation table for multi-location correctness:

add coverage for:

```text id="chl51y"
allocation quantities
correct ProductStock target
rollback
release
consume
immutability
```

If no allocation table was introduced, do not invent tests for one.

---

# 108. Real MySQL Concurrency Suite

This is mandatory for Phase 5.10 closure.

Use independent DB connections/processes.

SQLite cannot substitute.

---

# 109. Last Unit Race

Initial:

```text id="eowyoz"
quantity = 1
reserved = 0
```

Run two concurrent reservation attempts for 1.

Assert:

```text id="89lahg"
exactly one succeeds
exactly one fails
quantity = 1
reserved = 1
available = 0
```

---

# 110. Concurrent Capacity Race

Several callers request more units in total than exist.

Final committed reservation total must never exceed physical quantity.

---

# 111. Adjustment vs Adjustment Race

Verify no lost update.

Example:

```text id="07ebla"
quantity 10
+5
-3
```

final:

```text id="oezs6j"
12
```

if both succeed.

---

# 112. Adjustment vs Reservation Race

Verify no possible committed state violates:

```text id="up912t"
reserved_quantity <= quantity
```

---

# 113. Same-Key Concurrent Mutation

Two parallel identical INV-003 requests using one Idempotency-Key:

```text id="41j19n"
one actual adjustment
one audit event
one durable idempotent operation
```

---

# 114. Rollback Under Concurrent Failure

Where practical, prove failure does not leave a partial multi-row reservation.

---

# 115. Deadlock/Retry Behavior

If Phase 5.10 implemented bounded deadlock retry:

test it at the service level where reproducible.

Do not build fragile timing-only tests that randomly fail CI.

---

# 116. Concurrency Test Stability

Use explicit coordination/barriers.

Do not depend purely on arbitrary:

```text id="091hyd"
sleep(1)
```

timing.

---

# 117. Repetition

Run the critical last-unit race multiple times in the targeted integration test.

Use a reasonable repeat count such as:

```text id="umgd2c"
10–20
```

if runtime remains practical.

---

# 118. Database State Inspection

After every race inspect final:

```text id="f841u6"
quantity
reserved_quantity
available_quantity
audit count where relevant
idempotency records where relevant
```

Do not assert only response codes.

---

# 119. Global Inventory Invariant Assertion

After all inventory/concurrency tests verify:

```text id="xj4yjr"
quantity >= 0
reserved_quantity >= 0
reserved_quantity <= quantity
```

for affected rows.

---

# 120. Public Availability After Reservation

Verify Phase 5.7 reads respond to reserved stock.

Example:

```text id="u8eh9k"
quantity = 6
reserved = 0
→ available 6

reserve 1

quantity = 6
reserved = 1
→ available 5
```

and stock indicator updates according to finalized threshold.

---

# 121. Public Availability After Release

Released reservation must increase public available inventory on subsequent read.

---

# 122. Public Availability After Consumption

Converting reservation to sale must keep available quantity unchanged.

---

# 123. Public Availability After Manual Adjustment

INV-003 crossing stock boundaries should naturally alter public:

```text id="muq9h2"
availability
stock_indicator
```

without persisted Product availability fields.

---

# 124. No Catalog Mutation on Read

CAT-001..006 must not:

```text id="mr220k"
reserve inventory
touch ProductStock
change Product timestamps
create audit entries
```

---

# 125. Route Contract Test

Inspect route list or test routes to ensure expected Group E surface exists.

At minimum:

```text id="pezfqg"
GET /api/v1/categories
GET /api/v1/categories/{category}
GET /api/v1/products
GET /api/v1/products/{product}
GET /api/v1/products/{product}/variants
GET /api/v1/products/{product}/variants/{variant}
GET /api/v1/inventory
GET /api/v1/inventory/{inventory}
POST /api/v1/inventory/{inventory}/adjust
```

subject to the final Phase 5.9 route reconciliation.

---

# 126. Rejected Routes

Assert or manually verify no accidental V1 routes such as:

```text id="se4n64"
/api/v1/search
/api/v1/variants
/api/v1/categories/{category}/products
/api/v1/products/{product}/images
PATCH /api/v1/inventory/{inventory}
DELETE /api/v1/inventory/{inventory}
```

---

# 127. Response Envelope Consistency

Every successful single-resource endpoint uses:

```text id="4qx6yf"
{"data": {...}}
```

Collection:

```text id="qrktwz"
{"data": [...], "meta": {...}}
```

No custom wrappers unless explicitly frozen.

---

# 128. Error Envelope Consistency

Verify representative Group E failures contain the canonical:

```text id="eoggk8"
errors[]
meta.request_id
```

shape.

---

# 129. Error Code Stability

Exercise representative:

```text id="qwnst2"
RESOURCE_NOT_FOUND
INVALID_VALUE
MISSING_REQUIRED_FIELD
AUTHENTICATION_REQUIRED
FORBIDDEN
INSUFFICIENT_STOCK
DUPLICATE_OPERATION
RESOURCE_VERSION_CONFLICT
RATE_LIMITED
```

only where applicable.

Do not invent new error codes during the test phase.

---

# 130. 404 Masking

Ensure public nested resources retain masking behavior.

Do not reveal:

```text id="d8lqdr"
inactive object exists
unpublished object exists
Variant belongs to another Product
```

through differentiated error responses.

---

# 131. Authorization Regression

Inventory operational permissions must not bleed into customer-account administration.

A Staff member with:

```text id="tndim6"
inventory.manage
```

still receives no generic ability to modify Customers.

This may already be covered in Group D; ensure Group E changes did not weaken the permission boundary.

---

# 132. Private Cache Headers

Inventory endpoints should use the established private/no-store policy.

Public catalog remains public/cacheable where defined.

Do not mix these.

---

# 133. Serialization Allow-Lists

Add regression assertions against accidental Eloquent leakage.

Never trust:

```text id="i1pc0e"
toArray()
```

shape indirectly.

Test critical sensitive keys are absent.

---

# 134. Query Count / N+1 Tests

Where existing project conventions make query-count tests reliable, cover likely hotspots:

```text id="2uz5yj"
CAT-001 Product summaries
CAT-002 Product detail
CAT-005 Variant collection
INV-001 inventory collection
```

Focus on obvious regressions rather than brittle exact query counts across framework versions.

---

# 135. Search Pagination Count

Relationship search must not inflate paginator total when multiple Variants match.

Keep an explicit regression.

---

# 136. Availability Query Pagination Count

Stock joins/subqueries must not duplicate Product rows or inflate totals.

Keep an explicit regression.

---

# 137. Query Performance Smoke Checks

Do not turn Phase 5.11 into benchmarking.

Only verify grossly inefficient regressions such as N+1 or loading the full catalog before pagination.

---

# 138. Factory Reliability

Review factories used by catalog tests.

Ensure they generate explicit states for:

```text id="7fz6ky"
published
draft
active
inactive
IN_STOCK
MADE_TO_ORDER
Variant active/inactive
stocked/out-of-stock/reserved
```

where useful.

---

# 139. Avoid Fragile Random Fixtures

For contract tests, use deterministic values for:

```text id="k0m2id"
slug
SKU
prices
stock
display_order
timestamps
```

Do not depend on Faker ordering.

---

# 140. Test Data Isolation

Every test must create its own state or use safe test setup.

Do not rely on DemoSeeder for API correctness tests.

---

# 141. No Production Data

Never run integration tests against development/staging/production catalog data.

---

# 142. Disposable MySQL Safety

For destructive MySQL test setup require:

```text id="59wkmu"
APP_ENV != production
AND
DB_DATABASE == furnitureapp_test_disposable
```

or the exact current approved repository guard.

---

# 143. MySQL Cleanup

Destroy/reset only the approved disposable test database.

Do not touch configured application DBs.

---

# 144. OpenAPI Contract Review

Cross-check implemented Group E endpoints and schemas against:

```text id="59gqza"
docs/api/openapi.yaml
```

Tests should detect meaningful runtime drift where practical.

Do not build a massive custom OpenAPI testing framework.

---

# 145. Documentation Drift Review

Review:

```text id="5yg6gv"
api-contract.md
api-resources.md
api-conventions.md
business-rules.md
decisions.md
openapi.yaml
```

for consistency with the actual Phase 5.1–5.10 implementation.

Only correct genuine drift.

Do not rewrite historical architecture unnecessarily.

---

# 146. Known Phase 5.5 Status

Before declaring Group E complete, inspect current Phase 5.5 blocker status.

Previously:

```text id="1i60rm"
implementation complete
SQLite verified
BLOCKED pending:
- disposable MySQL/MariaDB FULLTEXT check
- PHPStan baseline resolution
```

If still unresolved:

Group E cannot honestly be reported fully clean.

---

# 147. FULLTEXT Closure

If the disposable MySQL harness is now available:

run the targeted FULLTEXT tests.

Then update Phase 5.5 status appropriately.

If not available:

leave Phase 5.5 BLOCKED.

---

# 148. Phase 5.10 Closure

Similarly:

if real MySQL concurrent tests have not run:

```text id="w8vsg2"
Phase 5.10 remains BLOCKED
```

regardless of SQLite semantic tests.

---

# 149. PHPStan Baseline Closure

Run:

```bash id="3qbb71"
vendor/bin/phpstan analyse
```

Distinguish:

```text id="hs2a28"
new Group E PHPStan errors
existing baseline errors
```

Do not attribute old failures to Phase 5.11.

---

# 150. No New PHPStan Errors

Required:

```text id="x73ab3"
new errors introduced by Group E = 0
```

---

# 151. If Baseline Is Now Clean

Report:

```text id="y9wuuw"
PHPStan PASS
```

and update prior blocked statuses if their only remaining blocker was the baseline and all other gates also pass.

---

# 152. If Baseline Still Fails

Report it honestly.

Do not weaken the exit criteria.

---

# 153. Pint

Run:

```bash id="mwx7dt"
vendor/bin/pint --test
```

Must pass.

---

# 154. Composer Audit

Run:

```bash id="tfpmcq"
composer audit
```

Report any blocker distinctly from test failures.

---

# 155. Git Diff Check

Run:

```bash id="h7wnke"
git diff --check
```

Must be clean for whitespace errors.

---

# 156. Full Canonical Suite

Run:

```bash id="u6emtx"
php artisan test
```

Record:

```text id="xxclca"
tests
assertions
failures
skipped
duration if useful
```

Do not report vague "tests pass".

---

# 157. Focused Catalog Suite

Where test organization permits, run Group E tests separately.

Example conceptually:

```text id="gwg7r0"
CategoryReadApiTest
ProductReadApiTest
ProductSearchTest
ProductAvailabilityTest
InventoryReadApiTest
InventoryAdjustmentTest
InventoryConcurrencyTest
```

Use actual filenames.

---

# 158. Test Naming

Tests should describe behavior, not implementation details.

Prefer:

```text id="hlmjdl"
inactive_variant_sku_does_not_surface_product
```

over:

```text id="y33u79"
whereHas_has_is_active
```

---

# 159. No Giant Test Class

If one test class becomes enormous, split by domain responsibility.

Do not create:

```text id="n340na"
CatalogEverythingTest.php
```

with hundreds of unrelated assertions.

---

# 160. Avoid Duplicating Existing Group C Schema Tests

Phase 5.11 should rely on existing Group C schema tests for:

```text id="7ly4dt"
foreign keys
column types
unique constraints
basic model invariants
```

Add only regressions required by Group E behavior.

---

# 161. Keep Schema Regression Suite Green

Run and preserve relevant existing tests such as:

```text id="tkyvzi"
Category schema
Product schema
ProductVariant schema
ProductImage schema
ProductStock schema
SchemaIntegrity
MigrationRebuild
```

or current equivalents.

---

# 162. Test Behavior, Not Laravel Internals

Avoid assertions tied to framework implementation details such as:

```text id="zo0adp"
exact generated SQL string
exact middleware array ordering
exact paginator class internals
```

unless the contract genuinely depends on them.

---

# 163. Database-Specific Assertions Are Isolated

MySQL-specific tests may inspect:

```text id="xdshs5"
FULLTEXT index
real lock semantics
```

but SQLite tests should not contain MySQL-specific SQL.

---

# 164. No Raw PRAGMA in MySQL Suite

Do not reuse SQLite-only:

```text id="21vhvi"
PRAGMA ...
```

in shared tests.

The repository already recorded this as a MySQL harness failure class.

---

# 165. No MySQL-Only DROP Syntax in Shared Tests

Do not put raw:

```text id="d6pwy8"
DROP CHECK
```

into shared driver-agnostic helpers without capability handling.

---

# 166. Forked Connection Safety

The repository previously recorded `pcntl` MySQL connection issues.

If concurrency tests use process forking:

each child must establish its own database connection after fork.

Do not reuse the parent's PDO connection.

---

# 167. Connection Reconnect

Explicitly purge/reconnect in each child/process when required.

The test should prove application locking, not fail because of inherited sockets.

---

# 168. Concurrency Test Timeout

Use bounded timeouts.

Do not allow a failed lock test to hang CI indefinitely.

---

# 169. Skips Must Be Honest

A skipped MySQL-specific test should include a clear reason such as:

```text id="s32wsz"
requires disposable MySQL/MariaDB integration database
```

Do not silently skip.

---

# 170. Skipped Required Gate Means BLOCKED

If a required Group E gate is skipped because infrastructure is unavailable:

the relevant phase/Group E status remains BLOCKED.

Skipped is not PASS.

---

# 171. Group E Security Regression

Check at least:

```text id="qubmmv"
public catalog no auth required
private inventory requires auth
Customer cannot access inventory
Staff requires explicit inventory permission
raw inventory never leaks publicly
cost price never leaks publicly
hidden Products remain hidden
wrong-parent Variant is masked
mutation fields cannot be mass-assigned
```

---

# 172. Group E Data Integrity Regression

Check:

```text id="kjv4l4"
price consistency
Variant ownership
quantity >= reserved
available derived
no overselling
idempotent adjustment
deterministic pagination
no duplicate Product search results
```

---

# 173. Group E Contract Regression

Check:

```text id="egqw2w"
paths
methods
query parameter names
enum values
response envelopes
pagination shape
error shape
public/private representations
```

---

# 174. No Frontend Tests

Phase 5.11 is backend Group E.

Do not modify or test:

```text id="hz985p"
Next.js UI
MUI components
Flutter
design-system
```

Frontend groups come later.

---

# 175. No Browser E2E Yet

Do not introduce Playwright/Cypress/Appium solely for Group E.

API/backend tests are sufficient here.

System E2E belongs Group S.

---

# 176. No Load Testing

Do not add performance/load infrastructure in this phase.

Group S/T can later test sustained traffic.

---

# 177. No Mutation Fuzzing Framework

Use representative boundary/security cases.

Do not introduce a property-testing dependency unless already part of the project.

---

# 178. No External Dependencies Expected

Expected:

```text id="kqkz0z"
new Composer packages = NONE
```

Use PHPUnit/Laravel existing tooling.

---

# 179. Schema Changes Expected

Expected:

```text id="6hhkbn"
NONE
```

unless Phase 5.10 already required a narrowly justified allocation table.

Phase 5.11 itself should not redesign schema.

---

# 180. Code Changes During Test Phase

If a test discovers a genuine bug:

fix the smallest underlying defect.

Do not merely weaken the test.

Document:

```text id="cp0a6m"
bug
root cause
fix
regression test
```

---

# 181. Contract Conflict During Testing

If implementation and docs disagree:

determine which Phase decision is authoritative.

Do not blindly change tests to whatever runtime currently does.

Resolve genuine drift minimally.

---

# 182. Preserve Accepted Phase Decisions

Examples:

```text id="0qv1nn"
active-Variant-only relationship search
Phase 5.6 paginator behavior
Phase 5.7 product_type/publication rules
Phase 5.8 Inventory identity
Phase 5.9 canonical adjust route
Phase 5.10 lock strategy
```

must not be reopened casually.

---

# 183. Decisions Record

Add a Group E closure ADR/entry only if repository conventions use one.

It should summarize:

```text id="pjmfcl"
test scope
driver split
critical regressions
remaining blockers
Group E status
```

Do not duplicate the entire test suite in prose.

---

# 184. Recommended Test Matrix

Prepare a concise implementation matrix internally:

```text id="ew5psa"
Requirement
Endpoint/service
SQLite coverage
MySQL coverage
Status
```

Use it to identify gaps.

It does not need to become a runtime artifact unless project documentation benefits.

---

# 185. Required MySQL-Only Gates

At minimum mark these as MySQL-specific:

```text id="fdrxng"
native FULLTEXT
FULLTEXT index
last-unit concurrent reservation
concurrent adjust lost-update protection
adjust-vs-reservation race
```

---

# 186. Required SQLite-Compatible Gates

At minimum:

```text id="dvny7w"
all CAT contract tests
search semantics fallback
filters
sorting
pagination
availability
Inventory read auth
Inventory mutation rules
idempotency semantics
audit semantics
transaction rollback
```

---

# 187. Status Model

At completion report status separately for:

```text id="30v51j"
Phase 5.1
Phase 5.2
Phase 5.3
Phase 5.4
Phase 5.5
Phase 5.6
Phase 5.7
Phase 5.8
Phase 5.9
Phase 5.10
Phase 5.11
Group E overall
```

Do not hide blocked subphases inside an overall PASS.

---

# 188. Phase 5.5 Status Rule

Phase 5.5 becomes PASS only if:

```text id="v5sqb3"
SQLite semantics pass
AND
real MySQL/MariaDB FULLTEXT integration passes
AND
applicable PHPStan quality gate is clean
```

---

# 189. Phase 5.10 Status Rule

Phase 5.10 becomes PASS only if:

```text id="n6naoc"
business semantics pass
AND
real MySQL/MariaDB concurrent race tests pass
```

SQLite-only is insufficient.

---

# 190. Group E Overall PASS Rule

Group E may be marked:

```text id="llpfb6"
PASS
```

only when all required Group E phases have passed their own mandatory gates.

If MySQL infrastructure remains unavailable:

report:

```text id="xquq2f"
Group E implementation complete
Group E verification BLOCKED
```

rather than pretending full completion.

---

# 191. Known Baseline Reporting

If PHPStan baseline is still unresolved:

report:

```text id="tvufw0"
Group E introduced errors: 0
Repository baseline: BLOCKED / N existing
```

with exact current numbers where available.

---

# 192. Test Failure Classification

Every failure discovered during closure should be categorized as:

```text id="v1mq47"
Group E defect
pre-existing defect
test defect
environment issue
database-driver incompatibility
known baseline
```

Do not lump everything together.

---

# 193. No Ignored Failures

Do not annotate failing Group E contract tests as:

```text id="qhjmt0"
@doesNotPerformAssertions
@skip
```

merely to finish the phase.

Required failing behavior means the phase is not complete.

---

# 194. Quality Commands

Run:

```bash id="wvktk1"
php artisan test
vendor/bin/pint --test
vendor/bin/phpstan analyse
composer audit
git diff --check
php artisan route:list
```

and the targeted MySQL/MariaDB integration suite separately.

---

# 195. Test Counts

Report exact:

```text id="f6unk6"
SQLite test count
SQLite assertions
SQLite failures
SQLite skipped

MySQL targeted test count
MySQL assertions
MySQL failures
MySQL skipped
```

where available.

---

# 196. Group E Completion Report

Return:

## Phase 5.11 status

```text id="ne787l"
PASS
```

or:

```text id="mq3ezb"
BLOCKED
```

## Public Catalog

Report:

```text id="x0krow"
CAT-001
CAT-002
CAT-003
CAT-004
CAT-005
CAT-006
```

status.

## Search

Report:

```text id="15dacq"
SQLite semantics
active Variant restriction
MySQL FULLTEXT
```

separately.

## Pagination / Sorting

Report deterministic ordering and metadata coverage.

## Availability

Report:

```text id="w0vod1"
IN_STOCK
LOW_STOCK
MADE_TO_ORDER
reserved-stock effect
```

coverage.

## Inventory Read

Report authentication/authorization and exact quantity coverage.

## Inventory Mutation

Report:

```text id="z4ictm"
delta rules
CLOSED reasons
invariants
idempotency
audit
```

coverage.

## Concurrency

Report:

```text id="q7lz24"
last-unit race
lost-update race
adjust-vs-reserve
MySQL engine used
iterations
```

## Security

Report public/private leakage tests.

## SQLite

Explicitly state what SQLite proves.

## MySQL/MariaDB

Explicitly state what real DB tests prove.

## PHPStan

Report new errors separately from baseline.

## Schema

Expected Phase 5.11 changes:

```text id="d038zd"
NONE
```

## Dependencies

Expected:

```text id="prszd5"
NONE
```

## Frontend

Must state:

```text id="qtsjam"
NONE
```

## Quality

Report:

```text id="xxiehw"
Pint
PHPStan
Composer audit
git diff --check
```

---

# 197. Group E Final Status Table

Return a concise table conceptually like:

```text id="ojd5qf"
5.1   PASS
5.2   PASS
5.3   PASS
5.4   PASS
5.5   PASS/BLOCKED
5.6   PASS
5.7   PASS
5.8   PASS
5.9   PASS
5.10  PASS/BLOCKED
5.11  PASS/BLOCKED

Group E: PASS/BLOCKED
```

Use actual results only.

Do not assume PASS.

---

# 198. Definition of Done

Phase 5.11 is complete when:

* every implemented Group E endpoint has focused API coverage;
* CAT-001..006 public behavior is regression-tested;
* category visibility is covered;
* Product publication/activation masking is covered;
* Product Summary and Detail shapes are exact;
* Variant parent ownership masking is covered;
* embedded vs standalone Variant representations are protected;
* Product/Variant price consistency is covered;
* image/path leakage is covered;
* search name/description/SKU/attribute behavior is covered;
* inactive Variant relationship search regression is permanently covered;
* search special-character behavior is covered;
* SQLite fallback is explicitly treated as semantics only;
* native MySQL FULLTEXT has a separate integration gate;
* filters compose correctly;
* sorting is allow-listed and deterministic;
* pagination totals do not duplicate Products;
* pagination metadata matches V1;
* Product Type filtering works;
* publication rules work;
* IN_STOCK availability works;
* MADE_TO_ORDER availability works;
* LOW_STOCK boundaries are covered;
* reservations affect availability correctly;
* exact raw inventory remains operational-only;
* Inventory read authorization is covered;
* Inventory row/location identity is covered;
* Inventory mutation authorization is covered;
* quantity delta/reason rules are covered;
* quantity cannot fall below zero;
* quantity cannot fall below reserved quantity;
* reserved quantity cannot be silently changed by manual adjustment;
* inventory idempotency is covered;
* inventory audit is covered;
* real MySQL concurrency proves the last-unit race;
* concurrent adjustments do not lose updates;
* adjust-vs-reserve race preserves `reserved <= quantity`;
* public catalog reads never reserve stock;
* no sensitive operational fields leak publicly;
* all required Group C regression tests remain green;
* route inventory matches finalized Group E surface;
* OpenAPI/docs have no known Group E drift;
* full SQLite suite passes;
* required MySQL-specific tests pass or status remains BLOCKED;
* no new PHPStan errors exist;
* Pint passes;
* Composer audit has no new blocker;
* no frontend changes are made.

---

# 199. Out of Scope

Do not implement:

```text id="dk24gw"
Group F cart workflows
Group G checkout endpoint
Group H payment integration
order cancellation implementation
frontend catalog
frontend inventory management
end-to-end browser tests
load tests
accessibility tests
production monitoring
```

Those belong to later groups.

---

# 200. STOP Condition

STOP when Group E has an evidence-based status supported by automated tests.

The final state must clearly distinguish:

```text id="4strvx"
application semantics proven on SQLite
```

from:

```text id="16iszd"
MySQL FULLTEXT proven on MySQL/MariaDB
```

and:

```text id="5wirfr"
inventory concurrency proven under real parallel MySQL/MariaDB transactions
```

No skipped mandatory database-specific verification may be reported as PASS.

Do not continue automatically to Group F.

DO NOT COMMIT OR PUSH.

The project owner handles all Git operations.
