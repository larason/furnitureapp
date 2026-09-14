# Phase 3.19 — Migration Test

## Purpose

Perform the final migration/rebuild verification for Phase Group C.

The objective is to prove that the complete Group C database can be created from migration history on an empty database and that the resulting schema:

* contains all intended tables;
* creates relationships in the correct order;
* creates all required indexes and constraints;
* accepts valid seed data;
* rejects invalid relational data;
* preserves the approved domain invariants;
* can be rebuilt repeatably;
* does not depend on undocumented manual database changes.

This phase is the final gate before the Group C exit condition.

`AGENTS.md` explicitly requires migrations to be the authoritative schema history and requires repeatable seeders/factories and appropriate foreign keys/constraints.

---

## Dependencies

Complete:

* Phases 3.1–3.18.

Especially:

* Phase 3.17 constraint/index review;
* Phase 3.18 factories and seed data.

---

# 1. Core Test Principle

The test must answer:

> Can the entire database be destroyed and recreated using the repository's migrations alone, then populated using the approved seeders, without manual intervention?

The answer must be **yes**.

Do not use an already-existing developer database as proof.

The test must begin from a clean database.

---

# 2. Fresh-Database Migration Test

Run the canonical Laravel fresh migration process against a dedicated test/development database.

Use the project's configured database tooling.

The essential sequence is:

```bash
php artisan migrate:fresh
```

followed by:

```bash
php artisan db:seed
```

or the project's equivalent approved seeding command.

Do not run this against production.

---

# 3. Migration Ordering Verification

Verify that migration execution succeeds in dependency order.

Check especially:

```text
users
→ roles/permissions
→ categories
→ products
→ variants
→ images
→ inventory
→ carts
→ cart_items
→ orders
→ order_items
→ order_status_history
→ payments
→ payment_webhook_events
→ deliveries
→ furniture_requests
→ enquiries
→ notifications
```

The actual sequence may differ where framework-generated RBAC tables or FK dependencies require it, but every prerequisite must exist before the dependent FK is created.

Do not disable foreign-key checking simply to force migrations to succeed.

---

# 4. Schema Existence Verification

After a successful fresh migration, verify:

* every intended table exists;
* no unintended Group C table was created;
* all required columns exist;
* required primary keys exist;
* all expected foreign keys exist;
* expected unique constraints exist;
* expected CHECK constraints exist where supported;
* expected indexes exist;
* timestamps have correct presence/nullability;
* soft deletes exist only where approved.

Do not rely only on migration command success.

A migration can complete successfully while a required index or constraint is accidentally missing.

---

# 5. Foreign-Key Verification

Programmatically or through database inspection verify every FK reviewed in Phase 3.17.

For each FK verify:

* referenced table;
* referenced column;
* child column;
* delete action;
* update action where configured.

Pay particular attention to:

* product/category restrictive relationship;
* variant/product cascade;
* image/product cascade;
* image/variant nulling;
* inventory/variant cascade;
* order/customer restrictive protection;
* order item/catalog snapshot nulling;
* history/actor nulling;
* payment/order preservation;
* delivery/order restrictive relationship;
* request/enquiry optional references;
* notification recipient relationship.

No orphan-capable relationship should remain unintentionally.

---

# 6. Unique-Constraint Verification

Attempt deliberate duplicates in the dedicated test database.

Verify rejection of:

* duplicate user email where uniqueness is required;
* duplicate category slug;
* duplicate product slug;
* duplicate SKU;
* duplicate guest cart token digest;
* duplicate order reference;
* duplicate payment reference;
* duplicate webhook provider/event identity;
* duplicate delivery for one order;
* duplicate request reference;
* duplicate enquiry reference.

Also verify that legitimate non-unique records remain insertable.

Do not over-test or enforce uniqueness that the domain does not require.

---

# 7. CHECK-Constraint Verification

For database-supported CHECK constraints, deliberately attempt invalid records.

Examples:

### Inventory

Attempt:

```text
reserved_quantity > quantity
```

### Quantities

Attempt negative quantity.

### Money

Attempt negative values where prohibited.

### Cart

Attempt both:

```text
user_id != null
guest_token_digest != null
```

and neither ownership field populated.

### Categories

Attempt self-parenting if the schema has a DB-level prevention mechanism.

### Order

Attempt impossible fee/total combinations where database-level checks are implemented.

The test must confirm that database constraints are active, not merely present in source code.

Where an invariant is intentionally application-only, do not falsely classify the database test as covering it.

---

# 8. Referential-Integrity Tests

Verify:

### Catalogue

Deleting a category with products is rejected.

Deleting a product:

* cascades approved variant/image records;
* preserves historical order information as designed.

Deleting a variant:

* removes stock rows where approved;
* nulls image variant references where approved;
* preserves historical order snapshots.

### Users

Deleting a user must behave according to the approved retention policy and must not silently destroy historical commerce records.

### Orders

Deleting an order must cascade only the approved dependent records.

Payment and operational-history retention behavior must match Phase 3.17.

---

# 9. Seed-Rebuild Test

Perform this complete sequence against a fresh dedicated database:

```text
destroy existing test database
→ migrate fresh
→ seed
→ run integrity assertions
→ run application tests
```

Repeat the fresh rebuild at least once more.

The second run should prove that the process does not rely on undocumented state left behind by the first run.

---

# 10. Factory Compatibility Test

After migration and seeding:

* create factory-generated users;
* create factory-generated categories/products;
* create variants;
* create inventory;
* create carts;
* create orders;
* create order items;
* create status history;
* create payments;
* create requests/enquiries;
* create notifications.

Verify that factories work against the actual migrated schema rather than an assumed schema.

---

# 11. Seed Idempotency Test

For fixed reference seed data, verify the configured seeding process does not create duplicate reference rows when rerun.

Do not require every random factory-generated demo record to be globally idempotent.

The distinction is:

```text
Reference seed data → repeatable/idempotent
Factory-generated demo data → intentionally creatable
```

Document this distinction in the seeder structure if it is not already obvious.

---

# 12. Migration Rollback Review

Review rollback behavior for the Group C migration history.

Where the project expects rollback support, verify:

```bash
php artisan migrate:rollback
```

works for the applicable migration batch.

Then rebuild using:

```bash
php artisan migrate
```

Do not claim that rollback testing is complete if the project intentionally has irreversible/destructive migration steps.

Document any irreversible migration explicitly.

The objective is controlled schema evolution, not pretending every future production migration will be safely reversible.

---

# 13. Migration History Integrity

Verify that:

* migration filenames are ordered correctly;
* each migration has one clear responsibility;
* no migration depends on manually pre-created tables;
* migrations do not silently modify unrelated domains;
* applied migration history matches the actual repository files;
* no migration has hard-coded local machine paths;
* no production data is embedded in migrations;
* seeds are not being used as schema creation mechanisms.

Do not edit an already-applied production migration to solve a newly discovered schema problem; create a follow-up migration instead, as required by `AGENTS.md`.

---

# 14. Database Engine/Environment Consistency

Run the test against the MySQL version/environment intended for Laravel development.

Verify compatibility of:

* foreign keys;
* CHECK constraints;
* JSON columns;
* indexes;
* unsigned numeric types;
* collations;
* timestamp behavior.

Do not rely solely on SQLite behavior to declare the MySQL schema correct if MySQL is the authoritative production database.

SQLite may still be used for fast unit tests where appropriate, but this phase must include the real MySQL schema rebuild validation.

---

# 15. Constraint Regression Tests

Create a small dedicated schema-integrity test suite covering the most important invariants.

The suite should prevent future changes from silently removing:

* unique SKU protection;
* order reference uniqueness;
* webhook idempotency uniqueness;
* cart ownership XOR;
* inventory reservation bounds;
* historical order retention relationships;
* delivery uniqueness;
* product/variant referential integrity.

These tests become regression protection for later phases.

---

# 16. API Compatibility Check

Confirm that migration/schema implementation has not changed externally observable V1 behavior.

Especially verify:

* nullable vs required response concepts remain unchanged;
* CLOSED enum values remain unchanged;
* money representation remains integer minor units;
* no new API-visible status was introduced;
* product price remains compatible with the frozen contract;
* order/customer relationships remain consistent with ownership rules.

The V1 API contract is frozen; internal database improvements are allowed only when external behavior remains unchanged.

---

# 17. Security Verification

Verify that migrations and seeders do not:

* expose real passwords;
* create production-like credentials accidentally;
* store API keys in database seed data;
* insert payment secrets;
* create unauthorized admin users in production paths;
* bypass FK constraints;
* disable database integrity checks permanently.

Development admin/test accounts must never be automatically created by a production deployment path unless explicitly designed and secured.

---

# 18. Performance Sanity Check

After seeding, verify that representative queries can execute using the intended indexes.

At minimum inspect:

* product by slug;
* products by category;
* variants by product;
* images by product;
* inventory by variant;
* active cart by user;
* customer orders by creation time;
* order history by order/time;
* payment by order/reference;
* delivery by order;
* customer requests;
* customer enquiries;
* notifications by recipient/time.

Do not run a full production load test here.

The purpose is to catch obvious missing/incorrect indexes before moving forward.

---

# 19. Automated Test Command Set

Run the project-established checks from Group B.

At minimum:

```bash
php artisan migrate:fresh
php artisan db:seed
php artisan test
```

Plus the project's formatting/static-analysis commands.

Run the MySQL-backed integration tests explicitly if the repository distinguishes them from faster unit tests.

Record the exact commands used in the phase completion report.

---

# 20. Files Changed

At the end explicitly report:

* migration files changed/added;
* factory files changed;
* seeders changed;
* test files added/changed;
* documentation changed.

If the correct result is that no migration changes were required after Phase 3.17, state that explicitly.

---

# 21. Schema/API Changes

Expected outcome:

**No new API changes.**

Schema changes should occur only if the migration test uncovers a genuine defect in Phases 3.1–3.17.

Any correction must:

* be minimal;
* preserve the frozen V1 contract;
* use a new migration where prior migrations may already have been applied;
* include a regression test.

Do not redesign the domain during the migration test.

---

# 22. Expected Result

A completely fresh MySQL database can be created from migration history, populated from approved seeders, and exercised by the relevant tests without manual SQL intervention.

The database must end in a state that accurately represents the agreed Group C domain.

---

# 23. Known Risks

Record any:

* MySQL-version-specific behavior;
* FK cascade issue;
* CHECK-constraint limitation;
* index mismatch;
* migration-order dependency;
* seed-order dependency;
* non-reversible migration;
* test-only difference between SQLite and MySQL;
* performance issue discovered during verification.

Do not hide environment-specific limitations.

---

# 24. Final Group C Exit Review

Before declaring Group C complete, verify all of the following:

```text
API contract
    ↓
domain model
    ↓
Laravel migrations
    ↓
foreign keys
    ↓
constraints
    ↓
indexes
    ↓
factories
    ↓
seed data
    ↓
fresh-database rebuild
    ↓
integration tests
```

Every layer must agree.

The final Group C exit condition is:

> Database schema accurately represents the agreed domain and can be rebuilt from migrations.

---

# 25. Definition of Done

Phase 3.19 is complete when:

* fresh MySQL database creation succeeds;
* all migrations execute in dependency order;
* all intended tables exist;
* all intended columns exist;
* all foreign keys exist and use correct actions;
* all important unique constraints work;
* all implemented CHECK constraints work;
* critical application-level invariants have regression coverage;
* indexes exist as approved in Phase 3.17;
* factories work on the migrated schema;
* seeders populate the database successfully;
* reference seed data remains repeatable;
* migration/rebuild process succeeds from clean state;
* relevant MySQL-backed tests pass;
* formatting/static analysis pass;
* no critical security issue remains;
* no frozen V1 API behavior was changed;
* Group C exit condition is demonstrably true.

---

# 26. Out of Scope

Do not begin:

* authentication implementation;
* Laravel API controllers;
* policies/gates;
* catalog API;
* cart API;
* checkout;
* payment integration;
* inventory workflow;
* notification delivery;
* Next.js UI;
* Flutter UI;
* production deployment.

Those belong to later roadmap groups.

---

# 27. STOP Condition

After the fresh migration/rebuild test passes and the Group C exit condition is demonstrated, STOP.

Do not proceed into Phase Group D implementation in the same task.

The next implementation request should explicitly begin:

**Phase Group D — Authentication and Authorization**
