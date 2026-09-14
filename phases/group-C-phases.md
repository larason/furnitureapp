# Phase 3.17 — Foreign Keys / Indexes / Constraints Review

## Purpose

Perform a final relational-integrity review of the database schema produced by Phases 3.1–3.16.

The goal is to verify that:

* foreign keys correctly represent domain relationships;
* delete and update behavior cannot silently corrupt historical or operational data;
* nullability matches the agreed domain rules;
* unique constraints enforce only true invariants;
* check constraints enforce invariants that are appropriate at database level;
* indexes support the documented access patterns without unnecessary duplication;
* money, quantities, timestamps, and identifiers use safe database types;
* CLOSED V1 enums remain consistent with the API contract;
* cross-table invariants that cannot be fully enforced by MySQL are explicitly identified for application/domain enforcement;
* migration order can reliably rebuild the complete schema from an empty database;
* constraints do not conflict with legitimate business workflows;
* the schema is ready for Group C exit and later implementation phases.

This phase is a **review and hardening phase**. Do not redesign the domain or introduce new business concepts.

---

## Dependencies

Complete before starting:

* Phase 3.1 — Users schema
* Phase 3.2 — Roles / permissions model
* Phase 3.3 — Categories schema
* Phase 3.4 — Products schema
* Phase 3.5 — Product Variants schema
* Phase 3.6 — Product Images schema
* Phase 3.7 — Inventory schema
* Phase 3.8 — Cart schema
* Phase 3.9 — Orders schema
* Phase 3.10 — Order Items schema
* Phase 3.11 — Order Status History schema
* Phase 3.12 — Payment schema
* Phase 3.13 — Delivery schema
* Phase 3.14 — Furniture Request schema
* Phase 3.15 — Enquiry schema
* Phase 3.16 — Notification schema

The API contract is already frozen for V1. Do not change endpoint semantics, public enum values, field nullability, ownership rules, or financial rules merely to simplify the database.

---

## Authoritative Inputs

Use these sources in this priority order:

1. `docs/VISION.md`
2. `AGENTS.md`
3. `docs/api/api-contract.md`
4. `docs/api/api-resources.md`
5. `docs/api/api-conventions.md`
6. `docs/domain/business-rules.md`
7. `docs/decisions.md`
8. The final implementation decisions from Phases 3.1–3.16.

The project already establishes that V1 enums are CLOSED and machine-stable, and that a compatibility-breaking schema/API change requires explicit review.

---

# 1. Review Strategy

Review the schema in this order:

1. table and column types;
2. nullability;
3. primary keys;
4. foreign keys;
5. foreign-key delete/update actions;
6. unique constraints;
7. check constraints;
8. indexes;
9. composite indexes;
10. cross-table invariants;
11. migration dependency order;
12. representative constraint/failure tests;
13. performance sanity review;
14. security/data-retention implications.

For every proposed change ask:

> Does this enforce an already-agreed business invariant, or does it introduce a new rule?

Only the first category belongs in Phase 3.17.

---

# 2. Global Database Standards

Apply these consistently.

## 2.1 Primary Keys

Every domain table must have a stable primary key.

Use the project's established ID strategy consistently.

Do not introduce a second competing identifier merely for convenience.

Business-facing references remain separate where already defined:

* `slug` for catalog lookup;
* `order_reference` for customer-facing orders;
* `payment_reference` for payment records;
* `request_reference` for furniture requests;
* `enquiry_reference` for enquiries.

Do not expose raw sequential database IDs as public business identifiers where the API contract specifies opaque IDs or references.

---

## 2.2 Foreign-Key Column Naming

Use conventional relationship names:

* `user_id`
* `customer_id`
* `category_id`
* `product_id`
* `product_variant_id`
* `variant_id`
* `cart_id`
* `order_id`
* `payment_id`
* `actor_id`
* `recipient_user_id`

Avoid ambiguous names such as:

* `owner`
* `user`
* `parent`
* `reference_id`

unless the domain explicitly requires a generic reference.

---

## 2.3 Integer / Money Types

Money fields must use integer minor units.

Do not use floating-point types.

Do not use database `float` or `double` for financial amounts.

Use a sufficiently large integer type for:

* variant price;
* compare-at price;
* cost;
* order subtotal;
* order delivery fee;
* order total;
* order-item unit price;
* order-item line total;
* payment amount.

The database representation must remain consistent with the API rule that money is integer minor units and always paired with `currency`.

TZS remains the V1 default currency.

Do not introduce currency-conversion logic into the schema review.

---

## 2.4 Quantity Types

Quantities that cannot be negative should use an unsigned integer-compatible type.

Examples:

* inventory `quantity`;
* inventory `reserved_quantity`;
* cart-item `quantity`;
* order-item `quantity`;
* furniture-request `quantity` where persisted.

Do not use floating-point types for countable units.

---

## 2.5 Timestamps

Use a consistent database timestamp strategy for:

* `created_at`;
* `updated_at`;
* `occurred_at`;
* `initiated_at`;
* `confirmed_at`;
* `expires_at`;
* `received_at`;
* `processed_at`.

Nullable lifecycle timestamps remain nullable where the event has not happened.

Do not substitute magic zero dates or empty strings for absent timestamps.

The external API continues to serialize timestamps in the project's UTC ISO-8601 format.

---

## 2.6 Soft Deletes

Keep soft deletes only where previously approved.

Current approved use:

* `products.deleted_at`.

Do not add soft deletes automatically to:

* orders;
* payments;
* order status history;
* deliveries;
* notifications;
* carts;
* inventory;
* furniture requests;
* enquiries.

Historical and financial records must remain structurally traceable.

---

# 3. Users and Profiles

## 3.1 `users`

Verify:

* primary key exists;
* `email` has the intended uniqueness rule;
* authentication fields are appropriately non-null;
* `name`, `email`, and `phone` match the shared identity model;
* `is_active` is explicitly typed as boolean-compatible;
* credential material is never nullable unless the authentication design explicitly requires it;
* password hashes are never exposed through serialization.

Do not add:

* `role` column;
* `is_admin`;
* `is_staff`;
* `is_customer`;
* customer restriction fields.

Role assignment belongs to the RBAC model.

The existing authorization model is relational and server-controlled rather than client-supplied.

## 3.2 `customer_profiles`

Verify:

* `user_id` is a foreign key to `users.id`;
* `user_id` is unique;
* the relationship is one-to-one;
* deletion behavior does not accidentally delete the user or historical commerce data.

If profile data is currently minimal, do not use this phase to add:

* address book;
* loyalty;
* preferences;
* saved delivery addresses.

## 3.3 `staff_profiles`

Apply the same one-to-one integrity review.

Staff-profile deletion must not imply deletion of the underlying user identity.

Do not use profile records as substitutes for RBAC role membership.

---

# 4. RBAC Tables

Review the exact role/permission implementation selected in Phase 3.2.

Verify that:

* role relationships have proper foreign keys;
* permission relationships have proper foreign keys;
* unique composite constraints prevent duplicate assignments;
* pivot-table foreign keys use appropriate cascading behavior;
* deleting a role/permission cannot orphan pivot rows;
* customer, staff, and admin roles remain CLOSED at application/domain level;
* no database rule accidentally grants a new role merely because a row exists.

Do not introduce wildcard admin permissions or a generic `is_admin` shortcut.

Authorization remains a combination of identity, role, resource, action, ownership, and business state.

---

# 5. Categories

## 5.1 `categories`

Verify:

* `parent_id` references `categories.id`;
* `parent_id` is nullable for root categories;
* deleting a parent uses `SET NULL` / `nullOnDelete` according to the approved schema;
* `slug` is unique;
* `display_order` is non-negative;
* `is_active` is boolean-compatible;
* `space_type` accepts only the approved CLOSED values.

Do not allow a self-parenting category.

At application/domain level also verify:

* category hierarchy does not exceed the agreed three-level taxonomy;
* seed data follows the approved taxonomy;
* cyclic parent relationships cannot be introduced.

Do not invent additional categories simply to satisfy a foreign key or recommendation relationship.

## 5.2 `category_recommendations`

Verify:

* `category_id` references `categories.id`;
* `recommended_category_id` references `categories.id`;
* both use correct delete behavior;
* `(category_id, recommended_category_id)` is unique;
* self-recommendation is rejected;
* relation type follows the approved CLOSED vocabulary;
* priority is appropriately bounded.

Do not create a separate table for concepts not already approved.

---

# 6. Products

## 6.1 `products`

Verify:

* `category_id` references `categories.id`;
* category deletion is restrictive rather than silently removing products;
* `slug` is unique;
* `assembly_required` uses the approved CLOSED values;
* product lifecycle flags are boolean-compatible;
* `deleted_at` is indexed appropriately for active/public catalogue queries where useful.

Do not allow deletion of a category that would orphan a product.

Do not cascade-delete products from category deletion.

---

# 7. Product Variants

## 7.1 `product_variants`

Verify:

* `product_id` references `products.id`;
* product deletion cascades variants;
* `sku` is unique;
* money uses integer minor units;
* dimensions and weight use appropriate numeric types;
* `is_default` and `is_active` are boolean-compatible;
* `display_order` is non-negative;
* JSON attributes are nullable or required exactly as previously approved.

The following rules are domain/application invariants unless the chosen MySQL implementation can enforce them safely:

* a variant must belong to the stated product;
* a product may have at most one default variant.

Do not duplicate product-level price data here or elsewhere unless specifically required by the frozen contract.

---

# 8. Product Images

## 8.1 `product_images`

Verify:

* `product_id` references `products.id` with cascade delete;
* `product_variant_id` references `product_variants.id` with `SET NULL`;
* `sort_order` is unsigned;
* `is_primary` is boolean-compatible;
* `file_path` is non-null;
* `alt_text` nullability matches the approved design.

Cross-table domain invariant:

> When `product_variant_id` is present, that variant must belong to the same `product_id`.

This is not safely represented by two independent foreign keys alone.

Enforce it at the application/domain layer unless a carefully designed composite-key constraint is intentionally adopted.

Do not introduce a composite-key redesign merely for this review.

The approved API embeds images in product detail rather than introducing an independent public images workflow.

---

# 9. Inventory

## 9.1 `product_stocks`

Verify:

* `product_variant_id` references `product_variants.id`;
* deletion of a variant cascades its stock rows;
* `quantity >= 0`;
* `reserved_quantity >= 0`;
* `reserved_quantity <= quantity`;
* `(product_variant_id, warehouse_location)` is unique;
* `warehouse_location` is non-empty and appropriately bounded.

`available_quantity` remains derived:

`quantity - reserved_quantity`

Do not persist a duplicate `available_quantity` column.

The invariant `reserved_quantity <= quantity` should be enforced with a database check if the deployed MySQL version supports the required behavior reliably; otherwise also enforce it inside every mutation transaction.

Concurrency-safe inventory mutation is a later application concern; the schema review must not pretend a simple CHECK removes race conditions.

The project already requires concurrency-safe handling for inventory mutations.

---

# 10. Carts

## 10.1 `carts`

Verify the XOR ownership model:

* authenticated cart → `user_id` populated and `guest_token_digest` null;
* guest cart → `user_id` null and `guest_token_digest` populated;
* never both;
* never neither.

Verify:

* `user_id` references `users.id`;
* guest token digest is fixed-length `CHAR(64)` or the already-approved equivalent;
* `guest_token_digest` is unique;
* status is CLOSED `ACTIVE|INACTIVE`;
* appropriate index exists on `user_id + status`;
* appropriate index exists on `status + updated_at`.

Do not persist the raw guest bearer token.

The raw client credential and the stored digest remain conceptually separate.

At database/application level verify:

* at most one ACTIVE cart per authenticated customer;
* historical INACTIVE carts remain possible;
* guest cart ownership cannot be reassigned to another customer;
* cart ownership cannot be changed through ordinary client updates.

## 10.2 `cart_items`

Verify:

* `cart_id` cascades on cart deletion;
* `product_id` is restrictive;
* `variant_id` is restrictive;
* quantity is positive and bounded as approved;
* appropriate cart lookup index exists.

Cross-table domain invariant:

* when `variant_id` is non-null, that variant must belong to `product_id`.

Do not introduce a persisted cart price.

---

# 11. Orders

## 11.1 `orders`

Verify:

* `customer_id` references `users.id`;
* customer deletion is restrictive;
* `order_reference` is unique;
* `status` is CLOSED;
* `fulfillment_type` is CLOSED;
* `delivery_fee_status` is CLOSED;
* `currency` is fixed/validated as approved;
* money fields use unsigned integer-compatible storage;
* delivery address is nullable;
* recipient snapshot fields have correct nullability.

Verify these business combinations:

### Pickup

* `fulfillment_type = PICKUP`
* `delivery_fee_status = FINALIZED`
* `delivery_fee_amount = 0`
* `total_amount = subtotal_amount`

### Delivery before fee assignment

* `fulfillment_type = DELIVERY`
* `delivery_fee_status = PENDING`
* `delivery_fee_amount = NULL`
* `total_amount = subtotal_amount`

`total_amount` is provisional while `PENDING` and must not be treated as the payable amount — see `docs/api/api-contract.md §24`; payment stays blocked until `FINALIZED`.

### Delivery after fee assignment

* `fulfillment_type = DELIVERY`
* `delivery_fee_status = FINALIZED`
* `delivery_fee_amount >= 0`
* `total_amount = subtotal_amount + delivery_fee_amount`

These cross-column relationships should be enforced as strongly as practical through database checks, with domain validation providing the authoritative workflow enforcement.

Do not create a customer-facing address foreign key because the approved order model stores a historical address snapshot.

---

# 12. Order Items

## 12.1 `order_items`

Verify:

* `order_id` cascades on order deletion;
* `product_id` uses `SET NULL`;
* `variant_id` uses `SET NULL`;
* product/variant references are nullable;
* snapshot `sku`, `name`, and optional `variant_name` remain available even if catalog entities later disappear;
* `unit_price_amount`, `quantity`, and `line_total_amount` use safe integer types;
* quantity is positive and appropriately bounded.

Verify:

`line_total_amount = unit_price_amount × quantity`

This is a domain/application invariant and may additionally be checked by application tests.

Do not make historical order snapshots dependent on the continued existence of product rows.

---

# 13. Order Status History

## 13.1 `order_status_history`

Verify:

* `order_id` cascades with the order;
* `actor_id` references `users.id` with `SET NULL`;
* `from_status` is nullable;
* `to_status` is required;
* `actor_type` is CLOSED;
* `occurred_at` is required;
* `created_at` is required;
* there is no `updated_at`.

Verify append-only integrity at the application layer.

Do not provide:

* generic UPDATE;
* generic DELETE;
* mutable event rows.

Indexes should support:

* all history for one order ordered by `occurred_at`;
* deterministic tie-breaking with `id`.

The tracking model already requires chronological history and does not introduce a second authoritative status source.

---

# 14. Payments

## 14.1 `payments`

Verify:

* `order_id` references `orders.id`;
* payment deletion does not cascade from order deletion;
* `payment_reference` is unique;
* multiple payment attempts per order remain possible;
* provider transaction identity is uniquely protected when the provider supplies it;
* payment amount and currency use safe financial types;
* lifecycle timestamps have correct nullability;
* provider secrets and raw payment credentials are absent.

Where appropriate, use a uniqueness strategy that allows `provider_transaction_id` to remain nullable without creating false duplicate conflicts.

Do not make `order_id` unique.

## 14.2 `payment_webhook_events`

Verify:

* `payment_id` references `payments.id`;
* deletion of a payment does not erase webhook-event history;
* `(provider, provider_event_id)` is unique;
* `processing_status` uses the approved CLOSED internal values;
* `processed_at` and `failure_reason` have correct nullability.

Webhook-event persistence must support idempotent processing.

The project explicitly requires durable uniqueness and atomic check/apply behavior for repeated provider events.

Do not store:

* raw card data;
* provider authentication secrets;
* sensitive webhook credentials;
* unrestricted raw provider payloads unless separately justified and secured later.

---

# 15. Deliveries

## 15.1 `deliveries`

Verify:

* `order_id` references `orders.id`;
* `order_id` is unique;
* one delivery record can exist for one delivery order;
* order deletion behavior is restrictive;
* recipient/contact/address snapshot fields have correct types and nullability;
* `delivery_instructions` remains optional.

Domain invariant:

* a Pickup order must not have a Delivery row;
* a Delivery order may have exactly one Delivery row once created.

Do not introduce:

* `delivery_status`;
* GPS fields;
* driver fields;
* carrier fields;
* tracking numbers;
* ETA;
* route geometry.

Order lifecycle and order status history remain authoritative.

---

# 16. Furniture Requests

## 16.1 `furniture_requests`

Verify:

* `user_id` is nullable;
* `product_id` is nullable;
* `product_id` uses `SET NULL`;
* `user_id` uses `SET NULL`;
* `request_reference` is unique;
* `request_status` is CLOSED;
* structured JSON fields are correctly nullable;
* contact fields match the schema defined for persistence.

Guest submissions must remain valid without a user record.

Authenticated submissions must derive `user_id` from the authenticated server identity rather than accepting arbitrary ownership from the client.

Where product linkage exists, application/domain validation must ensure the product is eligible for the requested workflow.

Do not introduce a hard uniqueness constraint on request content.

---

# 17. Enquiries

## 17.1 `enquiries`

Verify:

* `user_id` nullable with `SET NULL`;
* `product_id` nullable with `SET NULL`;
* `order_id` nullable with restrictive behavior;
* `enquiry_reference` unique;
* `enquiry_status` is CLOSED;
* customer-supplied content remains historically stable;
* staff internal notes are separately stored.

Do not impose hard uniqueness on:

* email;
* phone;
* subject;
* product;
* order;
* message.

Multiple legitimate enquiries may contain the same information.

Anonymous retrieval must not be enabled merely because the row is public in database terms.

---

# 18. Notifications

## 18.1 `notifications`

Verify:

* `recipient_user_id` references `users.id`;
* deletion behavior preserves or intentionally removes notifications according to the approved account-retention policy;
* `type` uses the approved CLOSED notification vocabulary;
* `title` and `message` are server-generated;
* `target` is nullable structured JSON;
* `source_type` and `source_id` are nullable;
* `read_at` is nullable;
* there is no `is_read` duplicate field.

Verify:

* `read_at IS NULL` → unread;
* `read_at IS NOT NULL` → read.

Do not create a public foreign key from `source_id` because source events may originate from different domains.

Instead, use:

`source_type + source_id`

as the generic traceability pair, with domain/application validation.

The notification system remains recipient-scoped and private; clients do not create notification records directly.

---

# 19. Foreign-Key Delete Policy Matrix

Produce and review one explicit matrix covering every FK.

The expected direction is:

| Relationship                  | Expected behavior                                        |
| ----------------------------- | -------------------------------------------------------- |
| Customer/profile → User       | Profile should not delete User                           |
| Category parent → Category    | `SET NULL`                                               |
| Product → Category            | `RESTRICT`                                               |
| Variant → Product             | `CASCADE`                                                |
| Image → Product               | `CASCADE`                                                |
| Image → Variant               | `SET NULL`                                               |
| Stock → Variant               | `CASCADE`                                                |
| Cart → User                   | preserve/restrict according to approved retention policy |
| Cart Item → Cart              | `CASCADE`                                                |
| Cart Item → Product           | `RESTRICT`                                               |
| Cart Item → Variant           | `RESTRICT`                                               |
| Order → Customer/User         | `RESTRICT`                                               |
| Order Item → Order            | `CASCADE`                                                |
| Order Item → Product          | `SET NULL`                                               |
| Order Item → Variant          | `SET NULL`                                               |
| Status History → Order        | `CASCADE`                                                |
| Status History → Actor/User   | `SET NULL`                                               |
| Payment → Order               | `RESTRICT`                                               |
| Webhook Event → Payment       | preserve on payment deletion                             |
| Delivery → Order              | `RESTRICT`                                               |
| Furniture Request → User      | `SET NULL`                                               |
| Furniture Request → Product   | `SET NULL`                                               |
| Enquiry → User                | `SET NULL`                                               |
| Enquiry → Product             | `SET NULL`                                               |
| Enquiry → Order               | `RESTRICT`                                               |
| Notification → Recipient/User | follow approved account-retention policy                 |

Do not automatically apply `CASCADE` everywhere.

Historical records must survive deletion of mutable catalogue data.

---

# 20. Unique-Constraint Review

Every unique constraint must answer:

> Is duplicate data actually impossible according to the business rules?

Required or expected uniqueness includes:

* user email, according to authentication policy;
* category slug;
* product slug;
* product variant SKU;
* cart guest token digest;
* category recommendation pair;
* one active cart per customer, through a suitable uniqueness strategy;
* order reference;
* payment reference;
* provider + provider transaction identity where applicable;
* webhook provider + provider event ID;
* delivery order ID;
* furniture request reference;
* enquiry reference;
* RBAC pivot assignment combinations.

Do not add convenience uniqueness for:

* names;
* descriptions;
* phone numbers unless explicitly approved;
* addresses;
* enquiry messages;
* furniture request contents.

---

# 21. Check-Constraint Review

Where safe and supported by the project's MySQL version, use database CHECK constraints for local invariants such as:

* non-negative numeric quantities;
* non-negative money values;
* `reserved_quantity <= quantity`;
* valid boolean domains;
* self-reference prevention where straightforward;
* mutually exclusive cart ownership fields;
* order fee/total combinations where implementation is practical.

Do not attempt to encode full business workflows in CHECK constraints.

Do not use CHECK constraints as a replacement for:

* authorization;
* order transition logic;
* payment workflow;
* inventory concurrency control;
* role/permission evaluation.

---

# 22. Enum Consistency Review

Inventory every database enum or constrained status field and compare it with the authoritative V1 API registry.

Verify:

* exact spelling;
* exact case;
* exact allowed values;
* no undocumented additions;
* no removed values;
* no lowercase/uppercase drift.

The V1 convention is CLOSED for machine-facing enums, with the explicitly documented availability exception.

Do not introduce future statuses simply because a domain might eventually need them.

Examples that must not be silently invented:

* extra order statuses;
* extra payment statuses outside the approved contract;
* delivery statuses;
* notification types;
* extra staff roles;
* extra request statuses.

If implementation and contract disagree, **STOP and record the mismatch** rather than silently changing the contract.

---

# 23. Index Review

For every index, document:

* columns;
* expected query;
* selectivity/reason;
* whether it duplicates another index.

At minimum inspect access patterns for:

## Users

* unique email;
* role/RBAC lookup indexes required by the selected implementation;
* active-state lookup only where actually queried.

## Categories

* unique slug;
* parent lookup;
* active/display-order traversal.

## Products

* unique slug;
* category filtering;
* active/deleted catalogue queries;
* featured/active queries if actually used.

Avoid creating every conceivable combination.

## Variants

* unique SKU;
* product lookup;
* product + active ordering if needed.

## Images

* product lookup + ordering;
* variant lookup + ordering.

A useful composite index may be:

`(product_id, sort_order, id)`

when deterministic image ordering is required.

## Inventory

* unique `(product_variant_id, warehouse_location)`;
* variant lookup;
* warehouse-oriented lookup only if an approved operational query requires it.

## Carts

* user + status;
* status + updated_at;
* unique guest token digest.

## Cart Items

* cart lookup;
* cart + product/variant access only where required.

## Orders

Support:

* customer orders by creation time;
* staff operational order listing by status/creation time;
* order-reference lookup.

Use composite indexes based on actual query shapes rather than indexing every individual column.

## Order Items

* order lookup.

## Order Status History

* order + occurred_at + deterministic tie-breaker.

## Payments

* order + creation time;
* payment reference;
* provider/provider transaction identity.

## Webhook Events

* unique provider + provider event ID;
* payment lookup where required.

## Deliveries

* unique order ID.

## Furniture Requests

Support:

* authenticated user's requests;
* request status + creation time;
* reference lookup;
* product linkage where operationally queried.

## Enquiries

Support:

* user's enquiries;
* status + creation time;
* product lookup;
* order lookup;
* reference lookup.

## Notifications

Support:

* recipient + creation time;
* recipient + unread state where operationally useful.

The standard customer inbox ordering remains:

`created_at DESC, id ASC`

and protected collections must always be queried within the authorized dataset.

---

# 24. Avoid Redundant Indexes

Remove indexes that are fully covered by a stronger composite/unique index unless the DB optimizer and query workload justify retaining both.

Examples to review:

* standalone foreign-key index + equivalent composite index;
* standalone status index when `(status, created_at)` is already present and all relevant queries use both;
* duplicate unique + non-unique indexes over the same columns.

Do not remove framework-required indexes blindly.

Confirm actual migration/database behavior before deletion.

---

# 25. Cross-Table Invariant Register

Create a final register with three categories:

### A. Database-enforced

Examples:

* unique SKU;
* unique slug;
* FK existence;
* order-reference uniqueness;
* webhook-event uniqueness;
* one delivery per order;
* non-negative values;
* cart guest-token digest uniqueness.

### B. Database + application enforced

Examples:

* reserved quantity cannot exceed quantity;
* cart ownership XOR;
* one ACTIVE cart per customer;
* order fulfillment/fee/total consistency;
* at most one default variant;
* variant belongs to product;
* image variant belongs to image product;
* cart variant belongs to cart product.

### C. Application/domain only

Examples:

* authorized customer owns the cart/order;
* valid order state transitions;
* staff/admin capability decisions;
* payment success causes correct order transition;
* product type eligibility for furniture requests;
* notification creation from authoritative business events.

Do not misclassify authorization as a database constraint.

---

# 26. Referential-Integrity Test Matrix

Add migration/integration tests that explicitly prove:

## Foreign Keys

* invalid FK values fail;
* intended parent deletion succeeds/fails according to policy;
* cascading children are removed only where approved;
* historical references are nulled where approved.

## Unique Constraints

* duplicate slug fails;
* duplicate SKU fails;
* duplicate order reference fails;
* duplicate payment reference fails;
* duplicate webhook provider/event identity fails;
* duplicate delivery for one order fails.

## Check Constraints

* negative quantities fail;
* negative money values fail;
* invalid reservation state fails;
* invalid ownership combinations fail where DB-supported.

## Historical Integrity

* deleting a product does not destroy historical order snapshots;
* deleting a variant does not destroy historical order item snapshots;
* payment history is preserved;
* order history remains coherent;
* requests/enquiries survive removal of optional catalogue/user references according to the approved delete rules.

## Cart Integrity

* guest cart cannot become authenticated cart through arbitrary field updates;
* cart cannot have both owner types;
* cart cannot have neither owner type;
* duplicate active customer carts are rejected.

---

# 27. Migration Order Review

Confirm migrations can be executed from a clean database in dependency order.

The dependency graph should generally flow:

`users`

→ `RBAC tables`

→ `categories`

→ `products`

→ `product_variants`

→ `product_images`

→ `product_stocks`

→ `carts`

→ `cart_items`

→ `orders`

→ `order_items`

→ `order_status_history`

→ `payments`

→ `payment_webhook_events`

→ `deliveries`

→ `furniture_requests`

→ `enquiries`

→ `notifications`

Adjust only where the actual FK graph requires a different sequence.

Do not solve a circular dependency by weakening a foreign key.

If a real cycle exists, document and resolve it deliberately.

---

# 28. Migration Safety

Verify that:

* all foreign-key column types exactly match referenced primary-key types;
* signedness matches;
* string lengths/collations are compatible where applicable;
* referenced columns are indexed;
* migrations run successfully on an empty database;
* migrations run successfully from the current project state;
* rollback behavior is understood;
* destructive changes are not introduced accidentally;
* production seed data is not required for referential integrity unless explicitly approved.

Never silently drop existing data as part of this phase.

---

# 29. Performance Sanity Review

Do not prematurely optimize.

Check only realistic V1 workloads:

* public product/category browsing;
* customer order history;
* staff order queue;
* notification inbox;
* inventory lookup;
* cart retrieval;
* payment lookup by order/reference;
* request/enquiry operational queues.

Look for:

* missing FK indexes;
* missing order-by support;
* large scans on private collections;
* duplicate indexes;
* low-value indexes on columns with extremely poor selectivity;
* composite indexes whose order does not match actual filtering/sorting.

Do not add indexes solely because a column is frequently present in a migration.

---

# 30. Security and Data-Integrity Review

Verify that database design does not undermine the authorization model.

In particular:

* staff cannot gain customer-account authority through a database shortcut;
* client-supplied `user_id`, `actor_id`, `recipient_user_id`, or role fields cannot be trusted;
* historical records cannot be reassigned to another owner;
* notification recipients are server-derived;
* order customers are server-derived;
* request/enquiry users are server-derived when authenticated;
* payment records cannot be reassigned casually;
* audit/history data is not casually mutable.

The project requires server-derived ownership and rejects client-supplied authority.

---

# 31. Maintainability Review

Apply these code-quality constraints during migration/model implementation:

* keep each migration cohesive;
* keep meaningful string literals centralized where they represent domain constants;
* centralize CLOSED status/type definitions rather than duplicating them across migrations, models, policies, and services;
* avoid duplicated index/constraint definitions;
* keep database naming consistent;
* use small schema helper methods only where they materially improve clarity.

For implementation functions/methods:

* cognitive complexity target: **≤ 15**;
* maximum **3 return statements** per function;
* avoid deeply nested conditional migration logic;
* avoid giant migration files that mix unrelated domains;
* comments should be minimal and explain only non-obvious constraints.

Do not create abstractions merely to reduce line count.

---

# 32. Schema Consistency Audit

Produce a final table covering every table with:

| Table | PK | FKs | Delete policy | Unique constraints | Checks | Main indexes | Domain-only invariants |
| ----- | -- | --- | ------------- | ------------------ | ------ | ------------ | ---------------------- |

Every column and relationship must be explainable through one of:

* API contract;
* domain rule;
* security rule;
* persistence integrity requirement.

If a field cannot be justified, flag it for removal or explicit decision.

---

# 33. Required Deliverables

At the end of Phase 3.17, provide:

1. a complete schema integrity review;
2. a final FK/delete-policy matrix;
3. a unique/check-constraint matrix;
4. an index inventory and duplicate-index review;
5. a cross-table invariant register;
6. migration dependency/order verification;
7. referential-integrity migration tests;
8. any required migration corrections;
9. an explicit list of unresolved issues, if any.

Do not create a permanent `phase-3.17.md` document unless the project documentation structure specifically requires it.

Update the consolidated project documentation only where the review identifies a durable schema decision or correction.

---

# 34. Definition of Done

Phase 3.17 is complete only when:

* every Phase 3.1–3.16 table has been reviewed;
* all FKs are explicit;
* FK delete behavior is deliberate;
* no orphan-producing relationship remains unintentionally;
* unique constraints reflect real invariants;
* no unjustified uniqueness constraints remain;
* check constraints cover appropriate local invariants;
* cross-table invariants are explicitly documented;
* indexes support the real V1 query patterns;
* redundant indexes have been reviewed;
* all FK types are compatible;
* all money fields use safe integer storage;
* quantities cannot become negative;
* CLOSED enums are consistent with the contract;
* migration order works from an empty database;
* migration tests cover the critical constraints;
* no customer/order/payment history can be accidentally destroyed through catalogue deletion;
* authorization/ownership is not incorrectly delegated to the database;
* no unresolved schema contradiction with the frozen V1 API remains.

---

# 35. Out of Scope

Do not implement:

* order workflow/state-transition services;
* payment provider integration;
* payment webhook signature verification;
* inventory reservation algorithms;
* concurrency strategy implementation;
* checkout orchestration;
* delivery pricing logic;
* authentication flows;
* authorization policies;
* notification dispatch;
* email/SMS/push delivery;
* frontend changes;
* API endpoint implementation;
* attachment storage;
* warehouse-management domain;
* advanced reporting indexes;
* search-engine infrastructure;
* new product/category/domain concepts;
* future V2 statuses or roles.

Those belong to later phases.

---

# 36. STOP Condition

STOP after the schema review, constraint corrections, migration updates, and integrity tests are complete.

Do not begin:

* model/business-service implementation beyond what is necessary to validate the schema;
* repository/API implementation;
* controllers;
* checkout;
* payments;
* inventory workflows;
* frontend integration.

If the review discovers a conflict between an existing schema decision and the frozen V1 API/domain contract, do not silently redesign it. Record the conflict and STOP for an explicit decision before proceeding.

Phase 3.17 must end with the database structure being **internally consistent, referentially safe, migration-rebuildable, and aligned with the frozen V1 domain contract**.
