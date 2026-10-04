# Phase 11.3 — Product CRUD

## 1. Objective

Implement Version 1 administrative Product management for authorized Staff/Admin.

Before implementing Product CRUD, first resolve the confirmed frozen-contract/runtime inconsistency around Product pricing.

The phase therefore executes in two ordered parts:

```text
Part A — Product price persistence reconciliation
Part B — Product operational CRUD implementation
```

Do not begin Part B until Part A is complete, documented, migrated, tested, and verified.

---

# PART A — PRODUCT PRICE PERSISTENCE RECONCILIATION

## 2. Confirmed Contract/Runtime Gap

The frozen V1 API requires Product-level price input and output:

```text
CAT-007 ProductCreateRequest
→ price REQUIRED

CAT-008 ProductUpdateRequest
→ price mutable

CAT-001/CAT-002
→ Product.price REQUIRED and non-null

CAT-013/CAT-014
→ operational Product price
```

But current persistence places price only on:

```text
product_variants
```

and the Product table has no Product-level price columns.

Creating a Variant also requires:

```text
sku
name / variant_name
```

which CAT-007 does not accept.

There is no approved rule that CAT-007 silently creates a synthetic/default Variant.

Therefore Phase 11.3 must first reconcile persistence with the frozen external API.

---

# 3. Chosen Reconciliation

Adopt the following authoritative reconciliation:

```text
Product owns the V1 base/display price.

ProductVariant owns variant-specific price.
```

Conceptually:

```text
Product
├── base/display price
│
└── ProductVariants
    ├── sku
    ├── name
    ├── attributes
    ├── dimensions
    └── variant-specific price
```

Do NOT change CAT-007 into Product+Variant creation.

Do NOT introduce an `initial_variant` request object.

Do NOT automatically create hidden/default Variants.

Do NOT remove `price` from Product create/update.

---

# 4. Why This Reconciliation Is Required

The frozen HTTP contract already presents:

```text
product.price
```

as a first-class Product field.

Changing the API instead of persistence would create larger V1 compatibility problems.

Therefore preserve:

```text
ProductCreateRequest
ProductUpdateRequest
Product public representation
Product operational representation
CAT-010 variant separation
```

and reconcile the internal persistence model underneath them.

---

# 5. New Product Price Persistence

Add Product-level base price persistence.

Use repository naming conventions, conceptually:

```text
products.price_amount
products.price_currency
```

or:

```text
products.base_price_amount
products.base_price_currency
```

Choose the exact names only after reviewing existing schema naming conventions.

Prefer the smallest naming that matches existing money-column conventions.

---

# 6. Money Storage Rules

Product base price must follow global money rules:

```text
integer minor units
currency = TZS
no float
no decimal money
no formatted strings
```

Example:

```text
1,250,000 TZS
→ amount = 125000000
currency = TZS
```

---

# 7. Database Constraints

Add a new migration.

Do not edit historical migrations.

The Product price columns must enforce:

```text
amount >= 0
currency valid
amount/currency present together
```

Because frozen V1 requires Product.price non-null, new Products must always have Product price.

For existing rows, use a safe migration/backfill strategy.

---

# 8. Existing Product Backfill

Existing Products already have Variants.

Backfill each Product's Product-level price from its existing canonical public price source.

Before migration logic, inspect current Group E projection and determine how Product.price is currently derived.

Possible existing rule may be:

```text
default active Variant price
```

or another already-documented rule.

Use the actual existing projection.

Do not invent:

```text
minimum variant price
average variant price
first DB row
latest variant
```

unless that is already the established rule.

---

# 9. Backfill Must Be Deterministic

Every existing Product must resolve to exactly one base price.

If an existing Product cannot be mapped safely:

STOP.

Report:

```text
Product price migration blocker
Product: <opaque/reference-safe identifier>
Reason: no deterministic existing canonical price source
```

Do not assign zero merely to satisfy NOT NULL.

---

# 10. Migration Atomicity

The migration must not leave:

```text
some Products with Product-level price
some without
```

if frozen schema requires price.

Use a safe staged migration if required:

```text
1. add nullable columns
2. deterministic backfill
3. verify no nulls
4. add non-null/constraints
```

Implement using repository migration conventions.

---

# 11. No Variant Mutation During Backfill

Do not modify existing Variant prices.

The reconciliation creates a Product-level base/display price source.

It does not rewrite historical Variant pricing.

---

# 12. Product Price Meaning

Define:

```text
Product.price
= Product base/display price
```

This price is always present.

---

# 13. MADE_TO_ORDER Semantics

For:

```text
product_type = MADE_TO_ORDER
```

Product base price means:

```text
display price
starting-at price
informational catalog price
SEO/display amount
```

It is NOT:

```text
quote
checkout price
payment amount
price commitment
```

MADE_TO_ORDER remains excluded from ordinary Cart/Checkout.

---

# 14. IN_STOCK Semantics

For:

```text
product_type = IN_STOCK
```

Product base price is the Product-level catalog/display price.

Where a selected ProductVariant exists:

```text
variant.price
```

remains the authoritative variant-specific purchase price.

Do not remove Variant price.

---

# 15. Product vs Variant Price

The architecture becomes:

```text
Product.price
→ base/display/catalog price

ProductVariant.price
→ price for that exact SKU/variant
```

This is not duplicate authority over the same semantic.

They represent two different pricing levels.

---

# 16. Multi-Variant Products

Do not derive Product.price dynamically from Variants after reconciliation.

Once Product owns persisted base price:

```text
Product.price
```

is explicit Product state.

Variants may:

```text
equal Product.price
or
override Product.price
```

depending on variant configuration.

---

# 17. Product Price Independence

Changing one Variant price must NOT silently rewrite:

```text
Product.price
```

Changing Product.price must NOT automatically rewrite every Variant price.

These are separate explicit fields after reconciliation.

---

# 18. Public Product Projection

Refactor CAT-001/CAT-002 Product price projection to read from the new Product base price.

Do not keep a hidden second dynamic fallback indefinitely.

After migration:

```text
Product.price
→ products base price columns
```

---

# 19. Variant Projection

CAT-005/CAT-006 and embedded Variant summaries continue reading:

```text
ProductVariant.price
```

unchanged.

---

# 20. No Synthetic Variant

CAT-007 must NOT create a Variant just because Product price exists.

A Product may exist before variants are added.

---

# 21. CAT-010 Remains Separate

Variant creation remains:

```http
POST /api/v1/products/{product}/variants
```

CAT-010.

This preserves the frozen resource boundary:

```text
CAT-007 = Product creation
CAT-010 = Variant creation
```

---

# 22. Variantless Product

A Product may therefore exist with:

```text
Product.price present
variants = []
```

This must be a valid Product persistence state unless existing domain invariants explicitly forbid it.

Do not invent a mandatory Variant solely for price storage.

---

# 23. Variantless MADE_TO_ORDER Product

Valid.

Example:

```text
Product
type = MADE_TO_ORDER
price = starting-at amount
variants = []
```

This works naturally for the current request-first production mode.

---

# 24. Variantless IN_STOCK Product

Persistence may be valid.

Its availability remains governed by Group E inventory/variant rules.

Do not fabricate stock or Variant rows.

If no active purchasable Variant/stock exists:

```text
availability = unavailable
```

according to existing catalog logic.

---

# 25. Cart/Checkout Pricing

Do not redesign Cart or Checkout.

Preserve existing rule:

```text
selected Variant exists
→ variant-specific price remains authoritative
```

If existing transactional behavior supports variantless IN_STOCK Products, verify how it obtains price.

If that path is currently undefined because transactional commerce is deferred:

document it as a Group G/I follow-up rather than inventing Checkout behavior in 11.3.

---

# 26. Current Request-Only Production Safety

The current production release publishes:

```text
MADE_TO_ORDER
```

Products.

Therefore this reconciliation is immediately useful without requiring transactional commerce.

---

# 27. Schema Migration Tests

Test:

```text
price columns exist
integer amount
TZS currency
non-negative constraint
non-null after migration
amount/currency consistency
existing Products correctly backfilled
```

---

# 28. Product Model

Update Product model with explicit price fields/casts/value mapping.

Do not expose raw DB column details directly to API resources.

---

# 29. Money Value Object

Reuse existing Money infrastructure.

Do not create Product-specific money logic.

---

# 30. Product Factory

Update ProductFactory so every generated Product has a valid Product base price.

Do not make ProductFactory silently create Variants unless explicit test state requires them.

---

# 31. Seed Data

Update seeders only as required by the new Product price persistence.

Preserve deterministic demo/reference data.

Do not alter production-safe seeding policy.

---

# 32. Existing Product/Variant Factory Relationships

Keep:

```text
Product factory
≠ automatically Variant factory
```

unless an explicit test state such as:

```text
withVariants()
```

already exists.

---

# 33. Backward API Compatibility

The reconciliation must preserve exactly:

```json
"price": {
  "amount": 125000000,
  "currency": "TZS"
}
```

No HTTP request or response shape change.

---

# 34. OpenAPI

Expected:

```text
UNCHANGED
```

No `initial_variant`.

No nullable Product price.

No new required request fields.

---

# 35. Documentation Reconciliation

Update documentation explaining the revised internal architecture.

Record explicitly:

```text
Previous schema design made ProductVariant the sole pricing persistence boundary.

Frozen V1 API independently requires a non-null Product-level price.

Phase 11.3 reconciles this by persisting Product base/display price while retaining Variant-specific pricing.

This is a persistence reconciliation preserving the frozen external contract.
```

---

# 36. ADR

Add a specific ADR before Product CRUD implementation.

Conceptual title:

```text
Product Base Price Persistence Reconciliation
```

Use next repository-consistent ADR identifier.

---

# 37. ADR Must State

At minimum:

```text
Product.price remains frozen API field.
Product base price is now persisted on Product.
Variant price remains variant-specific.
No synthetic default Variant.
No initial_variant request field.
CAT-007 remains Product-only creation.
CAT-010 remains Variant creation.
MADE_TO_ORDER Product price is informational/starting-at.
No Product API compatibility break.
Existing Product rows are deterministically backfilled.
```

---

# 38. Price Reconciliation Exit Gate

Do NOT continue to CAT-007 implementation until:

- migration exists;
- existing Product rows backfill safely;
- Product model updated;
- public Product price reads from Product storage;
- Variant price remains unchanged;
- factories/seeds updated;
- schema tests pass;
- CAT-001/CAT-002 regression tests pass;
- no API shape changed.

If this gate fails:

```text
Phase 11.3 BLOCKED
```

with the exact reason.

---

# PART B — PRODUCT CRUD IMPLEMENTATION

# 39. Scope

After Part A passes, implement:

```text
CAT-007 POST  /api/v1/products
CAT-008 PATCH /api/v1/products/{product}
CAT-013 GET   /api/v1/admin/products
CAT-014 GET   /api/v1/admin/products/{product}
```

---

# 40. No Product DELETE

Despite the roadmap label "Product CRUD", frozen V1 contains no:

```http
DELETE /api/v1/products/{product}
```

Do not add one.

---

# 41. Existing Soft Delete

Product soft-delete support remains persistence infrastructure only.

Do not expose it through a new API.

---

# 42. Product Visibility Controls

Admin hides Products using existing:

```text
is_active
is_published
```

semantics.

Do not invent:

```text
ARCHIVED
DRAFT
LIVE
```

Product states.

---

# 43. Actor Model

Allowed:

```text
STAFF
ADMIN
```

with permissions.

Denied:

```text
Anonymous
CUSTOMER
```

---

# 44. Authorization

Mutation:

```text
products.manage
```

Operational read:

```text
products.view
or products.manage
```

according to current PermissionCatalog/frozen CAT-013/014 rules.

Verify exact runtime implementation.

---

# 45. Authorization Matrix

Required:

```text
Anonymous
→ 401

CUSTOMER
→ 403

STAFF without products permission
→ 403

STAFF products.view
→ CAT-013/014 allowed
→ CAT-007/008 denied

STAFF products.manage
→ CAT-007/008/013/014 allowed as contracted

ADMIN
→ explicit permission still required
```

---

# 46. No Role-Only Bypass

Do not authorize solely via:

```text
role == ADMIN
```

Permissions remain explicit.

---

# 47. Operational vs Public Product

Preserve:

```text
Public Product
≠
Operational Product
```

Public:

```text
CAT-001
CAT-002
```

Operational:

```text
CAT-013
CAT-014
```

---

# 48. CAT-013 Operational List

Implement:

```http
GET /api/v1/admin/products
```

with:

```text
private/no-store
pagination
operational Product representation
```

---

# 49. CAT-013 Pagination

Reuse:

```text
page
per_page
meta.pagination
```

with global limits.

---

# 50. CAT-013 Filters

Do not invent filters.

If CAT-013 currently freezes only:

```text
page
per_page
```

implement only those.

Do not automatically inherit CAT-001 filters.

---

# 51. CAT-013 Sorting

Use existing frozen/default deterministic ordering.

Do not add arbitrary Admin sort parameters unless defined.

---

# 52. Operational Visibility

Operational list/detail must expose Products unavailable to public storefront where approved, such as:

```text
unpublished
inactive
```

Do not reuse public scope blindly.

---

# 53. Soft-Deleted Product Visibility

Inspect contract.

If archival Products are not explicitly part of CAT-013/014:

do not automatically use:

```text
withTrashed()
```

Record any ambiguity rather than inventing behavior.

---

# 54. CAT-014 Operational Detail

Implement:

```http
GET /api/v1/admin/products/{product}
```

using canonical Product identifier resolution.

---

# 55. Product Identifier

Preserve:

```text
prod_...
or slug
```

where frozen.

Never expose/use numeric DB ID externally.

---

# 56. Operational Product Resource

Use explicit serializer/resource.

Include only frozen fields, conceptually:

```text
id
name
slug
description
product_type
price
category
is_active
is_published
images
variants
approved inventory summary
created_at
updated_at
```

Use actual `OperationalProduct` OpenAPI schema as authority.

---

# 57. Sensitive Data

Do not leak:

```text
cost price
supplier internals
raw warehouse internals
database IDs
staff secrets
```

unless explicitly frozen.

---

# 58. CAT-007 Create Product

Implement:

```http
POST /api/v1/products
```

Expected:

```text
201
```

---

# 59. Create Allow-List

Exactly:

```text
name
slug
description
product_type
price
category_id
is_active
is_published
```

---

# 60. Required Create Fields

```text
name
slug
product_type
price
category_id
```

---

# 61. Optional Fields

```text
description
is_active
is_published
```

Use frozen defaults only.

---

# 62. No Variant Object

Reject:

```text
initial_variant
variants
sku
variant_name
variant_price
```

in ProductCreateRequest.

---

# 63. Unknown Fields

Reject with canonical 422.

Do not silently ignore.

---

# 64. Server-Controlled Fields

Reject:

```text
id
availability
stock_indicator
reserved_quantity
available_quantity
created_at
updated_at
```

---

# 65. `name`

Validate:

```text
strict string
trimmed
non-empty
max 200
```

---

# 66. `slug`

Validate:

```text
lowercase
kebab-case
^[a-z0-9]+(?:-[a-z0-9]+)*$
globally unique
```

---

# 67. Slug Collision

Translate DB uniqueness conflicts into canonical API errors.

Never leak SQL exceptions.

---

# 68. `product_type`

CLOSED enum:

```text
IN_STOCK
MADE_TO_ORDER
```

Strict case.

---

# 69. Price Input

Now map directly to Product base price persistence.

Example:

```json
{
  "price": {
    "amount": 125000000,
    "currency": "TZS"
  }
}
```

No Variant creation is needed.

---

# 70. Price Validation

```text
amount integer
amount >= 0
currency exactly TZS
```

---

# 71. Category Resolution

Resolve canonical:

```text
category id or slug
```

according to frozen contract.

No numeric DB IDs.

---

# 72. Category Must Exist

Unknown Category → canonical not-found/validation error.

---

# 73. Category Must Be Active

Product creation/update may target only an active Category unless frozen operational rules explicitly say otherwise.

---

# 74. Category Persistence

Persist internal FK only.

Do not duplicate Category snapshots.

---

# 75. `is_active`

Strict boolean.

---

# 76. `is_published`

Strict boolean.

---

# 77. Create Transaction

Persist Product fields and Product base price atomically.

No related Variant creation.

---

# 78. CAT-007 Response

Return operational Product representation.

---

# 79. CAT-008 Update Product

Implement:

```http
PATCH /api/v1/products/{product}
```

Expected:

```text
200
```

---

# 80. Frozen PATCH Allow-List

Current machine-readable contract indicates optional:

```text
name
slug
description
product_type
price
category_id
is_active
is_published
```

Verify before coding.

---

# 81. Documentation Discrepancy

Older `api-resources.md` prose may list a narrower update set.

Reconcile stale prose against:

```text
OpenAPI
latest security conventions
post-freeze decisions
```

before implementation.

Do not silently choose.

---

# 82. Update Price

CAT-008:

```text
price
```

updates only Product base/display price.

It must not modify ProductVariant prices.

---

# 83. Variant Price Independence Test

Example:

```text
Product.price = 100
Variant A = 100
Variant B = 150

PATCH Product.price = 120
```

Expected:

```text
Product.price = 120
Variant A = 100
Variant B = 150
```

unless an explicit future operation changes variants.

---

# 84. Multiple Field Atomicity

If PATCH contains:

```text
slug
category_id
price
product_type
```

validate everything first.

One transaction.

No partial update.

---

# 85. Same-Value PATCH

Prefer no unnecessary writes where repository conventions support it.

---

# 86. Product Type Change

Support only if current frozen ProductUpdateRequest includes `product_type`.

---

# 87. IN_STOCK → MADE_TO_ORDER

Derived consequences:

```text
new Cart admission prohibited
new Request linkage permitted if public
stock_indicator = MADE_TO_ORDER
```

Do not mutate existing Cart/Request history.

---

# 88. MADE_TO_ORDER → IN_STOCK

Do not create:

```text
Variant
Inventory
Stock
```

automatically.

If no stock exists:

```text
availability = unavailable
```

according to existing Group E semantics.

---

# 89. Public Visibility

`is_active=false` or `is_published=false` must remove Product from public catalog.

Operational reads remain available as contracted.

---

# 90. Product Price Public Projection

After CAT-007:

```text
CAT-007 input price
=
CAT-013/014 operational Product.price
=
CAT-001/002 public Product.price
```

---

# 91. Product Price Update Projection

After CAT-008 price mutation:

```text
CAT-013/014
CAT-001/002
```

must both show the updated Product base price.

---

# 92. Variant Projection Regression

CAT-005/006 must continue returning each Variant's own price.

---

# 93. No Variant Auto-Synchronization

Do not run:

```text
UPDATE product_variants SET price = product.price
```

during Product mutation.

---

# 94. Images Out of Scope

Phase 11.5 owns:

```text
CAT-009
```

Reject Product create/update fields:

```text
images
image
image_url
primary_image
media
```

---

# 95. Variants Out of Product Payload

Reject nested variants in CAT-007/CAT-008.

---

# 96. CAT-010 Ownership

CAT-010 remains a separate frozen endpoint.

Record its Group K owner explicitly.

Because Phase 11.3 now establishes Product CRUD and Variant price separation, if `group-K-phases.md` does not assign CAT-010 elsewhere, classify CAT-010 as:

```text
Product/Variant management follow-up
```

but do not silently implement it unless Phase 11.3's documented scope explicitly includes it.

---

# 97. Inventory Out of Scope

Phase 11.6 owns inventory management.

Reject:

```text
quantity
reserved_quantity
available_quantity
warehouse_location
stock
```

---

# 98. No Inventory Creation

Creating Product does not create ProductStock.

---

# 99. No Inventory Mutation

Updating Product does not adjust stock.

---

# 100. No Cart Mutation

Product mutation never directly updates Cart.

---

# 101. No Request Mutation

Product mutation never rewrites FurnitureRequest.

---

# 102. No Enquiry Mutation

Product mutation never rewrites Enquiry.

---

# 103. No Order Mutation

Product mutation never rewrites historical OrderItem snapshots.

---

# 104. MADE_TO_ORDER Current Production Regression

A Product created:

```text
product_type = MADE_TO_ORDER
is_active = true
is_published = true
```

must be:

```text
publicly discoverable
requestable
non-purchasable through Cart
price displayed as informational/starting-at
```

---

# 105. IN_STOCK Without Variant/Stock

If valid persistence allows it:

```text
public active/published Product
price visible
availability unavailable
```

until variant/inventory configuration makes it purchasable.

Do not fake availability.

---

# 106. Operational Cache

CAT-013/014:

```text
Cache-Control: private, no-store
```

---

# 107. Public Cache

CAT-001/002 remain public-safe.

Product mutations should invoke any existing application cache invalidation infrastructure.

Do not introduce production CDN integrations here.

---

# 108. Audit

Privileged Product mutations are conceptually auditable.

Inspect current AuditAction closed enum.

If an exact Product mutation action already exists:

use it as required.

If not:

do not add new AuditAction values silently.

Document the frozen audit vocabulary gap for later reconciliation.

---

# 109. ADM-007 Gap

Do not touch:

```text
audit.view
ADM-007
```

That remains Phase 11.13.

---

# 110. Product Create Service

Prefer a focused service/action such as:

```text
CreateProduct
```

Responsibilities:

```text
validated input
canonical Category resolution
Product base price mapping
persistence
response model
```

No image/inventory/variant management.

---

# 111. Product Update Service

Prefer:

```text
UpdateProduct
```

Responsibilities:

```text
operational Product resolution
validated partial input
Category resolution
base price update
Product state update
atomic persistence
```

---

# 112. Controller

Thin:

```text
authenticate
authorize
FormRequest
DTO
service
resource
```

---

# 113. Input DTOs

Use explicit:

```text
CreateProductInput
UpdateProductInput
```

or repository equivalent.

---

# 114. Never Use `$request->all()`

Use only:

```text
validated()
```

and explicit allow-list.

---

# 115. Operational Product Query

Use dedicated operational query logic.

Do not use public Product scope.

---

# 116. Eager Loading

Avoid N+1 for:

```text
category
images
variants
inventory summaries
```

where operational resource includes them.

---

# 117. Product DELETE Regression

Assert:

```http
DELETE /api/v1/products/{product}
```

does not exist.

---

# 118. Create Tests

Cover:

```text
valid MADE_TO_ORDER
valid IN_STOCK
required fields
Product price persistence
price strictness
category exists/active
slug unique
closed enum
strict booleans
unknown fields
server-controlled fields
nested variants rejected
image fields rejected
inventory fields rejected
```

---

# 119. Update Tests

Cover:

```text
partial field changes
Product price update
Variant prices unchanged
slug collision
category validation
product_type change
visibility flags
unknown fields
atomic multi-field failure
```

---

# 120. Operational Read Tests

Cover:

```text
pagination
private cache
draft visibility
inactive visibility
explicit operational resource
no sensitive leaks
opaque identifiers
```

---

# 121. Authorization Tests

Cover:

```text
Anonymous
CUSTOMER
STAFF without permission
STAFF products.view
STAFF products.manage
ADMIN with permission
ADMIN missing permission
```

---

# 122. Price Reconciliation Tests

Mandatory regression:

```text
Product base price stored on Product
Variant price remains on Variant
Product public price reads Product price
Variant public price reads Variant price
Product update doesn't modify Variants
Variant update doesn't implicitly modify Product
```

---

# 123. Existing Product Migration Tests

For migrated seed/existing Products:

assert Product base price equals the previously canonical Product public price.

---

# 124. Factory Tests

ProductFactory must produce a valid Product without requiring Variant creation.

---

# 125. MADE_TO_ORDER Tests

Verify:

```text
Product.price persisted
requestable
cart rejection
no inventory dependency
```

---

# 126. Cross-Domain Tests

Run relevant:

```text
Catalog
Cart
FurnitureRequest
Enquiry
Inventory
Variant
```

regressions.

---

# 127. MariaDB Migration Verification

Because the schema changes, verify the migration on the local MariaDB development environment.

Use a disposable/test database only.

Run at minimum:

```text
migrate:fresh
migration rollback/reset where repository practice requires
schema constraints
backfill verification
```

Do not touch production.

---

# 128. SQLite Verification

Canonical test suite remains required.

---

# 129. Migration Portability

Remember current environment:

```text
development: MariaDB 11.8.8
future production: MySQL-compatible environment
```

Use portable migration constructs where practical.

Avoid MySQL-only SQL if Laravel schema builder can express the rule.

---

# 130. No Historical Migration Modification

Add new migration only.

---

# 131. OpenAPI

Expected:

```text
unchanged
```

because HTTP contract remains frozen.

---

# 132. API Documentation

Only explanatory persistence reconciliation may change.

Do not change request/response schema.

---

# 133. Schema Changes

Expected and explicitly approved for this reconciliation:

```text
Product base price amount
Product base price currency
```

Nothing more unless required for safe constraints.

---

# 134. Dependencies

Expected:

```text
NONE
```

---

# 135. Frontend

Expected:

```text
NONE
```

---

# 136. Category CRUD

Do not implement CAT-011/012.

Phase 11.4 owns Category CRUD.

---

# 137. Image Management

Do not implement CAT-009.

Phase 11.5 owns Product images.

---

# 138. Inventory Management

Do not implement INV-* mutation work.

Phase 11.6 owns Inventory management.

---

# 139. Deferred Commerce

Do not activate:

```text
Group H
Group I
11.7
11.11
11.12
```

---

# 140. Documentation

Update:

```text
phases/group-K-phases.md
docs/decisions.md
```

and any Product schema/API architecture documentation whose statement:

```text
Products carry no price
```

has become stale.

Do not leave contradictory documentation.

---

# 141. Historical Decision Reconciliation

Do not delete the old Variant-pricing ADR.

Instead add a new accepted ADR that explicitly supersedes only the statement:

```text
Product has no persisted price
```

while retaining:

```text
Variant has variant-specific price
```

This preserves decision history.

---

# 142. Completion Report — Phase Status

Return:

## Phase 11.3 Status

```text
PASS
```

or:

```text
BLOCKED
```

---

# 143. Completion Report — Reconciliation

Report:

```text
Previous conflict:
Frozen API:
Previous persistence:
Chosen reconciliation:
Migration:
Backfill rule:
Backfill count:
Null Product prices after migration:
OpenAPI changed:
```

Expected:

```text
OpenAPI changed: NO
```

---

# 144. Completion Report — Product Price Authority

Report:

```text
Product base price storage:
Variant price storage:
CAT-001 Product price source:
CAT-002 Product price source:
CAT-013 Product price source:
CAT-014 Product price source:
CAT-005/006 Variant price source:
```

---

# 145. Completion Report — Existing Data

Report:

```text
Products migrated:
Backfill source used:
Ambiguous Products:
Migration result:
```

---

# 146. Completion Report — Endpoints

Report:

```text
CAT-007 ACTIVE/STUB
CAT-008 ACTIVE/STUB
CAT-013 ACTIVE/STUB
CAT-014 ACTIVE/STUB
```

---

# 147. Completion Report — Delete

Report exactly:

```text
Product DELETE endpoint: NONE
```

---

# 148. Completion Report — Variants

Report:

```text
Synthetic default Variant: NO
initial_variant field: NO
CAT-010 changed: NO
Variant prices rewritten by Product CRUD: NO
```

---

# 149. Completion Report — Images

```text
CAT-009 untouched
Phase 11.5
```

---

# 150. Completion Report — Inventory

```text
Inventory mutation untouched
Phase 11.6
```

---

# 151. Completion Report — Authorization

Report exact runtime:

```text
products.view
products.manage
```

matrix.

---

# 152. Completion Report — Schema

Report the exact new Product price columns and constraints.

---

# 153. Completion Report — API Compatibility

Expected:

```text
CAT-007 request shape: unchanged
CAT-008 request shape: unchanged
Product response shape: unchanged
Variant response shape: unchanged
OpenAPI: unchanged
```

---

# 154. Completion Report — Side Effects

Confirm no direct mutation to:

```text
Cart
Order
Payment
Inventory quantity
FurnitureRequest
Enquiry
```

---

# 155. Completion Report — Tests

Report focused suites:

```text
Product price migration
Product CRUD
Operational Product reads
Authorization
Public catalog projection
Variant price independence
Cart regression
Request regression
Enquiry regression
Migration rebuild
```

---

# 156. Completion Report — Quality

Report:

```text
PHPUnit
MariaDB migration verification
OpenAPI
PHPStan
Pint
Composer audit
git diff --check
route:list
```

---

# 157. Definition of Done

Phase 11.3 is complete only when:

- frozen Product.price API semantics remain unchanged;
- Product base/display price is persisted directly on Product;
- Variant price remains persisted on ProductVariant;
- no synthetic Variant is created;
- no `initial_variant` field is introduced;
- CAT-010 remains separate;
- existing Products are deterministically backfilled;
- no Product has missing base price after migration;
- Product public projection reads Product base price;
- Variant projection reads Variant-specific price;
- Product price update does not modify Variants;
- Variant price remains independent;
- MADE_TO_ORDER Product price remains informational;
- no Product DELETE endpoint exists;
- CAT-007 is implemented;
- CAT-008 is implemented;
- CAT-013 is implemented;
- CAT-014 is implemented;
- Product create/update allow-lists remain frozen;
- unknown fields are rejected;
- Category validation is preserved;
- Product type enum remains CLOSED;
- operational/public representation separation is preserved;
- images remain Phase 11.5;
- inventory remains Phase 11.6;
- deferred commerce remains deferred;
- migration works on SQLite test environment and local MariaDB;
- OpenAPI remains unchanged;
- full regression remains green.

---

# 158. STOP Condition

STOP when the repository can prove:

```text
Frozen HTTP contract
        ↓
ProductCreateRequest.price
        ↓
Product base price persistence
        ↓
CAT-001/002/013/014 Product.price
```

while separately proving:

```text
CAT-010
        ↓
ProductVariant
        ↓
Variant-specific price
        ↓
CAT-005/006 Variant.price
```

with:

```text
no synthetic Variant
no initial_variant
no duplicate semantic price source
no Product DELETE API
```

and report:

```text
Phase 11.3 PASS

Phase 11.4 — Category CRUD READY
```

Do not begin Phase 11.4 automatically.

DO NOT COMMIT, STAGE OR PUSH.

The project owner handles all Git operations.

---

## Phase 11.3 Outcome — 2026-10-04

**Status:** PASS

- Reconciled frozen Product price semantics with persistence through `products.price_amount` and `products.price_currency`. The columns are non-null, non-negative integer minor units, and constrained to `TZS`.
- Previous conflict: CAT-001/CAT-002 derived Product price from the lowest active Variant while CAT-007/CAT-008 required Product-level price input. Product now owns the base/display price; Variant price remains SKU-specific.
- Migration preflights every existing Product's canonical active Variant price (`price_amount ASC`, `id ASC`), requiring a non-negative amount and `TZS` currency before adding columns. It aborts for an unmappable or invalid Product. Development and disposable MariaDB preflights had zero unmappable Products; the SQLite migration regression verifies the backfill path.
- Migration rollback is lossless-only: it is blocked before any schema change while Products exist, because Product base price may be independent of Variant prices. Empty catalog rollback remains supported.
- CAT-007, CAT-008, CAT-013, and CAT-014 are active. Product write responses and operational reads use an explicit operational resource with private/no-store caching. CAT-013 supports frozen `page`/`per_page` pagination and deterministic `created_at DESC, id ASC` ordering.
- Authorization: `products.manage` is required for CAT-007/008. CAT-013/014 allow `products.view` or `products.manage` for active Staff/Admin accounts. No role-only bypass exists.
- CAT-001/002/013/014 read Product base price. CAT-005/006 and embedded Variant summaries still read Variant price. Product and Variant price updates do not synchronize.
- Product DELETE endpoint: NONE. Synthetic default Variant: NO. `initial_variant` field: NO. CAT-010 changed: NO. CAT-009 and inventory mutation remain deferred to Phases 11.5 and 11.6.
- No direct mutation was added for Cart, Order, Payment, inventory quantity, FurnitureRequest, or Enquiry. OpenAPI request/response schemas remain unchanged.
- Verification: SQLite feature suite `1584 passed, 1 skipped`; PHPStan clean; Pint clean; Composer audit clean; `git diff --check` clean; disposable MariaDB `migrate:fresh --seed --force` completed successfully against `furnitureapp_test_disposable` only.
