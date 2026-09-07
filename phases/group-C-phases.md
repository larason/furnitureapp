# Group C phases instructions

# Phase 3.2 — Roles/permissions model

## Purpose

Implement the database and application foundation for **RBAC (role-based access control)** using the single `users` identity created in Phase 3.1.

This phase establishes:

* the V1 role model;
* the explicit permission vocabulary;
* role-to-permission assignments;
* user-to-role assignment;
* deny-by-default authorization primitives;
* the Laravel relationships and authorization abstractions that later phases can use.

This phase must **not** implement authentication workflows, login/session/token handling, customer account administration, staff administration endpoints, or domain-specific operational authorization. Those belong to later phases.

The resulting model must support the authoritative rule:

> `authenticated identity + role + resource + action + ownership/context + business-state` determine authorization; role alone never authorizes.

---

## Dependencies

Required before starting:

* Phase 2.1–2.12 backend foundation is complete.
* Phase 3.1 Users Schema is complete.
* `users`, `customer_profiles`, and `staff_profiles` exist and their migrations/tests pass.
* Existing Laravel coding standards, static analysis, test framework, exception handling, and logging conventions are already established.
* `docs/VISION.md`
* `docs/api/api-contract.md`
* `docs/api/api-resources.md`
* `docs/api/api-conventions.md`
* `docs/domain/business-rules.md`
* `AGENTS.md`

Do not reopen decisions already frozen by those documents.

---

## Authoritative constraints

### 1. Roles are CLOSED

V1 has exactly these user roles:

```text
CUSTOMER
STAFF
ADMIN
```

Use the exact uppercase values.

Do not introduce:

```text
SUPER_ADMIN
MANAGER
SUPPORT
ORDER_STAFF
INVENTORY_STAFF
MODERATOR
GUEST
SYSTEM
```

or other additional user roles.

`SYSTEM` may exist conceptually as an internal execution actor for jobs/webhooks later, but it is **not** a user RBAC role.

Adding another user role is a V1 compatibility decision and must not happen casually.

### 2. Role is server-controlled

A client must never be able to:

* choose its own role;
* promote itself;
* demote itself;
* assign a role to another user;
* submit `role` as an ordinary profile field;
* submit a permission list;
* submit wildcard permissions;
* change authorization through mass assignment.

Examples that must never be treated as authoritative:

```json
{
  "role": "ADMIN"
}
```

```json
{
  "permissions": ["*"]
}
```

```json
{
  "is_admin": true
}
```

The backend owns role and authorization state.

### 3. Staff must not become customer administrators

The model must make it impossible to infer customer-account administration from ordinary staff operations.

`STAFF` must not gain permissions for:

* changing customer passwords;
* changing customer roles;
* changing customer permissions;
* disabling or blocking customer accounts;
* changing customer security state;
* impersonating customers;
* transferring customer ownership;
* accessing customer credentials;
* deleting customer accounts.

Staff operational access is separate from customer-account administration.

### 4. No wildcard authorization model

Do not implement:

```text
*
admin.*
staff.*
all
full_access
superuser
```

as the mechanism by which a role becomes authorized.

The authorization vocabulary must remain explicit and auditable.

---

# Implementation instructions

## 3.2.1 Decide the RBAC persistence approach

Inspect the current Laravel project before adding dependencies.

Use **one** authorization implementation only.

A maintained Laravel RBAC package such as `spatie/laravel-permission` may be used when compatible with the existing Laravel version and project conventions. Do not install it blindly if an equivalent authorization foundation already exists.

Before introducing the package:

* inspect `composer.json`;
* inspect currently installed authorization/security packages;
* check whether migrations, models, middleware, gates, or policies already provide conflicting role/permission behavior;
* avoid installing two overlapping RBAC systems.

If a package is chosen, use its normal relational role/permission model rather than maintaining a parallel custom role/permission implementation.

Do not hard-code package-specific behavior into controllers.

Do not commit to a package merely because it is familiar; choose one coherent implementation and document the decision.

If a package is not appropriate, implement a small explicit relational RBAC model using Laravel conventions.

---

## 3.2.2 Role persistence

Represent roles as first-class authorization entities rather than adding another authorization boolean to `users`.

Do **not** add:

```text
users.is_admin
users.is_staff
users.is_customer
```

Do **not** add:

```text
users.role
```

merely as a shortcut if the selected RBAC implementation stores roles relationally.

The role authority must live in the RBAC model.

Use a stable role identifier/name containing exactly:

```text
CUSTOMER
STAFF
ADMIN
```

Role records must be uniquely identifiable.

Do not permit duplicate logical roles.

Do not introduce role hierarchies such as:

```text
ADMIN > STAFF > CUSTOMER
```

as implicit authorization behavior.

A user's possession of `ADMIN` must not automatically authorize an operation unless the corresponding explicit permission exists.

---

## 3.2.3 Permission persistence

Create explicit permissions as first-class authorization capabilities.

Use a stable naming convention:

```text
resource.action
```

or the exact equivalent supported by the selected authorization implementation.

Permission names are part of the V1 authorization contract. Keep them deterministic and avoid synonyms for the same capability.

Use this V1 permission vocabulary:

### Customer-owned capabilities

Customer capabilities are primarily enforced through ownership and policy rather than creating a large customer permission matrix.

Do not create hundreds of permissions such as:

```text
customer.orders.read_own
customer.orders.cancel_own
customer.cart.read_own
...
```

unless the selected implementation genuinely requires them.

Ownership rules remain policy-level authorization.

### Staff/Admin operational capabilities

Establish the explicit operational permission set below:

```text
products.view
products.manage

inventory.view
inventory.manage

orders.view_operational
orders.accept
orders.process
orders.ready_for_pickup
orders.ship
orders.deliver

requests.view
requests.manage

enquiries.view
enquiries.manage

staff.approve
staff.manage

users.manage_authorized
```

These names are capabilities, not unconditional access.

For example:

```text
orders.ship
```

means the actor may potentially perform the ship operation, but authorization still requires the applicable order ownership/operational scope and business-state precondition.

Similarly:

```text
products.manage
```

must not silently include inventory quantity management.

`inventory.manage` is separate from catalog management.

`orders.process` must not implicitly authorize:

```text
orders.accept
orders.ready_for_pickup
orders.ship
orders.deliver
```

The separation is intentional.

---

## 3.2.4 Permission-to-role matrix

Seed the initial V1 role/permission assignments explicitly.

Use this baseline:

### CUSTOMER

Customer authorization is ownership-based.

Do not grant broad operational permissions.

The customer role must not have:

```text
products.manage
inventory.view
inventory.manage
orders.view_operational
orders.accept
orders.process
orders.ready_for_pickup
orders.ship
orders.deliver
requests.manage
enquiries.manage
staff.approve
staff.manage
users.manage_authorized
```

Public catalog access does not require an authenticated customer role.

### STAFF

Grant only operational capabilities justified by the V1 business model:

```text
products.view
products.manage

inventory.view
inventory.manage

orders.view_operational
orders.accept
orders.process
orders.ready_for_pickup
orders.ship
orders.deliver

requests.view
requests.manage

enquiries.view
enquiries.manage
```

Do **not** grant:

```text
staff.approve
staff.manage
users.manage_authorized
```

Do not create an implicit `customer_admin` capability.

Do not grant customer security/account-management permissions.

### ADMIN

Grant the full set of explicitly defined operational capabilities:

```text
products.view
products.manage

inventory.view
inventory.manage

orders.view_operational
orders.accept
orders.process
orders.ready_for_pickup
orders.ship
orders.deliver

requests.view
requests.manage

enquiries.view
enquiries.manage

staff.approve
staff.manage

users.manage_authorized
```

However, even `ADMIN` must remain subject to the explicit authorization policy for the target resource and operation.

Do not implement an unconditional wildcard bypass.

---

## 3.2.5 Treat public access separately from RBAC

Public catalog access is not a `CUSTOMER` permission.

These resources are explicitly public:

```text
products
categories
search
product details
```

Authentication is not required for normal public catalog reads.

Do not create:

```text
PUBLIC
ANONYMOUS
GUEST
```

as user roles just to represent public access.

Public/private access is an endpoint/resource policy concern.

---

## 3.2.6 User-to-role relationship

Add the relationship required by the selected RBAC implementation:

```text
User -> roles
Role -> users
Role -> permissions
Permission -> roles
```

The exact Laravel relationship implementation may differ depending on the selected package, but the resulting semantics must be equivalent.

A user may have a role set that supports the system model without requiring role-specific copies of the same user identity.

For V1, the business model expects one effective role per ordinary user:

```text
CUSTOMER
STAFF
ADMIN
```

Do not build a complicated multi-role precedence system unless the chosen implementation requires it.

If the package technically supports multiple roles, establish an application invariant that ordinary users have one effective V1 role.

Do not invent role-merging precedence rules.

---

## 3.2.7 Default-role behavior

Do not make role assignment depend on an Eloquent model-created event.

Phase 3.1 intentionally avoided automatic profile creation because role assignment belongs to this phase and authentication workflows belong to Group D.

Now establish the authoritative provisioning rule:

* new self-registered customers receive `CUSTOMER`;
* `STAFF` and `ADMIN` must never be self-assigned;
* staff creation/approval is an administrative workflow;
* the backend must reject any client attempt to choose a privileged role.

Do not implement the registration or staff-approval workflow in this phase.

Only establish the RBAC model and, where necessary for seed/bootstrap behavior, the role assignment primitives.

---

## 3.2.8 Bootstrap/seed data

Create deterministic seed data for:

### Roles

```text
CUSTOMER
STAFF
ADMIN
```

### Permissions

Every V1 permission defined in this phase must have exactly one canonical seeded identifier/name.

### Role assignments

Seed only the agreed role-permission mappings.

Do not seed real customer accounts.

Do not create fake staff/admin accounts unless the existing project has an established local-development bootstrap convention.

If a local development administrator is needed, make it clearly development-only and ensure it cannot become production seed behavior accidentally.

---

## 3.2.9 Central authorization abstraction

Establish a small authorization layer that later domain policies can depend on.

Prefer Laravel-native authorization concepts where appropriate:

* Gates;
* Policies;
* permission checks;
* authorization services/abstractions.

Do not scatter raw role comparisons through controllers:

```php
if ($user->role === 'ADMIN') {
    ...
}
```

Do not make controllers responsible for reconstructing the entire authorization model.

Use centralized policy/capability checks.

The authoritative convention explicitly calls for centralized policies such as:

```text
OrderPolicy.view
OrderPolicy.cancel
OrderPolicy.process
OrderPolicy.ship

ProductPolicy.view
ProductPolicy.manage
```

rather than scattered role checks.

At this phase, create only the reusable foundation required for those policies.

Do not fully implement all domain policies yet.

---

## 3.2.10 Ownership remains separate from RBAC

Do not make a permission such as:

```text
orders.view
```

mean "can view every order".

Operational order viewing and customer ownership are different concepts.

The eventual authorization calculation must be able to answer:

```text
Who is the authenticated principal?
What role do they have?
What permission do they have?
What resource are they accessing?
What action are they performing?
Do they own the resource?
What operational context applies?
What business state applies?
```

Customer A must never gain access to Customer B's order merely because both are authenticated.

Staff operational access is not ownership.

Customer ownership and private-resource masking remain policy concerns.

---

## 3.2.11 Admin authority must remain explicit

The model must support explicit Admin-only permissions for:

```text
staff.approve
staff.manage
users.manage_authorized
```

These capabilities must not be inherited by STAFF.

Do not expose a generic:

```text
admin.*
```

shortcut.

Do not introduce an "is super admin" escape hatch.

Do not make role-changing self-service possible.

The architecture must preserve the separation of duties:

```text
STAFF  -> operational processing
ADMIN  -> privileged staff management/approval
```

Staff approval must remain Admin-controlled and auditable.

---

# Database requirements

Create the required RBAC tables/migrations according to the chosen implementation.

At minimum the resulting persistence model must represent:

```text
roles
permissions
role <-> permissions
user <-> roles
```

Use:

* foreign keys;
* uniqueness constraints;
* appropriate indexes;
* stable identifiers;
* timestamps where appropriate;
* the existing project's normal migration conventions.

Do not duplicate authorization state into unrelated tables.

Do not store serialized JSON permission arrays as the authoritative permission model.

Do not store permissions as comma-separated strings.

Do not use a free-form text blob as the role registry.

The database must be rebuildable from migrations.

---

# Model requirements

Update the `User` model only as required to expose the RBAC relationship.

The model must not expose:

```text
password
remember_token
authorization internals
permission secrets
```

through API serialization.

Do not expand the `UserResource` yet unless the existing project needs the relationship for a specific internal test.

Remember that serialization is an actor-sensitive boundary; authorization state is server-controlled.

Do not create a public user listing endpoint.

Do not create customer-management APIs.

---

# Authorization semantics

Establish these invariants:

### CUSTOMER

```text
Customer access = authenticated self + ownership + business rules
```

### STAFF

```text
Staff access = authenticated staff + explicit permission + operational scope + business-state rules
```

### ADMIN

```text
Admin access = authenticated admin + explicit permission + applicable resource/business rules
```

### Anonymous

```text
Anonymous access = explicitly public resource/action only
```

### System/background actor

Background jobs, payment callbacks, notification dispatch, inventory cleanup, and order timeouts are not represented by a CUSTOMER/STAFF/ADMIN role. Their authorization boundary will be handled by the applicable later phase.

---

# Validation and security checks

Implement tests alongside the RBAC foundation.

## Role integrity tests

Verify:

* only `CUSTOMER`, `STAFF`, `ADMIN` exist in V1 seed data;
* duplicate role creation is rejected;
* unknown roles cannot become effective user roles;
* a client cannot self-assign `STAFF`;
* a client cannot self-assign `ADMIN`;
* role data is not accepted through ordinary profile mutation.

## Permission integrity tests

Verify:

* all canonical V1 permissions are seeded;
* permissions are uniquely identified;
* wildcard permissions are not used as an authorization shortcut;
* STAFF does not receive Admin-only permissions;
* CUSTOMER does not receive staff operational permissions.

## Separation-of-duties tests

Explicitly test:

```text
STAFF cannot staff.approve
STAFF cannot staff.manage
STAFF cannot users.manage_authorized
STAFF cannot customer security/account administration
```

Explicitly test that:

```text
ADMIN may have staff.approve
ADMIN may have staff.manage
ADMIN may have users.manage_authorized
```

subject to the later domain policy implementation.

## Authorization primitive tests

Verify that:

* permission checks deny when no permission exists;
* deny-by-default is preserved;
* role alone is not treated as sufficient authorization;
* missing authenticated identity cannot pass authenticated checks;
* ownership is not inferred from client-supplied `user_id`;
* authorization state cannot be supplied from request payloads.

## Persistence tests

Verify:

* migrations run from an empty database;
* migrations roll back cleanly;
* foreign keys are valid;
* uniqueness constraints work;
* seed data is deterministic;
* rebuilding the database produces the same RBAC baseline.

## Security tests

Verify that no path exists in this phase allowing:

```json
{
  "role": "ADMIN"
}
```

or:

```json
{
  "permissions": ["*"]
}
```

to alter authorization.

Verify that authorization information cannot be changed through mass assignment.

Follow the established error envelope and do not leak framework/database implementation details.

---

# Code-quality requirements

Keep the implementation small and cohesive.

Prefer:

```text
Role
Permission
User relationship
Authorization abstraction
Seeders
Migrations
Focused tests
```

Avoid:

* giant authorization services;
* generic "everything is allowed" helpers;
* duplicated role checks;
* copied permission strings across controllers;
* hidden authorization behavior inside unrelated models;
* premature domain-specific policies for features not yet implemented.

Use constants/enums/value objects where they improve correctness and match existing project conventions.

Use dependency injection where it materially improves testability.

Keep code comments to the absolute minimum. Explain non-obvious authorization decisions through names, structure, tests, or the existing architecture/decision documentation rather than large comment blocks.

---

# Documentation / decision record

Update `docs/decisions.md` only if necessary to record a durable architectural choice such as:

* selected RBAC implementation/package;
* why an existing authorization mechanism was reused;
* why roles are relational rather than stored as booleans;
* why customer authorization remains ownership/policy-based;
* why wildcard admin permissions are intentionally excluded.

Do not create a permanent Phase-3.2 markdown file merely to duplicate this instruction.

If the selected RBAC package requires an important configuration assumption, document only that durable decision.

---

# Explicitly out of scope

Do **not** implement:

* user registration;
* login/logout;
* password hashing workflows;
* password reset;
* email verification;
* MFA;
* Sanctum/Passport/token issuance;
* browser session handling;
* Flutter authentication;
* authentication middleware;
* staff approval endpoints;
* staff suspension/reactivation endpoints;
* customer administration endpoints;
* customer disable/block functionality;
* impersonation;
* order authorization rules;
* inventory authorization rules;
* product authorization endpoints;
* request/enquiry authorization endpoints;
* admin UI;
* staff UI;
* customer UI;
* Flutter authorization UI;
* payment authorization;
* audit-event storage implementation;
* notification authorization;
* authorization caching.

Authentication is Group D. Domain authorization is implemented with the corresponding domain phases.

---

# Definition of done

Phase 3.2 is complete only when:

1. The project has one coherent RBAC implementation.
2. V1 contains exactly `CUSTOMER`, `STAFF`, and `ADMIN`.
3. V1 permissions are explicit, stable, and seeded.
4. Role/permission relationships are persisted with proper constraints.
5. User-to-role relationships are established on the Phase 3.1 `User`.
6. STAFF has only the agreed operational capabilities.
7. ADMIN has the agreed explicit administrative capabilities without a wildcard escape hatch.
8. CUSTOMER does not receive staff/admin operational permissions.
9. Customer ownership is clearly separated from staff operational authorization.
10. Staff cannot acquire customer-account administration capabilities through the RBAC model.
11. Client-supplied role/permission values cannot alter authorization.
12. No mass-assignment path exists for authorization state.
13. Authorization primitives are centralized and reusable by future domain policies.
14. Migration tests pass from an empty database and rollback successfully.
15. RBAC tests cover role integrity, permission mapping, deny-by-default, and separation of duties.
16. Static analysis, formatting, and the existing test suite pass.
17. No authentication or business-domain workflow has been pulled forward from later phases.
18. The implementation contains only the minimum comments necessary.

---

# STOP condition

Stop after the RBAC database/model/foundation and its tests are complete.

Do not continue into Phase 3.3 Categories Schema.

Do not implement authentication, staff approval, user management endpoints, or domain-specific authorization workflows merely because the RBAC foundation now exists.

Do not commit, stage, or push changes.
