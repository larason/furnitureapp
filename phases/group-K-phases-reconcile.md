# Phase 11.4 — Category CRUD — Approved Placement Reconciliation

## 1. Current Status

Phase 11.4 was correctly BLOCKED after completing the Category persistence reconciliation.

Confirmed:

```text
categories.description
categories.image_url
```

already exist through:

```text
2026_09_19_120000_add_public_fields_to_categories_table.php
```

and the persistence/public-read regression is green.

The remaining blocker is the absence of a frozen CAT-011 rule for server-controlled:

```text
parent_id
space_type
display_order
is_active
```

This document resolves that gap.

After recording and implementing this reconciliation, continue the remaining Phase 11.4 CAT-011/CAT-012 work.

---

# 2. Approved V1 Consistency Reconciliation

CAT-011 retains its frozen external request:

```json
{
  "name": "...",
  "slug": "...",
  "description": null,
  "image": null
}
```

Do NOT add:

```text
parent_id
space_type
display_order
is_active
```

to `CategoryCreateRequest`.

Instead, they are server-controlled according to the following authoritative V1 rule.

---

# 3. CAT-011 Placement Rule

Every Category created through:

```http
POST /api/v1/categories
```

is created as an:

```text
active direct child of the structural Furnitures Root
```

For CAT-011:

```text
parent_id
= internal id of canonical Furnitures Root

space_type
= hybrid

display_order
= current highest display_order among direct children
  of Furnitures Root + 1

is_active
= true
```

This rule applies only to CAT-011 V1 creation.

---

# 4. Why This Rule Is Chosen

The existing taxonomy defines:

```text
Furnitures Root
→ level-1 storefront categories
→ deeper grouping/category descendants
```

CAT-003 publicly lists:

```text
active direct children of Furnitures Root
```

Therefore placing CAT-011-created Categories at this level gives the otherwise hierarchy-free create endpoint a deterministic and useful meaning.

---

# 5. `space_type = hybrid`

This is now an explicit V1 decision.

`SpaceType` remains CLOSED:

```text
home
office
hybrid
```

Because CAT-011 does not accept usage context, the server must not guess:

```text
home
```

or:

```text
office
```

from a Category name.

Use:

```text
hybrid
```

as the neutral V1 server-controlled value for dynamically created root-level storefront Categories.

Do not infer `space_type` from words such as:

```text
office
bedroom
living
desk
outdoor
```

---

# 6. Seeded Taxonomy Is Unchanged

This reconciliation does NOT change existing seeded `space_type` assignments.

The current seed remains authoritative:

```text
Furnitures Root = hybrid

existing level-1 seeded categories
= their explicitly documented space_type

existing descendants
= existing documented inheritance
```

Only new CAT-011-created Categories receive the new:

```text
space_type = hybrid
```

rule.

---

# 7. No Retroactive Normalization

Do not rewrite existing categories to `hybrid`.

Do not alter CategorySeeder mappings.

Do not alter the 76-category canonical taxonomy merely to align it with CAT-011 defaults.

---

# 8. Parent Resolution

Resolve the structural root using the canonical root identity already established by the repository.

Expected canonical slug:

```text
furnitures-root
```

Do not use a hard-coded numeric database ID.

Prefer a dedicated resolver/constant if one already exists.

---

# 9. Root Missing

If `Furnitures Root` does not exist:

CAT-011 must fail safely.

Do not create another root automatically.

Do not create the new Category with:

```text
parent_id = null
```

A missing structural root is an application/reference-data integrity problem.

---

# 10. Root Ambiguity

There must be exactly one canonical structural root.

The global unique slug already protects duplicate:

```text
furnitures-root
```

entries.

Do not search by display name alone.

---

# 11. `is_active = true`

CAT-011-created Categories are active immediately.

Reason:

V1 has no Category activation endpoint.

Creating them inactive would create Categories that CAT-011 can create but V1 cannot subsequently activate.

Therefore:

```text
CAT-011 create
→ active storefront Category
```

is the approved V1 behavior.

---

# 12. Public Visibility Consequence

Because the Category is:

```text
parent = Furnitures Root
is_active = true
```

it becomes eligible for CAT-003 public navigation immediately after successful creation.

This is intentional.

---

# 13. `display_order`

New CAT-011 Categories append after existing direct children.

Compute:

```text
max(display_order for direct children of Furnitures Root) + 1
```

Do not accept `display_order` from the client.

---

# 14. Empty Sibling Set

If the root somehow has no children:

start with the repository's canonical first ordering value.

Inspect existing seed convention.

Expected conceptually:

```text
0 or 1
```

depending on actual existing taxonomy convention.

Use the established convention; do not mix indexing styles.

---

# 15. Concurrency-Safe Append

Two concurrent CAT-011 requests must not independently calculate the same append position through an unsafe read-before-write sequence.

Serialize allocation by locking the canonical root row inside the create transaction before calculating:

```text
MAX(display_order)
```

for its children.

Conceptually:

```text
BEGIN

lock Furnitures Root FOR UPDATE

maxOrder = children MAX(display_order)
nextOrder = maxOrder + 1

insert Category(
    parent_id = root.id,
    display_order = nextOrder,
    space_type = hybrid,
    is_active = true
)

COMMIT
```

Use repository transaction conventions.

---

# 16. Sibling Ordering

After concurrent successful creates:

```text
display_order
```

must remain deterministic.

Do not depend on timestamp race ordering.

---

# 17. Slug Collision Concurrency

Global slug uniqueness remains independent.

If two requests concurrently use the same slug:

```text
at most one succeeds
```

and the loser receives the canonical validation/conflict response.

No SQL exception leakage.

---

# 18. CAT-011 Is Not General Hierarchy Creation

The approved endpoint semantics are specifically:

```text
Create a new top-level storefront Category beneath Furnitures Root.
```

It is NOT:

```text
create an arbitrary node anywhere in taxonomy
```

Do not extend it beyond that in V1.

---

# 19. CAT-012 Remains Content-Only

CAT-012 continues to accept only:

```text
name
slug
description
image
```

It may NOT mutate:

```text
parent_id
space_type
display_order
is_active
```

---

# 20. No Reparenting Through CAT-012

Reject:

```json
{
  "parent_id": "cat_..."
}
```

with canonical unknown/server-controlled-field validation.

Existing internal:

```text
Category::changeParent()
```

remains domain infrastructure, not a V1 HTTP capability.

---

# 21. No Reordering Through CAT-012

Reject:

```text
display_order
```

No administrative drag-and-drop taxonomy ordering API is added in V1.

---

# 22. No Activation Mutation

Reject:

```text
is_active
```

CAT-011 creates active Categories.

CAT-012 does not activate/deactivate Categories.

If lifecycle controls are needed later, they require a dedicated contract decision.

---

# 23. No Space-Type Mutation

Reject:

```text
space_type
```

CAT-011 dynamically created Categories remain:

```text
hybrid
```

for V1.

Changing their semantic usage classification requires a future explicit management contract.

---

# 24. Existing Seeded Categories

CAT-012 may update allowed content fields on an existing Category where the current resolver/authorization permits it.

It must not alter the existing Category's:

```text
parent_id
space_type
display_order
is_active
```

---

# 25. Structural Root Protection

The structural:

```text
Furnitures Root
```

is system/reference taxonomy infrastructure.

CAT-011 never creates it.

For CAT-012, inspect existing repository policy.

If no frozen requirement permits editing the root:

protect the structural root from ordinary CAT-012 mutation.

Do not allow an accidental API request to rename the root slug and thereby break CAT-011 placement and CAT-003 navigation.

---

# 26. Recommended Root Mutation Rule

Adopt the following consistency rule unless an existing stronger rule already exists:

```text
Furnitures Root is system-owned and not mutable through CAT-012.
```

Attempted CAT-012 mutation of the canonical root should be rejected with the closest existing canonical authorization/business response.

Do NOT add a new error code unless required.

This protects the server-controlled structural anchor CAT-011 depends upon.

---

# 27. Description/Image Persistence

Keep the already completed persistence reconciliation.

CAT-011/012 may write:

```text
description
image
```

into the existing:

```text
categories.description
categories.image_url
```

mapping.

Do not add another migration for these fields.

---

# 28. Image Semantics

Input:

```text
image: URI string|null
```

Persistence:

```text
image_url
```

Response:

```json
{
  "image": {
    "url": "..."
  }
}
```

or:

```json
{
  "image": null
}
```

according to frozen Category representation.

No binary upload.

No remote fetch.

No SSRF.

---

# 29. CAT-011 Implementation

Now implement:

```http
POST /api/v1/categories
```

Authorization:

```text
products.manage
```

Actors:

```text
STAFF
ADMIN
```

subject to permission.

Response:

```text
201 Created
```

---

# 30. CAT-011 Request Allow-List

Exactly:

```text
name
slug
description
image
```

Required:

```text
name
slug
```

Optional nullable:

```text
description
image
```

---

# 31. CAT-011 Server-Derived Fields

Always derive:

```text
parent_id
space_type
display_order
is_active
```

from the approved placement rule.

Client cannot override them.

---

# 32. CAT-012 Implementation

Implement:

```http
PATCH /api/v1/categories/{category}
```

Authorization:

```text
products.manage
```

Allowed fields:

```text
name
slug
description
image
```

No other mutation.

---

# 33. Canonical Category Slug

Continue using the established domain invariant:

```text
^[a-z0-9]+(?:-[a-z0-9]+)*$
```

Do not weaken it to the broader current OpenAPI regex.

---

# 34. OpenAPI Slug Consistency Correction

The OpenAPI regex:

```text
^[a-z0-9-]+$
```

permits values the established domain rejects.

Correct it to the canonical kebab-case rule:

```text
^[a-z0-9]+(?:-[a-z0-9]+)*$
```

for Category create/update schemas if necessary.

Classify this explicitly as:

```text
frozen-contract consistency correction
```

because it documents an already-established server invariant rather than introducing new runtime behavior.

---

# 35. Do Not Change Product Slug Contract Accidentally

If Product and Category schemas use different named regex definitions:

change only Category validation documentation unless Product already has the same established stronger invariant.

Do not broaden this correction unnecessarily.

---

# 36. Authorization Tests

Verify:

```text
anonymous → 401
CUSTOMER → 403
STAFF missing products.manage → 403
STAFF products.manage → allowed
ADMIN missing permission → denied
ADMIN products.manage → allowed
```

---

# 37. CAT-011 Placement Tests

Mandatory assertions:

```text
parent = Furnitures Root
space_type = hybrid
is_active = true
display_order = previous max + 1
```

---

# 38. Public Visibility Test

After CAT-011:

```text
GET /api/v1/categories
```

must include the new Category according to CAT-003 pagination/navigation semantics.

---

# 39. Public Detail Test

After CAT-011:

```text
GET /api/v1/categories/{new-slug}
```

must return the new active Category.

---

# 40. Concurrent Append Test

Add a MariaDB concurrency test if necessary to prove:

```text
two simultaneous CAT-011 creates
→ both valid unique categories survive
→ distinct deterministic display_order positions
→ same parent
→ hierarchy remains valid
```

Reuse the established:

```text
RunsConcurrentWorkers
UsesDisposableMysqlDatabase
```

infrastructure where appropriate.

---

# 41. Concurrent Duplicate Slug

Also protect:

```text
two simultaneous CAT-011 requests
same slug
→ one success maximum
```

The unique DB constraint remains final authority.

---

# 42. Hierarchy Regression

Existing:

```text
no-cycle
concurrent reparent
taxonomy structure
```

tests must remain green.

CAT-011 itself does not invoke general reparenting.

---

# 43. Recommendation Regression

CAT-011/CAT-012 must not modify:

```text
category_recommendations
```

Existing recommendation tests remain green.

---

# 44. Seed Regression

Existing CategorySeeder must remain deterministic.

CAT-011 behavior must not alter seeding rules.

---

# 45. No Category Delete

Do not add:

```http
DELETE /api/v1/categories/{category}
```

---

# 46. No New Category Read Surface

Do not add:

```text
/admin/categories
/staff/categories
```

---

# 47. No Hierarchy Management Surface

Do not add:

```text
reparent
move
reorder
activate
deactivate
```

endpoints.

---

# 48. No Recommendation Management Surface

Do not add recommendation mutation APIs.

---

# 49. Audit

If existing closed audit vocabulary does not contain Category mutation actions:

do not invent them here.

Record the same catalog-audit gap for later reconciliation.

Do not touch the ADM-007 / `audit.view` Phase 11.13 issue.

---

# 50. Documentation

Update:

```text
phases/group-K-phases.md
docs/decisions.md
```

with this exact placement decision.

The ADR must record that this was a post-freeze consistency resolution required because CAT-011's external schema intentionally contains no hierarchy fields.

---

# 51. ADR Decision

Record conceptually:

```text
CAT-011 Server-Controlled Category Placement
```

with:

```text
parent = Furnitures Root
space_type = hybrid
display_order = append
is_active = true
```

and:

```text
CAT-012 remains content-only
```

---

# 52. Compatibility Classification

Classify this as:

```text
frozen V1 implementation/consistency reconciliation
```

not a new external capability.

Why:

```text
request schema unchanged
response schema unchanged
endpoint unchanged
authorization unchanged
```

Only previously-unspecified server-controlled persistence semantics are now defined.

---

# 53. OpenAPI Changes

Allowed only for the Category slug-regex consistency correction.

Expected otherwise:

```text
request/response shapes unchanged
```

---

# 54. Schema Changes

Expected for the continuation:

```text
NONE
```

The existing description/image migration already solved persistence.

---

# 55. Dependencies

Expected:

```text
NONE
```

---

# 56. Frontend

Expected:

```text
NONE
```

---

# 57. Verification

Run:

```bash
php artisan test
vendor/bin/phpstan analyse
vendor/bin/pint --test
composer audit
git diff --check
php artisan route:list --path=api --except-vendor
```

Run the OpenAPI tests/parser.

Run relevant focused suites:

```text
Category create
Category update
Category public reads
Category hierarchy
Category concurrent hierarchy
Category recommendations
Category seed
Catalog/Product category filtering
RBAC/authorization
```

---

# 58. MariaDB Verification

Use:

```text
furnitureapp_test_disposable
```

for any real concurrency proof.

Production database must remain untouched.

---

# 59. Completion Report — Placement

Report explicitly:

```text
parent_id:
Furnitures Root

space_type:
hybrid

display_order:
append after highest direct child

is_active:
true
```

---

# 60. Completion Report — CAT-011

Report:

```text
route:
status:
authorization:
request fields:
server-derived fields:
public visibility:
```

---

# 61. Completion Report — CAT-012

Report:

```text
route:
status:
authorization:
mutable fields:
server-controlled fields:
root protection:
```

---

# 62. Completion Report — Contract

Report:

```text
Category request shape changed: NO
Category response shape changed: NO
Hierarchy fields exposed to client: NO
```

and any Category-slug OpenAPI regex correction separately.

---

# 63. Completion Report — Non-Goals

Confirm:

```text
Category DELETE: NONE
Hierarchy management endpoints: NONE
Recommendation management endpoints: NONE
Admin Category read aliases: NONE
```

---

# 64. Phase 11.4 Final State

If all verification passes:

```text
Phase 11.4 PASS
Phase 11.5 — Image management READY
```

---

# 65. Definition of Done

Phase 11.4 is complete when:

- existing description/image persistence reconciliation remains green;
- CAT-011 is active;
- CAT-012 is active;
- CAT-011 creates a direct child of Furnitures Root;
- new CAT-011 Category uses `space_type=hybrid`;
- new CAT-011 Category is active;
- new CAT-011 Category appends deterministically after current root children;
- concurrent creates cannot corrupt sibling ordering;
- client cannot override hierarchy fields;
- CAT-012 changes content only;
- CAT-012 cannot reparent;
- CAT-012 cannot reorder;
- CAT-012 cannot activate/deactivate;
- CAT-012 cannot change space type;
- structural root cannot be accidentally corrupted through ordinary Category management;
- canonical Category slug validation remains enforced;
- public CAT-003 reflects successful new Category;
- public CAT-004 reflects successful create/update;
- Product category filtering remains functional;
- existing hierarchy remains acyclic;
- recommendation graph remains unchanged;
- CategorySeeder remains deterministic;
- no Category DELETE endpoint is added;
- no hierarchy management endpoint is added;
- no recommendation management endpoint is added;
- full suite is green.

---

# 66. STOP Condition

STOP only when the agent can report:

```text
Phase 11.4 PASS

CAT-011 ACTIVE
CAT-012 ACTIVE

CAT-011 placement:
parent = Furnitures Root
space_type = hybrid
display_order = append
is_active = true

No external hierarchy fields added.
No Category DELETE added.
No recommendation/hierarchy management API added.

Phase 11.5 — Image management READY
```

Do not begin Phase 11.5 automatically.

DO NOT COMMIT, STAGE OR PUSH.

The project owner handles all Git operations.