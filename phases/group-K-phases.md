# Phase 11.4 — Category CRUD

## 1. Objective

Implement the Version 1 administrative Category management backend for authorized Staff/Admin.

## Implementation Status

Phase 11.4 is complete. The existing `2026_09_19_120000_add_public_fields_to_categories_table.php` migration persists nullable `categories.description` and `categories.image_url`, and CAT-011/CAT-012 implement the frozen request/response contract without exposing hierarchy controls.

CAT-011 creates an active direct child of `furnitures-root`, assigns `SpaceType::HYBRID`, and appends after the highest direct-child `display_order` (starting at `1` for an empty sibling set) inside a transaction that locks the root row. CAT-012 resolves opaque IDs or slugs including inactive categories, updates only `name`, `slug`, `description`, and `image`, and rejects mutation of the system-owned root. The approved reconciliation is recorded in `phases/group-K-phases-reconcile.md` and ADR/API-CAT-006 below.

Phase 11.4 executes in two ordered parts:

```text
Part A — Category persistence reconciliation
Part B — CAT-011 / CAT-012 implementation
```

Do not begin Part B until Part A is complete, documented, migrated, and tested.

---

# PART A — CATEGORY PERSISTENCE RECONCILIATION

## 2. Confirmed Contract/Persistence Gap

The frozen V1 Category management contract accepts:

```text
name
slug
description
image
```

for:

```text
CAT-011 POST /api/v1/categories
CAT-012 PATCH /api/v1/categories/{category}
```

`CategoryCreateRequest` requires:

```text
name
slug
```

and permits nullable:

```text
description
image
```

The public Category contract also exposes:

```text
CAT-003 summary:
id
name
slug
image

CAT-004 detail:
id
name
slug
description
image
created_at
```

However, the current Category persistence model was originally designed around:

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

and therefore does not directly persist the frozen:

```text
description
image
```

fields.

This must be reconciled before CAT-011/012 are implemented.

---

# 3. Reconciliation Principle

Preserve the frozen API.

Do NOT remove:

```text
description
image
```

from:

```text
CategoryCreateRequest
CategoryUpdateRequest
CAT-003
CAT-004
```

Do NOT make them new external concepts.

They are already part of V1.

Instead, reconcile persistence so the existing frozen contract can be implemented.

---

# 4. Approved Persistence Direction

Add Category-level persistence for:

```text
description
image
```

using the smallest schema change consistent with existing naming conventions.

Conceptually:

```text
categories.description
categories.image_url
```

or equivalent repository-consistent names.

Do not guess column names without first inspecting existing Product/media/category conventions.

---

# 5. Description Persistence

Recommended characteristics:

```text
nullable
text/string appropriate for max 1000 API chars
plain content
no HTML authority
```

Do not add rich-text semantics.

---

# 6. Image Persistence

The frozen Category contract currently accepts:

```text
image: string|null
format: uri
```

and returns an image object conceptually:

```json
{
  "image": {
    "url": "https://..."
  }
}
```

Therefore persist only the canonical image URL/reference needed for this V1 contract.

Do not create a full Category-media subsystem.

---

# 7. Category Image Is Not Product Media

Phase 11.5 concerns Product image management.

Category image in CAT-011/012 is already part of the frozen Category contract.

Do not incorrectly defer Category `image` to 11.5.

---

# 8. No Category Attachment Model

Do not create:

```text
category_images table
attachments relationship
media gallery
multiple category images
```

V1 requires one nullable Category image.

---

# 9. Existing Seeded Categories

Existing taxonomy contains 76 seeded categories.

Migration must preserve them.

Backfill:

```text
description = null
image = null
```

unless existing deterministic source data already supplies values.

Do not invent descriptions or image URLs in migration code.

---

# 10. Existing Public API Compatibility

If CAT-003/004 currently synthesize null images/descriptions:

after migration, preserve the same response shape.

No API shape change.

---

# 11. Category Factory

Update CategoryFactory to support:

```text
description
image
```

without requiring them.

Defaults may remain null.

Do not make factory-created categories depend on remote URLs unless explicitly requested by a test state.

---

# 12. Category Seeder

Do not rewrite taxonomy structure.

Existing:

```text
1 root
6 room categories
16 grouping categories
53 type categories
```

must remain intact.

If current seed definitions contain no descriptions/images:

leave them null.

---

# 13. Migration Safety

Add a new migration.

Do not modify Phase 3.3 historical migration.

Verify on:

```text
SQLite canonical tests
MariaDB 11.8.8 disposable database
```

---

# 14. Reconciliation ADR

Add a decision record explaining:

```text
Frozen CAT-011/012 and CAT-003/004 already include description/image.
Original Category schema omitted persistence for them.
Phase 11.4 adds Category description/image persistence without changing external V1 behavior.
Hierarchy, space_type, display_order, is_active, and recommendation graph remain unchanged.
```

---

# 15. Reconciliation Exit Gate

Do not continue to Category API implementation until:

- migration exists;
- current taxonomy survives;
- Category model/factory updated;
- public CAT-003/004 still pass;
- description/image serialize correctly;
- no hierarchy/recommendation behavior changed;
- OpenAPI shape remains unchanged.

If not:

```text
Phase 11.4 BLOCKED
```

with exact reason.

---

# PART B — CATEGORY CRUD IMPLEMENTATION

## 16. Canonical Endpoints

Implement:

```http
POST /api/v1/categories
PATCH /api/v1/categories/{category}
```

Endpoint IDs:

```text
CAT-011
CAT-012
```

Do not add:

```text
/admin/categories
/staff/categories
/backoffice/categories
```

The frozen canonical paths are unprefixed operational catalog paths.

---

# 17. Product Permission Reuse

Both endpoints require:

```text
products.manage
```

for authorized Staff/Admin.

Do not introduce:

```text
categories.manage
```

in Phase 11.4.

The frozen V1 catalog management model uses `products.manage`.

---

# 18. Actor Matrix

Expected:

```text
Anonymous
→ 401

CUSTOMER
→ 403

STAFF without products.manage
→ 403

STAFF with products.manage
→ allowed

ADMIN without permission
→ denied

ADMIN with products.manage
→ allowed
```

Use actual PermissionCatalog.

---

# 19. No Role-Only Authorization

Do not authorize solely because:

```text
role = ADMIN
```

Permission remains explicit.

---

# 20. CAT-011 — Create Category

Implement:

```http
POST /api/v1/categories
```

Expected response:

```text
201 Created
```

using the existing Category representation required by the frozen endpoint.

---

# 21. CAT-011 Frozen Input

Allow exactly:

```text
name
slug
description
image
```

No hierarchy controls are part of the frozen CAT-011 request.

---

# 22. Required Create Fields

Required:

```text
name
slug
```

Optional:

```text
description
image
```

---

# 23. Unknown Fields

Reject unknown fields.

Examples that must NOT be accepted through CAT-011:

```text
parent_id
space_type
display_order
is_active
products
children
recommendations
relation_type
priority
created_at
updated_at
```

unless a later formal contract reconciliation explicitly adds them.

---

# 24. Important Hierarchy Boundary

The Category schema internally supports:

```text
parent_id
space_type
display_order
is_active
```

but the frozen CategoryCreateRequest does NOT expose those fields.

Therefore CAT-011 must not silently turn into taxonomy-structure management.

---

# 25. Creation Placement Problem

Because CAT-011 does not accept:

```text
parent_id
space_type
display_order
is_active
```

the implementation must determine how newly created Categories are placed.

Inspect existing frozen docs/decisions for an already-approved rule.

Do NOT guess.

---

# 26. If No Placement Rule Exists

If no authoritative rule defines how CAT-011 chooses:

```text
parent_id
space_type
display_order
is_active
```

STOP the implementation at this sub-point.

Report:

```text
CAT-011 hierarchy placement gap
```

Do not invent:

```text
parent = Furnitures Root
space_type = hybrid
display_order = 0
is_active = true
```

without a contract decision.

---

# 27. Smallest Safe Reconciliation If Required

If the contract is silent, prefer a formal consistency correction over hidden defaults.

The correction must preserve the external API if possible.

For example, if the business intent is clearly:

```text
CAT-011 creates top-level storefront categories beneath Furnitures Root
```

that rule may be documented as server-controlled behavior.

But only do this if repository evidence supports it.

Otherwise STOP and ask for contract decision.

---

# 28. Do Not Expand Request Shape Automatically

Do not add:

```text
parent_id
space_type
display_order
is_active
```

to CategoryCreateRequest merely because the schema has them.

That changes the frozen strict request contract.

---

# 29. Category Hierarchy Remains Server-Controlled

Unless a later API explicitly supports reparenting:

```text
parent_id
```

remains server-controlled.

---

# 30. No Hierarchy Management Endpoint

Do not add:

```http
POST /categories/{category}/move
PATCH /categories/{category}/parent
POST /categories/{category}/reparent
```

in 11.4.

---

# 31. No Recommendation Management

Do not expose:

```text
category_recommendations
relation_type
priority
```

through CAT-011/012.

The recommendation graph already exists as durable configuration but has no frozen management endpoint.

---

# 32. Existing Recommendation Graph

Preserve:

```text
COMPLEMENTARY
PAIR_WITH
COMPLETE_THE_LOOK
ALTERNATIVE
```

relationships unchanged.

Category create/update must not mutate graph edges.

---

# 33. `name`

Validate:

```text
string
trimmed
non-empty
max 120
```

Use existing text-normalization conventions.

---

# 34. `slug`

Frozen OpenAPI pattern is:

```text
^[a-z0-9-]+$
```

but the Category model already enforces stronger canonical kebab-case:

```text
^[a-z0-9]+(?:-[a-z0-9]+)*$
```

which rejects:

```text
leading hyphen
trailing hyphen
consecutive hyphens
spaces
underscores
uppercase
```

This is a contract-validation consistency issue.

---

# 35. Slug Validation Authority

Preserve the established canonical Category slug invariant from the schema/domain.

Do not weaken the model to accept malformed slugs just because OpenAPI regex is broader.

Instead reconcile OpenAPI/documentation only if necessary through frozen-contract consistency correction.

---

# 36. Category Slug Examples

Accept:

```text
living-room
office-chairs
tv-stands-showcases
```

Reject:

```text
Living-Room
living_room
-living-room
living-room-
living--room
```

---

# 37. Slug Unique

Global uniqueness remains mandatory.

Duplicate slug must return canonical API validation/conflict behavior.

Never expose DB exception details.

---

# 38. Description

Rules:

```text
string|null
max 1000
```

Decide blank normalization consistently.

If project convention maps blank optional text to null:

use it.

Otherwise preserve explicit empty string semantics only if frozen.

---

# 39. Category Image Input

Rules:

```text
string|null
valid URI
```

No binary upload through CAT-011/012.

---

# 40. Category Image Security

Validate URI structurally.

Do not:

```text
fetch remote URL
proxy remote file
download image
perform SSRF request
```

during Category mutation.

Store only validated URI/reference.

---

# 41. Allowed URI Schemes

Inspect existing URI validation conventions.

Prefer:

```text
https
```

if frozen security policy already requires it.

Do not allow arbitrary:

```text
file:
javascript:
data:
ftp:
```

without explicit contract support.

---

# 42. CAT-012 — Update Category

Implement:

```http
PATCH /api/v1/categories/{category}
```

Expected:

```text
200
```

---

# 43. CAT-012 Frozen Allow-List

All optional:

```text
name
slug
description
image
```

Nothing else.

---

# 44. Partial Update

PATCH must modify only supplied fields.

No full-replacement semantics.

---

# 45. Atomicity

If update contains:

```text
name
slug
description
image
```

and one fails validation:

no field is persisted.

---

# 46. Same-Value PATCH

Prefer no unnecessary persistence if repository conventions support it.

---

# 47. Category Identifier

Use the canonical Category path resolver.

Frozen public Category detail supports:

```text
slug
or opaque category id
```

Preserve the same resolution semantics for CAT-012 unless contract states otherwise.

---

# 48. Opaque Identifier

Never expose raw numeric DB ID.

Use existing CategoryIdentifier.

---

# 49. Public Category Reads

Phase 11.4 must not redesign:

```text
CAT-003
CAT-004
```

They are already implemented in Group E.

---

# 50. CAT-003 Scope

Preserve:

```text
active storefront categories
direct children of Furnitures Root
```

only.

Do not make newly created categories public merely because they exist unless their internal placement/state satisfies CAT-003 rules.

---

# 51. CAT-004 Scope

Preserve existing:

```text
active Category only
```

public behavior.

Inactive/non-public Category still returns public 404 as already defined.

---

# 52. No Operational Category Read Endpoint

Frozen V1 does not define:

```text
GET /admin/categories
GET /admin/categories/{category}
```

Do not add them.

This means Category management write UX will later rely on the existing public Category reads and/or known identifiers unless a future contract explicitly adds operational reads.

Do not expand surface here.

---

# 53. Important Operational Limitation

Because no CAT-013/014 equivalent exists for Categories:

inactive Categories may not be directly retrievable through a dedicated Admin read API.

Do not solve this by inventing endpoints during 11.4.

Record it as a V1 operational limitation if relevant.

---

# 54. `is_active` Not Client-Writable

Although Category schema has:

```text
is_active
```

CAT-011/012 do not expose it.

Reject client input:

```json
{"is_active": false}
```

---

# 55. Activation Management

Do not add category activation/deactivation behavior unless a frozen contract explicitly defines it.

If current Category management cannot alter `is_active`, record that limitation.

---

# 56. `display_order` Not Client-Writable

Reject:

```text
display_order
```

through CAT-011/012.

---

# 57. `space_type` Not Client-Writable

Reject:

```text
space_type
```

through CAT-011/012.

---

# 58. `parent_id` Not Client-Writable

Reject:

```text
parent_id
```

through CAT-011/012.

---

# 59. Existing No-Cycle Logic

Do not weaken or remove:

```text
Category::changeParent()
```

or its no-cycle/concurrency protections.

Even though CAT-011/012 do not currently expose hierarchy mutation, the domain invariant remains authoritative.

---

# 60. Existing Root

Preserve:

```text
Furnitures Root
```

as structural taxonomy root.

Do not allow CAT-011/012 to accidentally rename or structurally corrupt it unless the frozen contract explicitly permits editing that exact Category.

---

# 61. Root Mutation Safety

Consider protecting structural root fields through domain rules if current architecture treats root as system-owned.

Inspect existing decisions.

Do not invent root immutability without evidence.

---

# 62. Existing Products

Changing Category:

```text
name
slug
description
image
```

must not change Product `category_id`.

Products remain linked to the same Category row.

---

# 63. Category Slug Update Effects

Products referencing Category continue to work because FK uses internal ID.

Product public embedded Category summary should immediately reflect updated:

```text
name
slug
description
```

where included.

---

# 64. Product Filter Effects

`GET /products?category={category}` must continue resolving Category by canonical slug/id after slug update according to current Group E resolver behavior.

Old slug history/redirect is not part of V1 unless already implemented.

---

# 65. No Product Reassignment

Category update must not bulk move Products.

---

# 66. No Product Deletion

Category update must not delete Products.

---

# 67. No Inventory Effects

No stock behavior belongs here.

---

# 68. No Request Effects

Existing FurnitureRequests stay unchanged.

---

# 69. No Enquiry Effects

Existing Enquiries stay unchanged.

---

# 70. Category Delete

There is no frozen:

```http
DELETE /api/v1/categories/{category}
```

Do not add one.

---

# 71. Existing FK Delete Behavior

Internal Category deletion behavior remains:

```text
parent delete → child parent_id SET NULL
product category FK RESTRICT
recommendation edges cascade
```

but none of this becomes an API operation in 11.4.

---

# 72. Controller

Keep thin:

```text
authenticate
authorize
validate
DTO
service
resource
```

---

# 73. Create Service

Prefer:

```text
CreateCategory
```

or repository-consistent equivalent.

It should handle:

```text
validated request
server-controlled placement/defaults if already approved
slug uniqueness
description/image persistence
```

---

# 74. Update Service

Prefer:

```text
UpdateCategory
```

for:

```text
name
slug
description
image
```

only.

---

# 75. FormRequests

Use:

```text
CreateCategoryRequest
UpdateCategoryRequest
```

or equivalent.

Unknown-field rejection mandatory.

---

# 76. DTOs

Use strict input objects.

Do not pass generic arrays deep into domain services.

---

# 77. Never Use `$request->all()`

Use:

```text
validated()
```

plus explicit mapping.

---

# 78. Category Resource

Reuse the existing public Category resource for CAT-011/012 response only if it exactly matches frozen mutation response requirements.

Do not mass serialize the model.

---

# 79. Description Exposure

CAT-004 should expose description.

CAT-003 summary should remain lean according to frozen representation.

Do not add description to CAT-003 if contract omits it.

---

# 80. Image Representation

Input:

```text
image = URI string|null
```

Response:

```json
"image": {
  "url": "..."
}
```

or:

```json
"image": null
```

according to frozen resource.

Do not return raw internal column names.

---

# 81. Cache Invalidation

Category mutations affect public navigation/catalog.

Reuse existing application cache invalidation infrastructure if available.

Do not build production CDN tooling here.

---

# 82. Public Cache Safety

CAT-003/004 remain public cacheable.

CAT-011/012 are non-cacheable mutations.

---

# 83. Audit

Privileged catalog changes are conceptually audit candidates.

Inspect current closed AuditAction vocabulary.

If no Category-specific safe action exists:

do not invent audit enum values silently.

Record the gap for later formal audit reconciliation.

---

# 84. Phase 11.13 Gap

Do not touch:

```text
ADM-007
audit.view
```

in this phase.

---

# 85. No Recommendation API

Do not add:

```text
POST /categories/{category}/recommendations
DELETE /categories/{category}/recommendations/{target}
```

---

# 86. No Hierarchy API

Do not add:

```text
move
reparent
reorder
activate
deactivate
```

routes.

---

# 87. Create Validation Tests

Mandatory:

```text
valid create
name required
slug required
name max 120
slug canonical kebab-case
slug global uniqueness
description nullable
description max 1000
image nullable
image valid URI
unknown fields rejected
parent_id rejected
space_type rejected
display_order rejected
is_active rejected
recommendations rejected
server fields rejected
```

---

# 88. Update Validation Tests

Mandatory:

```text
partial name
partial slug
partial description
clear description to null
partial image
clear image to null
duplicate slug
malformed slug
invalid URI
unknown fields
hierarchy fields rejected
atomic failure
```

---

# 89. Authorization Tests

Test:

```text
Anonymous
CUSTOMER
STAFF no permission
STAFF products.manage
ADMIN with permission
ADMIN missing permission
```

---

# 90. Persistence Reconciliation Tests

Mandatory:

```text
description persists
image persists
existing seeded categories migrate with nulls safely
factory works without description/image
public detail returns persisted description/image
summary returns image and omits detail-only fields
```

---

# 91. Hierarchy Regression Tests

Run existing:

```text
CategoryHierarchyTest
CategoryConcurrentReparentTest
```

or current equivalents.

Phase 11.4 must not destabilize hierarchy.

---

# 92. Recommendation Regression Tests

Run existing:

```text
CategoryRecommendationTest
CategorySeedTest
```

No edge changes expected.

---

# 93. Taxonomy Seed Regression

Expected seeded structure remains:

```text
76 categories
20 recommendation mappings
```

unless current repository counts have intentionally changed since that ADR.

Use actual current test expectations.

Do not hardcode stale counts if runtime differs.

---

# 94. Slug Concurrency

Two simultaneous creates with the same slug:

```text
at most one succeeds
```

DB unique constraint remains final guard.

Loser must receive canonical API error rather than raw SQL exception.

---

# 95. MariaDB Validation

Because schema changes in Part A:

run disposable MariaDB:

```text
migrate:fresh --seed --force
```

against:

```text
furnitureapp_test_disposable
```

Verify:

```text
new columns
constraints
seed integrity
slug uniqueness
```

---

# 96. No Production DB

Do not touch Coolify/Contabo production DB.

---

# 97. OpenAPI

Expected:

```text
unchanged
```

unless the stronger canonical slug regex is documented as an approved consistency correction.

---

# 98. Slug Regex Reconciliation

The machine-readable schema currently permits more strings than the domain does.

If correcting OpenAPI:

change only the regex/documentation to match the already-existing domain invariant.

Classify this as:

```text
frozen-contract consistency correction
```

not new behavior.

Do not weaken runtime validation.

---

# 99. Schema Changes Expected

Only:

```text
Category description persistence
Category image persistence
```

plus necessary indexes/constraints if already justified.

No hierarchy redesign.

---

# 100. Dependencies

Expected:

```text
NONE
```

---

# 101. Frontend

Expected:

```text
NONE
```

---

# 102. Product Media

Do not implement Phase 11.5.

---

# 103. Inventory

Do not implement Phase 11.6.

---

# 104. Deferred Commerce

Do not activate:

```text
11.7
11.11
11.12
Group H
Group I
```

---

# 105. Documentation

Update:

```text
phases/group-K-phases.md
docs/decisions.md
```

and Category schema/resource docs where the old persistence description is now stale.

---

# 106. Historical ADR Preservation

Do not delete:

```text
ADR/BACKEND-009
```

Add a later reconciliation ADR that supersedes only the statement that Categories persist no description/image.

Preserve the original hierarchy decision history.

---

# 107. Completion Report — Status

Return:

## Phase 11.4 Status

```text
PASS
```

or:

```text
BLOCKED
```

---

# 108. Completion Report — Persistence Reconciliation

Report:

```text
Previous schema:
Frozen Category API:
New columns:
Existing-row migration behavior:
Public API compatibility:
OpenAPI changed:
```

---

# 109. Completion Report — CAT-011

Report:

```text
route:
authorization:
required fields:
optional fields:
server-controlled placement behavior:
response:
```

---

# 110. Completion Report — CAT-012

Report:

```text
route:
authorization:
allow-list:
identifier resolution:
atomicity:
response:
```

---

# 111. Completion Report — Hierarchy Placement

Mandatory.

Report exactly how CAT-011 determines:

```text
parent_id
space_type
display_order
is_active
```

If there was no frozen rule and implementation had to stop:

report that as the blocker.

Do not omit this section.

---

# 112. Completion Report — Rejected Administrative Fields

Confirm CAT-011/012 reject:

```text
parent_id
space_type
display_order
is_active
recommendations
products
```

unless a formal reconciliation approved otherwise.

---

# 113. Completion Report — Delete

Report:

```text
Category DELETE endpoint: NONE
```

---

# 114. Completion Report — Recommendation Graph

Report:

```text
Recommendation management endpoints: NONE
Recommendation rows modified by CAT-011/012: NO
```

---

# 115. Completion Report — Public Reads

Report CAT-003/004 regression results.

---

# 116. Completion Report — Schema

Report exact new Category columns.

---

# 117. Completion Report — OpenAPI

Expected:

```text
unchanged
```

or exact slug-regex consistency correction.

---

# 118. Completion Report — Tests

Report focused:

```text
Category persistence
Category create API
Category update API
Authorization
Public Category reads
Slug uniqueness
Hierarchy regression
Concurrent reparent regression
Recommendation regression
Seed regression
```

---

# 119. Completion Report — Quality

Report:

```text
PHPUnit
MariaDB migration
OpenAPI
PHPStan
Pint
Composer audit
git diff --check
route:list
```

---

# 120. Next Phase

If PASS:

```text
Phase 11.5 — Image management READY
```

Do not begin automatically.

---

# 121. Definition of Done

Phase 11.4 is complete only when:

- Category `description` persistence is reconciled;
- Category `image` persistence is reconciled;
- frozen CAT-003/004 response shapes remain valid;
- CAT-011 is implemented;
- CAT-012 is implemented;
- `products.manage` is enforced;
- Anonymous is denied;
- Customer is denied;
- unauthorized Staff/Admin are denied;
- CAT-011 accepts only the frozen allow-list;
- CAT-012 accepts only the frozen allow-list;
- unknown fields are rejected;
- slug uniqueness is enforced;
- canonical kebab-case invariant remains intact;
- nullable description works;
- nullable image works;
- image URI is never fetched server-side;
- no Product behavior is pulled into Category mutation;
- no hierarchy mutation API is invented;
- no recommendation-management API is invented;
- no Category DELETE endpoint is invented;
- existing no-cycle protections remain intact;
- existing concurrent reparent protections remain intact;
- existing recommendation graph remains intact;
- taxonomy seed remains deterministic;
- Product filtering by Category continues working;
- Category updates do not mutate Product ownership;
- OpenAPI remains aligned;
- MariaDB migration verification passes;
- full regression is green.

---

# 122. STOP Condition

STOP when the backend can prove:

```text
authorized Staff/Admin
→ CAT-011 create Category
→ CAT-012 update Category
→ frozen description/image contract persists correctly
→ CAT-003/CAT-004 reflect approved public values
```

while preserving:

```text
existing taxonomy hierarchy
no-cycle protection
recommendation graph
products.manage authorization
no Category DELETE API
no hierarchy/recommendation management API
```

and report:

```text
Phase 11.4 PASS

Phase 11.5 — Image management READY
```

If CAT-011 has no authoritative server-side rule for its internal hierarchy fields:

```text
Phase 11.4 BLOCKED
Reason: CAT-011 hierarchy placement contract gap
```

Do not invent the rule.

DO NOT COMMIT, STAGE OR PUSH.

The project owner handles all Git operations.
