# Group C phases instructions

# Phase 3.3 — Categories Schema

## Purpose

Implement the database foundation for the furniture catalog's category taxonomy.

This phase establishes:

* the hierarchical `categories` table;
* parent/child category relationships;
* category metadata needed by public catalog navigation;
* the agreed furniture taxonomy;
* the category-to-category recommendation relationship structure;
* Eloquent relationships and focused schema/model tests.

The design must support a clear navigation tree today while leaving room for future product-category relationships and automated recommendation/cross-selling features.

This phase is **schema/model foundation only**.

Do not implement product APIs, recommendation APIs, recommendation algorithms, search ranking, or product-category assignment workflows in this phase.

---

# Dependencies

Required before starting:

* Phase 2.1–2.12 completed.
* Phase 3.1 Users Schema completed.
* Phase 3.2 Roles/permissions model completed.
* Laravel migrations/models/testing conventions established.
* Existing database connection and migration test infrastructure working.

Authoritative inputs:

* `docs/VISION.md`
* `docs/api/api-contract.md`
* `docs/api/api-resources.md`
* `docs/api/api-conventions.md`
* `docs/domain/business-rules.md`
* `docs/decisions.md`
* `AGENTS.md`

Do not reopen unrelated architectural decisions.

---

# 3.3.1 Category hierarchy model

Use an **adjacency-list hierarchy**.

The category table must contain:

```text
categories
  id
  parent_id
  name
  slug
  space_type
  display_order
  is_active
  created_at
  updated_at
```

`parent_id` references another row in `categories`.

Root categories have:

```text
parent_id = null
```

Child categories reference their immediate parent.

This provides a simple relational tree suitable for:

* catalog navigation;
* breadcrumbs;
* recursive category retrieval;
* future product-category joins;
* category-specific recommendations.

Do not introduce nested-set, materialized-path, closure-table, or graph-database infrastructure in V1.

---

# 3.3.2 Three-level taxonomy

The initial taxonomy is intentionally three levels:

```text
Level 1 = room/context
Level 2 = furniture grouping
Level 3 = specific furniture type
```

Seed the following canonical hierarchy.

## Furnitures Root

```text
Furnitures Root
├── Living Room
│   ├── Seating
│   │   ├── Sofas
│   │   ├── Sectionals
│   │   ├── Armchairs
│   │   ├── Recliners
│   │   ├── Loveseats
│   │   └── Stools/Poufs
│   ├── Tables
│   │   ├── Coffee Tables
│   │   ├── End/Side Tables
│   │   ├── Console Tables
│   │   └── Nesting Tables
│   └── Storage & Media
│       ├── TV Stands/Showcases
│       ├── Bookcases
│       └── Display Cabinets
├── Bedroom
│   ├── Beds
│   │   ├── Platform Beds
│   │   ├── Canopy Beds
│   │   ├── Storage Beds
│   │   ├── Daybeds
│   │   └── Bunk Beds
│   ├── Storage
│   │   ├── Dressers
│   │   ├── Nightstands
│   │   ├── Wardrobes
│   │   └── Chest of Drawers
│   └── Vanity & Seating
│       ├── Vanity Tables
│       └── Bedroom Benches
├── Dining Room & Kitchen
│   ├── Dining Sets & Tables
│   │   ├── Dining Tables
│   │   └── Kitchen Islands
│   ├── Dining Seating
│   │   ├── Dining Chairs
│   │   ├── Bar & Counter Stools
│   │   └── Dining Benches
│   └── Dining Storage
│       ├── Sideboards/Buffets
│       ├── Bar Carts
│       └── China Cabinets
├── Home Office & Corporate Workspaces
│   ├── Desks
│   │   ├── Executive Desks
│   │   ├── Standing/Adjustable Desks
│   │   ├── Corner/L-Shaped Desks
│   │   └── Writing Desks
│   ├── Office Seating
│   │   ├── Ergonomic Task Chairs
│   │   ├── Executive Chairs
│   │   └── Visitor Chairs
│   └── Office Storage
│       ├── Filing Cabinets
│       ├── Credenzas
│       └── Office Bookcases
├── Outdoor & Patio
│   ├── Outdoor Seating
│   │   ├── Patio Sofas
│   │   ├── Loungers
│   │   └── Hammocks
│   └── Outdoor Dining
│       ├── Patio Tables
│       └── Outdoor Bar Sets
└── Entryway & Accent
    ├── Entryway Furniture
    │   ├── Shoe Cabinets
    │   ├── Coat Racks
    │   └── Entryway Benches
    └── Accent Pieces
        ├── Accent Tables
        ├── Accent Chairs
        └── Room Dividers
```

Treat this as the initial V1 seed taxonomy.

Do not add additional category branches merely because they seem useful.

Do not create category records for individual brands, materials, colors, styles, dimensions, or prices.

---

# 3.3.3 Category depth

The intended taxonomy depth is three levels below the root category.

The database uses `parent_id`; it should not depend on hard-coded columns such as:

```text
level_1_id
level_2_id
level_3_id
```

Do not create separate tables for each hierarchy level.

The implementation must protect against:

* a category being its own parent;
* direct cyclic relationships;
* accidental recursive loops.

The required invariant is a **no-cycle invariant**: when a parent assignment is persisted, model/application validation must reject it if the assigned parent is the category itself or any descendant of the category. Walking the assigned parent's ancestor chain satisfies this and prevents self-parenting (`A → A`), two-node cycles (`A → B → A`), and deeper loops (`A → B → C → A`) alike.

This ancestor check is bounded by taxonomy depth; it is not a complicated recursive graph-validation subsystem. Closure tables, materialized paths, and lock-based traversal validation remain out of scope.

The seeded taxonomy must contain only the approved hierarchy.

---

# 3.3.4 Category table

Create the migration using the project's existing Laravel conventions.

Recommended structure:

```php
Schema::create('categories', function (Blueprint $table) {
    $table->id();
    $table->foreignId('parent_id')
        ->nullable()
        ->constrained('categories')
        ->nullOnDelete();

    $table->string('name');
    $table->string('slug')->unique();

    $table->enum('space_type', [
        'home',
        'office',
        'hybrid',
    ])->default('home');

    $table->integer('display_order')->default(0);
    $table->boolean('is_active')->default(true);

    $table->timestamps();

    $table->index(['parent_id', 'display_order']);
    $table->index(['is_active', 'display_order']);
    $table->index('space_type');
});
```

Adapt the exact migration syntax to the existing Laravel/database conventions rather than duplicating an already-established index or constraint pattern.

---

# 3.3.5 Category field semantics

## `id`

Server-generated primary key.

Never client-controlled.

## `parent_id`

Nullable foreign key to `categories.id`.

`null` means root category.

Normal delete behavior should detach children rather than cascade-delete an entire taxonomy branch.

Use `nullOnDelete()`.

Do not allow the API layer to create arbitrary recursive structures later without validation.

## `name`

Human-readable category name.

Examples:

```text
Living Room
Sofas
Coffee Tables
Executive Desks
```

Store the display label here.

Do not use `name` as the stable API identifier.

## `slug`

Stable URL/navigation identifier.

Must be unique across the category tree.

Generate and validate slugs server-side according to the project's slug convention.

Do not allow two categories to share the same slug.

## `space_type`

Use:

```text
home
office
hybrid
```

This field describes the primary usage context.

Do not use it as the hierarchy itself.

Do not infer `space_type` automatically from arbitrary category names.

The initial seeded data should assign sensible values according to the taxonomy.

Where a category spans contexts, use `hybrid` rather than duplicating the category merely to support another space.

## `display_order`

Integer used for deterministic sibling ordering.

Lower values appear first unless the application's existing ordering convention specifies otherwise.

Do not use database insertion order as presentation order.

## `is_active`

Controls whether the category is intended to be publicly available in the active catalog.

This is server-controlled.

An inactive category must not automatically mean its historical product associations are deleted.

Do not implement the category activation/deactivation API in this phase.

---

# 3.3.6 `space_type` assignment

Use these initial values:

### Home-oriented

Examples:

```text
Living Room
Bedroom
Dining Room & Kitchen
Outdoor & Patio
Entryway & Accent
```

### Office-oriented

Examples:

```text
Home Office & Corporate Workspaces
```

Use the taxonomy's actual context rather than mechanically assigning every category based on its level.

Use `hybrid` only where a category genuinely serves both home and office/hybrid contexts.

Do not create multiple copies of a category solely to support different `space_type` values.

## Explicit seed mapping

The seed must not invent or infer `space_type` values. The assignment rule is:

* the root container `furnitures-root` is `hybrid` because it spans every context;
* each level-1 room uses its documented value above;
* every level-2 and level-3 category **inherits** the `space_type` of its level-1 room root.

Applying that rule to the canonical taxonomy produces the complete slug-to-`space_type` mapping:

```text
hybrid (1): furnitures-root

home (61): living-room, seating, sofas, sectionals, armchairs, recliners, loveseats, stools-poufs, tables, coffee-tables, end-side-tables, console-tables, nesting-tables, storage-media, tv-stands-showcases, bookcases, display-cabinets, bedroom, beds, platform-beds, canopy-beds, storage-beds, daybeds, bunk-beds, storage, dressers, nightstands, wardrobes, chest-of-drawers, vanity-seating, vanity-tables, bedroom-benches, dining-room-kitchen, dining-sets-tables, dining-tables, kitchen-islands, dining-seating, dining-chairs, bar-counter-stools, dining-benches, dining-storage, sideboards-buffets, bar-carts, china-cabinets, outdoor-patio, outdoor-seating, patio-sofas, loungers, hammocks, outdoor-dining, patio-tables, outdoor-bar-sets, entryway-accent, entryway-furniture, shoe-cabinets, coat-racks, entryway-benches, accent-pieces, accent-tables, accent-chairs, room-dividers

office (14): home-office-corporate-workspaces, desks, executive-desks, standing-adjustable-desks, corner-l-shaped-desks, writing-desks, office-seating, ergonomic-task-chairs, executive-chairs, visitor-chairs, office-storage, filing-cabinets, credenzas, office-bookcases
```

Tests must enforce this mapping for every seeded category.

---

# 3.3.7 Category recommendation relationship

Create a separate:

```text
category_recommendations
```

table.

This is a relational foundation for future category-driven cross-selling.

Required columns:

```text
id
category_id
recommended_category_id
relation_type
priority
created_at
updated_at
```

Use:

```php
Schema::create('category_recommendations', function (Blueprint $table) {
    $table->id();

    $table->foreignId('category_id')
        ->constrained('categories')
        ->cascadeOnDelete();

    $table->foreignId('recommended_category_id')
        ->constrained('categories')
        ->cascadeOnDelete();

    $table->string('relation_type');
    $table->integer('priority')->default(1);

    $table->timestamps();

    $table->unique(
        ['category_id', 'recommended_category_id'],
        'cat_rec_unique'
    );

    $table->index(
        ['category_id', 'relation_type', 'priority']
    );
});
```

The exact relation type storage should follow the project's established CLOSED-enum conventions when this becomes part of a public API contract.

For this phase, establish the canonical vocabulary needed by the supplied model:

```text
COMPLEMENTARY
PAIR_WITH
COMPLETE_THE_LOOK
ALTERNATIVE
```

Do not add recommendation relation types beyond those required by the current design.

---

# 3.3.8 Recommendation directionality

Treat the recommendation relationship as directed:

```text
source category
        ↓
recommended category
```

For example:

```text
Sofas → Coffee Tables
```

does not automatically imply:

```text
Coffee Tables → Sofas
```

unless a separate row exists.

This makes recommendation priority and context controllable independently in future releases.

Do not automatically mirror every recommendation in the seed data.

---

# 3.3.9 Recommendation uniqueness

Prevent duplicate category pair records:

```text
(category_id, recommended_category_id)
```

must be unique.

Therefore this pair cannot appear twice:

```text
Sofas → Coffee Tables
Sofas → Coffee Tables
```

Do not include `relation_type` in the uniqueness constraint unless the business decision explicitly requires multiple relationship types for the same pair.

The supplied design uses one relationship row per category pair, so preserve that behavior.

---

# 3.3.10 Recommendation priority

Use:

```text
priority
```

as an integer.

Higher numbers indicate higher recommendation precedence.

Default:

```text
1
```

Do not implement ranking algorithms in this phase.

Do not interpret priority as product sales volume, popularity, stock level, conversion rate, or machine-learning score.

Those are future recommendation-engine inputs.

---

# 3.3.11 Seed category recommendations

Seed the explicit high-value category relationships supplied for future recommendation/cross-selling use.

The initial conceptual mappings are:

```text
Sofas/Sectionals
→ Coffee Tables
→ End Tables
→ TV Stands
→ Accent Rugs

Beds
→ Nightstands
→ Dressers
→ Wardrobes
→ Bedroom Benches

Dining Tables
→ Dining Chairs
→ Sideboards/Buffets
→ Bar Carts

Standing/Executive Desks
→ Ergonomic Task Chairs
→ Filing Cabinets
→ Desk Organizers

TV Stands/Showcases
→ Sofas
→ Bookcases
→ Media Cabinets

Vanity Tables
→ Accent Mirrors
→ Dressers
→ Bedroom Stools
```

Important:

Only create recommendation mappings where a corresponding canonical category exists in the approved taxonomy.

Do not invent categories solely to satisfy the recommendation table.

For example, because some supplied recommendation names such as:

```text
Accent Rugs
Accent Mirrors
Bedroom Stools
Media Cabinets
Desk Organizers
```

are not explicit categories in the provided taxonomy, **do not silently create them**.

Record these as deferred recommendation mappings until their categories are formally introduced.

This keeps the taxonomy authoritative and prevents recommendation data from creating undocumented categories.

## Canonical seed rows

The conceptual mappings above resolve to canonical taxonomy slugs as follows:

* `Sofas/Sectionals` splits into two sources: `sofas` and `sectionals`.
* `Standing/Executive Desks` splits into two sources: `standing-adjustable-desks` and `executive-desks`.
* `End Tables` resolves to the canonical `end-side-tables` (End/Side Tables).
* `TV Stands` (as a target of `sofas`/`sectionals`) resolves to the canonical `tv-stands-showcases` (TV Stands/Showcases).
* `Bedroom Stools` does **not** resolve to `stools-poufs` (a Living Room seating type); it is deferred below.

The seed contains exactly these rows — one exact source slug and target slug per row:

| source slug | target slug | relation_type | priority |
|---|---|---|---|
| sofas | coffee-tables | COMPLETE_THE_LOOK | 5 |
| sofas | tv-stands-showcases | COMPLETE_THE_LOOK | 4 |
| sofas | end-side-tables | COMPLETE_THE_LOOK | 3 |
| sectionals | coffee-tables | COMPLETE_THE_LOOK | 5 |
| sectionals | tv-stands-showcases | COMPLETE_THE_LOOK | 4 |
| sectionals | end-side-tables | COMPLETE_THE_LOOK | 3 |
| beds | nightstands | COMPLETE_THE_LOOK | 5 |
| beds | dressers | COMPLETE_THE_LOOK | 4 |
| beds | wardrobes | COMPLETE_THE_LOOK | 3 |
| beds | bedroom-benches | COMPLETE_THE_LOOK | 2 |
| dining-tables | dining-chairs | PAIR_WITH | 5 |
| dining-tables | sideboards-buffets | COMPLEMENTARY | 3 |
| dining-tables | bar-carts | COMPLEMENTARY | 2 |
| executive-desks | ergonomic-task-chairs | PAIR_WITH | 5 |
| executive-desks | filing-cabinets | COMPLEMENTARY | 3 |
| standing-adjustable-desks | ergonomic-task-chairs | PAIR_WITH | 5 |
| standing-adjustable-desks | filing-cabinets | COMPLEMENTARY | 3 |
| tv-stands-showcases | sofas | COMPLEMENTARY | 4 |
| tv-stands-showcases | bookcases | COMPLEMENTARY | 2 |
| vanity-tables | dressers | COMPLEMENTARY | 2 |

## Deferred mappings

These supplied targets have no canonical category in the approved taxonomy. They are **not** seeded and **must not** be created as categories. They stay deferred until their categories are formally introduced:

* `Accent Rugs` — requested by `sofas`, `sectionals`
* `Media Cabinets` — requested by `tv-stands-showcases`
* `Desk Organizers` — requested by `executive-desks`, `standing-adjustable-desks`
* `Accent Mirrors` — requested by `vanity-tables`
* `Bedroom Stools` — requested by `vanity-tables`

Tests must enforce the canonical rows exactly: no omitted rows and no additional or non-canonical rows.

---

# 3.3.12 Avoid premature product relationships

Do not create the final product-category association in this phase.

The next catalog schema phase will establish the Product model and determine whether products have:

* one primary category;
* multiple categories;
* a many-to-many category relationship;
* another catalog-specific association.

Phase 3.3 only needs to make categories structurally ready for that future relationship.

Therefore do not create:

```text
product_category
```

or equivalent tables yet unless the existing Product schema already exists and requires it.

The authoritative Group C sequence places Product Schema after Categories Schema.

---

# 3.3.13 Eloquent `Category` model

Create the category model using Laravel relationship types.

At minimum:

```php
public function parent(): BelongsTo
{
    return $this->belongsTo(Category::class, 'parent_id');
}

public function children(): HasMany
{
    return $this->hasMany(Category::class, 'parent_id')
        ->orderBy('display_order');
}

public function recommendedCategories(): BelongsToMany
{
    return $this->belongsToMany(
        Category::class,
        'category_recommendations',
        'category_id',
        'recommended_category_id'
    )
    ->withPivot('relation_type', 'priority')
    ->orderByPivot('priority', 'desc');
}

public function recommendedByCategories(): BelongsToMany
{
    return $this->belongsToMany(
        Category::class,
        'category_recommendations',
        'recommended_category_id',
        'category_id'
    )
    ->withPivot('relation_type', 'priority');
}
```

Use the inverse recommendation relationship so future recommendation queries do not require manually rebuilding the join.

Do not add product relationships yet.

---

# 3.3.14 Model constraints and mass assignment

Follow the project's mass-assignment rules.

Do not use:

```php
$request->all()
```

to create or update categories.

When category write operations are introduced later:

```text
validated input
→ explicit allow-list
→ DTO/command/domain logic
→ persistence
```

Server-controlled fields include:

* `id`;
* timestamps;
* recommendation records;
* relationship ownership;
* any future audit fields.

The established backend convention explicitly prohibits request-wide mass assignment.

---

# 3.3.15 Category naming and slug consistency

Seed category names exactly according to the approved taxonomy.

Generate deterministic slugs.

Examples:

```text
Living Room
→ living-room

Coffee Tables
→ coffee-tables

Home Office & Corporate Workspaces
→ home-office-corporate-workspaces
```

Do not change a canonical category name merely to produce a shorter slug.

Do not use numeric category IDs as public URLs.

Do not create duplicate categories with different capitalization solely because of slug differences.

---

# 3.3.16 Seed ordering

Set deterministic `display_order` values for siblings.

Example:

```text
Living Room
  Seating             1
  Tables              2
  Storage & Media     3
```

Likewise, order the Level-1 room categories deterministically.

The exact numeric spacing may use simple sequential values.

Do not depend on auto-increment IDs for UI ordering.

When a category has children, their order must be deterministic in the seed data.

---

# 3.3.17 Seed strategy

Use idempotent deterministic seeders.

The seeding process should be safe for local database reconstruction.

Do not rely on hard-coded numeric IDs.

Resolve parent relationships by stable slug or another deterministic key.

Recommendation seeds should likewise resolve both categories by stable identifiers rather than assumed primary-key values.

Do not seed production-like products, orders, users, or recommendations involving nonexistent products.

---

# 3.3.18 Database integrity

Add and test:

* primary key on `categories.id`;
* foreign key `categories.parent_id → categories.id`;
* unique category slug;
* foreign keys from `category_recommendations`;
* cascading deletion of recommendation rows when a referenced category is removed;
* nulling of `parent_id` when a category parent is removed;
* unique recommendation pair;
* useful hierarchy/recommendation indexes.

Test both sides of the recommendation relationship.

---

# 3.3.19 Authorization considerations

This phase does not create category-management endpoints.

The future public catalog remains explicitly public.

Future staff/admin category-management operations must use the RBAC foundation from Phase 3.2 and explicit catalog permissions, rather than client-controlled role fields or frontend checks.

The authorization architecture requires explicit permissions such as `products.view` / `products.manage`, with authorization still evaluated together with resource/action/context rather than role alone.

Do not implement those domain policies or endpoints here.

---

# 3.3.20 Tests

Create focused tests for the schema and model.

## Migration tests

Verify:

* fresh migration succeeds;
* rollback succeeds;
* categories can exist with `parent_id = null`;
* child category can reference parent category;
* parent deletion nulls child `parent_id`;
* duplicate slug is rejected;
* recommendation foreign keys are enforced;
* recommendation pair uniqueness is enforced.

## Hierarchy tests

Verify:

* root category has no parent;
* child resolves its parent;
* parent resolves its children;
* children are ordered by `display_order`;
* self-parenting is rejected by application validation;
* seeded hierarchy has the intended structure.

## Recommendation tests

Verify:

```text
Category A → Category B
```

is returned by `recommendedCategories`.

Verify the inverse relationship:

```text
Category B ← Category A
```

is returned by `recommendedByCategories`.

Verify:

* pivot `relation_type` is available;
* pivot `priority` is available;
* priority ordering is deterministic;
* duplicate pair records are rejected.

## Seed tests

Verify:

* all approved root categories exist;
* all approved second-level categories exist;
* all approved third-level categories exist;
* each child points to the intended parent;
* slugs are unique;
* recommendation mappings only reference categories that actually exist;
* deferred mappings are not represented by undocumented fake categories.

---

# 3.3.21 Category API readiness

The database and model should be ready for a later public category-read API.

The later API may expose:

```text
category
parent
children
slug
space_type
display_order
is_active
```

but this phase must not implement those endpoints.

Do not add:

```text
GET /categories
GET /categories/{slug}
POST /categories
PATCH /categories/{id}
DELETE /categories/{id}
```

yet.

The category read API belongs to the later catalog API work.

---

# 3.3.22 Recommendation API readiness

This phase must make future recommendation queries straightforward but must not implement a recommendation engine.

The future system should be able to conceptually perform:

```text
Product
→ primary/associated Category
→ recommended Categories
→ eligible Products
→ availability/inventory filtering
→ ranking
→ recommendation response
```

That is a future application/query concern.

Do not implement:

* random product selection;
* "frequently bought together";
* sales-history analysis;
* collaborative filtering;
* machine learning;
* recommendation scoring;
* product ranking;
* inventory-aware recommendation selection;
* product exclusion rules.

The category relationship table is merely the durable rule/configuration layer.

---

# Security and correctness checks

Verify that:

* category IDs are server-controlled;
* recommendation IDs are server-controlled;
* no client input can assign arbitrary category ownership;
* no API layer trusts a client-supplied role for category administration;
* no mass-assignment path can manipulate server-controlled fields;
* database relationships cannot be silently bypassed;
* invalid parent relationships are rejected before persistence;
* recursive/cyclic relationships are not permitted through the future category write boundary.

Do not expose internal database exceptions through API responses.

Follow the established error and logging discipline.

---

# Code-quality requirements

Keep the implementation small and cohesive.

Expected components:

```text
Category model
Category migration
CategoryRecommendation migration
Category seed data
Recommendation seed data
Focused tests
```

Avoid:

* recommendation service classes;
* recommendation controllers;
* category APIs;
* product APIs;
* search infrastructure;
* recursive tree utility frameworks;
* graph databases;
* caching layers;
* machine-learning infrastructure;
* generic taxonomy abstractions.

Use meaningful names and existing Laravel conventions.

Keep code comments to the absolute minimum.

Prefer expressive model relationships, seed data, tests, and clear names over explanatory comment blocks.

---

# Documentation / decision record

Update `docs/decisions.md` only for durable decisions that are genuinely architectural, such as:

* adjacency-list hierarchy chosen for V1;
* recommendation relationships modeled as a directed relational table;
* recommendation mappings intentionally separated from product associations;
* unsupported recommendation targets deferred rather than creating undocumented categories.

Do not create a permanent Phase-3.3 markdown document merely to duplicate this phase instruction.

---

# Explicitly out of scope

Do not implement:

* Product model;
* product-category associations;
* Product Variants;
* Product Images;
* Inventory;
* category CRUD API;
* category admin UI;
* category frontend pages;
* Flutter category screens;
* recommendation API;
* recommendation engine;
* product recommendation ranking;
* sales-based recommendations;
* "Frequently Bought Together" calculation;
* machine-learning recommendations;
* full-text category search;
* breadcrumb API;
* category caching;
* URL routing implementation;
* category authorization policies.

These belong to later phases.

---

# Definition of done

Phase 3.3 is complete only when:

1. `categories` exists with the approved adjacency-list structure.
2. Root categories support `parent_id = null`.
3. Parent/child relationships work correctly.
4. The approved furniture taxonomy is seeded deterministically.
5. Category slugs are unique and deterministic.
6. `space_type`, `display_order`, and `is_active` are persisted.
7. Category ordering is deterministic.
8. Self-parenting is prevented.
9. `category_recommendations` exists as a directed relational mapping.
10. Recommendation pair uniqueness is enforced.
11. Recommendation priority is persisted.
12. The Category model exposes parent/children relationships.
13. The Category model exposes both recommendation directions.
14. Recommendation seeds only reference categories that actually exist.
15. Undocumented recommendation target categories are explicitly deferred rather than invented.
16. Migrations run successfully from an empty database and roll back successfully.
17. Model, hierarchy, recommendation, and seed tests pass.
18. Existing formatting, static analysis, and test suites pass.
19. No product/recommendation API or business logic has been pulled into this phase.
20. Code comments remain minimal.

---

# STOP condition

Stop after the category schema, recommendation mapping schema, models, deterministic seed data, migrations, and tests are complete.

Do not continue into Phase 3.4 Products Schema.

Do not implement product-category associations before the Product schema establishes the appropriate relationship model.

Do not implement recommendation APIs or algorithms.

Do not commit, stage, or push changes.
