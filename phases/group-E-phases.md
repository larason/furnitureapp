# Phase 5.4 — Variant API

## Purpose

Implement the public Variant Read API for:

```text
CAT-005
GET /api/v1/products/{product}/variants
```

and:

```text
CAT-006
GET /api/v1/products/{product}/variants/{variant}
```

using the existing Group C ProductVariant model without redesigning the domain.

This phase must preserve the core invariant:

```text
Variant
belongs to exactly one Product
```

and therefore:

```text
Variant lookup
must always be scoped through its parent Product
```

A Variant is not an independent top-level catalog resource.

---

# 1. Read Current Authoritative Docs First

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

Also inspect the current implementations from:

```text
Phase 5.2 Product Read API
Phase 5.3 Product Detail API
```

Then inspect:

```text
app/Models/Product.php
app/Models/ProductVariant.php
app/Models/ProductStock.php
app/Models/ProductImage.php
```

and their current tests/factories.

Do not implement from old assumptions.

---

# 2. Preserve Group C Variant Model

The current ProductVariant schema is authoritative.

It includes concepts such as:

```text
id
product_id
sku
variant_name
price_amount
price_currency
compare_at_price_amount
compare_at_price_currency
cost_price_amount
cost_price_currency
width_cm
height_cm
depth_cm
weight_kg
attributes
is_default
is_active
display_order
timestamps
```

Use the actual schema.

Do not add duplicate Product/Variant state.

---

# 3. Variant Remains the Sellable / Pricing Unit

Group C established ProductVariant as the canonical unit for:

```text
SKU
price
variant name/configuration
physical dimensions
weight
variant attributes
```

Preserve this architecture.

Do not move these concerns onto Product.

---

# 4. Endpoint CAT-005

Implement exactly:

```http
GET /api/v1/products/{product}/variants
```

Purpose:

```text
retrieve public active variants belonging to one public Product
```

Do not create:

```text
GET /api/v1/variants
GET /api/v1/variants/{variant}
```

Global Variant endpoints are not part of V1.

---

# 5. Endpoint CAT-006

Implement exactly:

```http
GET /api/v1/products/{product}/variants/{variant}
```

Purpose:

```text
retrieve one public Variant
strictly under its parent Product
```

---

# 6. Public Access

Both endpoints are:

```text
PUBLIC_READ
```

No Clerk authentication.

No CUSTOMER role.

No Staff/Admin permission.

Anonymous browsing must work.

---

# 7. No Authentication Coupling

Do not invoke:

```text
AuthenticateClerk
LocalUserProvisioner
Clerk API
RBAC
```

for CAT-005 or CAT-006.

---

# 8. Product Resolution

`{product}` uses the same Product resolution semantics already established for CAT-002.

It may accept:

```text
Product slug
or
Product public ID
```

according to the frozen contract.

Reuse the existing Product resolver.

---

# 9. Product Must Be Publicly Visible

Do not return variants for a Product that is not publicly visible.

Current rules should include whatever Phase 5.2/5.3 already enforce, such as:

```text
soft-deleted Product
→ 404

inactive Product
→ 404
```

After Phase 5.7:

```text
is_published = false
→ 404
```

should use the same public scope.

---

# 10. No Variant Enumeration Through Hidden Products

A caller must not be able to discover Variant data for a hidden Product by directly knowing:

```text
variant ID
```

Parent Product public visibility is evaluated first.

---

# 11. Variant Resolution

For CAT-006, `{variant}` resolves strictly by Variant machine ID.

Do not resolve Variant by:

```text
SKU
variant name
slug
```

unless the current frozen contract explicitly changes this.

The current contract uses Variant ID.

---

# 12. Strict Parent-Child Ownership

This is the most important CAT-006 rule.

Given:

```text
Product A
Variant B
```

if Variant B belongs to Product C:

```text
GET /products/A/variants/B
```

must return:

```text
404 RESOURCE_NOT_FOUND
```

Do not return 403.

Do not return:

```text
VARIANT_BELONGS_TO_ANOTHER_PRODUCT
```

Do not reveal cross-Product existence.

---

# 13. Resolve Through Parent Relationship

Prefer a query equivalent to:

```text
$product
    ->variants()
    ->whereKey($variantId)
```

with public-active scope.

Do not:

```text
ProductVariant::find($variantId)
```

first and then reveal whether ownership mismatched.

Authorization/privacy should be inherent in the scoped lookup.

---

# 14. Active Variants Only

Public Variant endpoints return only:

```text
is_active = true
```

variants.

Inactive Variant:

```text
404 RESOURCE_NOT_FOUND
```

for detail.

Inactive variants do not appear in collection.

---

# 15. CAT-005 Ordering

Variant collection order must be deterministic.

Use the existing Group C order:

```text
display_order ASC
id ASC
```

or the actual established relationship ordering.

Do not rely on insertion order.

---

# 16. CAT-005 Response Representation

CAT-005 returns standalone public Variant objects.

Current Phase 5.4 Variant representation:

```text
id
product_id
sku
name
price
availability
created_at
updated_at
```

The current implementation deliberately defers `stock_indicator` to Phase 5.7,
along with final product-type and availability semantics. Until that phase
establishes authoritative sources, CAT-005 and CAT-006 omit the deferred field;
it must not be inferred from zero stock or fabricated from ProductVariant state.
Do not return the embedded-summary shape if the contract requires standalone
detail shape.

---

# 17. CAT-006 Response Representation

CAT-006 uses the same standalone Variant object representation:

```text
id
product_id
sku
name
price
availability
created_at
updated_at
```

Use one shared serializer where practical.

---

# 18. Embedded vs Standalone Variant Shapes

There are two different representations.

## Embedded in CAT-002

```text
id
sku
name
price
availability
```

No `product_id`.

No timestamps.

## Standalone CAT-005/CAT-006

```text
id
product_id
sku
name
price
availability
created_at
updated_at
```

Preserve this distinction.

Do not collapse them accidentally into one over-broad resource.

---

# 19. Resource Classes

Prefer clear resources such as:

```text
VariantSummaryResource
VariantResource
```

or equivalent.

`VariantSummaryResource` may serve CAT-002 embedding.

`VariantResource` may serve CAT-005/CAT-006.

Do not expose internal Eloquent arrays directly.

---

# 20. API `name` Mapping

Database:

```text
variant_name
```

API:

```text
name
```

Map this at serialization.

Do not rename the database column.

---

# 21. Product ID

Standalone Variant responses include:

```text
product_id
```

Use the public Product machine ID representation.

Do not expose an internal raw DB key if the API has an opaque ID abstraction.

---

# 22. SKU

SKU is public for standalone Variant reads according to the frozen contract.

Return the authoritative Group C:

```text
sku
```

Do not derive a second SKU.

---

# 23. Variant Price

Variant price comes directly from:

```text
price_amount
price_currency
```

Return:

```json
{
  "amount": 125000000,
  "currency": "TZS"
}
```

Money remains integer minor units.

---

# 24. Never Use Float Money

Do not convert:

```text
125000000
```

to floating-point TZS.

Do not format currency server-side for display.

Clients format money.

---

# 25. Cost Price Must Never Leak

Never expose:

```text
cost_price_amount
cost_price_currency
```

through CAT-005 or CAT-006.

This remains true for authenticated Staff/Admin callers using the public route.

---

# 26. Compare-at Price

Do not expose:

```text
compare_at_price
```

unless the current API contract explicitly includes it.

The DB field existing is not enough.

---

# 27. Physical Dimensions

Do not expose:

```text
width_cm
height_cm
depth_cm
weight_kg
```

unless the authoritative Variant public contract explicitly includes them.

The current CAT-005/CAT-006 structure does not list them.

Do not expand the API opportunistically.

---

# 28. Variant Attributes

Do not automatically serialize:

```text
attributes
```

JSON.

Examples such as:

```text
color
fabric
finish
size
configuration
leg_finish
```

remain normalized/flexible Group C data but are not automatically public API fields.

Only expose them if the current frozen resource contract says so.

---

# 29. `is_default`

Do not expose:

```text
is_default
```

unless current API resources explicitly include it.

Frontend default selection can later be based on approved representation/business rules.

Do not leak internal presentation flags simply because they exist.

---

# 30. `is_active`

Never expose:

```text
is_active
```

through public Variant representation.

It controls visibility but is not public state.

---

# 31. `display_order`

Do not expose `display_order` unless the contract explicitly requires it.

The server uses it to determine output order.

Clients should not need internal ranking values.

---

# 32. Availability

Public Variant API exposes:

```text
availability:
available | unavailable
```

according to current V1 conventions.

This is a coarse public signal.

---

# 33. Stock Indicator

Variant responses do not expose `stock_indicator` in Phase 5.4.

The field and its approved public values are deferred to Phase 5.7, which must
define the authoritative product classification and final availability
semantics before the field is added to any Variant response.

Deferred values are:

```text
IN_STOCK
LOW_STOCK
MADE_TO_ORDER
```

No Phase 5.4 client may depend on this deferred field.

---

# 34. Availability Must Be Derived

Do not persist:

```text
availability
```

on ProductVariant merely for API convenience.

Derive them from authoritative state.

---

# 35. Inventory Source

Group C stock remains stored separately in:

```text
product_stocks
```

associated with Variant.

Do not duplicate:

```text
quantity
reserved_quantity
available_quantity
```

onto ProductVariant.

---

# 36. Never Expose Raw Inventory

Public Variant API must not expose:

```text
physical_quantity
quantity
reserved_quantity
available_quantity
warehouse_location
warehouse notes
```

Only coarse public availability.

---

# 37. Shared Availability Resolver

Reuse the same availability logic already used by:

```text
CAT-001
CAT-002
```

Do not create a Variant-only interpretation that can disagree with Product Detail.

---

# 38. Availability Consistency

The same Variant should have identical:

```text
availability
```

when returned through:

```text
CAT-002 embedded variant
CAT-005
CAT-006
```

`stock_indicator` is deferred to Phase 5.7 and is not part of this Phase 5.4
consistency check.

Add regression tests.

---

# 39. Phase 5.7 Dependency

If final availability requires authoritative:

```text
Product.product_type
Product.is_published
```

and those remain deferred to Phase 5.7:

do not fabricate them in Phase 5.4.

Document the dependency.

---

# 40. MADE_TO_ORDER

Do not infer:

```text
zero stock
=
MADE_TO_ORDER
```

MADE_TO_ORDER is a Product business classification.

It must come from the authoritative Product Type once Phase 5.7 provides it.

---

# 41. Product Visibility and Variant Visibility Are Separate

A Variant can be active while its Product is hidden.

That Variant is still not publicly accessible.

Public eligibility requires:

```text
public Product
AND
active Variant
```

plus final Phase 5.7 publication rules.

---

# 42. CAT-005 Pagination

The current endpoint matrix marks CAT-005 pagination as:

```text
Optional
```

Do not silently invent pagination behavior.

Inspect:

```text
api-contract.md
api-resources.md
openapi.yaml
```

for the latest definitive decision.

---

# 43. Preferred Pagination Handling

If the latest docs still leave CAT-005 pagination unspecified:

use the smallest coherent V1 behavior.

Because Product variant counts are normally bounded and the endpoint exists for one Product:

```text
return all public active variants
```

in deterministic order is acceptable if recorded as the clarification.

Do not introduce pagination solely because every collection "should" paginate.

---

# 44. If CAT-005 Pagination Is Defined

If newer docs explicitly define:

```text
page
per_page
```

follow those exact conventions.

Do not invent a third format.

---

# 45. No Search on Variant Collection

Do not add:

```text
?search=
?sku=
?color=
?size=
```

to CAT-005 unless explicitly contracted.

Variant retrieval is scoped to one Product and intended to be small.

---

# 46. No Sorting Parameters

Do not add client-controlled:

```text
?sort=
?sort_direction=
```

to CAT-005 unless already approved.

Server-side `display_order` is the authoritative presentation order.

---

# 47. No Filtering DSL

Do not add:

```text
?attributes[color]=blue
?fabric=linen
?width_gt=100
```

during Phase 5.4.

Advanced variant filtering is not part of this endpoint contract.

---

# 48. No Variant Slug

Do not add:

```text
slug
```

to ProductVariant schema merely for public routing.

Variants are resolved by ID under Product.

---

# 49. No Global SKU Lookup API

Do not create:

```text
GET /variants/by-sku/{sku}
```

SKU remains data, not a new public route.

---

# 50. No Variant Images Endpoint

Do not create:

```text
GET /products/{product}/variants/{variant}/images
```

unless explicitly approved.

Product images remain part of CAT-002 gallery architecture.

Variant-image association in Group C does not automatically imply a new endpoint.

---

# 51. Variant-Associated Images

Group C permits ProductImages to optionally reference a Variant.

Do not automatically add image arrays to CAT-005/CAT-006 unless the frozen Variant contract includes them.

Preserve the relationship for future frontend/media logic.

---

# 52. No Product Detail Duplication

CAT-005 and CAT-006 should not repeat full Product detail.

Standalone Variant already includes:

```text
product_id
```

The frontend can use Product APIs when Product context is needed.

---

# 53. No Category Data

Do not embed Category in Variant API.

The parent Product owns Category.

---

# 54. No Materials/Room/Style Data

Do not embed:

```text
materials
room_tags
style_tags
```

inside Variant.

These belong to Product-level classification.

---

# 55. No Assets

Do not embed Product 3D/AR assets into Variant responses.

---

# 56. No Cart State

Do not add fields such as:

```text
in_cart
cart_quantity
is_selected
```

Variant API is public and user-neutral.

---

# 57. No `is_purchasable` Unless Contracted

Do not add:

```text
is_purchasable
```

to Variant API simply because Cart later needs to know.

Use the exact Variant public contract.

Cart performs its own authoritative validation.

---

# 58. No Reservation

GET Variant endpoints must not:

```text
lock stock
reserve stock
change reserved_quantity
```

Reads are side-effect free.

---

# 59. No Inventory Guarantee

`availability = available` does not guarantee checkout.

Checkout revalidates transactionally.

---

# 60. Caching

CAT-005 and CAT-006 are:

```text
PUBLIC
CACHEABLE
```

Use existing public catalog cache semantics.

---

# 61. No User-Specific Variation

Public Variant response must not vary by:

```text
anonymous
Customer
Staff
Admin
```

when using CAT-005/CAT-006.

Do not leak operational data to authenticated privileged users on public routes.

---

# 62. Rate Limiting

Use the existing moderate:

```text
public-read
```

limiter.

Do not create stricter Variant-specific throttling.

---

# 63. Error — Unknown Product

Unknown Product:

```text
404 RESOURCE_NOT_FOUND
```

according to current public Product semantics.

---

# 64. Error — Hidden Product

Inactive/deleted/future-unpublished Product:

```text
404
```

No Variant information should be returned.

---

# 65. Error — Unknown Variant

Product exists publicly but Variant ID does not exist:

```text
404 RESOURCE_NOT_FOUND
```

---

# 66. Error — Wrong Parent

Variant exists globally but belongs to another Product:

```text
404 RESOURCE_NOT_FOUND
```

This is mandatory masking behavior.

---

# 67. Error — Inactive Variant

Variant exists but:

```text
is_active = false
```

Return:

```text
404
```

on CAT-006.

Exclude from CAT-005.

---

# 68. Avoid Distinguishing 404 Causes

Public response should not reveal whether:

```text
Variant doesn't exist
Variant belongs elsewhere
Variant inactive
Parent Product hidden
```

where masking semantics require the same result.

---

# 69. Explicit Resource Serialization

Use allow-listed serialization.

Do not:

```php
return $variant;
```

or:

```php
return response()->json($variant->toArray());
```

---

# 70. Timestamps

Standalone Variant includes:

```text
created_at
updated_at
```

in standard:

```text
ISO 8601 UTC
```

format.

Embedded CAT-002 summary continues to omit them.

---

# 71. Collection Envelope

CAT-005 must follow the global response envelope.

If unpaginated:

```json
{
  "data": [
    {}
  ]
}
```

according to current collection conventions.

If pagination is explicitly adopted:

use the standard:

```text
meta.pagination
```

shape.

Do not invent a custom `variants` wrapper.

---

# 72. Detail Envelope

CAT-006:

```json
{
  "data": {
  }
}
```

No raw object.

---

# 73. Empty Variant Collection

A publicly visible Product with no active Variants should return:

```json
{
  "data": []
}
```

unless the latest contract states otherwise.

Do not return 404 merely because the Product has zero active variants.

---

# 74. Variant Model Integrity

Do not modify Group C invariants such as:

```text
global SKU uniqueness
one Product parent
one default Variant max per Product
positive physical measurements
money-pair integrity
active state
display_order
```

Phase 5.4 consumes these guarantees.

---

# 75. Default Variant Logic

Do not use `is_default` as an authorization/visibility condition.

A Product may have multiple active Variants and at most one default.

CAT-005 should return all public active Variants.

---

# 76. Product With One Variant

Do not special-case single-Variant Products into a different JSON shape.

Return the same Variant representation.

---

# 77. Product With Many Variants

Do not calculate Product-level price differently inside Variant endpoints.

Each Variant exposes its own authoritative price.

---

# 78. Product With No Variants

CAT-005 should safely return empty data.

CAT-006 for any Variant ID under such Product returns 404.

---

# 79. Query Efficiency

CAT-005 should execute bounded queries.

Avoid:

```text
N queries for N variants
```

when deriving availability.

Use eager loading/aggregates appropriately.

---

# 80. CAT-006 Efficiency

Resolve:

```text
public Product
+
one scoped Variant
+
minimal availability data
```

Do not load full Product gallery/category graph unnecessarily.

---

# 81. Reuse Existing Public Product Resolver

Do not duplicate logic for:

```text
Product slug/ID
public visibility
```

Reuse Phase 5.2/5.3 abstractions.

---

# 82. Reuse Variant Availability Logic

If Phase 5.3 already calculates availability for embedded variants:

reuse it.

CAT-002, CAT-005, CAT-006 should not drift.

---

# 83. Avoid Giant Controller

Controller should be simple.

Conceptually:

```text
resolve public Product
→ list public Variants
→ VariantResource collection
```

and:

```text
resolve public Product
→ resolve active Variant scoped to Product
→ VariantResource
```

---

# 84. Do Not Overengineer

Do not introduce:

```text
VariantRepositoryInterface
VariantQueryBus
VariantGraphService
VariantReadPipelineFramework
```

without genuine need.

Use the simplest maintainable Laravel structure.

---

# 85. Tests — CAT-005 Public Access

Without authentication:

```text
GET /api/v1/products/{product}/variants
→ 200
```

for a public Product.

---

# 86. Tests — Collection Parent by Slug

Resolve Product using slug.

Verify expected Variants.

---

# 87. Tests — Collection Parent by ID

Resolve same Product by Product public ID.

Verify equivalent results.

---

# 88. Tests — Active Variant Inclusion

Create:

```text
active Variant
```

Verify it appears.

---

# 89. Tests — Inactive Variant Exclusion

Create:

```text
active Variant
inactive Variant
```

Only active one appears.

---

# 90. Tests — Variant Collection Ordering

Create Variants with different:

```text
display_order
```

and insertion order.

Verify deterministic ordering.

---

# 91. Tests — Empty Collection

Public Product with no active Variants:

```text
200
data = []
```

---

# 92. Tests — Hidden Product Collection

Inactive/deleted Product:

```text
GET .../variants
→ 404
```

Do not expose Variant collection.

---

# 93. Tests — CAT-006 Public Access

Anonymous request for valid Variant:

```text
200
```

---

# 94. Tests — Variant Detail Parent by Slug

Use Product slug + Variant ID.

Verify correct Variant.

---

# 95. Tests — Variant Detail Parent by ID

Use Product ID + same Variant ID.

Verify same semantics.

---

# 96. Tests — Wrong Product

Create:

```text
Product A
Product B
Variant B1 belongs to Product B
```

Request:

```text
/products/A/variants/B1
```

Expected:

```text
404
```

Mandatory regression test.

---

# 97. Tests — Unknown Variant

Unknown Variant ID under valid Product:

```text
404
```

---

# 98. Tests — Inactive Variant Detail

Known inactive Variant:

```text
404
```

---

# 99. Tests — Standalone Shape

Verify CAT-005/CAT-006 contains exactly the approved public Variant fields.

At minimum:

```text
id
product_id
sku
name
price
availability
created_at
updated_at
```

subject to latest contract.

---

# 100. Tests — Embedded vs Standalone Difference

Verify CAT-002 embedded Variant:

```text
does not contain product_id
does not contain created_at
does not contain updated_at
```

while CAT-006 standalone does.

This prevents serializer drift.

---

# 101. Tests — Product ID Correctness

Standalone Variant `product_id` must match the parent Product API ID.

---

# 102. Tests — Price

Verify exact integer minor-unit amount and currency.

---

# 103. Tests — Cost Price Hidden

Seed:

```text
cost_price_amount
cost_price_currency
```

Assert absence.

---

# 104. Tests — Compare-at Price Hidden

If not contracted, seed compare-at values and assert they remain absent.

---

# 105. Tests — Internal State Hidden

Assert absence of:

```text
is_active
is_default
display_order
cost_price
raw stock
```

unless explicitly public in latest docs.

---

# 106. Tests — Attributes Hidden

If attributes are not part of frozen response:

seed them and assert they do not leak.

---

# 107. Tests — Dimensions Hidden

Seed dimensions.

Assert they are absent if not contracted.

---

# 108. Tests — Raw Inventory Hidden

Seed stock records.

Assert public Variant response does not contain:

```text
quantity
reserved_quantity
available_quantity
warehouse_location
```

---

# 109. Tests — Availability Consistency

For same Variant:

```text
CAT-002 embedded
CAT-005 standalone
CAT-006 detail
```

must produce the same:

```text
availability
```

`stock_indicator` is deferred to Phase 5.7 and is omitted from Phase 5.4
representations and tests.

---

# 110. Tests — Role-Neutral Public Output

If useful with existing helpers, compare CAT-006 output as:

```text
anonymous
Customer
Staff
Admin
```

No operational fields should appear.

---

# 111. Tests — Side Effects

Variant reads must not mutate:

```text
Variant
Product
stock
reserved quantities
timestamps
```

---

# 112. Tests — Query Efficiency

Where practical, add focused protection against obvious N+1 availability queries.

Do not add a performance framework solely for this phase.

---

# 113. Existing CAT-002 Regression

Phase 5.4 must not change CAT-002 embedded Variant structure unexpectedly.

Run Product Detail tests.

---

# 114. Existing Group C Regression

Keep all Group C tests green, especially:

```text
ProductVariantSchemaTest
ProductStockSchemaTest
ProductImageSchemaTest
SchemaIntegrityTest
```

or their current equivalents.

---

# 115. No Variant Mutation API

Do not implement:

```text
POST /products/{product}/variants
PATCH /products/{product}/variants/{variant}
DELETE /products/{product}/variants/{variant}
```

in Phase 5.4.

CAT-010/admin management belongs later.

---

# 116. No Admin Variant Representation

Do not expose:

```text
cost price
raw stock
is_active
is_default
display_order
attributes
internal dimensions
```

through public API merely because future Admin needs them.

Operational/admin APIs are separate.

---

# 117. No Inventory API

Do not implement:

```text
INV-001
INV-002
INV-003
```

here.

Variant availability is public informational projection only.

---

# 118. No Cart Logic

Do not implement:

```text
add Variant to cart
validate cart quantity
reserve inventory
```

inside Variant API.

---

# 119. No Checkout Logic

Do not perform transactional stock validation here.

Checkout owns final inventory validation.

---

# 120. No Frontend Work

Do not modify:

```text
frontend/web/
frontend/app/
frontend/design-system/
```

No Variant selector UI belongs in this phase.

---

# 121. No Schema Changes Expected

Expected:

```text
Schema changes:
NONE
```

The Group C ProductVariant schema already supports these endpoints.

---

# 122. Stop on Unexpected Schema Requirement

If the agent concludes that CAT-005/CAT-006 require a new Variant column:

first verify whether the requested field is actually part of the frozen public contract.

Do not silently alter Group C.

---

# 123. Phase 5.7 Fields

Do not add:

```text
Product.product_type
Product.is_published
```

during Phase 5.4.

Continue documenting their effect on final availability/public visibility as a Phase 5.7 dependency.

---

# 124. Migration Safety

If a genuine unrelated defect requires a migration:

* use a new migration;
* never edit historical Group C migrations;
* run destructive verification only against a non-production disposable isolated DB;
* check `APP_ENV`;
* check the exact database name;
* use explicit `--force` only under the documented safeguards.

---

# 125. New Dependencies

Expected:

```text
NONE
```

Laravel/Eloquent/API Resources are sufficient.

---

# 126. OpenAPI

Update `docs/api/openapi.yaml` only to align it with the actual approved CAT-005/CAT-006 behavior.

Verify:

```text
paths
parent Product parameter
Variant ID parameter
public security
response schema
404 behavior
collection envelope
```

---

# 127. Resolve Optional Pagination Documentation

Because the current master contract marks CAT-005 pagination as:

```text
Optional
```

this phase must eliminate ambiguity.

After reviewing the latest docs:

either document:

```text
CAT-005 is intentionally unpaginated because variants are bounded per Product
```

or implement the already-approved pagination rule if one exists.

Do not leave runtime behavior undocumented.

---

# 128. Recommended V1 Clarification

If there is still no existing authoritative pagination decision, prefer:

```text
CAT-005:
unpaginated
deterministically ordered
bounded to one Product
```

This keeps the endpoint simple and avoids unnecessary pagination UX for small Variant sets.

Record this as an API clarification, not a new business feature.

The implemented CAT-005 behavior is unpaginated. It returns all active
variants for the publicly visible parent Product in `display_order ASC, id ASC`
order and uses the standard `{data: [...]}` collection envelope.

---

# 129. Code Quality

Maintain:

```text
cognitive complexity <= 15
<= 3 returns where practical
minimal comments
explicit resources
no duplicated availability logic
no magic route/status strings where shared domain constants exist
```

---

# 130. Expected Files

Likely areas:

```text
app/Http/Controllers/
app/Http/Resources/
app/Services/ or app/Queries/
routes/api.php
tests/Feature/
docs/api/
docs/decisions.md
```

Modify only files needed for CAT-005/CAT-006.

---

# 131. Verification

Run focused Variant API tests first.

Then run:

```bash
php artisan test
vendor/bin/pint --test
vendor/bin/phpstan analyse
composer audit
git diff --check
```

No destructive migration command should normally be needed.

---

# 132. Route Review

Verify exactly:

```text
GET /api/v1/products/{product}/variants
GET /api/v1/products/{product}/variants/{variant}
```

and no global:

```text
GET /api/v1/variants
```

route exists.

---

# 133. Completion Report

Return:

## Phase 5.4 status

```text
PASS
```

or:

```text
BLOCKED
```

## CAT-005

Report:

```text
public access
parent resolution
active filtering
ordering
pagination decision
response shape
```

## CAT-006

Report:

```text
parent-child scoped lookup
wrong-parent masking
inactive masking
response shape
```

## Variant pricing

State integer minor-unit source.

## Availability

State reused availability derivation and any Phase 5.7 dependency.

## Data protection

Confirm absence of:

```text
cost price
raw stock
internal flags
uncontracted dimensions
uncontracted attributes
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

# 134. Definition of Done

Phase 5.4 is complete when:

* CAT-005 exists;
* CAT-006 exists;
* both are public;
* Product slug parent resolution works;
* Product ID parent resolution works;
* hidden Products expose no Variants;
* only active Variants are listed publicly;
* CAT-005 ordering is deterministic;
* CAT-005 pagination behavior is explicitly resolved/documented;
* CAT-006 resolves Variant only within the Product relationship;
* wrong-parent Variant returns masked 404;
* unknown Variant returns 404;
* inactive Variant returns 404;
* standalone Variant serialization matches the frozen contract;
* embedded CAT-002 Variant serialization remains smaller;
* Variant name maps correctly from `variant_name`;
* Variant SKU is authoritative;
* Variant price uses integer minor units;
* cost price does not leak;
* raw inventory does not leak;
* internal Variant state does not leak;
* uncontracted attributes/dimensions do not leak;
* availability is consistent across CAT-002/CAT-005/CAT-006;
* GET requests have no side effects;
* public responses do not vary by authenticated role;
* no global Variant route is introduced;
* no Variant mutation API is introduced;
* no inventory/cart/checkout behavior is pulled forward;
* no Group C schema redesign occurs;
* no frontend code is changed;
* existing Product Detail tests stay green;
* Group C Variant/Inventory tests stay green;
* focused Variant tests pass;
* full backend suite passes;
* Pint passes;
* PHPStan passes;
* Composer audit has no blocker.

---

# 135. Out of Scope

Do not implement:

```text
Variant create/update/delete
CAT-010 management
global Variant search
Variant slug routing
Variant-specific filter DSL
Variant image endpoint
inventory management
cart mutation
checkout
frontend variant selector
product_type schema
is_published schema
```

---

# 136. STOP Condition

STOP when:

```text
GET /api/v1/products/{product}/variants
```

and:

```text
GET /api/v1/products/{product}/variants/{variant}
```

provide safe, deterministic, public Variant reads strictly scoped to the existing ProductVariant → Product relationship and without exposing internal inventory or financial data.

Do not continue automatically to Phase 5.5.

DO NOT COMMIT, STAGE OR PUSH.

The project owner handles Git operations.
