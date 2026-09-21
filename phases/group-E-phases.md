# Phase 5.6 — Pagination / Sorting

## Purpose

Complete and harden the pagination and sorting behavior for the public catalog:

```text
CAT-001
GET /api/v1/products
```

using the conventional V1 e-commerce model already frozen in the API contract:

```text
page-based pagination
+
configurable page size
+
total result count
+
allow-listed sorting
+
deterministic secondary ordering
```

This phase must integrate with the existing Phase 5.5 pipeline:

```text
search
→ filter
→ sort
→ deterministic tie-breaker
→ paginate
```

Do not introduce cursor pagination, infinite-scroll-specific API contracts, or a second collection endpoint.

---

# 1. Preserve Existing V1 Contract

The authoritative request parameters remain:

```text
page
per_page
sort
sort_direction
```

combined with the already-supported:

```text
search
category
availability
min_price
max_price
```

Do not rename them.

Do not introduce aliases such as:

```text
pageSize
page_number
limit
offset
order
orderBy
sortBy
direction
```

---

# 2. Standard Pagination Strategy

Use conventional offset/page pagination through Laravel's normal paginator.

Preferred implementation:

```text
Laravel paginate()
```

or the project's existing equivalent.

Do not manually implement:

```text
OFFSET calculations
total-count arithmetic
last-page arithmetic
```

unless there is a concrete framework limitation.

---

# 3. Do Not Introduce Cursor Pagination

Do not add:

```text
cursor
after
before
next_cursor
previous_cursor
```

in V1.

Cursor pagination may be useful for high-volume feeds, but CAT-001 already requires:

```text
current_page
per_page
total
last_page
has_next
has_previous
```

which naturally fits page-based pagination.

---

# 4. Why Page Pagination Fits This Store

Preserve page pagination because catalog browsing benefits from:

```text
page URLs
total product counts
jump-to-page behavior
SEO-compatible query URLs
back-button restoration
desktop catalog navigation
mobile pagination/infinite-scroll adaptation
```

The frontend may later visually implement:

```text
numbered pagination
Load More
infinite scrolling
```

while still consuming the same page-based backend API.

Do not change the backend contract merely because the frontend interaction differs.

---

# 5. Pagination Parameters

`page`:

```text
optional
integer
1-based
minimum = 1
default = 1
```

`per_page`:

```text
optional
integer
minimum = 1
maximum = 100
default = 20
```

Use the exact frozen values.

---

# 6. Strict Validation

Reject invalid input.

Examples:

```text
?page=0
?page=-1
?page=1.5
?page=abc

?per_page=0
?per_page=-20
?per_page=101
?per_page=abc
```

must return the canonical validation error.

Do not silently clamp malformed values unless current API conventions explicitly require it.

---

# 7. Avoid Large Page Sizes

Maximum:

```text
100
```

is a hard API limit.

Do not allow:

```text
per_page=500
per_page=1000
```

for convenience.

This protects:

```text
DB load
serialization cost
network payload
mobile clients
shared caching
```

---

# 8. Pagination Must Run Last

The pipeline is mandatory:

```text
1. public visibility
2. search
3. filters
4. sort
5. id tie-breaker
6. paginate
```

Never:

```text
paginate
→ search/filter in PHP
```

---

# 9. Filtering Before Count

Pagination totals must describe the **filtered result set**, not the entire Product table.

Example:

```text
search=oak
category=dining-room
```

If 17 Products match:

```text
total = 17
```

not total catalog size.

---

# 10. No In-Memory Pagination

Do not:

```php
Product::all()
```

and then call collection pagination helpers.

Filtering/sorting/pagination belong in SQL.

---

# 11. Standard Response Envelope

CAT-001 must return:

```json
{
  "data": [],
  "meta": {
    "pagination": {
      "current_page": 1,
      "per_page": 20,
      "total": 0,
      "last_page": 1,
      "has_next": false,
      "has_previous": false
    }
  }
}
```

No Laravel-specific paginator internals should leak.

---

# 12. Exact Metadata Fields

Only expose the approved metadata:

```text
current_page
per_page
total
last_page
has_next
has_previous
```

Do not casually add:

```text
from
to
first_page_url
last_page_url
next_page_url
prev_page_url
path
links[]
```

unless the V1 contract is deliberately changed.

---

# 13. No Pagination Links Yet

Pagination URLs are intentionally deferred.

Do not return:

```text
links.next
links.previous
```

in Phase 5.6.

Clients can construct query requests using the metadata.

---

# 14. Empty Catalog

For zero results:

```text
data = []
total = 0
current_page = 1
last_page = 1
has_next = false
has_previous = false
```

Use the exact current convention.

---

# 15. Page Beyond Last Page

Example:

```text
total = 41
per_page = 20
last_page = 3
```

Requesting:

```text
?page=50
```

must return:

```text
200
data = []
```

with valid pagination metadata.

Do not return 404.

---

# 16. `has_next`

Derive semantically from:

```text
current_page < last_page
```

through Laravel paginator metadata where possible.

Do not maintain a duplicate manual state.

---

# 17. `has_previous`

Equivalent semantic rule:

```text
current_page > 1
```

subject to existing empty-result convention.

Prefer paginator authority.

---

# 18. Sorting Allow-List

Public Product sorting supports only:

```text
created_at
price
name
```

Do not allow arbitrary database columns.

---

# 19. Sort Mapping

Map public sort values explicitly to trusted query expressions.

Conceptually:

```text
created_at
→ products.created_at

name
→ products.name

price
→ authoritative derived public Product price expression
```

Never:

```php
orderBy($request->sort)
```

without an allow-list.

---

# 20. Default Sorting

When `sort` is absent:

```text
created_at DESC
id ASC
```

This is the default catalog display ordering.

Preserve it.

---

# 21. Name Sorting

When:

```text
sort=name
```

default direction:

```text
ASC
```

unless `sort_direction` explicitly overrides it.

---

# 22. Price Sorting

When:

```text
sort=price
```

default direction:

```text
ASC
```

unless overridden.

Use the authoritative Product public price expression established in Phase 5.2.

---

# 23. Created Date Sorting

When:

```text
sort=created_at
```

default direction:

```text
DESC
```

unless overridden.

---

# 24. Sort Direction

Accepted values:

```text
asc
desc
```

Case-insensitive input may normalize to lowercase if the current request contract allows that.

Do not accept:

```text
ascending
descending
1
-1
random
```

---

# 25. Direction Without Sort

Inspect the current contract/request implementation.

Preferred behavior if already established:

```text
sort_direction without sort
→ applies to the default created_at sort
```

only if that matches current API docs.

Otherwise reject or ignore consistently according to the existing contract.

Do not invent ambiguous behavior in Phase 5.6.

---

# 26. Deterministic Secondary Ordering

Every Product collection ordering must end with:

```text
id ASC
```

unless `id` is already the unique primary sort.

Examples:

```sql
ORDER BY created_at DESC, id ASC
```

```sql
ORDER BY name ASC, id ASC
```

```sql
ORDER BY derived_price ASC, id ASC
```

This is mandatory.

---

# 27. Why Tie-Breaking Matters

Many Products may share:

```text
same name
same price
same created_at
```

Without a unique tie-breaker:

```text
Product can drift between pages
duplicate across pages
disappear between pages
```

when SQL chooses an undefined equal-value order.

Always use `id ASC`.

---

# 28. Tie-Breaker Direction

The frozen contract explicitly uses:

```text
id ASC
```

regardless of primary sort direction.

Therefore:

```text
created_at DESC, id ASC
price DESC, id ASC
name DESC, id ASC
```

are valid.

Do not automatically make ID direction match the primary sort.

---

# 29. Derived Price Sorting

Product does not own its own price column.

Do not add:

```text
products.price
```

for sorting convenience.

Sort using the exact same authoritative derived Product price that CAT-001 serializes.

---

# 30. Price Consistency Invariant

For a Product:

```text
serialized Product price
=
value used for price filtering
=
value used for price sorting
```

This is mandatory.

Do not maintain three separate pricing calculations.

---

# 31. Price Sorting Must Stay in SQL

Do not:

```text
load page candidates
serialize their price
sort them in PHP
```

That produces incorrect pagination.

Sorting must happen before pagination in the DB query.

---

# 32. Null Price Semantics

Review current Product price derivation.

If a public Product can legitimately lack a usable active Variant/derived price:

use the already-approved visibility/price semantics.

Do not invent arbitrary:

```text
NULL FIRST
NULL LAST
price = 0
```

rules.

Ideally public Product eligibility already prevents an invalid public pricing state.

---

# 33. Search + Sorting

Search results still use the frozen sort contract.

Example:

```text
?search=oak&sort=price&sort_direction=asc
```

means:

```text
match search
→ apply filters
→ price ASC
→ id ASC
→ paginate
```

---

# 34. Search Without Sort

Do not silently introduce relevance ordering during Phase 5.6.

Existing default remains:

```text
created_at DESC
id ASC
```

unless an explicit future API decision adds relevance sorting.

---

# 35. Phase 5.5 Search Integration

Do not rewrite Phase 5.5 search logic.

Reuse its existing Product catalog query.

Preserve:

```text
native MySQL FULLTEXT
variant SKU search
approved variant attribute search
active Variant restriction
```

---

# 36. Active Variant Search Regression

Do not regress the corrected rule:

```text
whereHas('variants')
```

must only consider:

```text
is_active = true
```

Variants for SKU/attribute matching.

Inactive Variants must never make a Product appear in public search.

---

# 37. Sorting Must Not Reactivate Hidden Results

Sorting/pagination only operate on Products already satisfying:

```text
public visibility
search
filters
```

Never expand the query set.

---

# 38. Filters + Sorting

Examples that must work correctly:

```text
?category=living-room&sort=name
```

```text
?availability=available&sort=price&sort_direction=desc
```

```text
?min_price=50000000&max_price=150000000&sort=price
```

Sorting never changes filter semantics.

---

# 39. Product Type Dependency

If `product_type` remains scheduled for Phase 5.7:

do not change that here.

Phase 5.6 simply preserves the query composition point.

---

# 40. Publication Dependency

Likewise:

```text
is_published
```

remains Phase 5.7 if still deferred.

Pagination must reuse shared public scope so publication can later be added once without pagination changes.

---

# 41. Stable Public Scope

Preferred architecture:

```text
Product public scope/query
→ search
→ filters
→ sort
→ paginate
```

Do not duplicate visibility conditions in paginator code.

---

# 42. Public Only

CAT-001 remains:

```text
PUBLIC_READ
```

Pagination does not require authentication.

---

# 43. No User-Specific Sort

Do not add:

```text
recommended
for_you
recently_viewed
personalized
```

sort modes.

Those would make a public cacheable endpoint user-dependent.

---

# 44. No Popularity Sort

Do not introduce:

```text
best_selling
most_popular
trending
rating
```

without explicit business/data contracts.

V1 supports only:

```text
created_at
price
name
```

---

# 45. No Random Sort

Do not implement:

```text
?sort=random
```

Random ordering:

```text
breaks deterministic pagination
hurts caching
is expensive
causes duplicates
```

---

# 46. No Client-Supplied SQL

Never map arbitrary request strings directly into:

```text
column name
raw ORDER BY
direction
```

Use explicit mappings.

---

# 47. Index Use

Review existing indexes for:

```text
created_at
name
```

and Product query behavior.

Do not automatically add indexes unless query analysis shows a real need.

---

# 48. Price Sorting Index Limitation

Derived price may not be directly indexable because it comes from Variants.

Do not denormalize Product price merely to get an index in V1.

Use the current efficient aggregate/subquery implementation.

Measure before redesigning.

---

# 49. Pagination Count Query

Laravel pagination generally performs:

```text
COUNT query
+
page SELECT
```

This is expected.

Do not prematurely replace `paginate()` with `simplePaginate()` because V1 requires:

```text
total
last_page
```

---

# 50. Do Not Use `simplePaginate()`

`simplePaginate()` omits the total count.

It therefore cannot satisfy the frozen response contract.

Use normal pagination.

---

# 51. Do Not Use Cursor Pagination

Similarly, `cursorPaginate()` does not match the V1 metadata contract.

Do not use it.

---

# 52. Performance Expectations

For the expected V1 catalog scale:

```text
normal Laravel paginate()
+
indexed filters
+
bounded Product summary
```

is appropriate.

Do not optimize for millions of Products prematurely.

---

# 53. Offset Pagination Limitation

Document, but do not overengineer around, the known property of page/offset pagination:

if Product data changes between requests, page membership may shift.

This is acceptable for a live retail catalog.

Do not implement:

```text
snapshot tokens
catalog versions
transactional multi-page browsing
```

---

# 54. Concurrent Catalog Changes

If a Product is added/removed between:

```text
page 1
page 2
```

the result set may shift.

That is normal V1 behavior.

The deterministic tie-breaker addresses ambiguous SQL ordering, not live-data mutation.

---

# 55. Query String Compatibility

Filters and sort parameters must remain usable together with page values.

Example:

```text
/products
?search=oak
&category=dining-room
&sort=price
&sort_direction=asc
&page=2
&per_page=20
```

must work as one canonical request.

---

# 56. Frontend URL Stability

The API should support straightforward website URLs such as:

```text
/products?page=3&sort=price&sort_direction=asc
```

without special server state.

Do not introduce session-based pagination.

---

# 57. Cache Keys

Public cache identity naturally depends on the complete query string:

```text
search
filters
sort
page
per_page
```

Do not manually cache all combinations in Phase 5.6.

---

# 58. Response Resource

Continue using:

```text
ProductSummaryResource
```

from Phase 5.2.

Pagination must not change Product representation.

---

# 59. No Paginator Internals

Do not expose Laravel keys such as:

```text
first_page_url
last_page_url
next_page_url
prev_page_url
path
links
from
to
```

unless explicitly approved.

Transform paginator metadata into the frozen API representation.

---

# 60. Avoid Double Pagination

Do not call `paginate()` inside a helper and again in the controller.

The query should be paginated exactly once.

---

# 61. Avoid Double Sorting

Sorting should have one authoritative application point.

Do not:

```text
apply default sort in model scope
then apply user sort in controller
```

without clearing/reconciling previous order clauses.

---

# 62. Explicit Sort Application

Ensure custom sort replaces the default primary sort rather than stacking unexpectedly.

Expected:

```text
sort=price
→ price <direction>, id ASC
```

not:

```text
created_at DESC,
price ASC,
id ASC
```

unless that ordering is explicitly intended.

---

# 63. Query Builder Structure

Prefer extending the focused catalog query from Phase 5.5.

Conceptually:

```text
applySearch()
applyFilters()
applySort()
paginate()
```

Do not introduce another:

```text
ProductPaginationService
```

if the existing query object already owns collection composition.

---

# 64. Pagination Serialization Helper

A small reusable pagination metadata mapper is acceptable if the project already has several paginated endpoints.

For example conceptually:

```text
PaginationMeta
```

or:

```text
PaginationResource
```

But do not build an elaborate pagination framework.

---

# 65. Reuse Global Pagination Conventions

If the backend already has pagination response helpers from earlier phases:

reuse them.

CAT-001 should not create a different metadata shape from:

```text
CAT-003
ORD-001
NOT-001
```

later.

---

# 66. Validation Layer

Keep pagination/sort validation in the existing CAT-001 FormRequest.

Do not validate:

```text
page
per_page
sort
sort_direction
```

inside the controller.

---

# 67. Strict Types

Do not broadly coerce malformed values.

Follow current query validation conventions.

Examples:

```text
page=1
```

valid.

```text
page=1.0
```

should follow current integer validation policy.

---

# 68. Unknown Sort Field

Examples:

```text
?sort=sku
?sort=id
?sort=cost
?sort=inventory
?sort=deleted_at
```

must return:

```text
422 INVALID_VALUE
```

if not in the approved allow-list.

---

# 69. Internal Field Protection

Sorting must not become a side-channel that exposes internal fields.

Do not allow sorting by:

```text
cost_price
reserved_quantity
is_active
is_published
warehouse quantity
```

---

# 70. Case Handling

Follow the frozen request semantics.

If `sort_direction` is case-insensitive:

normalize:

```text
ASC → asc
DESC → desc
```

Do not necessarily make `sort` field names case-insensitive unless docs say so.

---

# 71. Invalid `per_page` Is Not Silently Capped

Do not turn:

```text
per_page=1000
```

into:

```text
100
```

unless the existing global convention says to clamp.

Preferred frozen behavior is validation error.

---

# 72. Test — Default Pagination

Seed more than 20 visible Products.

Request:

```text
GET /products
```

Assert:

```text
current_page = 1
per_page = 20
correct total
correct last_page
correct has_next
has_previous = false
```

---

# 73. Test — Custom `per_page`

Example:

```text
?per_page=10
```

Verify metadata and 10-or-fewer Product results.

---

# 74. Test — Page 2

Verify:

```text
?page=2
```

returns the expected second window.

---

# 75. Test — Last Page

Verify:

```text
has_next = false
has_previous = true
```

for a multi-page collection's last page.

---

# 76. Test — Beyond Last Page

Verify:

```text
200
data = []
```

and correct metadata.

---

# 77. Test — Zero Results

Use a filter/search producing no Products.

Assert the exact zero-result convention.

---

# 78. Test — Invalid Page

Cover:

```text
0
-1
non-integer
```

---

# 79. Test — Invalid Per Page

Cover:

```text
0
101
negative
non-integer
```

---

# 80. Test — Default Sort

Create Products with deliberately different timestamps.

Verify:

```text
created_at DESC
id ASC
```

---

# 81. Test — Created Date Ascending

Request:

```text
?sort=created_at&sort_direction=asc
```

Verify order.

---

# 82. Test — Name Ascending

Request:

```text
?sort=name
```

Verify default:

```text
ASC
```

---

# 83. Test — Name Descending

Request:

```text
?sort=name&sort_direction=desc
```

Verify exact reverse primary ordering plus stable ID tie-break.

---

# 84. Test — Price Ascending

Seed Products with known derived Variant prices.

Verify public price order.

---

# 85. Test — Price Descending

Verify:

```text
?sort=price&sort_direction=desc
```

---

# 86. Test — Price Source Consistency

For every test Product:

```text
sort value
=
ProductSummaryResource price
```

Do not assert against an unrelated Variant price.

---

# 87. Test — Tie on Name

Create multiple visible Products with the same name.

Verify final ordering by:

```text
id ASC
```

---

# 88. Test — Tie on Price

Create multiple Products with the same derived price.

Verify:

```text
id ASC
```

---

# 89. Test — Tie on Created At

Create Products with identical timestamps.

Verify:

```text
id ASC
```

---

# 90. Test — No Duplicate Between Pages

Use tied primary sort values spanning multiple pages.

Verify no Product ID appears on both pages.

---

# 91. Test — No Missing Product

Across a stable DB state, concatenate all pages.

Verify all expected Product IDs appear exactly once.

---

# 92. Test — Search + Pagination

Use Phase 5.5 search.

Verify totals/pages include only matching results.

---

# 93. Test — Search + Sort

Example:

```text
?search=oak&sort=price
```

Verify search runs before sort.

---

# 94. Test — Filter + Pagination

Example:

```text
?category=living-room&per_page=5
```

Ensure total reflects only filtered Products.

---

# 95. Test — Combined Query

Add at least one realistic full-pipeline regression:

```text
search
category
price filter
availability where authoritative
sort
sort_direction
page
per_page
```

Verify the final result set and metadata.

---

# 96. Test — Active Variant Search Restriction

Preserve the Phase 5.5 regression:

An active Product with:

```text
inactive Variant matching search
```

must not appear unless another active Variant or Product text matches.

Pagination/sorting changes must not weaken this behavior.

---

# 97. Test — Duplicate Variant Search Match

If multiple active Variants match one Product:

the Product appears only once and counts once toward:

```text
total
```

This is especially important for paginator totals.

---

# 98. COUNT Correctness

Relationship search must not cause:

```text
total
```

to count duplicate Product rows.

Verify this explicitly.

---

# 99. MySQL vs SQLite

Phase 5.6 sorting/pagination semantics should work on both where possible.

However, Phase 5.5 remains subject to the existing MySQL FULLTEXT verification gate.

Do not claim MySQL FULLTEXT is verified merely because Phase 5.6 tests pass on SQLite.

---

# 100. SQLite Coverage

SQLite may validly prove:

```text
pagination
metadata
sort allow-list
sort direction
tie-breaking
price sorting semantics
filter composition
duplicate suppression
active-Variant search semantics
```

using the existing deterministic search fallback.

---

# 101. MySQL FULLTEXT Blocker Remains Separate

Until the disposable MySQL/MariaDB Phase 5.5 integration check succeeds:

```text
Phase 5.5 remains BLOCKED
```

Phase 5.6 implementation may still be independently correct.

Do not rewrite this status.

---

# 102. PHPStan Baseline

The previously reported project PHPStan baseline issue also remains a Phase 5.5/quality blocker until resolved.

Phase 5.6 must:

```text
not introduce additional PHPStan errors
```

and should report the existing baseline separately if still present.

---

# 103. Phase 5.6 Status Classification

If Phase 5.6's own implementation/tests pass but global PHPStan is still failing from a known pre-existing baseline:

report:

```text
Phase 5.6 implementation: PASS
Global static-analysis gate: BLOCKED by existing PHPStan baseline
```

or use the repository's established status convention.

Do not hide pre-existing failures.

---

# 104. No MySQL-Specific Pagination Rewrite

Do not branch pagination behavior by DB driver.

Only the Phase 5.5 FULLTEXT search mechanism needs the MySQL/SQLite distinction.

Pagination/sorting semantics should remain shared.

---

# 105. Query Performance

Use database pagination.

Do not load full filtered result sets into memory just to compute:

```text
total
pages
sorting
```

---

# 106. Query Plan Review

Where a disposable MySQL environment becomes available, optionally inspect the combined query plan for:

```text
default sort
name sort
FULLTEXT + pagination
```

but do not make this a mandatory Phase 5.6 blocker unless performance is clearly poor.

---

# 107. No Premature Keyset Optimization

Do not replace offset pagination because deep-page offset becomes slower at massive scale.

The expected V1 catalog does not justify that complexity.

---

# 108. No Maximum Page Cap

Do not introduce arbitrary:

```text
page <= 100
```

unless approved.

Very high pages may return empty data naturally.

---

# 109. No Stateful Pagination Tokens

Do not store pagination state server-side.

Every request is self-contained.

---

# 110. No Sorting Preferences Storage

Do not persist a customer's preferred sort order.

Frontend-local preference may be considered later.

---

# 111. Public Cache Safety

Pagination and sorting remain public/user-neutral.

Same URL/query should produce the same representation for:

```text
anonymous
Customer
Staff
Admin
```

subject only to catalog changes.

---

# 112. No Privileged Product Fields

Sorting as Admin through public CAT-001 must not cause:

```text
is_active
is_published
inventory quantity
cost
```

to appear.

---

# 113. OpenAPI

Verify OpenAPI accurately documents:

```text
page
per_page
sort
sort_direction
```

including:

```text
defaults
bounds
allowed sort fields
allowed directions
pagination metadata
```

Do not add new query fields.

---

# 114. Documentation

Update consolidated API docs only if runtime behavior differs from current documentation.

Do not create a second pagination specification.

---

# 115. Standard Customer-Facing Sort Mapping

Frontend later may label the existing sort combinations as:

```text
Newest
Name: A–Z
Name: Z–A
Price: Low to High
Price: High to Low
```

Backend remains:

```text
sort=created_at&sort_direction=desc

sort=name&sort_direction=asc

sort=name&sort_direction=desc

sort=price&sort_direction=asc

sort=price&sort_direction=desc
```

Do not encode display labels into the API.

---

# 116. Do Not Add "Recommended"

Many established stores have:

```text
Recommended
Featured
Best Selling
```

but those require ranking/merchandising data.

Do not imitate them without a business model.

The existing three sorts are enough for V1.

---

# 117. No Frontend Work

Do not modify:

```text
frontend/web/
frontend/app/
frontend/design-system/
```

No pagination component or sorting dropdown belongs here.

---

# 118. No Schema Change Expected

Expected:

```text
Schema changes:
NONE
```

Pagination/sorting should operate on existing Phase 5.2–5.5 query structures.

---

# 119. Do Not Add Denormalized Sort Columns

Do not add:

```text
sort_price
catalog_rank
popularity
search_rank
```

to Product.

---

# 120. No New Dependencies

Expected:

```text
Composer dependencies:
NONE
```

Laravel pagination/query builder is sufficient.

---

# 121. Code Quality

Maintain:

```text
cognitive complexity <= 15
<= 3 returns where practical
explicit sort maps
small query methods
no raw untrusted ORDER BY
minimal comments
```

---

# 122. Likely Implementation Areas

Likely changes should be limited to:

```text
CAT-001 FormRequest
ProductCatalogQuery
pagination metadata/resource helper
Product collection controller/resource
tests
docs where required
```

Do not broaden the phase.

---

# 123. Verification

Run focused Phase 5.6 tests first.

Then:

```bash
php artisan test
vendor/bin/pint --test
vendor/bin/phpstan analyse
composer audit
git diff --check
```

Report known pre-existing PHPStan failures separately from new failures.

---

# 124. No Destructive Migration Expected

Do not run:

```text
migrate:fresh
```

merely for pagination/sorting.

If another phase's integration setup requires it, use the existing disposable-database safety rules.

---

# 125. Completion Report

Return:

## Phase 5.6 status

```text
PASS
```

or:

```text
BLOCKED
```

with exact reason.

## Pagination

Report:

```text
page default
per_page default/max
metadata structure
beyond-last behavior
empty-result behavior
```

## Sorting

Report:

```text
created_at
price
name
default directions
id ASC tie-breaker
```

## Price sorting

State the authoritative derived-price expression reused.

## Search integration

Confirm:

```text
search → filter → sort → paginate
```

and active-Variant relationship search remains enforced.

## Duplicate protection

State how relationship search avoids duplicate Product rows and inflated totals.

## SQLite

State which pagination/sorting semantics were verified under SQLite.

## MySQL

Do not conflate Phase 5.6 with the outstanding Phase 5.5 FULLTEXT integration check.

## PHPStan

Report whether:

```text
new Phase 5.6 errors = 0
```

and separately identify any existing project baseline blocker.

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

Report exact focused/full counts.

## Quality

Report:

```text
Pint
PHPStan
Composer audit
git diff --check
```

---

# 126. Definition of Done

Phase 5.6 is complete when:

* CAT-001 uses standard 1-based page pagination;
* default page is 1;
* default per-page is 20;
* maximum per-page is 100;
* invalid pagination inputs are rejected;
* pagination runs after search/filter/sort;
* metadata is exactly under `meta.pagination`;
* `current_page` is correct;
* `per_page` is correct;
* `total` counts distinct matching Products;
* `last_page` is correct;
* `has_next` is correct;
* `has_previous` is correct;
* zero-result behavior matches the V1 contract;
* beyond-last-page requests return empty data rather than 404;
* no Laravel paginator internals leak;
* no pagination URLs are added;
* sorting is allow-listed;
* allowed fields remain `created_at`, `price`, and `name`;
* sort directions remain `asc`/`desc`;
* default catalog order remains `created_at DESC, id ASC`;
* name and price default to ascending when explicitly selected;
* every non-unique primary sort appends `id ASC`;
* price sorting uses the same Product price used for filtering/serialization;
* pagination does not duplicate Products across stable pages;
* relationship search does not inflate paginator totals;
* inactive Variant matches do not influence public search;
* search/filter/sort/page composition works;
* no cursor pagination is introduced;
* no random/popularity/recommended sorting is invented;
* no Product schema changes are introduced;
* no new dependency is added;
* no frontend code is modified;
* focused pagination/sort tests pass;
* existing CAT-001 search/filter tests remain green;
* Phase 5.2–5.5 catalog regressions remain green;
* no new PHPStan errors are introduced;
* Pint passes;
* Composer audit has no blocker.

---

# 127. Out of Scope

Do not implement:

```text
cursor pagination
keyset pagination
Load More API
infinite-scroll-specific API
pagination links
random sorting
relevance sorting
best-selling sorting
popularity sorting
recommended sorting
ratings sorting
frontend pagination
frontend sort selector
analytics
catalog snapshots
```

---

# 128. STOP Condition

STOP when CAT-001 behaves like a conventional production e-commerce collection API:

```text
public query
→ search
→ filters
→ approved sort
→ deterministic id tie-break
→ page-based pagination
→ ProductSummary collection
→ meta.pagination
```

with stable results for unchanged catalog state, correct distinct totals, and no schema/API expansion.

Do not continue automatically to Phase 5.7.

DO NOT COMMIT, STAGE OR PUSH.

The project owner handles all Git operations.
