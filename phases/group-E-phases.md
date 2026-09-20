# Phase 5.5 — Product Search / Filter API

## Purpose

Implement the complete search/filter/sort behavior for:

```text
CAT-001
GET /api/v1/products
```

using:

```text
Laravel Query Builder / Eloquent
+
native MySQL/MariaDB FULLTEXT
+
existing Group C relationships
```

Do not introduce:

```text
Algolia
Meilisearch
Typesense
Elasticsearch
OpenSearch
Laravel Scout
external search services
```

for V1.

The public API contract remains unchanged.

---

# 1. Critical Architecture Decision

Use:

```text
MySQL/MariaDB FULLTEXT
```

for Product text search over:

```text
products.name
products.description
```

Use ordinary Laravel relational querying for:

```text
variant SKU
controlled variant attributes
category
price
availability
product_type
sorting
pagination
```

Do not create a second search index service.

---

# 2. Preserve Existing Group C Model

Do not denormalize searchable data into Product merely for convenience.

Continue to use:

```text
Product
→ ProductVariant
→ ProductStock
→ Category
```

according to the existing relationships.

Specifically do not add:

```text
products.sku
products.variant_attributes
products.stock
products.price
products.search_blob
```

just to make search easier.

---

# 3. Read Current Authoritative Docs First

Before implementation, read:

```text
AGENTS.md
docs/VISION.md
docs/api/api-contract.md
docs/api/api-resources.md
docs/api/api-conventions.md
docs/api/openapi.yaml
docs/domain/business-rules.md
docs/decisions.md
phases/group-C-phases.md
```

Also inspect:

```text
Phase 5.2 Product Read API
Phase 5.3 Product Detail API
Phase 5.4 Variant API
```

and current implementations of:

```text
Product
ProductVariant
ProductStock
Category
ProductCatalogQuery
```

or their actual equivalents.

---

# 4. Endpoint

Do not add a new search endpoint.

Search remains:

```http
GET /api/v1/products?search=...
```

Do not create:

```text
/search
/search/products
/products/search
```

CAT-001 is the sole public product discovery pipeline.

---

# 5. Public Access

Search/filter remains:

```text
PUBLIC_READ
```

No authentication.

No Clerk.

No role.

No user-specific results.

---

# 6. Frozen Query Parameters

Support only the approved query parameters:

```text
search
category
product_type
availability
min_price
max_price
sort
sort_direction
page
per_page
```

Do not introduce aliases such as:

```text
q
query
pageSize
sortBy
minPrice
maxPrice
```

---

# 7. Query Pipeline

The implementation order must remain:

```text
1. search
2. filters
3. sort
4. id ASC tie-breaker
5. pagination
```

Do not change this order casually.

---

# 8. Search Input

`search` is:

```text
optional
string
trimmed
max 100 chars
case-insensitive
```

An empty trimmed string should behave as:

```text
search omitted
```

Do not execute FULLTEXT for an empty search.

---

# 9. Primary Search Fields

Native FULLTEXT applies to:

```text
products.name
products.description
```

Create a composite FULLTEXT index if one does not already exist.

Example conceptually:

```php
$table->fullText([
    'name',
    'description',
]);
```

Use the actual current migration style.

---

# 10. New Migration Is Acceptable Here

Unlike Phases 5.2–5.4, Phase 5.5 may legitimately require a schema migration for:

```text
FULLTEXT(name, description)
```

This is an indexing change, not a domain-model redesign.

Do not edit historical Product migrations.

Create a new migration.

---

# 11. MySQL / MariaDB Compatibility

The production search implementation must work with the repository's supported MySQL/MariaDB environment.

Do not write search SQL specific to a database version without checking compatibility.

Prefer Laravel:

```php
whereFullText(...)
```

where it produces correct behavior for the supported driver.

---

# 12. Do Not Use Raw MATCH SQL Unless Necessary

Prefer:

```text
whereFullText
```

or a small controlled query abstraction.

Only use raw SQL if Laravel cannot express a required FULLTEXT behavior.

If raw SQL is used:

```text
bind all values
never interpolate user input
```

---

# 13. Search Semantics

Product text matching should search:

```text
name
description
```

through FULLTEXT.

This is the primary linguistic search path.

---

# 14. Variant SKU Search

SKU lives on:

```text
product_variants.sku
```

Do not include SKU in the Product FULLTEXT index.

Use relationship-aware matching.

Preferred semantics:

```text
exact SKU match
OR
prefix SKU match
```

Examples:

```text
SOFA-RED-3S
SOFA-RED
SOFA
```

Do not use:

```text
LIKE '%term%'
```

as the primary SKU strategy.

---

# 15. SKU Index

Reuse the existing global unique SKU index.

Do not add duplicate SKU indexing unless query analysis proves it necessary.

---

# 16. Variant Attribute Search

The frozen CAT-001 contract references Variant attributes as searchable.

Do not blindly search every arbitrary JSON value.

Keep V1 bounded.

---

# 17. Controlled Attribute Keys

Inspect the actual Variant data currently used.

Only search supported customer-meaningful keys, for example:

```text
color
fabric
finish
size
configuration
leg_finish
```

Use the real keys present in the project.

Do not invent new attribute vocabulary.

---

# 18. No Generic JSON Search Engine

Do not build:

```text
recursive JSON flattening
dynamic path search
EAV conversion
search DSL
```

for V1.

Use a narrow explicit allow-list.

---

# 19. Search Combination Semantics

A Product should match `search` if any approved search source matches:

```text
Product name/description FULLTEXT
OR
Variant SKU
OR
supported Variant attribute value
```

Keep this grouped correctly in SQL.

Do not accidentally turn later filters into OR conditions.

---

# 20. Search + Filter Composition

Example:

```text
search = "oak"
category = dining-room
min_price = ...
```

must mean:

```text
matches "oak"
AND
belongs to category
AND
within price range
```

not:

```text
search OR category OR price
```

---

# 21. Use One Query Pipeline

Do not:

```text
run text search
collect IDs in PHP
then run filters in memory
```

Build one database query where practical.

This keeps:

```text
filtering
sorting
pagination
```

correct and scalable.

---

# 22. Avoid Load-All Search

Do not:

```text
Product::all()
```

then search/filter in PHP.

Search and filters belong in SQL.

---

# 23. Category Filter

Use:

```text
?category={slug|id}
```

according to the frozen CAT-001 contract.

Reuse Phase 5.1 category resolution.

Do not add:

```text
/categories/{category}/products
```

---

# 24. Category Filter Validation

Unknown/invalid Category must follow the current frozen API behavior.

Do not silently reinterpret invalid identifiers.

---

# 25. Category Hierarchy

Do not invent descendant-category inclusion semantics in Phase 5.5.

Use the current authoritative category-filter behavior.

If still unresolved:

document the ambiguity rather than silently implementing recursion.

---

# 26. Product Type Filter

Approved values:

```text
IN_STOCK
MADE_TO_ORDER
```

only.

Do not accept arbitrary values.

---

# 27. Phase 5.7 Dependency

If Product Type is still not physically present until Phase 5.7:

do not fake this filter.

Keep the filter path clearly deferred/inactive until Phase 5.7 supplies authoritative data.

Do not infer:

```text
inventory > 0 = IN_STOCK
```

---

# 28. Availability Filter

Supported public values:

```text
available
unavailable
```

only.

Do not accept:

```text
IN_STOCK
LOW_STOCK
MADE_TO_ORDER
```

as `availability` filters.

---

# 29. Stock Indicator Is Not Filterable

Do not add:

```text
?stock_indicator=
```

The frozen contract explicitly keeps it display-only.

---

# 30. Availability Derivation

Reuse the same public availability resolver used by:

```text
CAT-001 summaries
CAT-002
CAT-005
CAT-006
```

Do not implement separate search-only inventory logic.

---

# 31. Price Filter

Use:

```text
min_price
max_price
```

as integer minor units.

No floats.

---

# 32. Price Source

Product-level public price must use the same derived pricing rule established in Phase 5.2.

Do not filter using a different price calculation than the one serialized.

---

# 33. Price Filter Consistency

If a Product serializes:

```text
price = X
```

then:

```text
min_price <= X <= max_price
```

must govern inclusion.

Do not filter against cost price or arbitrary Variant price if that differs from the public Product price rule.

---

# 34. `min_price`

Validation:

```text
integer or integer numeric string
>= 0
```

---

# 35. `max_price`

Validation:

```text
integer or integer numeric string
>= 0
```

---

# 36. Invalid Price Range

If:

```text
min_price > max_price
```

return:

```text
422 INVALID_VALUE
```

---

# 37. Sorting

Only allow:

```text
created_at
price
name
```

Do not pass arbitrary user input into `orderBy`.

---

# 38. Sort Direction

Only:

```text
asc
desc
```

Use the current contract defaults:

```text
name → asc
price → asc
created_at → desc
```

when sort direction is omitted.

---

# 39. Default Sorting

When `sort` is omitted:

```text
created_at DESC
id ASC
```

remains the catalog default.

---

# 40. Search Relevance

Do not silently replace the frozen sort contract with relevance ranking.

MySQL FULLTEXT may calculate relevance internally, but:

```text
?sort=
```

must still obey the V1 allow-list.

---

# 41. No `sort=relevance` Yet

Do not add:

```text
?sort=relevance
```

unless an explicit API-contract decision is approved.

This phase should not expand the frozen enum.

---

# 42. Search Without Explicit Sort

Use the existing default catalog sort unless the docs explicitly approve relevance-first search behavior.

Do not hide a contract change inside SQL.

---

# 43. Deterministic Tie-Breaker

Always append:

```text
id ASC
```

when the primary sort is non-unique.

This applies to:

```text
created_at
name
price
```

---

# 44. Pagination

Use:

```text
page
per_page
```

with current V1 rules.

Current:

```text
page >= 1
per_page 1..100
default 20
```

---

# 45. Pagination After Search / Filter / Sort

Do not paginate first.

Correct order:

```text
search
→ filters
→ sort
→ tie-breaker
→ paginate
```

---

# 46. Stable Pagination

Search/filter results must not drift unnecessarily between pages when DB state is unchanged.

Use deterministic ordering.

---

# 47. Duplicate Product Prevention

Joining/searching Variant rows may duplicate Product rows.

Prevent duplicate Product summaries.

Do not allow:

```text
same Product appears multiple times
because multiple Variants match
```

Use appropriate:

```text
EXISTS
whereHas
subqueries
distinct where justified
```

---

# 48. Prefer `EXISTS` / Relationship Filters

For Variant SKU/attributes, prefer:

```text
whereHas
EXISTS
```

semantics over broad joins where this avoids duplicate Product rows.

---

# 49. Search Safety

All search values are untrusted.

Never concatenate search strings into SQL.

Use bound query parameters.

---

# 50. Wildcards

If prefix SKU matching uses `LIKE`:

escape user wildcard characters appropriately if required by current query helper.

Do not allow user input to alter pattern semantics unintentionally.

---

# 51. Search Term Normalization

Keep normalization minimal:

```text
trim
case behavior handled by DB/collation
```

Do not implement:

```text
stemming service
synonym engine
phonetic search
typo correction
```

in V1.

---

# 52. Stop Words / FULLTEXT Behavior

Native MySQL FULLTEXT has engine-specific behavior around:

```text
stop words
minimum token lengths
natural-language ranking
```

Do not attempt to recreate or override all of it in application code.

Document this as a V1 characteristic.

---

# 53. No Custom Search Parser

Do not expose MySQL Boolean-mode syntax directly to clients.

Treat `search` as plain user text.

Do not allow customers to submit:

```text
+oak -chair >table
```

as a public search DSL unless explicitly approved.

---

# 54. Natural-Language FULLTEXT

Prefer standard natural-language FULLTEXT behavior for V1 unless the current DB/query implementation clearly requires another mode.

Keep semantics intuitive.

---

# 55. No External Search Dependency

Do not add:

```text
scout
meilisearch
algolia
typesense
elastic
```

packages/configuration.

---

# 56. No Queue Synchronization

Because MySQL is the search source:

do not create:

```text
search indexing jobs
index sync events
reindex commands
search replicas
```

---

# 57. Source of Truth

Search sees current committed DB state.

There is no eventually consistent external index.

---

# 58. Public Visibility First

Search results must still obey public Product visibility.

Search must never surface:

```text
inactive
soft-deleted
future unpublished
```

Products.

---

# 59. Search Cannot Bypass Visibility

A direct FULLTEXT match on a hidden Product must still remain hidden.

Public scope and search scope must compose.

---

# 60. Phase 5.7 Publication Dependency

When Phase 5.7 adds:

```text
is_published
```

public search must automatically restrict to published Products through the shared public scope.

Do not create a second search-specific publication condition.

---

# 61. Internal Fields Must Not Affect Public Search Improperly

Do not search:

```text
cost_price
internal stock notes
warehouse location
admin notes
deleted records
```

---

# 62. SKU Search Is Acceptable Publicly

SKU is already part of the public Variant representation.

Searching SKU is consistent with the public contract.

---

# 63. Variant Attributes Privacy

Only search Variant attribute keys intended for customer-facing catalog use.

Do not accidentally make internal attributes searchable if the JSON field later contains operational metadata.

---

# 64. Query Abstraction

Use or extend one focused abstraction such as:

```text
ProductCatalogQuery
```

Prefer methods conceptually like:

```text
applySearch()
applyCategoryFilter()
applyProductTypeFilter()
applyAvailabilityFilter()
applyPriceRange()
applySort()
paginate()
```

Do not put all logic in the controller.

---

# 65. Avoid Generic Filter Framework

Do not add an external query-builder/filter package merely for Phase 5.5.

Normal Laravel querying is sufficient.

---

# 66. Controller

Controller should remain thin:

```text
validated query
→ ProductCatalogQuery
→ ProductSummaryResource collection
```

Do not write FULLTEXT SQL directly in controller methods.

---

# 67. FormRequest

Use a dedicated query request or existing CAT-001 request validator.

Validate:

```text
search
category
product_type
availability
min_price
max_price
sort
sort_direction
page
per_page
```

before query execution.

---

# 68. Unknown Query Parameters

Follow the repository's current unknown-field/query policy.

If unknown query parameters are rejected, keep that behavior.

Do not silently accept unsupported filters.

---

# 69. Invalid Search Type

Examples:

```text
search[]=oak
search object
```

must fail validation.

Do not cast arbitrary structures to strings.

---

# 70. Search Length

Reject search strings beyond:

```text
100
```

characters according to the frozen contract.

---

# 71. Empty Search

Input:

```text
search=
```

or whitespace-only:

```text
search=   
```

must behave as no search filter.

Do not issue invalid FULLTEXT SQL.

---

# 72. Search + SKU

A term that exactly matches a Variant SKU must return its parent Product.

---

# 73. Search + Variant Attributes

A supported Variant attribute match must return the parent Product exactly once.

---

# 74. Search + Multiple Matching Variants

If three Variants under one Product match:

```text
same Product appears once
```

---

# 75. Search + Category

Test:

```text
search=oak
category=dining-room
```

Only Products satisfying both criteria appear.

---

# 76. Search + Price

Test combined search with:

```text
min_price
max_price
```

against the public derived Product price.

---

# 77. Search + Availability

Test combined search and availability when final availability is authoritative.

---

# 78. Search + Product Type

Test combined Product Type only after Phase 5.7 provides authoritative Product Type.

Until then:

document as deferred.

---

# 79. MySQL FULLTEXT Index Migration

If FULLTEXT index is newly added:

use a new migration with a clear name, such as conceptually:

```text
add_fulltext_index_to_products_name_description
```

Follow repository naming conventions.

---

# 80. Migration `down()`

The migration must safely remove the FULLTEXT index.

Account for supported MySQL/MariaDB syntax through Laravel schema APIs where possible.

---

# 81. SQLite Limitation — Mandatory

SQLite canonical tests must **not** be treated as proof of MySQL FULLTEXT behavior.

This must be explicitly documented.

---

# 82. SQLite Canonical Suite

Keep the canonical fast suite running on SQLite where currently configured.

Do not migrate the entire test suite to MySQL just because Phase 5.5 uses FULLTEXT.

---

# 83. SQLite Search Fallback

For SQLite test/runtime compatibility, implement the smallest deterministic fallback needed for semantic API tests.

The fallback may use:

```text
LIKE
```

for:

```text
Product name
Product description
```

only in the SQLite path.

The SQLite fallback must explicitly escape user-supplied `LIKE` metacharacters
`%`, `_`, and the escape character itself, and must emit an explicit single-
character `ESCAPE '\\'` clause. Tests must prove that literal `%`, `_`, and
backslash searches do not behave as wildcards.

---

# 84. SQLite Fallback Is Not Production Search

Document clearly:

```text
SQLite fallback exists for test compatibility.
Production MySQL/MariaDB uses FULLTEXT.
```

Do not claim equivalent ranking/performance semantics.

---

# 85. Driver Detection

If query implementation branches by driver:

keep the branch localized.

Conceptually:

```text
mysql/mariadb
→ whereFullText

sqlite
→ deterministic LIKE fallback
```

Do not scatter:

```text
DB::getDriverName()
```

checks throughout the application.

---

# 86. Do Not Use `APP_ENV` to Choose Search Engine

Search behavior should depend on database capability/driver, not:

```text
APP_ENV=testing
```

Avoid test-only production bypasses.

---

# 87. Unsupported SQLite Test Cases

Tests that specifically depend on MySQL FULLTEXT internals must not run as fake SQLite equivalents.

Examples:

```text
FULLTEXT index existence
MATCH semantics
MySQL relevance behavior
stop-word behavior
minimum token behavior
execution plan/index usage
```

These are MySQL-specific.

---

# 88. MySQL-Specific Test Classification

Place MySQL-only integration tests in a clearly identifiable location/tag/group according to repository conventions.

Examples conceptually:

```text
MySqlProductSearchIntegrationTest
```

or:

```text
@group mysql
```

Use actual project style.

---

# 89. MySQL Integration Test Scope

At minimum verify:

```text
FULLTEXT index exists
name match works
description match works
non-match excluded
combined public visibility still enforced
```

Do not over-test MySQL's own search engine implementation.

---

# 90. Do Not Re-Test MySQL Itself

Do not build exhaustive tests for:

```text
natural-language ranking algorithm
stop-word dictionary
stemming
tokenizer
```

Test our integration and assumptions only.

---

# 91. MySQL Test Database Safety

Any MySQL integration test must use a:

```text
uniquely named disposable database
```

Never use the configured application development/staging/production DB.

---

# 92. Destructive Migration Safety

Before:

```text
migrate:fresh --seed --force
```

require:

```text
APP_ENV != production
explicit disposable database name
database-name guard
```

according to the project's updated safety policy.

---

# 93. SQLite Migration Compatibility

If Laravel/SQLite cannot create the FULLTEXT index through the same migration:

do not break canonical SQLite migrations unnecessarily.

Use a driver-aware migration strategy if required.

---

# 94. Migration Must Not Lie

If SQLite skips the FULLTEXT index:

document that explicitly.

Do not create a normal SQLite B-tree index and call it FULLTEXT equivalent.

---

# 95. Fulltext Migration Strategy

Acceptable pattern:

```text
MySQL/MariaDB:
create FULLTEXT(name, description)

SQLite:
skip FULLTEXT-specific index
```

provided the canonical SQLite fallback query remains tested.

---

# 96. Migration Rollback

Driver-aware rollback must not fail when the FULLTEXT index was never created on SQLite.

---

# 97. MySQL Index Naming

Give the FULLTEXT index a deterministic explicit name if project migration conventions favor that.

This makes rollback and verification safer.

---

# 98. Query Performance

Search should avoid:

```text
full table PHP filtering
N+1 variant queries
unbounded JSON scans
```

---

# 99. SKU Search Performance

Use the existing indexed SKU column.

Do not wrap SKU in functions that destroy index usefulness unless required.

---

# 100. Attribute Search Performance

Keep JSON attribute matching limited.

If it becomes a performance bottleneck later:

that is a future schema/search evolution decision.

Do not prematurely denormalize now.

---

# 101. Price Sorting Performance

Price sort must use the same derived Product price mechanism already selected.

Do not retrieve every Variant into PHP to sort.

---

# 102. Search Result Representation

CAT-001 continues returning:

```text
ProductSummaryResource
```

Search must not produce a different Product shape.

---

# 103. Search Does Not Expose Relevance Score

Do not add:

```text
relevance
score
match_score
```

to Product JSON unless explicitly added to the V1 contract.

---

# 104. Cacheability

Search/filter GETs remain public/cacheable where current conventions permit.

Cache keys must naturally vary by full query string at proxy/CDN level later.

Do not add application cache complexity here.

---

# 105. Public Rate Limit

Use existing:

```text
public-read
```

rate limiting.

Do not create a harsh search-specific limiter unless evidence later requires it.

---

# 106. Test — No Search

`GET /products` without `search` must preserve Phase 5.2 behavior.

---

# 107. Test — Name Search

Seed Products with controlled names.

Verify only matching visible Products return.

SQLite semantic fallback test is acceptable here.

---

# 108. Test — Description Search

Same for description.

---

# 109. Test — SKU Search

Product whose Variant SKU matches should return.

---

# 110. Test — SKU Prefix Search

If prefix semantics are approved:

verify expected parent Product appears.

---

# 111. Test — Variant Attribute Search

For each supported key class, add focused coverage.

Do not test arbitrary unknown JSON keys as searchable.

---

# 112. Test — Duplicate Suppression

Multiple matching Variants under one Product:

```text
one Product result
```

---

# 113. Test — Empty Search

Whitespace-only search behaves identically to no search.

---

# 114. Test — Max Search Length

Over-100-character input returns canonical validation error.

---

# 115. Test — Search Does Not Leak Hidden Product

Inactive/deleted Product matching search must remain absent.

After Phase 5.7:

unpublished matching Product must also remain absent.

---

# 116. Test — Category Filter

Verify slug and ID filtering according to existing CAT-001 contract.

---

# 117. Test — Product Type Validation

Unknown values:

```text
STANDARD
CUSTOM
in_stock
```

should fail according to CLOSED enum rules.

Only run behavior-dependent success cases once Phase 5.7 is implemented.

---

# 118. Test — Availability Validation

Accept:

```text
available
unavailable
```

Reject:

```text
IN_STOCK
LOW_STOCK
AVAILABLE
```

according to current casing contract.

---

# 119. Test — Min Price

Verify lower-bound inclusion/exclusion.

---

# 120. Test — Max Price

Verify upper-bound inclusion/exclusion.

---

# 121. Test — Combined Price Range

Verify:

```text
min <= price <= max
```

---

# 122. Test — Invalid Price Range

Return:

```text
422 INVALID_VALUE
```

for:

```text
min_price > max_price
```

---

# 123. Test — Invalid Price Format

Examples:

```text
1.5
abc
-5
```

must follow current validation/error contract.

---

# 124. Test — Sort Name

Verify:

```text
name ASC
name DESC
```

with deterministic ID tie-breaking.

---

# 125. Test — Sort Created At

Verify both directions.

---

# 126. Test — Sort Price

Verify against derived public Product price.

---

# 127. Test — Invalid Sort

Reject:

```text
cost_price
sku
stock
random
```

---

# 128. Test — Sort Direction

Reject unsupported directions.

---

# 129. Test — Pagination After Search

Search results must paginate correctly.

---

# 130. Test — Combined Pipeline

Add at least one realistic combined test:

```text
search
+
category
+
price range
+
sort
+
pagination
```

This should prove pipeline order and composition.

---

# 131. Test — Query Safety

Use search terms containing characters such as:

```text
'
%
_
"
\
```

Verify no SQL errors/injection behavior.

Do not use destructive SQL payloads.

---

# 132. Test — Public Shape

Search results must retain exactly the normal Product Summary representation.

No search-only fields.

---

# 133. Test — MySQL FULLTEXT Integration

On MySQL/MariaDB only:

verify actual FULLTEXT matching on:

```text
name
description
```

using the real production search path.

---

# 134. MySQL Test Must Not Run on SQLite

If the MySQL test runs under SQLite:

```text
skip with explicit reason
```

according to project test conventions.

Do not silently pass a different query and call it the same test.

---

# 135. Completion Report Must Distinguish Test Classes

Report separately:

```text
SQLite semantic/API tests
MySQL FULLTEXT integration tests
```

Do not combine them in a way that suggests SQLite verified FULLTEXT.

---

# 136. Example Completion Wording

Use wording like:

```text
SQLite canonical suite:
PASS — validates API semantics, filtering, sorting, pagination, SKU/relationship search, and deterministic fallback behavior.

MySQL FULLTEXT integration:
PASS — validates actual FULLTEXT index + name/description search behavior.
```

If MySQL integration cannot run:

```text
NOT RUN / BLOCKED
```

with exact reason.

Do not falsely mark it PASS.

---

# 137. CI Strategy

Do not require the entire existing CI suite to switch to MySQL.

If CI currently has only SQLite:

keep canonical CI green.

The targeted MySQL FULLTEXT test may remain:

```text
supplementary
```

until the project later provisions MySQL CI in Group U.

---

# 138. Group U Handoff

Document that when MySQL CI is introduced later:

the MySQL FULLTEXT integration test should be added to that job.

Do not lose this requirement.

---

# 139. Known SQLite Unsupported Coverage

Explicitly list:

```text
FULLTEXT index creation semantics
actual MySQL MATCH behavior
relevance behavior
FULLTEXT execution/index plan
MySQL tokenization/stop-word behavior
```

as not provable by SQLite.

---

# 140. Do Not Block Semantic Search Tests

SQLite can still validly test:

```text
input validation
filter composition
sorting
pagination
visibility
SKU matching
controlled attribute matching
response shape
duplicate suppression
```

Use it for these.

---

# 141. Search Result Correctness Over Ranking Sophistication

For V1 prioritize:

```text
correct inclusion/exclusion
stable filters
fast indexed search
```

over advanced ranking.

---

# 142. No Typo Tolerance

Do not implement:

```text
soffa → sofa
tabl → table
```

automatically.

This is a later external-search-engine capability if ever needed.

---

# 143. No Synonyms

Do not create synonym dictionaries such as:

```text
couch = sofa
settee = sofa
```

in V1.

---

# 144. No Search Analytics

Do not store:

```text
search terms
zero-result searches
customer query history
```

during this phase.

Analytics belongs later if approved.

---

# 145. No Search History

Do not create user-specific recent searches.

CAT-001 remains stateless/public.

---

# 146. No Autocomplete Endpoint

Do not implement:

```text
/search/suggestions
/autocomplete
```

in Phase 5.5.

---

# 147. No Facet Counts

Do not add counts like:

```text
Sofas (14)
Wood (8)
Available (6)
```

unless later approved.

Filtering itself is enough for V1.

---

# 148. No Material / Style Filters Yet

Do not expand CAT-001 to:

```text
material
style
color
room
```

unless they are explicitly part of the frozen contract.

This phase implements the existing query set.

---

# 149. No Search Engine Abstraction for Hypothetical Future

Do not add:

```text
SearchEngineInterface
AlgoliaSearchEngine
MysqlSearchEngine
```

merely because the app may migrate later.

A focused Product search/query service is sufficient.

---

# 150. Future Migration Path

A future external engine can replace the internals while retaining:

```text
GET /api/v1/products?search=...
```

No need to pre-build infrastructure now.

---

# 151. Schema Changes

Expected schema change:

```text
FULLTEXT index on products(name, description)
```

Only.

Do not bundle unrelated fields.

---

# 152. Do Not Add Phase 5.7 Fields Here

Do not add:

```text
product_type
is_published
```

in Phase 5.5 if they remain scheduled for Phase 5.7.

---

# 153. No Frontend Changes

Do not modify:

```text
frontend/web/
frontend/app/
frontend/design-system/
```

No search UI belongs here.

---

# 154. No New Dependencies

Expected:

```text
Composer dependencies:
NONE
```

---

# 155. Code Quality

Maintain:

```text
cognitive complexity <= 15
<= 3 returns where practical
small focused query methods
no raw interpolated SQL
no duplicated filter logic
minimal comments
```

---

# 156. Likely Implementation Areas

Depending on current structure:

```text
app/Http/Requests/
app/Queries/
app/Services/
app/Models/
database/migrations/
tests/Feature/
tests/Integration/
docs/api/
docs/decisions.md
```

Modify only what Phase 5.5 needs.

---

# 157. Verification — SQLite Canonical

Run:

```bash
php artisan test
vendor/bin/pint --test
vendor/bin/phpstan analyse
composer audit
git diff --check
```

Do not claim this verifies MySQL FULLTEXT.

---

# 158. Verification — MySQL FULLTEXT

Where a disposable MySQL/MariaDB test environment is available:

run the targeted search integration test against it.

Use the project's destructive migration safety rules.

---

# 159. MySQL Safety

Before any destructive test rebuild:

verify:

```text
APP_ENV != production
DB_DATABASE is disposable
```

Never infer safety from `APP_ENV` alone.

---

# 160. Completion Report

Return:

## Phase 5.5 status

```text
PASS
```

or:

```text
BLOCKED
```

## Search engine

State:

```text
MySQL/MariaDB native FULLTEXT
Laravel Query Builder/Eloquent
```

## FULLTEXT fields

State:

```text
products.name
products.description
```

## Relationship search

State:

```text
Variant SKU
approved Variant attribute keys
```

## Filters

Report behavior for:

```text
category
product_type
availability
min_price
max_price
```

## Sorting

Report:

```text
created_at
price
name
id ASC tie-breaker
```

## SQLite testing

Explicitly report:

```text
SQLite validates application semantics only.
It does not validate MySQL FULLTEXT behavior.
```

## MySQL FULLTEXT test

Report:

```text
PASS
NOT RUN
BLOCKED
```

with exact reason.

## Schema changes

Expected:

```text
FULLTEXT index only
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

Exact counts.

## Static checks

Report:

```text
Pint
PHPStan
Composer audit
git diff --check
```

---

# 161. Definition of Done

Phase 5.5 is complete when:

* CAT-001 `search` is implemented;
* Product name/description use native MySQL/MariaDB FULLTEXT;
* Variant SKU search works through existing relationships;
* supported Variant attribute search works without generic JSON complexity;
* search values are parameterized safely;
* Product results are not duplicated by matching multiple Variants;
* public visibility cannot be bypassed by search;
* category filtering works;
* price range filtering works;
* availability filtering works where authoritative;
* Product Type filtering remains correctly dependent on Phase 5.7 if not yet available;
* only approved sort fields are accepted;
* deterministic ID tie-breaking remains;
* pagination occurs after search/filter/sort;
* CAT-001 Product Summary shape is unchanged;
* no external search service is added;
* no Scout dependency is added;
* no search synchronization queue is added;
* no Product denormalization is introduced;
* a FULLTEXT index exists on Product name/description for MySQL/MariaDB;
* SQLite fallback exists only where needed for semantic test compatibility;
* SQLite tests do not falsely claim FULLTEXT coverage;
* targeted MySQL FULLTEXT integration coverage exists where infrastructure permits;
* unsupported SQLite-specific FULLTEXT cases are explicitly documented;
* no frontend code is changed;
* no Phase 5.7 schema fields are pulled forward;
* full canonical backend suite passes;
* Pint passes;
* PHPStan passes;
* Composer audit has no blocker.

---

# 162. Out of Scope

Do not implement:

```text
Algolia
Meilisearch
Typesense
Elasticsearch
OpenSearch
Laravel Scout
typo tolerance
synonyms
faceted counts
autocomplete
search history
search analytics
relevance sort API
material/style/color filters not already contracted
frontend search UI
```

---

# 163. STOP Condition

STOP when:

```text
GET /api/v1/products
```

supports the complete approved V1 search/filter/sort/pagination pipeline using:

```text
native MySQL/MariaDB FULLTEXT
+
normal Laravel query construction
+
existing normalized Product/Variant relationships
```

and the test suite clearly distinguishes:

```text
SQLite semantic coverage
```

from:

```text
actual MySQL FULLTEXT integration coverage
```

Do not continue automatically to Phase 5.6.

DO NOT COMMIT, STAGE OR PUSH.

The project owner handles Git operations.
