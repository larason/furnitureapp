# Phase 4.10 — Laravel Policies / Permissions / Ownership Authorization

## Purpose

Implement the Laravel authorization layer for authenticated application users.

Phase 4.9 established:

```text
CUSTOMER
STAFF
ADMIN
```

Phase 4.10 now answers:

> May this authenticated actor perform this specific action on this specific resource in its current state?

The authorization model must combine:

```text
authenticated User
+
role
+
permission
+
ownership / operational scope
+
resource state
+
action
=
authorization decision
```

Role alone is never enough.

This is a backend-only phase.

---

# 1. Dependencies

Required:

* Phase 4.1–4.8 complete;
* Phase 4.9 RBAC complete;
* Clerk authentication resolves to local Laravel User;
* CUSTOMER / STAFF / ADMIN roles work;
* canonical permissions infrastructure exists;
* public CUSTOMER provisioning works;
* `/me` works;
* role cannot be client-controlled;
* Staff cannot control customer accounts;
* Admin/Staff boundaries are documented.

Do not start if role resolution is still ambiguous.

---

# 2. Scope

Implement authorization for existing V1 backend operations using standard Laravel:

```text
Policies
Gates where appropriate
RBAC permissions
ownership checks
resource-state checks
404 masking where required
```

Focus on authorization.

Do not redesign domain workflows.

Do not build frontend guards.

Do not implement new APIs merely to demonstrate policies.

---

# 3. Core Authorization Pipeline

Preserve:

```text
Transport
→ Schema validation
→ Authentication
→ Authorization
→ Domain validation
→ Concurrency / Transaction
→ Persistence
```

Authorization must happen before protected business mutation.

Do not:

```text
load private resource
→ mutate
→ then check permission
```

---

# 4. Default Deny

Use:

```text
not explicitly authorized
→ deny
```

Do not infer access because:

```text
user is authenticated
user is STAFF
user is ADMIN
resource exists
frontend displayed the action
```

Every protected action needs a defined authorization path.

---

# 5. Standard Laravel Authorization

Prefer:

```text
Laravel Policies
```

for resource/action authorization.

Use Gates for non-resource/global operations where appropriate.

Examples:

```text
OrderPolicy
ProductPolicy
InventoryPolicy
FurnitureRequestPolicy
EnquiryPolicy
StaffPolicy
```

Use actual project domain names.

Do not create a custom authorization engine.

---

# 6. Policy Responsibilities

Policies should answer:

```text
may actor perform action?
```

Policies may consider:

* role;
* permission;
* ownership;
* account state;
* resource relationship;
* coarse resource state where authorization depends on it.

Policies should not perform:

* inventory mutation;
* order state transition;
* pricing calculation;
* payment processing;
* notifications;
* large domain workflows.

---

# 7. Authorization vs Domain Validation

Keep this distinction strict.

Example:

```text
CUSTOMER owns order
→ authorization allows attempting cancel
```

Then:

```text
order cancellation window expired
→ domain rejects cancellation
```

Do not place the entire cancellation algorithm inside `OrderPolicy`.

Likewise:

```text
STAFF has orders.accept
→ policy may allow access
```

but:

```text
order current status invalid for ACCEPTED transition
→ domain validation rejects
```

---

# 8. CUSTOMER Authorization Model

CUSTOMER access should usually require:

```text
authenticated CUSTOMER
+
owns resource
```

Examples:

```text
own profile
own cart
own orders
own request history
own enquiry history
own notifications
```

CUSTOMER must not see or modify another customer's private resources.

---

# 9. Customer Ownership Source

Ownership must come from server data.

Example:

```text
$order->user_id === $user->id
```

not:

```text
request.user_id
request.customer_id
query.user_id
```

Never trust ownership identifiers submitted by clients.

---

# 10. Own Profile

For:

```text
GET /me
PATCH /me
```

identity already derives from authentication.

Do not require a separately supplied customer ID.

This is the safest ownership model.

---

# 11. Own Cart

Authenticated cart operations must use the cart belonging to the authenticated local User.

Do not authorize:

```text
cart.user_id supplied by client
```

For guest carts, use the existing guest-token ownership rules separately.

Do not mix guest-cart bearer authority with authenticated User authority.

---

# 12. Own Orders

CUSTOMER may only access orders where:

```text
orders.user_id == authenticated user.id
```

or the exact existing ownership relation.

Do not allow CUSTOMER to query arbitrary order IDs and receive differential authorization information.

---

# 13. Order Detail Masking

For a private order belonging to another customer:

prefer:

```text
404 RESOURCE_NOT_FOUND
```

rather than:

```text
403 FORBIDDEN
```

where the existing contract requires ownership masking.

This prevents order enumeration.

---

# 14. Order Cancellation Authorization

Policy should answer:

```text
Is this Customer allowed to attempt cancellation of this Order?
```

Typical authorization criteria:

```text
authenticated
CUSTOMER or otherwise explicitly allowed actor
owns Order
```

Then domain logic handles:

```text
20-minute window
current order status
other cancellation rules
```

Do not duplicate cancellation timing logic inside policy unless needed only as authorization state.

---

# 15. Order History

CUSTOMER may list only own orders.

Prefer authorization-aware queries:

```text
where user_id = authenticated user.id
```

rather than:

```text
load all
→ filter afterward
```

Never fetch another customer's rows into a customer-visible response path.

---

# 16. Furniture Requests

For authenticated request history:

```text
CUSTOMER
→ own authenticated requests only
```

Anonymous furniture requests remain anonymous.

Do not automatically authorize access to an old anonymous request because:

```text
request.email == user.email
```

Email is not ownership proof.

---

# 17. Enquiries

Same rule:

```text
authenticated enquiry history
→ own linked enquiries only
```

Do not attach or expose anonymous enquiries by matching email.

---

# 18. Notifications

CUSTOMER must see only notifications owned by that local User.

Never authorize by:

```text
notification email
Clerk user ID in request
frontend user ID
```

Use local ownership.

---

# 19. STAFF Authorization Model

STAFF authorization should require:

```text
role = STAFF
+
specific permission
+
resource/action context
+
valid operational scope
```

Do not implement:

```text
if STAFF → allow all operational APIs
```

---

# 20. STAFF Operational Access

STAFF may later handle explicitly authorized operations such as:

```text
orders
inventory
catalog
delivery
requests/enquiries
```

only when corresponding permissions exist.

Example:

```text
STAFF
+
orders.accept
→ may attempt order acceptance
```

Domain rules still validate the transition.

---

# 21. STAFF Customer Protection

STAFF must never gain ordinary authority over:

```text
customer role
customer permissions
customer password/security
customer email identity
customer suspension
customer deletion
customer impersonation
```

Even if Staff can view customer contact information required to fulfill an order.

Operational access is not account ownership.

---

# 22. STAFF Order Visibility

STAFF may view operational order information if explicitly permitted.

Do not treat that as permission to:

```text
edit customer profile
view authentication data
change ownership
```

Serialize only information needed for the operational workflow.

---

# 23. STAFF Inventory Access

Inventory changes require explicit permission such as the approved equivalent of:

```text
inventory.manage
```

Authorization permits the operation.

Domain/service layer still validates:

```text
product/variant
quantity
location
stock invariant
transaction correctness
```

---

# 24. STAFF Catalog Access

If Staff has approved catalog permissions:

policy may allow those specific actions.

Do not automatically give Staff every catalog operation.

Use documented permission names only.

---

# 25. STAFF Cannot Approve STAFF

Staff approval remains:

```text
ADMIN-only
```

No Staff policy should authorize:

```text
staff.approve
```

unless the V1 model is formally changed.

---

# 26. ADMIN Authorization Model

ADMIN is the highest role but must still use explicit authorization.

Preferred:

```text
ADMIN
+
required permission
+
resource/action conditions
```

Do not use one universal:

```php
before() {
    return $user->hasRole('ADMIN');
}
```

to bypass every policy automatically.

That would defeat explicit authorization and domain controls.

---

# 27. Policy `before()` Use

If using Laravel Policy `before()`:

use cautiously.

Do not create a blanket ADMIN override unless explicitly intended and documented.

For this V1, prefer explicit permissions so privileged actions remain auditable and predictable.

---

# 28. Admin Staff Management

Admin-only actions may include:

```text
approve Staff
suspend Staff
reactivate Staff
manage Staff permissions
```

according to the existing contract.

Do not extend these into unrestricted Customer account control.

Customer-management APIs remain separately controlled.

---

# 29. Role + Permission Pattern

A typical policy pattern should conceptually be:

```text
if actor lacks permission
→ deny

if actor/resource relationship invalid
→ deny

otherwise
→ allow attempt
```

Keep policy methods small.

---

# 30. Avoid Repeated Role Checks

Where permissions already encode the approved action, do not unnecessarily duplicate:

```text
role == STAFF
&& permission == orders.accept
```

unless the role restriction itself is part of the invariant.

Use the cleanest existing RBAC mechanism.

---

# 31. Permission Is Not Domain State

Do not create permissions like:

```text
orders.accept.pending
orders.accept.processing
```

to encode order states.

Use:

```text
orders.accept
```

then let the domain validate whether the current state can transition.

---

# 32. Authorization-Aware Queries

Where possible, scope queries before loading private resources.

Examples:

```text
customer order:
Order::where('user_id', $user->id)
```

Operational Staff queries may use approved operational scope.

Avoid:

```text
Order::find($id)
→ afterward discover ownership mismatch
```

where query scoping provides stronger privacy.

---

# 33. Route Model Binding

Review Laravel route model binding for private resources.

Default model binding can reveal resource existence depending on error behavior.

For ownership-sensitive Customer routes:

use authorization-aware resolution or consistent masking.

Do not allow:

```text
existing other-user resource → 403
nonexistent resource → 404
```

when the contract requires existence masking.

---

# 34. 404 Masking

Apply 404 masking where private resource existence must not be disclosed.

Typical candidates:

```text
customer order detail
customer request detail
customer enquiry detail
other private customer-owned resources
```

Do not blindly use 404 for every authorization failure.

---

# 35. 403 Use

Use:

```text
403 FORBIDDEN
```

when:

* resource visibility itself is not sensitive;
* authenticated actor lacks permission;
* role/action denial is safe to reveal.

Examples may include:

```text
STAFF tries admin staff approval
CUSTOMER accesses clearly administrative endpoint
```

Use the existing contract.

---

# 36. 401 Use

Unauthenticated caller:

```text
401 AUTHENTICATION_REQUIRED
```

Never use 403 for missing authentication.

---

# 37. Private Resource Enumeration

Tests must verify attackers cannot enumerate:

```text
orders
private requests
enquiries
notifications
```

through status-code differences or detailed error messages.

---

# 38. No Authorization Through Validation Errors

Do not reveal resource details before authorization by returning:

```text
ORDER_NOT_CANCELLABLE
```

for another user's order.

Ownership/authz should be resolved first.

For private resources:

```text
other user's order
→ 404
```

before domain-specific cancellation details are exposed.

---

# 39. Authorization Order Example

Customer cancel:

```text
validate route/input
→ authenticate
→ resolve Order safely
→ ownership authorization
→ cancellation domain rules
→ transaction
```

Do not evaluate cancellation timing before confirming ownership if doing so could leak state.

---

# 40. No Controllers With Inline Authorization Everywhere

Avoid:

```php
if (!$user->hasRole(...)) ...
if ($order->user_id !== ...) ...
```

repeated across controllers.

Centralize resource authorization in Policies.

Controllers should call standard Laravel authorization APIs.

---

# 41. Standard Controller Pattern

Prefer something equivalent to:

```text
validate
authenticate
authorize
call service
return resource
```

not:

```text
controller
→ 60 lines of role/ownership/domain logic
```

---

# 42. Service Authorization Boundary

Do not assume controller authorization is always enough for sensitive reusable services.

If a service can be invoked from multiple entry points, ensure authorization expectations are explicit.

However, do not duplicate every policy check inside every domain service.

Use clear layer contracts.

---

# 43. Background Jobs

Jobs operating as system/internal workflows should not fake CUSTOMER/STAFF roles.

If jobs perform privileged system actions later:

define explicit trusted system execution paths.

Do not use:

```text
User::firstWhere(role = ADMIN)
```

as a fake actor.

Out of scope unless existing jobs require it.

---

# 44. API Ownership Fields

Clients must not control:

```text
user_id
customer_id
owner_id
created_by
actor_id
```

where ownership should derive from authenticated context.

Reject or ignore according to the frozen API contract.

Prefer rejection for strict write schemas.

---

# 45. CUSTOMER Create Ownership

When a Customer creates an authenticated resource:

```text
ownership
=
authenticated local User
```

Do not accept owner identifiers from body.

---

# 46. STAFF Action Actor

For Staff operational actions, actor identity comes from:

```text
authenticated local User
```

Audit/history records should use server-derived actor.

Never accept:

```text
performed_by
staff_id
```

from request as authority.

---

# 47. ADMIN Action Actor

Same for Admin:

```text
actor = authenticated Admin local User
```

Do not let clients nominate another Admin as actor.

---

# 48. Policies and Soft Deletes

If resources use soft deletes:

define whether deleted resources may be viewed/restored and by whom.

Do not accidentally expose soft-deleted customer resources through default queries.

Only implement restore authorization where such endpoint already exists.

---

# 49. Product Public Read

Public product/category reads remain:

```text
no authentication required
```

Do not introduce Policy requirements that block public catalog.

Publication/visibility rules belong to catalog domain/query logic.

---

# 50. Product Write

Administrative/Staff product writes should require explicit catalog permission.

Detailed product validation remains domain/service layer responsibility.

---

# 51. Inventory

Inventory reads/writes that are operational must use appropriate permission.

Public stock availability representation remains public catalog behavior.

Do not expose raw internal inventory just because public product availability exists.

---

# 52. Checkout

Checkout requires:

```text
authenticated application User
```

and appropriate customer-commerce eligibility.

Do not authorize checkout based on role string alone.

Later checkout service still validates:

```text
cart ownership
cart validity
inventory
fulfillment
pricing
```

---

# 53. STAFF / ADMIN Purchasing

If Staff/Admin may purchase products for themselves, permit this through explicit self-commerce policy where required.

Do not assign them CUSTOMER role merely for checkout.

Keep one-role V1 invariant.

---

# 54. Notifications

Notification read/update actions must be ownership-scoped.

A Staff role does not automatically permit reading Customer notifications.

Operational Staff notifications, if later supported, follow their own ownership/scope.

---

# 55. Requests and Enquiries Operational Access

If STAFF may review incoming furniture requests/enquiries:

require explicit operational permissions.

Do not imply that Staff can edit the Customer profile associated with them.

---

# 56. Anonymous Records

Anonymous request/enquiry records have:

```text
user = null
```

Do not create a fake ownership policy based on matching email/phone.

Operational Staff/Admin access may be permission-based.

Customer cannot automatically claim them.

---

# 57. Payment Authorization

Payment provider-specific implementation belongs to Group H.

Phase 4.10 should only preserve authorization boundary concepts where payment endpoints already exist.

Do not design payment-provider permissions now.

---

# 58. Delivery Authorization

Where delivery resources exist:

CUSTOMER may view only delivery information tied to their own Order.

STAFF may perform approved delivery operations where permission exists.

Do not make delivery ownership a separate client-controlled identity.

---

# 59. Order State Transitions

Policies authorize the actor/action.

Domain transition service validates:

```text
current state
requested transition
business preconditions
```

Do not place the entire transition graph into Policy classes.

---

# 60. Generic Status PATCH Prohibited

Do not authorize:

```http
PATCH /orders/{id}
{
  "status": "SHIPPED"
}
```

as a generic mutation.

Existing explicit action endpoints remain the preferred model.

Policies should correspond to explicit actions.

---

# 61. Explicit Actions

Examples:

```text
accept order
ship order
cancel order
set delivery fee
approve staff
adjust inventory
```

Each should have a clear authorization method.

Avoid generic:

```text
update()
```

policy methods for unrelated business-state transitions where explicit methods improve safety.

---

# 62. Policy Method Naming

Use meaningful actions:

```text
view
viewAny
updateProfile
cancel
accept
ship
adjustInventory
approve
suspend
```

according to existing project conventions.

Do not create vague:

```text
manageEverything()
```

methods.

---

# 63. Permission Constants

Centralize meaningful permission names.

Avoid repeated string literals in:

```text
policies
seeders
tests
controllers
```

Use existing enums/constants/support classes.

---

# 64. Permission Seeding

If Phase 4.10 introduces approved permissions not yet seeded:

update canonical permission seed data.

Keep seeding idempotent.

Do not invent speculative future permissions.

---

# 65. Policy Registration

Use Laravel's standard policy discovery/registration.

Do not create a custom global policy registry unless the framework/version requires explicit registration.

Follow current Laravel conventions.

---

# 66. No Frontend Work

Do not modify:

```text
frontend/web/
frontend/app/
frontend/design-system/
```

during Phase 4.10.

No:

```text
route guards
permission hooks
AdminOnly components
role-based menus
button hiding
```

Frontend authorization cues belong to later UI phases.

---

# 67. Frontend Is Advisory

Record for future frontend implementation:

```text
UI guards improve UX
Laravel policies provide security
```

Hiding a button is never authorization.

---

# 68. Policy Performance

Avoid unnecessary N+1 queries.

Use already-loaded relationships where safe.

Do not load large relationship graphs merely to authorize a simple ownership check.

---

# 69. No External Clerk Calls

Authorization should not call Clerk.

At this point:

```text
authenticated local User
```

already exists.

Policies use Laravel state only.

Do not fetch Clerk metadata during authorization.

---

# 70. No Email-Based Authorization

Never authorize based on:

```text
user.email == resource.email
```

for ownership.

Use persisted local relationships.

Email can change and is not ownership proof.

---

# 71. No Phone-Based Authorization

Likewise never authorize using phone-number equality.

Phone is contact data only.

---

# 72. No Client-Type Authorization

Do not grant different permissions because request came from:

```text
web
mobile
```

Same user + same operation should follow the same backend authorization rules.

---

# 73. Staff Operational Scope

If the project later introduces location/cafe/branch-specific operational scope, that belongs to an explicit domain model.

Do not infer Staff scope from frontend route or request parameters.

For current V1, use only already-defined operational scope.

---

# 74. Admin Is Not Domain Override

ADMIN authorization does not allow invalid business operations.

Example:

```text
ADMIN authorized to ship order
```

does not mean:

```text
CANCELLED → SHIPPED
```

becomes valid.

Domain invariants remain mandatory.

---

# 75. Validation Cannot Be Bypassed

All roles, including ADMIN, must still satisfy:

```text
schema validation
domain validation
transactions
financial invariants
```

Authorization is not input-validation bypass.

---

# 76. Security Tests — Cross-Customer Order View

Customer A requests Customer B's Order.

Expected:

```text
404
```

where masking applies.

No order data leaked.

---

# 77. Security Tests — Cross-Customer Order Cancel

Customer A attempts cancellation of Customer B's Order.

Expected:

```text
404
```

before cancellation-state details are revealed.

---

# 78. Security Tests — Own Order

Customer accesses own Order.

Authorization succeeds.

Then normal domain rules apply.

---

# 79. Security Tests — Staff Operational Order

STAFF with required permission:

may reach approved operational action.

STAFF without permission:

```text
403
```

---

# 80. Security Tests — Staff Customer Account

STAFF attempts customer-account control.

Expected:

```text
403
```

or no route exists.

Mandatory regression coverage.

---

# 81. Security Tests — Admin Staff Approval

ADMIN with required permission may reach Staff approval action.

Non-Admin actors denied.

---

# 82. Security Tests — Clerk Metadata Cannot Authorize

Fake Clerk metadata indicating ADMIN while local User is CUSTOMER.

Policy must treat actor as CUSTOMER.

---

# 83. Security Tests — Body Ownership Tampering

Send:

```json
{
  "user_id": "another-user"
}
```

where ownership is server-derived.

Ensure request cannot take ownership or access another user's data.

---

# 84. Security Tests — Query Ownership Tampering

Attempt:

```text
?user_id=other
```

on self-owned resources.

Ensure authorization remains bound to authenticated User.

---

# 85. Security Tests — Anonymous Protected Route

No auth:

```text
401 AUTHENTICATION_REQUIRED
```

---

# 86. Security Tests — Authenticated Forbidden Action

Valid auth but missing permission:

```text
403 FORBIDDEN
```

where masking is not required.

---

# 87. Security Tests — Enumeration

Probe sequential private resource identifiers as Customer.

Responses must not reveal which belong to other users.

Test status/code/message consistency.

---

# 88. Security Tests — Public Catalog

Anonymous public catalog remains accessible.

No authorization regression.

---

# 89. Security Tests — STAFF Role Does Not Imply All Permissions

STAFF without a specific operational permission must be denied.

This confirms:

```text
role != blanket authority
```

---

# 90. Security Tests — ADMIN Domain Rule

ADMIN authorized for action but invalid resource state.

Expected:

```text
authorization passes
domain rejects
```

This proves policy/domain separation.

---

# 91. Security Tests — Own Request / Enquiry

Customer may access own authenticated records.

Cannot access another customer's.

Anonymous historical record is not claimable merely by matching email.

---

# 92. Security Tests — Notifications

Customer sees only own notifications.

Staff/Admin do not automatically inherit customer notifications.

---

# 93. Test Organization

Prefer policy-focused unit tests plus API feature tests.

For example:

```text
tests/Unit/Policies/
tests/Feature/Authorization/
```

or existing project conventions.

Do not create a new testing structure if one already exists.

---

# 94. Policy Unit Tests

Policy tests should be small and deterministic.

Cover:

```text
allowed role/permission/ownership
denied role
missing permission
wrong owner
```

Do not test complete domain workflows in every policy unit test.

---

# 95. Feature Tests

Feature/API tests verify integration:

```text
route
→ auth
→ policy
→ error mapping
```

These are essential for:

```text
401
403
404 masking
```

---

# 96. Offline Tests

No real Clerk calls.

Use existing fake authentication/verifier setup.

Authorization tests begin with an already-authenticated local User.

---

# 97. No Test Backdoors

Do not add production headers like:

```text
X-Test-Role
X-Test-Permission
```

Use factories/container/test authentication utilities.

---

# 98. Factory States

Reuse:

```text
customer()
staff()
admin()
```

and permission helpers as needed.

Keep fixtures explicit.

---

# 99. Policy Complexity

Keep each policy method small.

If authorization becomes complicated:

extract narrowly named helper methods.

Maintain cognitive complexity target ≤15.

Do not build a generic authorization DSL.

---

# 100. Return Count

Follow project guidance of maximum 3 returns per function where practical.

Keep policies readable rather than overly clever.

---

# 101. Avoid Large `switch(role)`

Do not build every policy as:

```text
switch role:
 CUSTOMER ...
 STAFF ...
 ADMIN ...
```

when permissions/ownership provide cleaner composition.

Use standard Laravel authorization patterns.

---

# 102. No Global `isAdmin()` Shortcut Everywhere

An `isAdmin()` helper may exist for legitimate use, but do not turn it into a universal authorization bypass.

Prefer permissions/policies.

---

# 103. API Contract Review

Review:

```text
docs/api/api-contract.md
docs/api/api-resources.md
docs/api/openapi.yaml
```

for protected operations.

Ensure documented authentication/authorization requirements match implemented policies.

Do not change endpoint behavior casually.

---

# 104. Error Contract

Use existing CLOSED error codes.

Authorization failures should map to approved:

```text
AUTHENTICATION_REQUIRED
FORBIDDEN
RESOURCE_NOT_FOUND
```

or resource-specific not-found code where already defined.

Do not create:

```text
NOT_OWNER
INSUFFICIENT_ROLE
NEEDS_ADMIN
```

unless formally approved.

---

# 105. Error Messages

Do not reveal:

```text
resource exists but belongs to user X
required role is ADMIN
missing internal permission name
```

unless explicitly safe and contracted.

Keep client messages generic enough to avoid security leakage.

---

# 106. Logging

Authorization-denial logs may include:

```text
request_id
local actor ID
action
resource type
outcome
```

where useful.

Do not log:

```text
token
password
private resource payload
```

Avoid excessive logging of ordinary 403/404 probes unless monitoring requires it.

---

# 107. Auditing

Authorization denial is not the same as permanent audit.

Privileged successful actions may later require audit records.

Do not implement the entire audit subsystem here unless already present.

Carry forward:

```text
role changes
staff approval
inventory adjustments
order state changes
```

as audit-sensitive actions.

---

# 108. Documentation

Update consolidated docs only where necessary:

```text
docs/api/api-conventions.md
docs/domain/business-rules.md
docs/decisions.md
docs/api/api-contract.md
docs/api/api-resources.md
docs/api/openapi.yaml
AGENTS.md
```

Do not create excessive phase-specific policy documents.

---

# 109. Authorization Matrix

Maintain a concise implementation matrix.

Example structure:

| Resource      | Action           | CUSTOMER | STAFF                        | ADMIN      | Ownership / condition |
| ------------- | ---------------- | -------- | ---------------------------- | ---------- | --------------------- |
| Profile       | view/update self | Yes      | Self                         | Self       | current actor only    |
| Order         | view             | Own      | permission                   | permission | customer ownership    |
| Order         | cancel           | Own      | No unless explicitly defined | explicit   | domain checks later   |
| Order         | accept           | No       | permission                   | permission | operational           |
| Order         | ship             | No       | permission                   | permission | operational           |
| Inventory     | adjust           | No       | permission                   | permission | operational           |
| Staff account | approve          | No       | No                           | permission | Admin only            |

Use actual frozen operations and permissions.

Do not use this example to invent unsupported operations.

---

# 110. Authorization Matrix Is Authoritative Aid

The matrix helps implementation review.

Actual enforcement must remain in:

```text
Laravel Policies / Gates
```

Do not perform authorization by reading configuration tables dynamically unless already designed.

---

# 111. Policy Naming Consistency

Match policy action names to explicit API operations where practical.

Avoid ambiguity between:

```text
update
manage
modify
operate
```

Use action-oriented names.

---

# 112. Route Middleware

Use the configured Clerk bearer middleware:

```text
clerk.auth
```

for identity and standard authorization middleware. It resolves the verified
Clerk bearer credential before role, permission, ownership, and state checks.

Do not encode complex policy logic directly in route middleware strings if policies are cleaner.

---

# 113. Permission Middleware

Package-provided permission middleware may be used for coarse route gates where suitable.

Still use policies for:

```text
ownership
resource-specific authorization
state-aware conditions
```

Do not rely only on route permission middleware for private resources.

---

# 114. Query Scope vs Policy

Use query scoping to avoid loading inaccessible rows.

Use Policies to authorize actions.

These complement each other.

Do not treat one as complete replacement for the other.

---

# 115. Admin Listing Endpoints

Where Admin/Staff list operational resources:

scope data to the permitted operational dataset.

Do not reuse Customer ownership scopes.

Exact operational listing filters follow resource contracts.

---

# 116. Customer Listing Endpoints

Always constrain to authenticated Customer's own resources.

Do not accept arbitrary owner filters.

---

# 117. Resource Serialization

Authorization must happen before serialization.

Do not serialize private fields and then remove them after access denial.

---

# 118. Field-Level Authorization

Where Staff may view only operational fields, use explicit Resources/serializers.

Do not return full Customer/User models merely because Staff can process an Order.

Detailed field-level restrictions should follow existing resource contract.

---

# 119. Sensitive Customer Fields

Operational Staff visibility must not include:

```text
Clerk ID
security attributes
password fields
internal account state beyond need
permission internals
```

unless explicitly required.

---

# 120. Authorization and Transactions

Authorization generally happens before opening expensive mutation transactions.

Domain state must still be revalidated within transaction where race-sensitive.

Do not hold database locks while performing unnecessary authorization checks.

---

# 121. TOCTOU Awareness

For authorization based on mutable resource state:

re-check relevant domain state during transaction where required.

Do not assume policy evaluation permanently freezes resource state.

Keep policy coarse and domain transaction authoritative.

---

# 122. Account State

If local account state exists:

authorization layer must respect it according to prior phases.

Valid Clerk authentication does not override:

```text
suspended
inactive
pending
```

application state.

Do not encode these as roles.

---

# 123. Staff State

STAFF with suspended/inactive operational state must not perform Staff operations even if role and permission rows remain.

Integrate the approved staff-state check in the authorization boundary.

---

# 124. Admin State

Likewise, disabled Admin must not retain operational privileges merely because role remains ADMIN.

---

# 125. No Frontend Assumptions

Do not assume future UI will prevent invalid requests.

Backend must be secure against direct API calls.

---

# 126. No Security by URL

Do not assume:

```text
/admin/*
```

or:

```text
/staff/*
```

path itself grants authority.

Every protected endpoint still authenticates and authorizes the actor.

---

# 127. No Security by HTTP Method Alone

`POST` or `DELETE` does not imply privilege.

Policy must authorize the specific operation.

---

# 128. No Implicit Admin Through Seeder

Ensure test/production seeds do not accidentally assign Admin broadly.

Permissions and roles must be deterministic.

---

# 129. Schema Changes

Expected:

```text
NONE
```

unless a missing RBAC permission table/setup from earlier phases is genuinely discovered.

Do not add authorization-specific resource owner columns if ownership already exists.

---

# 130. Frontend Changes

Expected:

```text
NONE
```

Do not modify Groups L–Q frontend code.

---

# 131. Commands

Run:

```bash
php artisan test
vendor/bin/pint --test
vendor/bin/phpstan analyse
composer audit
```

If permission seed definitions changed:

```bash
php artisan migrate:fresh --seed
```

must also pass.

Use existing repository scripts where available.

---

# 132. Files Changed Report

At completion report:

## Policies added/updated

List exact policy classes.

## Gates

List any global/non-resource gates.

## Permissions

List only new/changed canonical permissions.

## Ownership rules

Summarize Customer-owned resources.

## Masking

List resources using 404 ownership masking.

## Staff boundary

Confirm operational access does not grant customer-account control.

## Admin boundary

Confirm Admin still uses explicit authorization.

## Schema

Expected:

```text
none
```

## Frontend

Must state:

```text
NONE
```

## Tests

List policy/feature tests and results.

---

# 133. Definition of Done

Phase 4.10 is complete when:

* authenticated Laravel User is the authorization actor;
* CUSTOMER / STAFF / ADMIN roles are integrated with policies;
* role alone never grants resource access;
* explicit permissions are used for operational/admin actions;
* Customer private resources are ownership-scoped;
* private cross-customer resource access is masked where required;
* Staff operational access is explicit;
* Staff cannot control Customer accounts;
* Staff approval remains Admin-only;
* Admin does not universally bypass domain/policy rules;
* Clerk metadata has zero authorization authority;
* email/phone are never ownership proof;
* ownership always comes from server relationships;
* client-supplied owner/actor IDs cannot grant access;
* policies stay separate from domain transition logic;
* unauthenticated requests produce 401;
* forbidden authorized users produce 403 where appropriate;
* private ownership failures produce 404 where required;
* public catalog remains public;
* authorization-aware query scoping is used where appropriate;
* no frontend authorization implementation is added;
* no duplicate authorization framework is created;
* policy tests pass;
* API authorization tests pass;
* full backend tests pass;
* Pint passes;
* PHPStan passes;
* Composer audit passes;
* documentation/OpenAPI match the implemented authorization model.

---

# 134. Out of Scope

Do not implement:

* frontend guards;
* role-based navigation;
* Staff/Admin UI;
* new roles;
* Clerk authorization metadata;
* generic policy engine;
* full audit subsystem;
* new business workflows;
* payment-provider authorization;
* new customer-management APIs;
* speculative operational scopes;
* wildcard Super Admin behavior.

---

# 135. STOP Condition

STOP when the backend consistently answers:

```text
Who is the actor?
→ authenticated local Laravel User

What kind of actor?
→ CUSTOMER / STAFF / ADMIN

Does the actor have the required capability?
→ Laravel permission

Does this actor own / have operational authority over this resource?
→ Policy

Is the business operation valid in the resource's current state?
→ Domain layer
```

with secure 401 / 403 / 404 behavior and all authorization tests passing.

Do not continue automatically.

The next backend roadmap phase is:

**Phase 4.11 — Authentication / Authorization Rate Limiting**
