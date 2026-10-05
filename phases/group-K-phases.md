# Phase 11.8 — Customer Management

## Objective

Implement **Group K / Phase 11.8 — Customer Management** as the secure, purpose-limited Admin visibility surface for Customer accounts.

The canonical V1 endpoints are:

```text
ADM-008
GET /api/v1/users

ADM-009
GET /api/v1/users/{user}
```

Authorization:

```text
ADMINISTRATIVE
users.manage_authorized
```

This phase is intentionally **read-only**.

“Customer Management” in Group K does NOT mean that Admin or Staff may arbitrarily mutate, disable, impersonate, block, delete, or alter Customer credentials.

The goal is:

```text
Admin
→ securely find Customers
→ inspect the minimum Customer account information needed for business administration
```

while preserving:

```text
Customer ownership
Staff operational boundaries
Clerk credential authority
data minimization
no impersonation
no arbitrary account restriction
```

---

# 1. First Action — Read Repository Authorities

Before editing code, inspect the current repository and determine the exact existing state.

At minimum inspect:

```text
AGENTS.md

phases/group-K-phases.md

docs/api/api-contract.md
docs/api/api-resources.md
docs/api/api-conventions.md
docs/api/openapi.yaml
docs/domain/business-rules.md
docs/decisions.md

routes/api.php

User model
CustomerProfile model
StaffProfile model

current USER-001 / USER-002 implementation
current ADM-001..006 implementation/stubs
current ADM-008 / ADM-009 route/controller stubs

PermissionName
PermissionCatalog
Authorization

current User/Profile resources
Clerk/local user projection
LocalUserProvisioner
relevant middleware/policies

existing Admin/Staff tests
existing User/Profile tests
RBAC tests
```

Do not assume the roadmap wording alone defines the wire contract.

Reconcile implementation against the frozen V1 authorities.

---

# 2. Mandatory Git Workflow Skill

The project owner now explicitly authorizes the coding agent to perform Git operations for this and subsequent work **only through the repository's approved Git workflow**.

Before performing ANY Git operation, locate and read the root project skill:

```text
git-workflow-and-versioning
```

Use the actual skill file/instructions present at the project root.

Do not guess its commands.

Do not substitute your own Git workflow.

The skill is authoritative for:

```text
branching
status checks
staging
commits
commit format
versioning
tags
push behavior
cleanup
pre-commit verification
```

If the skill conflicts with generic agent Git habits:

```text
git-workflow-and-versioning
wins.
```

## Git safety

Never:

```text
force-push
reset --hard
discard unrelated owner changes
rewrite unrelated commits
delete owner branches
commit secrets
commit .env
```

unless the root skill explicitly authorizes the specific action and it is appropriate to the current task.

Before touching Git, preserve existing uncommitted owner work.

If unrelated modifications already exist, do not absorb or overwrite them.

All Git actions performed must be included in the completion report.

---

# 3. Group K Boundary

Group K already established:

```text
Customer visibility
→ ADM-008 / ADM-009

Staff lifecycle
→ ADM-001..006
```

Do not merge these domains.

Therefore Phase 11.8 must NOT convert:

```text
GET /api/v1/users
```

into a replacement Staff-management surface.

Staff management remains:

```text
/api/v1/admin/staff
```

under its own permissions and lifecycle.

---

# 4. Resolve the ADM-008/009 Scope Correctly

The historical endpoint catalogue calls ADM-008:

```text
List users
```

while the later Group K architecture explicitly assigns:

```text
customer visibility → ADM-008/009
staff lifecycle → ADM-001..006
```

For Phase 11.8, reconcile this without changing the frozen paths or endpoint IDs.

The intended Group K interpretation is:

```text
ADM-008 / ADM-009
= authorized Admin visibility into CUSTOMER accounts
```

not a second Staff/Admin directory.

Therefore:

```text
ADM-008
GET /api/v1/users
→ CUSTOMER accounts only

ADM-009
GET /api/v1/users/{user}
→ CUSTOMER account only
```

Do not return Staff or Admin identities through this customer-management surface.

Staff/Admin identities remain governed by their own administration/self-service boundaries.

Record this reconciliation clearly in:

```text
docs/decisions.md
phases/group-K-phases.md
```

Do not rename the frozen endpoints.

Do not create:

```text
/api/v1/customers
/api/v1/admin/customers
```

aliases.

---

# 5. Read-Only Phase

Phase 11.8 introduces no Customer mutation endpoint.

Canonical surface:

```text
GET /api/v1/users
GET /api/v1/users/{user}
```

ONLY.

Do NOT add:

```text
POST   /api/v1/users
PATCH  /api/v1/users/{user}
PUT    /api/v1/users/{user}
DELETE /api/v1/users/{user}

POST /api/v1/users/{user}/suspend
POST /api/v1/users/{user}/activate
POST /api/v1/users/{user}/block
POST /api/v1/users/{user}/ban
POST /api/v1/users/{user}/impersonate
POST /api/v1/users/{user}/reset-password
POST /api/v1/users/{user}/change-role
```

There is no frozen V1 contract for these operations.

Do not invent one.

---

# 6. Authorization

Both endpoints require:

```text
authentication
+
users.manage_authorized
```

Expected:

```text
ADMIN with users.manage_authorized
→ allowed

STAFF
→ forbidden

CUSTOMER
→ forbidden

anonymous
→ authentication required
```

Do NOT rely merely on:

```php
$user->role === 'ADMIN'
```

Use the existing central permission infrastructure.

`users.manage_authorized` is explicit Admin authority.

No wildcard permission.

No role hierarchy shortcut.

---

# 7. STAFF Must Not Browse Customers

This invariant is critical.

Staff operational access to Customers occurs only through the business resources needed for their jobs, such as:

```text
Request
Enquiry
Order
```

where those resources expose the minimum contact data necessary.

Staff must NOT gain:

```text
GET /api/v1/users
GET /api/v1/users/{user}
```

access merely because they can process Requests or Enquiries.

Test this explicitly.

---

# 8. Customer Self-Service Remains `/me`

Do not weaken:

```text
GET /api/v1/me
PATCH /api/v1/me
```

Those remain the sole self-service identity boundary.

A Customer must not use ADM-009 to retrieve themselves or another Customer.

A Staff member must not use ADM-009 as an alternate `/me`.

Admin customer visibility is separate from self-service.

Do not add:

```text
/me?user_id=...
/me?as_user=...
```

or similar identity substitution.

---

# 9. Resource Identity

Use the existing opaque User identifier:

```text
user_...
```

for ADM-009.

Do not expose or accept raw database primary keys as the public identifier.

Do not resolve administrative user detail using:

```text
email
Clerk subject
phone
numeric DB ID
```

in the URI.

Canonical detail lookup is:

```text
/users/{user}
```

with the project's opaque User identity.

---

# 10. Customer Administrative Representation

Create or reuse a dedicated administrative Customer/User resource if required.

Do NOT blindly reuse the Eloquent User model serialization.

The response must be explicit and allow-listed.

The safe administrative Customer representation should remain limited to already-approved User/Profile fields such as:

```text
id
role
name
email
phone
email_verified
created_at
updated_at
```

where those fields exist in the frozen contract/current implementation.

Because this Phase is CUSTOMER-only:

```text
role = CUSTOMER
```

for every returned ADM-008/009 Customer.

Do not add speculative business fields.

---

# 11. Never Serialize Secrets

Even Admin must NEVER receive:

```text
clerk_user_id
password
password_hash
password digest
authentication token
refresh token
session token
reset token
verification token
provider secret
API credential
Clerk secret
security answer
raw permission assignments
internal RBAC pivot rows
payment credentials
database IDs
```

Admin authorization is not permission to expose authentication internals.

This must have regression coverage.

---

# 12. Clerk Boundary

Clerk remains credential and identity-verification authority.

Laravel remains local business projection/RBAC authority.

ADM-008/009 must NOT call Clerk per row to construct collection results.

Avoid:

```text
N Customer rows
→ N Clerk API requests
```

The local User projection must be sufficient for normal administrative list/detail.

If some frozen response field is derived from a synchronized Clerk snapshot, use the existing local snapshot.

Do not turn ADM-008 into a remote Clerk directory proxy.

---

# 13. No Credential Management

Phase 11.8 does not allow Admin to:

```text
change Customer password
reset Customer password
change Customer Clerk subject
view Customer Clerk session
revoke Customer session
change Customer verified email
mark Customer email verified
```

Credential/security operations belong to Clerk or separately approved security workflows.

Do not implement them here.

---

# 14. No Customer Suspension or Blocking

The frozen V1 business rules deliberately prohibit generic:

```text
Staff → block Customer
```

and no approved Customer account restriction workflow exists in Phase 11.8.

Therefore do NOT add Customer:

```text
suspended
blocked
banned
disabled
ordering_disabled
browse_disabled
```

mutation behavior.

Do not reuse Staff suspension (`ADM-005`) for Customers.

Staff lifecycle state and Customer ownership are separate domains.

---

# 15. No Impersonation

Absolutely no:

```text
Login as Customer
Act as Customer
Generate Customer token
Switch identity
Assume Customer session
```

functionality.

Do not issue authentication credentials from ADM-008/009.

Admin visibility is read-only.

---

# 16. No Customer Role Changes

ADM-008/009 are reads.

Do not add any role-changing behavior.

Customer role must not be promoted to:

```text
STAFF
ADMIN
```

through this phase.

Staff provisioning/approval remains the dedicated Staff lifecycle.

Do not implement:

```text
PATCH /users/{user} {"role":"STAFF"}
```

or equivalent.

---

# 17. ADM-008 — Customer Collection

Implement/complete:

```text
GET /api/v1/users
```

as a paginated Admin-only Customer collection.

Every row must satisfy:

```text
effective role = CUSTOMER
```

Do not list:

```text
STAFF
ADMIN
```

through this endpoint.

---

# 18. Pagination

ADM-008 must follow standard V1 collection conventions.

Use:

```text
page
per_page
```

Maximum:

```text
100
```

Response:

```json
{
  "data": [],
  "meta": {
    "pagination": {
      "current_page": 1,
      "per_page": 20,
      "total": 0,
      "last_page": 1,
      "has_next": false,
      "has_previous": false
    }
  }
}
```

Use existing project helpers/resources where available.

Do not invent:

```text
pageSize
page_size
limit
offset
cursor
```

aliases.

---

# 19. Deterministic Ordering

ADM-008 must have stable deterministic pagination.

Inspect current conventions and existing collection patterns.

Unless an already-frozen ADM-008 sort is explicitly documented, use a simple server-controlled deterministic order consistent with existing User collection conventions, for example:

```text
created_at DESC
id ASC
```

Do not expose arbitrary SQL sort fields.

Do not add a generic `sort` API unless already frozen.

Document whichever existing canonical order is used.

---

# 20. Search / Filter Contract — Do Not Invent Broad Query Surface

Inspect OpenAPI and the current ADM-008 stub before implementing filters.

Only implement query parameters already supported by the frozen V1 contract.

Do not casually introduce:

```text
role
account_state
has_orders
total_spend
last_order_at
city
verified
registration_source
staff_status
```

filters.

Because Phase 11.8 itself already scopes ADM-008 to Customers, a `role` filter is unnecessary.

If the frozen OpenAPI currently defines no Customer-search parameter:

```text
do not invent one merely for Admin convenience
```

and use pagination-only collection.

If an already-frozen search parameter exists, implement it exactly.

Unknown query parameters must follow the project's strict validation convention rather than being silently ignored.

---

# 21. Search Privacy, If Already Frozen

If repository inspection proves ADM-008 already has an approved search field, constrain it to the documented fields only.

Do not build broad:

```text
LIKE %input%
```

across unrelated columns.

Do not search:

```text
Clerk IDs
internal IDs
tokens
permissions
security fields
```

Normalize and escape search values using existing query-building conventions.

Do not introduce enumeration-prone public behavior; the endpoint is Admin-only.

---

# 22. ADM-009 — Customer Detail

Implement/complete:

```text
GET /api/v1/users/{user}
```

Requirements:

```text
authenticated
users.manage_authorized
opaque user_... resolution
Customer target only
private response
```

A target that is not a Customer must not become visible through this customer-management endpoint.

Do not redirect to Staff management.

Do not return an Admin/Staff User representation.

Use safe not-found masking consistent with the existing administrative contract.

---

# 23. Non-Customer Target Behavior

Because Group K separates:

```text
Customer visibility
from
Staff lifecycle
```

ADM-009 must not expose Staff/Admin identities.

For:

```text
/user/{staff-id}
/user/{admin-id}
```

through ADM-009, return the canonical not-found behavior rather than leaking that the principal exists but has another privileged role.

Prefer:

```text
404 RESOURCE_NOT_FOUND
```

or the existing canonical equivalent.

Do not return:

```text
403 "That user is Staff"
```

because that unnecessarily reveals role/existence information.

Use existing error registry wording.

---

# 24. Do Not Join Customer History Into the User Resource

ADM-008/009 must remain lightweight account visibility.

Do not embed:

```text
orders[]
cart
requests[]
enquiries[]
notifications[]
payments[]
saved_addresses[]
delivery_addresses[]
reviews[]
```

Customer business resources have their own APIs and authorization.

This also avoids:

```text
N+1
huge payloads
cross-domain leakage
```

---

# 25. Do Not Calculate CRM Metrics

Do not add speculative fields such as:

```text
total_orders
total_spent
average_order_value
lifetime_value
last_purchase
conversion_rate
request_count
enquiry_count
risk_score
customer_segment
```

unless already frozen elsewhere.

Phase 11.8 is not a CRM analytics phase.

---

# 26. Customer Profile Nullability

Preserve the current signup/profile reality:

```text
name may be null
phone may be null
email required
```

because customer signup is email/password only and phone belongs to later profile/contact flows.

Do not make ADM-008/009 fail because:

```text
name == null
phone == null
```

Serialize the frozen nullable representation correctly.

---

# 27. Email Verification

If `email_verified` is part of the existing User/Profile representation, it is:

```text
server-controlled
read-only
```

ADM-008/009 may display it if the contract permits.

Do not allow Admin to modify it in Phase 11.8.

Do not query Clerk live for every collection row merely to refresh it.

---

# 28. Account State

Inspect the current User model and frozen Admin/User resource before exposing any account-state field.

Do not invent a Customer account-state contract.

If a local state exists primarily for:

```text
Staff/Admin lifecycle
```

do not automatically expose or mutate it for Customers.

Only serialize fields explicitly approved for ADM-008/009.

---

# 29. Query Efficiency

ADM-008 must not introduce N+1 behavior.

If role membership is stored using Spatie RBAC tables:

```text
filter Customers efficiently at query level
```

rather than:

```text
load all users
→ call hasRole() once per row
→ filter in PHP
```

Pagination must occur **after authorized Customer scoping**, not before.

Conceptually:

```text
User query
→ constrain effective role = CUSTOMER
→ authorized dataset
→ deterministic order
→ paginate
→ resource serialization
```

Do not paginate all Users then remove Staff/Admin from the result.

That would produce incorrect totals/pages.

---

# 30. Effective Single-Role Invariant

V1 ordinary Users are intended to have one effective CLOSED role.

Phase 11.8 must use the existing role model rather than adding a `users.role` column.

Do not denormalize RBAC merely to simplify ADM-008.

If malformed historical data gives a User multiple roles, follow existing role-invariant handling.

Do not silently classify an Admin/Customer multi-role account as a normal Customer.

Add a defensive regression if relevant to existing domain rules.

---

# 31. Private Caching

ADM-008 and ADM-009 contain private personal information.

Responses must be:

```http
Cache-Control: private, no-store
Vary: Authorization
```

Do not place these responses behind public catalog caching.

Do not make them CDN-cacheable.

---

# 32. Data Minimization

Admin receives only what is required by the approved administrative User representation.

Admin access is not justification for returning every User column.

The serialization boundary must remain explicit.

Prefer a dedicated resource such as the repository's existing administrative User representation.

Do not return:

```php
return User::all();
```

or:

```php
return $user->toArray();
```

if that can expose unreviewed fields.

---

# 33. No Write Audit Requirement From Reads

ADM-008/009 are read-only.

Do not create an `audit_event` for every routine Customer list/detail read unless the existing frozen contract explicitly requires read-access auditing.

Do not expand the audit enum merely for this phase.

Phase 11.13 owns Audit visibility.

Normal infrastructure/security access logs are separate from domain mutation audit.

---

# 34. ADM-007 Boundary

Do not implement:

```text
GET /api/v1/admin/audit-logs
```

during Phase 11.8.

The known:

```text
audit.view
```

reconciliation remains owned by Phase 11.13.

Do not use Phase 11.8 as a reason to resolve ADM-007 early.

---

# 35. Staff Lifecycle Boundary

Do not refactor or broaden:

```text
ADM-001
ADM-002
ADM-003
ADM-004
ADM-005
ADM-006
```

except where a narrowly required regression fix is uncovered.

Customer management must not alter:

```text
Staff approval
Staff suspension
Staff reactivation
Admin bootstrap
```

semantics.

---

# 36. Current Request-First Production Boundary

The release remains request-first.

Phase 11.8 must not reactivate:

```text
checkout
orders
payments
delivery
```

Customer visibility is useful for:

```text
Customer account identification
Request/Enquiry administration
support context
```

without turning transactional commerce back on.

---

# 37. Required Authorization Tests

Add/retain permanent tests proving:

```text
anonymous
GET /users
→ 401

anonymous
GET /users/{customer}
→ 401

Customer
GET /users
→ 403

Customer
GET /users/{customer}
→ 403

Staff
GET /users
→ 403

Staff
GET /users/{customer}
→ 403

Admin with users.manage_authorized
GET /users
→ 200

Admin with users.manage_authorized
GET /users/{customer}
→ 200
```

Also prove:

```text
authenticated Admin missing users.manage_authorized
→ 403
```

if the test infrastructure allows constructing that permission state.

---

# 38. Required Customer-Only Scope Tests

Create:

```text
Customer A
Customer B
Staff
Admin
```

Then assert ADM-008 returns:

```text
Customer A
Customer B
```

and excludes:

```text
Staff
Admin
```

Pagination totals must count only Customers.

This is essential.

---

# 39. Required Detail Masking Tests

Prove:

```text
GET /users/{customer}
→ 200
```

but:

```text
GET /users/{staff}
→ 404

GET /users/{admin}
→ 404

GET /users/{unknown}
→ 404
```

using canonical error envelopes.

Do not leak target role through different error messages.

---

# 40. Required Serialization Tests

Assert the permitted Customer response includes only approved fields.

Explicitly verify absence of:

```text
clerk_user_id
password
password_hash
remember_token
permissions
tokens
sessions
role pivot internals
security metadata
internal DB IDs
```

Do not rely only on snapshot inspection.

Use explicit negative assertions for sensitive fields.

---

# 41. Nullable Profile Tests

Create a Customer with:

```text
name = null
phone = null
```

and prove:

```text
ADM-008
ADM-009
```

serialize the Customer successfully.

Do not manufacture placeholder values like:

```text
Unknown
N/A
-
```

at the backend contract layer.

---

# 42. Pagination Tests

Cover:

```text
default page
explicit page
per_page
maximum per_page
invalid page
invalid per_page
stable deterministic ordering
Customer-only totals
has_next
has_previous
```

Unknown query parameters should follow the existing strict-query validation convention.

---

# 43. N+1 / Query-Scaling Regression

Because ADM-008 may need RBAC role filtering, add a query-scaling regression where practical.

Prove increasing Customer count does not create one role/profile query per Customer.

The intended shape is bounded query growth.

Do not over-optimize prematurely, but prevent an obvious:

```text
foreach user
→ hasRole()
```

N+1 collection implementation.

---

# 44. `/me` Regression Tests

Ensure existing:

```text
GET /me
PATCH /me
```

semantics remain unchanged.

Test that introducing ADM-008/009 does not make:

```text
Customer → another User
Staff → Customer User
```

possible through self-service.

No:

```text
user_id
as_user
account_id
```

selector should be accepted on `/me`.

---

# 45. Clerk Regression

Ensure ADM-008 collection does not require one Clerk call per Customer.

If the current test architecture can fake/spy on the Clerk gateway, assert:

```text
ADM-008 normal list
→ zero per-row Clerk network resolutions
```

Use local authoritative projections.

Do not modify JIT provisioning behavior merely for this phase.

---

# 46. Route Surface Regression

Canonical routes:

```text
GET /api/v1/users
GET /api/v1/users/{user}
```

must exist.

Ensure these do NOT appear:

```text
POST   /api/v1/users
PATCH  /api/v1/users/{user}
PUT    /api/v1/users/{user}
DELETE /api/v1/users/{user}

GET /api/v1/admin/users
GET /api/v1/admin/customers
GET /api/v1/customers

POST /api/v1/users/{user}/block
POST /api/v1/users/{user}/suspend
POST /api/v1/users/{user}/impersonate
POST /api/v1/users/{user}/role
```

No aliases.

---

# 47. OpenAPI

Inspect and complete the frozen definitions for:

```text
ADM-008
ADM-009
```

Ensure OpenAPI agrees with runtime for:

```text
paths
methods
security
ADMIN actor
users.manage_authorized purpose
pagination
query parameters
response envelope
User/Customer administrative resource
opaque user identifier
401
403
404
private semantics where documented
```

Do not add write schemas.

If ADM-008/009 are still marked:

```text
PROPOSED
```

and Phase 11.8 successfully completes the frozen implementation/reconciliation, mark them:

```text
APPROVED
```

consistently across authoritative documentation.

---

# 48. Document the Customer-Only Reconciliation

Add an ADR documenting why the frozen `/users` endpoints are Customer-scoped in Group K.

Suggested decision:

```text
ADR/GROUP-K-CUSTOMER-VISIBILITY
```

or next repository-consistent ADR identifier.

Record:

```text
ADM-008/009 remain canonical /users paths.

Group K assigns them to Customer account visibility.

Only CUSTOMER targets are visible.

Staff/Admin identities remain under their own
self-service / staff-lifecycle boundaries.

ADM-008/009 are read-only.

No Customer suspension, blocking, impersonation,
credential management, role mutation, or deletion is added.
```

This is a reconciliation of ambiguous historical wording, not a new endpoint family.

---

# 49. Code Structure

Use established project patterns.

Likely structure may include, depending on current repository:

```text
AdminUserController or UserAdministrationController
ListCustomersRequest
CustomerAdministrativeResource
CustomerAdministrativeQuery
```

but do NOT create these mechanically if existing abstractions already fit.

Prefer:

```text
thin controller
query/service for authorized collection
explicit API Resource
central permission check
strict query validation
```

Avoid business logic in controllers.

---

# 50. Database / Schema

Expected:

```text
schema changes = NONE
```

Do not add:

```text
customer_status
is_blocked
is_banned
customer_role
customer_notes
CRM tables
```

No migration should be necessary.

If implementation appears to require schema expansion, stop that expansion and report the mismatch rather than inventing new state.

---

# 51. Dependencies

Expected:

```text
new dependencies = NONE
```

Do not add:

```text
CRM package
admin package
search engine
Elasticsearch
Meilisearch
Scout
```

for this phase.

Existing Laravel/Eloquent/RBAC capabilities are sufficient.

---

# 52. Security Review

Explicitly verify:

```text
IDOR resistance
Customer/Staff denial
opaque identifier use
Customer-only scoping
no credential exposure
no Clerk subject exposure
no unrestricted User model serialization
private caching
strict query allow-list
no SQL injection through search/sort
pagination over authorized scope
no role enumeration through ADM-009
```

---

# 53. Verification

Run the repository's canonical equivalent of:

```bash
cd backend/laravel

php artisan test

./vendor/bin/phpstan analyse
./vendor/bin/pint --test
composer audit

php artisan route:list

git diff --check
```

Also run focused:

```text
ADM-008 tests
ADM-009 tests
User/Profile tests
RBAC tests
Admin Staff lifecycle regression
Clerk/local-projection regression
OpenAPI/route contract regression
```

No MariaDB concurrency gate is required merely for read-only Customer visibility unless repository behavior introduces DB-lock-sensitive semantics—which it should not.

---

# 54. Git Operations

After implementation and all required verification pass, use the root:

```text
git-workflow-and-versioning
```

skill for every Git action.

The project owner explicitly authorizes the agent to perform the Git operations permitted by that skill.

Do not perform Git actions before reading it.

Follow its exact workflow for:

```text
status inspection
branch handling
staging
commit creation
version updates if applicable
push if applicable
```

Do not invent a commit message convention outside the skill.

Do not commit:

```text
.env
credentials
R2 secrets
Clerk secrets
database credentials
generated secret material
```

If the skill says a certain operation requires owner confirmation, obey the skill.

The user's general Git authorization does not override explicit safety gates inside the skill.

---

# 55. Out of Scope

Do NOT implement:

```text
Phase 11.9 Request Management
Phase 11.10 Enquiry Management
Phase 11.13 Audit Visibility

Phase 11.7 Orders
Phase 11.11 Payments
Phase 11.12 Delivery
```

11.7/11.11/11.12 remain deferred.

Also exclude:

```text
Customer mutation
Customer deletion
Customer suspension
Customer blocking
Customer banning
Customer impersonation
role mutation
credential reset
email change
verification override
session revocation
marketing segmentation
CRM
analytics
customer notes
saved addresses
support ticketing
bulk export
CSV export
frontend Admin screens
```

---

# 56. Completion Report

Return:

```text
Phase 11.8 status:
PASS / BLOCKED

ADM-008:
PASS / BLOCKED

ADM-009:
PASS / BLOCKED

ADM-008 CUSTOMER-only:
YES / NO

ADM-009 CUSTOMER-only:
YES / NO

Staff/Admin identities excluded from ADM-008:
PASS / FAIL

Staff/Admin target masking on ADM-009:
PASS / FAIL

users.manage_authorized enforced:
PASS / FAIL

Staff customer browsing:
REJECTED / FAIL

Customer customer-directory access:
REJECTED / FAIL

Customer mutation endpoints added:
NO

Customer suspension/blocking:
NO

Impersonation:
NO

Credential management:
NO

Role mutation:
NO

Secret/Clerk ID exposure:
NO

Private/no-store responses:
PASS / FAIL

Pagination:
PASS / FAIL

Deterministic ordering:
PASS / FAIL

N+1 regression:
PASS / FAIL

ADM-008/009 OpenAPI status:
APPROVED / BLOCKED

Schema changes:
NONE / <explain>

Dependency changes:
NONE / <explain>

Full PHPUnit:
<x> passed, <y> skipped

PHPStan:
PASS / FAIL

Pint:
PASS / FAIL

Composer audit:
PASS / FAIL

Route surface:
PASS / FAIL

OpenAPI:
PASS / FAIL

git diff --check:
PASS / FAIL

Git workflow skill read:
YES / NO

Git operations performed:
<exact actions>

Commit:
<hash/message or NONE>

Push:
<result or NONE according to skill>

Phase 11.9:
READY / BLOCKED
```

Also list:

```text
files changed
tests added/changed
documentation reconciliations
genuine defects found
security findings
```

---

# 57. STOP Condition

Phase 11.8 is PASS only when:

- ADM-008 and ADM-009 are implemented and canonical;
- both require `users.manage_authorized`;
- only Admin can use them;
- ADM-008 exposes Customers only;
- ADM-009 resolves Customers only;
- Staff/Admin targets are masked through ADM-009;
- Customer and Staff cannot browse Customer accounts;
- approved fields only are serialized;
- credentials, Clerk subject, permissions and secrets are never exposed;
- pagination is correct over the authorized Customer dataset;
- `/me` ownership remains unchanged;
- no Customer mutation/control surface is introduced;
- OpenAPI and docs agree with runtime;
- full backend verification passes;
- Git operations, if performed, followed the root `git-workflow-and-versioning` skill.

Then report:

```text
Phase 11.8 — PASS
Phase 11.9 — READY
```

Do not begin Phase 11.9 automatically.

**Git operations are authorized only through the root `git-workflow-and-versioning` skill. Follow that skill exactly.**

---

## Phase 11.8 Completion Record

**Status:** Implemented pending final verification.

- `ADM-008` and `ADM-009` are canonical, Admin-only, permission-gated Customer visibility reads.
- Customer-only SQL scoping excludes Staff/Admin identities and masks non-Customer detail targets with `404 RESOURCE_NOT_FOUND`.
- The administrative response is allow-listed, opaque-identifier based, paginated, deterministic, private/no-store, and uses local projections only.
- No schema or dependency changes; no Customer mutation, restriction, credential, impersonation, role, or CRM capability was added.
