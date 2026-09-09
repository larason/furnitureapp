# Group C phases instructions

# Phase 3.4 — Products Schema

## Purpose

Implement the core `products` catalog entity and establish the relational foundation required by the subsequent product phases.

This phase must prepare the catalog for:

* furniture product descriptions and metadata;
* category association;
* product variants;
* variant-level pricing;
* variant dimensions and physical specifications;
* materials and configurable attributes;
* inventory/stock;
* product images and staged-room media;
* 3D and AR assets;
* future room-style compatibility and recommendation features.

The important architectural boundary is:

> `products` represents the parent catalog concept. A sellable SKU, its price, physical dimensions, and stock are variant/inventory concerns and must not be collapsed into the base product.

The Group C roadmap explicitly places Product Schema before Product Variants, Product Images, and Inventory, so this phase must establish the parent structure without prematurely implementing those later schemas.

---

# Dependencies

Required:

* Phase 2.1–2.12 completed.
* Phase 3.1 Users Schema completed.
* Phase 3.2 Roles/permissions model completed.
* Phase 3.3 Categories Schema completed.
* Existing migration, testing, formatting, and static-analysis conventions operational.

Authoritative inputs:

* `docs/VISION.md`
* `docs/api/api-contract.md`
* `docs/api/api-resources.md`
* `docs/api/api-conventions.md`
* `docs/domain/business-rules.md`
* `docs/decisions.md`
* `AGENTS.md`

Do not reopen unrelated API, authentication, payment, or checkout decisions.

---

# 3.4.1 Product entity responsibility

`products` is the parent catalog record.

It describes the conceptual item customers browse, such as:

```text id="u8g9ai"
Nordic Velvet Lounge Chair
```

A product may later have multiple sellable variants:

```text id="m0u3m1"
Forest Green / Natural Oak
Charcoal / Black Oak
Natural Beige / Walnut
```

The product record itself must therefore **not** become the source of truth for:

* variant-specific price;
* variant-specific SKU;
* variant-specific stock;
* variant-specific dimensions where those dimensions differ between variants.

Those belong to later product-variant/inventory phases.

---

# 3.4.2 Core products table

Create:

```text id="t4i5wc"
products
```

with the following conceptual structure:

```text id="x22qj8"
id
category_id
name
slug
sku_prefix
short_description
description
brand
room_type
assembly_required
primary_material
is_active
is_featured
timestamps
soft-deletion metadata
```

Recommended Laravel migration:

```php id="8mjz3f"
Schema::create('products', function (Blueprint $table) {
    $table->id();

    $table->foreignId('category_id')
        ->constrained('categories')
        ->restrictOnDelete();

    $table->string('name');
    $table->string('slug')->unique();
    $table->string('sku_prefix')->nullable()->unique();

    $table->text('short_description')->nullable();
    $table->longText('description')->nullable();

    $table->string('brand')->nullable();
    $table->string('room_type')->nullable();
    $table->string('assembly_required')->default('none');
    $table->string('primary_material')->nullable();

    $table->boolean('is_active')->default(true);
    $table->boolean('is_featured')->default(false);

    $table->timestamps();
    $table->softDeletes();

    $table->index(['category_id', 'is_active']);
    $table->index(['is_active', 'is_featured']);
    $table->index('room_type');
});
```

Adapt exact syntax to existing project conventions.

Do not blindly copy the supplied migration if the existing Laravel version/project conventions require a different constraint method.

---

# 3.4.3 Category relationship

A product has one primary catalog category in this phase:

```text id="w6iw5k"
Category 1 ─── N Products
```

Use:

```php id="x2n0wu"
public function category(): BelongsTo
{
    return $this->belongsTo(Category::class);
}
```

Do not create a `product_categories` many-to-many table yet.

This phase must establish the simplest authoritative relationship consistent with the current catalog design.

A future requirement for products to belong to multiple categories can be introduced as a compatibility-reviewed schema change.

Do not build that complexity speculatively.

---

# 3.4.4 Category deletion behavior

Do not cascade-delete products when a category is deleted.

Products are durable catalog entities and may later participate in:

* orders;
* order-item snapshots;
* requests;
* inventory;
* historical reporting;
* recommendations.

Therefore prefer:

```text id="48rxz3"
Category deleted
→ product remains
→ deletion is restricted or handled through an explicit future category-management workflow
```

Use `restrictOnDelete()` or the project's equivalent restrictive foreign-key behavior.

Do not use:

```php id="7ymxm6"
->cascadeOnDelete()
```

for `products.category_id`.

A category removal must not silently destroy product data.

---

# 3.4.5 Product identity

## `id`

Server-generated primary key.

Never client-controlled.

## `name`

Customer-facing product name.

Examples:

```text id="84v0w7"
Nordic Velvet Lounge Chair
Walnut Executive Desk
Modern Oak Dining Table
```

Use the existing project string-length convention.

## `slug`

Stable unique catalog URL identifier.

Examples:

```text id="f8h0q5"
nordic-velvet-lounge-chair
walnut-executive-desk
modern-oak-dining-table
```

Must be unique.

Do not use product IDs as SEO URLs.

Do not make slugs dependent on variant names.

## `sku_prefix`

Optional product-level identifier prefix.

Example:

```text id="y19qg2"
SOF-NORDIC
```

This is **not** a sellable SKU.

Actual sellable SKU belongs to Phase 3.5 Product Variants.

Use this field only as an optional product-level naming/reference aid.

Do not use it for inventory identity.

Do not require it to exist for every product.

If uniqueness is enabled, uniqueness must apply to non-null values according to the database engine's semantics.

---

# 3.4.6 Product descriptions

### `short_description`

Compact catalog summary.

Suitable for:

* listing cards;
* category pages;
* search results;
* short product previews.

### `description`

Full product description.

Suitable for:

* product details;
* material/usage information;
* furniture characteristics;
* customer-facing long-form content.

Do not treat either field as trusted HTML.

Future rich content must have a defined sanitization/rendering contract.

Do not implement a rich-text editor or HTML sanitization pipeline in this phase.

---

# 3.4.7 Brand

`brand` is nullable.

Keep it as a simple string in V1.

Do not create a brands table in Phase 3.4.

Do not assume every furniture business item has a standardized external brand entity.

A dedicated Brand schema can be introduced later if the business requirements justify it.

---

# 3.4.8 Room context

Use:

```text id="q7sblf"
room_type
```

as a product-level contextual hint.

Examples:

```text id="p93q2f"
Living Room
Bedroom
Dining Room
Office
Outdoor
Entryway
Hybrid
```

However, do not duplicate the category hierarchy unnecessarily.

The category remains the authoritative catalog classification.

`room_type` exists to support future room-context discovery and compatibility.

Do not turn `room_type` into an uncontrolled collection of multiple values in this phase.

Do not create a `rooms` table yet.

Do not make `room_type` the recommendation engine.

Do not make product recommendations depend solely on matching `room_type`.

---

# 3.4.9 Room-style compatibility readiness

The product model must remain extensible for future room-staging and recommendation metadata.

Do not add arbitrary JSON such as:

```json id="fv7wxd"
{
  "room_styles": [
    "modern",
    "minimalist",
    "scandinavian"
  ]
}
```

as the authoritative room-style system.

At this phase, do not create the full room-style taxonomy.

Instead:

* retain `room_type` as a simple current product attribute;
* keep the product entity independent from future staging entities;
* leave room-style compatibility for a later schema decision.

Future structures may include normalized compatibility records or a controlled attribute system once requirements are sufficiently defined.

Do not prematurely create either a complex style graph or machine-learning recommendation fields.

---

# 3.4.10 Assembly requirements

Use:

```text id="5v4bny"
assembly_required
```

with the V1 controlled values:

```text id="jng1w7"
none
partial
full
```

Do not use unrestricted strings for these known business values.

Prefer a Laravel enum/cast or equivalent centralized constant representation if consistent with the existing project.

Reject unknown values.

Do not add additional assembly states such as:

```text id="0o0wwq"
SELF_ASSEMBLY
PROFESSIONAL_ONLY
OPTIONAL
```

unless the V1 contract explicitly expands.

---

# 3.4.11 Primary material

Use:

```text id="8os3ac"
primary_material
```

as a customer-facing descriptive field.

Examples:

```text id="c1n3h8"
Solid Oak
Velvet & Solid Oak
Solid Teak
Engineered Wood
```

This field is a summary, not a complete materials database.

The project conventions already distinguish furniture material/color from CLOSED enums and permit bounded free text for such descriptive attributes.

Do not create:

```text id="g57f89"
materials
product_materials
material_types
```

in this phase.

A future normalized material structure can be introduced when the requirements justify filtering, composition percentages, supplier data, or material-specific behavior.

---

# 3.4.12 Variations readiness

The product schema must prepare for variants without duplicating variant data.

Do not add:

```text id="5k22w1"
color
fabric
finish
size
variant_price
variant_sku
variant_stock
variant_width
variant_height
variant_depth
```

directly to `products`.

Those characteristics belong to `product_variants` in Phase 3.5.

Conceptually:

```text id="5svz9j"
Product
├── Variant A
│   ├── SKU
│   ├── attributes
│   ├── dimensions
│   └── price
├── Variant B
│   ├── SKU
│   ├── attributes
│   ├── dimensions
│   └── price
└── Variant C
    ├── SKU
    ├── attributes
    ├── dimensions
    └── price
```

This avoids storing multiple potentially conflicting sources of truth.

---

# 3.4.13 Pricing readiness

Do **not** add a product-level `price` field merely because a product has a price.

The project's API conventions define money as integer minor units:

```json id="y1npf4"
{
  "amount": 35000000,
  "currency": "TZS"
}
```

with `1 TZS = 100` minor units.

Financial authority is server-side. Client-supplied totals/prices are not authoritative.

Actual sellable pricing belongs to the Product Variant schema.

Therefore Phase 3.4 must **not** create:

```text id="w5y0fq"
decimal price
decimal compare_at_price
decimal cost_price
```

on `products`.

Phase 3.5 must decide the exact variant-level financial representation using the already-established money convention.

This prevents inconsistent decimal-vs-minor-unit implementations across the catalog.

---

# 3.4.14 Inventory readiness

Do not add inventory quantities to `products`.

Do not add:

```text id="3q9n3l"
stock
quantity
reserved_quantity
available_quantity
warehouse_location
```

to `products`.

Inventory is variant-specific and is scheduled as Phase 3.7.

The eventual model should conceptually support:

```text id="r4tsf9"
Product
   ↓
Product Variant
   ↓
Inventory record(s)
```

This is necessary because two variants of the same sofa can have different stock.

Client inputs must never become authoritative inventory quantities. The server owns availability, stock, reservation, and consumption.

---

# 3.4.15 Product media readiness

Do not create `product_media` in this phase.

The roadmap assigns product images/media to Phase 3.6.

The Product model should later expose relationships such as:

```text id="xggfqt"
Product → media
```

without placing media paths directly in `products`.

Do not add:

```text id="ac7ltu"
image_url
video_url
thumbnail_url
staged_room_url
```

to the product table.

This keeps product metadata separate from physical media records.

---

# 3.4.16 3D/AR readiness

Do not add 3D or AR file paths directly to `products`.

Do not create:

```text id="9fwb0d"
glb_url
usdz_url
gltf_url
3d_model_path
ar_model_path
```

inside the base product table.

The future `product_assets` structure should own these files.

Therefore do not add `has_3d_model` as an authoritative boolean in Phase 3.4.

A derived property can later answer:

```text id="yoywip"
Product has eligible 3D/AR assets
```

based on related asset records.

Avoid duplicated state such as:

```text product.has_3d_model = false
product_assets contains GLB
```

which can become inconsistent.

---

# 3.4.17 Product active/featured state

Use:

```text id="n2i4iu"
is_active
is_featured
```

### `is_active`

Controls whether the product is active in the catalog.

It is server-controlled.

Do not expose a client-controlled field that can make a product active through ordinary public requests.

### `is_featured`

Controls whether the product is intentionally highlighted.

It is a merchandising flag, not an algorithmic ranking.

Do not interpret `is_featured` as:

* best seller;
* highest margin;
* most recommended;
* highest inventory;
* sponsored product.

Those are separate concerns.

---

# 3.4.18 Soft deletion

Use soft deletes for products:

```php id="ed5z2b"
$table->softDeletes();
```

The purpose is preservation of catalog history and protection against destructive deletion of entities that can later participate in business records.

Do not implement hard-delete workflows.

Do not add cascading business-data deletion.

Later phases must account for soft-deleted products when querying:

* public catalog;
* product variants;
* inventory;
* product media;
* historical order data.

---

# 3.4.19 Product model

Create:

```text id="7o5z9m"
App\Models\Product
```

with relationships required at this phase:

```php id="lbj0s3"
public function category(): BelongsTo
{
    return $this->belongsTo(Category::class);
}
```

Do not add relationships to models that do not exist yet.

Do not pre-create empty relationships to:

```text ProductVariant
ProductMedia
ProductAsset
ProductStock
```

unless the corresponding classes/tables already exist as part of an existing implementation.

Those relationships should be introduced in their respective phases.

---

# 3.4.20 Product casts

Use appropriate casts for:

```text id="vs6js8"
is_active
is_featured
```

and `assembly_required` if represented by a Laravel enum.

Do not cast customer-visible strings into arbitrary custom structures without a defined contract.

Do not add a JSON `attributes` field to `products`.

Variant attributes belong to Product Variants.

---

# 3.4.21 Product attributes and extensibility

The product table should contain only attributes that are genuinely product-level.

Good examples:

```text id="r6t2b7"
name
brand
room_type
assembly_required
primary_material
```

Avoid turning `products` into a flexible attribute warehouse containing:

```text id="z3n5i7"
color
size
width
height
weight
fabric
finish
stock
sale_price
3d_model
room_style
warehouse
```

This is precisely why the later variant, inventory, media, and asset schemas exist.

Do not introduce a generic EAV schema in this phase.

Do not introduce an unbounded JSON metadata field as a substitute for proper relational design.

---

# 3.4.22 Product slug and naming integrity

Validate:

* product name is non-empty;
* slug is unique;
* slug is normalized according to project convention;
* slug conflicts are detected;
* slug is not generated from variant names;
* product ID remains the stable internal identifier.

Do not allow multiple active products with the same slug.

Avoid silently overwriting an existing product when generating slugs.

---

# 3.4.23 Product/category integrity

The product's category must reference an existing category.

A product must not reference an inactive/nonexistent category through invalid foreign keys.

The application layer must decide, in a future write API, whether a product may be assigned to an inactive category.

Do not implement that product-management policy now.

Database integrity handles existence; domain authorization/validation handles business rules.

---

# 3.4.24 Future recommendation readiness

The schema must support the future flow:

```text id="gso9eq"
Product
   ↓
Primary Category
   ↓
Category Recommendations
   ↓
Eligible Product Variants
   ↓
Inventory/availability
   ↓
Recommendation ranking
```

Phase 3.4 must not add recommendation-specific fields such as:

```text id="l6c0p6"
recommendation_score
recommended_product_ids
frequently_bought
cross_sell_score
ml_embedding
```

The Category recommendation graph from Phase 3.3 remains independent of the Product table.

Do not duplicate category recommendation mappings into products.

---

# 3.4.25 Future room-staging readiness

The base product should remain compatible with later staging relationships.

Conceptually:

```text id="la33bv"
Product
   ├── Product Media
   │      └── staged room images
   │
   └── Product Assets
          ├── GLB
          ├── GLTF
          └── USDZ
```

Potential future metadata may include:

```text id="a0ag8g"
room style
interior theme
placement suitability
staging context
AR availability
```

but these should belong to dedicated future structures rather than becoming an uncontrolled `products` JSON blob.

Do not create the full staging metadata model in Phase 3.4.

---

# 3.4.26 Product seeding

Create minimal deterministic product factory support only as needed for testing.

Do not seed the production furniture catalog yet unless the project already defines authoritative seed products.

If development seed products are required:

* associate them with real seeded categories;
* use realistic names;
* generate deterministic slugs;
* do not create variants;
* do not create stock;
* do not create media;
* do not create AR assets;
* do not invent production pricing.

Factories must not accidentally imply that variant/stock/media functionality already exists.

---

# 3.4.27 Database indexes

Add indexes for the access patterns expected from the base catalog:

```text id="yz6skc"
slug
category_id
category_id + is_active
is_active + is_featured
room_type
```

Do not add speculative indexes for future variant/inventory queries to the products table.

Later schema phases should index their own tables according to actual access patterns.

---

# 3.4.28 Database constraints

Enforce:

* primary key on `id`;
* foreign key from `category_id` to `categories.id`;
* restrictive category deletion behavior;
* unique `slug`;
* appropriate uniqueness for `sku_prefix` if retained as a product-level identifier;
* non-null required product identity fields;
* valid default for `assembly_required`.

Do not encode complex recommendation, inventory, or variant business rules in database constraints at this stage.

---

# 3.4.29 Security and mass assignment

When product write APIs are introduced later:

```text id="oz8ndm"
validated request
→ explicit product allow-list
→ DTO/command
→ domain validation/authorization
→ persistence
```

Never use:

```php id="0x6q3d"
$request->all()
```

to hydrate a Product model.

Client must never directly control:

* IDs;
* timestamps;
* soft-delete timestamps;
* future inventory fields;
* future order/business state;
* server-derived product relationships;
* authorization fields.

The project's backend conventions explicitly prohibit request-wide mass assignment and require explicit allow-lists.

---

# 3.4.30 API serialization readiness

Do not implement a Product API in this phase.

However, the model must remain compatible with the project's API serialization rules.

A future public Product representation may contain:

```text id="k5j6da"
id
name
slug
category
short_description
description
brand
room_type
assembly_required
primary_material
is_featured
variants
media
3d/ar assets
availability
```

but those fields must be assembled through explicit representation/resource classes rather than:

```php id="i6ht0w"
$model->toArray()
```

The project requires field-level serialization before output and prohibits indiscriminate model serialization, particularly when later product data includes stock, internal cost, storage paths, or other sensitive fields.

In particular, future internal variant `cost_price`, inventory quantities, storage keys, and internal operational metadata must not automatically enter public catalog responses.

---

# 3.4.31 Tests

Create focused tests alongside implementation.

## Migration tests

Verify:

* products migration succeeds on an empty database;
* products migration rolls back;
* valid category can own products;
* invalid `category_id` is rejected by the foreign key;
* deleting a referenced category does not cascade-delete products;
* slug uniqueness is enforced;
* indexes/constraints are created as intended;
* soft deletes function correctly.

## Model tests

Verify:

* `Product → category`;
* category association resolves correctly;
* active/featured casts return booleans;
* assembly requirement representation is valid;
* soft-deleted products are excluded by default;
* `withTrashed()` behavior works where appropriate.

## Integrity tests

Verify that:

* no product requires a product variant at the base-schema level;
* no product requires inventory to exist;
* no product requires media;
* no product requires 3D/AR assets;
* product-level price does not become a second financial source of truth;
* variant-only attributes are not stored as product columns.

## Seed/factory tests

Where factories exist, verify:

* generated product has a valid category;
* slugs are unique;
* factory does not fabricate future-phase relationships.

---

# 3.4.32 Static analysis and quality

Run the established project checks:

```text id="qbd7hk"
format check
static analysis
unit tests
feature tests
```

Fix all findings introduced by this phase.

Do not broaden the changes to unrelated code.

Do not suppress analyzer warnings globally.

Do not add large abstraction frameworks for a single Product model.

Keep comments to the absolute minimum.

---

# Explicitly out of scope

Do not implement:

* Product Variant schema;
* variant attributes;
* variant SKU generation;
* variant pricing;
* compare-at pricing;
* internal cost pricing;
* variant dimensions;
* variant weight;
* inventory/stock tables;
* warehouse schema;
* product images;
* product video;
* staged-room media;
* 3D assets;
* AR assets;
* material normalization;
* room-style taxonomy;
* staging metadata schema;
* product recommendation algorithms;
* recommendation API;
* product catalog API;
* Product CRUD endpoints;
* staff product-management endpoints;
* Admin product-management endpoints;
* search/filter API;
* SEO API;
* Next.js product pages;
* Flutter product screens;
* product availability calculations.

These belong to later phases.

---

# Definition of done

Phase 3.4 is complete only when:

1. `products` exists as the parent catalog entity.
2. Each product has a valid primary category.
3. Category deletion cannot silently destroy products.
4. Product names and slugs are modeled correctly.
5. Optional product-level SKU prefix is available without being treated as a sellable SKU.
6. Product descriptions are separated into short and full descriptions.
7. Brand is supported as optional product-level metadata.
8. `room_type` is available for future room-context behavior without replacing category taxonomy.
9. `assembly_required` uses the agreed controlled values.
10. `primary_material` supports customer-facing material description without pretending to be a normalized materials database.
11. `is_active` and `is_featured` exist with server-controlled semantics.
12. Soft deletion is supported.
13. Product/category Eloquent relationship exists.
14. Indexes and foreign-key constraints are appropriate.
15. Variant-specific price, SKU, dimensions, and attributes are not duplicated onto products.
16. Inventory quantities are not stored on products.
17. Media and AR storage paths are not stored on products.
18. Product structure is ready for later Product Variant, Product Media, Product Asset, and Inventory relationships.
19. Tests cover migration, category integrity, model relationships, soft deletion, and core constraints.
20. Formatting, static analysis, and the existing test suite pass.
21. No product API or later-phase business functionality has been implemented.
22. Code comments remain minimal.

---

# STOP condition

Stop after the base Product schema, Product model, migration/factory support, constraints, indexes, and tests are complete.

Do not continue into Phase 3.5 Product Variants.

Do not implement pricing, stock, media, 3D/AR assets, product recommendation logic, or product APIs.

Do not commit, stage, or push changes.
