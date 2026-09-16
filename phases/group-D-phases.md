# Phase 4.3 — Customer Registration / Sign-In Flow with Clerk

## Purpose

Complete the **customer authentication entry flow** using Clerk as the credential/session authority and Laravel as the application identity and authorization authority.

Phase 4.2 established:

```text
verified Clerk identity
        ↓
users.clerk_user_id
        ↓
local Laravel User
        ↓
CUSTOMER role for new public users
```

Phase 4.3 now establishes how a customer:

```text
signs up / signs in with Clerk
        ↓
obtains an authenticated Clerk session
        ↓
presents that session to Laravel
        ↓
Laravel authenticates the request
        ↓
resolves/provisions the local User
        ↓
protected API receives authenticated Laravel principal
```

Do not recreate Laravel username/password authentication.

Do not build the final Next.js or Flutter authentication UI yet.

---

# 1. Roadmap Context

The original `AGENTS.md` roadmap contains:

```text
4.1 Authentication design review
4.2 Customer registration
4.3 Login/logout
4.4 Password recovery
4.5 Email verification if required
4.6 Profile operations
4.7 API authentication for mobile
4.8 SPA authentication for website
4.9 Roles
4.10 Policies/permissions
4.11 Rate limiting
4.12 Authentication tests
```

Because Clerk adoption changed the implementation structure:

```text
4.1 → Clerk architecture/design review
4.2 → Clerk identity → local CUSTOMER provisioning
4.3 → customer sign-up/sign-in/session-entry flow
```

This adaptation must preserve the Group D exit condition:

> Customer, staff and admin access paths are secure and tested.

Do not pull later frontend work forward.

---

# 2. Dependencies

Required before starting:

* Phase 4.1 complete;
* Phase 4.2 complete;
* Clerk selected as authentication authority;
* official Clerk PHP/backend integration installed as required;
* `users.clerk_user_id` mapping implemented;
* CUSTOMER JIT provisioning implemented;
* email-based auto-linking prohibited;
* local RBAC remains authoritative;
* Clerk metadata cannot assign application role;
* Laravel can map a verified Clerk identity to a local User;
* existing test suite passes.

If Phase 4.2 is incomplete, STOP and finish it first.

---

# 3. Authoritative Inputs

Review:

1. `AGENTS.md`
2. Phase 4.1 Clerk ADR
3. Phase 4.2 implementation
4. `docs/api/api-contract.md`
5. `docs/api/api-resources.md`
6. `docs/api/api-conventions.md`
7. `docs/api/openapi.yaml`
8. `docs/domain/business-rules.md`
9. `docs/decisions.md`
10. current Clerk documentation/MCP skills.

Do not rely on remembered Clerk APIs where the current official documentation or installed Clerk skill can verify them.

---

# 4. Core Responsibility Boundary

Preserve:

```text
Clerk
├── sign-up
├── sign-in
├── passwords / passwordless factors
├── verification challenges
├── sessions
├── session renewal
├── session revocation
└── authentication security

Laravel
├── maps Clerk identity → local User
├── CUSTOMER / STAFF / ADMIN
├── account business state
├── ownership
├── authorization
├── commerce
└── API error contract
```

Never blur these responsibilities.

Laravel must not validate a customer's password.

Laravel must not issue a replacement customer credential after Clerk sign-in.

---

# 5. Do Not Build Frontend Authentication UI Yet

Do not modify:

```text
frontend/web/
frontend/app/
```

Phase 15.1 owns the website registration/login UI.

Phase 16.5 owns Flutter authentication storage/session concerns.

Phase 4.3 should make the backend authentication contract ready for both clients.

Do not add:

```text
<SignIn />
<SignUp />
<ClerkProvider />
UserButton
Flutter Clerk widgets
```

during this phase.

---

# 6. Do Not Run Clerk Frontend Initialization Yet

Do not execute:

```bash
clerk init
```

against the repository root merely because authentication is being implemented.

When Phase 4.8 or the website foundation phase reaches `frontend/web`, initialize Clerk there according to the project-owner supplied setup guide.

Do not convert the monorepo root into a frontend Clerk project.

---

# 7. Inspect Clerk Application Configuration

Using Clerk MCP/CLI/dashboard documentation where available, inspect the **configured authentication strategies** for the selected Clerk application.

Determine:

* sign-up identifiers;
* sign-in identifiers;
* whether password is enabled;
* whether email verification is required;
* whether email code/link authentication is enabled;
* whether phone is enabled;
* whether social providers are enabled;
* whether passkeys are enabled;
* whether MFA/device trust is configured;
* user-enumeration protection mode;
* session settings.

Do not enable additional strategies merely because Clerk supports them.

Implement the application according to the explicitly configured/approved Clerk strategy.

---

# 8. Authentication Strategy Must Not Be Invented

Do not arbitrarily decide between:

```text
email + password
email OTP
email link
phone OTP
OAuth
passkey
```

Phase 4.3 must follow:

```text
Phase 4.1 decision
+
actual Clerk application configuration
```

If those disagree, document the conflict.

Do not silently change production authentication requirements.

---

# 9. Registration Flow

The application registration flow is conceptually:

```text
Customer
   ↓
Clerk sign-up
   ↓
Clerk validates configured credentials/factors
   ↓
required Clerk verification
   ↓
Clerk User created
   ↓
Clerk session becomes active
   ↓
first protected Laravel request
   ↓
Laravel verifies session token
   ↓
Phase 4.2 LocalUserProvisioner
   ↓
local CUSTOMER user exists
```

Do not create a Laravel password-registration controller.

Do not duplicate Clerk registration validation.

---

# 10. Sign-In Flow

Existing customer:

```text
Customer
   ↓
Clerk sign-in
   ↓
Clerk authenticates credential/factor
   ↓
active Clerk session
   ↓
client gets usable session token
   ↓
Laravel API request
   ↓
verify Clerk session
   ↓
extract trusted `sub`
   ↓
find users.clerk_user_id
   ↓
bind local User
```

If a valid Clerk identity has no local user yet:

```text
verified identity
    ↓
Phase 4.2 JIT provisioning
    ↓
CUSTOMER local user
```

Do not create a separate Laravel login session.

---

# 11. Clerk Sign-Up/Sign-In States

Do not assume every Clerk authentication attempt jumps directly from:

```text
STARTED → COMPLETE
```

Account for Clerk's configured flow states conceptually, including where relevant:

```text
needs identifier
needs verification
missing requirements
needs first factor
needs second factor
device/client trust
complete
abandoned / failed
```

The future clients must use Clerk APIs/components to complete required tasks.

Laravel must see a request as authenticated only **after a valid authenticated Clerk session exists**.

Do not provision a user from an incomplete Clerk sign-up attempt.

---

# 12. Verification Belongs to Clerk

If the Clerk configuration requires email or phone verification during signup:

```text
Clerk owns verification challenge
```

Laravel must not:

* generate its own verification code;
* create its own verification token;
* send competing verification email;
* accept `email_verified=true` from the client.

Phase 4.5 will decide any application-level policy around verified identity.

This phase only respects Clerk's authentication completion state.

---

# 13. User Enumeration Protection

Preserve the project's requirement to avoid unnecessary account enumeration.

Existing conventions explicitly require login/recovery behavior not to expose account existence unnecessarily.

Where Clerk provides strict user-enumeration protection or privacy-preserving sign-in-or-up behavior, preserve that configuration.

Do not add Laravel endpoints such as:

```http
POST /auth/email-exists
GET /users/check-email
```

Do not expose:

```text
"This email is registered"
"This email is not registered"
```

merely to drive frontend UX.

Clerk supports sign-in-or-up flows that can avoid revealing account existence before verification; use Clerk's supported flow rather than recreating an enumeration oracle.

---

# 14. Authentication Request Boundary

Create or finalize the Laravel authenticated-request boundary.

Conceptually:

```text
Request
    ↓
extract Clerk credential
    ↓
ClerkTokenVerifier
    ↓
AuthenticatedClerkIdentity
    ↓
Local User Resolver
    ↓
JIT provision if permitted
    ↓
Laravel authenticated principal
    ↓
next middleware/controller
```

This should become the standard boundary for protected API endpoints.

---

# 15. Accept Session Tokens Only for Human Authentication

The customer authentication middleware must accept the Clerk **session-token** credential type intended for signed-in users.

Do not accidentally accept:

```text
M2M token
API key
OAuth machine token
```

as equivalent customer authentication unless a separate endpoint explicitly requires that credential type in the future.

Human-user and machine authentication must remain distinguishable.

---

# 16. Bearer Token Transport

For Laravel API requests coming from a different origin/client, use:

```http
Authorization: Bearer <Clerk session token>
```

Clerk currently documents this as the standard cross-origin request pattern.

Do not invent custom authentication headers such as:

```http
X-Clerk-User
X-Authenticated-User
X-Session-ID
```

for authentication.

The bearer token is the credential.

---

# 17. Never Trust the Clerk User ID Header

Reject any architecture in which a client can authenticate using:

```http
X-Clerk-User-Id: user_123
```

or body:

```json
{
  "clerk_user_id": "user_123"
}
```

The Clerk User ID must come from the successfully verified session token.

---

# 18. Token Authentication

Use the Phase 4.2 verification abstraction.

Laravel must verify the session credential, including the currently required Clerk properties such as:

```text
signature
expiration
not-before
issuer
authorized party
session/user claims
audience if configured
```

Use current Clerk backend guidance.

Do not manually decode and trust JWT claims.

---

# 19. Authorized Parties

Configure explicit authorized parties/origins where appropriate.

Current Clerk backend guidance recommends explicit `authorizedParties` validation to reduce cross-site request abuse.

The configuration should eventually include approved application origins such as the real website origin.

Do not permanently use:

```text
*
```

for authorized parties.

Use environment-specific configuration:

```text
LOCAL
STAGING
PRODUCTION
```

Do not hard-code production domains inside middleware.

---

# 20. Session Token Version

Current Clerk documentation uses the current session-token claim format and notes that older token claim versions have been deprecated.

Do not build the application around deprecated claim shapes.

Only depend on claims needed by the integration.

Prefer:

```text
sub
sid
iss
azp
exp
nbf
```

when needed.

Do not copy every Clerk claim into application state.

---

# 21. Authenticate vs Decode

Maintain this distinction:

```text
decode token
≠
authenticate request
```

A token is not trusted simply because its payload parses.

Authentication requires cryptographic and semantic verification.

All downstream identity resolution must receive only a **verified identity object**.

---

# 22. Local User Resolution

After successful Clerk authentication:

```text
clerk_user_id = token.sub
```

resolve:

```text
User::where('clerk_user_id', ...)
```

Do not resolve by:

```text
email
name
phone
public metadata
```

Use the immutable external identity mapping.

---

# 23. JIT Provisioning

If a successfully authenticated Clerk customer does not yet have a local user:

invoke the Phase 4.2 provisioner.

Do not duplicate provisioning code inside middleware.

Expected:

```text
middleware
    ↓
UserResolver
    ↓
LocalUserProvisioner when missing
```

not:

```text
middleware
    ├── fetch Clerk user
    ├── create DB user
    ├── assign role
    ├── create profile
    └── ...
```

Keep the middleware small.

---

# 24. Authenticated Laravel Principal

After successful resolution, bind the local User into Laravel's request authentication context.

Downstream code should use ordinary application-level identity mechanisms.

For example:

```text
$request->user()
```

or the equivalent chosen Laravel auth integration.

Domain services must not need to inspect Clerk JWTs.

---

# 25. One Authentication Guard Strategy

Define one clear guard/provider strategy for Clerk-backed application users.

Do not create:

```text
clerk_customer_guard
clerk_staff_guard
clerk_admin_guard
```

solely because roles differ.

Authentication determines identity.

RBAC determines application capability.

Prefer:

```text
one Clerk-backed authenticated User
+
Laravel roles/policies
```

unless Phase 4.1 approved otherwise.

---

# 26. Authentication ≠ Authorization

A valid Clerk session means:

```text
identity authenticated
```

It does not mean:

```text
checkout allowed
staff access allowed
admin access allowed
account active
order owned
```

After authentication, later authorization still evaluates:

```text
local user
+ role
+ permission
+ ownership
+ resource
+ action
+ business state
+ operational context
```

The project explicitly requires backend authorization rather than frontend role checks.

---

# 27. Local Account State

If Phase 4.1 established Laravel application account state such as:

```text
ACTIVE
SUSPENDED
PENDING
```

do not interpret a valid Clerk session as bypassing it.

Conceptually:

```text
Clerk session valid
        ↓
identity authenticated
        ↓
Laravel account state check
        ↓
application access
```

Keep credential validity and application eligibility separate.

Do not invent new account states in this phase.

---

# 28. Public Signup Role

A new identity arriving through public Clerk registration must result in:

```text
CUSTOMER
```

only.

Never assign:

```text
STAFF
ADMIN
```

from:

* signup URL;
* request body;
* Clerk metadata;
* email domain;
* social provider;
* custom query parameter.

This must already be enforced by Phase 4.2 and remain covered here.

---

# 29. Sign-In Must Never Modify Role

Signing in is identity resolution.

Do not run:

```text
assignRole(CUSTOMER)
```

on every successful sign-in.

An existing STAFF or ADMIN identity must retain its existing local role.

Authentication must not mutate authorization state.

---

# 30. Registration Data Collection

The original project contract expected minimum registration information including:

```text
name
email
phone
password
```

Clerk adoption changes credential ownership.

Review the post-freeze Phase 4.1 decision and actual Clerk configuration.

The resulting architecture should distinguish:

```text
authentication-required fields
```

from:

```text
application profile-required fields
```

For example:

```text
email/password → Clerk authentication
name/phone → Clerk signup requirements or later application profile completion
```

Do not make Laravel accept plaintext passwords merely to preserve the old request shape.

---

# 31. Missing Application Profile Fields

A Clerk sign-up can potentially complete while a local application field remains incomplete depending on Clerk configuration.

Do not silently fabricate missing data.

If local User creation requires data not guaranteed by Clerk:

use the Phase 4.1/4.2 approved strategy:

```text
Clerk required field
```

or:

```text
post-auth profile-completion state
```

Do not invent a third solution.

Full profile mutation belongs to Phase 4.6.

---

# 32. No Laravel Registration Endpoint Unless Contract Retained It

Review the Phase 4.1 endpoint migration matrix.

If the original endpoint:

```text
POST /api/v1/auth/register
```

was formally retired because Clerk now owns registration:

do not implement it.

If Phase 4.1 retained a specific application-bootstrap endpoint, implement only that approved purpose.

Do not send credentials through Laravel unnecessarily.

---

# 33. No Laravel Login Endpoint Unless Contract Retained It

Likewise, do not recreate:

```http
POST /api/v1/auth/login
```

that receives:

```json
{
  "email": "...",
  "password": "..."
}
```

if Clerk owns login.

The future client signs in with Clerk.

Laravel authenticates subsequent session-token requests.

That is the new architecture.

---

# 34. Session Establishment

On successful Clerk sign-in:

```text
Clerk creates / activates session
```

Laravel does not need to issue:

```text
session cookie
Sanctum token
Passport token
custom access token
custom refresh token
```

The active Clerk session remains the customer credential source.

---

# 35. Multi-Device Sessions

Preserve the existing project requirement that multiple legitimate sessions may exist across:

```text
web
phone
tablet
```

unless the actual Clerk application is deliberately configured otherwise.

Logging in on Flutter must not create a second local User.

Logging in on Next.js must not create a second local User.

Both resolve through:

```text
Clerk sub
→ same users.clerk_user_id
→ same users.id
```

---

# 36. Website / Mobile Identity Invariant

Mandatory testable invariant:

```text
Clerk user_A
```

from any supported client maps to:

```text
Laravel user ID 42
```

every time.

Do not let client type participate in identity generation.

Never create:

```text
user_A_web
user_A_mobile
```

application identities.

---

# 37. Logout Semantics

Because `AGENTS.md` defines Phase 4.3 as Login/Logout, document and implement the backend-side logout semantics appropriate to Clerk.

The primary logout/session termination belongs to Clerk.

Do not implement:

```text
DELETE local user
```

on logout.

Do not clear application history.

Do not destroy all sessions unless the user explicitly chose global sign-out.

Expected ordinary logout concept:

```text
client
   ↓
terminate current Clerk session
   ↓
discard local client session state
   ↓
future Laravel requests without valid session
→ 401
```

---

# 38. Current Session vs All Sessions

Do not conflate:

```text
sign out current session
```

with:

```text
revoke all sessions
```

The default customer logout should affect the intended current session only unless Clerk/client behavior explicitly selects global sign-out.

Global account/session revocation belongs to dedicated security/admin workflows.

---

# 39. Laravel Logout Endpoint

Review the Phase 4.1 AUTH endpoint matrix.

If the old Laravel logout endpoint has been retired:

do not keep an endpoint that simply returns success while the Clerk session remains valid.

That would create false logout semantics.

If a Laravel logout endpoint remains for orchestration, it must actually coordinate the Clerk session revocation according to the approved architecture.

Never claim logout succeeded while the credential remains usable.

---

# 40. Token After Logout

Tests should establish expected behavior:

```text
valid session
→ authenticated request works

session revoked / signed out
→ future validly checked request no longer authenticates
```

Be aware that exact revocation timing depends on Clerk session-token semantics and token lifetime.

Document the actual behavior observed/specified by Clerk rather than inventing instantaneous guarantees.

---

# 41. Expired Session

An expired Clerk session token must map to the existing application auth error semantics.

Prefer the already-approved error vocabulary such as:

```text
SESSION_EXPIRED
INVALID_AUTHENTICATION
AUTHENTICATION_REQUIRED
```

based on the existing contract.

Do not expose raw Clerk error codes as the application's API contract.

---

# 42. Missing Authentication

Protected endpoint with no Clerk credential:

```text
401 AUTHENTICATION_REQUIRED
```

Do not return:

```text
403
```

for a caller who is not authenticated.

Preserve the project error semantics.

---

# 43. Invalid Authentication

Malformed, forged, wrong-issuer, wrong-authorized-party, or otherwise invalid credentials must fail safely.

Do not downgrade an invalid token to:

```text
anonymous user
```

on a protected endpoint.

For endpoints that optionally support authentication, define explicit behavior:

* no credential → anonymous;
* valid credential → authenticated;
* invalid credential → authentication failure.

Never silently ignore a supplied invalid credential.

---

# 44. Optional Authentication Middleware

Some routes support both anonymous and authenticated callers:

```text
POST /requests
POST /enquiries
```

Provide a clean optional-authentication mechanism if required.

Behavior:

```text
no token
→ continue anonymous

valid token
→ resolve local User

invalid supplied token
→ reject
```

Do not create separate duplicate request/enquiry controllers for anonymous and authenticated modes.

---

# 45. Public Catalog Must Bypass Authentication

Do not require Clerk middleware for:

```text
GET products
GET product detail
GET categories
search
```

The project explicitly requires public catalog pages to remain no-auth and cacheable.

Do not perform JIT user provisioning merely because a browser has a Clerk cookie while requesting public catalog data.

Public catalog requests should remain customer-state-free where practical.

---

# 46. Session Token Must Not Enter Cache Keys/Public Caches

Authenticated responses must remain private.

Never allow:

```text
Authorization
session token
local user ID
```

to leak into shared CDN caches.

Existing private-resource cache rules remain authoritative.

---

# 47. CORS

Configure/prepare CORS so future approved web/mobile clients can send:

```http
Authorization: Bearer ...
```

to Laravel.

Do not use permissive:

```text
Access-Control-Allow-Origin: *
```

together with credential assumptions.

Use environment-specific allow-lists.

Do not broaden existing CORS beyond actual application clients.

---

# 48. CSRF Boundary

Bearer-token API authentication and browser-cookie authentication have different CSRF characteristics.

Document that Laravel API customer authentication is Clerk-token based.

Do not globally disable CSRF protections for unrelated cookie-authenticated routes.

Authorized-party validation remains an important defense at the Clerk token boundary.

---

# 49. Session Tokens Must Never Be Logged

Audit middleware/logging for:

```text
Authorization
Bearer token
__session
JWT
```

Ensure they are never written to:

* application logs;
* error context;
* request dumps;
* telemetry;
* test snapshots.

Logging authentication failure category is acceptable.

Logging the credential is not.

---

# 50. Error Mapping Boundary

Create or finalize a centralized mapper such as:

```text
Clerk authentication failure
        ↓
project auth error
```

Possible inputs:

```text
missing
expired
malformed
invalid signature
wrong issuer
wrong authorized party
revoked/invalid session
provider temporarily unavailable
```

Outputs must use existing project error vocabulary.

Do not scatter Clerk exception handling across controllers.

---

# 51. Do Not Leak Clerk Internals

API responses must not reveal:

```text
Clerk SDK exception class
JWKS URL
public-key parsing details
Clerk Backend API response
raw JWT claim
session ID
Clerk user ID
```

unless a specific value is part of an explicitly approved representation.

Customer-facing errors remain provider-independent.

---

# 52. Login Enumeration

If the actual future frontend uses a custom sign-in flow, it must follow the configured Clerk user-enumeration protection.

Clerk currently supports a privacy-preserving sign-in-or-up pattern where verification happens before revealing whether an account must be created.

Do not implement custom account-existence checks in Laravel.

Prefer Clerk's prebuilt authentication components in the future website phase unless a custom UI is genuinely required.

---

# 53. Prefer Clerk Components Later

Record for Phase 15.1:

For the website's eventual registration/login UI, prefer official Clerk components or documented flows unless the design requires a custom authentication UI.

This reduces custom authentication logic.

But do not implement those components now.

---

# 54. Password Handling

If password authentication is enabled in the Clerk instance:

```text
password
```

must travel only through Clerk's supported authentication flow.

Laravel must never receive it as part of:

```text
/api/v1 auth request
application log
local users record
```

Do not build password validation inside Laravel.

---

# 55. Passwordless Handling

If the Clerk application uses:

```text
email OTP
email link
phone OTP
passkey
```

Laravel does not need special credential-validation code for each strategy.

Once Clerk establishes a valid session:

```text
Laravel sees the same verified session-token boundary.
```

This provider independence is intentional.

---

# 56. OAuth / Social Sign-In

If existing Clerk configuration enables social sign-in:

do not implement provider-specific Laravel identity mappings.

Example:

```text
Google → Clerk user_ABC
Email/password → Clerk user_ABC
```

Laravel still sees:

```text
user_ABC
```

Do not create:

```text
google_user_id
facebook_user_id
```

on Laravel users unless a future requirement specifically needs them.

Clerk owns identity-provider linking.

---

# 57. Session Tasks / Incomplete Authentication

Clerk may require additional session tasks before the session is fully usable depending on configuration.

Do not assume:

```text
session exists
=
all security requirements complete
```

Use current Clerk semantics for determining whether the request is fully authenticated/eligible.

Do not bypass mandatory verification/MFA/session tasks in Laravel.

---

# 58. MFA

Do not implement custom MFA.

If MFA is enabled in Clerk:

Clerk owns the MFA challenge.

Laravel receives the resulting authenticated session once completed.

Detailed MFA policy remains outside this phase unless Phase 4.1 explicitly made it a V1 requirement.

---

# 59. Device Trust

Do not recreate Clerk Device Trust/client-trust functionality.

If enabled, respect Clerk's sign-in/session state.

Do not bypass a `needs_client_trust` or equivalent incomplete sign-in condition by creating a local Laravel session.

---

# 60. Authentication Middleware Placement

Follow existing project structure, likely under:

```text
backend/laravel/app/Http/Middleware/
```

with supporting services under the already-selected Phase 4.1 structure.

Example conceptual split:

```text
AuthenticateWithClerk
    ↓
ClerkTokenVerifier
    ↓
LocalUserResolver
    ↓
Laravel auth context
```

Keep middleware orchestration small.

---

# 61. Middleware Alias

Register a clear middleware alias where appropriate, for example conceptually:

```text
auth.clerk
```

or the naming already approved in Phase 4.1.

Do not scatter direct token verification across route definitions.

Protected endpoint groups should use one standardized authentication boundary.

---

# 62. Route Classification Review

Review the existing V1 route inventory and classify:

### PUBLIC

No authentication:

```text
catalog/category/product/search
```

### OPTIONAL AUTH

```text
request creation
enquiry creation
guest/cart-sensitive flows where contract allows
```

### REQUIRED AUTH

```text
/me
checkout
own orders
order cancellation
notifications
own requests/enquiries
profile
```

Do not change endpoint business accessibility while applying middleware.

---

# 63. Do Not Implement Authorization Policies Yet

Phase 4.10 owns full policies/permissions.

Phase 4.3 may establish:

```text
authenticated local User
```

but should not implement every domain authorization policy.

Tiny existing checks required to preserve current endpoint safety are acceptable.

Do not leak Phase 4.10 into this phase.

---

# 64. Current-User Smoke Endpoint

If an existing:

```http
GET /api/v1/me
```

implementation already exists sufficiently to validate the authenticated principal, use it as the authentication smoke boundary.

Do not invent:

```http
GET /api/v1/auth/whoami
```

just for testing unless Phase 4.1 explicitly approved such an endpoint.

`/me` is already the canonical application self-context.

---

# 65. `/me` Identity

For a protected `/me` request:

```text
Clerk token.sub
    ↓
users.clerk_user_id
    ↓
Laravel User
    ↓
GET /me
```

Never allow:

```http
GET /me?user_id=123
```

to substitute identity.

Never accept:

```json
{"user_id": "..."}
```

for current-user selection.

---

# 66. Existing Local User Without Clerk Mapping

If a protected request has a valid Clerk identity but collides with an old local account having the same email and no Clerk mapping:

use the Phase 4.2 safe conflict behavior.

Do not add sign-in-time email auto-linking.

Do not weaken this security invariant for UX convenience.

---

# 67. Existing Clerk Mapping with Changed Email

If:

```text
users.clerk_user_id = user_ABC
```

but Clerk email has changed:

the identity is still:

```text
user_ABC
```

Do not create a second Laravel account.

Email synchronization follows the approved Phase 4.1/4.6 policy.

Sign-in should not rebind identity based on email differences.

---

# 68. Clerk Backend API Availability

For already mapped users, normal sign-in request resolution should ideally not require Clerk Backend API calls beyond cryptographic authentication.

Expected hot path:

```text
verify token
→ local user lookup
→ continue
```

not:

```text
verify token
→ call Clerk Users API
→ local user lookup
→ every request
```

This reduces latency and external dependency.

---

# 69. User Data Freshness

Do not synchronously refresh full Clerk profile data on every authenticated request.

Only synchronize identity data according to the field-ownership strategy established earlier.

Profile synchronization belongs mainly to:

```text
webhook reconciliation
explicit profile/security flow
bounded JIT reconciliation
```

not every API hit.

---

# 70. Tests — Missing Credential

Protected endpoint:

```text
no Authorization token
```

Expected:

```text
401 AUTHENTICATION_REQUIRED
```

Verify no local user provisioning occurs.

---

# 71. Tests — Valid Existing Customer

Given:

```text
valid Clerk session
sub = user_A
```

and:

```text
users.clerk_user_id = user_A
```

verify:

* request authenticates;
* correct local User is bound;
* CUSTOMER role remains;
* protected endpoint works.

---

# 72. Tests — First Authenticated Request

Given:

```text
valid Clerk session
sub = user_NEW
```

and no local mapping:

verify:

* Phase 4.2 provisioner invoked;
* one local CUSTOMER created;
* same request proceeds under that User;
* no second auth credential created.

---

# 73. Tests — Invalid Signature

Provide token failing cryptographic verification.

Verify:

* 401;
* no local lookup/provisioning;
* no raw provider error exposed.

---

# 74. Tests — Expired Token

Provide expired token.

Verify expected existing project error:

```text
SESSION_EXPIRED
```

or exact approved equivalent.

Do not provision.

---

# 75. Tests — Wrong Issuer

Token cryptographically valid but issued by an unapproved issuer:

reject.

Do not provision.

---

# 76. Tests — Unauthorized Party

Token has unapproved `azp` / authorized party:

reject.

This must not become an authenticated Laravel User.

---

# 77. Tests — Client-Supplied Identity Tampering

Send:

```json
{
  "user_id": "admin-id",
  "clerk_user_id": "user_admin",
  "role": "ADMIN"
}
```

alongside a valid CUSTOMER session.

Verify authenticated identity remains the token-bound local Customer.

No identity substitution.

---

# 78. Tests — Same Identity Across Client Types

Simulate two valid session tokens belonging to the same:

```text
sub = user_A
```

representing different sessions/devices.

Both must resolve to the same:

```text
users.id
```

Multi-session does not mean multi-account.

---

# 79. Tests — Existing Admin/Staff

Valid session for an existing mapped Staff/Admin:

authentication should resolve that existing user.

Do not assign CUSTOMER.

Do not mutate role.

This test verifies auth and provisioning stay separate.

---

# 80. Tests — Optional Authentication

For an optional-auth route:

### No token

continue anonymous.

### Valid token

attach authenticated User.

### Invalid token supplied

reject authentication rather than silently treating caller as anonymous.

---

# 81. Tests — Public Route

Call public catalog endpoint without token.

Verify:

* succeeds according to catalog semantics;
* Clerk verifier not required;
* no local User created.

This prevents accidental global auth middleware.

---

# 82. Tests — Logout / Revocation

Using fake verifier/session abstraction:

```text
before logout
→ session accepted

after simulated Clerk revocation/termination
→ authentication rejected
```

Do not require real Clerk network access in ordinary PHPUnit tests.

---

# 83. Tests — Authentication Provider Failure

Simulate verifier infrastructure failure where appropriate.

Verify:

* request fails safely;
* user is not treated as anonymous;
* no user is provisioned from unverified data;
* secrets/provider internals not exposed.

---

# 84. Tests Must Be Offline

Normal PHPUnit suite must not call Clerk.

Use test doubles:

```text
FakeClerkTokenVerifier
FakeClerkUserGateway
```

or existing Phase 4.2 equivalents.

Real Clerk tests are deferred to Phase 4.12 or dedicated integration environment.

---

# 85. Do Not Add Test Authentication Headers

Never create a production middleware bypass such as:

```text
X-Test-User
X-Debug-Auth
X-Role
```

Tests should replace dependencies through the Laravel container.

No runtime backdoor.

---

# 86. Rate Limiting

Do not fully implement Phase 4.11 yet.

But preserve/record that:

* Clerk owns credential-level abuse controls for Clerk authentication;
* Laravel still owns application endpoint throttling.

Do not introduce redundant password-login throttles in Laravel if Laravel no longer receives passwords.

---

# 87. Logging

May log:

```text
authentication success/failure category
request_id
local user ID where appropriate
```

Do not log:

```text
session token
Authorization header
password
Clerk secret key
full JWT
full Clerk User payload
```

Avoid logging external identifiers unless operationally needed.

---

# 88. Metrics / Diagnostics

If existing logging/metrics infrastructure supports it, distinguish high-level categories:

```text
auth.missing
auth.invalid
auth.expired
auth.user_resolution_failed
auth.provisioning_failed
```

Do not add a new observability platform.

Detailed monitoring remains a later production phase.

---

# 89. Documentation Updates

Update consolidated documentation to reflect the implemented flow.

Likely:

```text
docs/api/api-conventions.md
docs/domain/business-rules.md
docs/decisions.md
```

and the approved Clerk-related sections of:

```text
docs/api/api-contract.md
docs/api/openapi.yaml
```

where necessary.

Do not recreate obsolete custom Laravel login/password descriptions.

---

# 90. Auth Endpoint Inventory

Confirm each original `AUTH-*` endpoint remains correctly marked:

```text
retained
retired
Clerk-owned
application-owned
```

Do not recycle retired endpoint IDs.

Do not silently leave OpenAPI routes describing Laravel password login when the implementation no longer exposes them.

---

# 91. OpenAPI Security Scheme

Ensure the protected API security scheme accurately describes:

```text
Bearer token
=
Clerk-issued authenticated session token
```

rather than a Laravel-issued access token.

Do not expose internal token validation details unnecessarily.

---

# 92. Registration/Login UI Remains Future Work

Explicitly note that successful backend completion does **not** mean the website now has registration/login screens.

AGENTS.md assigns:

```text
Phase 15.1 — Registration/login UI
```

to website customer commerce.

Phase 4.3 provides the secure authentication contract those screens will later consume.

---

# 93. Flutter Remains Future Work

Do not implement secure mobile token storage now.

AGENTS.md assigns Flutter authentication/session storage to:

```text
Phase 16.5
```

Phase 4.7 will establish the API-authentication specifics required for mobile before then.

---

# 94. Code Quality

Maintain:

* cognitive complexity ≤15;
* maximum 3 returns per function where practical under project rules;
* small middleware;
* small services;
* dependency injection;
* centralized error mapping;
* centralized Clerk configuration;
* minimal comments;
* no duplicate authentication logic.

Avoid:

```text
500-line Clerk middleware
```

or controllers parsing tokens themselves.

---

# 95. Expected Production Classes

Use existing Phase 4.1/4.2 names where already implemented.

Possible responsibilities include:

```text
AuthenticateWithClerk
ClerkTokenVerifier
AuthenticatedClerkIdentity
LocalUserResolver
LocalUserProvisioner
ClerkAuthenticationExceptionMapper
```

Do not create duplicates if equivalent abstractions already exist.

Refactor rather than parallel-implement.

---

# 96. No Unnecessary Schema Changes

Expected schema change:

```text
NONE
```

unless Phase 4.2 left a clearly documented auth prerequisite.

Do not change User/RBAC schemas merely to simplify middleware.

Do not add:

```text
last_login_token
session_token
refresh_token
auth_provider_password
```

to `users`.

---

# 97. Session IDs

Do not persist Clerk session IDs in the users table merely for login.

One user may have multiple sessions.

If future security/session-management requirements need a session table, design it in the appropriate security phase.

Do not prematurely duplicate Clerk's session store.

---

# 98. No Authentication Cache by Token

Do not create an unbounded mapping cache keyed by bearer token.

If identity caching is introduced later:

* tokens must not appear in logs/cache keys visible externally;
* expiration must be respected;
* revocation semantics must be considered.

For V1 scale, simple verified request + indexed `clerk_user_id` lookup is preferred.

---

# 99. ReferenceGenerator Deferred Work

Do not mix the Group C ReferenceGenerator cleanup into this phase.

Outstanding:

```text
OrderFactory
PaymentFactory
FurnitureRequestFactory
production order/payment/request services
```

remain owned by their appropriate Group D/E/H/J work.

Authentication changes should stay focused.

---

# 100. Carry Forward Remaining Risks

Do not address unrelated Group C risks here:

```text
products.product_type / is_published → Group E 5.7
MySQL enum case behavior → Group K
MySQL CI helper failures → Group U if needed
cart user FK delete action → Phase 4.6 / Group K retention policy
```

Keep them recorded.

---

# 101. Commands / Verification

From:

```text
backend/laravel/
```

run the established project checks.

At minimum:

```bash
php artisan test
vendor/bin/pint --test
vendor/bin/phpstan analyse
composer audit
```

If migrations/configuration changed:

```bash
php artisan migrate:fresh --seed
```

must still succeed.

Use repository-defined Composer scripts where they already wrap these commands.

---

# 102. Files Changed Report

At completion report:

### Files changed

Exact paths.

### Schema changes

Expected:

```text
none
```

unless explicitly justified.

### API changes

List any auth-contract/OpenAPI corrections.

### Tests added

List authentication scenarios.

### Commands run

List exact commands and outcomes.

### Known risks

List remaining Clerk/session integration risks.

### Deferred

Explicitly state what belongs to:

```text
4.4
4.5
4.6
4.7
4.8
4.9+
```

---

# 103. Definition of Done

Phase 4.3 is complete when:

* the Clerk sign-up/sign-in responsibility is unambiguous;
* Laravel does not accept customer passwords;
* Laravel does not issue a parallel customer auth credential;
* protected requests accept and verify Clerk session credentials;
* verified Clerk `sub` resolves to the correct local User;
* Phase 4.2 provisioning is reused for first authenticated requests;
* public signup can still create CUSTOMER only;
* sign-in does not mutate roles;
* one Clerk identity maps to one local User across sessions/devices;
* missing credentials return `401 AUTHENTICATION_REQUIRED`;
* expired/invalid credentials map to project-level auth errors;
* unauthorized issuers/authorized parties are rejected;
* optional-auth routes distinguish no-token from invalid-token;
* public catalog remains unauthenticated;
* Clerk provider errors do not leak;
* tokens/secrets are not logged;
* logout/session termination semantics are consistent with Clerk;
* ordinary tests require no real Clerk network access;
* backend PHPUnit tests pass;
* Pint passes;
* PHPStan passes;
* Composer/security checks pass;
* documentation/OpenAPI no longer describes obsolete Laravel-owned login behavior;
* no frontend authentication UI was implemented prematurely.

---

# 104. Out of Scope

Do not implement:

* password recovery;
* detailed email verification policy;
* profile editing;
* account deletion;
* full RBAC policies;
* role-management UI;
* rate-limiting implementation;
* Next.js Clerk UI;
* Flutter Clerk UI;
* Flutter secure storage;
* cart merge;
* admin UI;
* MFA configuration;
* Clerk Organizations;
* Clerk webhook reconciliation;
* payment authentication.

---

# 105. STOP Condition

STOP once a Clerk-authenticated customer session can be securely translated into the correct authenticated Laravel User for protected API requests, registration/sign-in semantics are documented, logout semantics are correct, and all Phase 4.3 checks pass.

Do not continue automatically.

The next roadmap phase is:

**Phase 4.4 — Password Recovery with Clerk**

unless the project-owner-approved Clerk roadmap explicitly renames that phase while preserving the same dependency order.
