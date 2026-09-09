# Group C phases instructions

# Phase 3.5 — Product Variants Schema

## Purpose

Implement the `product_variants` schema and model as the canonical representation of a product's distinct sellable configurations.

A Product is the parent catalog concept.

A Product Variant represents a distinct purchasable configuration such as:

```text
3-Seater / Forest Green / Velvet
2-Seater / Natural Beige / Linen
King / Walnut / Solid Oak
```

Each variant may have its own:

* SKU;
* customer-facing variant name;
* price;
* compare-at price;
* internal cost price;
* dimensions;
* weight;
* configurable attributes;
* default/display status.

This phase must prepare the database for the later:

* product images/media phase;
* inventory phase;
* cart phase;
* checkout/order item snapshot phase;
* public Variant API phase.

The variant is the pricing and sellable-unit boundary. Inventory remains separate.

---

# Dependencies

Required before starting:

* Phase 2.1–2.12 completed.
* Phase 3.1 Users Schema completed.
* Phase 3.2 Roles/permissions model completed.
* Phase 3.3 Categories Schema completed.
* Phase 3.4 Products Schema completed.
* Existing Laravel migration/model/test conventions operational.

Authoritative inputs:

* `docs/VISION.md`
* `docs/api/api-contract.md`
* `docs/api/api-resources.md`
* `docs/api/api-conventions.md`
* `docs/domain/business-rules.md`
* `docs/decisions.md`
* `AGENTS.md`

Do not reopen unrelated authentication, authorization, checkout, payment, or order decisions.

---

# 3.5.1 Variant responsibility

`product_variants` is the canonical representation of a product's sellable configuration.

Conceptually:

```text
Product
├── Variant A
│   ├── SKU
│   ├── price
│   ├── dimensions
│   └── attributes
├── Variant B
│   ├── SKU
│   ├── price
│   ├── dimensions
│   └── attributes
└── Variant C
    ├── SKU
    ├── price
    ├── dimensions
    └── attributes
```

Do not duplicate variant-specific data into `products`.

Do not place the following on `products`:

```text
sku
price
compare_at_price
cost_price
color
fabric
finish
size
width
height
depth
weight
```

The Product table remains the parent catalog entity established in Phase 3.4.

---

# 3.5.2 Variant table

Create:

```text
product_variants
```

with this structure:

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

created_at
updated_at
```

Recommended Laravel migration:

```php
Schema::create('product_variants', function (Blueprint $table) {
    $table->id();

    $table->foreignId('product_id')
        ->constrained('products')
        ->cascadeOnDelete();

    $table->string('sku')->unique();
    $table->string('variant_name');

    $table->unsignedBigInteger('price_amount');
    $table->char('price_currency', 3)->default('TZS');

    $table->unsignedBigInteger('compare_at_price_amount')->nullable();
    $table->char('compare_at_price_currency', 3)->nullable();

    $table->unsignedBigInteger('cost_price_amount')->nullable();
    $table->char('cost_price_currency', 3)->nullable();

    $table->decimal('width_cm', 10, 2)->nullable();
    $table->decimal('height_cm', 10, 2)->nullable();
    $table->decimal('depth_cm', 10, 2)->nullable();
    $table->decimal('weight_kg', 10, 2)->nullable();

    $table->json('attributes')->nullable();

    $table->boolean('is_default')->default(false);
    $table->boolean('is_active')->default(true);
    $table->unsignedInteger('display_order')->default(0);

    $table->timestamps();

    $table->index(['product_id', 'is_active', 'display_order']);
    $table->index(['product_id', 'is_default']);
});
```

Adapt exact Laravel syntax to the project's established migration conventions.

Do not blindly introduce duplicate indexes if an existing migration convention already covers them.

---

# 3.5.3 Product relationship

Every variant belongs to exactly one product:

```text
Product 1 ─── N ProductVariants
```

Implement:

```php
public function product(): BelongsTo
{
    return $this->belongsTo(Product::class);
}
```

On `Product` add:

```php
public function variants(): HasMany
{
    return $this->hasMany(ProductVariant::class);
}
```

Do not expose variants through a global unscoped relationship when the intended operation is under a product.

The future canonical variant resource is nested:

```text
/api/v1/products/{product}/variants
/api/v1/products/{product}/variants/{variant}
```

and a variant detail lookup must be constrained to its parent product.

This prevents a valid Variant ID belonging to Product B from being accepted under Product A.

---

# 3.5.4 Foreign-key delete behavior

A product's variants represent child catalog configurations.

For this phase, use:

```text
Product deletion
→ Variant deletion
```

through the normal relational cascade because a variant has no independent meaning without its parent product.

However, do not implement destructive product deletion workflows in this phase.

The database relationship exists so that future product lifecycle operations cannot leave orphan variants.

Historical order records must not depend on live variant rows for their financial identity; that protection belongs to the later Order Item Snapshot phase.

---

# 3.5.5 SKU

`sku` is the authoritative machine identifier for a sellable variant.

Examples:

```text
SOFA-NORDIC-3S-GRN
SOFA-NORDIC-3S-BEI
BED-OAK-KING-WAL
```

Requirements:

* required;
* unique globally;
* stable;
* server-controlled;
* never reused casually.

The `sku_prefix` from `products` may be used when generating human-readable SKU conventions later, but it is not the SKU itself.

Do not permit two variants to share the same SKU.

Do not use `variant_name` as the SKU.

Do not use database IDs as SKU values.

---

# 3.5.6 Variant ID

Variant primary key is server-generated.

Do not expose any mechanism allowing clients to choose a variant ID.

The variant ID is the machine identifier used by later cart/order/catalog APIs.

A client selecting:

```json
{
  "variant_id": 501
}
```

is expressing purchase intent only.

The backend must resolve that ID under the relevant Product and validate that the variant belongs to that Product.

---

# 3.5.7 Variant name

`variant_name` is the customer-facing label for the configuration.

Examples:

```text
Forest Green / Oak
3-Seater / Emerald Green / Velvet
King / Walnut
Natural Beige / Linen
```

It is descriptive rather than authoritative.

Do not encode price, stock, or business state into the string.

Do not parse `variant_name` to determine color, size, material, or dimensions.

Those values belong in structured fields.

---

# 3.5.8 Pricing model

Pricing belongs to the variant.

The project's frozen V1 money convention requires:

```json
{
  "amount": 35000000,
  "currency": "TZS"
}
```

where the amount is an integer in minor units and `1 TZS = 100` minor units. It applies to product and variant pricing, and a bare decimal price or formatted currency string is not valid.

Therefore the database should use integer amount fields rather than:

```text
DECIMAL(12,2)
```

for money.

Use:

```text
price_amount
price_currency
```

rather than a serialized JSON money object in the database.

This separates:

```text
Database storage
→ integer amount + currency

API representation
→ {amount, currency}
```

without losing exactness.

---

# 3.5.9 Variant price

`price_amount` is required.

`price_currency` defaults to:

```text
TZS
```

At V1, the expected catalog currency is TZS.

Do not allow arbitrary client-provided currencies to change the product's financial meaning.

Do not calculate price from:

```text
variant_name
attributes
product name
category
inventory
```

Price must be explicitly persisted as the authoritative variant price.

---

# 3.5.10 Compare-at price

Support optional:

```text
compare_at_price_amount
compare_at_price_currency
```

for merchandising/display of a prior or reference price.

Rules:

* both amount and currency are null together when absent;
* if amount exists, currency must exist;
* compare-at price is not the authoritative checkout price;
* actual sale/checkout price is `price_amount`.

Do not implement pricing promotions or discount engines in this phase.

Do not add:

```text
discount_percent
sale_start_at
sale_end_at
coupon_code
```

here.

---

# 3.5.11 Internal cost price

Support optional:

```text
cost_price_amount
cost_price_currency
```

for internal operational/accounting use.

This field is highly sensitive.

It must:

* never be included in public catalog serialization;
* never be included in customer-facing variant responses;
* never be exposed merely because the caller is authenticated;
* never be accepted from a public customer API.

The future Admin/Staff representation must expose only what the applicable authorization policy permits.

Field-level serialization is mandatory; internal values must not be exposed through automatic model serialization.

Do not implement cost accounting or margin calculations in this phase.

---

# 3.5.12 Product price representation compatibility

The frozen API convention requires a non-null public `product.price` even though variant pricing is the sellable pricing boundary.

Do **not** create a second authoritative `products.price` column solely to duplicate variant pricing.

Instead, later Product API serialization must derive the required public product-level `price` representation from the product's valid variant/pricing state according to the catalog contract.

That derivation belongs to the later Catalog API phase.

Do not implement the derivation algorithm now.

Do not introduce a second price source of truth.

---

# 3.5.13 Dimensions

Store variant physical dimensions separately:

```text
width_cm
height_cm
depth_cm
```

Use centimeters consistently.

Do not store:

```text
"85 x 90 x 80 cm"
```

as a single text value.

Do not store units in the numeric columns.

Do not create:

```text
width_unit
height_unit
depth_unit
```

for V1.

The database value is numeric; future API serialization can express the structured dimensions explicitly.

---

# 3.5.14 Weight

Store:

```text
weight_kg
```

as a numeric value in kilograms.

Do not store:

```text
"18.5 kg"
```

as the database value.

Do not mix kilograms and pounds in the same field.

Do not introduce unit-conversion infrastructure in this phase.

---

# 3.5.15 Physical measurement validation

Where a dimension/weight is provided:

* it must be greater than zero;
* unreasonable negative values must be rejected;
* numeric precision must be sufficient for furniture measurements;
* null remains valid where a specification is genuinely unavailable.

Do not silently convert zero into null.

Do not use negative numbers as a sentinel for "unknown".

Do not invent minimum/maximum furniture dimensions unless explicitly required by the business rules.

---

# 3.5.16 Variant attributes JSON

Use:

```text
attributes
```

as an optional structured JSON object for flexible variant options.

Example:

```json
{
  "color": "Forest Green",
  "fabric": "Velvet",
  "leg_finish": "Natural Oak",
  "size": "3-Seater"
}
```

This exists for variant-specific options that do not warrant dedicated relational columns yet.

Do not duplicate fixed physical specifications into this JSON:

```json
{
  "width_cm": 85,
  "height_cm": 90,
  "depth_cm": 80,
  "weight_kg": 18.5
}
```

Those belong in dedicated columns.

Do not place price or inventory values inside `attributes`.

Do not place:

```text
price
cost_price
stock
reserved_quantity
warehouse
```

inside `attributes`.

---

# 3.5.17 Attribute rules

`attributes` must be a JSON object when present.

Prefer shallow key/value attributes:

```json
{
  "color": "Forest Green",
  "fabric": "Velvet",
  "finish": "Natural Oak"
}
```

Avoid deeply nested arbitrary structures.

Do not allow arrays or arbitrary executable content to become an implicit variant schema.

Do not use JSON attributes for authorization state or business state.

Do not create an EAV system in this phase.

The JSON field is a bounded extensibility mechanism, not a replacement for relational modeling.

---

# 3.5.18 Attribute naming

Use canonical `snake_case` keys.

Examples:

```text
color
fabric
finish
leg_finish
size
configuration
```

Do not mix:

```text
legFinish
leg-finish
Leg Finish
```

The API convention uses `snake_case` consistently across JSON fields.

Do not create arbitrary duplicate representations of the same attribute.

---

# 3.5.19 Attribute semantics

An attribute value is descriptive configuration data.

It must not become a hidden source of truth for:

* price;
* inventory;
* availability;
* product status;
* authorization;
* payment state.

For example:

```json
{
  "color": "Forest Green"
}
```

is valid.

This is not:

```json
{
  "stock": 15
}
```

because stock belongs to the Inventory domain.

---

# 3.5.20 Variant default

Use:

```text
is_default
```

to identify the default variant for customer/catalog presentation.

V1 invariant:

```text
A Product has at most one default Variant.
```

A product may temporarily have no default variant during controlled internal provisioning if the future workflow allows it, but a public product representation must obey the catalog contract's requirements before publication.

Do not permit two default variants for the same product.

Because the standard MySQL uniqueness constraint does not directly express "only one row where `is_default = true`" portably, enforce this invariant in the application/service layer and test it.

When changing the default variant later, the operation must be transactional so concurrent updates cannot leave two defaults.

Do not implement that mutation workflow in this phase.

---

# 3.5.21 Variant active state

Use:

```text
is_active
```

to control whether the variant is eligible for future catalog/purchase behavior.

An inactive variant is not automatically deleted.

Do not equate inactive with out-of-stock.

These are different concepts:

```text
is_active
→ catalog/business availability

inventory
→ physical stock availability
```

Inventory availability will be established in Phase 3.7 and later Catalog API phases.

---

# 3.5.22 Display order

Use:

```text
display_order
```

for deterministic presentation of variants belonging to a Product.

Do not depend on database insertion order.

Use non-negative integer values.

Do not interpret `display_order` as priority for recommendation algorithms or stock ranking.

---

# 3.5.23 Variant/product integrity

The following must always be true:

```text
Variant.product_id references an existing Product.
Variant.sku is globally unique.
Variant.price_amount exists.
Variant dimensions are numeric when supplied.
Variant attributes are structured JSON when supplied.
```

A variant cannot exist independently of a product.

Do not allow a variant to move between Products through an ordinary public update.

If variant reassignment is ever supported internally, it must be an explicit controlled operation with appropriate integrity checks.

Do not implement reassignment now.

---

# 3.5.24 Inventory separation

Do not add:

```text
quantity
reserved_quantity
available_quantity
warehouse_location
stock_status
```

to `product_variants`.

Inventory is Phase 3.7.

The eventual relationship is:

```text
Product
   ↓
Product Variant
   ↓
Inventory / Stock
```

This allows the same variant to have different inventory positions later.

The server remains the authority for availability and stock; client-submitted inventory values are never authoritative.

---

# 3.5.25 Media separation

Do not add image/video paths to `product_variants`.

Variant-specific media will be connected in Phase 3.6.

The final relationship may conceptually be:

```text
Product
├── Variants
└── Media
      └── optionally linked to a specific Variant
```

The variant itself must remain independent of file storage.

Do not add:

```text
image_url
thumbnail_url
video_url
file_path
```

to this table.

---

# 3.5.26 Product assets separation

Do not add:

```text
glb_url
usdz_url
gltf_url
ar_model_path
```

to `product_variants` in this phase.

3D/AR assets belong to the later product asset/media architecture.

A future asset may optionally target a product or a specific variant depending on the finalized Phase 3.6 asset model.

Do not decide that relationship prematurely here.

---

# 3.5.27 Room-staging compatibility

Variant attributes may eventually influence staging, but do not encode recommendation/staging logic into the variant.

Do not add:

```text
room_style_score
ar_scale
staging_priority
recommended_room
compatibility_score
```

to `product_variants`.

Physical dimensions provide future room-planning inputs.

Variant attributes provide future visual/configuration inputs.

The actual recommendation/staging engine belongs to later application/API work.

---

# 3.5.28 Variant financial authority

The authoritative hierarchy is:

```text
Variant.price
    ↓
Cart informational price
    ↓
Checkout resolves current authoritative price
    ↓
Order item snapshots historical unit price
```

Do not allow:

```text
Product.price
Variant.price
Cart.price
OrderItem.price
```

to become competing mutable sources of truth.

The project convention explicitly states that the server owns financial values and clients supply intent rather than authoritative totals/prices.

The later Order Item Snapshot phase will preserve historical pricing at purchase time.

---

# 3.5.29 Variant model

Create:

```text
App\Models\ProductVariant
```

with:

```php
public function product(): BelongsTo
{
    return $this->belongsTo(Product::class);
}
```

On `Product`:

```php
public function variants(): HasMany
{
    return $this->hasMany(ProductVariant::class);
}

public function defaultVariant(): HasOne
{
    return $this->hasOne(ProductVariant::class)
        ->where('is_default', true);
}
```

Prefer `HasOne` for `defaultVariant()` because the business invariant is one default variant at most.

Do not use `HasMany` for a logically singular default relation.

If the project prefers a method such as `defaultVariant()` returning a query constrained by the same invariant, preserve the type semantics consistently.

---

# 3.5.30 Casts

Use appropriate Laravel casts for:

```text
attributes → array/object representation
is_default → boolean
is_active → boolean
display_order → integer
numeric dimensions/weight → appropriate numeric representation
```

Money amount columns must remain integer-valued.

Do not convert money to floating-point values.

Do not use floating-point arithmetic for financial calculations.

---

# 3.5.31 Attribute validation boundary

Because `attributes` is flexible, the future write boundary must validate:

```text
object shape
key naming
supported keys
value types
size limits
```

Do not accept unlimited arbitrary JSON payloads.

Do not allow arbitrary nested objects that could produce large or unpredictable documents.

The exact V1 attribute vocabulary may remain open until the Variant API phase, but the database should not prevent legitimate furniture options such as:

```text
color
fabric
finish
size
configuration
leg_finish
```

Do not convert these to CLOSED enums until the API/domain requirements explicitly establish a complete vocabulary.

---

# 3.5.32 Product/variant publication integrity

A future public Product representation must not expose a product as purchasable merely because a variant row exists.

Later catalog availability must consider:

```text
Product.is_active
Variant.is_active
Inventory availability
Product type/business rules
```

Do not calculate final availability in Phase 3.5.

This phase only supplies the variant data required by those later decisions.

---

# 3.5.33 Stable serialization readiness

The future Variant representation must use explicit allow-lists.

Expected public fields can include:

```text
id
sku
variant_name
price
compare_at_price
dimensions
weight
attributes
is_default
```

subject to the exact frozen Product/Variant API contract.

Do not serialize:

```text
cost_price
internal storage metadata
audit internals
future inventory internals
```

to customers.

The project requires explicit serialization per audience and forbids indiscriminate model serialization.

---

# 3.5.34 Security and mass assignment

Future variant creation/update must follow:

```text
validated input
→ explicit allow-list
→ DTO/command
→ domain validation
→ authorization
→ persistence
```

Never:

```php
$request->all()
```

Never permit clients to set:

```text
id
product_id   // except through the trusted parent route/context
price currency without validation
created_at
updated_at
cost_price through public/customer APIs
inventory
is_default without privileged authorization
```

In particular, a nested route such as:

```text
POST /products/{product}/variants
```

must derive the parent Product from the server-side route/model context rather than trusting a separate body `product_id`.

The project explicitly requires server authority for identity and forbids client-controlled resource ownership.

---

# 3.5.35 API route readiness

Do not implement Variant endpoints in this phase.

However, the schema must support the later canonical routes:

```text
GET /api/v1/products/{product}/variants
GET /api/v1/products/{product}/variants/{variant}
```

The variant detail must be resolved under its Product.

Do not create alternate routes such as:

```text
GET /api/v1/variants/{id}
GET /api/v1/product-variants/{id}
```

unless the frozen API contract explicitly introduces them.

The project convention specifically defines shallow product/variant nesting.

---

# 3.5.36 Tests

Create focused tests alongside the migration and model.

## Migration tests

Verify:

* migration succeeds from an empty database;
* rollback succeeds;
* variant requires a valid Product;
* deleting a Product removes its variants through the foreign key;
* SKU uniqueness is enforced;
* required price exists;
* nullable compare-at/cost price fields behave correctly;
* dimension and weight columns support the expected precision;
* JSON attributes field works;
* indexes are created correctly.

## Relationship tests

Verify:

```text
Product → variants
Variant → product
Product → defaultVariant
```

Test that:

* product returns its variants;
* variant returns its parent product;
* default variant returns the default record;
* non-default variants do not appear as the default.

## Default-variant integrity tests

Verify:

* a product can have one default variant;
* application validation prevents two defaults;
* changing defaults leaves exactly one default;
* no accidental duplicate default can be produced by the intended application operation.

Where concurrent default changes are implemented later, add transactional/concurrency tests there rather than prematurely implementing the workflow in this phase.

## Pricing tests

Verify:

* prices are stored as integer minor units;
* no floating-point price representation is used;
* TZS is represented correctly;
* compare-at price may be null;
* cost price may be null;
* cost price is not present in the public representation tests.

## Attribute tests

Verify:

* null attributes are supported;
* valid JSON object attributes are persisted;
* structured variant options round-trip correctly;
* product-level fields are not duplicated into attributes;
* inventory values are not represented as variant attributes.

## Ownership/integrity tests

Verify that a variant belongs to exactly one Product.

Where a future nested API test is introduced:

```text
Product A + Variant belonging to Product B
→ must not resolve successfully
```

That API authorization/lookup test belongs to the later Variant API phase, but the data model must make the relationship explicit now.

---

# 3.5.37 Factories

Do not create production catalog seed data in this phase.

A `ProductVariantFactory` may be created for automated tests.

Factory requirements:

* generates a real Product relationship;
* generates a unique SKU;
* generates valid positive dimensions when supplied;
* generates valid integer minor-unit pricing;
* generates structured attributes;
* does not create inventory;
* does not create media;
* does not create orders.

Avoid making every generated variant `is_default = true`.

Provide a deterministic mechanism for creating a single default variant when a test needs one.

---

# 3.5.38 Code quality

Keep the implementation cohesive.

Expected components:

```text
ProductVariant migration
ProductVariant model
Product relationship updates
Factory
Focused tests
```

Avoid:

* Variant controllers;
* Variant API resources;
* inventory services;
* price calculation services;
* recommendation services;
* media services;
* AR services;
* search/filter services.

Do not create generic "attribute management" abstractions before the actual API/domain requirements require them.

Keep comments to the absolute minimum.

Prefer clear names, relationships, constraints, and tests.

---

# 3.5.39 Documentation / durable decisions

Update `docs/decisions.md` only for durable architectural decisions that are genuinely useful, such as:

* variants are the sellable/pricing unit;
* money is stored as integer minor units;
* variant attributes are structured JSON for V1 flexibility;
* dimensions use centimeters and weight uses kilograms;
* inventory remains separated from variants;
* product-level public price is derived rather than duplicated as another financial source of truth.

Do not create a permanent Phase-3.5 markdown document merely to duplicate this instruction.

---

# Explicitly out of scope

Do not implement:

* product inventory;
* warehouse locations;
* stock quantities;
* reserved quantities;
* availability calculation;
* Product Media;
* Product Images;
* staged-room images;
* 3D assets;
* AR assets;
* recommendation engine;
* product search;
* product filtering;
* Product CRUD API;
* Variant CRUD API;
* customer cart;
* checkout;
* payment;
* order creation;
* historical order pricing;
* discounts;
* coupons;
* promotion rules;
* price scheduling;
* cost accounting;
* room-staging engine;
* recommendation scoring;
* Next.js catalog pages;
* Flutter catalog screens.

---

# Definition of done

Phase 3.5 is complete only when:

1. `product_variants` exists and references `products`.
2. Every variant has a globally unique SKU.
3. Variant name is modeled separately from SKU.
4. Variant pricing is stored as integer minor units.
5. Currency is represented explicitly.
6. Compare-at pricing is optional and separate from authoritative price.
7. Internal cost pricing is optional and protected from public serialization.
8. Variant dimensions are stored separately as numeric centimeters.
9. Weight is stored separately as numeric kilograms.
10. Flexible variant attributes are stored as structured JSON.
11. Product-level fields are not duplicated unnecessarily into variant attributes.
12. `is_default` supports one default variant per product.
13. `is_active` supports variant-level activation independently from inventory.
14. `display_order` provides deterministic variant ordering.
15. Product/Variant Eloquent relationships are implemented.
16. `defaultVariant()` is modeled as a logically singular relationship.
17. Product deletion cannot leave orphan variants.
18. Variant schema is ready for future media and inventory relationships.
19. No inventory data has been placed in the variant table.
20. No media or AR paths have been placed in the variant table.
21. No floating-point financial representation exists.
22. Tests cover migration, relationships, SKU uniqueness, pricing, attributes, default variant integrity, and core constraints.
23. Factories, where added, do not fabricate later-phase inventory/media/order data.
24. Formatting, static analysis, and the existing test suite pass.
25. No Variant API or later catalog business logic has been implemented.
26. Code comments remain minimal.

---

# STOP condition

Stop after the Product Variant migration, model relationships, factory support, constraints, and tests are complete.

Do not continue into Phase 3.6 Product Images Schema.

Do not implement inventory, media, AR assets, Variant APIs, cart behavior, availability calculation, or checkout pricing logic.

Do not commit, stage, or push changes.
