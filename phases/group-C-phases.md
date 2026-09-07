# Group C phases instructions

# Phase 3.1 — Users Schema

## Objective

Implement the database schema and Laravel model foundation for the project's unified user identity.

The architecture for this phase is:

```text
                         ┌────────────────────┐
                         │       users        │
                         │────────────────────│
                         │ id                 │
                         │ name               │
                         │ email              │
                         │ phone              │
                         │ password           │
                         │ account state      │
                         │ timestamps         │
                         └─────────┬──────────┘
                                   │
                       ┌───────────┴───────────┐
                       │                       │
                    1 : 1                   1 : 1
                       │                       │
             ┌─────────▼──────────┐  ┌────────▼──────────┐
             │ customer_profiles  │  │  staff_profiles   │
             │────────────────────│  │───────────────────│
             │ user_id            │  │ user_id           │
             │ customer-specific  │  │ staff-specific    │
             │ data only          │  │ data only         │
             └────────────────────┘  └───────────────────┘
```

Use one authentication identity for all authenticated people.

Do not create separate authentication tables such as:

```text
customers
staff
admins
```

The same Laravel `User` identity must be capable of representing a customer, staff member, or admin.

The project's conventions explicitly define one shared `User` identity across Next.js, Flutter, and administrative access.

---

# 1. Phase Context

Phase Group B is complete.

Now begin:

**Phase Group C — Database and Domain Model**

Current phase:

**Phase 3.1 — Users schema**

Next phase:

**Phase 3.2 — Roles/permissions model**

Do not implement Phase 3.2 in this phase.

The Group C roadmap explicitly separates Users schema from Roles/permissions model.

---

# 2. Authoritative Sources

Before changing the repository, read:

1. `AGENTS.md`
2. `docs/api/api-contract.md`
3. `docs/api/api-resources.md`
4. `docs/api/api-conventions.md`
5. `docs/domain/business-rules.md`
6. the existing Laravel schema/model structure from Group B

Treat the frozen API contract as authoritative.

Do not silently introduce fields that have no business or contract requirement.

---

# 3. Architecture Decision

Use:

```text
users
customer_profiles
staff_profiles
```

as the database identity/profile structure.

Authentication identity belongs to `users`.

Specialized profile attributes belong in the appropriate profile table.

Roles and permissions are **not** represented by separate authentication tables.

Role and permission assignment will be implemented in Phase 3.2.

Authentication workflows will be implemented later in Group D.

---

# 4. `users` Table Responsibility

The `users` table is the central identity/authentication table.

It should contain only cross-role identity/authentication state.

Required conceptual fields are:

```text
id
name
email
phone
password
account state
timestamps
```

Use the actual naming required by the existing project/API conventions.

The schema must support the project's requirement that:

* email identifies the authentication account
* password is stored only as a secure one-way hash
* phone may be nullable according to the existing contract
* account state is server-controlled
* timestamps are server-controlled

The authentication conventions define registration data as `name`, `email`, `phone`, and `password`, with password storage as a secure one-way hash.

---

# 5. Do Not Add Role Column

Do **not** add:

```text
role
```

to `users` as the authoritative RBAC implementation.

Do not add:

```text
is_admin
is_staff
is_customer
```

either.

The V1 role system is server-controlled and belongs to the authorization/RBAC model.

Phase 3.2 will establish how roles and permissions are persisted.

The frozen conventions explicitly distinguish roles from ordinary profile fields and state that the role is server-controlled.

---

# 6. V1 Role Vocabulary

The schema must be compatible with the frozen V1 role vocabulary:

```text
CUSTOMER
STAFF
ADMIN
```

These are closed roles.

Do not introduce:

```text
WAREHOUSE_STAFF
CATALOG_MANAGER
SUPPORT
MANAGER
SUPER_ADMIN
DELIVERY_AGENT
```

as V1 roles.

More granular staff capabilities are represented through permissions rather than creating a role explosion.

The project explicitly defines `CUSTOMER`, `STAFF`, and `ADMIN` as the V1 closed roles and discourages additional roles.

---

# 7. `users.email`

`email` is a required identity attribute.

The current conventions state that `email` is required and not nullable.

Implement appropriate database uniqueness.

The uniqueness requirement must support case-consistent authentication behavior.

Do not solve email normalization entirely through a database constraint.

Do not invent a separate email-identity table.

The exact application-level normalization behavior belongs to later authentication implementation.

---

# 8. `users.phone`

`phone` may be nullable according to the current contract.

Do not make it required merely because the proposed SQL example used a nullable field differently.

Do not add unnecessary phone-specific tables.

Do not introduce SMS/OTP functionality.

Phone/SMS authentication is not required by default and is outside this phase.

---

# 9. `users.password`

Create the database field needed to store the user's password credential securely.

The field must be suitable for a one-way password hash.

Do not store plaintext passwords.

Do not create:

```text
password_plaintext
password_confirmation
```

Do not create reset tokens or verification secrets in the `users` table as part of this phase.

Password reset and verification are later authentication workflows.

The authentication conventions explicitly require secure one-way password hashing and prohibit returning credential material through the API.

---

# 10. Account State

The user identity must support server-controlled account state.

Do not allow client profile operations to mutate account state.

The API conventions explicitly identify `account_state` as server-controlled.

### Important implementation constraint

Do not invent a new set of account-state enum values unless the authoritative domain/API documentation already defines them.

The repository mentions lifecycle concepts such as:

```text
PENDING
ACTIVE
```

for staff approval, but exact lifecycle semantics are deferred.

Therefore:

* inspect existing contract/domain definitions first
* if an authoritative account-state definition already exists, implement it exactly
* otherwise use the minimum schema representation necessary to preserve the deferred lifecycle without pretending to finalize the enum

Do not hard-code an unapproved V1 account-state vocabulary.

---

# 11. `customer_profiles`

Create a one-to-one customer profile extension table only for customer-specific information that is actually supported by the current project.

At minimum the relationship should be:

```text
customer_profiles.user_id → users.id
```

with one customer profile per user.

Enforce the one-to-one relationship with a unique constraint on `user_id`.

---

# 12. Customer Profile Fields

Do **not** copy the proposed fields:

```text
default_shipping_address_id
loyalty_points
preferences
```

into Phase 3.1 unless those fields are already explicitly established in the authoritative project requirements.

Reason:

* saved address book functionality is not currently part of the users schema phase
* loyalty points are not established as a V1 domain requirement
* arbitrary preference JSON has no current contract requirement

The frozen profile conventions currently identify `name` and `phone` as the ordinary customer-profile fields, with identity/security fields handled separately.

Do not invent customer data merely to make the profile extension table look complete.

---

# 13. Profile Responsibility Clarification

The database design may contain `name` and `phone` in either the central identity layer or customer-profile layer depending on the final model chosen during implementation, but the implementation must preserve the API semantics:

```text
name
phone
```

are ordinary profile data.

The following remain authentication/security concerns:

```text
email
password
role
permissions
account_state
verification state
```

The conventions explicitly require this separation of responsibility even where data is ultimately stored on related user records.

Do not duplicate the same authoritative `name` or `phone` values in both `users` and `customer_profiles`.

Choose one authoritative storage location.

For this project, because `name`, `email`, and `phone` are part of the shared registration identity and `User` is the common cross-platform identity, prefer keeping `name`, `email`, and `phone` on `users` unless the existing repository already establishes a different model.

`customer_profiles` should therefore contain only genuinely customer-specific attributes introduced by later requirements.

If no such attributes are currently required, keep the table intentionally minimal rather than inventing fields.

---

# 14. `staff_profiles`

Create a one-to-one staff profile extension table for staff/admin-specific information that is genuinely required by the project.

At minimum:

```text
staff_profiles.user_id → users.id
```

with a unique constraint on `user_id`.

This permits:

```text
User
 ├── customer profile
 └── staff profile
```

to remain separate from authentication.

---

# 15. Staff Profile Fields

Do **not** automatically add:

```text
employee_id
department
```

from the proposed example unless these fields are supported by the project's actual requirements.

The current frozen business/API sources define Staff primarily by operational capability and permissions, not by employee-code or department attributes.

Avoid speculative schema.

If an internal staff attribute is not needed by current requirements, defer it.

---

# 16. Admin Profile

Do **not** create an `admin_profiles` table.

Admins use the same `staff_profiles` extension model if administrative-specific profile data is required.

The architectural model is:

```text
users
 ├── CUSTOMER → customer profile
 └── STAFF/ADMIN → staff profile
```

Role membership determines which capability/profile interpretation applies later.

Do not create separate authentication identities for Admin.

---

# 17. RBAC Relationship

Phase 3.1 must be designed to work with a later RBAC implementation.

The relationship should eventually resemble:

```text
users
   │
   └── RBAC roles/permissions
```

and independently:

```text
users
   ├── customer_profiles
   └── staff_profiles
```

Do not store permissions directly in profile JSON.

Do not create ad hoc:

```text
permissions JSON
roles JSON
is_admin boolean
```

inside `users`.

Phase 3.2 will establish the authoritative role/permission schema.

---

# 18. RBAC Package Boundary

The user has specified an RBAC package such as `spatie/laravel-permission`.

For this roadmap:

* **do not implement the package integration in Phase 3.1**
* **do not create its role/permission tables manually**
* **do not assign roles in Phase 3.1**
* **do not add package-specific traits to `User` unless the project already uses the package**

Phase 3.2 is explicitly named:

**Roles/permissions model**

Use that phase to evaluate and implement the RBAC package/model consistently.

This preserves the dependency order defined by the roadmap.

---

# 19. Important Staff/Customer Flexibility

The unified `users` architecture must not make a user permanently incapable of belonging to another operational role simply because a profile row exists.

The project's motivation for a unified identity is valid:

```text
one User identity
+
role/permission model
+
optional profile extension
```

However, do not implement role switching or multi-role assignment in Phase 3.1.

That behavior is a Phase 3.2/Group D design concern.

The schema should simply avoid architectural assumptions that make future role changes impossible.

---

# 20. One User, One Authentication Identity

There must be exactly one authentication identity per account.

Do not create:

```text
customer_users
staff_users
admin_users
```

Do not create multiple password fields.

Do not create separate authentication guards in the database schema.

The project explicitly requires a shared identity across website and Flutter clients.

---

# 21. Foreign Keys

Profile tables must reference `users.id`.

Use an actual foreign-key constraint.

The relationship must not be represented only by a convention such as:

```text
customer_profiles.user_id
```

with no database enforcement.

Use the project's existing migration style.

---

# 22. Delete Behavior

Decide delete behavior deliberately.

The current project does not support destructive deletion of users in normal workflows, especially where historical orders/payments/requests/enquiries may exist.

The authentication conventions explicitly caution against hard-deleting customers with historical records.

Therefore:

* do not build a user-deletion workflow
* do not add cascade deletion from `users` to future business history
* choose foreign-key behavior that will not unexpectedly destroy historical domain data

For profile extension rows that are purely dependent on `users`, cascading profile deletion can be considered only if it does not conflict with future retention/audit requirements.

Do not implement destructive account workflows in this phase.

---

# 23. Timestamps

Use the project's standard timestamp conventions.

User/profile rows should use:

```text
created_at
updated_at
```

where appropriate.

Do not add arbitrary audit timestamps such as:

```text
last_login_at
approved_at
suspended_at
email_verified_at
password_changed_at
```

unless the authoritative requirements already establish them for this schema.

Those attributes may be needed later, but this phase must not guess.

---

# 24. Remember Token

If the current Laravel authentication model requires a `remember_token` column for its planned web authentication mechanism, preserve the standard framework-compatible field.

Do not treat the remember token as an API credential.

Do not expose it through the API.

Do not log it.

Do not create additional token columns as part of Phase 3.1.

---

# 25. API Serialization

Do not create API resources or endpoints in Phase 3.1.

However, the model design must remain compatible with the frozen serialization requirements.

Never serialize:

```text
password
password_hash
remember_token
session credentials
RBAC internals
security secrets
```

The project's serialization rules require explicit allow-lists and identify credentials/tokens as internal-only.

---

# 26. No Authentication Implementation

Do not implement:

* registration
* login
* logout
* password recovery
* email verification
* Sanctum
* Passport
* API tokens
* browser authentication
* session handling
* MFA

Those belong to Group D.

The current source explicitly defers Laravel/Sanctum, user authentication implementation, hashing workflows, reset flows, and token/session handling to the authentication group.

Phase 3.1 only creates the database/model prerequisites.

---

# 27. Model Implementation

Create the Eloquent `User` model foundation required for the schema.

Keep the model small.

It may contain:

* correct table association if needed
* guarded/fillable configuration
* casts appropriate to actual persisted fields
* relationships to profile models
* authentication compatibility required by the current Laravel version

Do not place:

* authentication workflows
* authorization rules
* business rules
* password-reset logic
* role assignment logic
* profile mutation workflows

inside the model.

---

# 28. Mass Assignment

Continue the established project rule:

```text
validated input
→ explicit allow-list
→ DTO/Command
→ domain/application logic
→ persistence
```

Never introduce:

```php
$user->fill($request->all());
```

or:

```php
$user->update($request->all());
```

The project explicitly requires validated input → DTO/command and prohibits unrestricted request-to-model assignment.

---

# 29. Password Mass Assignment

Treat passwords specially.

Even though the users table contains the password credential:

* do not permit generic profile updates to write passwords
* do not add password to ordinary profile allow-lists
* do not expose password in serialization
* do not create generic user-update helpers that accept arbitrary fields

The authentication workflow will later own password changes.

---

# 30. Role Mass Assignment

Likewise, do not make role assignment a model fillable field.

This must remain impossible through generic input.

The frozen contract explicitly prohibits client modification of:

```text
role
permissions
account_state
verification
```

through normal profile operations.

---

# 31. Profile Relationships

Establish clear Eloquent relationships:

```text id="qz6v2t"
User
 ├── customerProfile
 └── staffProfile
```

Use one-to-one relationships.

Do not automatically assume that every user has both profiles.

Profile existence is determined by later role/application behavior.

Do not create both profile rows automatically for every user.

---

# 32. Do Not Use Eloquent Events for Profile Creation Yet

Do not implement the earlier proposed behavior:

```text
User created
→ automatically create customer profile
```

unless the actual application design later establishes this as necessary.

The problem is that role assignment itself belongs to Phase 3.2 and customer registration belongs to Group D.

Creating role-dependent profiles before the role model and lifecycle exist can create invalid states.

In Phase 3.1, establish the schema relationships only.

Profile creation policy should be implemented together with the role/authentication workflow once those dependencies exist.

---

# 33. Schema for Future Role Changes

The schema must allow a user to later transition through supported role-management workflows without duplicating authentication records.

Avoid database rules such as:

```text
user_id may exist only in customer_profiles forever
```

or:

```text
every users row must have exactly one profile
```

unless such constraints are actually required by the final authorization design.

The authentication identity remains stable while profile/role capabilities can evolve.

---

# 34. Unique Profile Ownership

Each profile extension must contain:

```text
UNIQUE(user_id)
```

so a user cannot accidentally have two customer profiles or two staff profiles.

Do not rely solely on application code for this one-to-one relationship.

---

# 35. Indexing

Create indexes needed for actual constraints/query patterns.

At minimum:

* unique `users.email`
* appropriate phone uniqueness/index behavior according to the contract
* unique `customer_profiles.user_id`
* unique `staff_profiles.user_id`

Do not create dozens of speculative indexes.

Phase 3.17 will conduct the broader foreign-key/index/constraint review.

---

# 36. Migration Order

Ensure migrations execute in dependency order:

```text
users
    ↓
customer_profiles
    ↓
staff_profiles
```

Profile migrations must not execute before `users`.

Do not create role/permission migrations here.

Do not create future address tables here.

---

# 37. Migration Rollback

Every migration added in Phase 3.1 must have a correct rollback.

The rollback must:

* reverse constraints safely
* remove tables in dependency order
* not rely on manually deleting rows
* not require future domain tables

Test both migration and rollback behavior as far as the current test setup permits.

---

# 38. Existing Database Awareness

Inspect the current database state before adding migrations.

If a `users` table already exists from Laravel initialization:

* do not blindly create a second `users` table
* determine whether the existing migration should be adapted
* preserve any framework-required structure that remains useful
* make the smallest coherent change

Similarly, if profile tables already exist, extend/reconcile rather than duplicating them.

Do not leave duplicate migrations that attempt to create the same table.

---

# 39. Schema Naming

Use the project's existing naming conventions:

```text
users
customer_profiles
staff_profiles
```

Use:

```text
user_id
created_at
updated_at
```

and standard Laravel/MySQL naming conventions.

Do not mix:

```text
userID
customerId
employeeID
```

with project `snake_case`.

---

# 40. MySQL Compatibility

Implement the schema using features supported by the project's actual MySQL version.

Do not assume unsupported database features.

Check current Laravel/MySQL configuration before choosing:

* string lengths
* JSON behavior
* index lengths
* generated columns
* check constraints
* collation

Do not introduce advanced MySQL-specific features without a real requirement.

---

# 41. Charset and Collation

Preserve the project's existing database charset/collation configuration.

Do not introduce table-specific charset/collation differences unless required.

Email uniqueness and text comparisons should follow the project's consistent database identity behavior.

Do not solve application identity normalization using arbitrary table-level collation changes.

---

# 42. Sensitive Data

Treat the users table as highly sensitive.

Do not:

* log password fields
* expose password hashes
* seed real customer data
* place credentials in migration files
* place real emails/passwords in committed fixtures
* serialize authentication fields by default

The project security baseline requires secret management and explicitly prohibits sensitive credential exposure.

---

# 43. Testing

Add schema/model-focused tests appropriate to the phase.

At minimum verify:

### Users table

* users table can be created
* required identity fields exist
* email uniqueness is enforced
* nullable phone behavior matches the contract
* password field exists and can hold the intended hash representation
* timestamps exist where intended

### Profile relationships

* customer profile references users
* staff profile references users
* each profile relationship is one-to-one
* duplicate profile rows for the same user are rejected

### Referential integrity

* profile cannot reference a nonexistent user
* migration order is correct
* rollback succeeds

### Security structure

Verify that model/API serialization does not expose:

```text
password
remember_token
future credential fields
```

where current model serialization tests exist.

---

# 44. Test the Schema, Not Future Authentication

Do not write tests that require:

* Sanctum
* login
* registration
* password recovery
* role assignment
* authorization policies

Those dependencies do not exist yet.

The Phase 3.1 tests should validate the database/model foundation only.

---

# 45. Factories

Do not create the final `UserFactory` unless the existing test framework and current tests genuinely require one.

If the repository already has Laravel's default `UserFactory`, adapt it only as necessary to keep current tests working.

Do not add:

```text
CustomerFactory
StaffFactory
AdminFactory
```

in this phase unless a real current test requires them.

Those should be introduced when the corresponding domain/authentication behavior exists.

---

# 46. Seeders

Do not create production-like customer/staff/admin seed data.

Do not assign roles before Phase 3.2.

Do not seed passwords intended for production.

If an existing Laravel development seeder requires a user, keep it minimal and clearly non-production.

Do not create a complete RBAC seed system in Phase 3.1.

---

# 47. No Address Book

Do not create:

```text
addresses
customer_addresses
shipping_addresses
default_shipping_address_id
```

in Phase 3.1.

Saved addresses are deferred.

Checkout's delivery address is a separate later domain concern and is eventually handled by the checkout/order architecture.

---

# 48. No Loyalty System

Do not create:

```text
loyalty_points
loyalty_tiers
rewards
customer_rewards
```

There is no current requirement establishing these.

Avoid speculative commerce features.

---

# 49. No Preferences JSON

Do not add:

```text
preferences JSON
```

merely to hold hypothetical UI preferences such as room style or 3D-view preferences.

Those preferences are not part of the frozen V1 user/profile contract.

If product UX later requires persisted preferences, introduce them through a separately justified phase.

---

# 50. No Employee Metadata Without Requirement

Do not create:

```text
employee_id
department
job_title
manager_id
```

unless the authoritative project requirements establish them.

Staff permissions determine operational capability; arbitrary organizational metadata is not a reason to expand this schema.

---

# 51. API Contract Compatibility

Do not add or alter API endpoints.

This phase is database/model work.

Do not implement:

```text
GET /api/v1/me
PATCH /api/v1/me
GET /api/v1/users/{id}
```

even though the schema will eventually support them.

Those endpoints already have frozen semantics and will be implemented in later phases.

The current contract defines `/me` as authenticated-principal context and explicitly prohibits user-ID substitution.

---

# 52. Authorization Compatibility

Do not implement authorization policies.

However, preserve the data model necessary for:

```text
customer-owned account
staff operational identity
admin identity
```

without granting any database-level implied authority.

Role and permission enforcement are later concerns.

---

# 53. Authentication Compatibility

Do not implement authentication.

The schema should simply make later authentication possible.

In particular:

* one `User` model
* one email identity
* one password credential field
* one account-state location
* no duplicated authentication tables

The actual Sanctum/session/token model comes later.

---

# 54. Code Comments

Keep comments in migrations and models to the absolute minimum.

Do not add comments that merely restate column names.

Avoid:

```php
// Create users table
Schema::create('users', function (...) {
```

Prefer self-explanatory code.

A comment is justified only for a non-obvious schema/security/compatibility decision.

Do not place the entire architecture explanation inside migration comments.

---

# 55. Documentation

Do not create a new large users-schema document.

If necessary, update the existing project architecture/decision documentation with the finalized architectural decision:

```text
single users identity
+
customer_profiles
+
staff_profiles
+
RBAC introduced separately
```

Keep the note short.

Do not document unsupported fields as though they are implemented.

---

# 56. Verification

Run the established project checks.

At minimum:

```bash
php artisan migrate
php artisan migrate:rollback
php artisan test
```

Use a safe test/local database.

Then rebuild the schema from migrations:

```text
fresh migration sequence
→ users
→ customer_profiles
→ staff_profiles
```

Confirm the schema can be created without manual intervention.

Do not run destructive migration commands against a production database.

---

# 57. Model Verification

Verify the Eloquent relationship behavior manually or through tests:

```text
User
  → customerProfile
User
  → staffProfile
```

Confirm:

* no duplicate profile rows can exist
* profile foreign keys are enforced
* profile relationships do not require both profile rows

---

# 58. Security Review

Before completion, explicitly inspect for:

```text id="2b4d4c"
password exposure
remember-token exposure
duplicate authentication tables
role stored as insecure boolean
permission JSON
arbitrary role mutation path
missing foreign keys
missing unique user/profile relationship
cascade deletion risks
real credentials in seeders
real customer data
```

The project treats security as mandatory from the first backend implementation.

---

# 59. Definition of Done

Phase 3.1 is complete only when:

1. A single `users` table is the authoritative identity/authentication table.
2. No separate `customers`, `staff`, or `admins` authentication tables exist.
3. The `User` model maps cleanly to the central user identity.
4. `users.email` is correctly constrained as the required identity field.
5. `users.phone` follows the documented nullability.
6. The password field is suitable for secure hashing and is never treated as ordinary profile data.
7. Account state is server-controlled and ready for later lifecycle implementation.
8. `customer_profiles` exists as a one-to-one extension only where justified.
9. `staff_profiles` exists as a one-to-one extension only where justified.
10. Profile foreign keys and unique constraints are enforced by MySQL.
11. No unsupported loyalty/address/preferences/employee metadata was invented.
12. No role/permission implementation was added to this phase.
13. The schema is ready for Phase 3.2 RBAC implementation.
14. Authentication workflows remain deferred to Group D.
15. Migration and rollback work correctly.
16. Focused schema/model tests pass.
17. Existing tests remain passing.
18. No API contract was changed.
19. New code contains only the minimum necessary comments.
20. The resulting schema can be rebuilt from migrations.

---

# 60. Review Checklist

Before completion:

* [ ] one central `users` authentication identity exists
* [ ] no `customers` authentication table
* [ ] no `staff` authentication table
* [ ] no `admins` authentication table
* [ ] no `role` boolean/string shortcut in `users`
* [ ] no `is_admin`/`is_staff` shortcut
* [ ] email is required and uniquely constrained
* [ ] phone follows the documented nullable behavior
* [ ] password storage field exists and is not exposed
* [ ] authentication secrets are not stored in profile tables
* [ ] account-state storage does not invent unsupported enum values
* [ ] customer profile relationship is one-to-one
* [ ] staff profile relationship is one-to-one
* [ ] `customer_profiles.user_id` is unique
* [ ] `staff_profiles.user_id` is unique
* [ ] profile foreign keys reference `users.id`
* [ ] migrations execute in dependency order
* [ ] rollbacks work
* [ ] no address-book schema was added
* [ ] no loyalty schema was added
* [ ] no arbitrary preferences JSON was added
* [ ] no speculative employee metadata was added
* [ ] no RBAC package integration was implemented prematurely
* [ ] no role assignments were introduced
* [ ] no authentication workflows were introduced
* [ ] no API endpoints were added/changed
* [ ] no `$request->all()` mass-assignment path was introduced
* [ ] schema/model tests pass
* [ ] existing tests pass
* [ ] static analysis still passes
* [ ] formatting still passes
* [ ] no secrets or real customer data were added
* [ ] comments are minimal

---

# 61. Explicitly Out of Scope

Do **not** implement during Phase 3.1:

* RBAC roles
* RBAC permissions
* `spatie/laravel-permission` integration
* role/permission pivot tables
* role assignment
* permission assignment
* customer registration
* login
* logout
* Sanctum
* Passport
* browser sessions
* API tokens
* password reset
* email verification
* MFA
* authentication middleware
* authorization middleware
* policies
* gates
* customer profile API
* `/me` API implementation
* `/users/{id}` API implementation
* user administration API
* staff approval workflow
* staff suspension/reactivation workflow
* customer account disabling
* customer impersonation
* address book
* saved shipping addresses
* loyalty points
* loyalty tiers
* customer preferences
* employee hierarchy
* employee department management
* audit-log infrastructure
* notification infrastructure
* product domain
* category domain
* inventory domain
* cart domain
* order domain
* payment domain
* delivery domain
* furniture-request domain
* enquiry domain

---

# 62. STOP Condition

Stop immediately when the Phase 3.1 definition of done is satisfied.

Do not continue into Phase 3.2.

Do not install or configure the RBAC package yet.

Do not assign roles.

Do not implement authentication.

Do not implement registration/login.

Do not add speculative profile fields.

Do not create address, loyalty, or preference tables.

Do not create customer/staff/admin authentication tables.

Do not change the frozen Version 1 API contract.

Do not commit, stage, or push changes. Leave source-control operations to the project owner.
