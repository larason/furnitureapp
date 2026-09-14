# Phase 3.18 — Factories and Seed Data

## Purpose

Create safe, deterministic, repeatable Laravel factories and seeders for the database/domain model established in Phases 3.1–3.17.

The purpose of this phase is to make the database easy to populate for:

* local development;
* automated tests;
* API development;
* frontend integration;
* manual verification of relationships and constraints.

Seed data must represent the **real agreed domain**, not invent future functionality.

Laravel migrations remain the authoritative database schema history, while factories/seeders provide repeatable development and test data.

---

## Dependencies

Complete before starting:

* Phase 3.1 — Users schema
* Phase 3.2 — Roles/permissions model
* Phase 3.3 — Categories schema
* Phase 3.4 — Products schema
* Phase 3.5 — Product variants schema
* Phase 3.6 — Product images schema
* Phase 3.7 — Inventory schema
* Phase 3.8 — Cart schema
* Phase 3.9 — Orders schema
* Phase 3.10 — Order items snapshot model
* Phase 3.11 — Order status history
* Phase 3.12 — Payment
* Phase 3.13 — Delivery
* Phase 3.14 — Furniture requests
* Phase 3.15 — Enquiries
* Phase 3.16 — Notifications
* Phase 3.17 — FK/index/constraint review

Do not start factories until the final relationships and constraints have been reviewed.

---

## Authoritative Inputs

Use:

1. `AGENTS.md`
2. `docs/VISION.md`
3. `docs/api/api-contract.md`
4. `docs/api/api-resources.md`
5. `docs/api/api-conventions.md`
6. `docs/domain/business-rules.md`
7. `docs/decisions.md`
8. Final schema decisions from Phases 3.1–3.17.

Do not use factories to compensate for an undefined domain rule.

---

# 1. General Factory Rules

Factories must:

* generate valid records by default;
* respect all required FK relationships;
* respect all unique constraints;
* respect all CHECK constraints;
* use realistic but obviously synthetic development data;
* avoid production secrets;
* avoid real people's personal information;
* remain deterministic enough for tests when a seed is fixed;
* support states where a domain naturally has multiple valid states.

Do not put business workflows into factories.

A factory should construct valid records.

It must not simulate:

* payment processing;
* inventory reservation algorithms;
* order state-transition services;
* notification dispatch;
* authentication workflows;
* external webhooks.

---

# 2. Factory Scope

Create factories for domain models that will be instantiated during development and tests.

At minimum review/create factories for:

* `User`
* `CustomerProfile`
* `StaffProfile`
* RBAC models required by the selected implementation
* `Category`
* category recommendation relations through `Category`
* `Product`
* `ProductVariant`
* `ProductImage`
* `ProductStock`
* `Cart`
* `CartItem`
* `Order`
* `OrderItem`
* `OrderStatusHistory`
* `Payment`
* `PaymentWebhookEvent`
* `Delivery`
* `FurnitureRequest`
* `Enquiry`
* `Notification`

Do not create factories for tables that do not actually exist.

Do not create artificial factories for pivot tables unless the selected RBAC implementation requires them.

---

# 3. User Factories

Create clearly separated factory states for:

### Customer

Valid:

* active;
* realistic synthetic name;
* unique synthetic email;
* synthetic phone;
* secure generated password hash.

### Staff

Valid staff identity with appropriate role/profile association.

### Admin

Valid administrative identity for test/development environments only.

Never hard-code a production administrator password.

For seeded development credentials, document them clearly as **local-only development credentials**, and make the password value configurable through environment/configuration rather than committing a production secret.

Never store plaintext passwords in production seed data.

---

# 4. RBAC Seed Data

Create the exact V1 role set:

* `CUSTOMER`
* `STAFF`
* `ADMIN`

Do not seed:

* `MANAGER`;
* `SUPPORT`;
* `DELIVERY_AGENT`;
* future roles.

Roles are CLOSED and server-controlled.

Seed only the permissions that were explicitly approved in Phase 3.2.

Examples include operational capabilities such as:

* product management;
* inventory management;
* order viewing/processing;
* request/enquiry management;
* staff approval/management.

Do not use a wildcard `admin.*` permission as the sole authorization mechanism. The project requires explicit capabilities and separation of duties.

Ensure:

* CUSTOMER receives customer capabilities only;
* STAFF receives operational capabilities only;
* ADMIN receives approved administrative capabilities.

In particular, Staff must not receive customer-account administration capabilities.

---

# 5. Category Seed Data

Seed the approved furniture taxonomy from the project domain documentation.

Requirements:

* preserve exact category names/slugs already approved;
* create the required parent-child hierarchy;
* use valid `space_type` values;
* use stable display ordering;
* mark expected public categories active.

The seed must include the approved **Furnitures Root** structure and the existing taxonomy rather than inventing replacement categories.

For recommendations:

* only reference categories that actually exist;
* create only approved category recommendation relationships;
* do not create placeholder categories solely because a future recommendation may need them.

Seed hierarchy deterministically so repeated seeding does not create duplicate logical categories.

---

# 6. Product Seed Data

Create representative catalogue products covering the approved domain.

Include enough variety to test:

* different categories;
* different room types;
* different materials;
* active/inactive products;
* featured/non-featured products;
* products with one variant;
* products with multiple variants;
* products with images;
* products with no image where the schema permits it;
* products suitable for normal in-stock purchasing;
* products representing the approved made-to-order workflow where the product model supports it.

Do not invent unsupported product types or attributes.

Every public product must satisfy the frozen API requirement that `product.price` is non-null.

Because variant pricing is authoritative at the database/domain level, seeded products with variants must have coherent variant pricing and the later API mapping must derive the product representation without creating a second financial source of truth.

---

# 7. Product Variant Seed Data

Create variants with:

* unique SKU;
* valid product relationship;
* realistic integer minor-unit prices;
* valid TZS currency;
* realistic dimensions/weight where applicable;
* structured attributes;
* deterministic display order;
* appropriate active/default flags.

Seed examples with:

* one default variant;
* multiple variants;
* one inactive variant where useful.

Never create two default variants for one product.

Do not place stock quantity directly on the variant.

---

# 8. Product Image Seed Data

Create representative image rows using **synthetic internal file paths**.

Examples of the pattern are acceptable:

```text
products/dev/sofa-01/main.webp
products/dev/dining-table-01/main.webp
```

These are storage keys only.

Do not upload real production images in this phase.

Seed:

* one primary image where appropriate;
* additional ordered images;
* variant-specific images where useful.

Ensure variant-specific images reference a variant belonging to the same product.

Do not generate fake external CDN URLs unless the existing local application explicitly requires URLs for a test.

---

# 9. Inventory Seed Data

Create stock records for selected variants.

Include:

* at least one stock-positive variant;
* at least one zero-stock variant;
* multiple warehouse locations only where useful for testing multi-location behavior;
* realistic reserved quantities where necessary.

Maintain:

`0 <= reserved_quantity <= quantity`

Never seed impossible inventory.

Do not create inventory reservations or movement history because those are later workflow concerns.

---

# 10. Cart Seed Data

Seed representative:

* active customer cart;
* inactive historical customer cart;
* guest cart;
* cart with multiple items.

For guest carts:

* generate a raw guest token using secure randomness;
* store only its approved digest;
* never persist the raw token;
* never log either token or digest.

For tests requiring the raw token, generate it within the test itself rather than placing a bearer credential in static seed files.

Ensure cart ownership XOR remains valid.

Do not seed a cart that belongs simultaneously to a user and guest token.

Do not create more than one ACTIVE cart for the same customer.

---

# 11. Order Seed Data

Seed representative historical orders covering the approved lifecycle.

At minimum include examples of:

### Pickup

```text
PAID
→ ACCEPTED
→ PROCESSING
→ READY_FOR_PICKUP
→ COMPLETED
```

### Delivery

```text
PAID
→ ACCEPTED
→ PROCESSING
→ SHIPPED
→ DELIVERED
→ COMPLETED
```

Also include suitable examples of:

* `PENDING_PAYMENT`;
* `CANCELLED`.

The order lifecycle defined by the project must be preserved; seed data must not introduce undocumented statuses.

Orders must use:

* valid `OD-*****` references;
* coherent subtotal;
* valid delivery fee state;
* valid total;
* historical recipient snapshot;
* historical delivery address where applicable.

Do not calculate order totals from arbitrary frontend-like values.

Seed values should be mathematically coherent.

---

# 12. Order Item Seed Data

Order items must represent historical snapshots.

Store:

* SKU snapshot;
* product name snapshot;
* variant name snapshot where applicable;
* unit price snapshot;
* quantity;
* line total.

Do not depend on current product values to reconstruct historical seeded orders.

Include at least one order whose item has enough snapshot data to remain meaningful if the referenced product is later removed.

This reflects the requirement that historical orders preserve purchased information and price snapshots.

---

# 13. Order Status History Seed Data

Create chronological events matching seeded order status.

Every seeded status timeline must obey:

* valid transition sequence;
* correct `from_status`;
* correct `to_status`;
* appropriate `actor_type`;
* actor association where applicable;
* valid `occurred_at`.

For the first event:

`from_status = null`

where the approved history model requires the initial state event.

Do not seed contradictory timelines.

For example, do not create:

```text
PAID
→ COMPLETED
→ PROCESSING
```

Use deterministic timestamps separated enough to make ordering unambiguous.

---

# 14. Payment Seed Data

Seed payment attempts that represent database states only.

Examples:

* pending;
* processing;
* succeeded;
* failed;
* cancelled;
* expired,

but only for statuses actually approved by the frozen implementation/contract at this point.

Use:

* unique payment references;
* coherent amount/currency;
* synthetic provider names/identifiers;
* nullable provider transaction fields where appropriate.

Do not store real provider credentials.

Do not make seeded payment data look like verified production transactions.

Do not invoke external payment systems.

---

# 15. Payment Webhook Seed Data

Create synthetic webhook event records only when useful for testing persistence/idempotency.

Ensure:

* `(provider, provider_event_id)` is unique;
* payment relation is valid when present;
* processing status is internally coherent;
* timestamps are logical.

Use fake provider event IDs such as:

```text
dev_evt_000001
```

Do not use real gateway webhook payloads or secrets.

---

# 16. Delivery Seed Data

Create delivery rows only for delivery orders.

Ensure:

* one delivery per delivery order;
* no delivery row for pickup orders;
* recipient/address snapshot matches the order scenario;
* synthetic delivery instructions are realistic but harmless.

Do not seed a delivery status, driver, carrier, GPS coordinate, tracking number, or ETA because those are outside the approved domain.

---

# 17. Furniture Request Seed Data

Seed:

* at least one guest request;
* at least one authenticated customer request;
* request with optional product linkage;
* request without product linkage;
* different valid request statuses.

Use realistic synthetic:

* name;
* email;
* phone;
* style;
* message;
* dimensions;
* material;
* color.

Keep all structured JSON within the approved shape.

Do not seed actual customer information.

Do not attach files in this phase.

---

# 18. Enquiry Seed Data

Seed representative:

* guest enquiry;
* authenticated enquiry;
* product-context enquiry;
* order-context enquiry where valid;
* open enquiry;
* closed enquiry.

Respect the approved nullable contact rules.

Do not create duplicate uniqueness assumptions.

Do not expose seeded private enquiries as public catalogue data.

---

# 19. Notification Seed Data

Seed only server-valid notifications using the approved CLOSED notification types:

* `ORDER_RECEIVED`
* `ORDER_ACCEPTED`
* `ORDER_PROCESSING`
* `ORDER_READY_FOR_PICKUP`
* `ORDER_SHIPPED`
* `ORDER_DELIVERED`
* `ORDER_COMPLETED`
* `ORDER_CANCELLED`
* `NEW_ORDER`
* `NEW_MADE_TO_ORDER_REQUEST`
* `NEW_ENQUIRY`

Do not invent `PAYMENT_*` notification types in this phase because those are deferred to Group H.

Seed examples of:

* unread notifications;
* read notifications;
* order target references.

Ensure notification recipients are valid users.

Do not seed anonymous notification recipients.

---

# 20. Seeder Architecture

Prefer:

```text
DatabaseSeeder
    ↓
small domain-specific seeders
```

when the number of records makes separation useful.

For example:

```text
RolePermissionSeeder
CategorySeeder
CatalogSeeder
InventorySeeder
DevelopmentUserSeeder
CommerceDemoSeeder
```

Do not create dozens of tiny seeders with no meaningful ownership.

Keep the dependency order explicit.

For example:

```text
roles/permissions
→ users
→ categories
→ products
→ variants
→ images
→ inventory
→ carts
→ orders
→ order items/history
→ payments/webhooks
→ deliveries
→ requests
→ enquiries
→ notifications
```

---

# 21. Idempotent Seed Strategy

Static reference/domain seed data must be safe to run repeatedly.

Use stable identifiers such as approved slugs/references for lookup/upsert behavior where appropriate.

Do not blindly call:

```php
Model::create(...)
```

for fixed reference data if repeated `db:seed` would generate duplicates.

Factories used for random test data may intentionally create new records.

Clearly separate:

* repeatable reference seeders;
* disposable demo/test data.

---

# 22. Production Safety

Do not make `db:seed` automatically insert demo/test users or fake commerce data into production.

Development/demo seeders must be explicitly invoked.

Do not put:

* payment credentials;
* API secrets;
* real customer passwords;
* real customer addresses;
* real phone numbers;
* real provider transaction data

into committed seed files.

Use environment configuration for any unavoidable development-only secret.

---

# 23. Factory States

Where multiple valid states are important, use named factory states rather than scattered overrides.

Examples:

```text
active()
inactive()
featured()
default()
outOfStock()
guest()
customer()
staff()
admin()
pickup()
delivery()
read()
unread()
```

Only create states that represent real domain distinctions.

Do not create a state for every individual field combination.

---

# 24. Factory Maintainability

Respect project code-quality rules:

* small cohesive factory definitions;
* explicit naming;
* no giant callbacks;
* no duplicate business rules;
* no scattered magic strings;
* centralize reusable CLOSED values where appropriate;
* cognitive complexity ≤15;
* maximum 3 returns per function;
* minimal comments.

Factories must remain test-data builders, not hidden application services.

---

# 25. Tests for Phase 3.18

Add tests proving:

* default factories create valid rows;
* required relationships are created correctly;
* unique constraints are respected;
* category hierarchy is valid;
* product/variant/image relationships are valid;
* inventory invariants hold;
* guest cart ownership is valid;
* customer cart ownership is valid;
* order totals are coherent;
* status-history timelines are coherent;
* delivery rows correspond only to delivery orders;
* notification recipients are valid;
* repeated reference seeding does not create duplicates.

At minimum run the factory/seed suite against a fresh test database.

---

# 26. Files Changed

At the end of the phase explicitly report:

* factory files added/changed;
* seeder files added/changed;
* model changes, if any;
* test files added/changed;
* documentation changes.

Do not change API contract documentation merely because test data was added.

---

# 27. Schema/API Changes

Expected result:

**No schema or API contract changes.**

If seed data exposes a contradiction in the schema or contract, do not hide it in the factory.

Record the contradiction and STOP.

---

# 28. Commands / Checks

Run the project's agreed formatting, static analysis, and test commands.

Also verify a clean database workflow using the repository's Laravel setup.

At minimum verify:

```bash
php artisan migrate:fresh
php artisan db:seed
```

and then repeat the seed where the configured reference seeders are expected to be idempotent.

Run the relevant test suite afterward.

Use the exact project tooling already established in Group B rather than introducing a new test framework.

---

# 29. Expected Result

A fresh development database can be populated with realistic, internally consistent domain data using supported Laravel seed/factory commands.

Repeated execution must not silently corrupt reference data.

Factories can then be reused by Phase Group D onward.

---

# 30. Known Risks

Review specifically for:

* duplicate seed records;
* fake credentials accidentally resembling production credentials;
* invalid cross-table relationships;
* contradictory order timelines;
* invalid inventory states;
* seed-only assumptions that are not actually supported by the domain;
* excessive seed volume slowing tests;
* external integrations accidentally triggered during seeding.

---

# 31. Definition of Done

Phase 3.18 is complete when:

* factories exist for all required testable domain models;
* reference seed data is repeatable;
* seed data respects every reviewed constraint;
* role/permission seed data matches the approved V1 model;
* category taxonomy is correct;
* catalog data exercises product/variant/image relationships;
* inventory data is valid;
* cart data covers guest and customer paths;
* orders cover representative lifecycle states;
* order history is coherent;
* payment and webhook records are safe synthetic data;
* delivery data is valid;
* furniture requests and enquiries cover guest/authenticated cases;
* notifications use only approved notification types;
* no production secrets or real customer data are seeded;
* factory/seed tests pass;
* formatting/static analysis pass;
* clean database population succeeds.

---

# 32. Out of Scope

Do not implement:

* authentication workflows;
* API endpoints;
* payment providers;
* webhook handlers;
* notification delivery;
* order workflow services;
* inventory reservation;
* frontend integration;
* production catalog import;
* production user migration;
* production payment data import.

---

# 33. STOP Condition

STOP after factories, seeders, and their tests are complete and verified.

Do not proceed to Group D.

Phase 3.19 must perform the final migration rebuild verification before Group C can be declared complete.
