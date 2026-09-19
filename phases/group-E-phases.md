# Phase 5.3 — Product Detail API

## Purpose

Implement the complete public Product Detail read path for:

```text
CAT-002
GET /api/v1/products/{product}
```

using the domain model already established in Group C and the Product Read foundation from Phase 5.2.

This phase exists to turn the normalized backend catalog model into the rich public representation needed by:

```text
product landing pages
Next.js SSR/SEO
Flutter product details
variant selection
gallery display
cart preparation
made-to-order request preparation
```

without changing the underlying Product architecture.

---

# 1. Critical Rule — Preserve Group C

Do not redesign:

```text
products
product_variants
product_images
product_stocks
categories
product_materials
product_room_tags
product_style_tags
product_assets
```

The API must assemble Product Detail from these relationships.

Do not denormalize convenience data back onto `products`.

---

# 2. Phase Dependency

Phase 5.3 assumes Phase 5.2 established the reusable Product Read foundation, including where applicable:

```text
public product resolution
public visibility scope
price derivation
availability derivation
primary image resolution
public resource mapping
```

Reuse that implementation.

Do not create a second independent Product Detail pricing or visibility system.

---

# 3. Read Current Authoritative Docs First

Before modifying code, read:

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

Then inspect current Phase 5.1/5.2 implementation.

The latest repository state is authoritative.

---

# 4. Endpoint

Implement exactly:

```http
GET /api/v1/products/{product}
```

Do not add:

```text
/products/{product}/detail
/product/{product}
/catalog/products/{product}
/products/{product}/images
```

CAT-002 is the canonical Product Detail endpoint.

---

# 5. Public Access

CAT-002 is:

```text
PUBLIC_READ
```

No authentication required.

Do not require:

```text
Clerk
CUSTOMER
STAFF
ADMIN
```

for Product Detail.

---

# 6. No Authentication Side Effects

Serving Product Detail must not invoke:

```text
AuthenticateClerk
LocalUserProvisioner
Clerk backend API
```

Public catalog availability must remain independent of login.

---

# 7. `{product}` Dual Resolution

`{product}` accepts either:

```text
canonical slug
or
stable public machine ID
```

Examples:

```text
modern-3-seater-fabric-sofa
prod_...
```

Reuse the resolver established in Phase 5.2.

---

# 8. Slug Is Canonical for SEO

Slug remains the preferred human-facing URL identity.

Use exact lookup.

Do not use:

```text
LIKE
contains
fuzzy matching
case-normalized guessing
```

for detail resolution.

---

# 9. Machine ID

Machine ID must resolve to the same Product representation.

Do not create different serialization depending on whether the caller used ID or slug.

---

# 10. Public Visibility

Product Detail must use the same public visibility rules as CAT-001.

At the current stage this includes every authoritative visibility condition already implemented.

Examples:

```text
soft deleted → hidden
inactive → hidden
```

Once Phase 5.7 introduces publication state:

```text
is_published = false
→ hidden
```

must join the same scope.

---

# 11. Public Masking

For:

```text
unknown Product
inactive Product
soft-deleted Product
future unpublished Product
```

return the same public not-found behavior.

Normally:

```text
404 RESOURCE_NOT_FOUND
```

according to the current error registry.

Do not reveal internal state.

---

# 12. Phase 5.7 Dependency Remains

Do not add:

```text
products.product_type
products.is_published
```

during Phase 5.3.

Those remain assigned to Phase 5.7 unless the latest roadmap explicitly supersedes that decision.

---

# 13. Do Not Fabricate Missing Fields

Until Phase 5.7 establishes an authoritative Product Type:

do not fake:

```json
"product_type": "IN_STOCK"
```

for every Product.

Do not infer Product Type from inventory.

---

# 14. Product Detail Representation

The final CAT-002 representation is based on Product Summary plus:

```text
description
images[]
variants[]
created_at
updated_at
```

The final output also contains the currently implemented contracted summary fields:

```text
id
name
slug
price
category
availability
```

`product_type` and `stock_indicator` are explicitly deferred to Phase 5.7,
which will establish their authoritative sources and final availability
semantics. They must not be included in the Phase 5.3 response until that
phase is complete.

---

# 15. Explicit Product Detail Resource

Use a dedicated serializer such as:

```text
ProductDetailResource
```

or the current project equivalent.

Do not reuse a generic model dump.

---

# 16. No Mass Serialization

Never use:

```php
$product->toArray()
```

as the Product Detail contract.

The Product model contains internal fields that must remain private.

---

# 17. Public Field Allow-List

CAT-002 should expose only fields approved by the current API contract.

Do not add fields because they happen to exist in MySQL.

---

# 18. Internal Product Fields

Never expose:

```text
is_active
is_published
deleted_at
sku_prefix
internal timestamps not contracted
internal notes
storage paths
```

through CAT-002.

---

# 19. Description

Product Detail includes:

```text
description
```

from the existing Product model.

Do not transform stored plain catalog text into HTML.

Return according to the existing serialization convention.

---

# 20. Price Comes From Variants

Product Detail price must reuse the same Product-level pricing derivation established for CAT-001.

Do not duplicate the pricing algorithm in `ProductDetailResource`.

---

# 21. No Product Price Column

Do not add:

```text
products.price
```

ProductVariant remains the pricing boundary.

---

# 22. Money Shape

Return:

```json
{
  "amount": 125000000,
  "currency": "TZS"
}
```

Use integer minor units.

Never return financial amounts as floats.

---

# 23. Price Consistency

For the same Product state:

```text
CAT-001 price
=
CAT-002 price
```

The collection and detail APIs must never derive different prices.

Add a regression test.

---

# 24. Internal Cost Protection

Never expose:

```text
cost_price_amount
cost_price_currency
```

from ProductVariant.

This applies even to authenticated Staff/Admin callers using the public CAT-002 route.

Operational product reads have separate endpoints later.

---

# 25. Compare-at Price

Do not expose compare-at pricing unless currently part of the frozen public Product Detail schema.

Database capability alone does not make it API-contract data.

---

# 26. Category

Product Detail embeds Category according to the approved CAT-002 representation.

Do not recursively embed:

```text
category.children
category.products
category.recommendations
```

---

# 27. Category Contract Ambiguity Review

Before implementation, compare:

```text
api-contract.md CAT-002 example
api-contract.md summary/detail table
api-resources.md
openapi.yaml
```

There may be a discrepancy over whether Product Detail's embedded Category contains:

```text
id
name
slug
```

only,

or additionally:

```text
description
```

Do not silently choose a new representation.

Resolve using the most authoritative current schema.

If the docs remain contradictory:

record a minimal clarification in existing API/decision documentation.

Do not create a third shape.

---

# 28. Category Visibility

A public Product must not expose an internal/inactive Category unexpectedly.

Use the public Category rules established by Phase 5.1.

---

# 29. Product Images

CAT-002 embeds the full Product gallery.

Images come exclusively from:

```text
product_images
```

Do not add image fields to `products`.

---

# 30. Image Resource

Each image contains exactly the approved public fields:

```text
id
url
alt_text
sort_order
is_primary
```

---

# 31. Never Expose `file_path`

Group C stores:

```text
product_images.file_path
```

as an internal storage reference.

It must never appear in public JSON.

---

# 32. Public URL Derivation

Convert `file_path` to:

```text
url
```

through the existing storage/media abstraction.

Do not hard-code:

```text
localhost
production domain
S3 bucket hostname
CDN hostname
```

inside Product resources.

---

# 33. Gallery Ordering

Gallery ordering must remain deterministic:

```text
sort_order ASC
id ASC
```

Reuse the Group C relationship if it already enforces this.

---

# 34. Primary Image Integrity

At most one image may have:

```text
is_primary = true
```

per Product.

Group C already owns this invariant.

Do not reproduce primary-image validation inside the read controller.

---

# 35. Primary Image Consistency

If CAT-001 exposes `primary_image`, the image selected there must match the image marked:

```text
is_primary = true
```

inside CAT-002 gallery.

Add regression coverage.

---

# 36. Product Without Images

A Product may legitimately have no gallery images.

Return:

```json
"images": []
```

unless the frozen contract explicitly defines another representation.

Do not invent placeholder image records.

---

# 37. Image Alt Text

Return the stored:

```text
alt_text
```

according to the Group C image model.

Do not auto-generate repetitive alt text in Laravel during reads.

Frontend/display accessibility logic can use the provided catalog metadata later.

---

# 38. Variant Embedding

CAT-002 embeds active Product Variants.

Use the existing:

```text
Product::variants()
```

relationship and Group C ordering.

---

# 39. Embedded Variant Fields

Each embedded variant summary uses the frozen structure:

```text
id
sku
name
price
availability
stock_indicator
```

where those public signals are currently authoritative.

---

# 40. Do Not Include `product_id`

The Product parent is already implicit.

Do not include:

```text
product_id
```

inside CAT-002 embedded variants.

---

# 41. Do Not Include Variant Timestamps

Embedded variant summaries omit:

```text
created_at
updated_at
```

Standalone CAT-005/CAT-006 variants handle their own detail representation later.

---

# 42. Variant Name Mapping

Group C stores:

```text
variant_name
```

The API contract uses:

```text
name
```

Map at the resource boundary.

Do not rename the database schema for presentation reasons.

---

# 43. Variant Price

Variant price comes directly from:

```text
price_amount
price_currency
```

using integer minor units.

No floating point conversion.

---

# 44. Active Variants Only

Public Product Detail must not include:

```text
is_active = false
```

variants.

---

# 45. Variant Ordering

Variants must be stable:

```text
display_order ASC
id ASC
```

or exactly the deterministic ordering already enforced by the current relationship.

---

# 46. Cross-Product Variant Leakage

No Product Detail may contain a Variant belonging to another Product.

Add explicit regression coverage.

---

# 47. Variant Attributes

Do not automatically serialize the full:

```text
attributes
```

JSON object merely because ProductVariant stores it.

Only expose fields currently approved by CAT-002.

---

# 48. Dimensions

Do not automatically add:

```text
width_cm
height_cm
depth_cm
weight_kg
```

to CAT-002 unless the current Product Detail schema explicitly includes them.

Keep API expansion intentional.

---

# 49. Standalone Variant Endpoints

Do not implement:

```text
CAT-005
CAT-006
```

during Phase 5.3 unless the latest Group E roadmap explicitly assigns them here.

This phase embeds variant summaries only.

---

# 50. Availability

Product Detail exposes the same public:

```text
availability
stock_indicator
```

as Product Summary.

Reuse the shared catalog availability resolver from Phase 5.2.

---

# 51. No Separate Availability Endpoint

Do not add:

```text
GET /products/{product}/availability
```

Availability is embedded in Product Detail.

---

# 52. Public Stock Security

Never expose:

```text
physical_quantity
quantity
reserved_quantity
available_quantity
warehouse_location
```

through CAT-002.

---

# 53. Availability Is Informational

The API may say:

```text
available
```

but this does not reserve stock or guarantee checkout.

Do not introduce inventory locks into Product Detail.

---

# 54. Read Must Be Side-Effect Free

GET Product Detail must not modify:

```text
inventory
reserved inventory
Product timestamps
Variant timestamps
Image timestamps
view counters
```

---

# 55. MADE_TO_ORDER

Once Phase 5.7 provides Product Type:

```text
MADE_TO_ORDER
```

products remain publicly discoverable.

Their primary customer action is later:

```text
Request Furniture
```

rather than Add to Cart.

Do not implement request creation here.

---

# 56. Product Type Does Not Come From Stock

Never infer:

```text
zero stock = MADE_TO_ORDER
```

These are different domain concepts.

---

# 57. Materials

Do not automatically embed normalized Product materials in CAT-002 unless current API contract explicitly includes them.

Preserve the Group C relation for later specification/filter work.

---

# 58. Room Tags

Do not expose room tags unless currently contracted.

Do not duplicate Category data using room tags.

---

# 59. Style Tags

Same rule:

preserve them in the domain but do not expand CAT-002 without an approved contract.

---

# 60. 3D/AR Assets

Do not expose:

```text
product_assets
```

during Phase 5.3 unless the current frozen Product Detail schema explicitly includes them.

Do not build AR presentation yet.

---

# 61. Recommendations

Do not add:

```text
related_products
recommended_products
frequently_bought_together
```

to Product Detail.

Recommendation behavior belongs to a later phase.

---

# 62. No Reviews

Do not implement reviews/ratings in Product Detail.

They are not part of current V1 scope unless explicitly approved later.

---

# 63. No Wishlist

Do not add:

```text
is_favourite
is_wishlisted
```

to Product Detail.

The public endpoint must not depend on authenticated user context.

---

# 64. No Customer-Specific Serialization

The same CAT-002 response shape should serve:

```text
anonymous
Customer
Staff
Admin
```

when using the public route.

Do not expose additional internal fields merely because an Authorization header happens to be present.

---

# 65. SSR / SEO Readiness

CAT-002 must provide enough current contracted public data for future Next.js rendering of:

```text
page title
description
canonical product page
OpenGraph preview
Schema.org Product data
```

Do not implement frontend SEO tags here.

---

# 66. No SEO Metadata Duplication

Do not add:

```text
meta_title
meta_description
canonical_url
og_title
```

to Product unless explicitly approved.

The frontend can derive presentation metadata from the Product Detail payload.

---

# 67. Resource Resolution Efficiency

Resolve the Product once.

Avoid patterns such as:

```text
query Product
query Product again in Resource
query Product again for image
```

Use appropriate eager loading.

---

# 68. Eager Loading

CAT-002 should load only relationships required by its representation.

Likely:

```text
category
images
active variants
stock information required for coarse availability
```

according to current implementation.

Do not load unrelated commerce data.

---

# 69. Avoid N+1

Do not execute stock queries separately for every embedded variant when an eager-loaded relation/aggregate can provide the required information.

---

# 70. Keep Query Bounded

A Product Detail request retrieves:

```text
one Product
its bounded image gallery
its variants
necessary availability data
```

Do not recursively retrieve entire categories/catalog.

---

# 71. Controller

Keep the controller small.

Conceptually:

```text
identifier
→ public Product resolver/query
→ eager load approved detail relations
→ ProductDetailResource
```

Do not calculate pricing/gallery/availability manually inside the controller.

---

# 72. Reuse Phase 5.2 Components

Reuse existing:

```text
ProductPublicScope
ProductResolver
CatalogPriceResolver
CatalogAvailabilityResolver
ProductImageUrlResolver
```

or their actual equivalents.

Do not create parallel "detail" versions of the same business logic.

---

# 73. Do Not Overabstract

Do not introduce:

```text
ProductDetailOrchestratorFactory
CatalogGraphEngine
ProductPresentationRepositoryInterface
```

unless existing project architecture genuinely requires it.

Use simple Laravel patterns.

---

# 74. Cacheability

CAT-002 is:

```text
PUBLIC
CACHEABLE
```

Use the same approved public cache semantics as CAT-001/CAT-003/CAT-004.

---

# 75. No User-Specific Cache Variation

Because CAT-002 is public:

do not vary its response by:

```text
user
role
session
Clerk identity
```

This keeps it safe for shared caching/CDN use later.

---

# 76. Rate Limiting

Use the existing moderate:

```text
public-read
```

limiter.

Do not create an aggressive Product Detail-specific limiter.

---

# 77. Error Envelope

All errors must use the canonical API error structure.

No ad hoc:

```json
{"message":"Product not found"}
```

unless that is nested inside the canonical envelope as currently defined.

---

# 78. 404 Consistency

These should be indistinguishable publicly:

```text
unknown slug
unknown ID
inactive Product
soft-deleted Product
future unpublished Product
```

where appropriate.

---

# 79. No Variant-Specific Query Parameter

Do not add:

```text
GET /products/{product}?variant=...
```

as a new detail contract unless already approved.

CAT-002 returns Product Detail plus variant choices.

The frontend can select among returned variants later.

---

# 80. No Dynamic Includes

Do not add:

```text
?include=images,variants,category
?fields=...
```

V1 deliberately avoids dynamic graph/field selection.

---

# 81. No Separate Image Endpoint

Continue to reject:

```text
GET /products/{product}/images
```

Gallery is embedded in CAT-002.

---

# 82. Test — Anonymous Product Detail

Without authentication:

```text
GET /api/v1/products/{product}
→ 200
```

for a publicly visible Product.

---

# 83. Test — Slug Resolution

Create a Product with known slug.

Request by slug.

Verify correct Product.

---

# 84. Test — ID Resolution

Request same Product by public ID.

Verify identical resource semantics.

---

# 85. Test — Slug/ID Consistency

The same Product fetched using either identifier must serialize equivalent Product data.

---

# 86. Test — Unknown Identifier

Unknown slug and unknown ID:

```text
404
```

with canonical error.

---

# 87. Test — Inactive Product

Existing inactive Product:

```text
404
```

No internal state leakage.

---

# 88. Test — Soft Deleted Product

Soft-delete a Product.

Both slug and ID requests must return public not-found.

---

# 89. Test — Description

Verify Product Detail includes the correct description.

CAT-001 should remain lightweight.

---

# 90. Test — Price Consistency

Verify:

```text
CAT-001 Product price
=
CAT-002 Product price
```

for the same Product.

---

# 91. Test — Gallery Shape

Verify each image exposes only:

```text
id
url
alt_text
sort_order
is_primary
```

---

# 92. Test — Gallery Ordering

Create images with non-sequential insertion order.

Verify API returns:

```text
sort_order ASC
id ASC
```

---

# 93. Test — File Path Leakage

Assert response JSON does not contain:

```text
file_path
```

or internal storage identifiers.

---

# 94. Test — Primary Image Consistency

Verify:

```text
CAT-001 primary_image
```

matches CAT-002 gallery primary image.

---

# 95. Test — No Images

A Product without images must return the approved empty/null structures without server error.

---

# 96. Test — Variant Shape

Verify embedded variants contain approved fields only.

Do not merely test array count.

---

# 97. Test — Variant Order

Verify `display_order`.

---

# 98. Test — Inactive Variant Exclusion

Seed:

```text
active variant
inactive variant
```

Only the active variant appears.

---

# 99. Test — Cross-Product Isolation

Product A Detail must never include Product B's variants or images.

---

# 100. Test — Variant Cost Hidden

Seed internal cost price.

Assert it does not appear.

---

# 101. Test — Raw Inventory Hidden

Seed stock rows.

Assert response contains no:

```text
quantity
reserved_quantity
available_quantity
warehouse_location
```

---

# 102. Test — Availability Consistency

Where authoritative in the current phase:

```text
CAT-001 availability
=
CAT-002 availability
```

for the same Product state.

---

# 103. Test — Category Shape

Assert the exact approved embedded category fields.

This test should protect whichever representation is resolved from the current docs.

---

# 104. Test — Category Internal Fields Hidden

Ensure Product Detail does not accidentally expose:

```text
parent_id
space_type
display_order
is_active
```

inside category data.

---

# 105. Test — Public Resource Does Not Change by Role

If convenient using existing test helpers, request CAT-002 as:

```text
anonymous
Customer
Staff
Admin
```

and verify the public representation does not expose privileged data.

Do not require auth just to run the test.

---

# 106. Test — Side Effects

Snapshot relevant:

```text
stock
reserved stock
timestamps
```

before GET.

Verify no mutation afterward.

---

# 107. Test — Query Efficiency

If existing repository conventions support query-count tests:

protect against obvious N+1 behavior for:

```text
images
variants
variant availability
category
```

Do not add a complex benchmarking framework.

---

# 108. Existing Group C Tests

All existing tests for:

```text
Product
ProductVariant
ProductImage
ProductStock
Category
schema integrity
```

must remain green.

---

# 109. Existing Phase 5.2 Tests

Phase 5.3 must not regress:

```text
CAT-001
search/filter/sort
pagination
public visibility
summary resources
```

---

# 110. Contract Consistency Review

Before completion compare runtime CAT-002 against:

```text
api-contract.md
api-resources.md
openapi.yaml
api-conventions.md
```

Do not let implementation become a fifth independent specification.

---

# 111. OpenAPI

Update OpenAPI only where necessary to match the implemented approved CAT-002 contract.

Do not document deferred fields as implemented if they are still waiting for Phase 5.7.

---

# 112. Phase 5.7 Handoff

Document that Phase 5.7 must complete CAT-002 behavior for:

```text
product_type
is_published
unpublished Product masking
MADE_TO_ORDER indicator
final Product availability semantics
```

where those cannot yet be authoritative.

---

# 113. No Schema Changes Expected

Expected:

```text
Schema changes:
NONE
```

Phase 5.3 should consume the normalized Group C model.

---

# 114. Stop on Unexpected Schema Need

If the agent believes Product Detail requires adding a new Product/Variant/Image column not already planned:

do not silently add it.

First determine whether:

```text
the API contract truly requires it
or
the value already exists in another normalized Group C relation
```

Prefer existing relations.

---

# 115. Migration Safety

If a genuine unrelated schema defect is discovered and a migration becomes unavoidable:

* create a new migration;
* do not edit historical Group C migrations;
* use only a non-production disposable isolated database for destructive migration verification;
* verify database name explicitly;
* use `--force` only with the documented safeguards.

---

# 116. No New Dependencies

Expected:

```text
Composer dependencies:
NONE
```

Laravel/Eloquent/API Resources/storage abstractions are sufficient.

---

# 117. No Frontend Work

Do not modify:

```text
frontend/web/
frontend/app/
frontend/design-system/
```

No Product Detail page UI belongs here.

---

# 118. No Cart Implementation

CAT-002 prepares data the future frontend/cart can use.

Do not implement:

```text
add to cart
quantity selection persistence
cart validation
```

here.

---

# 119. No Request Implementation

For future MADE_TO_ORDER Products, CAT-002 supports request initiation context.

Do not implement Furniture Request submission here.

---

# 120. No Admin Product Detail

Do not implement:

```text
CAT-014
GET /api/v1/admin/products/{product}
```

during this phase.

Operational Product Detail has different exposure rules.

---

# 121. No Variant Detail API

Do not implement CAT-005/CAT-006 unless separately requested.

---

# 122. Code Quality

Maintain:

```text
cognitive complexity <= 15
<= 3 returns where practical
small resource/query classes
minimal comments
no duplicated domain strings
```

Avoid giant Product Detail methods.

---

# 123. Expected Implementation Areas

Depending on existing Phase 5.2 code:

```text
app/Http/Controllers/
app/Http/Resources/
app/Services/ or app/Queries/
routes/api.php
tests/Feature/
docs/api/
docs/decisions.md
```

Modify only what this phase needs.

---

# 124. Verification Commands

Run focused Product Detail tests first.

Then run:

```bash
php artisan test
vendor/bin/pint --test
vendor/bin/phpstan analyse
composer audit
git diff --check
```

Use canonical repository scripts if different.

No `migrate:fresh` should normally be required.

---

# 125. Route Review

Verify:

```text
GET /api/v1/products/{product}
```

exists exactly once and remains public.

Confirm there is no:

```text
/products/{product}/images
/products/{product}/detail
```

duplicate.

---

# 126. Completion Report

Return:

## Phase 5.3 status

```text
PASS
```

or:

```text
BLOCKED
```

## CAT-002

Confirm the endpoint and public access.

## Identifier resolution

Report:

```text
slug
ID
```

behavior.

## Product representation

List final exposed fields.

## Price

State the reused Product price derivation.

## Category

State exact embedded category representation.

## Gallery

State ordering and public URL behavior.

## Variants

State active filtering, order, and embedded fields.

## Availability

State current implemented semantics and any Phase 5.7 dependency.

## Internal data protection

Confirm:

```text
file_path hidden
cost price hidden
raw inventory hidden
internal Product state hidden
```

## Schema

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

Report exact focused and full-suite counts.

## Quality

Report:

```text
Pint
PHPStan
Composer audit
git diff --check
```

---

# 127. Definition of Done

Phase 5.3 is complete when:

* CAT-002 is publicly callable;
* slug resolution works;
* machine-ID resolution works;
* both identifiers return the same Product semantics;
* unknown Product is masked correctly;
* inactive Product is masked;
* soft-deleted Product is masked;
* Product Detail uses explicit serialization;
* description is exposed correctly;
* Product price reuses the authoritative variant-derived rule;
* Category embedding matches the authoritative current contract;
* Product gallery comes from `product_images`;
* image URLs are derived from storage configuration;
* `file_path` never leaks;
* gallery ordering is deterministic;
* primary-image state remains consistent with CAT-001;
* no-image Products behave safely;
* only active Product Variants are embedded;
* embedded variants are deterministically ordered;
* variant price uses integer minor units;
* cross-Product variants/images cannot leak;
* variant cost fields remain private;
* raw inventory remains private;
* availability matches CAT-001 where currently authoritative;
* GET is side-effect free;
* public serialization does not vary by authenticated role;
* no recursive graph is introduced;
* no separate Product Images endpoint is added;
* no standalone Variant API is pulled forward;
* no admin Product API is pulled forward;
* no Group C schema redesign occurs;
* no duplicate Product price/image/inventory columns are introduced;
* Phase 5.7 dependencies remain explicitly documented;
* existing Group C tests remain green;
* Phase 5.2 tests remain green;
* Product Detail tests pass;
* full backend suite passes;
* Pint passes;
* PHPStan passes;
* Composer audit has no blocker;
* no frontend files are modified.

---

# 128. Out of Scope

Do not implement:

```text
Product admin CRUD
CAT-005/CAT-006 standalone Variant API
CAT-014 operational Product Detail
cart mutations
checkout
Furniture Request submission
reviews
ratings
wishlist
recommendation engine
product comparisons
AR viewer
frontend Product Detail page
product_type schema migration
is_published schema migration
```

unless the latest authoritative roadmap explicitly moves one into Phase 5.3.

---

# 129. STOP Condition

STOP when:

```text
GET /api/v1/products/{product}
```

provides a complete, tested, public Product Detail representation assembled from the existing Group C Product, Variant, Image, Category and Inventory relationships without duplicating their state onto `products`.

Do not continue automatically to Phase 5.4.

DO NOT COMMIT, STAGE OR PUSH.

The project owner handles Git operations.
