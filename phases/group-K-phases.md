# Phase 11.2 — Admin Authentication

## 1. Objective

Implement and verify the **Admin authentication boundary** for the Group K backend.

Phase 11.2 must establish that:

```text
Clerk
= credential + identity + session authority

Laravel
= local User + ADMIN role + permissions + business authorization authority
```

An Admin is authenticated only when:

```text
valid Clerk session token
+
verified Clerk identity
+
matching local Laravel User
+
exact local ADMIN role
+
required explicit permission
+
valid local account state
```

A valid Clerk session by itself is never Admin authority.

---

# 2. Primary Goal

The intended Admin authentication flow is:

```text
Admin browser
    ↓
Clerk sign-in
    ↓
Clerk session token
    ↓
Authorization: Bearer <Clerk session token>
    ↓
Laravel Clerk verification
    ↓
resolve existing local User
    ↓
verify local ADMIN role/state
    ↓
verify endpoint permission
    ↓
Admin operation
```

Do not introduce:

```text
Laravel admin passwords
Laravel login tokens
Sanctum
Passport
custom JWT
admin session cookies
parallel Admin authentication
```

---

# 3. Frozen V1 Authentication Authority

Reuse all Group D authentication infrastructure.

Clerk remains sole authority for:

```text
email/password credentials
email verification
sign-in
sign-out
password recovery
password change
session lifecycle
session revocation
security-task sessions
```

Laravel must not receive or validate an Admin password.

---

# 4. Laravel Authority

Laravel remains authoritative for:

```text
local User identity mapping
CUSTOMER / STAFF / ADMIN role
PermissionCatalog
staff state
application account state
business authorization
ownership/context
audit
```

Clerk metadata must not become role authority.

---

# 5. Roles Remain CLOSED

Do not add:

```text
SUPER_ADMIN
OWNER
MANAGER
ROOT_ADMIN
SYSTEM_ADMIN
```

V1 roles remain:

```text
CUSTOMER
STAFF
ADMIN
```

Use permissions for finer authority.

---

# 6. Critical Admin Rule

A Clerk identity does not become Admin because:

```text
email matches owner email
Clerk metadata says ADMIN
user visits /admin
frontend claims ADMIN
request body says role=ADMIN
```

Only Laravel's trusted local role assignment is authoritative.

---

# 7. Public Registration Rule

Public Clerk signup remains:

```text
Visitor
→ Clerk signup
→ verified identity
→ Laravel JIT provisioning
→ CUSTOMER
```

It must never result in:

```text
STAFF
ADMIN
```

based on:

```text
email domain
query parameter
frontend route
Clerk unsafe metadata
request body
```

---

# 8. Admin Web Application Authentication UX Contract

The future Admin web application should expose:

```text
Sign in
Sign out
password recovery/security through Clerk
```

It should **not** expose a public:

```text
Register as Admin
Register as Staff
Choose role
```

flow.

Phase 11.2 is backend-focused; do not build the Next.js UI yet unless a tiny frontend integration prerequisite has already been explicitly assigned.

---

# 9. First Admin Bootstrap

The first Admin must be established through:

```text
real Clerk identity
+
controlled server/deployment-side local assignment
```

not through public role registration.

This is the bootstrap-of-trust problem.

There is no existing Admin yet who can authorize the first Admin.

Therefore bootstrap must be external to ordinary user-facing APIs.

---

# 10. Bootstrap Is Not an API Endpoint

Do not add:

```http
POST /api/v1/admin/register
POST /api/v1/admin/bootstrap
POST /api/v1/register-admin
```

An HTTP bootstrap endpoint unnecessarily expands the attack surface.

Prefer a controlled:

```text
Artisan command
deployment task
one-time server-side setup operation
```

consistent with the repository's operational practices.

---

# 11. Recommended Bootstrap Architecture

Implement a narrow internal bootstrap service such as conceptually:

```text
BootstrapInitialAdmin
```

plus a controlled Artisan command such as:

```text
php artisan admin:bootstrap ...
```

Names may follow repository conventions.

The command is an implementation mechanism, not a public V1 endpoint.

---

# 12. Bootstrap Input Authority

Do not bootstrap Admin merely by trusting an arbitrary email string.

Prefer the immutable Clerk identity:

```text
Clerk user ID / subject (`sub`)
```

as the primary identity key.

Use trusted Clerk-provided email only for consistency/diagnostic matching where the existing identity mapping requires it.

---

# 13. Inspect Existing Identity Mapping First

Before implementing bootstrap, inspect the actual Group D mapping:

```text
users table
Clerk identity columns
local identity projection
JIT provisioning service
OfficialClerkTokenVerifier
Clerk user/profile gateway
UserResolver
```

Reuse the canonical mapping.

Do not add another:

```text
admin_clerk_id
admin_users table
```

---

# 14. Bootstrap Existing Local User

If the real Clerk identity already maps to a local User:

the controlled bootstrap may assign:

```text
ADMIN
```

using the existing RBAC authority.

Do not duplicate the User.

---

# 15. Bootstrap Identity Not Yet Local

If the Clerk identity exists but has no local projection:

use the smallest trusted existing provisioning path.

Possible safe patterns, depending on current Group D implementation:

```text
A. trusted server-side Clerk identity lookup
   → create local projection
   → assign ADMIN

or

B. identity authenticates once
   → normal local projection exists
   → controlled bootstrap promotes local role
```

Choose based on actual code.

Do not create a second identity provisioning architecture.

---

# 16. Bootstrap Email Is Not Sufficient Identity Proof

Reject architecture such as:

```php
User::where('email', $email)->first()->assignRole('ADMIN');
```

without verifying canonical Clerk/local identity mapping.

Email is mutable/contact identity and may create account-linking risk.

---

# 17. Bootstrap Must Be Explicit

Never automatically make:

```text
first user
first Clerk identity
first verified email
first user in database
specific email domain
```

an Admin.

There must be an explicit deployment/operator action.

---

# 18. Bootstrap Must Be Production-Safe

Do not derive Admin bootstrap from:

```text
APP_ENV=production
DB empty
number of users == 0
```

alone.

An empty database is not authorization.

---

# 19. Bootstrap Repeat Behavior

Bootstrap must be deterministic and safe on retry.

If the same exact identity is already ADMIN:

prefer:

```text
safe no-op / already configured
```

rather than assigning duplicates.

---

# 20. Different Second Bootstrap Identity

This is security-sensitive.

Do not silently allow:

```text
run admin:bootstrap again
with arbitrary second identity
→ ADMIN
```

without an explicitly approved mechanism.

The command should distinguish:

```text
initial bootstrap
```

from:

```text
additional Admin creation
```

---

# 21. Initial Bootstrap Guard

Recommended invariant:

```text
if no active local ADMIN exists
→ initial bootstrap may proceed

if an ADMIN already exists
→ initial bootstrap refuses
```

unless the repository has an explicitly approved recovery mode.

This prevents the bootstrap mechanism becoming a permanent privilege-escalation shortcut.

---

# 22. Race-Safe Bootstrap

Two simultaneous bootstrap attempts must not create:

```text
two first Admins
duplicate local Users
multiple roles
```

Use a transaction and appropriate DB constraints/locking.

Do not rely solely on:

```php
Admin::count() === 0
```

outside a protected transaction.

---

# 23. One Effective Role

Preserve the existing V1 invariant:

```text
one effective human role per ordinary User
```

Do not leave:

```text
CUSTOMER + ADMIN
```

assigned simultaneously unless the existing RBAC architecture explicitly models that.

---

# 24. Role Transition During Bootstrap

If the identity was previously JIT-provisioned as:

```text
CUSTOMER
```

and is then trusted as initial Admin:

perform a controlled role reassignment:

```text
CUSTOMER → ADMIN
```

according to existing Spatie role APIs.

Do not simply attach ADMIN and leave CUSTOMER.

---

# 25. Bootstrap Audit

Initial Admin bootstrap is a privileged security operation.

Record a durable audit event if the current audit architecture supports deployment/system actor semantics safely.

Audit should capture conceptually:

```text
action
target local User
previous role
new role
timestamp
request/correlation or deployment correlation
result
```

Do not fabricate a human Admin actor when none yet exists.

---

# 26. System Actor

If existing audit supports an internal/system actor, use that.

Do not create:

```text
fake admin user
```

solely to audit bootstrap.

If current audit cannot safely represent deployment bootstrap, document the limitation and record the operation through the approved deployment/operations record rather than corrupting audit semantics.

---

# 27. Bootstrap Secrets

Do not put:

```text
Clerk secret
Admin password
session token
database password
```

in command arguments that may be stored in shell history if avoidable.

Prefer identifiers plus environment-provided secrets.

---

# 28. No Password Argument

Never implement:

```bash
php artisan admin:bootstrap \
  --email=... \
  --password=...
```

Laravel does not own passwords.

---

# 29. Clerk Credential Boundary

The bootstrap process may identify a Clerk User.

It must not:

```text
set Clerk password
read Clerk password
copy Clerk password into Laravel
```

---

# 30. Admin Sign-In After Bootstrap

After bootstrap, normal Admin authentication is ordinary Clerk authentication:

```text
Admin
→ Clerk sign-in
→ valid Clerk token
→ Laravel verify
→ local ADMIN resolution
→ Admin API authorization
```

Bootstrap is not repeated on each sign-in.

---

# 31. No Admin-Specific Laravel Login

Do not add:

```http
POST /api/v1/admin/login
```

The Admin app signs into Clerk.

Laravel receives only:

```http
Authorization: Bearer <Clerk session token>
```

---

# 32. Admin Route Gate

Ensure every administrative route requires:

```text
valid Clerk authentication
+
local ADMIN authorization
+
specific permission
```

Do not rely only on a frontend `/admin` route guard.

---

# 33. Authentication vs Authorization

Keep distinct:

```text
Authentication:
Is this really Clerk user X?

Authorization:
Is local User X allowed to perform operation Y?
```

Do not collapse them into:

```text
if token valid → admin
```

---

# 34. Existing Clerk Verification

Reuse current:

```text
OfficialClerkTokenVerifier
Clerk authentication middleware
local User resolver
pending-session rejection
```

No duplicate token decoder.

---

# 35. Signature Verification

Admin tokens must undergo the same cryptographic validation as all Clerk sessions:

```text
signature
issuer
exp
nbf
subject
session semantics
azp/audience where configured
```

No decode-only JWT path.

---

# 36. Pending Clerk Security Tasks

A Clerk session with:

```text
sts=pending
```

remains not fully authenticated.

It must not access Admin routes.

Expected:

```text
401 SESSION_EXPIRED
```

or exact existing mapping.

---

# 37. Revoked Session

Revoked Clerk Admin session:

```text
→ rejected
```

Laravel must not preserve a parallel session.

---

# 38. Expired Session

Same.

---

# 39. Local Role Removal

If a Clerk identity remains valid but the local role is changed away from ADMIN:

Admin access stops immediately on the next request.

Do not cache Admin authority in client state.

---

# 40. Local Suspension

If local Staff/Admin account state is:

```text
SUSPENDED
DISABLED
inactive
```

according to actual current model:

authentication may succeed at Clerk,

but Laravel protected Admin access must fail.

Use current account-state middleware.

---

# 41. Sign-Out

Admin logout uses Clerk.

Do not create:

```text
POST /api/v1/admin/logout
```

unless the frozen contract already defines one—which it should not.

---

# 42. Multi-Session

The frozen auth contract permits multiple legitimate Staff/Admin sessions conceptually.

Do not introduce single-session locking in 11.2 unless already approved.

---

# 43. Stronger Admin Session Controls

Concepts such as:

```text
shorter Admin idle timeout
mandatory MFA
session inventory
force logout all
IP allow-list
```

are not automatically Phase 11.2 requirements.

Do not invent them unless already approved.

They may belong to later production-security work.

---

# 44. MFA

Do not build custom Laravel MFA.

If MFA is later required:

Clerk remains the appropriate credential/factor authority.

---

# 45. Admin Frontend Role Gate

Although frontend implementation is later, document the expected behavior:

```text
valid Clerk identity
but local CUSTOMER
→ Admin app access denied

valid Clerk identity
but local STAFF
→ only Staff operational workspace if future Admin app supports it

local ADMIN
→ administrative workspace according to permissions
```

Backend remains authoritative.

---

# 46. Staff and Admin May Share Admin Application

The Admin Next.js application may eventually serve both:

```text
STAFF
ADMIN
```

with permission-sensitive navigation.

Do not interpret "Admin app" as:

```text
ADMIN role only for every screen
```

Operational Staff need the management workspace too.

---

# 47. Backend Enforcement

Even if frontend hides:

```text
Staff Management
Customer Administration
Audit
```

from Staff:

Laravel must independently return:

```text
403
```

for unauthorized operations.

---

# 48. Staff Onboarding

V1 allows:

```text
Admin invite/create Staff
or
Staff candidate → Admin review
```

but public self-registration as STAFF is forbidden.

---

# 49. ADM-003

`ADM-003` is the approved Staff invite/create operation:

```http
POST /api/v1/admin/staff
```

It is not:

```text
public Staff registration
Admin registration
```

---

# 50. ADM-004

`ADM-004`:

```http
POST /api/v1/admin/staff/{user}/approve
```

means:

```text
approve STAFF
```

not:

```text
approve ADMIN
```

Do not broaden it.

---

# 51. Additional Admins

Important Phase 11.2 rule:

The frozen V1 API does not currently provide a dedicated endpoint for:

```text
promote Staff to Admin
invite another Admin
approve Admin candidate
```

Therefore Phase 11.2 must not invent one.

---

# 52. Additional Admin Current Policy

Until a separate contract is approved:

```text
additional Admin creation
→ trusted administrative/deployment assignment
```

using the same server-controlled principle as initial Admin bootstrap, but **not necessarily the same one-time bootstrap command**.

If an additional-Admin operational mechanism is needed now, STOP and classify it as a frozen-contract decision.

---

# 53. Do Not Abuse Staff Approval

Never implement:

```text
approve staff
+
body role=ADMIN
```

or:

```text
staff approval endpoint
→ optionally ADMIN
```

That would broaden frozen ADM semantics.

---

# 54. Role Change Audit

Any trusted local role change must be auditable according to the existing authorization contract.

---

# 55. Self-Promotion

Reject all paths by which an authenticated:

```text
CUSTOMER
STAFF
```

can make themselves ADMIN.

---

# 56. Admin Self-Assignment

Even an existing Admin should not be able to use arbitrary generic input:

```json
{"role":"ADMIN"}
```

because role assignment is controlled action, not mass assignment.

---

# 57. Clerk Metadata

Do not trust:

```text
publicMetadata.role
privateMetadata.role
unsafeMetadata.role
```

as Laravel RBAC authority.

Clerk verifies identity.

Laravel assigns application roles.

---

# 58. Email Domain

Do not use:

```text
@company.com
```

as automatic Admin/Staff authority.

Domain can be identity context, not permission.

---

# 59. Bootstrap Allow-List Configuration

If a deployment configuration needs to identify the initial Admin candidate, prefer an immutable identifier.

If an email allow-list is unavoidable as an operator convenience:

it must only select a candidate for trusted bootstrap and must not independently confer role on ordinary HTTP requests.

---

# 60. No Auto-Promotion Middleware

Forbidden:

```php
if ($user->email === config('admin.email')) {
    $user->assignRole('ADMIN');
}
```

on each request.

Bootstrap must be explicit and finite.

---

# 61. Admin Access Middleware

Inspect existing coarse role middleware from Group D.

Reuse it if correct.

Do not introduce another authorization framework.

---

# 62. Permission Checks

After Admin authentication, individual operations continue using:

```text
staff.manage
staff.approve
users.manage_authorized
products.manage
inventory.manage
requests.manage
enquiries.manage
...
```

according to current PermissionCatalog.

ADMIN role is not a wildcard bypass.

---

# 63. Permission Catalog Is Authority

Do not hard-code a Phase 11.2 list independent of:

```text
PermissionName
PermissionCatalog
RbacSeeder
```

---

# 64. Audit Permission Gap

Preserve Phase 11.1 finding:

```text
ADM-007 docs expect audit.view
runtime PermissionCatalog lacks audit.view
```

Phase 11.2 must not opportunistically fix this.

It belongs to:

```text
Phase 11.13
```

through frozen-contract reconciliation.

---

# 65. No `audit.view` Addition Here

Do not modify:

```text
PermissionName
PermissionCatalog
RbacSeeder
OpenAPI
ADM-007
```

to solve the Phase 11.1 audit gap.

---

# 66. Customer JIT Provisioning

Preserve current Group D behavior:

```text
valid new Customer identity
→ local CUSTOMER
```

Do not change general JIT provisioning to infer privileged roles.

---

# 67. Admin JIT Provisioning

Do not implement:

```text
unknown Clerk identity visits Admin
→ local ADMIN
```

That is privilege escalation.

---

# 68. Unknown Clerk Identity on Admin App

If an identity has no approved local privileged mapping:

it should not gain privileged access.

Depending on current Group D behavior it may:

```text
be provisioned as CUSTOMER
then receive 403

or

be denied privileged admission before provisioning
```

Preserve current security architecture.

Do not create ADMIN.

---

# 69. Existing CUSTOMER Tries Admin Route

Expected:

```text
valid Clerk token
local CUSTOMER
→ 403 FORBIDDEN
```

---

# 70. Existing STAFF Tries Admin-Only Route

Expected:

```text
valid Clerk token
local STAFF
missing Admin-only permission
→ 403
```

---

# 71. Existing ADMIN Without Required Permission

Because permission authority remains explicit:

```text
ADMIN role
but required permission absent
→ deny
```

Do not automatically allow merely by role string if current middleware supports permission-based authorization.

---

# 72. Bootstrap Authorization Is Outside HTTP RBAC

Initial bootstrap necessarily occurs before normal Admin authorization exists.

That exception must be narrowly limited to the controlled deployment mechanism.

Do not let the exception bleed into API middleware.

---

# 73. Recovery Scenario

Do not build an emergency "make me admin" HTTP escape hatch.

If all Admin access is lost:

recovery should use controlled server/deployment procedures.

Document the operational recovery approach.

---

# 74. Bootstrap Recovery vs Normal Administration

Distinguish:

```text
initial trust bootstrap
emergency trust recovery
ordinary Staff/Admin management
```

Do not implement them as one generic command with weak safeguards.

---

# 75. Recommended Bootstrap Command Safeguards

If implementing an Artisan command, include:

```text
interactive confirmation
non-production/production explicit mode awareness
exact target identity display
existing role display
refusal if initial Admin already exists
transactional update
clear success/failure output
no secrets printed
```

Do not depend on confirmation alone for security.

---

# 76. Non-Interactive Deployment

If Coolify later needs non-interactive bootstrap:

support an explicit deterministic deployment mode only if secure and necessary.

Do not require interactive prompts that make deployment impossible, but do not default to unsafe silent privilege assignment.

This can be documented now without implementing Coolify integration.

---

# 77. Production Deployment Context

Future production:

```text
Coolify
on Contabo VPS
```

Bootstrap should be executable securely inside the Laravel deployment environment.

Do not build Coolify-specific code.

---

# 78. Bootstrap Should Be Rare

Expected operational frequency:

```text
once for initial Admin
```

not every deployment.

---

# 79. Bootstrap Persistence

Admin assignment is persistent local RBAC state.

Redeployment must not reset it.

Do not put initial Admin assignment into a seeder that runs routinely.

---

# 80. DatabaseSeeder

Production-safe `DatabaseSeeder` must not create an Admin automatically.

Preserve this invariant.

---

# 81. DemoSeeder

Development/demo Admin users remain development-only fixtures.

They are not production bootstrap architecture.

---

# 82. No Hardcoded Admin Email

Never commit:

```text
owner@example.com
admin@business.com
```

as production Admin authority.

---

# 83. No Hardcoded Clerk User ID

Same.

Deployment supplies the target securely.

---

# 84. Admin Identity Verification

If existing Clerk backend integration supports fetching a User by Clerk ID:

use that trusted lookup before creating/promoting local identity.

Verify at least:

```text
Clerk identity exists
appropriate verified email state
subject matches intended identity
```

Do not make external Clerk lookup mandatory if the current identity architecture already safely requires a local mapped identity.

---

# 85. Email Verification

Admin identity must satisfy the same trusted Clerk email-verification expectations unless existing contract explicitly says otherwise.

Do not let bootstrap bypass identity verification casually.

---

# 86. Local Email Snapshot

If local email is synchronized from Clerk:

preserve existing synchronization authority.

Do not let bootstrap body overwrite it arbitrarily.

---

# 87. Local User ID

Never expose/accept numeric DB ID as the bootstrap's only external identity selector if a Clerk subject is available.

---

# 88. Testing — Initial Bootstrap

Test:

```text
no Admin exists
valid Clerk/local identity
→ ADMIN assigned
```

---

# 89. Testing — Existing Customer Promotion via Trusted Bootstrap

If chosen implementation permits an already-local CUSTOMER as the initial bootstrap identity:

test:

```text
CUSTOMER only
→ controlled bootstrap
→ ADMIN only
```

No dual role.

---

# 90. Testing — Repeat Same Identity

Test:

```text
already bootstrapped ADMIN
same exact bootstrap command
```

according to chosen semantics:

```text
safe no-op
or explicit already-configured result
```

---

# 91. Testing — Different Identity After Bootstrap

Mandatory:

```text
Admin already exists
initial-bootstrap command targets different identity
→ reject
```

Do not create second Admin through the initial-bootstrap path.

---

# 92. Testing — Invalid Clerk Identity

Reject safely.

No local Admin created.

---

# 93. Testing — Unverified Identity

If verification is required by existing Clerk contract:

reject.

---

# 94. Testing — Role Mass Assignment

Protect:

```json
{"role":"ADMIN"}
```

through ordinary API paths.

Must not promote.

---

# 95. Testing — Public Signup

Prove:

```text
public verified Clerk signup
→ CUSTOMER
```

never ADMIN.

---

# 96. Testing — Customer Admin Access

```text
CUSTOMER token
→ Admin-only route
→ 403
```

---

# 97. Testing — Staff Admin Access

Use at least one Admin-only endpoint.

```text
STAFF token
→ ADM-001/other Admin-only endpoint
→ 403
```

---

# 98. Testing — Admin Access

```text
ADMIN
with required permission
→ authorized boundary reached
```

The target endpoint may still return its current domain result/placeholder depending on later phase ownership.

Authentication test should distinguish:

```text
auth/authz succeeded
```

from:

```text
business endpoint implemented
```

---

# 99. Testing — Missing Permission

Construct actor without required permission.

Expected:

```text
403
```

even if role labeling alone might suggest privilege.

---

# 100. Testing — Invalid Token

Expected:

```text
401 INVALID_AUTHENTICATION
```

---

# 101. Testing — Missing Token

Expected:

```text
401 AUTHENTICATION_REQUIRED
```

---

# 102. Testing — Pending Session

Expected existing:

```text
401 SESSION_EXPIRED
```

before Admin authorization.

---

# 103. Testing — Revoked/Expired Token

Preserve Group D tests.

---

# 104. Testing — Local Suspended State

If current local privileged account state supports suspension:

```text
valid Clerk token
suspended local identity
→ protected access denied
```

Use existing status semantics.

---

# 105. Testing — Clerk Metadata Attack

Where test infrastructure supports claims/identity fixtures:

```text
CUSTOMER Clerk metadata role=ADMIN
```

must not create local Admin authority.

---

# 106. Testing — Admin Route Frontend Independence

Backend must deny unauthorized role regardless of:

```text
Referer
Origin path
/admin URL on frontend
```

Do not trust frontend route.

---

# 107. Bootstrap Concurrency

If practical, add a real DB concurrency test or a strong transactional test ensuring simultaneous initial bootstrap attempts cannot both succeed.

MariaDB proof is preferable if lock semantics are used.

Do not over-expand this phase if current constraints make a deterministic unique/transactional proof sufficient.

---

# 108. Audit Bootstrap Test

If bootstrap writes an audit record:

verify:

```text
target
role transition
no secrets
correct system/deployment actor semantics
```

---

# 109. No Authentication API Changes

Expected public API change:

```text
NONE
```

unless an actual frozen-contract mismatch is found.

The bootstrap command is not an HTTP API contract change.

---

# 110. OpenAPI

Expected:

```text
UNCHANGED
```

No Admin login/register endpoints should be added.

---

# 111. Schema

Prefer:

```text
NONE
```

Existing User/RBAC identity schema should be sufficient.

If bootstrap exposes a genuine missing persistence invariant, STOP and report before adding speculative schema.

---

# 112. Dependencies

Expected:

```text
NONE
```

Use existing Clerk + Spatie infrastructure.

---

# 113. Frontend

Expected:

```text
NONE
```

Phase 11.2 defines backend authentication behavior.

Do not implement the Next.js Admin login screen yet unless project phase ownership explicitly says otherwise.

---

# 114. Documentation

Update:

```text
phases/group-K-phases.md
docs/decisions.md
```

and, if needed:

```text
docs/clerk-authentication-architecture.md
```

only to document already-approved Admin bootstrap behavior.

---

# 115. Recommended ADR

Add next available ADR conceptually:

```text
Admin Authentication and Initial Trust Bootstrap
```

Do not guess the numbering/name convention.

---

# 116. ADR Must Record

At minimum:

```text
Clerk remains credential/session authority
Laravel remains role/permission authority
Admin web app has no public Admin registration
initial Admin is deployment-bootstrapped
bootstrap is not HTTP
bootstrap cannot self-promote arbitrary users
public signup remains CUSTOMER
Staff approval remains Admin-only
ADM-004 approves Staff only
additional Admin creation is not currently a public V1 workflow
additional Admins remain controlled assignment unless separately contracted
Admin access requires explicit permissions
audit.view gap remains deferred to 11.13
```

---

# 117. Additional Admin Decision — Do Not Hide the Gap

Phase 11.2 must explicitly document:

```text
V1 does not currently expose a dedicated additional-Admin onboarding endpoint.
```

This is not necessarily a blocker for current launch if one bootstrap Admin is sufficient.

---

# 118. If Multiple Admins Are Required for Launch

If the business explicitly requires multiple Admins before launch:

do not reuse Staff approval implicitly.

Report:

```text
frozen contract decision required:
dedicated Admin invite/promotion/recovery operation
```

and STOP that sub-scope until approved.

---

# 119. Current Launch Safe Default

For the current small-business launch, safe default:

```text
one deployment-bootstrapped ADMIN
+
one or more Admin-approved STAFF
```

is consistent with the existing frozen V1 surface.

---

# 120. Staff Registration Language

Avoid saying:

```text
Staff registers and Admin approves
```

if that implies public role selection.

Prefer:

```text
Staff candidate obtains/activates Clerk identity through approved onboarding
+
Admin performs local Staff approval
```

or:

```text
Admin invites Staff
→ Staff activates Clerk identity
→ local Staff authority becomes active
```

---

# 121. Admin Registration Language

Avoid:

```text
Admin registration
```

for V1.

Use:

```text
Admin bootstrap
Admin sign-in
```

---

# 122. Group K Admin Application Terminology

The application may be called:

```text
Admin application
```

but it can later serve:

```text
ADMIN
STAFF
```

depending on permissions.

Authentication is shared Clerk identity; authorization controls the workspace.

---

# 123. Security Headers / Caching

Protected Admin responses remain:

```text
private
no-store
```

where applicable.

Do not cache authorization context publicly.

---

# 124. CORS

Reuse existing approved frontend-origin CORS behavior.

Do not add:

```text
*
```

because Admin app needs access.

---

# 125. Bearer Transport

Admin Next.js → Laravel uses:

```http
Authorization: Bearer <Clerk session token>
```

No Laravel auth cookie.

---

# 126. SSR

Future Next.js Admin server-side requests may obtain the current Clerk session token server-side and forward it.

Do not expose token into rendered HTML.

---

# 127. Browser Storage

Do not recommend storing Clerk session tokens in:

```text
localStorage
sessionStorage
```

when Clerk manages session transport/lifecycle.

---

# 128. Logging

Never log:

```text
Authorization bearer
Clerk session token
Clerk secret
password/recovery data
```

Admin auth failures may log safe:

```text
request_id
failure category
local actor id where safely resolved
```

---

# 129. Rate Limiting

Do not introduce a second Laravel login rate limiter because Clerk owns login.

Protected Admin API rate limits remain resource/action-specific.

---

# 130. Password Recovery

Admin uses Clerk recovery.

No:

```text
/admin/forgot-password
```

Laravel endpoint.

Future Admin web UI may render Clerk recovery components/routes.

---

# 131. Email Verification

Remain Clerk-owned.

Laravel trusts verified Clerk state through the existing verifier/projection.

---

# 132. Session Revocation

Reuse existing Clerk session revocation semantics where approved.

Do not add local Admin token tables.

---

# 133. No Impersonation

Phase 11.2 must not implement:

```text
Admin login as Customer
Admin impersonate Staff
```

---

# 134. No Backdoor Credentials

Do not implement environment credentials such as:

```text
ADMIN_EMAIL
ADMIN_PASSWORD
```

for runtime login.

An environment/CLI identifier for one-time bootstrap is not an authentication credential.

---

# 135. Production Bootstrap Documentation

Document an operator procedure conceptually:

```text
1. Establish real verified Clerk identity for owner/Admin.
2. Deploy backend with Clerk configured.
3. Execute controlled initial Admin bootstrap against that Clerk identity.
4. Verify local User has only ADMIN role.
5. Verify Admin permissions from PermissionCatalog.
6. Sign into Admin application via Clerk.
7. Confirm Admin-only API authorization.
8. Never rerun initial bootstrap for unrelated users.
```

Do not include real secrets.

---

# 136. Development Bootstrap

Demo Admin fixtures may remain available only through explicit development/demo seeding.

Do not confuse them with production bootstrap.

---

# 137. Test Database

Bootstrap tests should run in normal isolated test DB.

If a concurrency-specific test is added, use the existing disposable MariaDB discipline.

---

# 138. Regression Suites

Run at minimum relevant:

```text
Clerk authentication tests
JIT provisioning tests
RBAC tests
authorization tests
account-state tests
API routing security tests
Group K architecture tests
```

---

# 139. Full Suite

Run:

```bash
php artisan test
```

---

# 140. PHPStan

Run:

```bash
vendor/bin/phpstan analyse
```

Expected:

```text
0 errors
```

---

# 141. Pint

Run:

```bash
vendor/bin/pint --test
```

---

# 142. Composer Audit

Run:

```bash
composer audit
```

---

# 143. Diff Check

Run:

```bash
git diff --check
```

---

# 144. Route Verification

Run:

```bash
php artisan route:list --path=api --except-vendor
```

Confirm no:

```text
/admin/login
/admin/register
/register-admin
```

Laravel auth endpoints were introduced.

---

# 145. OpenAPI Verification

Run existing OpenAPI tests/parser.

Expected:

```text
no new Admin auth endpoint
```

---

# 146. Completion Report — Phase Status

Return:

## Phase 11.2 Status

```text
PASS
```

or:

```text
BLOCKED
```

---

# 147. Completion Report — Authentication Authority

State:

```text
Credential authority:
Clerk

Session authority:
Clerk

Local identity:
Laravel projection

Role authority:
Laravel

Permission authority:
PermissionCatalog/RBAC
```

---

# 148. Completion Report — Initial Admin Bootstrap

Report:

```text
mechanism:
identity selector:
Clerk verification:
existing-user behavior:
no-existing-user behavior:
role replacement behavior:
retry behavior:
second-identity behavior:
audit behavior:
```

Do not expose secrets.

---

# 149. Completion Report — Admin Registration

Explicitly report:

```text
Public Admin registration: NONE
Public Staff role registration: NONE
Admin sign-in: Clerk
```

---

# 150. Completion Report — Additional Admins

Report:

```text
Dedicated additional-Admin V1 API:
NONE
```

unless a separately approved contract already exists.

State whether current implementation leaves this as controlled deployment assignment.

---

# 151. Completion Report — Staff Onboarding

Report distinction:

```text
ADM-003 = Staff invite/create
ADM-004 = Staff approval
```

Not Admin approval.

---

# 152. Completion Report — Security Tests

Report:

```text
public signup remains CUSTOMER
CUSTOMER denied Admin
STAFF denied Admin-only
ADMIN permitted when permission exists
missing permission denied
invalid token denied
missing token denied
pending session denied
self-promotion rejected
Clerk metadata role ignored
bootstrap second identity rejected
```

---

# 153. Completion Report — API Changes

Expected:

```text
NONE
```

---

# 154. Completion Report — Schema

Expected:

```text
NONE
```

---

# 155. Completion Report — Dependencies

Expected:

```text
NONE
```

---

# 156. Completion Report — Frontend

Expected:

```text
NONE
```

---

# 157. Completion Report — OpenAPI

Expected:

```text
UNCHANGED
```

---

# 158. Completion Report — Audit Gap

Reconfirm:

```text
ADM-007 / audit.view inconsistency remains deferred to 11.13
```

Phase 11.2 must not claim it fixed.

---

# 159. Completion Report — Quality

Report:

```text
PHPUnit:
OpenAPI:
PHPStan:
Pint:
Composer audit:
git diff --check:
route:list:
```

---

# 160. Next Phase

If PASS:

```text
Phase 11.3 — Product CRUD READY
```

Do not implement 11.3 automatically.

---

# 161. Definition of Done

Phase 11.2 is complete when:

- Clerk remains sole Admin credential/session authority;
- Laravel has no Admin password/login system;
- public signup cannot create ADMIN;
- public signup cannot create STAFF;
- first Admin uses controlled deployment bootstrap;
- bootstrap uses a real Clerk identity;
- bootstrap is not a public HTTP endpoint;
- role authority remains local Laravel RBAC;
- Clerk metadata cannot grant ADMIN;
- initial bootstrap is explicit rather than automatic-first-user promotion;
- bootstrap does not trust email alone where immutable Clerk identity is available;
- bootstrap is safe to retry for the same identity;
- initial bootstrap refuses an unrelated second identity after an Admin exists;
- bootstrap maintains one-effective-role invariant;
- no CUSTOMER+ADMIN dual role is accidentally retained;
- valid Admin Clerk session resolves correctly;
- CUSTOMER cannot access Admin-only APIs;
- STAFF cannot access Admin-only APIs without permissions;
- ADMIN still requires explicit permissions;
- expired/revoked/invalid tokens fail;
- pending Clerk sessions fail;
- local suspension/inactivation remains authoritative;
- no Admin login/register Laravel endpoint exists;
- no Admin-specific JWT/Sanctum token exists;
- ADM-003/004 remain Staff-only onboarding semantics;
- no additional-Admin public workflow is invented;
- additional Admin creation remains controlled/deployment-side unless later contracted;
- audit.view inconsistency remains isolated to Phase 11.13;
- schema remains unchanged unless a proven blocker exists;
- OpenAPI remains unchanged;
- no frontend Admin UI is implemented;
- full regression remains green.

---

# 162. STOP Condition

STOP when the system can prove:

```text
FIRST ADMIN
real Clerk identity
→ controlled deployment bootstrap
→ local ADMIN
→ explicit permissions
→ Clerk sign-in
→ authorized Admin API access
```

while proving:

```text
PUBLIC SIGNUP
→ CUSTOMER only

STAFF
→ Admin-controlled onboarding
→ never self-approved

CUSTOMER / STAFF
→ cannot self-promote to ADMIN

ADDITIONAL ADMIN
→ no public V1 registration/approval path
→ controlled assignment only until separately contracted
```

and report:

```text
Phase 11.2 PASS

Phase 11.3 — Product CRUD READY
```

Do not begin Phase 11.3 automatically.

DO NOT COMMIT, STAGE OR PUSH.

The project owner handles all Git operations.

---

# 163. Phase 11.2 Outcome — Admin Authentication and Initial Trust Bootstrap

## Authority Boundary

```text
Credential authority: Clerk
Session authority: Clerk
Local identity: Laravel User projection keyed by users.clerk_user_id
Role authority: Laravel RoleName / Spatie RBAC
Permission authority: PermissionCatalog / RbacSeeder
```

Administrative API routes continue to require Clerk authentication, active local `ADMIN` role through `AdministrativeAccess`, and their explicit route permission. A valid Clerk token, Clerk metadata, frontend route, request payload, email domain, or email address alone never grants Admin authority. Laravel has no Admin password, login, logout, registration, cookie, custom JWT, Sanctum, or Passport flow.

Public Clerk signup and JIT provisioning remain `CUSTOMER` only. `ADM-003` is Staff invite/create and `ADM-004` is Staff approval only; neither creates or approves an Admin. V1 has no public additional-Admin invite, promotion, approval, recovery, or self-promotion operation.

## Initial Admin Bootstrap

The one-time deployment mechanism is:

```bash
php artisan admin:bootstrap <verified-clerk-user-id> --confirm
```

It is not an HTTP endpoint. `--confirm` is required for deterministic non-interactive deployment; without it, the command displays the Clerk subject and requires an interactive confirmation. The command accepts no password, bearer token, Clerk secret, database credential, or email selector.

`BootstrapInitialAdmin` first retrieves the exact Clerk subject through the existing `ClerkUserGateway` and requires a matching verified-email snapshot. It then locks the seeded `ADMIN` role in a database transaction, serializing competing initial bootstrap attempts before checking existing Admin assignments.

| Situation | Result |
| --- | --- |
| No local mapping for the verified Clerk identity and no Admin | Reuse `LocalUserProvisioner`, then assign the sole `ADMIN` role, set local account state to `ACTIVE`, and create the existing Staff profile projection. |
| Existing local Customer/Staff mapping and no Admin | Reuse the mapped User, replace roles with only `ADMIN`, set local account state to `ACTIVE`, and create the Staff profile projection if absent. |
| Same Clerk identity already the sole Admin | Safe no-op; no second user or role is created. |
| Different Clerk identity after an Admin exists | Refuse; the initial bootstrap command cannot create a second Admin. |
| Missing, mismatched, unavailable, or unverified Clerk identity | Refuse before local Admin creation. |

The bootstrap command is intentionally limited to initial trust establishment. Additional Admin creation remains a separately controlled deployment/administrative assignment concern and needs a frozen-contract decision before a public workflow exists. It must not reuse Staff approval or this one-time initial bootstrap command.

## Audit Limitation and Operator Procedure

The current closed `AuditAction` and `AuditResourceType` vocabularies have no safe system/bootstrap value. Phase 11.2 therefore does not fabricate a human actor, extend the audit enums, or write an invalid audit event. Record the privileged deployment operation in the approved deployment/operations record with target Clerk subject, prior local role, resulting `ADMIN` role, timestamp, operator/deployment correlation, and outcome; do not record passwords, session tokens, or Clerk secrets.

Operator procedure:

1. Establish the intended owner identity in Clerk and complete Clerk email verification.
2. Deploy Laravel with valid Clerk backend configuration and seeded RBAC roles.
3. Run the command inside the deployment environment with the exact Clerk user ID and explicit `--confirm`.
4. Verify the local User has exactly the `ADMIN` role, `ACTIVE` account state, and permissions from `PermissionCatalog`.
5. Sign in through Clerk and verify an Admin-only API reaches its authorization boundary.
6. Do not use the initial bootstrap command for another identity. Use a controlled recovery/deployment procedure only if all Admin access is lost, pending a separately approved Admin-management contract.

## Phase 11.2 Verification Scope

- `InitialAdminBootstrapTest` covers verified initial bootstrap, existing-Customer role replacement, same-identity retry, second-identity refusal, unverified Clerk rejection, and command confirmation behavior.
- Existing Clerk/RBAC suites cover public signup as `CUSTOMER`, Clerk metadata non-authority, Customer/Staff denial at Admin routes, Admin permission checks, missing/invalid/pending Clerk sessions, local account-state denial, and mass-assignment resistance.
- `ADM-007` / `audit.view` remains the recorded Phase 11.1 inconsistency and is deferred unchanged to Phase 11.13.
- API, OpenAPI, schema, dependencies, and frontend are unchanged.
