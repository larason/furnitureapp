# Phase 3.10 — Order Items Snapshot Model

## Purpose

Implement the database model for **historical order items**.

An order item must preserve the commercial facts that existed when the order was created. Product and variant records remain useful references, but the order item itself is the historical source for the item's purchased identity, price, quantity, and line total.

This phase must establish the persistence model only. Do not implement checkout creation logic, payment processing, order-state transitions, inventory reservation, delivery workflows, or customer-facing order APIs here.

## Dependencies

Complete these phases first:

* Phase 3.4 — Products Schema
* Phase 3.5 — Product Variants Schema
* Phase 3.9 — Orders Schema

Use the existing `orders`, `products`, and `product_variants` schemas as dependencies. Do not redesign them in this phase.

## Authoritative Inputs

Treat these as authoritative:

* `docs/VISION.md`
* `docs/domain/business-rules.md`
* `docs/api/api-contract.md`
* `docs/api/api-resources.md`
* `docs/api/api-conventions.md`
* `AGENTS.md`
* the completed Phase 3.4 Products Schema
* the completed Phase 3.5 Product Variants Schema
* the completed Phase 3.9 Orders Schema

Do not introduce new V1 business states, enums, financial rules, or API shapes that conflict with those documents.

---

# 1. Core Design Rule

`order_items` is a **historical snapshot table**.

The order item must preserve the values needed to answer:

> What exactly did the customer buy, at what price, in what quantity, and for what line total?

Later changes to:

* product name
* product description
* product category
* variant name
* variant SKU
* variant price
* product availability
* product images
* catalog status

must not rewrite the historical order item.

The order item therefore stores snapshot fields directly rather than deriving historical order display from the current product or variant record.

Product and variant IDs may still be retained as references for traceability, but they are not the authoritative source for historical display or historical pricing.

---

# 2. Create `order_items` Table

Create a Laravel migration for:

`order_items`

Recommended columns:

| Column              | Type                                       | Rules                                            |
| ------------------- | ------------------------------------------ | ------------------------------------------------ |
| `id`                | big integer / Laravel standard primary key | Primary key                                      |
| `order_id`          | foreign key                                | Required; references `orders.id`; cascade delete |
| `product_id`        | nullable foreign key                       | References `products.id`; null on delete         |
| `variant_id`        | nullable foreign key                       | References `product_variants.id`; null on delete |
| `sku`               | string                                     | Required snapshot                                |
| `name`              | string                                     | Required snapshot                                |
| `variant_name`      | nullable string                            | Snapshot of selected variant name                |
| `unit_price_amount` | unsigned big integer                       | Required                                         |
| `quantity`          | unsigned integer                           | Required                                         |
| `line_total_amount` | unsigned big integer                       | Required                                         |
| timestamps          | Laravel timestamps                         | Required                                         |

Use appropriate lengths based on the existing catalog schema and project conventions. Do not create unnecessarily large generic string columns.

## Relationship rules

### `order_id`

`order_items.order_id` belongs to `orders.id`.

Deleting an Order must delete its Order Items as part of the aggregate.

Use cascade deletion for this parent-child relationship because an Order and its Order Items form one historical aggregate.

Do not allow an Order Item to exist without an Order.

### `product_id`

Retain the originating product ID when available.

Do not make historical order preservation depend on the continued existence of the Product row.

Use nullable `product_id` with `nullOnDelete`.

### `variant_id`

Retain the originating variant ID when available.

Do not make historical order preservation depend on the continued existence of the Variant row.

Use nullable `variant_id` with `nullOnDelete`.

The snapshot fields remain authoritative after either referenced catalog record is removed or changed.

---

# 3. Snapshot Fields

The following fields are historical data and must be copied into `order_items` when the order item is created.

## `sku`

Store the SKU that was actually purchased.

Do not calculate it dynamically from the current Product Variant.

Do not replace historical SKU data because the current variant SKU changes.

## `name`

Store the product name as it existed at order creation.

Do not dynamically read the current Product name for historical order rendering.

## `variant_name`

Store the selected variant name as it existed at order creation.

This may be nullable only when the order item genuinely has no variant-specific name.

Do not derive it from the current Variant during historical reads.

---

# 4. Historical Pricing

Use integer minor-unit money storage.

`unit_price_amount` is the historical unit selling price.

`line_total_amount` is the historical total for that order line.

Do not use:

* floating-point price fields
* database `float`
* database `double`
* application floating-point arithmetic for money

For V1/TZS, follow the existing money convention:

* currency remains TZS
* money is represented internally as integer minor units
* no duplicate floating-point representation is allowed

The Order already contains the authoritative order currency. Do not introduce a competing currency model in this phase.

## Line-total invariant

For a valid item:

`line_total_amount = unit_price_amount × quantity`

The implementation must use integer arithmetic.

Do not calculate historical line totals from the current Product Variant price during reads.

Do not recalculate and overwrite a stored historical line total merely because the current product price differs.

The order item stores the result that was authoritative when the order was created.

---

# 5. Quantity Rules

`quantity` is server-controlled.

Requirements:

* positive quantity
* no zero-value order item
* use an unsigned integer-compatible database type
* preserve the purchased quantity exactly

The existing cart contract limits a cart line quantity to a maximum of 100. Do not silently redefine that rule here.

Any checkout/order-creation enforcement of the cart limit belongs to the later checkout workflow, not this schema phase.

---

# 6. Product / Variant Consistency

When `variant_id` is present, it must belong to the same Product represented by `product_id`.

This cross-record consistency is a domain invariant.

Do not rely on a generic foreign key alone to enforce:

`order_items.variant_id → product_variants.product_id = order_items.product_id`

The database schema may retain both references, but the application/domain layer must validate their consistency when creating the snapshot.

Do not introduce a duplicated composite foreign-key design merely to enforce this relationship at schema level unless it is already consistent with the project's existing database strategy.

---

# 7. Order Aggregate Relationships

Implement Eloquent relationships:

### Order

`Order hasMany OrderItem`

### OrderItem

* `OrderItem belongsTo Order`
* `OrderItem belongsTo Product` with nullable relationship
* `OrderItem belongsTo ProductVariant` with nullable relationship

Keep relationship names explicit and conventional.

Do not introduce repository layers, generic base models, or speculative abstraction for this phase.

---

# 8. Historical Immutability

Order Items are historical records.

Once persisted as part of an order, the snapshot values must not be casually synchronized with catalog records.

Do not implement:

* product-to-order-item synchronization
* variant-to-order-item synchronization
* price refresh jobs
* catalog event listeners that rewrite order history
* model observers that update snapshots
* scheduled synchronization
* automatic historical recalculation

Do not use Eloquent events to silently mutate historical order-item values.

Later order workflows may permit tightly controlled operational actions, but those are separate concerns from catalog synchronization.

---

# 9. Deletion Rules

Do not soft-delete individual Order Items.

An Order Item exists as part of the historical Order aggregate.

Rules:

* deleting an Order cascades to its Order Items
* deleting a Product nulls `product_id`
* deleting a Product Variant nulls `variant_id`
* snapshot fields remain intact after either reference is nulled

Do not configure product or variant deletion to cascade into Order Items.

Historical commerce records must not disappear merely because a catalog record is retired or removed.

---

# 10. Constraints and Indexes

Add a foreign-key/index structure appropriate for the relationships.

At minimum:

* primary key on `id`
* index / foreign key on `order_id`
* index / foreign key on `product_id`
* index / foreign key on `variant_id`

Do not add indexes solely because they are theoretically possible.

An Order Item is normally retrieved through its Order, so `order_id` is the primary access path.

A unique constraint on `product_id` or `variant_id` is incorrect because the same product or variant may legitimately appear in multiple orders.

Do not require `(order_id, product_id)` uniqueness because product identity alone does not necessarily define historical line identity.

---

# 11. Mass Assignment and Serialization

Follow the existing project-wide mass-assignment policy.

Client input must never be allowed to directly assign historical server-controlled fields such as:

* `order_id`
* `product_id`
* `variant_id`
* `sku`
* `name`
* `variant_name`
* `unit_price_amount`
* `quantity`
* `line_total_amount`

These values must come from trusted order-creation logic later.

Do not expose internal database fields automatically through model serialization.

Public/customer/staff serialization belongs to the applicable later API phase and must use explicit allow-lists.

---

# 12. Do Not Add Checkout Logic Yet

This phase must not implement the process that creates an Order Item from a Cart Item.

Do not yet implement:

* cart-to-order conversion
* current-price resolution
* checkout transaction
* stock validation
* stock reservation
* order total calculation
* delivery fee calculation
* payment
* order acceptance
* order status transitions
* cancellation
* notifications

Those belong to later phases and must consume this model rather than redesign it.

---

# 13. Historical Pricing Source

The eventual order-creation workflow must snapshot pricing from the **authoritative current sellable variant price** at checkout/order creation time.

Do not snapshot a client-supplied price.

Do not trust:

* browser price
* Flutter price
* cart-submitted price
* hidden form values
* request payload totals

This phase only establishes where that historical result will be stored.

---

# 14. Financial Integrity

The schema and model must support these invariants:

* `unit_price_amount >= 0`
* `quantity > 0`
* `line_total_amount >= 0`
* line total is consistent with unit price × quantity
* historical values remain stable after catalog changes
* no floating-point money storage

The database should enforce what can be safely and portably enforced.

Cross-field financial rules that depend on runtime values may remain application/domain invariants where appropriate.

Do not introduce database triggers unless the project's documented database strategy explicitly requires them.

---

# 15. Model Design

Create the corresponding `OrderItem` model.

Use explicit casts appropriate for:

* integer money amounts
* integer quantity
* foreign-key IDs

Define relationships directly.

Do not add business-heavy methods to the model.

Keep financial calculations and order-creation orchestration in dedicated domain/service/action code when that workflow is implemented later.

The model should remain a persistence/domain entity rather than becoming a large procedural service.

---

# 16. Maintainability Requirements

For all new or refactored functions:

* cognitive complexity must be **15 or lower**
* no function may have more than **3 return statements**
* meaningful repeated string literals should be centralized using constants or enums where appropriate

Do not create a giant global constant class merely to satisfy this rule.

Prefer constants/enums close to the concept they represent.

Do not suppress static-analysis findings or raise analyzer thresholds to bypass these requirements.

Keep functions cohesive and small.

Avoid giant models, controllers, migrations, factories, or helper functions.

---

# 17. Tests

Add automated tests for the persistence and domain invariants introduced by this phase.

## Migration/schema tests

Verify:

* `order_items` table is created correctly
* primary key exists
* required columns exist
* foreign keys exist
* expected indexes exist
* nullable product and variant references are nullable
* Order deletion cascades to Order Items
* Product deletion nulls `product_id`
* Variant deletion nulls `variant_id`

## Model relationship tests

Verify:

* Order → Order Items
* Order Item → Order
* Order Item → Product
* Order Item → Product Variant

## Historical snapshot tests

Create an Order Item with snapshot values, then change the associated Product/Variant data and confirm the stored Order Item snapshot remains unchanged.

At minimum test changes to:

* product name
* variant name
* variant SKU
* variant price

The historical values must remain the original values.

## Financial tests

Verify:

* integer money storage
* positive quantity requirement
* line total consistency
* no floating-point representation

Test representative quantities including:

* 1
* multiple units
* the established cart maximum boundary

## Referential integrity tests

Verify that deleting an associated Product or Variant does not delete the historical Order Item and that the corresponding nullable reference becomes `null`.

## Ownership / aggregate tests

Verify that an Order Item cannot exist without a valid Order.

Do not introduce customer authorization tests yet; those belong to later API/domain workflow phases.

---

# 18. Factories and Seed Data

Extend factories as needed so tests can create:

* an Order with one item
* an Order with multiple items
* an item referencing a Product
* an item referencing a Product Variant
* an item with preserved snapshot values

Factory defaults must produce internally consistent records.

Do not create production-style fake order history in general seed data unless it is genuinely required by the project's development environment.

Do not introduce undocumented catalog fixtures or business scenarios merely to populate the database.

---

# 19. Migration Verification

Run the migration suite from a clean database.

Verify that:

1. all existing migrations execute successfully
2. `orders` exists before `order_items` is created
3. product/variant foreign keys resolve correctly
4. rollback works
5. migration can be re-run from a clean state
6. no unrelated tables are modified

Confirm the resulting schema matches the intended Order → Order Items aggregate.

---

# 20. Documentation Updates

Update only documentation that is actually affected by this phase.

Record the following domain fact in the appropriate authoritative document if it is not already captured:

> Order Items preserve historical product/variant identity, pricing, quantity, and line totals as snapshots. Catalog changes must not rewrite historical order items.

Do not create a permanent phase-specific documentation file solely for this decision.

If a conflict is discovered between existing documentation and this model, record the unresolved decision explicitly rather than silently changing the contract.

---

# 21. Security and Data Integrity Review

Before completion, verify:

* no client-controlled historical price is accepted by the model
* no client-controlled order ownership is introduced
* no mass-assignment path bypasses server authority
* no raw internal fields are serialized accidentally
* deleting catalog data cannot destroy historical order records
* historical values cannot be transparently overwritten through catalog updates
* migration foreign keys use the intended delete behavior
* no sensitive internal operational fields are added unnecessarily

---

# Definition of Done

Phase 3.10 is complete when:

* `order_items` migration exists and follows the agreed schema
* Order Item belongs to Order
* Product and Variant references are nullable historical references
* historical SKU/name/variant-name values are persisted
* historical unit price, quantity, and line total are persisted
* money uses integer minor units
* Order deletion cascades to its items
* Product/Variant deletion nulls references without deleting historical items
* snapshot data is not automatically synchronized with catalog data
* Eloquent relationships are implemented
* required schema, relationship, historical-snapshot, and financial tests pass
* factories support valid Order Item fixtures
* clean migration and rollback succeed
* maintainability constraints are satisfied
* no checkout, payment, inventory, delivery, or order-workflow logic has been pulled into this phase

# Out of Scope

Do not implement in Phase 3.10:

* cart-to-order item conversion
* checkout
* pricing resolution
* stock reservation
* payment
* delivery
* order status transitions
* order cancellation
* order status history
* customer/staff order APIs
* notifications
* invoices
* refunds
* discounts/promotions
* tax calculation
* product synchronization
* order item editing workflows

# STOP CONDITION

Stop after the Order Item persistence model, relationships, integrity rules, tests, and migration verification are complete.

Do not proceed to Phase 3.11 until this phase passes its Definition of Done.

The next phase is:

**Phase 3.11 — Order Status History Schema**
