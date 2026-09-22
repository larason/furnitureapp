# Phase 5.7 — Product Availability Rules

## Purpose

Complete the V1 catalog availability model by implementing the deferred Product authority for:

```text
product_type
is_published
```

and establishing one consistent derivation for:

```text
availability
stock_indicator
public visibility
```

across:

```text
CAT-001 Product Collection
CAT-002 Product Detail
CAT-005 Variant Collection
CAT-006 Variant Detail
```

This phase completes catalog presentation rules.

It does **not** implement cart admission, checkout reservation, inventory adjustment, or made-to-order request processing.

---

# 1. Preserve the Existing Domain Model

Do not redesign Group C.

The authoritative model remains:

```text
Product
    ↓
ProductVariant
        ↓
ProductStock
```

where:

```text
Product
→ catalog identity / visibility / product type

ProductVariant
→ sellable/pricing configuration

ProductStock
→ physical inventory by location
```

Do not add stock quantities to Product.

Do not add stock quantities to ProductVariant.

---

# 2. Read Authoritative Repository State First

Before modifying code, inspect:

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

Also inspect current implementations from:

```text
Phase 5.2
Phase 5.3
Phase 5.4
Phase 5.5
Phase 5.6
```

especially the existing:

```text
Product public scope
ProductCatalogQuery
price resolver
availability placeholder/resolver
Variant resources
Product resources
```

Do not create parallel logic.

---

# 3. Known Deferred Schema Gap

Group C deliberately left these Product fields absent:

```text
product_type
is_published
```

The repository explicitly assigns their correction to Phase 5.7.

This phase must now add them.

---

# 4. Product Type

Introduce authoritative Product field:

```text
product_type
```

Closed values:

```text
IN_STOCK
MADE_TO_ORDER
```

No third value.

Do not add:

```text
PREORDER
CUSTOM
BACKORDER
DIGITAL
SERVICE
```

in V1.

---

# 5. Product Type Support Type

Use an existing project enum/value-object pattern.

Preferred:

```text
App\Support\ProductType
```

or the current convention.

Do not scatter:

```php
'IN_STOCK'
'MADE_TO_ORDER'
```

through controllers/resources/query classes.

---

# 6. Product Type Database Representation

Follow the project's established CLOSED-enum storage strategy.

Do not redesign enum handling globally.

Remember the known MySQL/MariaDB case-insensitive enum behavior from Group C:

application validation remains authoritative for canonical uppercase values.

---

# 7. Product Type Migration

Create a new migration.

Do not modify historical Group C migrations.

The migration must make existing rows valid.

Preferred compatibility behavior:

```text
existing Product rows
→ IN_STOCK
```

because legacy Products existed before Product Type could be represented and previously participated in the ordinary inventory-backed catalog.

Record this explicitly as a migration compatibility decision.

---

# 8. Do Not Infer Legacy MADE_TO_ORDER

Do not attempt to identify legacy made-to-order Products from:

```text
zero stock
category
name
description
SKU
```

That would fabricate business meaning.

Legacy rows become:

```text
IN_STOCK
```

unless an explicit repository data migration says otherwise.

---

# 9. Publication State

Introduce:

```text
is_published
```

as a Product-level boolean.

This is distinct from:

```text
is_active
```

---

# 10. `is_active` vs `is_published`

Keep the meanings separate:

```text
is_active
→ Product is operationally active / not disabled

is_published
→ Product is intentionally visible in the public catalog
```

A Product must satisfy both to be publicly discoverable.

---

# 11. Public Visibility Rule

Canonical public Product predicate:

```text
deleted_at IS NULL
AND is_active = true
AND is_published = true
```

plus whatever existing public Category/pricing requirements already apply.

Centralize this predicate.

---

# 12. Do Not Duplicate Visibility Logic

Do not independently write:

```text
CAT-001 visibility
CAT-002 visibility
CAT-005 visibility
CAT-006 visibility
search visibility
```

in separate implementations.

Use one public Product scope/query abstraction.

---

# 13. Publication Migration Compatibility

Adding `is_published` must not accidentally make every existing public Product disappear.

Preferred migration strategy:

```text
new field default false for future safety
+
explicitly backfill existing active legacy Products to true
```

or an equivalent deterministic migration that preserves currently public legacy catalog data.

Document the exact approach.

---

# 14. Future Product Creation

Future catalog-management phases should explicitly choose publication state.

Do not depend permanently on implicit auto-publishing.

If a DB default is required:

prefer safe draft behavior for new rows:

```text
is_published = false
```

while migration backfill preserves legacy visibility.

---

# 15. Product Model

Update Product casts/type declarations for:

```text
product_type
is_published
```

using project conventions.

Do not make them client-mass-assignable merely because fields now exist.

Management APIs later own writes.

---

# 16. Public Serialization

Public Product responses now expose:

```text
product_type
```

because it is part of the frozen Product contract.

Do **not** expose:

```text
is_active
is_published
```

through CAT-001 or CAT-002.

Those remain operational/internal flags.

---

# 17. Publication Masking

For public Product detail:

```text
is_active = false
OR
is_published = false
OR
soft-deleted
```

must resolve as:

```text
404 RESOURCE_NOT_FOUND
```

Do not expose:

```text
PRODUCT_IS_DRAFT
PRODUCT_UNPUBLISHED
PRODUCT_INACTIVE
```

to public callers.

---

# 18. Variant Visibility Depends on Product Visibility

A Variant is public only when:

```text
parent Product is public
AND
Variant.is_active = true
```

An active Variant under an unpublished Product is not publicly accessible.

---

# 19. Keep the Phase 5.5 Active-Variant Fix

Relationship search must continue to require:

```text
variants.is_active = true
```

before SKU/attribute predicates.

Do not regress this.

Inactive Variants must never make a public Product appear through search.

---

# 20. Inventory Authority

Inventory remains authoritative in:

```text
product_stocks
```

Each stock row already provides derived:

```text
available_quantity = quantity - reserved_quantity
```

Do not add an `available_quantity` database column.

---

# 21. Aggregate Across Locations

A Variant may have stock in multiple locations.

Variant total available stock is:

```text
SUM(stock.quantity - stock.reserved_quantity)
```

across all current stock rows belonging to that Variant.

Do not inspect only the first location.

---

# 22. Location Is Internal

Public availability aggregation may use all inventory locations, but must never expose:

```text
warehouse_location
quantity
reserved_quantity
available_quantity
```

through public catalog APIs.

---

# 23. Variant Availability — IN_STOCK Product

For an active Variant belonging to a public:

```text
product_type = IN_STOCK
```

derive:

```text
total_available_quantity > 0
→ availability = "available"

total_available_quantity <= 0
→ availability = "unavailable"
```

No stock row is equivalent to zero available stock.

---

# 24. Product Availability — IN_STOCK Product

For a public Product whose:

```text
product_type = IN_STOCK
```

Product availability is:

```text
"available"
```

if at least one active Variant has:

```text
total_available_quantity > 0
```

Otherwise:

```text
"unavailable"
```

---

# 25. Do Not Include Inactive Variants in Product Availability

Stock belonging to:

```text
Variant.is_active = false
```

must not make the Product available.

Only active Variants contribute.

---

# 26. Do Not Include Hidden Product State

A Product that is inactive/unpublished is not publicly serialized at all.

Do not attempt to return:

```text
availability = unavailable
```

for an unpublished Product through CAT-001/CAT-002.

It is hidden.

---

# 27. MADE_TO_ORDER Availability

A public:

```text
product_type = MADE_TO_ORDER
```

does not derive public availability from physical inventory.

Its inventory count is irrelevant to requestability.

For a valid public MADE_TO_ORDER Product:

```text
availability = "available"
stock_indicator = "MADE_TO_ORDER"
```

provided the Product has the existing valid public pricing/configuration required by the catalog.

---

# 28. MADE_TO_ORDER Variant Availability

For an active Variant belonging to a public MADE_TO_ORDER Product:

```text
availability = "available"
stock_indicator = "MADE_TO_ORDER"
```

regardless of ProductStock quantity.

Do not require inventory rows for MADE_TO_ORDER Variant presentation.

---

# 29. MADE_TO_ORDER Is Not Purchasable

Do not confuse:

```text
availability = "available"
```

with:

```text
purchasable through cart
```

For MADE_TO_ORDER:

```text
available
→ available for the Request Furniture workflow

NOT
→ available for Cart/Checkout
```

Cart and Checkout must later reject it with:

```text
PRODUCT_NOT_PURCHASABLE
```

according to their own domain rules.

---

# 30. Do Not Implement Cart Rejection Here

Do not modify CART-002 merely to finish Phase 5.7 unless that cart implementation already exists and explicitly consumes a shared availability/product-type service.

This phase owns catalog rules.

Cart domain behavior belongs to its implementation phase.

---

# 31. Do Not Implement Checkout Revalidation Here

Do not add:

```text
inventory locks
reservation
row-level checkout locking
order creation
```

Availability reads remain informational.

Checkout later performs authoritative transaction-time validation.

---

# 32. Stock Indicator Contract

The frozen response values remain:

```text
IN_STOCK
LOW_STOCK
MADE_TO_ORDER
```

Do not add:

```text
OUT_OF_STOCK
SOLD_OUT
BACKORDER
```

inside V1 without explicit contract review.

---

# 33. `availability` Is Authoritative for Unavailable State

Because the frozen `stock_indicator` enum has no `OUT_OF_STOCK` value:

clients must treat:

```text
availability = "unavailable"
```

as the authoritative unavailable signal.

`stock_indicator` is secondary display/context information.

Document this clearly.

---

# 34. IN_STOCK Product with Zero Stock

For:

```text
product_type = IN_STOCK
available_quantity = 0
```

return:

```text
availability = "unavailable"
stock_indicator = "IN_STOCK"
```

Do not invent `OUT_OF_STOCK`.

Frontend later must prioritize:

```text
availability
```

before displaying stock badge text.

---

# 35. LOW_STOCK Threshold — Contract Gap

The existing docs define:

```text
LOW_STOCK
```

but do not currently establish a numeric threshold.

Do not hide a magic number in SQL.

Phase 5.7 must make the threshold explicit.

---

# 36. V1 Low-Stock Rule

If no newer authoritative repository decision already defines the threshold, establish the smallest explicit V1 rule:

```text
LOW_STOCK_THRESHOLD = 5 units
```

Record it as a Phase 5.7 business assumption/decision.

Do not add a DB column for the threshold.

---

# 37. Centralize Low-Stock Threshold

Define the threshold once in an appropriate domain support class/value object, for example:

```text
CatalogAvailability
StockIndicatorResolver
```

or equivalent.

Do not duplicate:

```text
5
```

across:

```text
resources
queries
tests
controllers
```

---

# 38. Why No `low_stock_threshold` Column Yet

Group C deliberately omitted:

```text
low_stock_threshold
```

from ProductStock.

Keep that decision.

V1 has one business-wide threshold.

Per-Product or per-Variant thresholds can be introduced later only if the business actually needs them.

---

# 39. Variant Stock Indicator — IN_STOCK Product

For an active Variant of an IN_STOCK Product:

```text
available_quantity == 0
→ availability = unavailable
→ stock_indicator = IN_STOCK

1 <= available_quantity <= LOW_STOCK_THRESHOLD
→ availability = available
→ stock_indicator = LOW_STOCK

available_quantity > LOW_STOCK_THRESHOLD
→ availability = available
→ stock_indicator = IN_STOCK
```

---

# 40. Product Stock Indicator — IN_STOCK Product

Use total available stock across all **active Variants**:

```text
product_available_quantity =
SUM(active Variant available quantities)
```

Then:

```text
product_available_quantity == 0
→ availability = unavailable
→ stock_indicator = IN_STOCK

1..LOW_STOCK_THRESHOLD
→ availability = available
→ stock_indicator = LOW_STOCK

> LOW_STOCK_THRESHOLD
→ availability = available
→ stock_indicator = IN_STOCK
```

This gives Product-level catalog cards one stable badge.

---

# 41. Do Not Use Physical Quantity Directly

Always derive from:

```text
quantity - reserved_quantity
```

not physical quantity alone.

Example:

```text
quantity = 10
reserved = 10
```

means:

```text
available = 0
```

not 10.

---

# 42. Clamp Is Not Needed

Group C already guarantees:

```text
0 <= reserved_quantity <= quantity
```

Do not hide integrity bugs by writing:

```text
max(quantity - reserved, 0)
```

unless an established shared accessor already does that.

Trust the invariant and surface test failures if violated.

---

# 43. Availability Resolver

Create or finalize one focused service/value object such as:

```text
CatalogAvailabilityResolver
```

or use the current existing Phase 5.2 abstraction.

It should own:

```text
Product availability
Product stock indicator
Variant availability
Variant stock indicator
```

Do not put this logic into API Resources.

---

# 44. Resources Should Serialize, Not Decide

Avoid:

```php
if ($this->stocks->sum(...) > 5) { ... }
```

inside ProductResource or VariantResource.

Resources should consume already-derived catalog presentation state.

---

# 45. Query Filtering Must Match Serialization

For every Product:

```text
availability used by
?availability=...
```

must be identical to:

```text
availability returned in JSON
```

No separate filter interpretation.

---

# 46. `availability=available`

CAT-001 filter:

```text
?availability=available
```

must include:

```text
public IN_STOCK Products with >0 available units
+
public MADE_TO_ORDER Products
```

because MADE_TO_ORDER Products are available to the customer through the Request workflow.

---

# 47. `availability=unavailable`

Must include only publicly visible:

```text
IN_STOCK
```

Products whose active Variants have zero total available quantity.

It should not include hidden/unpublished Products.

---

# 48. Product Type Filter Now Becomes Fully Active

Phase 5.7 completes:

```text
?product_type=IN_STOCK
?product_type=MADE_TO_ORDER
```

in CAT-001.

Use exact CLOSED values.

---

# 49. Product Type Filter Validation

Reject:

```text
in_stock
made_to_order
STANDARD
CUSTOM
PREORDER
```

unless current global request normalization explicitly allows case normalization.

Follow the current enum policy.

---

# 50. Product Type + Availability Composition

Examples:

```text
?product_type=IN_STOCK&availability=available
```

→ stocked standard Products with positive available inventory.

```text
?product_type=IN_STOCK&availability=unavailable
```

→ visible standard Products currently out of stock.

```text
?product_type=MADE_TO_ORDER&availability=available
```

→ visible requestable made-to-order Products.

```text
?product_type=MADE_TO_ORDER&availability=unavailable
```

→ normally empty under current V1 semantics.

---

# 51. Search Must Respect Publication

Phase 5.5 FULLTEXT/SQLite fallback must never return:

```text
is_published = false
```

Products.

Add the new public scope before search predicates.

---

# 52. Search Must Respect Active Variant Rules

SKU/attribute matches remain limited to:

```text
Variant.is_active = true
```

and parent:

```text
Product.is_active = true
Product.is_published = true
```

---

# 53. Sorting/Pagination Integration

Phase 5.6 ordering remains:

```text
public visibility
→ search
→ filters
→ sort
→ id ASC
→ paginate
```

Availability/product-type filters fit into the existing filter stage.

Do not change pagination architecture.

---

# 54. Price Still Comes From Variants

Adding Product Type does not create Product price storage.

For both:

```text
IN_STOCK
MADE_TO_ORDER
```

public Product price remains derived from the approved ProductVariant price rule.

---

# 55. MADE_TO_ORDER Price

The frozen contract requires Product price for MADE_TO_ORDER.

It represents:

```text
display / starting-at price
```

only.

Do not make it authoritative for Cart/Checkout.

---

# 56. Product Must Have Determinable Public Price

Do not return a Product that cannot satisfy the frozen non-null Product price contract.

Reuse the existing price eligibility behavior.

Do not emit:

```text
"price": null
```

to work around bad catalog data.

---

# 57. Public Product Summary

CAT-001 must now fully serialize:

```text
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

according to the existing contract.

---

# 58. Product Detail

CAT-002 must use exactly the same:

```text
product_type
availability
stock_indicator
```

values as CAT-001 for the same Product state.

Add regression coverage.

---

# 59. Embedded Variant Availability

CAT-002 embedded Variants must use the same availability resolver as:

```text
CAT-005
CAT-006
```

No divergence.

---

# 60. Variant API Consistency

For the same Variant:

```text
CAT-002 embedded
CAT-005 collection
CAT-006 detail
```

must produce identical:

```text
price
availability
stock_indicator
```

where fields overlap.

---

# 61. No Raw Stock Exposure

Even after availability is complete, public responses must never include:

```text
quantity
reserved_quantity
available_quantity
stock rows
warehouse_location
```

---

# 62. Public `availability` Enum

Keep exact lowercase values:

```text
available
unavailable
```

This is the frozen exception to the standard uppercase enum convention.

---

# 63. Stock Indicator Enum

Keep exact values:

```text
IN_STOCK
LOW_STOCK
MADE_TO_ORDER
```

Do not add aliases.

---

# 64. No `availability_display`

Do not introduce:

```text
availability_display
```

The contract explicitly rejects it.

---

# 65. Stock Indicator Is Not a Filter

Continue rejecting:

```text
?stock_indicator=LOW_STOCK
```

Only:

```text
?availability=
```

and:

```text
?product_type=
```

are filter inputs.

---

# 66. No Inventory Mutation

This phase must not change:

```text
quantity
reserved_quantity
warehouse location
```

Availability is read-derived.

---

# 67. No Reservation

Catalog reads must never increase:

```text
reserved_quantity
```

---

# 68. No Availability Cache Column

Do not add:

```text
products.availability
products.stock_indicator
product_variants.availability
product_variants.stock_indicator
```

These are derived values.

---

# 69. Avoid Stale Derived State

Do not persist coarse availability merely to make reads easy.

The stock source is small enough to derive correctly in V1.

---

# 70. Query Efficiency

CAT-001 must not issue:

```text
one stock query per Product
one stock query per Variant
```

Avoid N+1.

Use:

```text
subqueries
aggregate expressions
withSum
EXISTS
joinSub
```

or other clean Laravel/database mechanisms as appropriate.

---

# 71. Product Availability Query

Prefer SQL-level existence/aggregation so:

```text
availability filtering
sorting pipeline
pagination totals
```

remain correct before pagination.

Do not calculate availability after pagination in PHP.

---

# 72. Variant Availability Query

CAT-005/CAT-006 may use bounded eager-loaded aggregate stock data.

Do not load unrelated Product stock rows.

---

# 73. MySQL/SQLite Compatibility

Availability calculations should be expressed in SQL that works consistently on:

```text
SQLite tests
MySQL/MariaDB production
```

where practical.

Do not add another database-specific branch unless necessary.

---

# 74. Phase 5.5 Blocker Is Unchanged

Phase 5.5 remains:

```text
BLOCKED
```

until:

```text
disposable MySQL/MariaDB FULLTEXT integration check
+
project PHPStan baseline resolution
```

are complete.

Phase 5.7 does not erase that status.

---

# 75. Do Not Reinterpret SQLite Search Verification

SQLite may verify availability/filter composition.

It still does not prove native MySQL FULLTEXT behavior.

Report these separately.

---

# 76. Migration Safety

Because Phase 5.7 adds Product columns, migration verification matters.

Do not edit existing Group C migrations.

Create a new migration only.

---

# 77. Destructive Migration Guard

If using:

```text
php artisan migrate:fresh --seed --force
```

run only against:

```text
non-production
+
explicit disposable DB
+
database-name safety check
```

Do not infer disposability from `APP_ENV` alone.

---

# 78. SQLite Migration Verification

Ensure the new Product fields can rebuild cleanly under the canonical SQLite suite.

---

# 79. MySQL/MariaDB Migration Verification

Where a disposable MySQL/MariaDB DB is available:

verify:

```text
product_type
is_published
backfill
enum/value constraints
indexes if added
```

Do not use the normal application database.

---

# 80. Index Review

Because public queries now commonly use:

```text
is_active
is_published
product_type
```

inspect whether a targeted composite index is justified.

Do not blindly index every boolean.

---

# 81. Preferred Index Philosophy

Add an index only if it matches actual CAT-001 query shapes and EXPLAIN/query evidence.

Possible candidate:

```text
(is_active, is_published, product_type)
```

but do not add it automatically without reviewing current indexes/database plans.

---

# 82. No `availability` Index

Because availability is derived from Variant inventory:

do not create a fake Product availability index/column.

---

# 83. Factory Updates

Update ProductFactory so test/demo Products have explicit:

```text
product_type
is_published
```

states.

Default factory state should represent a normal usable Product unless current test conventions prefer draft by default.

Use explicit factory states such as conceptually:

```text
inStock()
madeToOrder()
published()
draft()
inactive()
```

only where useful.

Do not overbuild factory APIs.

---

# 84. Seeder Updates

Review DemoSeeder/reference Product data.

Ensure seeded Products explicitly reflect their intended:

```text
product_type
publication state
```

Do not let demo data rely on accidental DB defaults.

---

# 85. Test — Product Type Migration

Verify existing legacy Product rows are deterministically assigned:

```text
IN_STOCK
```

under migration compatibility behavior.

---

# 86. Test — Publication Migration

Verify existing active public legacy Products remain visible after migration/backfill.

---

# 87. Test — New Draft Default

If new Product DB default is:

```text
is_published = false
```

verify it.

Do not accidentally auto-publish new catalog entries.

---

# 88. Test — Public Visibility

Cover all combinations:

```text
active + published
→ visible

inactive + published
→ hidden

active + unpublished
→ hidden

inactive + unpublished
→ hidden

soft-deleted
→ hidden
```

---

# 89. Test — CAT-002 Masking

Unpublished Product by:

```text
slug
ID
```

must return:

```text
404
```

---

# 90. Test — Variant Parent Publication

Variant under unpublished Product:

```text
CAT-005
CAT-006
```

must not be accessible.

---

# 91. Test — Product Type Response

Verify Product Summary/Detail exposes:

```text
IN_STOCK
```

or:

```text
MADE_TO_ORDER
```

exactly.

---

# 92. Test — Product Type Filter

Verify both valid filter values.

Also test invalid values.

---

# 93. Test — Stock Aggregation by Location

Variant:

```text
location A:
quantity 5
reserved 2
available 3

location B:
quantity 4
reserved 1
available 3
```

must derive:

```text
Variant available quantity = 6
```

internally.

Do not expose `6` publicly.

---

# 94. Test — Fully Reserved Stock

Example:

```text
quantity 4
reserved 4
```

must produce:

```text
availability = unavailable
```

for IN_STOCK Variant if no other location has availability.

---

# 95. Test — Missing Stock Rows

Active IN_STOCK Variant with no ProductStock rows:

```text
availability = unavailable
```

---

# 96. Test — Variant Available

Positive available stock:

```text
availability = available
```

---

# 97. Test — Product Available Through One Variant

Product with:

```text
Variant A available = 0
Variant B available > 0
```

must be:

```text
availability = available
```

---

# 98. Test — Inactive Variant Stock Ignored

Product:

```text
active Variant available = 0
inactive Variant available = 100
```

must remain:

```text
availability = unavailable
```

for IN_STOCK.

---

# 99. Test — MADE_TO_ORDER Ignores Stock

MADE_TO_ORDER Product/Variant with:

```text
zero stock
no stock rows
```

still returns:

```text
availability = available
stock_indicator = MADE_TO_ORDER
```

when otherwise public/valid.

---

# 100. Test — Low Stock Boundary

If V1 threshold is 5:

```text
available = 0
→ unavailable / IN_STOCK

available = 1
→ available / LOW_STOCK

available = 5
→ available / LOW_STOCK

available = 6
→ available / IN_STOCK
```

Test exact boundary values.

---

# 101. Test — Product-Level Low Stock

Aggregate only active Variant availability.

Verify:

```text
aggregate 1..5
→ LOW_STOCK

aggregate >5
→ IN_STOCK
```

---

# 102. Test — MADE_TO_ORDER Indicator Always Wins

Even if stock rows exist accidentally for MADE_TO_ORDER:

```text
stock_indicator = MADE_TO_ORDER
```

Do not derive LOW_STOCK/IN_STOCK from inventory.

---

# 103. Test — Availability Filter

Verify:

```text
?availability=available
```

includes:

```text
stocked IN_STOCK
MADE_TO_ORDER
```

and excludes:

```text
out-of-stock IN_STOCK
hidden Products
```

---

# 104. Test — Unavailable Filter

Verify:

```text
?availability=unavailable
```

includes visible out-of-stock IN_STOCK Products only under current semantics.

---

# 105. Test — Product Type + Availability

Cover:

```text
IN_STOCK + available
IN_STOCK + unavailable
MADE_TO_ORDER + available
MADE_TO_ORDER + unavailable
```

---

# 106. Test — Search + Availability

Verify Phase 5.5 search composes correctly with new availability rules.

---

# 107. Test — Inactive Variant Search Regression

Retain and rerun:

```text
inactive matching Variant
must not surface Product
```

---

# 108. Test — Pagination Totals

Availability filters must not duplicate Products due to stock joins.

Paginator:

```text
total
```

must count distinct matching Products.

---

# 109. Test — CAT-001 / CAT-002 Consistency

Same Product state must produce identical:

```text
product_type
availability
stock_indicator
price
```

across collection/detail.

---

# 110. Test — Variant Consistency

Same Variant must produce identical:

```text
availability
stock_indicator
```

across:

```text
CAT-002
CAT-005
CAT-006
```

---

# 111. Test — No Raw Inventory Leakage

Search response JSON for prohibited keys such as:

```text
quantity
reserved_quantity
available_quantity
warehouse_location
```

where practical.

---

# 112. Test — Public Flags Hidden

Ensure CAT-001/CAT-002 do not expose:

```text
is_active
is_published
```

---

# 113. Test — No Side Effects

Availability reads must not mutate:

```text
Product
Variant
ProductStock
reserved_quantity
timestamps
```

---

# 114. Operational API Boundary

Future:

```text
CAT-013
CAT-014
INV-001
INV-002
```

may expose operational state where authorized.

Do not implement them here.

---

# 115. Catalog vs Cart Semantics

Keep these concepts distinct:

```text
catalog availability
→ informational discovery

cart purchasability
→ application/domain validation

checkout inventory
→ transactional authority
```

Do not collapse them into one boolean.

---

# 116. Do Not Add `is_purchasable` to Product Public API

Unless already frozen for Product itself, do not add:

```text
is_purchasable
```

to CAT-001/CAT-002.

Product Type + availability provide public presentation data.

Cart later owns purchasability.

---

# 117. MADE_TO_ORDER Request Eligibility

Do not fully implement Request-domain eligibility rules here.

Phase 5.7 only provides:

```text
product_type = MADE_TO_ORDER
```

as authoritative catalog classification.

Group J owns request validation/workflow.

---

# 118. OpenAPI

Update OpenAPI so runtime and contract agree on:

```text
ProductType
availability
stock_indicator
product_type filter
publication masking semantics where documented
```

Do not expose `is_published` publicly.

---

# 119. API Documentation

Update the consolidated docs with the finalized derivation rules, especially:

```text
IN_STOCK availability
MADE_TO_ORDER availability
LOW_STOCK threshold
multi-location stock aggregation
zero-stock stock_indicator interpretation
```

Do not leave these as implementation-only knowledge.

---

# 120. Decision Record

Record genuine new decisions:

```text
legacy Product type backfill
legacy publication backfill
LOW_STOCK threshold
MADE_TO_ORDER availability semantics
zero-stock stock_indicator behavior
```

Keep the ADR concise.

---

# 121. No New External Dependency

Expected:

```text
Composer packages:
NONE
```

---

# 122. No Frontend Changes

Do not modify:

```text
frontend/web/
frontend/app/
frontend/design-system/
```

Frontend badge/action presentation belongs to later groups.

---

# 123. No Inventory Adjustment UI/API

Do not implement:

```text
stock receive
stock decrement
warehouse transfer
manual adjustment
```

here.

---

# 124. No Concurrency Reservation Algorithm

This phase performs reads only.

Do not implement checkout stock locking.

---

# 125. Code Quality

Maintain:

```text
cognitive complexity <= 15
<= 3 returns where practical
small resolver methods
centralized enum/threshold constants
no duplicated inventory formulas
minimal comments
```

---

# 126. Likely Implementation Areas

Expected areas include:

```text
database/migrations/
app/Models/Product.php
app/Support/ProductType.php
catalog availability resolver/query
ProductCatalogQuery
Product/Variant resources
factories
seeders where necessary
tests/Feature/
tests/Unit/
docs/api/
docs/decisions.md
```

Modify only what this phase needs.

---

# 127. Static Analysis

Run:

```bash
vendor/bin/phpstan analyse
```

Phase 5.7 must introduce:

```text
0 new PHPStan errors
```

If the repository's previously reported PHPStan baseline remains unresolved:

report it separately.

Do not claim the global quality gate passes if it does not.

---

# 128. Phase 5.5 Blocker Reporting

The existing Phase 5.5 status remains:

```text
BLOCKED
```

until both:

```text
disposable MySQL/MariaDB FULLTEXT integration verification
project PHPStan baseline resolution
```

are complete.

Do not silently reclassify it during Phase 5.7.

---

# 129. Verification Commands

Run focused tests first.

Then:

```bash
php artisan test
vendor/bin/pint --test
vendor/bin/phpstan analyse
composer audit
git diff --check
```

If schema rebuild testing is needed:

use only the documented disposable DB guard.

---

# 130. MySQL/MariaDB Verification

Where disposable MySQL/MariaDB is available, verify:

```text
migration applies
legacy backfill correct
Product Type values persist canonically
publication values persist correctly
availability aggregate SQL behaves correctly
```

This may also be an opportunity to run the still-blocked Phase 5.5 FULLTEXT integration test, but do not make that implicit.

Report each verification independently.

---

# 131. Completion Report

Return:

## Phase 5.7 status

```text
PASS
```

or:

```text
BLOCKED
```

## Schema

Report:

```text
product_type
is_published
```

and migration/backfill behavior.

## Product Type

Confirm:

```text
IN_STOCK
MADE_TO_ORDER
```

only.

## Public visibility

Confirm:

```text
active
AND published
AND not deleted
```

plus existing catalog eligibility.

## IN_STOCK availability

State exact active-Variant/stock aggregation semantics.

## MADE_TO_ORDER availability

State that physical inventory is ignored for public availability.

## LOW_STOCK

State exact threshold and where it is centralized.

## Variant consistency

Report CAT-002/CAT-005/CAT-006 consistency.

## Filtering

Report:

```text
product_type
availability
```

behavior.

## Data exposure

Confirm raw stock and publication flags remain private.

## Phase 5.5 status

Restate its independent outstanding blockers if still unresolved.

## Frontend

Must state:

```text
NONE
```

## Dependencies

Expected:

```text
NONE
```

## Tests

Report focused and full counts.

## Quality

Report:

```text
Pint
PHPStan
Composer audit
git diff --check
```

---

# 132. Definition of Done

Phase 5.7 is complete when:

* `product_type` exists authoritatively on Product;
* `is_published` exists authoritatively on Product;
* legacy Product rows receive deterministic Product Type;
* legacy public Product visibility is preserved intentionally;
* future publication defaults are safe;
* Product Type uses only `IN_STOCK|MADE_TO_ORDER`;
* public Product scope requires active + published + non-deleted;
* hidden/unpublished Products return public 404;
* Variant public access requires public parent + active Variant;
* multi-location available stock aggregates correctly;
* reserved stock is deducted from available stock;
* inactive Variant stock is ignored;
* IN_STOCK Product availability is derived from active Variant inventory;
* IN_STOCK Variant availability is derived from its inventory;
* MADE_TO_ORDER does not depend on physical inventory;
* MADE_TO_ORDER returns `stock_indicator=MADE_TO_ORDER`;
* LOW_STOCK uses one explicit centralized V1 threshold;
* no per-Product threshold schema is introduced;
* zero-stock IN_STOCK Products use `availability=unavailable`;
* no new `OUT_OF_STOCK` enum is introduced;
* availability filter and response use identical semantics;
* Product Type filter is fully active;
* CAT-001 and CAT-002 agree;
* CAT-002/CAT-005/CAT-006 Variant availability agrees;
* search respects publication and active Variant restrictions;
* availability joins do not duplicate Product rows;
* pagination totals remain correct;
* raw inventory does not leak;
* `is_active`/`is_published` do not leak publicly;
* no Cart logic is implemented;
* no Checkout reservation is implemented;
* no Request workflow is implemented;
* no frontend code is changed;
* existing Group C inventory invariants remain green;
* Phase 5.2–5.6 regressions remain green;
* no new PHPStan failures are introduced;
* Pint passes;
* Composer audit has no new blocker.

---

# 133. Out of Scope

Do not implement:

```text
Cart admission
Checkout inventory reservation
inventory adjustment
warehouse management
per-Product low-stock thresholds
per-Variant low-stock thresholds
backorders
preorders
OUT_OF_STOCK enum
stock notifications
inventory history
request workflow
admin Product management
frontend availability badges
```

---

# 134. STOP Condition

STOP when the entire public catalog uses one authoritative model:

```text
Product visibility
=
active + published + non-deleted

Product type
=
IN_STOCK | MADE_TO_ORDER

IN_STOCK availability
=
derived from active Variant available inventory

MADE_TO_ORDER availability
=
requestable catalog availability independent of physical stock

public stock detail
=
availability + stock_indicator only
```

with identical semantics across CAT-001, CAT-002, CAT-005, CAT-006, search, filters, and pagination.

Do not continue automatically to the next phase.

DO NOT COMMIT, STAGE OR PUSH.

The project owner handles all Git operations.
