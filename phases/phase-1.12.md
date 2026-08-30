# Phase 1.12 — Define Pagination Convention

## 1. Purpose

Phase 1.12 establishes the project's **standard pagination strategy** for API collections.

The goal is to make collection responses predictable and consistent across:

* Next.js website
* Flutter mobile application
* Admin application
* future API consumers

This phase defines:

* pagination style;
* page numbering;
* page-size behavior;
* maximum page size;
* defaults;
* metadata;
* navigation information;
* empty-page behavior;
* ordering requirements;
* consistency considerations;
* cursor pagination policy;
* resource-specific pagination exceptions.

This phase does **not** yet define pagination for every individual endpoint and does not implement pagination in Laravel.

---

# 2. Dependency Position

The current sequence is:

```text id="1f4x3c"
1.1  API Scope
       ↓
1.2  Domain Nouns
       ↓
1.3  Domain Invariants
       ↓
1.4  Logical Data Model
       ↓
1.5  Logical Model Review + Freeze
       ↓
1.6  API Resource Inventory
       ↓
1.7  API Resource Relationships
       ↓
1.8  API Versioning
       ↓
1.9  Endpoint Naming
       ↓
1.10 HTTP Method Conventions
       ↓
1.11 Query Parameter Conventions
       ↓
1.12 Pagination Convention
       ↓
1.13 Response Envelope
       ↓
...
```

Pagination must remain compatible with the query conventions from Phase 1.11.

---

# 3. Authoritative Project Paths

The current authoritative project files are:

```text id="o5qj9v"
AGENTS.md
docs/VISION.md
```

Do not revert to older names.

---

# 4. Important Payment Assignment

Payment-specific implementation remains assigned to:

**Phase Group H**

not Group G.

Do not assign payment work to another phase in this document.

---

# 5. Enum Policy Reminder

All Version 1 enums are:

> **CLOSED by default.**

This applies to pagination-related enums if any are later introduced.

Do not reintroduce the previous `OPEN/EXTENSIBLE` interim rule.

---

# 6. Authoritative Inputs

Read:

```text id="j3i2lr"
AGENTS.md
docs/VISION.md

phase-1.1-api-scope.md
phase-1.2-domain-nouns.md
phase-1.3.md
phase-1.4.md

logical-data-model-v1.md
freeze-record.md

api-resource-inventory.md
api-resource-relationships.md
resource-relationship-map.md

api-versioning-strategy.md
api-breaking-change-policy.md

endpoint-naming-conventions.md
canonical-api-vocabulary.md
resource-path-policy.md
action-naming-policy.md
resource-nesting-policy.md

http-method-conventions.md
http-method-resource-matrix.md
api-action-method-policy.md
api-idempotency-candidates.md

query-parameter-conventions.md
query-filtering-policy.md
query-sorting-policy.md
query-search-policy.md
query-relationship-policy.md
query-security-policy.md
query-enum-policy.md
```

Use actual filenames where the project has renamed any document.

---

# 7. Core Pagination Principle

Pagination is a **collection retrieval concern**.

It must not change the meaning of an individual resource.

For example:

```text id="8us8bi"
/products
```

is a collection.

Pagination determines which subset of that collection is returned.

It does not turn:

```text id="zv3h2b"
/products/{product}
```

into a paginated resource.

---

# 8. Choose the Version 1 Pagination Strategy

Evaluate:

```text id="o25fvr"
OFFSET/PAGE PAGINATION
CURSOR PAGINATION
HYBRID
```

Consider:

```text id="5pnpgt"
catalog size
admin order size
Flutter implementation simplicity
Next.js implementation simplicity
database performance
stable ordering
mobile network usage
future growth
```

For the current small-scale business, prefer the simplest strategy that remains technically sound.

---

# 9. Recommended Version 1 Baseline

Unless project evidence requires otherwise:

> **Use page-number/offset pagination for normal Version 1 collections.**

A practical convention is:

```text id="q4y5pw"
?page=1
&per_page=20
```

This is easy for:

* Next.js
* Flutter
* Laravel
* administrative tables

to implement.

Do not introduce cursor pagination just because it is fashionable.

---

# 10. Page Numbering

Use one-based numbering:

```text id="keo7o0"
page=1
page=2
page=3
```

Do not use zero-based numbering.

Reason:

* more intuitive for humans;
* easier for UI pagination controls;
* common e-commerce expectation.

Record this as a project-wide rule.

---

# 11. Page Size Parameter

Use the query parameter established by Phase 1.11.

Recommended:

```text id="66f6ci"
per_page
```

Example:

```text id="e3y9h0"
/products?page=2&per_page=24
```

Do not create different names on different endpoints such as:

```text id="5rsv2l"
limit
page_size
items_per_page
count
```

Use one project-wide convention.

---

# 12. Default Page Size

Define one global default page size.

A reasonable starting value for this project is:

```text id="2lvu2s"
20 items
```

This should be formally recorded as the Version 1 default unless performance testing or UI requirements justify another value.

The default must be documented rather than left to framework behavior.

---

# 13. Maximum Page Size

Define a maximum page size.

Recommended starting point:

```text id="6yq2f0"
100
```

Therefore:

```text id="h5mpg0"
per_page <= 100
```

A request such as:

```text id="ocfao3"
?per_page=1000
```

must not cause the server to return 1000 records simply because the client requested it.

The exact maximum can be tuned later based on performance evidence, but it must exist.

---

# 14. Minimum Page Size

Define the minimum accepted `per_page`.

Recommended:

```text id="j8l5t1"
per_page >= 1
```

Examples:

```text id="qkjaw3"
per_page=0
```

should be invalid rather than treated ambiguously.

---

# 15. Invalid Pagination Values

Document behavior for:

```text id="2q3d3n"
page=0
page=-1
page=abc

per_page=0
per_page=-10
per_page=abc
per_page=1000
```

Recommended:

> Invalid pagination values should produce a validation error rather than silently changing to defaults.

Do not define HTTP status/error JSON here.

---

# 16. Missing Pagination Parameters

When omitted:

```text id="4kt9qk"
page
```

uses:

```text id="l8j3yk"
page = 1
```

When omitted:

```text id="a3j008"
per_page
```

uses the global default.

This should be consistent across paginated collections.

---

# 17. Pagination Only for Collections

Pagination applies to collections such as:

```text id="d40c9u"
products
categories where appropriate
orders
notifications
requests
enquiries
admin lists
```

It generally does not apply to:

```text id="n8k6at"
single Product
single Order
single Request
single Enquiry
single Payment
```

---

# 18. Resource-Specific Pagination Decision

Not every collection must be paginated merely because pagination exists.

For each collection later, decide (single vocabulary: `REQUIRED / DEFERRED (=OPTIONAL) / NOT NEEDED` — `DEFERRED` is the project implementation of `OPTIONAL`; see `pagination-resource-policy.md` §2):

```text id="k5t55w"
REQUIRED
DEFERRED (= OPTIONAL)
NOT NEEDED
```

Examples likely:

### Products

```text REQUIRED
```

A catalog will grow.

### Orders

```text REQUIRED
```

History grows indefinitely.

### Notifications

```text REQUIRED
```

Potentially many records.

### Product Images

May not need independent pagination when embedded in a product.

### Product Variants

Likely small collections; pagination may be unnecessary for normal product detail.

Do not hardcode these endpoint decisions in Phase 1.12; document them as candidates for the later endpoint contract.

---

# 19. Pagination Response Metadata

Define the standard pagination metadata.

At minimum the client should be able to determine:

```text id="9jhqto"
current page
page size
total items
total pages
```

Conceptually:

```json id="bu4flq"
{
  "current_page": 2,
  "per_page": 20,
  "total": 86,
  "last_page": 5
}
```

This is illustrative only.

The exact response envelope is defined in Phase 1.13.

---

# 20. Pagination Navigation Information

Evaluate whether the API should return navigation information such as:

```text id="evpy6w"
first
last
previous
next
```

Potentially:

```text id="h5f1b8"
{
  "current_page": 2,
  "last_page": 5,
  "links": {
    "first": "...",
    "prev": "...",
    "next": "...",
    "last": "..."
  }
}
```

Decide whether the Version 1 API should expose:

```text id="5j2j5s"
full navigation URLs
```

or simply numeric metadata.

For a multi-client API, navigation information can reduce client-side URL construction errors.

Record the decision.

---

# 21. Absolute vs Relative Pagination Links

If pagination links are included, decide whether they are:

```text id="of9q2l"
absolute URLs
```

or:

```text id="v4zknd"
relative paths
```

For a distributed application with:

```text id="2tfoh2"
Next.js
Flutter
Admin
```

absolute URLs can be convenient, but they may become problematic across environments.

Evaluate:

```text local
staging
production
```

and document the chosen convention.

Do not hardcode production URLs.

---

# 22. Query Preservation

Pagination navigation must preserve relevant query parameters.

Example:

```text id="n4h0wi"
/products
?category=sofas
&sort=price
&sort_direction=asc
&page=2
```

The next-page link must not accidentally remove:

```text id="frp7qm"
category=sofas
sort=price
```

The pagination design should preserve the active query context.

---

# 23. Query Parameter Ordering in Links

Pagination links should be deterministic.

Parameter ordering should not affect semantics.

The project may choose a canonical serialization order later.

Do not make clients depend on parameter ordering.

---

# 24. Empty Collections

Define what happens when a page contains no items.

Example:

```text id="sgifp8"
GET /products?page=10
```

when there are only 3 pages.

The API must define whether this means:

```text id="rur4tz"
empty result with normal pagination metadata
```

or:

```text id="x2z26s"
validation/resource-range error
```

For a predictable collection API, prefer a consistent behavior across resources.

A strong recommendation is:

> A valid collection request for a page beyond the last page returns an empty collection with valid pagination metadata rather than an error.

Record the final decision.

---

# 25. Empty Dataset

A collection with zero total items should still have valid pagination metadata.

Conceptually:

```text id="tqgyqu"
items = []
total = 0
current_page = 1
last_page = 1 or 0 according to the selected convention
```

Choose one convention and document it.

Avoid inconsistent behavior where one endpoint reports:

```text last_page = 0
```

and another:

```text last_page = 1
```

for an empty collection.

---

# 26. Recommended Empty Collection Convention

For client simplicity, prefer:

```text id="v5s63q"
total = 0
current_page = 1
last_page = 1
items = []
```

and:

```text has_previous = false
has_next = false
```

if boolean navigation metadata is used.

This avoids awkward page-zero logic in Next.js and Flutter.

Record this as a recommended Version 1 convention.

---

# 27. Stable Ordering Requirement

Pagination is only reliable when collection ordering is deterministic.

For example:

```text id="z6x8iy"
ORDER BY created_at
```

may still be ambiguous if two records have the same timestamp.

Therefore paginated queries should eventually use a stable tie-breaker.

Conceptually:

```text id="6z1yvo"
primary sort
+
unique deterministic tie-breaker
```

Do not implement SQL ordering yet.

Record this as a mandatory data/query implementation principle.

---

# 28. Pagination + Sorting

Pagination must work with sorting.

For example:

```text id="xhoqph"
/products?sort=price&sort_direction=asc&page=2
```

The same sort order must be maintained across pages.

The API must not reorder records unpredictably between requests.

---

# 29. Pagination + Filtering

Filtering must apply before pagination.

Conceptually:

```text id="v9s2sm"
All Products
    ↓
Filter
    ↓
Sort
    ↓
Paginate
```

not:

```text id="1f5xj0"
All Products
    ↓
Paginate
    ↓
Filter
```

otherwise page counts and user expectations become incorrect.

---

# 30. Pagination + Search

Likewise:

```text id="oa8vlw"
Search
    ↓
Filter
    ↓
Sort
    ↓
Paginate
```

is the logical model.

Do not paginate an unrelated dataset and then apply search in the client.

---

# 31. Pagination + Availability

For product availability filtering:

```text id="kh17hv"
availability
```

must be evaluated using current authoritative data at request time.

Do not rely on stale client-side inventory state when determining which items belong to the collection.

---

# 32. Pagination + Changing Data

Offset/page pagination has a known issue:

```text id="vm68dr"
Page 1 retrieved
↓
records inserted/deleted
↓
Page 2 retrieved
```

The customer may see:

* duplicates;
* missing records;
* shifted positions.

For the current small-scale application, document that:

> Normal page pagination is acceptable for Version 1 collection browsing where slight movement between requests is tolerable.

However, for critical operational workflows or very rapidly changing large collections, cursor pagination may later become appropriate.

Do not implement cursor pagination yet.

---

# 33. Cursor Pagination Evaluation

Evaluate whether any Version 1 resource genuinely needs cursor pagination.

Potential future candidates:

```text id="g0a2m5"
very large order history
very high-volume notification stream
high-volume event/activity feeds
```

For the current business scale, likely:

```text id="xvdx8e"
No Version 1 resource requires cursor pagination.
```

Record this only after reviewing actual data-growth expectations.

---

# 34. Hybrid Pagination Policy

Do not mix pagination styles arbitrarily.

If cursor pagination is eventually introduced, define where it is required and why.

Example:

```text id="shj8zc"
Products → page pagination
Orders → page pagination
Notifications → cursor pagination
```

would require explicit justification.

Do not create a resource-by-resource preference without a clear technical/business reason.

---

# 35. Pagination Token Security

If cursor pagination is later introduced, cursor values must not expose:

* database secrets;
* sensitive information;
* implementation details unnecessarily.

This is a future requirement only.

Do not design cursor encoding now if Version 1 does not use cursors.

---

# 36. Pagination Count Performance

Offset pagination often requires counting total records.

For large collections, `COUNT(*)` may become expensive.

For the current project:

> Exact totals are acceptable for Version 1 unless performance testing demonstrates otherwise.

If the system later scales significantly, it may move to:

```text id="34gv7s"
approximate totals
no total
cursor-based traversal
```

without changing the basic domain model.

Record this as a future performance consideration.

---

# 37. Pagination Limits as Security Controls

Pagination limits are also a resource-protection mechanism.

The API must prevent:

```text id="a9uq4v"
?per_page=100000
```

from causing unreasonable database/memory usage.

The maximum page size must therefore be enforced server-side.

---

# 38. Admin Pagination

Admin collections should follow the same pagination conventions.

Examples:

```text id="0cwc7q"
orders
products
customers
requests
enquiries
```

Do not create an entirely different pagination grammar for admin.

The admin may have different default `per_page` values only if explicitly justified.

---

# 39. Flutter Pagination

The convention must support:

```text id="b0yeo5"
pull-to-refresh
infinite scroll
"load more"
page navigation
```

even though Flutter may present the UI differently from the website.

The API should remain platform-neutral.

---

# 40. Next.js Pagination

The website may use pagination for:

```text id="7aaoop"
product listings
category listings
search results
```

The API should support deterministic page retrieval.

For SEO, public catalog pagination should also be compatible with the website's canonical route/query strategy.

SEO implementation is later.

---

# 41. Admin Table Pagination

The admin dashboard should be able to use:

```text id="9od0ll"
page
per_page
filters
sorting
```

together.

Pagination metadata must work consistently with Phase 1.11 query conventions.

---

# 42. Pagination and Authorization

Pagination must not allow a user to infer or access unauthorized records.

For example:

```text id="9w81d0"
/me/orders?page=2
```

must contain only the authenticated user's authorized orders.

Page counts must also be calculated over the authorized dataset, not the entire database.

---

# 43. Pagination and Data Exposure

Do not expose:

```text id="i2kt7p"
total_users
total_orders
```

to an unauthorized customer merely because they asked for a paginated collection.

Pagination metadata must reflect only the collection the requester is allowed to see.

---

# 44. Pagination and Caching

Pagination parameters form part of the cache key.

For example:

```text id="s7di6e"
/products?page=1
```

must not be confused with:

```text id="p5hy20"
/products?page=2
```

Filtering/sorting parameters also form part of the logical collection identity.

Caching implementation comes later.

---

# 45. Pagination and Closed Enums

If a paginated collection is filtered by a Version 1 enum:

```text id="t3rp28"
?product_type=IN_STOCK
```

the enum remains closed.

Unknown values must not silently produce arbitrary behavior.

Example:

```text id="cbt9s6"
?product_type=SOMETHING_NEW
```

must be treated as an unsupported value according to the future validation/error contract.

---

# 46. Pagination Metadata Naming

Choose canonical metadata names.

Recommended:

```text id="mwpokd"
current_page
per_page
total
last_page
```

Potential optional values:

```text id="axzbc1"
from
to
has_next
has_previous
```

Do not mix:

```text id="knq2a6"
currentPage
page_size
totalItems
lastPage
```

inside the same API.

The response envelope is finalized in Phase 1.13.

---

# 47. Pagination Metadata: Prefer Useful, Not Excessive

Do not include ten different pagination fields merely because a framework provides them.

For Version 1, prioritize:

```text id="6odjrt"
current_page
per_page
total
last_page
has_next
has_previous
```

Then add navigation links only if the project decides they meaningfully simplify clients.

---

# 48. Pagination Collection Shape

The actual response envelope is deferred to Phase 1.13.

However, define the conceptual structure:

```text id="h0tpv3"
response
├── items/data
└── pagination metadata
```

Do not decide the final `data`/`meta` JSON shape yet.

---

# 49. Pagination Resource-Specific Exceptions

Any resource that does not use normal page pagination must explicitly document why.

Example:

```text id="6h8hqt"
Resource: notifications
Pagination: cursor
Reason: high append frequency
```

Do not allow silent exceptions.

---

# 50. Query Contract Example

Conceptual example:

```text id="ng1v4k"
/api/v1/products
    ?page=2
    &per_page=24
    &category=sofas
    &sort=price
    &sort_direction=asc
```

This should mean:

```text id="2mqfom"
1. Select products matching category
2. Apply allowed sort
3. Return page 2
4. Return at most 24 items
```

The precise response is defined later.

---

# 51. Pagination Ordering Rule

Every paginated collection endpoint must eventually define an ordering.

Do not allow:

```text id="sm1zuj"
database natural order
```

to become an undocumented API behavior.

Recommended conceptual requirement:

> Every paginated collection has a deterministic default ordering.

---

# 52. Pagination Stability Rule

If the primary sort field is not unique, later implementation should add a stable deterministic tie-breaker.

For example (canonical V1 direction is `id ASC` — see `pagination-convention.md` §9 and `query-sorting-policy.md` §6; `DESC` tie-breaker is not used):

```text id="bbvhkw"
created_at DESC
+
unique identifier ASC  (= id ASC)
```

This is an implementation consideration, not yet a SQL design. The tie-breaker is always `id ASC` regardless of primary `sort_direction` to keep pagination stable (supersedes any `DESC` illustration).

---

# 53. Pagination and Deleted Records

If records disappear between page requests, the API should behave according to the current dataset.

The client must not assume that page numbers represent permanently fixed snapshots.

For ordinary Version 1 browsing, current-state pagination is acceptable.

For future workflows requiring snapshot consistency, define that separately.

---

# 54. Pagination and Transactions

Do not attempt to make the entire product/order collection globally transactional across multiple HTTP page requests.

Pagination is a read operation over evolving data.

Snapshot-consistent pagination should only be introduced if a real business requirement exists.

---

# 55. Required Deliverables

Produce:

## A. `pagination-convention.md`

Include:

```text id="c0c0d0"
pagination strategy
page numbering
page size
default
maximum
minimum
invalid values
empty results
sorting requirements
```

---

## B. `pagination-metadata-policy.md`

Define the canonical metadata fields:

```text id="pa5mxn"
current_page
per_page
total
last_page
has_next
has_previous
```

and optional navigation data.

---

## C. `pagination-resource-policy.md`

Classify Version 1 collections as:

```text id="qnz9fi"
paginated
not paginated
pagination decision deferred to resource contract
```

---

## D. `pagination-consistency-policy.md`

Document:

```text id="3i0h1r"
stable ordering
filter-before-pagination
sort-before-pagination
changing-data behavior
authorized-dataset counting
```

---

## E. `pagination-security-policy.md`

Document:

```text id="os8lkm"
page-size limits
authorization
query abuse
resource exhaustion
information leakage
```

---

## F. `pagination-cursor-policy.md`

Document:

* whether cursor pagination is used in Version 1;
* if not, why it is deferred;
* criteria that would justify introducing it later.

Recommended Version 1 starting position:

```text
Cursor pagination is not required initially.
```

---

## G. `pagination-decisions.md`

Record all final decisions.

Format:

```text id="r2tc7j"
Decision ID
Decision
Reason
Alternatives
Affected resources
Future phase impact
```

---

# 56. Version 1 Recommended Defaults

Unless later review finds evidence otherwise, evaluate these as the baseline:

```text id="j5c2cy"
Pagination strategy:
Page-number pagination

First page:
1

Default per_page:
20

Maximum per_page:
100

Minimum per_page:
1

Invalid page/per_page:
Validation error

Unknown pagination parameters:
Validation error

Ordering:
Deterministic and documented

Filtering:
Applied before pagination

Sorting:
Applied before pagination

Empty page:
Valid empty collection

Cursor pagination:
Deferred for Version 1
```

The agent must formally record whether each baseline is accepted.

---

# 57. Validation Checklist

Before completing:

### Strategy

* [ ] Version 1 pagination strategy is explicitly selected.
* [ ] Cursor pagination has been evaluated.
* [ ] No unnecessary hybrid strategy exists.

### Page numbering

* [ ] Pages begin at 1.
* [ ] `page` behavior is consistent.
* [ ] `per_page` behavior is consistent.

### Limits

* [ ] Default page size exists.
* [ ] Maximum page size exists.
* [ ] Minimum page size exists.
* [ ] Limits are server-enforced.

### Errors

* [ ] Invalid page values are defined.
* [ ] Invalid page-size values are defined.
* [ ] Unknown pagination parameters are handled consistently.

### Metadata

* [ ] Canonical pagination metadata is defined.
* [ ] Empty result metadata is defined.
* [ ] Navigation-link policy is decided.

### Ordering

* [ ] Every paginated collection has deterministic ordering.
* [ ] Tie-breaking requirements are documented.
* [ ] Sorting remains consistent across pages.

### Filtering/search

* [ ] Filtering occurs before pagination.
* [ ] Searching occurs before pagination.
* [ ] Sorting occurs before pagination.
* [ ] Query parameters are preserved across navigation links where links are used.

### Security

* [ ] Pagination cannot bypass authorization.
* [ ] Total counts reflect authorized records only.
* [ ] Excessive page sizes are blocked.
* [ ] Internal data is not exposed through pagination metadata.

### Clients

* [ ] Next.js compatibility considered.
* [ ] Flutter compatibility considered.
* [ ] Admin compatibility considered.

### Enums

* [ ] Version 1 enum filters remain CLOSED.
* [ ] The old OPEN/EXTENSIBLE interim rule is not reintroduced.

### Payment

* [ ] No payment implementation is included.
* [ ] Payment-specific work remains assigned to **Phase Group H**.

---

# 58. Explicitly Out of Scope

Do NOT:

```text id="dwgpvp"
Create Laravel pagination code
Create database queries
Define endpoint-specific pagination payloads
Define final response envelope
Define HTTP status codes
Create OpenAPI response schemas
Create Next.js pagination components
Create Flutter pagination widgets
Implement cursor tokens
Configure caching
Implement search
Implement filtering
```

---

# 59. Definition of Done

Phase 1.12 is complete when:

1. Version 1 pagination strategy is documented.
2. Page numbering is fixed.
3. Page-size parameter is fixed.
4. Default page size is fixed.
5. Maximum page size is fixed.
6. Minimum page size is fixed.
7. Invalid pagination input behavior is defined.
8. Empty collection/page behavior is defined.
9. Pagination metadata naming is fixed.
10. Navigation-link policy is decided.
11. Deterministic ordering is mandatory.
12. Tie-breaking requirement is documented.
13. Filter/search/sort ordering relative to pagination is defined.
14. Changing-data behavior is documented.
15. Authorization behavior is documented.
16. Cursor pagination policy is documented.
17. Next.js/Flutter/Admin compatibility is considered.
18. Version 1 closed-enum policy remains intact.
19. No endpoint-specific response schema has been implemented.
20. No Laravel implementation has been created.

---

# 60. STOP CONDITION — Mandatory

After completing:

```text id="9oxg07"
pagination-convention.md
pagination-metadata-policy.md
pagination-resource-policy.md
pagination-consistency-policy.md
pagination-security-policy.md
pagination-cursor-policy.md
pagination-decisions.md
```

and passing the validation checklist:

**STOP.**

Do not automatically design the response envelope.

Do not define JSON response structures for individual resources.

Do not implement Laravel pagination.

The next phase must be explicitly requested.

Recommended next phase:

# Phase 1.13 — Define API Response Envelope and Resource Representation

That phase should establish the common response structure, `data`/`meta`/`errors` conventions, resource representation rules, links, pagination placement, null handling, timestamps, money representation, identifier representation, relationship representation, and consistent serialization rules across Next.js, Flutter, and Admin.
