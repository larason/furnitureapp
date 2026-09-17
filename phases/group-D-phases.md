# Phase 4.9 — Laravel RBAC Integration for CUSTOMER / STAFF / ADMIN

## Purpose

Implement the Laravel role-based access-control foundation for the frozen V1 roles:

```text
CUSTOMER
STAFF
ADMIN
```

This phase establishes:

* canonical roles;
* role assignment;
* role resolution;
* role invariants;
* Staff approval boundary;
* Admin authority;
* Customer protection;
* Clerk/Laravel separation;
* RBAC tests.

This phase does **not** implement every resource/action policy.

Detailed authorization belongs to:

**Phase 4.10 — Policies / Permissions**

---

# 1. Core Architecture

The authorization stack must remain:

```text
Clerk
    ↓
authenticated identity
    ↓
Laravel User
    ↓
Laravel RBAC
    ↓
CUSTOMER / STAFF / ADMIN
    ↓
Laravel policies / permissions
    ↓
domain operation
```

Clerk authenticates.

Laravel authorizes.

Do not merge these concerns.

---

# 2. Closed V1 Roles

The complete V1 role set is:

```text
CUSTOMER
STAFF
ADMIN
```

This enum/set is CLOSED.

Do not add:

```text
SUPER_ADMIN
MANAGER
WAREHOUSE
DELIVERY_AGENT
SELLER
MODERATOR
```

during this phase.

Any new role requires an explicit V1 compatibility/domain decision.

---

# 3. Role Meaning

## CUSTOMER

Represents a normal ecommerce customer.

Typical capabilities later include:

```text
browse catalog
manage own cart
checkout
view own orders
cancel eligible own orders
manage own profile
submit own requests/enquiries
```

CUSTOMER does not have operational or administrative authority.

---

## STAFF

Represents an approved operational employee.

STAFF exists to perform explicit ecommerce operations.

Examples may later include:

```text
process orders
manage operational inventory
update allowed order states
manage delivery operations
handle allowed catalogue operations
```

STAFF is **not** customer-account administration.

---

## ADMIN

Represents the highest V1 administrative role.

ADMIN controls privileged administrative operations such as:

```text
approve Staff
manage Staff lifecycle
assign approved Staff permissions
perform explicitly authorized administrative operations
```

ADMIN is not a bypass for poorly designed authorization.

Policies still govern resource/action access.

---

# 4. Role Source of Truth

Role authority must remain entirely in Laravel.

Canonical source:

```text
Laravel RBAC
```

Never derive role from:

```text
Clerk publicMetadata
Clerk privateMetadata
Clerk unsafeMetadata
Clerk Organizations
email domain
request body
query parameter
header
frontend state
```

Clerk metadata may exist but is not application RBAC authority.

---

# 5. Do Not Store Role in Clerk

Do not synchronize:

```text
CUSTOMER
STAFF
ADMIN
```

into Clerk as the authoritative role model.

Do not implement:

```text
Clerk role → Laravel role
```

Laravel remains authoritative.

This prevents authentication-provider configuration from becoming business authorization.

---

# 6. Existing RBAC Technology

Use the RBAC mechanism already selected during Group C.

If the project already uses:

```text
spatie/laravel-permission
```

continue using it.

Do not create a second custom RBAC system.

Do not add:

```text
users.role
users.is_admin
users.is_staff
```

for convenience.

---

# 7. One Role Model

There must be one canonical role system.

Avoid:

```text
Spatie role
+
users.role
+
Clerk metadata.role
+
custom enum field
```

as competing authorities.

Use one Laravel RBAC source.

---

# 8. Canonical Role Constants / Enum

Centralize role names.

Use the project's preferred PHP enum/constants approach.

Conceptually:

```php
CUSTOMER
STAFF
ADMIN
```

Do not scatter raw strings:

```php
'customer'
'CUSTOMER'
'staff'
'admin'
```

through controllers and services.

Role names are meaningful domain constants.

---

# 9. Exact Case

Use canonical values exactly:

```text
CUSTOMER
STAFF
ADMIN
```

Do not introduce case variants.

Do not allow:

```text
customer
Customer
admin
Admin
```

as independent roles.

Normalize through the application definition, not by accepting arbitrary client input.

---

# 10. Public Registration Role

Public customer registration must create:

```text
CUSTOMER
```

only.

Flow:

```text
Clerk signup
    ↓
verified Clerk identity
    ↓
local user provisioning
    ↓
CUSTOMER
```

No public flow may assign:

```text
STAFF
ADMIN
```

---

# 11. Phase 4.2 Integration

Reuse Phase 4.2 provisioning.

For a newly provisioned public user:

```text
assign CUSTOMER
```

must happen atomically with user creation.

Do not duplicate role provisioning in Phase 4.9.

Refactor only if needed to centralize the role invariant.

---

# 12. Existing User Login

Authentication must not mutate role.

For an existing user:

```text
sign in
→ resolve local User
→ keep existing role
```

Never execute:

```text
assign CUSTOMER
```

on every login.

This protects existing STAFF and ADMIN accounts.

---

# 13. CUSTOMER Is Not a Fallback Role for Everything

Do not implement:

```text
if no role:
    assign CUSTOMER
```

globally.

CUSTOMER assignment is valid only for approved public customer provisioning.

A malformed Staff/Admin account with no role should fail safely and be corrected explicitly.

---

# 14. STAFF Creation Boundary

STAFF must not be created through public customer registration.

A Staff account must come through an explicit administrative onboarding/approval flow.

Conceptually:

```text
ADMIN-authorized operation
    ↓
approved Staff identity
    ↓
local User
    ↓
STAFF role
```

Exact Staff lifecycle implementation belongs to the appropriate later admin/user-management phase if not already contracted.

---

# 15. ADMIN Creation Boundary

ADMIN must never be created through:

```text
public signup
customer profile update
Clerk metadata
Staff action
```

Admin bootstrap must follow the existing secured bootstrap/deployment process.

Do not add a public:

```text
register-admin
```

endpoint.

---

# 16. Staff Approval Is Admin-Only

Mandatory invariant:

```text
ADMIN
+ staff.approve
→ may approve STAFF
```

STAFF cannot:

```text
approve themselves
approve another Staff
promote CUSTOMER to STAFF
promote themselves to ADMIN
```

Customer cannot approve Staff.

---

# 17. Self-Approval Forbidden

Even an account awaiting Staff activation must never be able to authorize its own approval.

Do not derive approval from:

```text
authenticated user == target
```

Approval requires a separate authorized ADMIN actor.

---

# 18. No Role Self-Service

Customers and Staff cannot change their own role.

Reject any flow equivalent to:

```json
{
  "role": "ADMIN"
}
```

through:

```text
PATCH /me
signup
login
profile update
Clerk metadata
```

Role is server-controlled.

---

# 19. CUSTOMER Ownership Model

Customer authorization is primarily based on ownership.

Future policy decisions should follow:

```text
CUSTOMER
+
owns resource
+
action allowed
+
resource state allows action
```

Example:

```text
CUSTOMER
+
owns Order
+
Order still cancellable
→ cancellation may be allowed
```

Role alone is insufficient.

---

# 20. STAFF Operational Boundary

STAFF is operational.

STAFF may later receive explicit permissions to work with:

```text
orders
inventory
catalogue
delivery operations
requests/enquiries where appropriate
```

But STAFF must have **zero ordinary customer-account administration**.

---

# 21. STAFF Cannot Control Customers

STAFF must not:

```text
change customer role
change customer permissions
disable customer account
suspend customer account
change customer password
change customer email
remove customer MFA/security
impersonate customer
delete customer
transfer customer ownership
view customer credentials
```

This is a hard V1 boundary.

Do not grant these actions via wildcard permission.

---

# 22. Customer Account Ownership

Customer accounts belong to customers.

STAFF operational authority over an Order does not imply authority over the Customer account that owns that Order.

Keep:

```text
order operational access
```

separate from:

```text
customer account control
```

---

# 23. ADMIN Authority

ADMIN is the highest V1 role.

ADMIN may later receive explicit permissions for:

```text
staff.approve
staff.manage
administrative catalogue operations
administrative operational actions
```

according to the frozen authorization model.

Do not rely on:

```text
role == ADMIN
→ allow everything
```

as the final policy implementation.

Phase 4.10 must still define explicit authorization rules.

---

# 24. Avoid Wildcard Authorization

Do not implement a broad:

```text
admin.*
```

or:

```text
*
```

permission model that bypasses resource policies.

ADMIN can be powerful while still using explicit permissions.

This keeps security auditable and predictable.

---

# 25. Role ≠ Permission

Maintain this distinction:

```text
Role
→ coarse actor classification

Permission
→ allowed action category

Policy
→ whether actor can perform action on this resource now
```

For example:

```text
STAFF
+
orders.update_status
+
valid transition
→ may proceed
```

Not:

```text
STAFF
→ may update anything
```

---

# 26. Phase 4.9 vs 4.10

Phase 4.9 owns:

```text
roles
role assignment
role invariants
role lifecycle boundaries
role resolution
basic permission foundation
```

Phase 4.10 owns:

```text
resource policies
ownership checks
action-specific permissions
state-aware authorization
404 masking decisions
```

Do not pull the entire policy matrix into Phase 4.9.

---

# 27. Permission Foundation

If using Spatie permissions, establish only the permission infrastructure needed for the existing design.

Do not invent every future permission now.

Use already-documented permissions where available.

Keep permission definitions explicit.

---

# 28. No Permission Explosion

Avoid overly granular permission names like:

```text
orders.view.processing
orders.view.shipped
orders.view.cancelled
orders.edit.customer_name
```

unless a real requirement exists.

Use meaningful action-level permissions.

Business state remains a policy/domain check.

---

# 29. Permission Naming

Use consistent canonical names.

Conceptual examples:

```text
orders.view_operational
orders.accept
orders.ship
inventory.adjust
catalog.manage
staff.approve
staff.manage
```

Use actual names already frozen in project docs where defined.

Do not invent conflicting aliases.

---

# 30. Role-Permission Assignment

Centralize V1 role-to-permission mapping.

Do not scatter:

```php
givePermissionTo(...)
```

across controllers/services.

Use seed/bootstrap configuration or another single authoritative setup mechanism.

---

# 31. CUSTOMER Permissions

CUSTOMER usually should not need many generic permission rows if ownership policies express their rights cleanly.

Do not overpopulate Customer permission tables simply to mirror every customer action.

Use the simplest model consistent with the project's existing RBAC implementation.

---

# 32. STAFF Permissions

Assign only approved operational permissions.

No customer-account management permissions.

Explicitly verify that Staff does not accidentally inherit:

```text
user.manage
customer.suspend
customer.role.change
credential.manage
```

or equivalents.

---

# 33. ADMIN Permissions

ADMIN receives approved administrative permissions.

Do not use ADMIN role as justification for bypassing input validation or domain rules.

Admin actions still follow:

```text
authentication
→ authorization
→ domain validation
→ transaction
```

---

# 34. Default Deny

Authorization philosophy remains:

```text
not explicitly allowed
→ denied
```

Do not infer permissions from:

```text
role hierarchy
higher numeric level
name contains admin
```

---

# 35. No Numeric Role Hierarchy

Avoid:

```text
CUSTOMER = 1
STAFF = 2
ADMIN = 3

if role >= STAFF
```

This creates accidental privilege inheritance.

Use explicit roles/permissions.

---

# 36. Role Checks

Where a role check is genuinely required, use the established RBAC API.

Do not write:

```php
if ($user->role === 'ADMIN')
```

if the application uses Spatie role relationships.

Use the canonical abstraction.

---

# 37. Do Not Trust Frontend Role

Any future frontend-provided value such as:

```json
{
  "role": "CUSTOMER"
}
```

is irrelevant to server authorization.

Server derives the role from the authenticated local User.

---

# 38. Clerk Token Does Not Carry Laravel Authority

Do not depend on a role embedded in the Clerk session token for application RBAC.

Canonical request path:

```text
verified token.sub
    ↓
local User
    ↓
Laravel roles/permissions
```

Not:

```text
verified token.role
→ authorization
```

---

# 39. User Mapping

Every authenticated actor must first resolve:

```text
Clerk user ID
→ local users.clerk_user_id
→ Laravel User
```

Role resolution happens after local User resolution.

Do not map role directly from external identity.

---

# 40. One User, One Primary Role

For V1, use one primary application role per user unless Group C explicitly established multi-role users.

Preferred invariant:

```text
one User
→ one of CUSTOMER / STAFF / ADMIN
```

Do not assign:

```text
CUSTOMER + STAFF
```

simultaneously merely because Staff may also purchase furniture.

---

# 41. Staff May Purchase Without CUSTOMER Role

If Staff can make purchases, do not assign CUSTOMER as an additional role just to allow commerce.

Instead, later policies can explicitly allow Staff to perform customer-commerce actions on their own account where required.

Keep actor classification clear.

---

# 42. Admin May Purchase Without CUSTOMER Role

Likewise, an Admin who may buy furniture does not require the CUSTOMER role.

Policies should permit appropriate self-commerce actions if the business rules allow them.

Do not create role stacking unnecessarily.

---

# 43. Role Transition Model

Role changes are privileged state changes.

Allowed conceptual transitions must be explicit.

Potential examples:

```text
CUSTOMER → STAFF
```

only through approved Admin-controlled onboarding if business rules allow it.

```text
STAFF → ADMIN
```

only through explicit privileged process if ever supported.

Do not allow arbitrary:

```text
role = X
```

generic updates.

---

# 44. No Generic Role PATCH

Do not implement:

```http
PATCH /users/{id}
```

with:

```json
{
  "role": "STAFF"
}
```

as a generic administrative write.

Use explicit administrative actions if role transitions are exposed later.

---

# 45. Account State Separate From Role

Keep:

```text
role
```

separate from:

```text
account state
approval state
is_active
```

A STAFF role does not necessarily imply an account is approved/active.

Do not encode approval status inside role names such as:

```text
PENDING_STAFF
ACTIVE_STAFF
```

unless explicitly defined by existing domain design.

---

# 46. Staff Approval State

If Staff lifecycle currently includes:

```text
PENDING
ACTIVE
SUSPENDED
```

or equivalent, continue using the approved account/staff-profile state model.

Do not replace it with multiple roles.

---

# 47. Suspended Staff

A Staff user may still have:

```text
role = STAFF
```

while account/staff state denies operational access.

This reinforces:

```text
role alone
≠
authorization
```

Phase 4.10 will evaluate these states in policies.

---

# 48. Disabled Customer

If customer application account state can be disabled later:

```text
role = CUSTOMER
```

remains role.

Account state separately determines availability.

Do not mutate role just to represent suspension.

---

# 49. Seeder Responsibility

Ensure canonical roles exist through deterministic seed/bootstrap logic.

Fresh database:

```text
migrate:fresh --seed
```

must create:

```text
CUSTOMER
STAFF
ADMIN
```

exactly once.

No duplicate case variants.

---

# 50. Idempotent Role Seeding

Running role seed logic repeatedly must not create duplicate roles or permissions.

Use deterministic `firstOrCreate`/sync semantics appropriate to the RBAC package.

---

# 51. Production Seed Safety

Do not seed real production admin credentials.

Role/permission definitions may be production-safe seed data.

Actual Admin identity/bootstrap must remain separate.

---

# 52. Admin Bootstrap

Preserve the approved Admin bootstrap mechanism.

Do not:

```text
create default admin@example.com
password = admin123
```

in seeders.

No hard-coded production admin credentials.

---

# 53. Role Seeder Naming

Use a clear seeder or setup location consistent with existing database seed structure.

Avoid multiple files independently defining the same role names.

---

# 54. Factory States

Factories may expose deterministic states:

```text
customer()
staff()
admin()
```

for tests.

They should use the canonical RBAC system.

Do not fake roles with arbitrary attributes.

---

# 55. Factory Defaults

If UserFactory needs a default role, be careful.

Generic factory creation should not accidentally hide role-assignment bugs.

Prefer explicit role states in tests where authorization matters.

---

# 56. Tests — Public Provisioning

Provision a new public Clerk customer.

Verify:

```text
role = CUSTOMER
```

and:

```text
not STAFF
not ADMIN
```

---

# 57. Tests — Client Role Tampering

Attempt registration/provisioning with:

```json
{
  "role": "ADMIN"
}
```

Verify CUSTOMER remains assigned.

---

# 58. Tests — Clerk Metadata Tampering

Fake Clerk metadata:

```json
{
  "role": "ADMIN"
}
```

Verify Laravel role remains whatever is stored locally.

Mandatory security regression test.

---

# 59. Tests — Existing Staff Login

Existing:

```text
role = STAFF
```

signs in.

Verify role remains STAFF.

No downgrade to CUSTOMER.

---

# 60. Tests — Existing Admin Login

Existing:

```text
role = ADMIN
```

signs in.

Verify role remains ADMIN.

No role mutation during authentication.

---

# 61. Tests — Missing Role

Create malformed local User with no role.

Verify protected role-sensitive operation fails safely.

Do not auto-promote/auto-default on login.

---

# 62. Tests — Duplicate Role Assignment

Ensure provisioning or seed reruns do not produce duplicate role relationships.

---

# 63. Tests — Customer Cannot Self-Promote

Authenticated CUSTOMER attempts any exposed role mutation path.

Expected:

```text
forbidden
```

or no route exists.

---

# 64. Tests — Staff Cannot Self-Promote

Authenticated STAFF attempts:

```text
STAFF → ADMIN
```

Expected:

```text
FORBIDDEN
```

or no route exists.

---

# 65. Tests — Staff Cannot Approve Staff

STAFF attempts Staff approval.

Expected:

```text
403 FORBIDDEN
```

when such action exists.

---

# 66. Tests — Customer Cannot Approve Staff

CUSTOMER attempts Staff approval.

Expected:

```text
403 FORBIDDEN
```

or route inaccessible according to contract.

---

# 67. Tests — Admin Approval Boundary

ADMIN with approved permission may reach the Staff approval authorization boundary.

Detailed approval domain behavior may remain for the appropriate Staff management phase.

Phase 4.9 only proves role/permission gating.

---

# 68. Tests — Staff Customer Account Protection

Add regression coverage proving STAFF cannot perform customer-account actions such as:

```text
change role
disable customer
change credentials
delete account
```

where routes/actions already exist.

If those routes do not yet exist, record the invariant for Phase 4.10/Group K rather than inventing them.

---

# 69. Tests — Customer Ownership Is Not Role Alone

Do not write tests implying:

```text
CUSTOMER
→ access all customer resources
```

Customer resources remain ownership-scoped.

Detailed policy tests belong to 4.10.

---

# 70. Tests — Role Is Laravel-Owned

Given:

```text
Clerk identity user_A
```

with local Laravel role CUSTOMER:

verify application role resolution returns CUSTOMER independent of Clerk profile data.

---

# 71. Tests — Same User Across Clients

The same Clerk user authenticated from future web/mobile contexts must resolve to the same role.

Do not create client-specific RBAC.

---

# 72. No Frontend Work

Do not modify:

```text
frontend/web/
frontend/app/
frontend/design-system/
```

during Phase 4.9.

No:

```text
route guards
role-based menus
admin navigation
customer navigation
frontend permission hooks
```

These belong to frontend phases.

---

# 73. Frontend Role Display Deferred

Future frontend may consume Laravel `/me` role for UX.

Do not implement:

```text
useRole()
RoleGuard
<AdminOnly>
```

now.

Group D remains backend-focused.

---

# 74. No Clerk Frontend Metadata Work

Do not add role metadata to Clerk merely to make future frontend route guards easier.

Future frontend must use Laravel application state where role matters.

---

# 75. API V1 Role Representation

Review the frozen API representation of role.

Ensure only canonical values are emitted:

```text
CUSTOMER
STAFF
ADMIN
```

No lowercase variants.

No internal permission model leaks.

---

# 76. `/me` Role

If `/me` includes:

```text
role
```

it must come from Laravel RBAC.

Do not calculate it from Clerk.

---

# 77. `/me` Is Read-Only for Role

`PATCH /me` must not allow role changes.

Ensure current profile request validation continues to reject:

```text
role
permissions
account_state
```

---

# 78. Admin/Staff User APIs

Do not create broad customer-management endpoints during Phase 4.9.

Any administrative account-management APIs belong to the appropriate Group K phase and Phase 4.10 policy groundwork.

---

# 79. Authorization Pipeline

Maintain:

```text
Transport
→ Schema
→ Authentication
→ Authorization
→ Domain
→ Transaction
```

Role resolution happens inside authorization context after identity authentication.

Do not perform domain mutation before authorization.

---

# 80. No Role-Based SQL Exposure

Do not trust query parameters like:

```text
?role=ADMIN
```

to establish authorization.

Filters may later filter user lists where authorized, but never grant role.

---

# 81. Role Change Audit

Any future privileged role transition should be auditable.

Record requirement:

```text
actor
target
old role
new role
timestamp
request_id
```

Do not build full audit infrastructure in this phase unless existing mechanisms already support it.

---

# 82. Role Assignment Transactions

When role assignment occurs alongside user creation/provisioning:

perform both atomically.

Avoid:

```text
User created
role assignment failed
```

leaving a usable roleless customer account.

---

# 83. Admin Bootstrap Transactions

If Admin bootstrap tooling touches local role assignment, ensure identity + role state is internally consistent.

Do not create partial privileged users.

---

# 84. Cache Considerations

Do not cache roles indefinitely outside Laravel.

Within backend requests, use the normal RBAC mechanisms.

Do not introduce custom Redis role caches unless existing Spatie configuration already uses supported permission caching.

---

# 85. Permission Cache

If using Spatie permission caching:

use its supported cache management.

Do not hand-roll cache invalidation.

Role/permission changes must invalidate relevant cached authorization state using package-standard mechanisms.

---

# 86. No Role in Session Token

Do not require Clerk custom session templates merely to embed:

```text
role
```

into the Clerk JWT.

This duplicates Laravel state and risks stale authorization.

Keep session token focused on identity.

---

# 87. No Permission Claims in Clerk JWT

Likewise do not embed full Laravel permissions into Clerk session claims.

Authorization stays server-side.

---

# 88. Permission Synchronization

There is no:

```text
Clerk ↔ Laravel permissions sync
```

in V1.

Laravel is authoritative.

Do not build such integration.

---

# 89. Security Boundary

The following must never grant privilege:

```text
email
verified email
phone
Clerk metadata
client type
frontend route
JWT custom user metadata
signup path
```

Only local Laravel RBAC plus policies determine application authority.

---

# 90. Customer Email Domain

Do not infer Staff/Admin role from email domain.

Example:

```text
@company.example
```

must not automatically imply STAFF.

Staff/Admin assignment requires explicit secured process.

---

# 91. Role Escalation Threat Review

Review against:

```text
mass assignment
Clerk metadata tampering
self-promotion
Staff-to-Admin escalation
customer-to-Staff escalation
role defaulting
seed misconfiguration
duplicate role systems
wildcard permissions
frontend authority
```

Add regression tests around the real risks present in current code.

---

# 92. Error Semantics

Unauthenticated:

```text
401 AUTHENTICATION_REQUIRED
```

Authenticated but role/permission denied:

```text
403 FORBIDDEN
```

unless specific private-resource masking requires:

```text
404
```

Phase 4.10 will handle detailed resource masking.

---

# 93. Do Not Leak Role Existence Through Errors

Do not return:

```text
You need ADMIN role
You are only STAFF
```

in sensitive API errors unless explicitly part of approved error messaging.

Generic:

```text
FORBIDDEN
```

is generally safer.

---

# 94. Logging

Safe logging may include:

```text
local user ID
role-change event category
actor/target IDs for privileged operations
request_id
```

Do not log:

```text
Clerk token
password
permission dumps unnecessarily
```

---

# 95. Documentation

Update relevant consolidated documentation only.

Likely:

```text
docs/api/api-conventions.md
docs/domain/business-rules.md
docs/decisions.md
AGENTS.md
```

and:

```text
docs/api/api-contract.md
docs/api/openapi.yaml
```

only if role representation/security requirements require correction.

Do not create excessive phase-specific docs.

---

# 96. Role Matrix Documentation

Maintain a concise role matrix:

| Capability category      | CUSTOMER           | STAFF                 | ADMIN                                    |
| ------------------------ | ------------------ | --------------------- | ---------------------------------------- |
| Public browsing          | Yes                | Yes                   | Yes                                      |
| Own customer commerce    | Yes                | As explicitly allowed | As explicitly allowed                    |
| Operational commerce     | No                 | Explicit permissions  | Explicit permissions                     |
| Customer account control | Own profile only   | No                    | Only explicitly authorized admin actions |
| Staff approval           | No                 | No                    | Yes                                      |
| Role self-change         | No                 | No                    | No                                       |
| Credential authority     | Clerk self-service | Clerk self-service    | Clerk/admin security policy              |

Do not treat this matrix as a substitute for Phase 4.10 policies.

---

# 97. Explicit Customer Protection Rule

Document prominently:

> STAFF operational access does not grant customer-account authority.

This must remain true even if Staff can view customer/order contact details necessary to process an order.

Operational visibility is not account control.

---

# 98. Permission Assignment Documentation

Document which permissions are assigned to:

```text
CUSTOMER
STAFF
ADMIN
```

only where already known.

Do not invent broad speculative permissions for future domains.

---

# 99. API Contract Stability

Roles are a CLOSED V1 set.

Changing:

```text
CUSTOMER / STAFF / ADMIN
```

or adding another role is a contract/domain change.

Do not silently extend the enum.

---

# 100. OpenAPI

Where role appears in API schemas:

ensure enum is exactly:

```yaml
CUSTOMER
STAFF
ADMIN
```

Do not expose internal permission tables unless contract requires them.

Do not add role mutation endpoints.

---

# 101. Seeder Verification

Run fresh database setup and verify canonical role data.

At minimum:

```bash
php artisan migrate:fresh --seed
```

when role/permission seeders change.

---

# 102. Tests

Run:

```bash
php artisan test
vendor/bin/pint --test
vendor/bin/phpstan analyse
composer audit
```

Use project-defined equivalents where available.

---

# 103. RBAC-Specific Test Suite

Ensure coverage for:

```text
CUSTOMER provisioning
STAFF persistence
ADMIN persistence
self-promotion rejection
metadata role rejection
Staff approval boundary
Staff/customer-account separation
missing-role failure
canonical role casing
idempotent role seeding
```

Do not rely solely on package tests.

---

# 104. Schema Changes

Expected:

```text
NONE
```

if Group C already installed the RBAC schema.

Do not add role columns.

If a required Spatie migration/schema is genuinely absent, inspect Group C first before adding anything.

---

# 105. Backend-Only Phase

Expected frontend changes:

```text
NONE
```

Do not install frontend packages.

Do not add route guards.

Do not alter website/app layouts.

---

# 106. Code Quality

Follow existing project standards:

* cognitive complexity ≤15;
* maximum 3 returns where practical;
* constants/enums for canonical roles;
* small role/permission bootstrap code;
* no giant authorization service;
* no magic strings;
* no duplicate role stores;
* minimal comments;
* strict dependency direction.

---

# 107. Avoid `RoleService` Overengineering

Do not create a generic:

```text
RoleService
```

with dozens of methods if package-standard RBAC APIs are sufficient.

Use small domain/application helpers only where real business invariants need centralization.

---

# 108. No Generic Authorization Engine

Do not build a custom rule engine.

Laravel Gates/Policies + the chosen RBAC package are sufficient.

Phase 4.10 will use standard Laravel authorization patterns.

---

# 109. Expected Implementation Areas

Likely changes may include:

```text
app/Models/User.php
app/Authorization/
app/Support/
database/seeders/
database/factories/
tests/Feature/Auth/
tests/Unit/
docs/
```

Reuse existing locations.

Do not restructure the backend merely for RBAC.

---

# 110. Files Changed Report

At completion report:

## Roles

Confirm:

```text
CUSTOMER
STAFF
ADMIN
```

only.

## Permissions

List canonical assignments actually implemented.

## Provisioning

Confirm public signup creates CUSTOMER only.

## Staff protection

Confirm Staff cannot manage customer accounts.

## Admin boundary

Confirm Staff approval requires ADMIN.

## Schema

Expected:

```text
none
```

unless justified.

## Frontend

Must state:

```text
NONE
```

## Tests / Commands

List exact commands and outcomes.

---

# 111. Definition of Done

Phase 4.9 is complete when:

* V1 roles are exactly CUSTOMER / STAFF / ADMIN;
* Laravel is the sole role authority;
* Clerk metadata cannot grant roles;
* no duplicate role field/system exists;
* public registration assigns CUSTOMER only;
* existing Staff/Admin are not downgraded on login;
* STAFF cannot self-promote;
* CUSTOMER cannot self-promote;
* Staff approval is ADMIN-only;
* STAFF has no ordinary customer-account control;
* role and account state remain separate concepts;
* role and permission concepts remain separate;
* role does not bypass ownership/domain checks;
* role definitions/seeding are deterministic;
* role casing is canonical;
* role/permission cache behavior uses package-standard mechanisms;
* `/me` role comes from Laravel;
* `/me` cannot mutate role;
* no frontend role guards/UI were implemented;
* no schema duplication was introduced;
* RBAC tests pass;
* full backend tests pass;
* Pint passes;
* PHPStan passes;
* Composer audit passes;
* documentation reflects the V1 role model.

---

# 112. Out of Scope

Do not implement:

* complete Laravel policy matrix;
* order ownership policies;
* inventory policies;
* catalogue policies;
* request/enquiry policies;
* detailed 404 masking logic;
* frontend route guards;
* admin navigation;
* customer navigation;
* Staff UI;
* Admin UI;
* customer management APIs;
* new roles;
* Clerk Organizations;
* role metadata synchronization to Clerk;
* full audit subsystem.

---

# 113. STOP Condition

STOP once Laravel has one secure, deterministic V1 RBAC foundation built around:

```text
CUSTOMER
STAFF
ADMIN
```

with public CUSTOMER provisioning, explicit Staff/Admin boundaries, zero Clerk role authority, zero frontend implementation, and all relevant backend tests passing.

Do not continue automatically.

The next backend roadmap phase is:

**Phase 4.10 — Laravel Policies / Permissions / Ownership Authorization**
