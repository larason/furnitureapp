# Phase 5.8 — Inventory Read Model

## Purpose

Implement the read-only operational inventory surface:

```text id="w2lg43"
INV-001
GET /api/v1/inventory
```

and:

```text id="hya7ik"
INV-002
GET /api/v1/inventory/{inventory}
```

using the existing Group C inventory model:

```text id="x2s3zd"
Product
    ↓
ProductVariant
        ↓
ProductStock
            ↓
warehouse_location
quantity
reserved_quantity
derived available_quantity
```

This phase is strictly read-only.

Do not implement inventory adjustments yet.

---

# 1. Preserve Group C Inventory Architecture

The authoritative inventory persistence model remains:

```text id="83tj8k"
product_stocks
```

with:

```text id="l76oze"
id
product_variant_id
warehouse_location
quantity
reserved_quantity
created_at
updated_at
```

and:

```text id="6kn29c"
available_quantity
=
quantity - reserved_quantity
```

derived, never persisted.

Do not redesign this schema.

---

# 2. Do Not Move Inventory to Product

Do not add:

```text id="5izhqb"
products.quantity
products.reserved_quantity
products.available_quantity
```

---

# 3. Do Not Move Inventory to Variant

Do not add:

```text id="9tygoi"
product_variants.quantity
product_variants.reserved_quantity
product_variants.available_quantity
```

Inventory remains Variant + location scoped.

---

# 4. Read Authoritative Files First

Before implementation inspect:

```text id="2bgcbo"
AGENTS.md
docs/VISION.md
docs/api/api-contract.md
docs/api/api-resources.md
docs/api/api-conventions.md
docs/api/openapi.yaml
docs/domain/business-rules.md
docs/decisions.md
```

Also inspect current:

```text id="8spqss"
ProductStock
ProductVariant
Product
inventory policies/permissions
Phase 5.7 availability resolver
```

Use the latest repository state.

---

# 5. Endpoint Scope

Implement only:

```text id="y62gwc"
INV-001
GET /api/v1/inventory
```

and:

```text id="w55n28"
INV-002
GET /api/v1/inventory/{inventory}
```

Do not implement:

```text id="ur3x9p"
INV-003
POST /api/v1/inventory/{product}/adjust
```

yet.

That belongs to Phase 5.9.

---

# 6. Authentication Required

Unlike CAT-001..006, inventory endpoints are not public.

Require authenticated Laravel-local identity resolved through the existing Clerk boundary.

Anonymous request:

```text id="z8v6pj"
401 AUTHENTICATION_REQUIRED
```

---

# 7. Authorization

Both read endpoints require:

```text id="yfwgrl"
inventory.view
```

through the existing Laravel authorization layer.

Allowed operational actors:

```text id="53anqt"
STAFF
ADMIN
```

subject to permission assignment.

Do not authorize simply because role == STAFF.

Permission remains authoritative.

---

# 8. CUSTOMER Must Not Read Inventory

CUSTOMER access to:

```text id="vzir87"
/api/v1/inventory
```

must return the existing unauthorized/forbidden behavior.

Do not expose operational quantities to Customers.

---

# 9. Public Catalog Separation

CAT APIs expose only:

```text id="m4okj3"
availability
stock_indicator
```

They must never expose:

```text id="hmwtng"
quantity
reserved_quantity
available_quantity
warehouse_location
```

Phase 5.8 must not weaken that boundary.

---

# 10. Operational Read Model

Inventory read APIs may expose exact operational quantities because they are protected by:

```text id="5iux29"
authentication
+
inventory.view
```

This is intentional.

---

# 11. Read Model Source

The read model must derive from:

```text id="i5htg9"
ProductStock
→ ProductVariant
→ Product
```

Do not create a second inventory table or materialized domain model.

---

# 12. Inventory Row Identity

Treat one `ProductStock` row as one inventory resource.

That means:

```text id="jyt5bs"
Inventory resource
=
one Variant
at one warehouse/location
```

This matches the Group C uniqueness rule:

```text id="1ea3dp"
UNIQUE(product_variant_id, warehouse_location)
```

---

# 13. `{inventory}` Resolution

`INV-002` path is frozen as:

```text id="s2gi6t"
/inventory/{inventory}
```

Resolve `{inventory}` using the stable public Inventory resource ID.

Do not reinterpret `{inventory}` as:

```text id="vhyk64"
product slug
product ID
variant SKU
warehouse name
```

unless newer authoritative docs explicitly changed the route.

---

# 14. Contract Ambiguity — Resolve Explicitly

Current docs contain wording that says:

```text id="kb3t09"
INV-002
"Get inventory by product/variant"
```

while the actual path is:

```text id="0huxf4"
/inventory/{inventory}
```

Do not implement ambiguous lookup semantics.

Preferred V1 clarification:

```text id="9p30wp"
INV-002
retrieves one ProductStock / Inventory resource
by Inventory ID.
```

Product/Variant lookup belongs in INV-001 filters if approved.

Record this clarification in the existing consolidated docs.

---

# 15. Inventory Resource Contract Review

Current OpenAPI Inventory representation contains:

```text id="97ji93"
id
product_id
variant_id
quantity
reserved_quantity
available_quantity
updated_at
```

Review this against the actual Group C schema before implementation.

---

# 16. `product_id` Is Derived

`product_stocks` does not have:

```text id="npws0e"
product_id
```

The API may expose `product_id` by deriving:

```text id="x7rtnj"
ProductStock
→ ProductVariant
→ Product
```

Do not add `product_id` to `product_stocks`.

---

# 17. `variant_id` Is Derived from FK

Expose:

```text id="7g63nh"
variant_id
```

from:

```text id="0nlgpd"
product_variant_id
```

through the normal public opaque ID representation.

Do not expose raw DB FK values if API IDs are transformed.

---

# 18. Variant ID Nullability Contract Gap

Group C requires every ProductStock row to belong to one Variant.

Therefore operational Inventory rows should naturally have:

```text id="5dprrw"
variant_id != null
```

The current OpenAPI allows `variant_id: null`.

Do not silently fabricate nullable semantics.

Review:

```text id="f6qcnx"
api-resources.md
api-contract.md
openapi.yaml
```

and minimally correct the contract if Group C remains authoritative.

Preferred interpretation:

```text id="s9b1dt"
Inventory.variant_id
required
non-null
```

because ProductStock cannot exist without ProductVariant.

---

# 19. `warehouse_location` Contract Gap

Group C distinguishes stock rows using:

```text id="w0n0sz"
warehouse_location
```

Yet current OpenAPI Inventory does not expose it.

This is operationally significant because:

```text id="z79zu4"
Variant A / main
Variant A / dar-es-salaam
```

are two distinct inventory records.

---

# 20. Resolve Location Visibility Deliberately

Before coding INV-001/002, determine whether the latest `api-resources.md` already exposes `warehouse_location`.

If yes:

follow it.

If docs remain inconsistent:

preferred Phase 5.8 clarification is to expose:

```text id="uvif69"
warehouse_location
```

on the STAFF/ADMIN Inventory representation.

Reason:

without it, two stock rows for the same Variant cannot be meaningfully distinguished operationally.

Do not expose location publicly in CAT endpoints.

---

# 21. Do Not Add Warehouse Entity

Even if `warehouse_location` becomes part of Inventory API:

do not create:

```text id="8tt77m"
warehouses table
Warehouse model
warehouse address
regions
delivery zones
```

V1 location remains the existing bounded machine string.

---

# 22. Inventory Representation

Preferred operational Inventory resource after contract reconciliation:

```text id="nxip8s"
id
product_id
variant_id
warehouse_location
quantity
reserved_quantity
available_quantity
updated_at
```

Do not automatically add Product/Variant full objects unless current contract explicitly requires them.

---

# 23. Example Shape

Conceptually:

```json id="1rfiwj"
{
  "id": "inv_...",
  "product_id": "prod_...",
  "variant_id": "var_...",
  "warehouse_location": "dar-es-salaam",
  "quantity": 12,
  "reserved_quantity": 3,
  "available_quantity": 9,
  "updated_at": "2026-09-22T09:00:00Z"
}
```

Use the actual opaque ID conventions.

---

# 24. `available_quantity`

Always calculate:

```text id="uc4bsy"
quantity - reserved_quantity
```

Do not store it.

Do not accept it from clients.

---

# 25. Preserve Inventory Invariant

Existing invariant remains:

```text id="ux5fbc"
0 <= reserved_quantity <= quantity
```

Phase 5.8 reads it.

Do not redesign or weaken enforcement.

---

# 26. Read Model Is Current State

INV-001/002 expose the current operational inventory state.

They are not:

```text id="9m34yf"
inventory history
stock ledger
adjustment audit log
reservation history
```

---

# 27. No Historical Reconstruction

Do not infer:

```text id="zqmlnn"
how stock became 12
who changed it
previous quantities
```

from timestamps.

History/audit belongs to later mutation/audit phases.

---

# 28. INV-001 Collection

Implement:

```http id="8fji6k"
GET /api/v1/inventory
```

as an operational paginated collection.

---

# 29. Pagination

Use existing global conventions:

```text id="9cn7l6"
page
per_page
```

with:

```text id="ybyfn0"
page >= 1
per_page default 20
per_page max 100
```

---

# 30. Pagination Metadata

Return:

```text id="5jr2l3"
meta.pagination.current_page
meta.pagination.per_page
meta.pagination.total
meta.pagination.last_page
meta.pagination.has_next
meta.pagination.has_previous
```

No raw Laravel paginator fields.

---

# 31. Deterministic Ordering

If no more specific frozen inventory sort exists:

use:

```text id="uy4m2w"
updated_at DESC
id ASC
```

only if consistent with current operational collection conventions.

If docs define another inventory ordering, use that.

Do not use random DB order.

---

# 32. Do Not Invent User Sorting

Do not add:

```text id="5tljna"
?sort=quantity
?sort=available_quantity
?sort=warehouse_location
```

unless already approved by current API contract.

Phase 5.8 is not a query-language expansion phase.

---

# 33. Inventory Filtering

Inspect the frozen INV-001 contract before adding filters.

Do not assume CAT-001 query parameters apply to inventory.

---

# 34. Preferred Minimal Filters if Contract Requires Clarification

If current docs say "paginated, filtered" but do not define exact filters, resolve minimally around existing identifiers:

```text id="pxt0u6"
product
variant
warehouse_location
```

only if necessary for operational usability.

Do not add arbitrary field filtering.

---

# 35. Product Filter

If approved:

```text id="5ait50"
?product={product_id|slug}
```

should resolve through existing Product resolver semantics where appropriate.

Do not create a new Product identity convention.

---

# 36. Variant Filter

If approved:

```text id="4x15n5"
?variant={variant_id}
```

must use stable Variant ID.

Do not resolve by SKU unless contract explicitly permits it.

---

# 37. Location Filter

If approved:

```text id="c9ngtf"
?warehouse_location=dar-es-salaam
```

must be exact bounded string matching.

Do not implement fuzzy location searching.

---

# 38. No Arbitrary Query Columns

Reject or ignore according to global conventions:

```text id="hy05of"
?reserved_quantity_gt=
?stock_lt=
?product_name=
?warehouse_contains=
```

unless explicitly contracted.

---

# 39. No Public Availability Filter Reuse

Do not automatically reuse:

```text id="c6s46p"
?availability=
```

from CAT-001.

Inventory read model deals in exact operational quantities, not public coarse availability.

Only add if the frozen INV contract explicitly defines it.

---

# 40. No Product Type Filter by Default

Do not add:

```text id="vefcqx"
?product_type=
```

unless existing operational contract explicitly includes it.

---

# 41. INV-002 Detail

Implement:

```http id="1j2sya"
GET /api/v1/inventory/{inventory}
```

as one operational Inventory resource.

---

# 42. Unknown Inventory

Return:

```text id="886hdp"
404 RESOURCE_NOT_FOUND
```

using canonical error envelope.

---

# 43. Authorization Before Serialization

Do not serialize Inventory data before confirming:

```text id="2md0ii"
authenticated
inventory.view authorized
```

Operational quantities are sensitive business data.

---

# 44. Staff Visibility

Staff with:

```text id="qywko8"
inventory.view
```

may read operational inventory.

Staff without permission:

```text id="ezjm8z"
403 FORBIDDEN
```

according to current authorization policy.

---

# 45. Admin Visibility

Admin does not receive a universal bypass through public assumptions.

Use the existing permission system.

If ADMIN receives `inventory.view` by seeded permissions, authorize through that.

---

# 46. CUSTOMER Isolation

Customer must never access exact stock values.

Add explicit tests.

---

# 47. Authentication Failure

Anonymous:

```text id="316edh"
401
```

Do not return:

```text id="2sdqgd"
404
```

merely to hide the route.

This is a protected operational endpoint.

---

# 48. Cache Control

Inventory endpoints contain operational state.

Use:

```text id="6qrcrf"
private
no-store
```

or the repository's current protected operational cache policy.

Never mark:

```text id="2lvsxg"
public
```

or CDN-cache inventory responses.

---

# 49. No CAT Resource Reuse if It Leaks Shape

Do not reuse public Product resources to serialize Inventory if that causes unnecessary catalog fields.

Inventory should have an explicit operational resource.

---

# 50. InventoryResource

Create/use an explicit resource such as:

```text id="eldb33"
InventoryResource
```

Do not serialize ProductStock directly.

---

# 51. Do Not Use `toArray()`

Never:

```php id="3p287q"
return $stock->toArray();
```

Operational resources still require allow-listed fields.

---

# 52. No Internal DB FK Leakage

Do not expose raw:

```text id="fie1mr"
product_variant_id
```

if public API uses:

```text id="y23bzw"
variant_id
```

Use the contracted name.

---

# 53. No Created Timestamp Unless Contracted

Current Inventory schema requires:

```text id="1q8jtm"
updated_at
```

but not necessarily:

```text id="hmamvr"
created_at
```

Do not add created_at opportunistically.

---

# 54. Product ID Derivation Efficiency

Avoid one Product query per Inventory row.

Use:

```text id="udixh0"
ProductStock
→ eager-loaded ProductVariant
→ Product
```

or joins/subqueries as appropriate.

---

# 55. N+1 Prevention

INV-001 must not produce:

```text id="0hxnlg"
1 query for inventory
+
N queries for variants
+
N queries for products
```

Use bounded eager loading.

---

# 56. Quantity Semantics

`quantity` means:

```text id="uf4kmj"
physical units owned at this location
```

Do not reinterpret it as:

```text id="832p38"
sellable units
available units
ordered units
```

---

# 57. Reserved Quantity

`reserved_quantity` means:

```text id="u1psrz"
units currently reserved and unavailable
for another reservation/consumption
```

It is operational state.

---

# 58. Available Quantity

`available_quantity` means:

```text id="0qi1wj"
quantity - reserved_quantity
```

at that specific inventory row/location.

Do not aggregate it in the row representation.

---

# 59. Product-Level Aggregate Is Separate

Phase 5.7 may aggregate inventory across active Variants for public availability.

INV-001/002 should still expose the underlying operational row values.

Do not replace location rows with Product-level totals.

---

# 60. Do Not Hide Zero-Stock Rows

Operational inventory lists should include legitimate rows where:

```text id="ymlqra"
quantity = 0
reserved_quantity = 0
available_quantity = 0
```

unless contract explicitly filters them.

A zero-stock row is meaningful operational state.

---

# 61. Inactive Product/Variant Operational Read

Unlike public CAT endpoints, operational inventory may need to show stock attached to:

```text id="a6zjrx"
inactive Product
inactive Variant
unpublished Product
```

Do not automatically reuse public visibility scope.

---

# 62. Important Separation

Public catalog visibility:

```text id="yapmdn"
active + published + not deleted
```

Operational inventory visibility:

```text id="zvwmvc"
authorized inventory record
```

These are different.

Do not hide operational stock merely because the catalog Product is unpublished.

---

# 63. Soft-Deleted Product Consideration

Inspect FK/soft-delete semantics.

If a Product is soft-deleted but Variant/stock still exists:

operational inventory read may still need it for reconciliation.

Do not automatically scope through `Product::public()`.

Use current business/domain contract.

---

# 64. No Product Restoration Logic

Reading inventory for archived/inactive records does not imply restoring Products.

No mutation belongs here.

---

# 65. MADE_TO_ORDER Rows

Phase 5.7 says MADE_TO_ORDER public availability ignores stock.

However, if ProductStock rows exist operationally for a MADE_TO_ORDER Product:

INV-001/002 may still display them.

Do not hide or reinterpret the physical stock table.

---

# 66. No Public Stock Indicator Needed

Operational Inventory resource does not need:

```text id="k3tege"
availability
stock_indicator
```

unless the frozen Inventory contract explicitly includes them.

It already exposes exact quantities.

Avoid redundant representation.

---

# 67. No Inventory Calculation Duplication

Reuse:

```text id="k2rila"
ProductStock.available_quantity
```

or equivalent authoritative domain accessor.

Do not rewrite:

```text id="w14zxr"
quantity - reserved_quantity
```

in multiple resources/controllers.

---

# 68. Read-Only Means No Locks Needed

INV-001 and INV-002 are observational reads.

Do not add:

```text id="bar2f5"
SELECT ... FOR UPDATE
pessimistic locks
transactions solely for reading
```

Normal consistent reads are sufficient.

---

# 69. Snapshot Nature

Inventory values are point-in-time operational reads.

The response does not guarantee the quantities remain unchanged after the request.

Document this if needed.

---

# 70. No Checkout Guarantee

Even if INV-002 says:

```text id="sbsu2f"
available_quantity = 5
```

checkout later must still revalidate inventory transactionally.

Do not treat read data as reservation authority.

---

# 71. No Mutation Through GET

GET must never:

```text id="ypo7vq"
reserve stock
adjust stock
normalize quantities
create missing stock rows
touch timestamps
```

---

# 72. Missing Stock Row

Do not auto-create inventory when a Variant has no ProductStock rows.

Absence remains absence.

---

# 73. INV-001 Lists Records, Not Every Variant

Do not fabricate zero-valued Inventory resources for Variants that have no ProductStock rows unless contract explicitly defines that projection.

Preferred:

```text id="f5cb35"
INV-001
lists persisted inventory rows.
```

---

# 74. No Inventory Aggregation Table

Do not create:

```text id="ng7o4n"
inventory_summary
product_inventory
variant_inventory_totals
```

for Phase 5.8.

---

# 75. Search

Do not automatically implement full-text search over inventory.

Phase 5.5 FULLTEXT belongs to public Product discovery.

Inventory does not need Algolia/MySQL FULLTEXT.

---

# 76. Inventory Query Should Be Simple

Expected query shape:

```text id="9ho2he"
authorized ProductStock rows
→ optional approved exact filters
→ deterministic sort
→ pagination
→ InventoryResource
```

---

# 77. Rate Limiting

Use the existing protected operational/read limiter appropriate for Staff/Admin.

Do not use public-read limits.

Do not invent a new inventory-specific rate limiter unless current Phase 4.11 categories require one.

---

# 78. Error Contract

Use canonical errors.

Do not return:

```json id="5uew0o"
{"message":"No inventory"}
```

as an ad hoc response.

---

# 79. Empty Inventory Collection

Valid empty list:

```json id="k3vufm"
{
  "data": [],
  "meta": {
    "pagination": {
    }
  }
}
```

Do not return 404.

---

# 80. Beyond Last Page

Same global convention:

```text id="xg153w"
200
data = []
```

with valid pagination metadata.

---

# 81. Tests — Authentication

Test:

```text id="hc07te"
anonymous INV-001
→ 401

anonymous INV-002
→ 401
```

---

# 82. Tests — CUSTOMER Forbidden

Authenticated CUSTOMER:

```text id="9mndvq"
INV-001
INV-002
```

must not receive inventory data.

---

# 83. Tests — STAFF Permission

STAFF with:

```text id="266amg"
inventory.view
```

can access both endpoints.

---

# 84. Tests — STAFF Without Permission

Must fail according to current authorization contract.

---

# 85. Tests — ADMIN

ADMIN with appropriate seeded permission can read inventory.

Do not rely on a universal role bypass.

---

# 86. Tests — Collection Envelope

Verify:

```text id="alj1wn"
data
meta.pagination
```

and no raw paginator internals.

---

# 87. Tests — Pagination

Cover:

```text id="up322q"
default page
custom per_page
second page
beyond-last page
empty dataset
```

---

# 88. Tests — Inventory Resource Fields

Assert exact approved operational fields.

Do not merely test 200.

---

# 89. Tests — Product ID Derivation

Given:

```text id="a4mk3y"
Product
→ Variant
→ Stock
```

Inventory resource `product_id` must refer to the correct Product.

---

# 90. Tests — Variant ID Derivation

Verify:

```text id="ziqewl"
variant_id
```

matches the stock row's Variant.

---

# 91. Tests — Location

If Phase 5.8 resolves location as public-to-operations:

verify exact:

```text id="tx6ank"
warehouse_location
```

serialization.

---

# 92. Tests — Multi-Location Rows

Create:

```text id="s5t0xa"
same Variant
main
dar-es-salaam
```

INV-001 must return two distinct Inventory resources.

Do not aggregate them into one.

---

# 93. Tests — Quantity Derivation

Example:

```text id="gzvhav"
quantity = 10
reserved = 4
```

must expose:

```text id="5iudw8"
available_quantity = 6
```

---

# 94. Tests — Fully Reserved

```text id="9gg4u8"
quantity = 5
reserved = 5
available = 0
```

---

# 95. Tests — Zero Stock

```text id="esbe7y"
0
0
0
```

must serialize correctly.

---

# 96. Tests — Exact Detail

INV-002 returns exactly the selected Inventory row.

---

# 97. Tests — Unknown Inventory

Return:

```text id="qj4a52"
404 RESOURCE_NOT_FOUND
```

---

# 98. Tests — No Cross-Resource Confusion

Product ID, Variant ID, and Inventory ID must not be interchangeable.

Passing a Product ID in `{inventory}` should not accidentally resolve a stock row unless the contract explicitly says otherwise.

---

# 99. Tests — Operational Hidden Product

If current domain permits stock on an inactive/unpublished Product:

authorized inventory read should still expose the Inventory record.

This protects public/operational scope separation.

---

# 100. Tests — No Public Leakage

CAT-001/CAT-002 must still not expose:

```text id="1e4d63"
quantity
reserved_quantity
available_quantity
warehouse_location
```

after Phase 5.8.

Add regression coverage if not already present.

---

# 101. Tests — No Mutation

Compare stock rows before/after:

```text id="vvavl5"
INV-001
INV-002
```

No quantity, reservation, or timestamps should change.

---

# 102. Tests — N+1

Where practical, guard against obvious:

```text id="z6omhw"
ProductStock
→ Variant
→ Product
```

N+1 queries.

---

# 103. Tests — Cache Headers

Protected inventory response must not be publicly cacheable.

Verify current private/no-store convention if headers are already machine-tested.

---

# 104. No Audit Event for Reads

Inventory reads should not create privileged mutation audit entries merely because stock was viewed.

Audit mutation belongs to INV-003.

Access logging may remain normal application logging.

---

# 105. No Adjustment Logic

Do not implement:

```text id="4k955o"
quantity_delta
adjustment reason
Idempotency-Key
inventory locking
audit event
```

in Phase 5.8.

Those belong to Phase 5.9 / 5.10.

---

# 106. No Generic PATCH

Do not create:

```text id="r8zpp9"
PATCH /api/v1/inventory/{inventory}
```

The frozen contract explicitly uses controlled:

```text id="wy7zcg"
POST /inventory/{product}/adjust
```

for future mutation.

---

# 107. No Delete

Do not implement:

```text id="ifbdua"
DELETE /inventory/{inventory}
```

Inventory deletion is not an ordinary API action.

---

# 108. No Reservation Endpoint

Do not create:

```text id="hke8wa"
/inventory/reserve
/inventory/release
```

Checkout will own reservation behavior.

---

# 109. No Stock Ledger

Do not introduce:

```text id="uzckku"
inventory_movements
stock_transactions
```

unless a later phase explicitly requires them.

---

# 110. No Warehouse Model

Do not normalize location yet.

---

# 111. OpenAPI Reconciliation

Update OpenAPI only after reconciling the read model.

Specifically verify:

```text id="y4hqxw"
Inventory.variant_id nullability
warehouse_location presence
INV-001 response pagination
INV-002 {inventory} semantics
auth/security
403
404
429
```

---

# 112. Documentation Reconciliation

If the current wording:

```text id="fxn89q"
"Get inventory by product/variant"
```

conflicts with:

```text id="63ukwx"
/inventory/{inventory}
```

clarify the docs.

Do not leave two lookup semantics.

---

# 113. Preferred V1 Inventory Detail Meaning

Unless newer repository authority says otherwise:

```text id="7cguwt"
{inventory}
=
Inventory resource ID
=
ProductStock row ID
```

This is the least surprising REST/resource interpretation and matches the frozen route parameter.

---

# 114. Operational Product Views Remain Separate

Do not fold:

```text id="rtvs9j"
CAT-013
CAT-014
```

into INV-001/002.

Operational Product APIs expose Product management context.

Inventory APIs expose stock resources.

---

# 115. Schema Changes

Expected:

```text id="9oq2bd"
NONE
```

The existing Group C schema already supports the read model.

---

# 116. Stop on Schema Temptation

If implementation seems to require:

```text id="z8b5rq"
product_id column
available_quantity column
warehouse table
inventory status column
```

stop and use existing relationships/derivation instead.

---

# 117. No New Dependencies

Expected:

```text id="at7b25"
NONE
```

Laravel/Eloquent/API Resources are sufficient.

---

# 118. SQLite / MySQL

Phase 5.8 inventory semantics should be testable under canonical SQLite.

There is no FULLTEXT-specific behavior here.

Keep Phase 5.5's separate MySQL FULLTEXT blocker unchanged.

---

# 119. PHPStan

Phase 5.8 must introduce:

```text id="l34suc"
0 new PHPStan errors
```

If the known project baseline remains unresolved:

report it separately.

Do not claim global static-analysis PASS if the baseline still fails.

---

# 120. Phase 5.5 Status Is Independent

Do not mark Phase 5.5 PASS merely because Phase 5.8 succeeds.

Phase 5.5 still requires its own:

```text id="jtmyai"
disposable MySQL/MariaDB FULLTEXT verification
+
PHPStan baseline resolution
```

if those remain outstanding.

---

# 121. Likely Implementation Areas

Expected:

```text id="v6bhwq"
routes/api.php
InventoryController
InventoryResource
InventoryQueryRequest
ProductStock model/relations if read helpers needed
policy/gate wiring
tests/Feature/
docs/api/
docs/decisions.md
openapi.yaml
```

Do not broaden the phase.

---

# 122. Verification Commands

Run focused inventory tests.

Then:

```bash id="qs0oeq"
php artisan test
vendor/bin/pint --test
vendor/bin/phpstan analyse
composer audit
git diff --check
php artisan route:list
```

No destructive migration should normally be required.

---

# 123. Route Review

Verify exactly:

```text id="xv039t"
GET /api/v1/inventory
GET /api/v1/inventory/{inventory}
```

for this phase.

Do not add INV-003 yet.

---

# 124. Completion Report

Return:

## Phase 5.8 status

```text id="x8pgc9"
PASS
```

or:

```text id="ik3ehc"
BLOCKED
```

## INV-001

Report:

```text id="dawcca"
authentication
inventory.view
pagination
filters if any
ordering
```

## INV-002

Report:

```text id="3ljknp"
Inventory ID resolution
404 semantics
```

## Inventory read model

State exact exposed fields.

## Contract reconciliation

State decisions for:

```text id="by5tmd"
variant_id nullability
warehouse_location
{inventory} meaning
```

## Quantity semantics

Confirm:

```text id="zzpzkd"
quantity
reserved_quantity
available_quantity = quantity - reserved_quantity
```

## Multi-location behavior

Confirm one Inventory resource per Variant/location row.

## Public separation

Confirm CAT endpoints still expose no exact inventory quantities.

## Schema

Must state:

```text id="3hdhy1"
NONE
```

## Mutation

Must state:

```text id="sbup9h"
NONE
```

## Frontend

Must state:

```text id="3pc9ku"
NONE
```

## Tests

Report focused/full counts.

## Quality

Report:

```text id="z4csu1"
Pint
PHPStan
Composer audit
git diff --check
```

---

# 125. Definition of Done

Phase 5.8 is complete when:

* INV-001 exists;
* INV-002 exists;
* both require authentication;
* both require `inventory.view`;
* CUSTOMER cannot read operational inventory;
* Staff/Admin authorization uses permissions, not blanket role trust;
* INV-001 is paginated;
* Inventory resources derive from ProductStock;
* Product ID is derived through Variant → Product;
* Variant ID correctly maps ProductStock ownership;
* `{inventory}` has one unambiguous meaning;
* warehouse/location semantics are explicitly resolved;
* one Variant/location row remains one Inventory resource;
* exact quantity is exposed only operationally;
* reserved quantity is exposed only operationally;
* available quantity is derived, not persisted;
* multi-location rows are not accidentally aggregated;
* zero-stock rows remain visible operationally;
* inactive/unpublished Product stock is not hidden merely by public catalog scope;
* public CAT endpoints continue hiding exact inventory data;
* no inventory mutation occurs;
* no locks/reservations are introduced;
* no INV-003 code is pulled forward;
* no generic PATCH inventory endpoint is created;
* no schema redesign occurs;
* no warehouse entity is introduced;
* no frontend changes occur;
* focused tests pass;
* catalog availability regression tests remain green;
* no new PHPStan errors are introduced;
* Pint passes;
* Composer audit has no new blocker.

---

# 126. Out of Scope

Do not implement:

```text id="wfzi5n"
INV-003 inventory adjustment
inventory mutation
quantity_delta
adjustment reasons
idempotency
stock locking
overselling protection
checkout reservation
inventory audit log
inventory history
warehouse entity
warehouse transfers
frontend inventory dashboard
```

---

# 127. STOP Condition

STOP when Staff/Admin with `inventory.view` can safely inspect the existing ProductStock state through:

```text id="xlp6ka"
GET /api/v1/inventory
GET /api/v1/inventory/{inventory}
```

with exact operational quantities, proper Variant/Product identity, explicit location semantics, pagination, authorization, and no mutations.

Do not continue automatically to Phase 5.9.

DO NOT COMMIT, STAGE OR PUSH.

The project owner handles all Git operations.
