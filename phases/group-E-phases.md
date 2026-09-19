# Phase 5.1 — Categories Read API

## Purpose

Implement the public V1 Category Read API using the existing Group C category hierarchy and the frozen catalog contract.

This phase implements:

```text
CAT-003
GET /api/v1/categories
```

and:

```text
CAT-004
GET /api/v1/categories/{category}
```

The objective is to provide a:

```text
fast
public
deterministic
SEO-compatible
cache-friendly
frontend-neutral
```

category read surface for future Next.js and Flutter clients.

Do not redesign the taxonomy.

Do not implement category management.

Do not implement product listing/filtering yet.

---

# 1. Read Latest Authoritative Docs First

Before modifying code, read the current repository versions of:

```text
AGENTS.md
docs/VISION.md
docs/api/api-contract.md
docs/api/api-resources.md
docs/api/api-conventions.md
docs/api/openapi.yaml
docs/domain/business-rules.md
docs/decisions.md
phases/group-D-phases.md
```

Also inspect the existing Group C category implementation:

```text
app/Models/Category.php
database/migrations/*categories*
database/seeders/CategorySeeder.php
tests/Feature/Category*
```

The current repository documentation is authoritative.

Do not use older phase notes to overwrite newer decisions.

---

# 2. Existing V1 API Contract Is Frozen

Implement the existing approved endpoints:

```text
CAT-003
GET /api/v1/categories
```

and:

```text
CAT-004
GET /api/v1/categories/{category}
```

Do not introduce alternative paths.

Do not add:

```text
GET /api/v1/category
GET /api/v1/category-tree
GET /api/v1/navigation/categories
GET /api/v1/categories/{category}/products
```

The last route is explicitly rejected in V1.

Product retrieval by category remains:

```text
GET /api/v1/products?category={category}
```

and belongs to the Product Read API phase.

---

# 3. Authentication

Both category read endpoints are:

```text
PUBLIC_READ
```

They must require:

```text
NO Clerk session
NO Laravel authentication
NO CUSTOMER role
```

Anonymous clients must be able to retrieve public categories.

Do not place authentication middleware on these routes.

---

# 4. Frontend Independence

The same API must later support:

```text
Next.js website
Flutter application
Admin application where public representation is sufficient
```

Do not create:

```text
/web/categories
/mobile/categories
```

or client-specific payloads.

---

# 5. Existing Category Data Model

Preserve the existing adjacency-list hierarchy:

```text
categories
├── id
├── parent_id
├── name
├── slug
├── space_type
├── display_order
├── is_active
├── created_at
└── updated_at
```

Use the actual current schema.

Do not replace it with:

```text
nested-set
closure table
materialized path
graph database
```

V1 deliberately uses the existing adjacency-list model.

---

# 6. Existing Hierarchy

The taxonomy already has:

```text
Furnitures Root
    ↓
room / department
    ↓
family
    ↓
specific furniture type
```

The existing intended depth is bounded.

Do not redesign or flatten the database hierarchy in Phase 5.1.

---

# 7. Preserve Existing Taxonomy

Do not replace the current seeded taxonomy simply because a different category list appears aesthetically preferable.

The current `CategorySeeder` is authoritative reference data unless the latest docs explicitly approve a taxonomy change.

Phase 5.1 is primarily a **read implementation**, not a taxonomy redesign.

---

# 8. Customer-Facing Taxonomy Review

Before implementation, inspect the existing 76-category seeded taxonomy and identify:

```text
root container
public top-level categories
descendant categories
inactive categories
display_order
space_type
```

Confirm it still matches the currently approved customer navigation plan.

If the current docs explicitly changed the taxonomy after Group C:

update the seeder/reference data only according to that documented decision.

Do not invent categories in code.

---

# 9. Root Container Is Structural

`Furnitures Root` is a structural taxonomy container.

It must not appear to customers as a normal shopping category unless the current API documentation explicitly says otherwise.

Customer-facing results should begin from the actual storefront category level.

Do not expose:

```text
Furnitures Root
```

as a visible navigation tile merely because it exists in the database.

---

# 10. CAT-003 Contract

Implement:

```http
GET /api/v1/categories
```

according to the frozen V1 contract.

It is a paginated public Category Summary collection.

Required response structure:

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

Do not return a raw JSON array.

---

# 11. Category Summary Representation

Each `CAT-003` item must contain only the currently contracted public summary fields:

```text
id
name
slug
image
```

Conceptually:

```json
{
  "id": "cat_...",
  "name": "Living Room",
  "slug": "living-room",
  "image": {
    "url": "https://..."
  }
}
```

Do not mass-serialize the Category model.

---

# 12. Do Not Add Unapproved Fields

Do not silently add:

```text
parent_id
space_type
display_order
is_active
updated_at
children
products
product_count
recommendations
filter_attributes
meta_title
meta_description
```

to the public response merely because they exist or may be useful later.

Adding API fields is a contract decision.

Implement the currently approved representation.

---

# 13. Hierarchical API Expansion Is Deferred

The database is hierarchical.

However, the current `CAT-003` wire contract is a paginated summary collection, not a recursive:

```json
{
  "children": [...]
}
```

tree.

Therefore Phase 5.1 must not silently convert `CAT-003` into a nested tree.

If the project later decides that Next.js/Flutter navigation requires recursive hierarchy directly from the API, make that an explicit additive API-contract decision.

---

# 14. Resolve Collection Scope Explicitly

Because the database contains:

```text
Furnitures Root
+
multiple hierarchy levels
```

while `CAT-003` is intended for public category navigation/menu generation, inspect the latest authoritative contract and current seed behavior to determine which hierarchy level the collection represents.

Do not guess.

Preferred interpretation, if the docs remain silent and examples reflect room-level navigation:

```text
CAT-003
→ active customer-facing top-level categories beneath Furnitures Root
```

rather than all 76 nodes.

Document the implemented interpretation in the existing decision/API documentation.

Do not expose a flat 76-node list without `parent_id`, because clients could not reconstruct the hierarchy reliably.

---

# 15. If Contract Clarification Is Necessary

If the current docs do not explicitly state whether `CAT-003` returns:

```text
all active category nodes
```

or:

```text
active storefront top-level categories
```

resolve the inconsistency minimally during this phase.

Preferred V1 behavior:

```text
CAT-003
=
active storefront top-level categories
```

because its documented purpose is:

```text
public category navigation
menu generation
category landing pages
```

and its summary representation does not expose hierarchy relationships.

Record this as a clarification, not a new endpoint.

---

# 16. Deterministic Ordering

Category navigation order must be deterministic.

Use:

```text
display_order ASC
```

for sibling/top-level ordering.

Add:

```text
id ASC
```

as a deterministic tie-breaker where useful.

Do not order categories randomly.

Do not rely on insertion order.

---

# 17. Public Active Scope

`CAT-003` must return only:

```text
is_active = true
```

categories.

Inactive categories are internal catalog state.

Do not expose them through the public collection.

---

# 18. Parent Visibility

A public child must not become accidentally discoverable through navigation if its required public hierarchy is inactive.

Review the current hierarchy semantics.

If a category's public visibility depends on its ancestor chain, enforce the smallest consistent rule.

Do not build a complicated category publishing engine.

---

# 19. Category Detail

Implement:

```http
GET /api/v1/categories/{category}
```

where `{category}` may be:

```text
slug
or
stable machine id
```

per the approved dual-resolution contract.

---

# 20. Slug Resolution

Slug is the canonical SEO identifier.

Example:

```text
living-room
```

It is globally unique and already validated as kebab-case.

Resolve safely through an indexed query.

Do not use fuzzy matching.

Do not make slug matching case-insensitive if that contradicts the canonical stored contract.

---

# 21. ID Resolution

The detail endpoint must also accept the stable category machine ID.

Use exact matching.

Do not interpret arbitrary input as SQL column names or dynamic query fragments.

---

# 22. Resolution Order

Implement one clear resolver.

Conceptually:

```text
identifier
    ↓
match category id OR slug
    ↓
require public-active visibility
    ↓
return resource
```

Do not duplicate slug/ID resolution logic across multiple controllers.

---

# 23. Inactive Category Masking

If the category:

```text
does not exist
```

or:

```text
exists but is inactive
```

return the same public result:

```text
404 RESOURCE_NOT_FOUND
```

Do not reveal:

```text
CATEGORY_EXISTS_BUT_IS_INACTIVE
```

to public callers.

---

# 24. Root Container Detail

The structural root should not become customer-visible through direct slug/ID lookup if it is not part of the public category contract.

Treat it according to the same public visibility rules.

Do not expose internal taxonomy scaffolding merely because the identifier is known.

---

# 25. Category Detail Representation

Implement exactly the current full Category Detail representation:

```text
id
name
slug
description
image
created_at
```

Conceptually:

```json
{
  "data": {
    "id": "cat_...",
    "name": "Living Room",
    "slug": "living-room",
    "description": "...",
    "image": {
      "url": "https://..."
    },
    "created_at": "2026-08-20T08:00:00Z"
  }
}
```

Do not expose internal category fields.

---

# 26. Existing Schema / Contract Gap

The Group C category schema historically contained:

```text
id
parent_id
name
slug
space_type
display_order
is_active
timestamps
```

while the frozen public API contract requires:

```text
description
image
```

for category reads.

Before implementation:

inspect the current migration/model carefully.

Do not assume those fields now exist.

---

# 27. Resolve `description` Minimally

If `description` is still absent from the schema:

add it through a new migration because it is already required by the frozen Category Detail contract.

Preferred:

```text
description
nullable text
```

unless the latest schema docs define another exact type/nullability.

Do not edit the original Group C migration.

---

# 28. Resolve Category Image Properly

Inspect whether the current project already models category images through:

```text
category column
media table
asset relation
existing image abstraction
```

Use that existing architecture if present.

Do not invent a second media subsystem.

---

# 29. Avoid Premature Category Media Architecture

If there is currently no category-image persistence mechanism but the API contract requires `image`:

implement the smallest production-safe solution consistent with existing project media conventions.

Do not build:

```text
category image gallery
responsive-image CMS
asset transformation service
multiple breakpoints table
```

in Phase 5.1.

V1 needs one public category image representation.

---

# 30. Image Nullability

Follow the latest API-resource/nullability contract.

If a category may legitimately have no image, return the documented null representation consistently.

Do not alternate among:

```json
"image": null
```

and:

```json
"image": {}
```

and field omission.

Use one frozen representation.

---

# 31. Explicit Resource Classes

Create or use explicit Laravel API Resource classes.

Likely separation:

```text
CategorySummaryResource
CategoryDetailResource
```

or equivalent.

Do not return:

```php
return response()->json($category);
```

Do not use unrestricted:

```text
Model::toArray()
```

for public serialization.

---

# 32. Summary vs Detail Must Stay Distinct

Collection:

```text
Category Summary
```

Detail:

```text
Category Detail
```

Do not make the collection payload as heavy as the detail payload.

Future navigation should remain lightweight.

---

# 33. Pagination

`CAT-003` uses:

```text
page
per_page
```

according to standard V1 pagination.

Current convention:

```text
page >= 1
per_page 1..100
default per_page = 20
```

Use the current authoritative values.

---

# 34. Pagination Metadata

Return:

```text
current_page
per_page
total
last_page
has_next
has_previous
```

inside:

```text
meta.pagination
```

No alternate shape.

---

# 35. Beyond Last Page

Follow V1 pagination behavior:

```text
page beyond last page
→ data: []
→ valid pagination metadata
```

Do not return 404 simply because a page has no results.

---

# 36. Query Parameters

Do not add arbitrary filtering/search parameters to `CAT-003` unless the current contract defines them.

Do not prematurely introduce:

```text
?parent=
?featured=
?space_type=
?depth=
?include=children
?tree=true
```

during this phase.

Keep the public endpoint stable and simple.

---

# 37. GET Request Body

Do not accept a request body for:

```text
CAT-003
CAT-004
```

GET input comes only through path/query according to the contract.

---

# 38. Cacheability

Both endpoints are:

```text
PUBLIC
CACHEABLE
```

Implement cache-safe response headers according to current API conventions.

Do not use:

```text
private
no-store
```

for public category responses unless latest docs explicitly require it.

---

# 39. Do Not Add Application Cache Prematurely

HTTP/cache semantics do not automatically require:

```text
Redis response cache
database cache table
manual cache repository
```

If the current backend already has a response caching mechanism, integrate appropriately.

Otherwise, return cache-friendly headers and let later infrastructure/CDN work handle shared caching.

Avoid premature cache invalidation complexity.

---

# 40. Response Determinism

For identical public state and query:

```text
same request
→ stable ordering
→ stable response shape
```

This helps:

```text
Next.js SSR
CDN caching
Flutter model parsing
tests
```

---

# 41. Category Product Counts

Do **not** add `product_count` in Phase 5.1 unless the latest frozen contract explicitly adds it.

The current V1 Category Summary/Detail representation does not require it.

Product count semantics require decisions about:

```text
active products
published products
descendant categories
made-to-order products
```

and should not be invented casually.

---

# 42. `is_featured`

Do not add:

```text
is_featured
```

merely because it could help a homepage.

The current Group C schema uses:

```text
display_order
is_active
```

and the public contract does not currently expose `is_featured`.

If homepage merchandising later requires it, address it in the appropriate catalog merchandising phase.

---

# 43. SEO Metadata

Do not add:

```text
meta_title
meta_description
metadata JSON
```

in Phase 5.1 unless already approved in current docs.

The current detail representation already provides:

```text
name
description
slug
image
```

which gives the future Next.js frontend a baseline for category page metadata.

Avoid expanding schema before actual SEO requirements demand it.

---

# 44. Filter Attributes

Do not add:

```text
filter_attributes
```

to Category.

Product filtering belongs to the Product Read/filter architecture.

Avoid creating a second source of truth for:

```text
material
dimensions
color
fabric
seating_capacity
```

---

# 45. Recommendations

The database already has:

```text
category_recommendations
```

for future cross-sell/navigation assistance.

Do not expose those recommendations in `CAT-003` or `CAT-004` unless current API docs explicitly require them.

Recommendation API behavior belongs to a later phase.

---

# 46. `space_type`

Keep:

```text
home
office
hybrid
```

as internal taxonomy/domain data unless current API contract explicitly exposes it.

Do not leak it simply because the column exists.

---

# 47. Public Data Minimization

Never expose:

```text
parent_id
internal taxonomy IDs beyond public id
is_active
space_type
internal notes
pivot recommendation data
DB-specific fields
```

unless contracted.

Use explicit allow-lists.

---

# 48. Category Model

Reuse existing relationships such as:

```text
parent
children
recommendedCategories
```

where needed internally.

Do not rewrite `Category` into a new taxonomy service.

---

# 49. Cycle Protection

Do not modify existing no-cycle guarantees unless implementation requires a bug fix.

The read API should rely on Group C hierarchy integrity.

Phase 5.1 is not a category reparenting phase.

---

# 50. Category Writes Are Out of Scope

Do not implement:

```text
POST /api/v1/categories
PATCH /api/v1/categories/{category}
DELETE /api/v1/categories/{category}
```

in Phase 5.1.

`CAT-011` and `CAT-012` belong to later administrative catalog management.

---

# 51. Do Not Add Delete Endpoint

There is no approved public/admin category DELETE endpoint in this phase.

Do not invent one.

---

# 52. Controller Design

Use a small controller.

Conceptually:

```text
CategoryController@index
CategoryController@show
```

or the existing project naming convention.

Controller responsibilities:

```text
accept validated request/query
call query/application layer if one exists
return resource
```

Do not put complex taxonomy transformation logic in the controller.

---

# 53. Avoid Overengineering Query Layer

If the query is simple enough:

```text
active top-level categories
ordered
paginated
```

a clean Eloquent query in a focused class/controller is acceptable.

Do not create:

```text
CategoryQueryBus
CategoryReadRepositoryInterface
CategoryTreeEngine
CategoryNavigationOrchestrator
```

without actual need.

Follow existing backend architecture.

---

# 54. Query Efficiency

`CAT-003` should execute an efficient bounded query.

Avoid:

```text
N+1 queries
loading all 76 categories and paginating in PHP
recursive traversal when response does not need recursion
```

Use database filtering, ordering, and pagination.

---

# 55. CAT-004 Efficiency

Detail lookup should use indexed:

```text
id
slug
```

resolution.

Avoid loading the entire category tree to locate one category.

---

# 56. IDs

Use the existing public API ID transformation/convention.

Do not expose raw database integer IDs if the project already transforms them into stable opaque API IDs.

Inspect current resource conventions before implementing.

---

# 57. Timestamp

`created_at` in Category Detail must use the standard V1 timestamp representation:

```text
ISO 8601 UTC
```

according to existing API conventions.

Do not use locale-formatted strings.

---

# 58. Error Contract

Category errors must use the global standard API error envelope.

Do not return:

```json
{
  "message": "Category not found"
}
```

as an ad hoc response.

Use the existing exception/error infrastructure.

---

# 59. Not Found

Unknown category:

```text
404 RESOURCE_NOT_FOUND
```

Inactive category:

```text
404 RESOURCE_NOT_FOUND
```

Structural non-public category:

```text
404 RESOURCE_NOT_FOUND
```

where applicable.

Public callers should not learn internal taxonomy state.

---

# 60. Invalid Pagination

Invalid:

```text
page
per_page
```

must follow the current V1 validation/error contract.

Use existing pagination request validation helpers if they exist.

Do not invent alternate errors.

---

# 61. Rate Limiting

Apply the existing:

```text
public-read
```

rate-limiter category from Phase 4.11 if that is how current routes are organized.

Do not add a category-specific aggressive limiter.

Normal storefront navigation must not easily hit 429.

---

# 62. Public Catalog Performance

Category browsing is high-read, low-risk.

Favor:

```text
simple query
small payload
deterministic ordering
cache-friendly response
```

over complex abstraction.

---

# 63. No Clerk Calls

These endpoints are public.

Do not invoke:

```text
Clerk
AuthenticateClerk
LocalUserProvisioner
```

to serve public category reads.

---

# 64. No RBAC

Do not require:

```text
CUSTOMER
STAFF
ADMIN
```

for public read endpoints.

Role logic belongs only to administrative category mutations later.

---

# 65. Category Seeder

Review `CategorySeeder` to ensure it remains:

```text
deterministic
idempotent
production-safe reference data
```

Do not move taxonomy creation into controllers/services.

---

# 66. Taxonomy Seed Changes

If the approved taxonomy genuinely needs adjustment:

modify the reference seeder carefully.

Do not delete/recreate categories casually where stable slugs/IDs may already be referenced.

Preserve compatibility.

---

# 67. Stable Slugs

Slugs are public URL contracts.

Do not casually rename existing slugs.

Changing:

```text
dining-room
```

to:

```text
dining-room-kitchen
```

may affect:

```text
SEO
bookmarks
frontend routes
external links
product filters
```

Any such change must be an explicit decision.

---

# 68. Display Labels vs Slugs

Human-readable names may evolve independently from stable slugs when appropriate.

Example:

```text
name:
Dining Room & Kitchen

slug:
dining-room
```

is acceptable if intentionally documented.

Do not force slug churn merely to mirror display text.

---

# 69. Product Relationships

Do not implement category product retrieval in this phase.

Future Product Read API owns:

```text
GET /api/v1/products?category={slug|id}
```

Phase 5.1 only ensures categories can be discovered and resolved.

---

# 70. Descendant Product Semantics Deferred

Do not decide here whether:

```text
?category=living-room
```

includes products belonging to descendant categories.

That belongs to Product Read/filter semantics.

Record it for the appropriate product phase if not already frozen.

---

# 71. Category Image Seeding

If category image persistence is added because the contract requires it:

seed deterministic safe development/reference image values only where project conventions allow.

Do not seed:

```text
temporary signed URLs
local developer filesystem paths
real private assets
```

---

# 72. Image URL Safety

Category image serialization must expose only a valid public-safe URL.

Do not expose:

```text
storage disk path
bucket secret
provider credential
internal file ID
```

unless explicitly part of public contract.

---

# 73. Tests — Public Access

Test:

```text
GET /api/v1/categories
```

without authentication.

Expected:

```text
200
```

No Clerk token needed.

---

# 74. Tests — Collection Envelope

Assert:

```text
data
meta.pagination
```

exist.

Do not only assert HTTP 200.

---

# 75. Tests — Summary Fields

Each category summary should contain exactly the approved public structure.

At minimum verify:

```text
id
name
slug
image
```

and verify sensitive/internal fields are absent.

---

# 76. Tests — Inactive Exclusion

Seed:

```text
active category
inactive category
```

Verify only the active customer-visible category appears.

---

# 77. Tests — Structural Root Exclusion

If `Furnitures Root` is non-public structural data:

explicitly test that it does not appear in `CAT-003`.

---

# 78. Tests — Collection Scope

If Phase 5.1 clarifies `CAT-003` as storefront top-level categories:

seed:

```text
root
top-level category
child
grandchild
```

and assert only intended navigation-level rows appear in the collection.

This test is mandatory if the contract ambiguity is resolved this way.

---

# 79. Tests — Deterministic Order

Create sibling categories with different:

```text
display_order
```

Assert response ordering.

Also cover tie-breaking if implementation adds `id ASC`.

---

# 80. Tests — Pagination

Cover:

```text
default page
custom per_page
second page
page beyond last page
```

according to the current pagination contract.

---

# 81. Tests — Pagination Validation

Cover invalid:

```text
page = 0
per_page = 0
per_page > 100
non-integer values
```

using current canonical validation errors.

---

# 82. Tests — Detail by Slug

Request:

```text
GET /api/v1/categories/living-room
```

or a deterministic test slug.

Verify the correct category is returned.

---

# 83. Tests — Detail by ID

Retrieve the same category using its public machine ID.

Verify the resource represents the same category.

---

# 84. Tests — Unknown Detail

Unknown slug/id:

```text
404 RESOURCE_NOT_FOUND
```

---

# 85. Tests — Inactive Detail

Known but inactive category:

```text
404 RESOURCE_NOT_FOUND
```

Do not leak existence.

---

# 86. Tests — Root Detail

If root is internal:

direct request to root slug/id should receive the approved non-public result, normally:

```text
404 RESOURCE_NOT_FOUND
```

---

# 87. Tests — Detail Shape

Verify detail contains:

```text
id
name
slug
description
image
created_at
```

and excludes:

```text
parent_id
space_type
display_order
is_active
updated_at
recommendations
```

unless the current frozen contract says otherwise.

---

# 88. Tests — No Authentication Regression

Explicitly verify anonymous access remains successful even if an invalid/unrelated Clerk setup exists.

Public catalog should not depend on authentication infrastructure.

---

# 89. Tests — Cache Headers

Verify public category responses use the project's approved public/cacheable semantics.

Do not assert arbitrary cache durations if the conventions do not freeze them.

---

# 90. Tests — Query Count / N+1

If practical within existing testing conventions, ensure collection does not produce N+1 queries for images or related public data.

Do not build a heavy performance-testing framework.

A focused regression is sufficient where relevant.

---

# 91. Tests — Seeder Stability

If taxonomy/reference seeding is changed:

rerun existing category seed tests.

Verify:

```text
idempotent reseed
stable category count
stable slugs
stable hierarchy
stable display order
```

according to current reference data.

---

# 92. Existing Category Tests Must Stay Green

Do not regress:

```text
CategorySchemaTest
CategoryHierarchyTest
CategoryRecommendationTest
CategorySeedTest
CategoryConcurrentReparentTest
```

or their current equivalents.

---

# 93. No Frontend Work

Do not modify:

```text
frontend/web/
frontend/app/
frontend/design-system/
```

Phase 5.1 provides the API only.

Frontend category navigation belongs to later frontend groups.

---

# 94. No Category UI

Do not implement:

```text
navigation menu
mega menu
category cards
sidebar
breadcrumbs UI
```

during this phase.

---

# 95. No Product Read API Yet

Do not implement:

```text
CAT-001
CAT-002
CAT-005
CAT-006
```

unless the current roadmap specifically groups them with this phase.

This instruction treats Phase 5.1 as Category Read API only.

---

# 96. No Filter Engine

Do not implement:

```text
search
material filters
price filters
availability filters
sorting products
```

here.

Those belong to Product Read API phases.

---

# 97. No Category Admin Mutations

Do not implement:

```text
CAT-011
CAT-012
```

yet.

Keep public read and administrative write concerns separate.

---

# 98. OpenAPI

Update `docs/api/openapi.yaml` only to make it accurately match the implemented, already-approved `CAT-003` and `CAT-004` contract.

Do not redesign the API.

Verify:

```text
paths
parameters
public security
response schema
pagination
404
```

match runtime behavior.

---

# 99. API Contract Documentation

If this phase resolves the collection-scope ambiguity:

update existing consolidated docs to state exactly what `CAT-003` lists.

For example:

```text
CAT-003 lists active storefront top-level categories directly beneath the structural Furnitures Root.
```

Only use that wording if it matches the implementation decision.

---

# 100. Decisions Documentation

Record only genuinely new clarifications, such as:

```text
root container is not public
CAT-003 public collection scope
description/image schema reconciliation
```

Do not duplicate the entire Phase 5.1 document into `decisions.md`.

---

# 101. Migration Safety

If this phase requires a new migration for contract-required Category fields:

do not run destructive migration commands against an ordinary development, staging, or production database.

Any:

```text
php artisan migrate:fresh --seed --force
```

verification must use:

```text
non-production environment
+
disposable isolated database
+
explicit safety guard
```

according to the updated project documentation.

---

# 102. Destructive SQLite Guard

Follow the project's documented guard pattern before destructive verification.

Conceptually:

```bash
test "${APP_ENV:-}" != "production"

test "${DB_DATABASE:-}" = ":memory:" \
  -o "${DB_DATABASE:-}" = "furnitureapp_test_disposable"

php artisan migrate:fresh --seed --force
```

Use the current canonical repository command/guard.

---

# 103. MySQL Destructive Verification

If MySQL migration verification is required:

create a uniquely named disposable database first.

Never point `migrate:fresh` at:

```text
development application DB
staging DB
production DB
```

Verify both:

```text
APP_ENV
DB_DATABASE
```

before running destructive commands.

Environment name alone is insufficient protection.

---

# 104. Schema Changes

Expected:

```text
NONE
```

if current schema already supports the frozen contract.

Potential justified exception:

```text
description/image storage required by existing CAT-004/CAT-003 contract
```

If required:

* add a new migration;
* preserve historical Group C migrations;
* keep scope minimal;
* document the reconciliation.

---

# 105. Do Not Add `is_featured` Opportunistically

Even if a migration is needed for description/image:

do not bundle unrelated fields such as:

```text
is_featured
meta_title
meta_description
filter_attributes
```

into it.

One phase, one need.

---

# 106. Code Quality

Follow project standards:

```text
cognitive complexity ≤ 15
≤ 3 returns where practical
explicit resources
small controller/query methods
no magic strings
no duplicate query logic
```

Do not overabstract.

---

# 107. Likely Implementation Areas

Depending on current repository state:

```text
app/Http/Controllers/
app/Http/Resources/
app/Http/Requests/
app/Models/Category.php
routes/api.php
database/migrations/
database/seeders/CategorySeeder.php
tests/Feature/
docs/
```

Modify only what is necessary.

---

# 108. No New Dependency Expected

Expected:

```text
new Composer packages:
NONE
```

Laravel/Eloquent/API Resources are sufficient.

---

# 109. Security Review

Before completion verify:

```text
public endpoint requires no auth
inactive categories hidden
internal fields absent
structural root hidden where required
no mass model serialization
no arbitrary SQL query parameters
slug/id resolution safe
errors reveal no hidden state
```

---

# 110. Performance Review

Verify:

```text
database pagination
indexed slug lookup
indexed/primary ID lookup
deterministic display_order
no load-all-and-filter-in-PHP
no unnecessary recursive traversal
```

Keep it simple.

---

# 111. Verification Commands

Run relevant focused tests first.

Then run the full backend verification.

At minimum:

```bash
php artisan test
vendor/bin/pint --test
vendor/bin/phpstan analyse
composer audit
git diff --check
```

If a schema migration was added, run fresh migration/seed verification only against the approved disposable database using the documented safety guard.

---

# 112. Route Verification

Inspect:

```bash
php artisan route:list
```

Verify exactly:

```text
GET /api/v1/categories
GET /api/v1/categories/{category}
```

for this public category read surface.

Do not accidentally add duplicate category routes.

---

# 113. Completion Report

Return:

## Phase 5.1 status

```text
PASS
```

or:

```text
BLOCKED
```

## Contract implemented

Confirm:

```text
CAT-003
CAT-004
```

## Collection scope

State exactly which hierarchy level/categories `CAT-003` returns.

## Serialization

State Category Summary and Category Detail fields.

## Visibility

Confirm inactive/internal/root behavior.

## Ordering

State deterministic ordering.

## Schema changes

State:

```text
NONE
```

or explain the exact contract-gap migration.

## Taxonomy changes

State:

```text
NONE
```

unless explicitly required by current authoritative docs.

## Frontend changes

Must state:

```text
NONE
```

## Tests

Exact focused and full-suite results.

## Static checks

Report:

```text
Pint
PHPStan
Composer audit
git diff --check
```

## Migration safety

If destructive verification was used, state only that it ran against a disposable isolated test database.

Do not expose credentials.

---

# 114. Definition of Done

Phase 5.1 is complete when:

* `CAT-003` is implemented;
* `CAT-004` is implemented;
* both endpoints are public;
* no authentication is required;
* public results include only active/customer-visible categories;
* structural taxonomy containers are not exposed as shopping categories;
* `CAT-003` collection scope is explicitly defined rather than ambiguous;
* category collection ordering is deterministic;
* collection pagination follows V1 conventions;
* category summary shape matches the frozen contract;
* detail resolves by slug or ID;
* inactive/non-public detail is masked as 404;
* Category Detail shape matches the frozen contract;
* no internal Category fields leak;
* no recursive category payload was silently introduced;
* no duplicate `/categories/{category}/products` route exists;
* no product/filter implementation was pulled forward;
* no category admin mutation was implemented;
* any existing schema/contract mismatch for `description`/`image` was resolved minimally;
* public caching semantics are correct;
* API/OpenAPI/docs agree with runtime behavior;
* existing Category hierarchy/seed/cycle tests remain green;
* focused CAT-003/CAT-004 tests pass;
* full backend suite passes;
* Pint passes;
* PHPStan passes;
* Composer audit has no blocker;
* no frontend code was changed.

---

# 115. Out of Scope

Do not implement:

```text
product collection API
product detail API
product search
product filters
product sorting
product counts per category
recursive children[] response
breadcrumbs
category recommendations API
category admin CRUD
category deletion
is_featured
SEO metadata columns
filter_attributes
frontend navigation
mega menu
category landing UI
Flutter category screens
```

unless the latest authoritative repository docs explicitly place one of these inside Phase 5.1.

---

# 116. STOP Condition

STOP when:

```text
GET /api/v1/categories
```

and:

```text
GET /api/v1/categories/{category}
```

provide the complete approved public V1 category-read behavior, the contract/schema are reconciled, all tests/checks pass, and no later catalog/frontend work has been pulled forward.

Do not continue automatically to the next Group E phase.

DO NOT COMMIT, STAGE OR PUSH.

The project owner handles Git operations.
