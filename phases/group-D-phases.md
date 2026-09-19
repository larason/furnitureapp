# Phase 4.12 — Authentication / Authorization Test Completion and Group D Exit Review

## Purpose

Complete Group D by performing the final authentication and authorization test pass, fixing only defects discovered within Group D scope, and verifying that the backend is ready for later commerce and frontend phases.

This phase is primarily:

```text
test
verify
review
fix regressions
document final state
```

It is **not** a phase for introducing new authentication architecture.

Group D should exit with this stable boundary:

```text
Clerk
    ↓
authenticated identity/session
    ↓
Laravel token verification
    ↓
local users.clerk_user_id mapping
    ↓
Laravel User
    ↓
CUSTOMER / STAFF / ADMIN
    ↓
permissions
    ↓
policies / ownership
    ↓
domain operation
```

---

# 1. Group D Exit Objective

At the end of Phase 4.12, the backend must have a proven authentication and authorization foundation for:

```text
CUSTOMER
STAFF
ADMIN
```

with:

```text
Clerk-owned authentication
Laravel-owned RBAC
Laravel-owned authorization
Laravel-owned profile/business state
```

The next backend groups should not need to redesign authentication.

---

# 2. Read the Latest Project Docs First

Before testing or modifying code, read the current repository versions of:

```text
AGENTS.md
docs/VISION.md
docs/api/api-contract.md
docs/api/api-resources.md
docs/api/api-conventions.md
docs/api/openapi.yaml
docs/domain/business-rules.md
docs/decisions.md
```

Also inspect the implementation/results from:

```text
Phase 4.1
Phase 4.2
Phase 4.3
Phase 4.4
Phase 4.5
Phase 4.6
Phase 4.7
Phase 4.8
Phase 4.9
Phase 4.10
Phase 4.11
```

The latest repository documentation is authoritative.

Do not rely on stale phase instructions if the current project docs deliberately supersede them.

---

# 3. Phase Scope

This phase covers:

* authentication regression testing;
* local user provisioning testing;
* Clerk identity mapping testing;
* email/password signup policy verification;
* email verification boundary testing;
* password recovery/security boundary verification;
* `/me` profile testing;
* RBAC testing;
* policy/ownership testing;
* Staff/Admin boundary testing;
* rate-limit testing;
* API error-contract verification;
* security regression review;
* static analysis;
* dependency/security audit;
* Group D documentation consistency;
* Group D exit report.

Only fix defects discovered in these areas.

---

# 4. Do Not Add New Features

Do not use Phase 4.12 to add:

```text
new authentication providers
social login
phone login
MFA product features
new roles
new profile fields
new permissions without an existing requirement
new customer management APIs
frontend authentication
Clerk webhooks unless already part of completed scope
```

If a missing feature belongs to a later group:

document it as deferred.

Do not pull it into Group D.

---

# 5. No Frontend Implementation

Do not modify:

```text
frontend/web/
frontend/app/
frontend/design-system/
```

during Phase 4.12.

This includes:

```text
sign-in UI
sign-up UI
verification UI
password recovery UI
route guards
ClerkProvider
Next.js middleware/proxy
Flutter AuthRepository
frontend role guards
```

Those remain later frontend implementation work.

Group D verifies the backend contracts those clients will consume.

---

# 6. Test Strategy

Use three layers:

```text
Unit tests
→ individual authentication/authorization services and policies

Feature/API tests
→ HTTP behavior, middleware, policies, errors

Integration-style tests
→ multiple Group D components working together
```

Do not turn every test into a full-stack test.

Keep tests focused.

---

# 7. No Live Clerk in Normal CI

Normal automated tests must not depend on:

```text
real Clerk account
real verification email
real password
real session
real Clerk API network call
```

Use the existing abstractions/fakes from Group D.

Examples may include:

```text
FakeClerkTokenVerifier
FakeClerkUserGateway
FakeClerkSessionGateway
```

or the project's actual equivalents.

---

# 8. Test Production Boundaries, Not Clerk Internals

Test:

```text
our token verifier integration contract
our User mapping
our middleware
our authorization
our errors
```

Do not test:

```text
Clerk's JWT cryptography implementation
Clerk's password hashing
Clerk's OTP algorithm
Clerk's email delivery system
```

Clerk owns those.

---

# 9. Authentication Test Matrix

Complete coverage for at least:

```text
missing token
malformed token
invalid signature
expired token
not-before violation
wrong issuer
unauthorized party / azp where configured
wrong token type where relevant
valid token
valid token with existing local User
valid token with first-time User
inactive local application account
provider unavailable during first-time provisioning
```

Use only checks actually applicable to the current verified Clerk integration.

Do not invent claims unsupported by the real implementation.

---

# 10. Missing Authentication

Protected route without credentials must return:

```text
401
AUTHENTICATION_REQUIRED
```

according to the current canonical error contract.

Do not expose provider-specific implementation details.

---

# 11. Invalid Authentication

Malformed or invalid Clerk token must return the project's approved authentication error.

Verify:

* no local User is provisioned;
* no domain operation executes;
* no raw JWT/parser exception is exposed.

---

# 12. Expired Session

Expired authentication must fail cleanly.

Expected semantics should match the current project contract, such as:

```text
SESSION_EXPIRED
```

or the currently documented equivalent.

Do not invent a new error code if the closed registry already defines one.

---

# 13. Valid Existing User

Given:

```text
valid Clerk sub
+
existing users.clerk_user_id
```

verify:

```text
same local User resolved
no duplicate User created
no Clerk API lookup on normal mapped hot path if architecture avoids it
```

Role and application profile remain unchanged.

---

# 14. First Authenticated Request / JIT Provisioning

Given:

```text
valid Clerk identity
+
no local User mapping
```

verify:

```text
Clerk identity validated
trusted Clerk User retrieved if required
local User created
CUSTOMER assigned
customer profile created where required
mapping persisted
request succeeds
```

The entire initial provisioning operation must satisfy the Phase 4.2 atomicity requirements.

---

# 15. Provisioning Idempotency

Repeat the same valid first-use scenario.

Verify:

```text
one local User
one mapping
one CUSTOMER role assignment
one profile
```

No duplicates.

---

# 16. Concurrent Provisioning

Test the concurrency behavior established in Phase 4.2.

Two concurrent attempts for the same:

```text
clerk_user_id
```

must not create duplicate local Users.

Use database uniqueness/transaction behavior rather than an unreliable in-memory lock.

---

# 17. Email Collision

Given a valid Clerk User whose email matches an existing unmapped Laravel User:

verify:

```text
NO automatic email-based account linking
```

The request must fail or enter the approved explicit migration/reconciliation path.

Never silently attach by email.

---

# 18. Stable Identity

Verify:

```text
email changes
+
same clerk_user_id
=
same local users.id
```

Email must never become the identity key.

---

# 19. Public Registration Role

First public provisioning must assign:

```text
CUSTOMER
```

only.

Never:

```text
STAFF
ADMIN
```

---

# 20. Existing STAFF Authentication

Given a local User already assigned:

```text
STAFF
```

successful authentication must preserve STAFF.

Do not reassign CUSTOMER on login.

---

# 21. Existing ADMIN Authentication

Same for:

```text
ADMIN
```

Authentication must not change the existing role.

---

# 22. Clerk Metadata Tampering

Create a trusted test identity containing metadata equivalent to:

```json
{
  "role": "ADMIN"
}
```

while Laravel User is:

```text
CUSTOMER
```

Verify authorization remains CUSTOMER.

Mandatory regression coverage.

---

# 23. Client Role Tampering

Requests containing:

```text
role
permissions
is_active
account_state
```

must not alter authority through ordinary signup/profile/API paths.

Use strict schema handling.

---

# 24. Signup Policy Review

Verify Group D consistently represents customer signup as:

```text
email
password
```

with Clerk email verification.

Phone must not be required for authentication registration.

---

# 25. Phone Regression Test

A valid customer with:

```text
phone = null
```

must be able to:

```text
authenticate
GET /me
use normal authenticated application APIs
```

where phone is not a business requirement.

---

# 26. Email Verification Boundary

Verify the architecture does not allow:

```text
client claims email_verified = true
```

to bypass Clerk.

Unverified/incomplete Clerk authentication must not become unrestricted authenticated Laravel access.

---

# 27. Laravel Verification Infrastructure Review

Confirm no active competing customer verification path remains using:

```text
Laravel verification tokens
verification links
verification notifications
```

unless current architecture explicitly retained one for a non-Clerk use case.

---

# 28. Password Boundary

Confirm Laravel does not:

```text
validate customer password
hash customer password
change customer password
reset customer password
issue password reset token
send password reset email
```

for Clerk-authenticated customers.

---

# 29. Password Recovery Boundary

Confirm old Laravel forgot/reset endpoints are:

```text
retired
absent
```

according to the current API contract.

Do not leave an undocumented second recovery route.

---

# 30. Pending Security State

If the current Clerk integration models pending/incomplete session security tasks:

verify protected application access remains denied until Clerk considers authentication complete.

Do not treat mere possession of a Clerk session object as full authorization if the provider state is incomplete.

---

# 31. `/me` GET

Test:

```http
GET /api/v1/me
```

for authenticated users.

Verify:

* correct local User;
* approved profile fields only;
* Laravel role;
* Clerk-owned email snapshot as appropriate;
* optional phone;
* no password;
* no secret;
* no raw Clerk identity unless explicitly contracted;
* no permission internals unless explicitly contracted.

---

# 32. `/me` Cache Control

Verify private profile responses use the current approved private cache policy.

Expected concept:

```text
private
no-store
```

Do not allow public/shared caching of `/me`.

---

# 33. `/me` PATCH

Test permitted fields only.

Current intended ordinary fields:

```text
name
phone
```

unless latest contract says otherwise.

---

# 34. `/me` Partial Update

Given:

```text
name = A
phone = P
```

PATCH only:

```json
{
  "name": "B"
}
```

must produce:

```text
name = B
phone = P
```

Omitted fields remain untouched.

---

# 35. Optional Phone Clearing

If current profile contract permits:

```json
{
  "phone": null
}
```

verify explicit clearing works.

Do not make phone mandatory again.

---

# 36. Profile Security Fields

Verify `/me` rejects attempts to change:

```text
email
email_verified
clerk_user_id
role
permissions
account state
timestamps
password
```

according to strict schema semantics.

---

# 37. Role Set

Verify the only V1 roles are:

```text
CUSTOMER
STAFF
ADMIN
```

No unexpected role variants exist.

---

# 38. Role Casing

Ensure canonical casing remains exact.

No duplicate role rows such as:

```text
customer
Customer
CUSTOMER
```

---

# 39. Role Seeder

Run role seeding repeatedly.

Verify idempotency.

Expected canonical roles remain exactly once.

---

# 40. Permission Seeder

Where canonical permissions exist:

verify repeated seeding does not create duplicate permissions or drift assignment unexpectedly.

---

# 41. Customer Self-Promotion

CUSTOMER must not elevate to:

```text
STAFF
ADMIN
```

through any API path.

---

# 42. Staff Self-Promotion

STAFF must not elevate to ADMIN.

No generic role PATCH should permit this.

---

# 43. Staff Approval Boundary

Verify:

```text
CUSTOMER → cannot approve Staff
STAFF → cannot approve Staff
ADMIN → may reach approved Staff-approval authorization boundary
```

Detailed Staff workflow may be implemented later if not yet part of existing API.

Test only current routes/contracts.

---

# 44. STAFF Customer Protection

Mandatory Group D exit invariant:

STAFF cannot ordinarily:

```text
change customer role
change customer permissions
suspend customer
delete customer
change customer email
change customer password/security
impersonate customer
```

If such APIs do not yet exist, confirm no route currently exposes the capability and preserve the rule in docs/tests where meaningful.

---

# 45. Authorization Test Matrix

Cover:

```text
unauthenticated
authenticated wrong role
authenticated missing permission
correct permission
wrong owner
correct owner
resource state invalid
private resource not found
private resource owned by someone else
```

Use current implemented resources only.

---

# 46. 401 Semantics

Verify:

```text
no valid authentication
→ 401
```

Do not return:

```text
403
404
```

for ordinary missing auth unless a specific documented endpoint intentionally behaves otherwise.

---

# 47. 403 Semantics

Verify authenticated actors lacking a permission receive:

```text
403 FORBIDDEN
```

where resource existence does not require masking.

Example:

```text
STAFF attempts ADMIN-only Staff approval
```

---

# 48. 404 Ownership Masking

For private Customer resources where existence must be hidden:

```text
Customer A requests Customer B resource
→ 404
```

not:

```text
403
```

according to Phase 4.10 and current API convention.

---

# 49. Own Order Test

CUSTOMER should be able to access own Order where operation permits.

Authorization succeeds before domain behavior.

---

# 50. Cross-Customer Order Test

CUSTOMER A must not access CUSTOMER B's Order.

No private data leakage.

---

# 51. Cross-Customer Cancellation

CUSTOMER A attempts cancellation of CUSTOMER B's Order.

Verify authorization/ownership fails before domain rules reveal:

```text
status
cancellation window
eligibility
```

---

# 52. Domain vs Authorization

Create at least one test where:

```text
actor is authorized
```

but:

```text
domain operation is invalid
```

Example:

```text
own Order
but cancellation no longer allowed
```

Verify:

```text
authorization passes
domain rejects
```

This proves layer separation.

---

# 53. STAFF Permission Test

A STAFF User with the required operational permission may reach the operation.

Same STAFF without permission must receive:

```text
403
```

Role alone must not authorize.

---

# 54. ADMIN Permission Test

ADMIN must still use the current explicit policy/permission model.

Do not assume:

```text
ADMIN → bypass everything
```

unless a specific current documented action explicitly defines such behavior.

---

# 55. Public Catalog Regression

Verify anonymous access still works for:

```text
products
categories
search
product detail
```

Authentication and policy middleware must not accidentally make public catalog private.

---

# 56. Anonymous Request / Enquiry Regression

Where the API permits anonymous:

```text
furniture requests
enquiries
```

verify they still work without Clerk authentication.

Do not accidentally require account registration.

---

# 57. Anonymous Record Claiming

Verify an authenticated customer cannot claim an old anonymous request/enquiry merely because:

```text
emails match
phones match
```

No automatic ownership inference.

---

# 58. Notification Ownership

Authenticated Customer must see only their own notifications.

Cross-user notification access must fail according to current masking rules.

---

# 59. Cart Ownership

Verify authenticated cart access is tied to the current local User.

Client-supplied:

```text
user_id
customer_id
```

must not redirect cart ownership.

---

# 60. Guest Cart Regression

Verify Group D auth changes did not break guest-cart identity model.

Do not implement guest-cart merge here unless already completed elsewhere.

---

# 61. Account State

If local:

```text
is_active
approval state
staff state
```

is enforced, test that:

```text
valid Clerk auth
+
inactive local account
```

does not automatically grant application access.

Authentication does not override application state.

---

# 62. Customer Account Closure / Retention

Verify Phase 4.6 retention decisions remain intact.

Do not hard-delete local commerce history merely because external identity disappears.

---

# 63. Cart FK Regression

If Phase 4.6 changed the cart-user FK policy:

test the intended behavior.

Authenticated cart must not silently degrade into an anonymous guest cart because a User row disappears.

---

# 64. Rate Limiting — Normal Usage

Verify normal customer behavior stays under limits.

Tests should confirm ordinary repeated reads/writes succeed below configured thresholds.

Do not only test failure.

---

# 65. Rate Limiting — 429

Exceed a controlled/test rate limit.

Verify:

```text
429
canonical RATE_LIMITED error
Retry-After header
```

---

# 66. `Retry-After`

Mandatory Group D regression:

every application-generated 429 must include:

```http
Retry-After
```

using the current documented semantics.

---

# 67. User-Based Rate Isolation

Authenticated Customer A exhausting a user-keyed limiter must not consume Customer B's quota.

---

# 68. Shared IP Fairness

Two authenticated Users sharing one IP should remain independently limited where Phase 4.11 selected user-based limiting.

This verifies reasonable NAT behavior.

---

# 69. Role Does Not Bypass Rate Limit

STAFF and ADMIN remain rate-limited where the endpoint limiter applies.

No universal privileged bypass.

---

# 70. Rate Limit Does Not Replace Authorization

Below rate limit:

```text
unauthorized actor
→ still 403/404
```

A request being under quota must not affect policy evaluation.

---

# 71. Rate Limit Does Not Cause Logout

Verify 429 does not mutate authentication state or deactivate the User.

Rate limiting is temporary abuse control.

---

# 72. Error Contract Sweep

Run a focused API-error review for Group D paths.

Verify existing canonical responses for:

```text
401
403
404
422
429
503/provider failure where defined
```

match:

```text
docs/api/api-conventions.md
docs/api/openapi.yaml
```

Do not introduce new error codes casually.

---

# 73. No Provider Internals

Error bodies must never leak:

```text
Clerk secret
JWT
Authorization header
raw Clerk exception
Clerk Backend API payload
stack trace
JWKS internals
```

---

# 74. Logging Review

Inspect auth/security logging.

Allowed examples:

```text
request_id
local user ID
authentication outcome category
authorization denial category
limiter category
```

Avoid:

```text
session token
Authorization header
password
verification code
Clerk secret
full personal-data payload
```

---

# 75. Token Logging Regression

Search for common accidental patterns:

```text
Authorization
bearerToken
jwt
sessionToken
getToken
```

in logging/debug statements.

Do not remove legitimate implementation references.

Remove only unsafe logging.

---

# 76. Secret Configuration Review

Confirm:

```text
CLERK_SECRET_KEY
webhook secret if configured
other auth secrets
```

remain server-only.

No real secrets committed.

No frontend secrets introduced.

---

# 77. Mass Assignment Review

Review Group D controllers/services/models for dangerous patterns:

```php
$request->all()
$request->input()
Model::create($requestData)
$user->update($request->all())
```

where strict validated data is required.

Canonical:

```text
FormRequest::validated()
→ DTO/command
→ service
```

---

# 78. Server-Controlled Fields

Ensure client input cannot control:

```text
clerk_user_id
role
permissions
is_active
account state
email_verified
owner_id
actor_id
```

---

# 79. Query Authorization Review

Review private resource endpoints for patterns equivalent to:

```text
Model::find($id)
```

followed by late authorization.

Where privacy requires it, prefer authorization-aware query scopes/masking.

---

# 80. Policy Coverage Review

List all implemented protected V1 operations.

For each, verify one of:

```text
Policy
Gate
explicit authenticated-self boundary
```

provides authorization.

Do not leave unreviewed protected mutations.

---

# 81. Route Middleware Audit

Inspect current `/api/v1` routes.

Verify:

```text
public routes
optional-auth routes
protected routes
Staff/Admin operational routes
```

have the correct middleware.

Look especially for accidentally public mutation endpoints.

---

# 82. Optional Authentication

For routes intentionally allowing both anonymous and authenticated callers:

```text
no token
→ anonymous

valid token
→ authenticated actor

invalid supplied token
→ authentication error
```

Do not silently downgrade an invalid token into anonymous mode.

---

# 83. Current-User Binding

Verify downstream services/controllers consistently use:

```text
authenticated local User
```

rather than:

```text
Clerk user ID from request
email from request
user_id from body
```

---

# 84. No Duplicate Authentication Systems

Search for active use of:

```text
Sanctum customer tokens
Passport
custom JWT
Laravel password login
custom remember token
custom refresh token
```

If Clerk replaced them, confirm they are not active customer authentication paths.

Do not delete unrelated package functionality without verifying scope.

---

# 85. No Test Authentication Backdoor

Confirm production code has no:

```text
X-Test-User
X-Test-Role
debug login
bypass authentication
force user ID header
```

used only to make tests easy.

Tests must use dependency substitution or test helpers.

---

# 86. No Email-Based Linking

Search for User lookup patterns such as:

```text
User::where('email', ...)
```

inside authentication/provisioning linkage paths.

Email may be used for normal business queries where appropriate.

It must not link Clerk identity to local account automatically.

---

# 87. No Role From Clerk Metadata

Search for:

```text
publicMetadata
privateMetadata
unsafeMetadata
role claim
```

in Laravel role assignment.

Ensure no external metadata grants application privilege.

---

# 88. No Frontend Auth Dependencies

Confirm Group D did not accidentally add:

```text
@clerk/nextjs
Flutter Clerk packages
NextAuth
frontend auth state packages
```

during the backend phases.

Frontend implementation remains deferred.

---

# 89. Migration Review

Review all Group D migrations.

Confirm:

* historical migrations were not improperly rewritten;
* new migrations are deterministic;
* Clerk mapping is unique;
* optional profile fields align with final policy;
* no password/reset/security duplication remains;
* FK decisions from Phase 4.6 are represented correctly.

---

# 90. Fresh Database Test

Run only against an explicitly non-production, disposable database. Do not run this
against the normal local/staging/production database or any database containing
valuable data. The command must be preceded by an environment/database guard, for
example:

```bash
test "${APP_ENV:-}" != "production"
test "${DB_DATABASE:-}" = ":memory:" -o "${DB_DATABASE:-}" = "furnitureapp_test_disposable"
php artisan migrate:fresh --seed --force
```

For MySQL, create a uniquely named disposable database first and set
`DB_DATABASE` to that database for the command. Never use an unqualified
`migrate:fresh` command against a configured application database.

This must succeed.

A clean database must contain the expected:

```text
roles
permissions
seed/reference data
```

without authentication corruption.

---

# 91. Role Seed Verification

After fresh seed, verify:

```text
CUSTOMER
STAFF
ADMIN
```

are exactly the intended V1 roles.

No accidental duplicate roles.

---

# 92. Admin Credential Safety

Fresh seed must not create insecure production-style credentials such as:

```text
admin@example.com
password123
admin123
```

unless a test-only seeder is explicitly isolated from production.

---

# 93. Factory Review

Factories should support auth tests without introducing production behavior.

Useful states may include:

```text
customer()
staff()
admin()
```

plus synthetic Clerk IDs.

No real Clerk credentials.

---

# 94. ReferenceGenerator Separation

Do not mix unrelated deferred:

```text
OrderFactory reference
PaymentFactory reference
FurnitureRequestFactory reference
```

cleanup into Phase 4.12 unless Group D directly broke those tests.

Document as existing deferred work.

---

# 95. Catalog Deferred Schema Work

Do not pull:

```text
products.product_type
products.is_published
```

into Group D.

Those remain the assigned Group E phase.

---

# 96. Enum Case-Sensitivity Work

Do not solve broader MySQL CLOSED-enum validation here unless authentication/RBAC specifically requires a correction already within Group D.

The general application enum work remains assigned later.

---

# 97. MySQL Harness Issues

Do not attempt to repair all previously known MySQL-only test-harness failures merely to make Group D exit.

SQLite remains the canonical CI database unless the latest project docs now state otherwise.

If MySQL CI has since been formally introduced, follow the updated docs.

---

# 98. PHPUnit Suite

Run the full backend test suite:

```bash
php artisan test
```

Not only Group D tests.

Group D changes must not regress:

```text
schema
cart
orders
products
requests
enquiries
```

already present.

---

# 99. Focused Auth Suite

Also run focused auth/authorization tests separately if test organization supports it.

Examples:

```text
tests/Feature/Auth/
tests/Feature/Authorization/
tests/Unit/Policies/
```

Use actual repository structure.

---

# 100. Static Analysis

Run:

```bash
vendor/bin/phpstan analyse
```

or the repository's canonical PHPStan command.

Maintain configured level.

Do not add broad ignores to make the phase pass.

---

# 101. Formatting

Run:

```bash
vendor/bin/pint --test
```

If it fails because files need formatting:

apply Pint according to repository policy, then rerun.

Do not perform unrelated formatting churn across untouched areas.

---

# 102. Dependency Audit

Run:

```bash
composer audit
```

Review vulnerabilities.

If a vulnerability affects Group D dependencies:

fix/update where safe and compatible.

If unrelated or requiring major upgrade:

document it accurately instead of performing uncontrolled framework upgrades.

---

# 103. Composer Dependency Review

Confirm the Clerk backend integration uses the currently approved dependency from earlier phases.

Do not switch SDK/package during exit testing without a concrete defect.

---

# 104. Configuration Review

Check auth-related configuration for:

```text
issuer
authorized parties
JWKS
Clerk server credentials
rate limits
environment defaults
```

Do not print secret values.

Validate presence/schema, not secrets themselves.

---

# 105. Environment File Safety

Do not read or print actual secret environment values in the final report.

Inspect configuration wiring only.

Use:

```text
.env.example
config/*.php
```

where appropriate.

---

# 106. Route List Review

Inspect:

```bash
php artisan route:list
```

or equivalent.

Check for accidental active endpoints such as:

```text
Laravel customer login
Laravel customer registration
forgot password
reset password
email verification
generic role update
```

that should have been retired under Clerk.

---

# 107. No Duplicate Auth Routes

There should not be two independent paths for:

```text
login
registration
password recovery
email verification
```

for the same Customer authentication model.

Clerk owns them.

---

# 108. OpenAPI Security Review

For protected Laravel endpoints, verify OpenAPI consistently describes the approved bearer-token security model.

Do not describe Laravel password login or Sanctum if no longer active.

---

# 109. OpenAPI Role Enum

Where role is exposed, verify:

```yaml
CUSTOMER
STAFF
ADMIN
```

only.

---

# 110. OpenAPI `/me`

Confirm `/me` documentation matches final implementation:

```text
GET /me
PATCH /me
```

including writable vs read-only fields.

---

# 111. OpenAPI 429

Verify current API docs represent:

```text
429
Retry-After
```

according to project conventions.

Do not duplicate operational thresholds unnecessarily if docs treat them as configuration.

---

# 112. Documentation Consistency Audit

Search for contradictory statements involving:

```text
Laravel password auth
phone required at signup
name required at auth signup
Laravel email verification
role from Clerk
Sanctum customer auth
flat auth assumptions
```

Reconcile only against already-decided Group D architecture.

Do not rewrite unrelated documentation.

---

# 113. Final Authentication Ownership Statement

Docs should consistently reflect:

```text
Clerk:
identity
email/password authentication
email verification
password recovery
sessions/security

Laravel:
local User
application profile
RBAC
permissions
account state
ownership
authorization
commerce
```

---

# 114. Final Customer Signup Statement

Docs should consistently reflect:

```text
required:
email
password

verification:
Clerk email verification

phone:
not required
```

---

# 115. Final Role Statement

Docs should consistently reflect:

```text
CUSTOMER
STAFF
ADMIN
```

as the CLOSED V1 role set.

---

# 116. Final Staff Boundary

Docs and tests must preserve:

> STAFF operational authority does not grant customer-account administration.

This is a Group D exit requirement.

---

# 117. Final Admin Boundary

ADMIN is the highest V1 application role but does not bypass:

```text
validation
domain invariants
financial invariants
transactions
```

Authorization and business validity remain separate.

---

# 118. Future Website Handoff

Verify documentation clearly says the future website will use:

```text
Next.js
→ Clerk browser session
→ Clerk session token
→ Authorization Bearer
→ Laravel
```

Do not implement it here.

---

# 119. Future Flutter Handoff

Verify documentation clearly says future Flutter authentication will use:

```text
Flutter Clerk-compatible authentication adapter
→ Clerk session token
→ Authorization Bearer
→ Laravel
```

with the actual client integration selected later.

Do not implement it here.

---

# 120. No Client-Specific Backend Auth

Group D exit invariant:

Laravel should authenticate:

```text
valid Clerk-authenticated request
```

not distinguish:

```text
web user
mobile user
```

Identity mapping remains client-neutral.

---

# 121. Security Review Checklist

Before Group D closes, explicitly verify:

```text
[ ] no customer password stored/handled by Laravel
[ ] no password reset token generated by Laravel
[ ] no Laravel customer email verification flow
[ ] email not used as identity-link key
[ ] clerk_user_id unique and server-controlled
[ ] public signup → CUSTOMER only
[ ] existing STAFF/ADMIN preserved
[ ] Clerk metadata cannot grant role
[ ] /me cannot mutate security/RBAC fields
[ ] Staff cannot control Customer accounts
[ ] ownership checks are server-derived
[ ] private resources use correct masking
[ ] rate limits are moderate
[ ] Retry-After exists on 429
[ ] secrets/tokens are not logged
[ ] no test auth backdoor exists
[ ] no frontend implementation exists
```

Every item must either pass or be explicitly explained.

---

# 122. Defect Classification

If tests reveal a defect, classify it:

```text
Group D defect
→ fix now

later-domain feature missing
→ defer

frontend implementation missing
→ defer

production infrastructure concern
→ hand off to Group T/U

payment-specific issue
→ Group H
```

Do not expand Group D unnecessarily.

---

# 123. Fix Only Root Causes

When a test fails:

do not merely weaken the test.

Determine whether:

```text
implementation wrong
test assumption wrong
documentation outdated
```

Fix the authoritative source.

Do not modify expected behavior just to get green CI.

---

# 124. Avoid Test Overfitting

Do not add production branches such as:

```php
if (app()->environment('testing')) {
    bypassSecurity();
}
```

to satisfy tests.

Security behavior must remain representative.

---

# 125. Test Determinism

Ensure auth/authorization/rate-limit tests:

```text
do not depend on ordering
clear rate-limit state
use deterministic factories
use fake provider gateways
```

No intermittent network/provider dependence.

---

# 126. Test Performance

Keep the suite practical.

Do not create hundreds of repeated HTTP requests where a test-configurable rate limit can validate behavior faster.

Do not reduce coverage just for speed.

---

# 127. Code Quality Review

Inspect new Group D code for:

```text
duplicate authentication services
giant Clerk service
repeated permission strings
repeated role strings
complex policies
controller authorization duplication
```

Refactor only where clearly justified.

---

# 128. Cognitive Complexity

Maintain target:

```text
≤ 15
```

for Group D methods.

Do not suppress quality rules to exit the group.

---

# 129. Return Count

Preserve:

```text
≤ 3 return statements per function where practical
```

according to project engineering conventions.

Do not contort simple policies merely to obey this mechanically.

---

# 130. Comments

Keep comments minimal.

Document:

```text
security invariant
provider-specific reason
non-obvious decision
```

Do not narrate obvious code.

---

# 131. Schema Changes

Expected:

```text
NONE
```

during Phase 4.12.

If a Group D defect genuinely requires a migration:

* explain the defect;
* create a new migration;
* do not modify historical applied migrations;
* rerun fresh database tests.

---

# 132. Dependencies

Expected:

```text
NONE
```

unless a security defect requires updating an existing dependency.

Do not introduce new auth packages during exit review.

---

# 133. Frontend Changes

Required result:

```text
NONE
```

Group D remains backend/security architecture.

---

# 134. Git Rule

DO NOT:

```text
git add
git commit
git push
```

Do not stage files.

Do not create a commit.

The project owner handles Git operations.

---

# 135. Minimum Command Set

Run at minimum, with the destructive migration command guarded as described in
Section 90 and pointed at an isolated disposable non-production database:

```bash
test "${APP_ENV:-}" != "production"
test "${DB_DATABASE:-}" = ":memory:" -o "${DB_DATABASE:-}" = "furnitureapp_test_disposable"
php artisan migrate:fresh --seed --force

php artisan test

vendor/bin/pint --test

vendor/bin/phpstan analyse

composer audit
```

Also run relevant:

```bash
php artisan route:list
```

for the route/security review.

Use repository-specific scripts where they are the canonical equivalent.

---

# 136. Test Results Must Be Exact

The completion report must state exact results.

Examples:

```text
PHPUnit:
237 passed
0 failed
```

not:

```text
tests look good
```

Likewise report:

```text
PHPStan
Pint
Composer audit
migration
```

accurately.

---

# 137. Known Failures

If any tests remain failing:

do not declare Group D complete.

Classify each remaining failure as:

```text
Group D blocker
known unrelated pre-existing issue
environment/harness issue
```

Provide evidence.

Group D blockers must be fixed before exit.

---

# 138. Pre-Existing Harness Issues

If previously documented MySQL-only harness problems remain and SQLite is still canonical CI:

do not treat those as Group D failures unless current project docs changed that policy.

List them under known deferred infrastructure issues if relevant.

---

# 139. Final Group D Architecture Summary

At completion produce a concise architecture summary:

```text
Authentication:
Clerk

External identity:
Clerk user ID

Local identity:
Laravel users.id

Mapping:
users.clerk_user_id

Public role:
CUSTOMER

Other roles:
STAFF / ADMIN

Role authority:
Laravel

Authorization:
Laravel permissions + policies

Profile:
Laravel name/optional phone

Email/password/security:
Clerk

API credential:
Clerk session Bearer token

Rate limiting:
Laravel application API + Clerk credential protection
```

---

# 140. Final API Surface Summary

Report any authentication-related Laravel endpoints that remain active.

Clearly state that Clerk-owned operations are not represented by duplicate Laravel endpoints.

Also summarize:

```text
/me
protected application routes
optional-auth routes
public routes
```

at a category level.

---

# 141. Final Security Boundary Summary

Report that:

```text
frontend/user input
cannot choose identity
cannot choose owner
cannot choose actor
cannot choose role
cannot choose permissions
cannot mark email verified
cannot activate account state
```

All are server/provider-controlled.

---

# 142. Deferred Items Report

At minimum carry forward relevant remaining work, such as:

```text
frontend Clerk integration → later website phases
Flutter Clerk integration → later Flutter phases
payment auth interactions → Group H
admin customer management → Group K
production monitoring/WAF → Group T/U
Clerk webhook reconciliation → assigned later phase if still deferred
```

Use current `AGENTS.md` ownership if it has changed.

---

# 143. Do Not Automatically Continue

Phase 4.12 is the exit gate.

After completing the review, STOP.

Do not begin Group E.

---

# 144. Definition of Done

Phase 4.12 is complete only when:

* latest authoritative project docs were reviewed;
* all Group D phases are implemented consistently;
* Clerk is the sole customer authentication/security authority;
* Laravel is the sole application RBAC/authorization authority;
* customer signup remains email + password;
* phone remains optional;
* email verification remains Clerk-owned;
* Laravel does not handle customer passwords;
* Laravel does not provide duplicate customer recovery;
* `clerk_user_id` mapping is stable and unique;
* no email-based automatic linking exists;
* JIT provisioning is idempotent and concurrency-safe;
* public signup creates CUSTOMER only;
* STAFF and ADMIN roles survive authentication unchanged;
* Clerk metadata cannot grant application role;
* `/me` works and has strict writable fields;
* RBAC uses only CUSTOMER / STAFF / ADMIN;
* Customer ownership isolation and masking work for every private resource implemented in Group D;
* Staff operational permissions work;
* Staff cannot control Customer accounts;
* Admin-only boundaries work;
* policies distinguish authorization from domain validation;
* private resource masking behaves correctly for every private resource implemented in Group D;
* public catalog remains public;
* anonymous request/enquiry behavior remains valid;
* rate limiting is moderate and working;
* 429 responses include Retry-After;
* authentication/authorization errors match the V1 contract;
* secrets/tokens are not logged;
* no test authentication backdoors exist;
* no frontend implementation was introduced;
* fresh database migration/seed succeeds;
* full PHPUnit suite passes;
* Pint passes;
* PHPStan passes;
* Composer audit has no unresolved Group D blocker;
* documentation/OpenAPI are consistent with implementation;
* remaining deferred work is clearly assigned;
* exact command results are reported.

---

# 145. Group D Exit Criteria

Group D exits when the backend can securely support future clients through this stable contract:

```text
Client
    ↓
Clerk authentication
    ↓
Clerk session Bearer token
    ↓
Laravel
    ↓
authenticated local User
    ↓
CUSTOMER / STAFF / ADMIN
    ↓
permissions + policies
    ↓
domain operation
```

without requiring the frontend to understand backend authentication internals and without requiring Laravel to know whether the client is web or mobile.

---

# 146. Final Completion Report Format

## Phase 4.12A Coverage-Gap Review (2026-09-19, superseded)

**Group D status at review time: BLOCKED**

Coverage added in this pass:

- STAFF and ADMIN role preservation through the HTTP Clerk authentication boundary.
- Clerk role-metadata tampering cannot promote a CUSTOMER or access Admin routes.
- Missing subject, configured issuer mismatch, and invalid-signature verifier mappings.
- Authenticated-write rate limiting, per-user isolation, canonical `429` responses,
  `Retry-After`, and account-state preservation.
- Database identity uniqueness/idempotency regression coverage for JIT provisioning.
- Attachment upload stubs require authentication.

Verification:

- Focused coverage tests: 31 passed, 136 assertions.
- Existing full-suite baseline remains passing before this pass: 751 tests, 2,415 assertions.

Open gaps identified at that review:

- A true concurrent two-request JIT provisioning harness is not available in the
  canonical in-memory SQLite test environment; the current test proves the unique
  mapping and idempotent persistence invariant but is not a concurrency test.
- The production Clerk SDK owns cryptographic claim validation. The repository's
  injected verifier seam covers error mapping and application-level subject/issuer
  checks, but does not provide a deterministic local signed-token harness for
  `exp`, `nbf`, `aud`, `azp`, and token-type claims. These remain provider-bound
  coverage and are not claimed as complete here.
- Customer order, notification, request, enquiry, and cart controllers remain
  `501 NOT_IMPLEMENTED`; ownership/masking tests for those resources are deferred
  until their implemented handlers exist. Current `/me` profile ownership coverage
  remains active.

No frontend, schema, dependency, or Group E changes were made.

## Phase 4.12B Final JIT Concurrency Exit (2026-09-19)

**Group D status: PASS**

The JIT concurrency blocker is closed. A dedicated MySQL integration
harness creates an isolated temporary database, starts two independently connected
`pcntl_fork` workers behind a filesystem barrier, and runs the real
`LocalUserProvisioner` concurrently for the same Clerk identity. No production
test route or authentication bypass is used.

Results:

- Worker A: success, resolved the same local user as Worker B.
- Worker B: success, resolved the same local user as Worker A.
- Final matching users: 1.
- Final Customer profiles: 1.
- Final role: CUSTOMER only.
- Email snapshot: `concurrency@example.test`.
- Repeated concurrency runs: 10 consecutive passes.

The race exposed a narrow recovery issue: the losing transaction could query the
mapping before the winning transaction committed. `LocalUserProvisioner` now retries
the expected mapping lookup briefly after a provisioning transaction failure; other
failures remain errors. Existing unique `users.clerk_user_id` enforcement remains
unchanged.

The MySQL concurrency test is explicitly opt-in via
`RUN_MYSQL_CONCURRENCY_TESTS=true`. It is skipped in the canonical SQLite suite and
must run in a provisioned MySQL integration job. With the flag enabled, the focused
test passes with 1 test and 18 assertions.

Final verification:

- Focused concurrency and provisioning tests: 7 passed, 39 assertions.
- Canonical PHPUnit suite: 760 passed, 1 skipped, 2,462 assertions.
- Explicit MySQL concurrency test: 1 passed, 18 assertions.
- Pint: passed.
- PHPStan: 0 errors.
- Composer audit: no security advisories.
- Migration/seed rebuild: passed.
- `git diff --check`: passed.

Deferred by explicit boundary: provider-owned Clerk cryptographic internals and
ownership tests for resource controllers that remain `501 NOT_IMPLEMENTED`.
Those tests are mandatory acceptance criteria for the phases that implement the
corresponding resources and do not independently block Group D. No frontend,
schema, dependency, or Group E changes were made.

Resource-phase ownership handoff:

- Orders: own-order access, cross-customer masking, list scoping, and owner-ID tampering.
- Cart: authenticated current-customer ownership and owner-ID tampering.
- Notifications: own notification access and customer-scoped list results.
- Furniture requests: authenticated ownership, cross-customer masking, and owner-ID tampering.
- Enquiries: authenticated ownership, cross-customer masking, and owner-ID tampering.

Return:

## Group D status

```text
PASS
```

or:

```text
BLOCKED
```

Use `BLOCKED` only for an unresolved defect within the Group D scope. Resource
ownership and masking tests for controllers that are intentionally
`501 NOT_IMPLEMENTED` are deferred acceptance criteria for those resource
implementation phases and do not block the Group D exit. Provider-owned Clerk
cryptographic claim validation is likewise not recreated in this repository;
the integration boundary and error mapping are the Group D responsibility.

## Authentication

Summarize Clerk boundary and test results.

## Identity provisioning

Summarize mapping/JIT/concurrency results.

## Profile

Summarize `/me` and field ownership.

## RBAC

Summarize CUSTOMER / STAFF / ADMIN results.

## Authorization

Summarize ownership, permissions, masking, Staff/Admin boundaries.

## Rate limiting

Summarize limiter categories and Retry-After validation.

## Security review

List notable checks completed.

## Documentation

List files updated/reconciled.

## Schema changes

Expected:

```text
NONE
```

unless a defect required a justified migration.

## Frontend changes

Must state:

```text
NONE
```

## Test results

Exact counts/results.

## Deferred items

List later-phase ownership.

## Group D exit

Explicitly state whether the Group D exit criteria are satisfied.

---

# 147. STOP Condition

STOP after the Group D exit report.

Do not automatically begin:

```text
Group E
```

even if all tests pass.

Wait for the project owner to request the next phase.

DO NOT COMMIT, STAGE OR PUSH.

The project owner handles all Git operations.
