# Group C phases instructions

# Phase 3.7 — Inventory Schema

## Purpose

Implement the database foundation for backend-controlled product inventory.

The inventory model must support:

* stock per Product Variant;
* more than one warehouse/location;
* physical quantity;
* reserved quantity;
* derived available quantity;
* deterministic stock lookup;
* future stock adjustments;
* future reservations/releases;
* future concurrency-safe checkout/cart behavior;
* future inventory API and operational staff workflows.

The core inventory relationship is:

```text id="0qtv8b"
Product
   ↓
Product Variant
   ↓
Inventory Stock
   ↓
Warehouse / Location
```

Inventory is **not** stored on `products`.

Inventory is **not** stored on `product_variants`.

The system must distinguish:

```text id="x6f3ap"
physical quantity
reserved quantity
available quantity
```

and final availability must be determined by Laravel/database logic rather than by frontend state.

---

# Dependencies

Required:

* Phase 2.1–2.12 completed.
* Phase 3.1 Users Schema completed.
* Phase 3.2 Roles/permissions model completed.
* Phase 3.3 Categories Schema completed.
* Phase 3.4 Products Schema completed.
* Phase 3.5 Product Variants Schema completed.
* Phase 3.6 Product Images Schema completed.
* Existing Laravel migration/model/test conventions operational.

Authoritative inputs:

* `docs/VISION.md`
* `docs/api/api-contract.md`
* `docs/api/api-resources.md`
* `docs/api/api-conventions.md`
* `docs/domain/business-rules.md`
* `docs/decisions.md`
* `AGENTS.md`

The Group C roadmap explicitly places Inventory Schema at Phase 3.7 and later separates inventory read/mutation/concurrency API work into Group E.

---

# 3.7.1 Inventory responsibility

Create a dedicated stock record for each:

```text id="s5q2zd"
Product Variant + Warehouse/Location
```

Conceptually:

```text id="8o3ytc"
Variant A
├── Main Warehouse
│   └── quantity = 10
└── Secondary Store
    └── quantity = 4

Variant B
└── Main Warehouse
    └── quantity = 7
```

This allows future expansion to multiple locations without duplicating Product Variant records.

Do not create inventory directly against Product.

The sellable unit is the Product Variant.

---

# 3.7.2 Primary inventory table

Create:

```text id="x5c9gz"
product_stocks
```

with the conceptual fields:

```text id="q4wqfd"
id
product_variant_id
warehouse_location
quantity
reserved_quantity
created_at
updated_at
```

Recommended Laravel migration:

```php id="mruqfs"
Schema::create('product_stocks', function (Blueprint $table) {
    $table->id();

    $table->foreignId('product_variant_id')
        ->constrained('product_variants')
        ->cascadeOnDelete();

    $table->string('warehouse_location');

    $table->unsignedInteger('quantity')->default(0);
    $table->unsignedInteger('reserved_quantity')->default(0);

    $table->timestamps();

    $table->unique(
        ['product_variant_id', 'warehouse_location'],
        'product_stock_variant_location_unique'
    );

    $table->index([
        'product_variant_id',
        'warehouse_location',
    ]);
});
```

Adapt the exact migration syntax to the project's existing Laravel/database conventions.

Do not duplicate an index that is already fully covered by a uniqueness/index definition.

---

# 3.7.3 Product Variant relationship

Every stock record belongs to one Product Variant.

Add to `ProductVariant`:

```php id="1t0tse"
public function stocks(): HasMany
{
    return $this->hasMany(ProductStock::class);
}
```

Create:

```text id="gn5h7c"
App\Models\ProductStock
```

with:

```php id="p1z7or"
public function productVariant(): BelongsTo
{
    return $this->belongsTo(ProductVariant::class);
}
```

Do not create a direct:

```text id="5yq3i3"
Product → stocks
```

relationship as the authoritative inventory relationship.

Products reach inventory through their variants.

---

# 3.7.4 Warehouse/location model

For V1, keep:

```text id="squ0a0"
warehouse_location
```

as a stable bounded string rather than introducing a separate warehouse subsystem.

Example values:

```text id="1xb7k4"
main
dar-es-salaam-warehouse
arusha-store
```

The value identifies the physical inventory location.

Do not allow arbitrary frontend-provided location strings in future public/cart APIs.

Inventory location selection is an operational/backend concern.

Do not create a `warehouses` table in this phase.

A normalized Warehouse entity can be introduced later when the business requires:

* address;
* contact information;
* operating hours;
* regions;
* delivery zones;
* warehouse status;
* transfer operations.

Until then, the simple location identifier is sufficient.

---

# 3.7.5 Location naming

Use a deterministic machine-friendly representation for future operational use.

Prefer:

```text id="1b1jaw"
main
dar-es-salaam
arusha-store
```

over arbitrary prose such as:

```text id="nwt0ka"
The big warehouse in Dar es Salaam
```

Do not treat location strings as customer-facing display labels.

If a future Warehouse entity is introduced, it can provide a separate human-readable name.

Do not add `warehouse_name` to `product_stocks` now.

---

# 3.7.6 Stock quantity

Use:

```text id="97qkt7"
quantity
```

as the physical quantity owned at the location.

Requirements:

* integer;
* non-negative;
* server-controlled;
* never accepted as authoritative customer input;
* never represented as floating point.

Example:

```text id="obsgyy"
quantity = 14
```

means the location physically has fourteen units according to the authoritative inventory state.

Do not use negative quantities to represent shortages.

Do not use `null` to represent unknown stock.

---

# 3.7.7 Reserved quantity

Use:

```text id="nkrq2y"
reserved_quantity
```

to represent units currently reserved and unavailable for another reservation/consumption operation.

Requirements:

* integer;
* non-negative;
* server-controlled;
* never accepted as authoritative customer input.

Example:

```text id="q4h5qd"
quantity = 14
reserved_quantity = 3
available = 11
```

Do not allow `reserved_quantity > quantity`.

This invariant must be enforced by the application/domain layer and tested.

If a database-level check constraint is supported safely by the project's target MySQL version and migration policy, it may be added as an additional defensive layer.

Do not rely on the database check alone.

---

# 3.7.8 Available quantity

Do **not** create a persisted:

```text id="p6t27k"
available_quantity
```

column.

Calculate:

```text id="5u1aom"
available_quantity = quantity - reserved_quantity
```

when required.

This avoids stale duplicated state.

A Product Variant with:

```text id="xaqtpc"
quantity = 20
reserved_quantity = 5
```

has:

```text id="om6xl3"
available_quantity = 15
```

Do not allow a client to submit:

```json id="1w7n3z"
{
  "available_quantity": 15
}
```

as authoritative inventory state.

The backend owns availability.

---

# 3.7.9 Available quantity invariant

The core invariant is:

```text id="0ybjpd"
0 <= reserved_quantity <= quantity
available_quantity = quantity - reserved_quantity
```

A stock operation must never produce:

```text id="u7t84c"
reserved_quantity > quantity
```

or:

```text id="00mqu8"
available_quantity < 0
```

Do not implement reservation mutation in this phase.

Only establish the persistence model and invariant tests.

---

# 3.7.10 Multiple inventory locations

A Product Variant may have multiple stock rows:

```text id="kmm6hc"
Variant 501 + Main Warehouse
Variant 501 + Retail Store
Variant 501 + Secondary Warehouse
```

But the same Variant/location pair must not appear more than once.

Enforce:

```text id="c8ha8a"
UNIQUE(product_variant_id, warehouse_location)
```

This prevents:

```text id="cl7i48"
Variant 501 + main
Variant 501 + main
```

from creating ambiguous stock totals.

---

# 3.7.11 Inventory aggregation

Later inventory services may calculate total inventory for a Variant by aggregating stock rows.

Conceptually:

```text id="bivsvw"
Variant
  Main Warehouse      10
  Retail Store         4
  Secondary Warehouse  2
  ----------------------
  Physical Total      16
```

Do not create a denormalized `product_variants.total_quantity` field.

Do not create a denormalized `product_variants.available_quantity` field.

Do not create summary fields until real query performance demonstrates a need.

The existing performance guidance explicitly favors measuring before introducing denormalization or speculative optimization.

---

# 3.7.12 Inventory and product status

Inventory quantity is not the same thing as Product/Variant activation.

Keep these concepts separate:

```text id="fu4xqg"
Product.is_active
Variant.is_active
Inventory.quantity
Inventory.reserved_quantity
```

Do not automatically set:

```text id="nqb3y0"
product.is_active = false
```

when inventory reaches zero.

Do not automatically delete inventory records when stock reaches zero.

Zero stock remains a valid inventory state.

---

# 3.7.13 Availability readiness

The later catalog API must derive:

```text id="6qsqpp"
availability
stock_indicator
```

from authoritative Product, Variant, and Inventory data.

The V1 contract already reserves:

```text id="85x3tc"
availability:
    available
    unavailable

stock_indicator:
    IN_STOCK
    LOW_STOCK
    MADE_TO_ORDER
```

with `availability` as the single lowercase enum exception and `stock_indicator` remaining uppercase.

Do **not** implement these derived API values in Phase 3.7.

Do not add:

```text id="d1g6ik"
availability
stock_indicator
```

columns to `product_stocks`.

They are derived/domain presentation state.

---

# 3.7.14 Low-stock logic

Do not create:

```text id="3xk5sa"
low_stock_threshold
```

in Phase 3.7 unless the existing domain contract explicitly defines such a threshold.

Do not invent:

```text id="ukf0b6"
5 units = LOW_STOCK
```

or another arbitrary threshold.

The later Inventory Read Model / Catalog Availability phase must establish how `LOW_STOCK` is calculated if the business requires it.

---

# 3.7.15 Made-to-order separation

A product that is `MADE_TO_ORDER` does not necessarily require physical stock.

Do not force every Product Variant to have an inventory row merely to make the schema work.

Inventory may legitimately have no stock row for a made-to-order configuration.

The availability/purchasability domain later determines whether:

```text id="n6m8r7"
IN_STOCK
MADE_TO_ORDER
```

is applicable.

Do not create a `product_type` field on `product_stocks`.

---

# 3.7.16 Inventory ownership

Inventory is server-owned operational data.

Clients may request quantities in cart/checkout operations, but they never control:

```text id="i8sgxr"
quantity
reserved_quantity
available_quantity
warehouse_location
```

The backend validates and mutates authoritative inventory state.

The global API convention explicitly marks inventory values as server-controlled and rejects client authority over stock/reservation fields.

---

# 3.7.17 Reservations

Do not create a separate reservation table in this phase.

The current schema needs only the authoritative aggregate:

```text id="v0dwh6"
reserved_quantity
```

The later Cart/Checkout/Inventory phases will define whether reservation records are required for:

* reservation identity;
* expiration;
* cart binding;
* order binding;
* release;
* recovery;
* reconciliation.

Do not prematurely create:

```text id="n8z50f"
inventory_reservations
stock_holds
reservation_tokens
```

The database model can support those later additions without changing the core stock relationship.

---

# 3.7.18 Stock movements / ledger

Do not create a stock movement ledger in Phase 3.7 unless the existing project already has an established inventory event model.

Do not add:

```text id="58hd69"
inventory_movements
stock_adjustments
inventory_transactions
```

speculatively.

Future inventory adjustments are expected to be explicit, auditable, and concurrency-safe, but the actual mutation/audit implementation belongs to later inventory operational phases. The project already requires inventory adjustments to be transactional and auditable.

---

# 3.7.19 Inventory adjustment readiness

The schema must support later operations such as:

```text id="2lnvps"
increase stock
decrease stock
reserve stock
release reservation
consume stock
restore stock
```

without changing the meaning of:

```text id="uuj5n7"
quantity
reserved_quantity
```

Those operations must be implemented by domain/application logic later.

Do not create generic:

```text id="h9n7m5"
PATCH /inventory/{id}
```

semantics in this phase.

Later inventory mutations should use explicit controlled operations.

---

# 3.7.20 Concurrency readiness

Inventory is concurrency-sensitive.

The schema must support later atomic/transactional operations.

Do not implement the final concurrency algorithm in Phase 3.7.

However, do not design the model in a way that requires:

```text id="8x2h7w"
read quantity
→ calculate available
→ update later without lock/version check
```

as the only possible implementation.

The project explicitly prohibits `SELECT then UPDATE` without appropriate concurrency control for inventory.

Later Group E phases will establish:

* transactional boundaries;
* row locking or equivalent protection;
* stale-state detection;
* overselling prevention;
* `409 CONFLICT` behavior where appropriate.

---

# 3.7.21 Inventory unique identity

The canonical stock identity is:

```text id="z9smj7"
product_variant_id + warehouse_location
```

Do not use:

```text id="46a5u1"
product_id + warehouse_location
```

because inventory belongs to variants.

Do not use:

```text id="fhb0kj"
sku + warehouse_location
```

as the database foreign-key identity because SKU is a business identifier and can be changed only under controlled operations.

Use the Product Variant foreign key.

---

# 3.7.22 Inventory indexing

Add indexes for the primary future query patterns:

```text id="60n0am"
product_variant_id
product_variant_id + warehouse_location
```

The unique constraint on:

```text id="ajg65q"
(product_variant_id, warehouse_location)
```

already supports variant/location lookup.

Do not create speculative indexes on:

```text id="f2bfl5"
quantity
reserved_quantity
created_at
updated_at
```

unless actual queries require them.

---

# 3.7.23 Delete behavior

When a Product Variant is removed through a future controlled product lifecycle operation:

```text id="j3c9r2"
Product Variant
→ related Product Stock records
```

can be removed through cascade because stock has no standalone catalog meaning without its Variant.

Use:

```php id="l66sp3"
->cascadeOnDelete()
```

for:

```text id="2olob4"
product_stocks.product_variant_id
```

Do not implement destructive product/variant deletion workflows here.

Historical order records must not rely on current inventory records for their historical quantity/financial identity.

The project requires historical orders to preserve snapshots rather than relying solely on current product state.

---

# 3.7.24 Inventory model

Create:

```text id="b7axnz"
App\Models\ProductStock
```

with:

```php id="g2hv58"
public function productVariant(): BelongsTo
{
    return $this->belongsTo(ProductVariant::class);
}
```

Provide a derived accessor/helper for available quantity only if the existing Laravel model conventions make that appropriate:

```text id="j6w2xr"
available_quantity = quantity - reserved_quantity
```

Do not persist it.

Do not create a large inventory service in this phase.

---

# 3.7.25 Available quantity helper complexity

If an accessor/helper is introduced, it should be trivial.

Do not implement complicated availability rules inside:

```text id="k2q97e"
getAvailableQuantityAttribute()
```

The accessor should calculate only:

```text quantity - reserved_quantity
```

Do not put:

* product activation;
* variant activation;
* stock indicator;
* made-to-order rules;
* reservations;
* checkout;
* permissions;

inside the accessor.

Those are domain/application responsibilities.

---

# 3.7.26 Inventory serialization readiness

Do not implement the Inventory API in this phase.

Future public catalog responses should generally expose derived availability rather than operational inventory internals.

For example, a public product can eventually expose:

```json id="1udf56"
{
  "availability": "available",
  "stock_indicator": "IN_STOCK"
}
```

rather than:

```json id="yk7veb"
{
  "quantity": 14,
  "reserved_quantity": 3,
  "available_quantity": 11
}
```

The latter is operational inventory data and belongs only to authorized Staff/Admin representations where required.

The project explicitly distinguishes public product fields from operational `quantity`/`reserved_quantity` and requires field-level serialization before output.

---

# 3.7.27 Staff/Admin authorization readiness

The Phase 3.2 RBAC foundation already defines:

```text id="q1pazx"
inventory.view
inventory.manage
```

as separate operational permissions from catalog management.

Do not implement those policies here.

Future inventory operations must require:

```text id="xy0r9g"
authenticated actor
+
inventory permission
+
authorized stock/resource
+
valid operation
+
business/concurrency rules
```

Role alone must never be treated as sufficient authorization.

The project explicitly separates `inventory.view` / `inventory.manage` from `products.manage`.

---

# 3.7.28 Mass assignment protection

Future inventory write operations must never use:

```php id="6wq9uq"
$request->all()
```

to populate ProductStock.

Use:

```text id="m1bd6m"
validated input
→ explicit allow-list
→ command/service
→ authorization
→ transaction/concurrency checks
→ persistence
```

Client input must never directly control:

```text id="u0ku19"
quantity
reserved_quantity
available_quantity
product_variant_id ownership
warehouse_location
created_at
updated_at
```

except where a future privileged inventory operation explicitly accepts the relevant operational input.

The existing global conventions require explicit distinction between client-controlled intent and server-controlled inventory state.

---

# 3.7.29 API input compatibility

Future Inventory API inputs must use strict JSON types and the established naming conventions:

```text id="j29kxm"
quantity
product_variant_id
warehouse_location
```

Do not introduce:

```text id="o7c5q0"
productVariantId
warehouse-location
stock
available
```

The API convention requires `snake_case`, strict types, and server-controlled inventory fields.

Do not implement API validation now.

---

# 3.7.30 Error readiness

Later inventory operations should use the existing error vocabulary where applicable:

```text id="o0ld7b"
INSUFFICIENT_STOCK
CONFLICT
FORBIDDEN
RESOURCE_NOT_FOUND
INVALID_VALUE
```

Do not create Product-specific or warehouse-specific error explosions such as:

```text id="j3t1x6"
SOFA_INSUFFICIENT_STOCK
MAIN_WAREHOUSE_EMPTY
VARIANT_501_OUT_OF_STOCK
```

The project explicitly favors stable generic machine codes such as `INSUFFICIENT_STOCK`.

---

# 3.7.31 Transaction readiness

Do not put transaction handling directly into model accessors.

Later operations such as:

```text id="v5j3qv"
reserve
release
consume
adjust
restore
```

must execute in the appropriate transaction boundary.

## The project requires inventory reservation/reduction to be transactional where required and emphasizes failure atomicity.

# 3.7.32 Maintainability requirements

Apply the maintainability rules established in Phase 3.6 to this phase.

### Cognitive complexity

Any new or refactored function must have:

```text id="zd5a3p"
cognitive complexity <= 15
```

If an affected existing function exceeds 15:

* refactor it;
* extract cohesive decision logic;
* reduce nesting;
* use guard clauses where helpful;
* keep responsibilities narrow.

Do not suppress complexity warnings.

Do not raise the analyzer threshold.

### Return statements

Functions introduced or refactored in this phase must contain:

```text id="d4jco1"
no more than 3 return statements
```

Do not introduce deeply nested logic merely to achieve the numeric limit.

Refactor into focused functions when control flow becomes difficult to understand.

### Duplicated string literals

Do not repeatedly duplicate meaningful literals.

Where a literal represents a shared domain/storage concept, use an appropriate constant or enum.

Examples of concepts that may warrant centralization:

```text id="11pn99"
default warehouse/location identifier
canonical relation/property names where actually reused
```

Do not create a giant generic string-constant class for every ordinary string.

Centralize only meaningful repeated concepts.

---

# 3.7.33 Maintainability tests/tooling

Where the existing static-analysis tooling supports these rules:

* fail on cognitive complexity greater than 15 for new/refactored functions;
* flag more than 3 returns;
* flag duplicated meaningful literals.

Do not suppress individual findings simply to make CI pass.

If a warning comes from unrelated legacy code, keep the change scoped to the functions affected by this phase unless the existing project policy requires broader cleanup.

---

# 3.7.34 Inventory tests

Create focused tests.

## Migration tests

Verify:

* migration succeeds from an empty database;
* rollback succeeds;
* stock requires a valid Product Variant;
* deleting a Product Variant removes its stock rows;
* duplicate Variant/location combinations are rejected;
* quantity defaults to zero;
* reserved quantity defaults to zero;
* quantity fields cannot become negative where supported by the database/type;
* indexes and uniqueness constraints are present.

## Relationship tests

Verify:

```text id="m4wxqd"
ProductVariant → stocks
ProductStock → productVariant
```

and confirm a Variant can have multiple locations.

## Quantity invariant tests

Verify:

```text id="phz8vo"
quantity = 10
reserved_quantity = 0
available = 10

quantity = 10
reserved_quantity = 4
available = 6
```

Verify invalid state:

```text id="1c59xv"
quantity = 10
reserved_quantity = 11
```

is rejected by application validation.

Do not implement reservation mutation here.

## Location uniqueness tests

Verify:

```text id="qh3f1u"
Variant A + main
Variant A + main
```

cannot coexist.

Verify:

```text id="l5cfy7"
Variant A + main
Variant A + retail-store
```

can coexist.

## Separation tests

Verify inventory is not stored on:

```text id="c8l5aa"
products
product_variants
```

and that `available_quantity` is derived rather than persisted.

## Factory tests

Where a factory exists, verify:

* valid Product Variant relationship;
* valid location;
* non-negative quantity;
* non-negative reserved quantity;
* reserved does not exceed quantity;
* no duplicate Variant/location records.

---

# 3.7.35 Factory support

Create `ProductStockFactory` only if it provides genuine testing value.

A factory should:

* create or reference a valid Product Variant;
* create a valid location identifier;
* generate a valid quantity;
* generate reserved quantity within the quantity boundary;
* avoid duplicate Variant/location combinations.

Do not create factories that automatically create:

* orders;
* carts;
* reservations;
* payments;
* warehouse entities;
* audit events.

Those belong to later phases.

---

# 3.7.36 Seed data

Do not create production-like warehouse inventory in this phase unless the project already defines authoritative development seed inventory.

Development seed data may include a small deterministic stock record if required for local testing.

Do not make future tests depend on unspecified stock quantities.

Do not use random stock values in deterministic domain seeds.

---

# 3.7.37 Performance

The expected primary access patterns are:

```text id="3o59iq"
get stock for Variant
get stock for Variant + Location
aggregate stock for Variant
```

The schema must support these efficiently through the Product Variant foreign key and unique Variant/location constraint.

Do not prematurely introduce:

* cached total stock columns;
* Redis inventory state;
* denormalized available quantities;
* materialized inventory views;
* background stock projections.

Those can be considered only after actual performance measurement.

---

# 3.7.38 Documentation / durable decisions

Update `docs/decisions.md` only when recording a durable architectural decision such as:

* inventory belongs to Product Variants rather than Products;
* stock is stored per Variant/location;
* available quantity is derived;
* warehouse/location remains a bounded identifier in V1;
* inventory mutation/ledger/reservation lifecycle is deferred to later phases.

Do not create a permanent Phase-3.7 markdown file merely to duplicate this instruction.

---

# Explicitly out of scope

Do not implement:

* Inventory API;
* inventory read endpoints;
* inventory adjustment endpoints;
* reservation API;
* release API;
* consumption API;
* checkout reservation logic;
* cart reservation logic;
* overselling prevention implementation;
* row-locking strategy;
* optimistic locking;
* inventory movement ledger;
* stock adjustment audit implementation;
* warehouse entity;
* warehouse CRUD;
* warehouse addresses;
* inter-warehouse transfers;
* low-stock thresholds;
* availability calculation;
* `stock_indicator` calculation;
* product catalog API;
* variant API;
* cart;
* checkout;
* orders;
* payment;
* recommendation logic;
* customer inventory access;
* Next.js inventory UI;
* Flutter inventory UI.

---

# Definition of done

Phase 3.7 is complete only when:

1. `product_stocks` exists.
2. Every stock record belongs to a Product Variant.
3. A Product Variant can have multiple inventory locations.
4. The same Variant/location pair cannot have duplicate stock rows.
5. `quantity` represents physical inventory.
6. `reserved_quantity` represents reserved inventory.
7. `available_quantity` is derived as `quantity - reserved_quantity`.
8. `available_quantity` is not persisted as a second source of truth.
9. `reserved_quantity` cannot exceed `quantity` through the intended application model.
10. Inventory is not stored on Product.
11. Inventory is not stored directly on Product Variant.
12. Warehouse/location is represented by a deterministic identifier.
13. Product Variant deletion cannot leave orphan stock records.
14. Eloquent relationships between Product Variant and Product Stock exist.
15. Schema supports future multi-location inventory.
16. Schema supports future reservation and adjustment workflows without redefining the core stock fields.
17. Schema supports future concurrency-safe inventory mutations.
18. Public product serialization is not polluted with raw inventory internals.
19. Inventory remains backend-controlled and server-authoritative.
20. Migration tests pass from an empty database and rollback successfully.
21. Relationship, uniqueness, quantity-invariant, and factory tests pass.
22. Formatting and static analysis pass.
23. New/refactored functions have cognitive complexity no greater than 15.
24. New/refactored functions contain no more than 3 return statements.
25. Meaningful duplicated string literals are centralized where appropriate.
26. No analyzer warnings are suppressed merely to satisfy the maintainability requirements.
27. No inventory API or later operational workflow has been implemented.
28. Code comments remain minimal.

---

# STOP condition

Stop after the inventory schema, Eloquent model/relationships, migration constraints, tests, and necessary maintainability refactoring are complete.

Do not continue into Phase 3.8 Cart Schema.

Do not implement reservations, stock adjustments, concurrency protection, availability calculation, inventory APIs, warehouse management, or checkout integration.

Do not commit, stage, or push changes.
