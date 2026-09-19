# Phase 5.2 — Product Read API

## Purpose

Implement the public Product Read API using the catalog/domain model already established in Group C.

This phase covers the read behavior for:

```text
CAT-001
GET /api/v1/products
```

and:

```text
CAT-002
GET /api/v1/products/{product}
```

The implementation must **not redesign the Product domain**.

The API must derive its public representation from the existing Group C relationships:

```text
Product
    ↓
Category

Product
    ↓
ProductVariant
        ↓
pricing
        ↓
variant attributes
        ↓
dimensions

Product
    ↓
ProductImage

ProductVariant
    ↓
ProductStock

Product
    ↓
materials / room tags / style tags / assets
```

where those relations exist in the current repository.

Do not duplicate variant, inventory, media, or classification data onto `products`.

---

# 1. Critical Constraint — Preserve Group C

The Group C catalog schema is authoritative.

Do not redesign:

```text
products
product_variants
product_images
product_stocks
product_materials
product_room_tags
product_style_tags
product_assets
categories
```

Do not replace them with:

```text
single giant products table
JSON inventory
JSON image arrays
price column copied to products
SKU copied to products
material strings copied to products
```

Phase 5.2 is a **read/API implementation phase**, not a schema redesign.

---

# 2. Read Current Authoritative Files First

Before modifying code, read the latest repository versions of:

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

Then inspect the actual implementations of:

```text
app/Models/Product.php
app/Models/ProductVariant.php
app/Models/ProductImage.php
app/Models/ProductStock.php
app/Models/Category.php
```

and all associated:

```text
migrations
factories
seeders
tests
```

Do not infer schema fields from old planning text.

Use the actual Group C implementation.

---

# 3. Known Group C Variance

The current repository explicitly records:

```text
products.product_type
products.is_published
```

as absent from the Group C schema.

They are deliberately deferred to:

```text
Phase 5.7
```

Do **not** add them during Phase 5.2.

Do **not** edit Group C migrations.

Do **not** fabricate them using defaults.

---

# 4. Consequence of the Phase 5.7 Deferral

The frozen public contract ultimately requires:

```text
product_type
is_published visibility
```

but Phase 5.2 must preserve roadmap sequencing.

Therefore:

```text
Phase 5.2
→ implement Product Read infrastructure and every contract behavior supported by the existing Group C model.

Phase 5.7
→ add product_type / is_published and complete the publication/product-type-dependent public visibility semantics.
```

Do not mark unsupported behavior as implemented.

Document this dependency clearly.

---

# 5. Do Not Return Fake Product Type

Until Phase 5.7 introduces the authoritative field, do not return:

```json
{
  "product_type": "IN_STOCK"
}
```

for every product merely to satisfy a response schema.

That would create false business state.

Likewise do not infer:

```text
MADE_TO_ORDER
```

from inventory quantity or category.

Product type is a first-class business attribute, not an inference.

---

# 6. Do Not Fake Publication State

Do not treat:

```text
is_active
```

as identical to:

```text
is_published
```

They represent different concerns in the frozen contract.

Until Phase 5.7 completes publication state:

do not claim the full `is_active && is_published` visibility rule is implemented.

---

# 7. Public API

Both endpoints are public:

```text
GET /api/v1/products
GET /api/v1/products/{product}
```

They require:

```text
NO authentication
NO Clerk token
NO role
```

Do not attach authentication middleware.

---

# 8. Public Read Only

Phase 5.2 does not implement:

```text
POST /products
PATCH /products/{product}
DELETE /products/{product}
```

Those belong to administrative catalog phases.

---

# 9. CAT-001 — Product Collection

Implement:

```http
GET /api/v1/products
```

as the canonical public product collection endpoint.

This endpoint eventually owns:

```text
search
category filtering
product-type filtering
availability filtering
price filtering
sorting
pagination
```

according to the frozen contract.

However, only implement a filter in Phase 5.2 if its authoritative source already exists.

---

# 10. Do Not Create Duplicate Product Endpoints

Do not add:

```text
GET /products/by-category/*
GET /categories/{category}/products
GET /available-products
GET /search/products
GET /featured-products
```

The canonical collection remains:

```text
GET /api/v1/products
```

with query parameters.

---

# 11. CAT-002 — Product Detail

Implement:

```http
GET /api/v1/products/{product}
```

using:

```text
slug
or
stable public ID
```

according to the frozen dual-resolution contract.

---

# 12. Slug

Product slug is the SEO/public routing identifier.

Use exact indexed lookup.

Do not:

```text
LIKE '%slug%'
LOWER arbitrary column
fuzzy match
```

for resource identification.

---

# 13. Machine ID

The detail endpoint must also resolve the existing stable Product API ID.

Do not create another public identifier.

---

# 14. Product Summary Contract

The final CAT-001 public Product Summary is:

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

But Phase 5.2 must only serialize fields whose authoritative source exists.

Do not invent missing `product_type`.

---

# 15. Product Detail Contract

The final CAT-002 representation extends summary with:

```text
description
images[]
variants[]
created_at
updated_at
```

Implement these through existing Group C relations.

---

# 16. Explicit Resources

Use explicit Laravel Resources.

Likely:

```text
ProductSummaryResource
ProductDetailResource
VariantSummaryResource
ProductImageResource
CategorySummaryResource
```

or the equivalent existing convention.

Do not return Eloquent models directly.

---

# 17. Never Use Mass Serialization

Do not use:

```php
return $product->toArray();
```

or unrestricted:

```php
return response()->json($product);
```

Public catalog serialization must be allow-listed.

---

# 18. Product Price Is Derived

Group C deliberately does not store Product price on:

```text
products
```

Pricing belongs to:

```text
product_variants
```

Do not add:

```text
products.price
```

during Phase 5.2.

---

# 19. Public Product Price

Implement the existing contract's public Product price using the approved variant-derived rule.

Before coding:

inspect the latest docs for the exact rule.

Possible existing rules may involve:

```text
default variant
lowest active variant price
starting-at price
```

Use only the documented rule.

Do not invent one.

---

# 20. If Price Semantics Are Ambiguous

If the latest documentation does not state how multiple variant prices become one Product Summary price:

do not silently choose.

Resolve the ambiguity minimally in:

```text
docs/api/api-contract.md
docs/decisions.md
```

before implementation.

Recommended principle:

```text
one deterministic server-derived public price
```

but use the approved project rule.

---

# 21. Money

All price amounts remain integer minor units.

API shape:

```json
{
  "amount": 125000000,
  "currency": "TZS"
}
```

Do not use:

```text
float
decimal money
formatted "TZS 1,250,000"
```

as API authority.

---

# 22. Cost Price Must Never Leak

`cost_price_amount` and related internal variant cost fields are:

```text
INTERNAL / OPERATIONAL
```

Never expose them in CAT-001 or CAT-002.

---

# 23. Compare-at Price

Do not expose `compare_at_price` unless the current public API contract explicitly includes it.

Existing database capability does not automatically make a field public.

---

# 24. Variant Model

Preserve:

```text
Product
1 → many ProductVariants
```

Variant remains the canonical:

```text
SKU
price
dimensions
weight
variant attributes
```

boundary.

Do not move these values to Product.

---

# 25. Active Variants

CAT-002 embedded variants must include only publicly eligible active variants according to current rules.

Do not expose inactive variants publicly.

---

# 26. Variant Ordering

Use existing:

```text
display_order ASC
```

and deterministic:

```text
id ASC
```

tie-breaking where needed.

Do not return variants in arbitrary database order.

---

# 27. Embedded Variant Summary

Use the frozen embedded structure:

```text
id
sku
name
price
availability
stock_indicator
```

Do not include:

```text
product_id
created_at
updated_at
cost_price
raw stock quantities
```

inside embedded CAT-002 variants.

---

# 28. Variant Name

Map the existing Group C:

```text
variant_name
```

to the contracted API:

```text
name
```

if that mapping is already defined.

Do not rename the database column simply to match API representation.

---

# 29. Variant Attributes

Do not automatically dump:

```text
attributes JSON
```

into public product payloads unless the frozen contract exposes it.

The presence of flexible JSON in Group C does not make it public by default.

---

# 30. Dimensions

Do not automatically expose:

```text
width_cm
height_cm
depth_cm
weight_kg
```

unless current CAT-001/CAT-002 contract requires them.

They remain part of the Group C model for later product specification use.

Avoid expanding the public API casually.

---

# 31. Product Images

Preserve Group C:

```text
product_images
```

as the authoritative media source.

Do not add:

```text
image_url
images JSON
```

columns to Product.

---

# 32. Internal Image Path

Group C stores:

```text
file_path
```

as an internal storage reference.

Never expose:

```text
file_path
```

to public clients.

---

# 33. Public Image URL

Derive:

```text
url
```

through the existing storage/media configuration.

The API should remain independent of whether production uses:

```text
local storage
S3-compatible object storage
CDN
```

Do not hard-code hostnames.

---

# 34. Product Image Structure

Use the frozen image representation:

```text
id
url
alt_text
sort_order
is_primary
```

for CAT-002.

Do not expose internal storage metadata.

---

# 35. Image Ordering

Gallery order:

```text
sort_order ASC
id ASC
```

or the exact existing deterministic Group C relationship order.

Do not sort client-side.

---

# 36. Primary Image

CAT-001 returns:

```text
primary_image
```

derived from the existing Product Image model.

Use:

```text
is_primary
```

according to the Group C invariant.

Do not store a second:

```text
products.primary_image_url
```

field.

---

# 37. Missing Primary Image

Follow the frozen nullability contract.

Do not invent placeholder asset URLs in Laravel.

If the contract allows:

```json
"primary_image": null
```

use it.

Frontend placeholders belong to later UI phases.

---

# 38. Category Representation

Product summary embeds a small Category summary:

```text
id
slug
name
```

according to the catalog conventions.

Do not embed the full Category tree.

---

# 39. Category Must Be Publicly Valid

If Product belongs to a Category that is not publicly eligible:

apply the current catalog visibility rule.

Do not expose an internal/inactive category through a public Product response.

---

# 40. No Recursive Graph

Do not serialize:

```text
product
→ category
→ products
→ category
```

Use bounded summaries.

---

# 41. Product Materials

Preserve existing normalized material relationships.

Do not convert them into:

```text
products.material
```

columns.

Do not expose them in CAT-001/CAT-002 unless currently contracted.

---

# 42. Room Tags

Preserve:

```text
product_room_tags
```

or the current equivalent.

Do not treat room tags as Category.

Do not duplicate them into Product JSON unless contract requires them.

---

# 43. Style Tags

Preserve:

```text
product_style_tags
```

as distinct classification metadata.

Do not redesign them as categories.

---

# 44. Product Assets

Preserve:

```text
product_assets
```

for 3D/AR or related assets.

Do not expose these publicly in Phase 5.2 unless the frozen Product Detail contract already requires them.

---

# 45. Inventory

Group C inventory remains:

```text
ProductVariant
→ ProductStock
```

with stock authority in stock records.

Do not add stock columns to Product or ProductVariant.

---

# 46. Public Inventory Security

Never expose:

```text
quantity
physical_quantity
reserved_quantity
available_quantity
warehouse_location
internal stock notes
```

through CAT-001/CAT-002.

Public catalog only exposes coarse availability signals.

---

# 47. Availability

The public contract ultimately exposes:

```text
availability:
available | unavailable
```

and:

```text
stock_indicator:
IN_STOCK | LOW_STOCK | MADE_TO_ORDER
```

Do not expose raw inventory numbers.

---

# 48. Availability Must Be Derived

Derive public availability from authoritative:

```text
variant active state
inventory
product state
product type
```

according to the frozen business rules.

Do not persist:

```text
products.availability
products.stock_indicator
```

as duplicate state.

---

# 49. Phase 5.7 Dependency

Because:

```text
product_type
is_published
```

are not yet in the schema, Phase 5.2 must not fake final public availability semantics that depend on them.

Implement the reusable availability/query structure where possible.

Complete final semantics in Phase 5.7.

---

# 50. Product Visibility During Phase 5.2

Until Phase 5.7:

use only the visibility rules that have an authoritative source.

At minimum:

```text
soft-deleted products
→ never public

is_active = false
→ never public
```

if those fields exist in the current schema.

Do not pretend publication state exists.

---

# 51. Soft Deletes

Products are soft-deleted.

Public queries must exclude:

```text
deleted_at != null
```

through normal Eloquent behavior.

Do not use:

```text
withTrashed()
```

for public catalog reads.

---

# 52. Inactive Product Detail

An inactive Product requested by slug or ID should be masked:

```text
404 RESOURCE_NOT_FOUND
```

according to current public-read semantics.

Do not disclose:

```text
PRODUCT_INACTIVE
```

to anonymous callers.

---

# 53. Future Draft Product Masking

After Phase 5.7:

```text
is_published = false
```

must use the same public 404 masking.

Phase 5.2 should structure the query so this condition can be added cleanly.

---

# 54. Search Pipeline

CAT-001's frozen pipeline is:

```text
search
→ filter
→ sort
→ tie-breaker
→ paginate
```

Do not change the execution semantics.

---

# 55. Search

Public search eventually covers:

```text
product name
description
SKU
variant attributes
```

according to the frozen contract.

Implement only using safe Eloquent/query-builder conditions.

---

# 56. Search Input

Rules:

```text
optional
trimmed
max 100 characters
case-insensitive
empty → ignored
```

according to current API docs.

Do not pass raw search strings into SQL fragments.

---

# 57. Avoid Expensive Search Architecture

Do not introduce:

```text
Elasticsearch
Algolia
Meilisearch
OpenSearch
```

for V1 Phase 5.2.

Use MySQL/Eloquent capabilities appropriate for the small business scope.

Search infrastructure can evolve later if actual scale requires it.

---

# 58. SKU Search

SKU lives on:

```text
product_variants
```

Search via the existing relationship.

Do not duplicate SKU to Product.

Use:

```text
whereHas variants
```

or the most efficient equivalent.

---

# 59. Variant Attribute Search

If the frozen contract requires searching variant attributes:

implement only within the capabilities of the current JSON schema/database.

Do not build an EAV search subsystem.

Keep query bounded.

---

# 60. Category Filter

CAT-001 canonical category filter:

```text
?category={slug|id}
```

Reuse Phase 5.1 Category resolution semantics.

Do not add nested product routes.

---

# 61. Category Filter and Hierarchy

Use the currently frozen semantics for whether filtering a parent category includes descendants.

If the docs do not yet define that behavior:

do not silently invent recursive filtering.

Document the gap for the appropriate catalog-filter phase.

---

# 62. Product Type Filter

The frozen query accepts:

```text
?product_type=IN_STOCK
?product_type=MADE_TO_ORDER
```

This filter cannot be truthfully completed until Phase 5.7 adds the authoritative field.

Do not emulate it using stock.

For Phase 5.2 either:

```text
leave implementation explicitly deferred
```

or use the project-approved staged mechanism if newer docs have superseded Phase 5.7.

---

# 63. Availability Filter

Frozen:

```text
?availability=available
?availability=unavailable
```

Implement only to the extent current authoritative fields support the final semantics.

Do not derive `MADE_TO_ORDER` from zero inventory.

---

# 64. Stock Indicator Is Not a Filter

Do not support:

```text
?stock_indicator=LOW_STOCK
```

The V1 contract explicitly does not expose it as a query filter.

---

# 65. Price Filter

Supported query parameters:

```text
min_price
max_price
```

represent integer minor units.

The filter must operate on the same derived Product price semantics used in the response.

Do not filter against a nonexistent Product price column.

---

# 66. Minimum Price Validation

`min_price`:

```text
integer / numeric integer string
>= 0
```

according to the frozen input convention.

No floats.

---

# 67. Maximum Price Validation

`max_price`:

```text
integer / numeric integer string
>= 0
```

and:

```text
max_price >= min_price
```

when both supplied.

---

# 68. Invalid Price Range

If:

```text
min_price > max_price
```

return:

```text
422 INVALID_VALUE
```

using the existing error envelope.

---

# 69. Sorting

Allowed:

```text
created_at
price
name
```

only.

Do not pass arbitrary:

```text
?sort=<db-column>
```

into `orderBy`.

---

# 70. Sort Direction

Allowed:

```text
asc
desc
```

according to the contract.

Reject unknown values.

---

# 71. Deterministic Tie-Breaker

Every non-unique sort must append:

```text
id ASC
```

to keep pagination stable.

---

# 72. Default Sort

Use the current catalog default:

```text
created_at DESC
id ASC
```

unless latest authoritative docs changed it.

---

# 73. Pagination

CAT-001 uses:

```text
page
per_page
```

with standard V1 pagination.

Current convention:

```text
page >= 1
per_page 1..100
default 20
```

---

# 74. Pagination Envelope

Return:

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

Do not return Laravel paginator internals.

---

# 75. Beyond Last Page

Return:

```text
data = []
```

with valid pagination metadata.

Do not return 404.

---

# 76. Query Validation

Use an explicit FormRequest or existing query-validation abstraction.

Do not validate ad hoc inside the controller.

---

# 77. Unknown Query Parameters

Follow current API conventions.

If V1 requires rejecting unsupported parameters:

reject them consistently.

Do not silently support:

```text
pageSize
sortBy
minPrice
maxPrice
```

aliases.

---

# 78. Query Builder Responsibility

Create a focused product-read query abstraction only if useful.

For example:

```text
ProductCatalogQuery
```

may own:

```text
public visibility
search
filters
sorting
pagination
```

Do not build a generic query framework.

---

# 79. Avoid Giant Controller

Controller should remain thin.

Conceptually:

```text
validated query
→ ProductCatalogQuery
→ Resource
```

and:

```text
identifier
→ public Product resolution
→ ProductDetailResource
```

---

# 80. Avoid Repository Overengineering

Do not automatically add:

```text
ProductRepositoryInterface
EloquentProductRepository
ProductQueryBus
ProductSpecificationEngine
CatalogReadFacade
```

unless an existing architecture requires them.

Standard Laravel service/query classes are sufficient.

---

# 81. Query Efficiency

CAT-001 must avoid N+1 for:

```text
category
primary image
derived price
availability
```

Use appropriate:

```text
joins
subqueries
with()
withMin()
withExists()
aggregates
```

depending on actual schema and performance.

Do not load every variant/image/stock row for every summary Product if a bounded aggregate will do.

---

# 82. Detail Eager Loading

CAT-002 may eagerly load:

```text
category
images
active variants
required stock summaries
```

because detail representation needs them.

Do not load:

```text
orders
carts
payments
enquiries
```

for a public Product detail.

---

# 83. Do Not Calculate Availability in PHP Loops

Avoid:

```text
fetch all products
for each product:
    query variants
    query stock
```

Use relationship eager loading or database aggregates.

---

# 84. Public Product Summary Must Stay Lightweight

CAT-001 must not include:

```text
full description
all images
all variants
inventory rows
materials graph
assets
recommendations
```

unless frozen contract says otherwise.

---

# 85. Product Detail Can Be Richer

CAT-002 includes:

```text
description
images
variants
timestamps
```

but still not internal operational data.

---

# 86. No Separate Images Endpoint

Do not implement:

```text
GET /products/{product}/images
```

Images are embedded in CAT-002.

This endpoint is explicitly rejected in V1.

---

# 87. Variant Read Endpoints

Do not implement:

```text
CAT-005
CAT-006
```

unless they are explicitly assigned to Phase 5.2 by the current roadmap.

This instruction treats Phase 5.2 as Product collection/detail only.

Embedded variants in CAT-002 are still required where supported.

---

# 88. No Cart Logic

Do not implement:

```text
Add to Cart
is_purchasable mutation
cart ownership
```

inside Product Read.

The API can expose informational availability.

Cart validation belongs to its domain phase.

---

# 89. No Checkout Guarantees

Public availability is informational.

Never imply:

```text
availability = available
```

guarantees checkout success.

Checkout later revalidates inventory transactionally.

---

# 90. No Inventory Reservation

Reading a Product must never:

```text
reserve stock
lock rows
increase reserved_quantity
```

Catalog reads are side-effect free.

---

# 91. No View Counter

Do not mutate:

```text
view_count
popularity
last_viewed_at
```

during GET requests unless explicitly approved elsewhere.

Keep reads side-effect free.

---

# 92. Cacheability

CAT-001 and CAT-002 are:

```text
PUBLIC
CACHEABLE
```

according to the catalog contract.

Use current public cache headers.

---

# 93. No Premature Redis Cache

Do not add application-level response caching simply because the endpoints are cacheable.

HTTP/CDN caching can be added later.

Only use an existing caching mechanism if already established.

---

# 94. Rate Limiting

Use the moderate:

```text
public-read
```

limiter from Phase 4.11.

Do not create a restrictive product-specific limiter.

Normal storefront browsing must not trigger 429 easily.

---

# 95. Error — Unknown Product

Unknown Product slug/ID:

```text
404 RESOURCE_NOT_FOUND
```

or the exact current product-not-found code if frozen.

Follow current contract.

---

# 96. Error — Inactive Product

Existing inactive Product:

```text
404
```

using the same public masking semantics.

Do not reveal internal existence/state.

---

# 97. Error — Soft Deleted Product

Soft-deleted Product:

```text
404
```

for public reads.

---

# 98. Future Unpublished Product

After Phase 5.7:

```text
is_published = false
```

must also resolve publicly as:

```text
404
```

not as a special draft-state response.

---

# 99. Internal Fields Must Never Leak

Public CAT-001/CAT-002 must exclude:

```text
is_active
is_published
deleted_at
sku_prefix
cost_price
reserved_quantity
physical_quantity
available_quantity
warehouse_location
internal storage paths
internal notes
```

unless a field is explicitly part of the public contract.

---

# 100. Public Product ID

Use the existing API identifier convention.

Do not expose raw DB IDs if the API abstraction already uses prefixed opaque IDs such as:

```text
prod_...
```

---

# 101. Timestamps

CAT-002:

```text
created_at
updated_at
```

must use ISO 8601 UTC formatting.

CAT-001 should not include timestamps unless contracted.

---

# 102. OpenAPI

Update `docs/api/openapi.yaml` only to reflect actual implementation.

Do not falsely mark:

```text
product_type filtering
is_published visibility
```

as runtime-complete if Phase 5.7 has not yet implemented their schema authority.

If necessary, document Phase 5.7 dependency explicitly.

---

# 103. Docs Must Not Lie

Do not report:

```text
CAT-001 fully implemented
```

if required Product Type/publication behavior remains intentionally deferred.

Use accurate wording such as:

```text
CAT-001 Product Read core implemented;
product_type / publication-dependent completion remains Phase 5.7.
```

---

# 104. Test — Public Collection

Anonymous:

```text
GET /api/v1/products
```

must succeed.

No Clerk dependency.

---

# 105. Test — Collection Envelope

Assert:

```text
data
meta.pagination
```

and no raw paginator fields.

---

# 106. Test — Public Summary Fields

For fields currently authoritative, verify public serialization contains only approved summary fields.

After Phase 5.7, add/activate assertions for:

```text
product_type
```

according to the final contract.

---

# 107. Test — Internal Field Exclusion

Explicitly assert CAT-001 does not expose:

```text
is_active
is_published
cost price
inventory quantities
file_path
deleted_at
```

---

# 108. Test — Product Detail by Slug

Verify:

```text
GET /products/{slug}
```

returns correct Product.

---

# 109. Test — Product Detail by ID

Verify the same Product resolves through its machine ID.

---

# 110. Test — Unknown Product

Expected public not-found behavior.

---

# 111. Test — Inactive Product

Existing inactive Product must not be publicly retrievable.

---

# 112. Test — Soft Deleted Product

Archived Product must not appear in:

```text
CAT-001
CAT-002
```

---

# 113. Test — Category Summary

Product response embeds the expected Category summary.

No recursive category graph.

---

# 114. Test — Price Source

Create a Product with known variants.

Verify Product public price is derived according to the approved rule.

Do not set a Product-level price in the test.

---

# 115. Test — Price Is Integer Minor Units

Assert:

```text
amount
currency
```

and integer amount.

No floats.

---

# 116. Test — Cost Price Hidden

Give Variant an internal cost price.

Ensure it never appears publicly.

---

# 117. Test — Primary Image

Create:

```text
multiple ProductImages
one primary
```

Verify CAT-001 uses the correct public primary image.

---

# 118. Test — No Primary Image

Verify the approved nullable image representation.

Do not require fake placeholder media.

---

# 119. Test — Gallery Ordering

CAT-002 images must be deterministic by:

```text
sort_order
id
```

according to current model rules.

---

# 120. Test — Internal File Path Hidden

Ensure:

```text
file_path
```

is never serialized.

---

# 121. Test — Variant Ordering

Embedded variants in CAT-002 follow deterministic display order.

---

# 122. Test — Inactive Variant Hidden

Inactive variants must not appear in public embedded variant summaries.

---

# 123. Test — Variant Product Ownership

Only variants belonging to the Product appear.

No cross-product variant leakage.

---

# 124. Test — Raw Inventory Hidden

Seed inventory quantities.

Verify CAT-001/CAT-002 expose only the allowed derived availability information when applicable.

Never raw stock.

---

# 125. Test — Search

Cover:

```text
name
description
SKU
```

and variant attribute search only if implemented by current contract.

Use controlled fixtures.

---

# 126. Test — Search Safety

Use strings containing SQL wildcard/control characters.

Verify query remains safely parameterized.

Do not test SQL injection by executing destructive strings.

---

# 127. Test — Category Filter

Verify:

```text
?category=<slug>
```

and:

```text
?category=<id>
```

where both are currently contracted.

---

# 128. Test — Invalid Category Filter

Unknown category must follow the frozen collection-filter semantics.

Use current documented error behavior.

Do not invent silent empty-list behavior if contract says validation error.

---

# 129. Test — Price Range

Verify:

```text
min_price
max_price
```

against derived Product price.

---

# 130. Test — Invalid Price Range

```text
min_price > max_price
```

must return:

```text
422 INVALID_VALUE
```

---

# 131. Test — Sort Allow-List

Cover:

```text
created_at
price
name
```

---

# 132. Test — Sort Injection Rejection

Unknown sort:

```text
?sort=cost_price
```

must be rejected.

Do not expose internal columns through sorting.

---

# 133. Test — Deterministic Pagination

Seed several products with equal primary sort values.

Verify:

```text
id ASC
```

tie-breaking avoids duplicate/drifting pages.

---

# 134. Test — Pagination

Cover:

```text
default page
custom per_page
multiple pages
beyond-last page
```

---

# 135. Test — Public Cache Headers

Assert the current approved public/cacheable semantics.

Do not hard-code arbitrary TTL if not frozen.

---

# 136. Test — No Auth

Explicitly verify Product endpoints work without:

```text
Authorization
Clerk
local User
```

---

# 137. Test — No Side Effects

Product reads must not change:

```text
inventory
reserved stock
Product timestamps
Variant timestamps
```

---

# 138. Query Count

Add focused query-count coverage if existing project test conventions support it.

Prevent obvious N+1 regressions.

Do not introduce a performance framework solely for this.

---

# 139. Existing Group C Tests Must Stay Green

Do not regress:

```text
ProductSchemaTest
ProductVariantSchemaTest
ProductImageSchemaTest
ProductStockSchemaTest
SchemaIntegrityTest
```

or current equivalents.

---

# 140. Phase 5.7 Compatibility Test Handoff

Document tests that Phase 5.7 must add/enable for:

```text
product_type
is_published
draft masking
MADE_TO_ORDER stock indicator
product_type query filter
final availability derivation
```

Do not lose these requirements.

---

# 141. No Schema Migration for Known 5.7 Fields

Phase 5.2 must not create:

```text
products.product_type
products.is_published
```

because the current roadmap assigns them to Phase 5.7.

This is a hard sequencing rule.

---

# 142. No Other Product Columns

Do not add:

```text
price
stock
image_url
material
color
dimensions
availability
stock_indicator
```

to `products`.

Group C intentionally normalized these concerns elsewhere.

---

# 143. No Frontend Changes

Do not modify:

```text
frontend/web/
frontend/app/
frontend/design-system/
```

Phase 5.2 is API/backend only.

---

# 144. No Product UI

Do not implement:

```text
product grid
product cards
product detail page
search bar
filter sidebar
sorting UI
```

Frontend groups handle these later.

---

# 145. Migration Safety

If an unrelated genuine defect requires a schema correction:

use a new migration only.

Any destructive test command must use the documented:

```text
non-production
+
disposable isolated DB
+
explicit --force
+
DB-name guard
```

policy.

Never run `migrate:fresh` against the normal application database.

---

# 146. No Migration Expected

Expected Phase 5.2 schema changes:

```text
NONE
```

because this phase reads the Group C model.

If the agent believes a schema change is necessary beyond the known Phase 5.7 fields:

STOP and document why before silently changing the model.

---

# 147. Dependencies

Expected new Composer dependencies:

```text
NONE
```

Laravel/Eloquent/API Resources are sufficient.

---

# 148. Code Quality

Maintain:

```text
cognitive complexity <= 15
max 3 returns where practical
small focused classes
explicit serializers
allow-listed sorting/filtering
no duplicated derived logic
```

Do not build an enterprise catalog framework.

---

# 149. Centralize Derived Catalog Logic

If price/availability/primary-image derivation is needed in multiple resources:

centralize it in a focused domain/read helper.

Avoid:

```text
controller computes price
resource recomputes price
cart later computes different price
```

However, do not make the read helper authoritative for checkout financial locking.

Checkout still re-resolves authoritative prices transactionally.

---

# 150. Likely Implementation Areas

Depending on current repository structure:

```text
app/Http/Controllers/
app/Http/Requests/
app/Http/Resources/
app/Services/ or app/Queries/
app/Models/Product.php
routes/api.php
tests/Feature/
docs/
```

Modify only what is necessary.

---

# 151. Route Verification

Verify:

```text
GET /api/v1/products
GET /api/v1/products/{product}
```

exist exactly once.

Do not create duplicate route aliases.

---

# 152. Required Verification

Run focused Product Read tests.

Then run:

```bash
php artisan test
vendor/bin/pint --test
vendor/bin/phpstan analyse
composer audit
git diff --check
```

Use repository-standard equivalents if defined.

No destructive migration should be necessary for Phase 5.2.

---

# 153. Completion Report

Return:

## Phase 5.2 status

```text
PASS
```

or:

```text
BLOCKED
```

## CAT-001

State which collection behavior is implemented.

## CAT-002

State which detail behavior is implemented.

## Group C preservation

Confirm:

```text
no product schema redesign
no price duplication
no inventory duplication
no media duplication
```

## Product price

State exact derivation rule.

## Availability

State exactly which current signals are implemented and which remain dependent on Phase 5.7.

## Search/filter/sort

List implemented query parameters.

## Schema changes

Expected:

```text
NONE
```

## Phase 5.7 dependency

Explicitly state:

```text
product_type
is_published
final publication/product-type visibility
```

remain deferred.

## Frontend

Must state:

```text
NONE
```

## Tests

Exact focused/full results.

## Quality checks

Exact Pint/PHPStan/audit/diff results.

---

# 154. Definition of Done

Phase 5.2 is complete when:

* CAT-001 public Product collection exists;
* CAT-002 public Product detail exists;
* both require no authentication;
* Product reads use the existing Group C schema;
* Product model is not redesigned;
* Product price is derived from ProductVariant according to the approved rule;
* no Product-level duplicate price column is introduced;
* public image URLs derive from ProductImage storage references;
* internal `file_path` never leaks;
* primary image is derived from Group C media state;
* CAT-002 gallery is deterministically ordered;
* embedded variants come from ProductVariant;
* inactive variants are excluded;
* raw inventory quantities never appear publicly;
* category is embedded only as a bounded summary;
* search/filter/sort use strict allow-lists;
* pagination follows V1 conventions;
* sorting is deterministic;
* slug/ID detail resolution works;
* inactive and deleted products are publicly masked;
* no separate image endpoint is added;
* no duplicate category-products route is added;
* no cart/checkout behavior is pulled forward;
* no frontend work is added;
* existing Group C tests remain green;
* Product Read tests pass;
* full backend suite passes;
* Pint passes;
* PHPStan passes;
* Composer audit has no blocker;
* the known `product_type` / `is_published` dependency remains assigned to Phase 5.7 rather than silently implemented here.

---

# 155. Out of Scope

Do not implement:

```text
product_type schema field
is_published schema field
final MADE_TO_ORDER availability semantics
product admin CRUD
variant standalone read endpoints unless roadmap says otherwise
inventory operational API
cart
checkout
recommendation engine
reviews
wishlist
product comparison
frontend product cards
frontend product detail page
advanced search engine
```

---

# 156. STOP Condition

STOP when CAT-001 and CAT-002 have a clean, tested Product Read implementation built **directly on the Group C domain model**, without duplicating ProductVariant, ProductImage, ProductStock, taxonomy, or metadata concerns onto the Product table.

Where behavior depends on the intentionally deferred:

```text
product_type
is_published
```

document the Phase 5.7 dependency rather than fabricating values or altering the roadmap.

Do not continue automatically.

DO NOT COMMIT, STAGE OR PUSH.

The project owner handles Git operations.
