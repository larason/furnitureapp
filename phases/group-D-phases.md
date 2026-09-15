# Phase 4.2 — Customer Registration with Clerk / Local User Provisioning

## Purpose

Implement the backend portion of customer registration after the Phase 4.1 Clerk architecture decision.

Clerk is responsible for creating and authenticating the customer's external identity.

Laravel is responsible for creating the corresponding **local application User** and assigning the new user the application role:

```text
CUSTOMER
```

The registration architecture is:

```text
Customer
    ↓
Clerk sign-up
    ↓
Clerk creates User
    ↓
Clerk session established
    ↓
Laravel receives verified Clerk identity
    ↓
resolve users.clerk_user_id
    ↓
local user exists?
 ┌───────────┴───────────┐
 YES                     NO
  │                       │
reuse user        securely provision user
                          │
                          ├── copy approved identity snapshot
                          ├── assign CUSTOMER
                          └── create required customer profile
                                  │
                                  ▼
                         local application User
```

The fundamental rule is:

> A successful Clerk identity does not become an application user through email matching. It becomes an application user through the verified Clerk User ID.

---

# 1. Phase Scope

Implement:

* Clerk PHP backend dependency where required;
* Laravel Clerk configuration;
* `users.clerk_user_id` mapping;
* schema adaptations approved in Phase 4.1;
* verified Clerk identity representation;
* Clerk Backend User gateway;
* local customer provisioner;
* default CUSTOMER role assignment;
* idempotent provisioning;
* concurrency-safe provisioning;
* minimum required identity snapshot;
* tests for the provisioning boundary.

Do not build frontend sign-up UI yet.

Do not implement the complete login/logout lifecycle yet.

Do not implement password recovery yet.

Do not implement full `/me` profile operations yet.

Do not implement Flutter or Next.js Clerk integration yet.

---

# 2. Dependencies

Required:

* Group C complete;
* Phase 4.1 complete;
* Clerk architecture ADR accepted;
* Clerk selected as authentication authority;
* local Laravel User retained as application principal;
* Clerk User ID chosen as external identity mapping;
* Laravel RBAC retained as authorization authority;
* Clerk metadata explicitly non-authoritative for application roles;
* Phase 4.1 AUTH endpoint migration/retirement matrix complete;
* Phase 4.1 data ownership matrix complete.

Before changing code, read the Phase 4.1 documentation changes.

Do not reinterpret decisions already settled there.

---

# 3. Active Project Directory

Work only in the active backend unless a documentation update requires repository-root files:

```text
furniture-ecommerce/
└── backend/
    └── laravel/
```

Do not modify:

```text
frontend/web/
frontend/app/
```

during this phase.

Do not run:

```bash
clerk init
```

at the repository root.

The frontend Clerk initialization belongs to later phases.

---

# 4. Review Current Clerk Documentation First

If Clerk MCP/Skills are installed, use them before implementation.

Verify:

* current official PHP Backend SDK API;
* current session-token verification guidance;
* current Backend User retrieval API;
* current Clerk User representation;
* Clerk application configuration needed by the integration.

Do not guess method/class names from JavaScript documentation.

Do not translate JavaScript SDK calls literally into PHP.

Use the current PHP SDK/API contract.

---

# 5. Install the Official Clerk PHP Backend SDK

From:

```text
backend/laravel/
```

install the official backend dependency:

```bash
composer require clerkinc/backend-php
```

Do not install JavaScript Clerk libraries in Laravel.

Do not install:

```text
@clerk/backend
@clerk/nextjs
@clerk/clerk-react
```

inside the Laravel project.

The PHP integration must remain native to the backend.

After dependency installation:

* inspect the resulting Composer changes;
* ensure the existing supported PHP version satisfies Clerk's requirements;
* do not upgrade unrelated dependencies unnecessarily;
* run Composer security checks according to existing project conventions.

---

# 6. Clerk Configuration

Create a small Laravel configuration boundary, for example:

```text
config/clerk.php
```

Centralize Clerk configuration there.

Configuration may include only values required by the selected implementation, such as:

```text
secret key environment reference
publishable key environment reference
JWT/public verification key
issuer
authorized parties
audience if adopted
Backend API URL if needed
```

Use existing project environment conventions.

Do not access `env()` throughout application services.

Application classes should read configuration through Laravel configuration.

---

# 7. Secret Handling

Never:

* print `.env`;
* log `.env`;
* print Clerk secret keys;
* commit Clerk secret keys;
* return them through API responses;
* put them in tests;
* place them in documentation.

Adding placeholder names to `.env.example` is allowed if consistent with project conventions.

For example:

```text
CLERK_SECRET_KEY=
CLERK_PUBLISHABLE_KEY=
CLERK_JWT_KEY=
```

only when those variables are actually required by the chosen architecture.

Never populate example files with real credentials.

If local secrets are unavailable, implementation and tests must still work through dependency injection/fakes.

---

# 8. Database Migration — Clerk User Mapping

Create a **new Group D migration**.

Do not edit the original Group C users migration.

Add the approved external identity mapping:

```text
users.clerk_user_id
```

Requirements:

* nullable initially where existing local development/test users require compatibility;
* unique;
* indexed through the unique constraint;
* sufficiently sized for Clerk identifiers;
* server-controlled;
* treated as immutable identity once linked.

Do not expose it as a writable API field.

Do not expose it in ordinary customer serialization.

---

# 9. Existing Password / Remember-Token Schema

Follow the exact Phase 4.1 migration decision.

Because Clerk owns customer credentials, do not create fake Laravel passwords merely to satisfy the old schema.

If Phase 4.1 decided that the Laravel password column must become nullable or be removed, implement that through a **new migration** now if necessary for JIT provisioning.

Likewise handle `remember_token` according to the approved design.

Never:

```php
password => Str::random(...)
```

just to satisfy a non-null database constraint.

Never retrieve a Clerk password.

Never copy a Clerk password into Laravel.

Never generate an unused parallel Laravel credential.

The end result must not accidentally preserve a usable second authentication system.

---

# 10. Local User Model

Update the local User model for Clerk mapping.

The User model must continue to represent the **application user**, not a Clerk SDK object.

Add the minimum required Clerk mapping behavior.

Do not turn the model into a Clerk API client.

Do not scatter Clerk API calls throughout the User model.

The relationship remains:

```text
Clerk User
    ↓ clerk_user_id
Laravel User
    ↓ users.id
Domain
```

---

# 11. `clerk_user_id` Is Internal

Treat:

```text
clerk_user_id
```

as INTERNAL application data.

Do not include it automatically in:

```text
GET /me
orders
notifications
requests
enquiries
admin resource responses
```

unless an explicitly authorized administrative use is later approved.

The client normally needs the application user identity, not the external-provider identifier.

---

# 12. Verified Clerk Identity Value Object

Create a small internal representation of a successfully authenticated Clerk request.

Example concept:

```text
AuthenticatedClerkIdentity
```

It should contain only what the authentication/provisioning boundary actually needs.

Typical fields:

```text
clerk_user_id
session_id
issuer
```

and only additional verified claims that have a concrete purpose.

Do not dump the entire JWT payload into application services.

Do not make authorization depend on arbitrary claims.

---

# 13. Token Verification Prerequisite

Provisioning may only run after the request's Clerk credential has been cryptographically verified.

Never provision based on:

```text
request.body.clerk_user_id
request.body.email
request.header.X-User-Id
decoded-but-unverified JWT
Clerk public metadata role
```

The trusted identity source is the verified Clerk session token.

Clerk session tokens are JWTs and their `sub` identifies the Clerk user.

The verifier must validate the requirements established in Phase 4.1, including applicable:

```text
signature
algorithm
exp
nbf
iss
azp / authorized party
audience, if configured
```

Do not merely Base64-decode JWT claims.

---

# 14. Authentication Verification Implementation Boundary

Implement or prepare the smallest reusable verification boundary required for Phase 4.2.

Prefer an interface such as:

```text
ClerkTokenVerifier
```

returning:

```text
AuthenticatedClerkIdentity
```

Do not combine:

```text
JWT verification
Clerk Backend API calls
user provisioning
role assignment
authorization
```

inside one giant middleware method.

Keep responsibilities separate.

---

# 15. Use Supported Cryptographic Verification

Use Clerk-supported verification mechanisms or standards-compliant JWT/JWKS libraries used by the official integration.

Do not hand-write cryptography.

Do not manually implement:

* RSA signature primitives;
* ASN.1 parsing;
* JWK conversion;
* token signing.

If the current PHP SDK exposes the required request/session verification facility, use it.

If it does not provide the necessary Laravel-oriented verifier, build a narrow adapter using Clerk's documented JWT/JWKS process and maintained libraries.

Document the selected mechanism in `docs/decisions.md`.

---

# 16. Networkless Authentication Preference

For already-known local users, ordinary authenticated Laravel requests should not require a Clerk Backend API request merely to prove authentication.

Use cryptographic token verification.

The ordinary path should become:

```text
session token
    ↓
verify locally/JWKS
    ↓
sub
    ↓
users.clerk_user_id
    ↓
Laravel User
```

Clerk Backend API calls should be necessary only when additional server-authoritative Clerk User information is actually required.

First-time local provisioning is one such case.

---

# 17. Clerk Backend User Gateway

Create a small integration boundary, for example:

```text
ClerkUserGateway
```

Responsibilities:

```text
get Clerk user by verified Clerk User ID
return minimum normalized identity information
map Clerk SDK/network failures into internal integration errors
```

It must not:

* assign Laravel roles;
* create Laravel users;
* authorize application actions;
* access Orders/Carts/etc.

---

# 18. Retrieve User by Clerk User ID

On first-time provisioning, retrieve the Clerk Backend User using the verified:

```text
sub
```

Do not retrieve by email.

Conceptually:

```text
verified sub
    ↓
Clerk Backend API getUser(sub)
    ↓
Clerk backend User
```

The Clerk Backend User may provide information such as:

* primary email;
* primary phone;
* full name;
* verified identifier information.

Use only fields that Phase 4.1 designated for the local projection.

---

# 19. Never Search by Email to Link Accounts

This is mandatory.

Do not implement:

```php
User::where('email', $clerkEmail)->first()
```

followed by automatic linking.

The flow must not be:

```text
same email
= same account
```

Instead:

```text
same clerk_user_id
= same external identity
```

Email is not ownership proof.

---

# 20. Existing Local Email Collision

A legitimate provisioning case may discover:

```text
Clerk User A
email = customer@example.com
```

while Laravel already contains:

```text
Local User
clerk_user_id = NULL
email = customer@example.com
```

Do **not** silently attach them.

Do **not** overwrite the existing local user.

Do **not** create an identity takeover vulnerability.

This is an account-linking/migration conflict.

Handle according to the conflict behavior approved in Phase 4.1.

If no behavior was approved, fail safely and record the conflict rather than linking by email.

Existing seeded/development identities may be reset or recreated explicitly in non-production environments instead of weakening production identity rules.

---

# 21. Customer Provisioner Service

Create a dedicated application service, for example:

```text
LocalUserProvisioner
```

or another name consistent with project conventions.

Input:

```text
verified Clerk identity
+
normalized authoritative Clerk profile snapshot
```

Output:

```text
local Laravel User
```

Responsibilities:

* find local user by `clerk_user_id`;
* return existing user when found;
* otherwise provision new customer safely;
* assign CUSTOMER;
* create required customer profile if Phase 4.1/Group C requires one;
* persist initial approved identity snapshots;
* enforce idempotency;
* protect against concurrency.

It must not own token parsing.

---

# 22. Provisioning Algorithm

Implement conceptually:

```text
1. Receive verified Clerk User ID.
2. Lookup users.clerk_user_id.
3. If found:
      return that local User.
4. If absent:
      retrieve authoritative Clerk Backend User.
5. Normalize only approved fields.
6. Begin database transaction.
7. Re-check clerk_user_id inside transaction.
8. If another request created it:
      return existing User.
9. Validate no unsafe local identity conflict.
10. Create local User.
11. Assign CUSTOMER role.
12. Create customer profile if required.
13. Commit.
14. Return local User.
```

Do not skip the re-check.

The unique index is the final race-condition safety boundary.

---

# 23. Concurrent First Requests

Assume that after signup the frontend may immediately issue several API requests simultaneously.

Example:

```text
GET /me
GET /notifications
GET /me/cart
```

All three could attempt first-time provisioning.

The system must still create exactly:

```text
1 local User
```

not three.

Use:

* unique `clerk_user_id`;
* transaction;
* transactional re-check or safe upsert strategy;
* duplicate-key reconciliation where appropriate.

Do not rely on an in-process static array or application memory for deduplication.

---

# 24. Provisioning Must Be Idempotent

For:

```text
Clerk user_ABC
```

calling the provisioner repeatedly must always resolve to the same local user.

Test:

```text
provision(user_ABC)
provision(user_ABC)
provision(user_ABC)
```

Expected:

```text
same users.id
one database user
one CUSTOMER assignment
one customer profile
```

No duplicated RBAC assignments.

No duplicated profiles.

---

# 25. Default Role Assignment

Public Clerk signup creates an application:

```text
CUSTOMER
```

only.

Never derive role from:

```text
Clerk unsafeMetadata
Clerk publicMetadata
request body
query string
custom header
email domain
phone number
client route
```

Never allow:

```text
role=STAFF
role=ADMIN
```

during ordinary customer provisioning.

---

# 26. Preserve Existing RBAC Model

Use the RBAC implementation chosen in Phase 3.2.

Do not add:

```text
users.role
users.is_admin
users.is_staff
```

to simplify provisioning.

Assign the existing CUSTOMER role through the project's established RBAC layer.

Do not introduce a second role system.

---

# 27. Role Assignment Must Be Atomic

New-user creation and CUSTOMER assignment should succeed or fail together.

Do not leave:

```text
User created
+
no role
```

because role assignment failed afterward.

Use a transaction where the selected RBAC implementation permits it.

If profile creation is mandatory, include it in the same atomic provisioning boundary.

---

# 28. Existing User Role Must Never Be Downgraded

When:

```text
users.clerk_user_id = user_ABC
```

already exists, provisioning must not blindly execute:

```text
assignRole(CUSTOMER)
```

on every request.

A mapped STAFF or ADMIN identity must stay STAFF/ADMIN according to Laravel RBAC.

Provisioning assigns CUSTOMER only at **new public customer creation**.

Existing-user authentication does not change roles.

---

# 29. Customer Profile Creation

Follow the Group C and Phase 4.1 profile decision.

If every newly registered customer requires:

```text
customer_profiles
```

then create it as part of the provisioning transaction.

Do not create StaffProfile.

Do not create AdminProfile.

Do not put authentication credentials in CustomerProfile.

---

# 30. Local Identity Snapshot

Persist only the fields approved by the Phase 4.1 ownership matrix.

Typical initial projection may include:

```text
name
email
phone
```

but use the actual approved ownership decision.

Classification should remain clear:

```text
clerk_user_id       → external identity mapping
email               → Clerk-owned local snapshot
name                → according to Phase 4.1
phone               → according to Phase 4.1
role                → Laravel RBAC
```

Do not copy the entire Clerk User JSON into Laravel.

---

# 31. Primary Email Selection

If local email is required:

* use Clerk's primary email;
* do not arbitrarily choose the first array entry;
* use the Backend User's primary email identifier/accessor;
* apply the verification rule approved in Phase 4.1.

Do not trust an email sent separately by the client.

---

# 32. Missing Primary Email

The current application contract expects an email-based customer identity.

If Clerk provisioning returns no usable primary email:

* do not invent one;
* do not use `clerk_user_id@example.invalid`;
* do not bypass database/domain requirements;
* do not silently create an incomplete user unless Phase 4.1 explicitly permits it.

Follow the approved profile-completion/registration failure semantics.

If Phase 4.1 failed to define this case, record the contract mismatch and stop that provisioning path safely.

---

# 33. Name Handling

If name is locally required, derive it only according to the Phase 4.1 field ownership decision.

Possible Clerk sources may include:

```text
full name
first name + last name
```

Do not fabricate realistic personal names.

Do not use email local-part as a hidden fallback unless explicitly approved.

If Clerk signup must collect name before successful application registration, configure that requirement in the future frontend Clerk sign-up phase rather than weakening Laravel validation.

---

# 34. Phone Handling

If phone is optional in the finalized local projection:

```text
phone = NULL
```

is acceptable.

If the project's updated registration contract requires phone:

* use Clerk's authoritative primary phone if available;
* do not accept an unrelated client `phone` as authenticated identity data unless the updated contract explicitly permits it.

Do not invent phone numbers.

---

# 35. Email Verification State

Do not independently recreate Clerk email verification.

If a local verification snapshot exists:

* derive it from Clerk's authoritative verification state;
* never trust `email_verified` from the request;
* do not create a Laravel verification token.

Full verification policy belongs to Phase 4.5.

Phase 4.2 only needs enough state to correctly provision the application User according to the Phase 4.1 decision.

---

# 36. Do Not Create Laravel Passwords

A newly provisioned Clerk customer must not receive:

```text
Laravel password
password reset token
remember token
Sanctum password credential
```

as part of provisioning.

Do not hash a dummy password.

Do not store Clerk credentials.

Clerk owns authentication credentials.

---

# 37. No Laravel Session Creation

Successful local provisioning must not issue:

* Laravel session cookie;
* Sanctum personal access token;
* Passport token;
* custom JWT;
* refresh token.

The existing Clerk session remains the authentication session.

Provisioning creates an application-domain identity, not a second login.

---

# 38. JIT Provisioning Integration Point

Use the integration point established in Phase 4.1.

Preferred model:

```text
verified authenticated request
    ↓
resolve local user by clerk_user_id
    ↓
JIT provision if absent
    ↓
attach local User to Laravel auth/request context
```

Do not invent a public password registration endpoint.

If the Phase 4.1 contract matrix retired the old Laravel `AUTH-001` registration endpoint, do not recreate it.

If Phase 4.1 retained a bootstrap/provisioning operation, implement exactly that documented shape.

---

# 39. Avoid an Explicit `/users/sync` API Unless Approved

Do not casually introduce endpoints such as:

```text
POST /api/v1/users/sync
POST /api/v1/auth/clerk
POST /api/v1/auth/provision
```

unless Phase 4.1 explicitly added one through the post-freeze process.

JIT provisioning can normally remain an internal authentication-boundary concern.

Avoid increasing the public API surface without need.

---

# 40. Registration Response Semantics

Clerk handles the actual credential registration response.

Laravel should not pretend to have created the Clerk account.

If an application endpoint participates after signup, its response concerns the local application User only.

Do not return:

```text
Clerk secret data
Clerk raw Backend User object
session token
password state
provider internals
```

Use the project's approved application representation.

---

# 41. Error Mapping

Translate integration failures into project-level errors.

Do not expose Clerk SDK exceptions directly.

Important classes include:

```text
invalid/expired authentication
Clerk identity unavailable
local identity conflict
local persistence failure
role assignment failure
temporary Clerk Backend API failure
```

Use existing API error vocabulary where applicable.

Do not invent new externally visible error codes casually because the V1 registry is CLOSED.

If a genuinely new client-visible error is unavoidable, follow the formal post-freeze process.

---

# 42. Clerk Backend API Failure

First-time provisioning may require:

```text
GET Clerk User by sub
```

If Clerk's Backend API is temporarily unavailable:

* do not fabricate local identity data;
* do not provision from request body values;
* do not fall back to email matching;
* leave the database unchanged;
* return the approved temporary failure.

Existing mapped users should preferably remain authenticatable without this Backend API round trip when local token verification succeeds.

---

# 43. Do Not Depend on Webhooks

Do not require `user.created` webhook delivery before provisioning.

Registration must work even when:

```text
Clerk user.created webhook
```

has not yet arrived.

Clerk documents `user.created`, `user.updated`, and `user.deleted` webhooks for synchronizing external databases, but webhook delivery is asynchronous.

Webhooks become reconciliation support later.

Do not implement webhook handlers in Phase 4.2.

---

# 44. Existing Mapping Is Authoritative

If:

```text
users.clerk_user_id = user_ABC
```

exists, that mapping is authoritative.

Do not compare the request email and switch to another local user.

Do not rebind:

```text
user_ABC
```

to another local user because an email changed.

Identity binding changes require a dedicated secure administrative/account-linking process.

---

# 45. Clerk User Deletion

Do not implement deletion synchronization in Phase 4.2.

A Clerk user deletion must not currently cause automatic hard deletion of:

* User;
* Orders;
* Requests;
* Enquiries;
* historical commerce.

Account-retention policy remains assigned to Phase 4.6/later management phases.

---

# 46. Guest Cart Preservation

Do not modify guest-cart behavior during registration.

Registration may eventually lead to:

```text
guest cart
+
new authenticated customer
→ CART-005 merge
```

but the merge belongs to the Cart/auth integration stage.

Phase 4.2 only ensures the authenticated customer has a valid local User.

Do not silently attach guest carts by browser state or email.

---

# 47. Furniture Requests / Enquiries

Do not automatically attach previous anonymous records to the newly registered customer.

Specifically, never perform:

```text
UPDATE enquiries
SET user_id = new_user
WHERE email = new_user.email
```

or equivalent for furniture requests.

Historical anonymous records remain anonymous unless a future secure claim mechanism is explicitly designed.

---

# 48. Authentication Context Binding

Once a local user has been resolved/provisioned, bind that local user into Laravel's authenticated request context using the architecture established in Phase 4.1.

Downstream code should be able to reason about:

```text
authenticated Laravel User
```

without repeatedly interacting with Clerk.

Domain services should not need to know:

```text
JWT
Clerk SDK
JWKS
session token
```

This isolates external identity-provider concerns at the boundary.

---

# 49. Keep Clerk Out of Domain Services

The following services should eventually receive the local User/application identity:

```text
Checkout
OrderService
CartService
RequestService
EnquiryService
NotificationService
```

They should not receive:

```text
Clerk User object
Clerk JWT
Clerk session ID
```

unless a narrowly defined authentication/security operation genuinely requires it.

Clerk belongs at the authentication/integration boundary.

---

# 50. Service Boundaries

A clean implementation may resemble:

```text
app/
├── Authentication/
│   ├── Clerk/
│   │   ├── ClerkTokenVerifier.php
│   │   ├── ClerkUserGateway.php
│   │   └── ...
│   ├── AuthenticatedClerkIdentity.php
│   └── LocalUserProvisioner.php
```

or the equivalent structure that fits the existing project layout.

Do not force this exact directory structure if Phase 4.1 selected another consistent structure.

Use the architecture already approved.

---

# 51. No Giant Authentication Service

Avoid:

```text
ClerkService
```

containing everything:

```text
verify token
fetch user
create local user
assign role
sync profile
authorize
logout
password reset
```

Prefer small responsibilities.

Example:

```text
ClerkTokenVerifier
ClerkUserGateway
LocalUserProvisioner
```

Each should be independently testable.

---

# 52. Dependency Injection

Bind external interfaces through the Laravel service container.

Production:

```text
ClerkUserGateway
→ real Clerk implementation
```

Tests:

```text
ClerkUserGateway
→ fake implementation
```

Likewise for token verification when applicable.

Feature/unit tests must not make real Clerk network requests.

---

# 53. Testing — Migration

Add tests proving:

* `clerk_user_id` column exists through migrations;
* multiple NULL values remain possible if nullable;
* duplicate non-null Clerk User ID is rejected;
* existing Group C users remain migratable;
* fresh migration remains successful.

Do not modify old Group C migrations to make these tests pass.

---

# 54. Testing — Successful New Customer Provisioning

Given:

```text
verified Clerk identity user_A
```

and a valid Clerk Backend User snapshot:

```text
email
approved name
approved phone
```

when provisioning occurs:

verify:

* exactly one local User exists;
* `clerk_user_id = user_A`;
* local approved snapshots are correct;
* CUSTOMER role exists;
* Staff role absent;
* Admin role absent;
* customer profile exists if required;
* no Laravel password credential was generated.

---

# 55. Testing — Existing User

Provision the same Clerk identity twice.

Verify:

```text
user1.id === user2.id
```

and:

```text
User::count()
```

does not increase on the second provisioning attempt.

Verify role/profile rows are not duplicated.

---

# 56. Testing — No Email Auto-Link

Prepare:

```text
Local User:
email = same@example.com
clerk_user_id = NULL
```

Then provision:

```text
Clerk user_NEW
email = same@example.com
```

Verify:

* existing local user is not silently claimed;
* `clerk_user_id` is not attached to it automatically;
* safe conflict behavior occurs;
* no privileged identity takeover is possible.

This is a mandatory security regression test.

---

# 57. Testing — Ignore Clerk Role Metadata

Provide a fake Clerk User containing metadata such as:

```json
{
  "public_metadata": {
    "role": "ADMIN"
  },
  "unsafe_metadata": {
    "role": "ADMIN"
  }
}
```

Provision the customer.

Verify application role is still:

```text
CUSTOMER
```

Do not trust Clerk metadata for local RBAC.

---

# 58. Testing — Client Role Tampering

If any Laravel endpoint participates in provisioning, send fields such as:

```json
{
  "role": "ADMIN",
  "permissions": ["*"],
  "user_id": "another-user",
  "clerk_user_id": "user_other"
}
```

They must not influence the authenticated/provisioned user.

Reject unknown/server-controlled fields according to the current API contract where applicable.

---

# 59. Testing — Missing Required Identity Data

Test required Clerk projection fields.

Examples depending on the approved ownership matrix:

```text
no primary email
no required name
no required phone
```

Verify no fabricated values are stored.

Verify provisioning either:

* follows the approved incomplete-profile flow; or
* fails safely.

Database must remain consistent.

---

# 60. Testing — Transaction Rollback

Force:

```text
role assignment failure
```

or another controlled provisioning failure.

Verify:

```text
no half-created customer
no orphan customer_profile
no role-less new user
```

The operation must be atomic.

---

# 61. Testing — Concurrency

Add a focused test for two provisioning attempts using the same:

```text
clerk_user_id
```

Verify final state contains:

```text
1 user
1 customer identity mapping
1 profile
1 CUSTOMER assignment
```

Use database uniqueness as the final invariant.

If the existing canonical SQLite test harness cannot model the exact concurrency mechanism safely, test the deterministic collision/retry path and document the limitation rather than introducing the known MySQL-only harness failures.

---

# 62. Testing — Existing Staff/Admin Mapping

Prepare an existing mapped Staff or Admin user.

Resolve/provision their Clerk identity through the same mapping boundary.

Verify provisioning does **not** overwrite their role with CUSTOMER.

This protects against accidental privilege downgrades and role mutation on ordinary requests.

---

# 63. Testing — Clerk Failure

Fake the Clerk gateway throwing a temporary integration failure during new-user provisioning.

Verify:

* no local user is created;
* no role assigned;
* no profile created;
* no unverified client data used as fallback;
* safe project-level error returned/mapped.

---

# 64. Test Isolation

Do not call real Clerk services from ordinary PHPUnit tests.

Use:

```text
fake ClerkTokenVerifier
fake ClerkUserGateway
```

or equivalent test doubles.

External integration tests may be added later under Phase 4.12.

The normal suite must remain:

* fast;
* deterministic;
* offline-capable.

---

# 65. Existing Group C Factories

Update UserFactory only as necessary to support the new schema.

Do not force every generic test user to have a real Clerk identity.

Useful states may include:

```text
withClerkIdentity()
customer()
staff()
admin()
```

according to existing factory conventions.

Use clearly synthetic Clerk IDs, for example:

```text
user_test_...
```

Never use real Clerk user identifiers in committed tests.

---

# 66. Do Not Reintroduce Faker in Production Paths

Production Clerk/local-user provisioning must not rely on Faker.

Also preserve the previously recorded deferred reference-generator cleanup:

```text
OrderFactory
PaymentFactory
FurnitureRequestFactory
```

That work remains independent and must not become mixed into this authentication phase unless those files are otherwise touched for a legitimate reason.

---

# 67. Security Logging

Safe logging may include:

```text
request_id
provisioning outcome
internal local user ID after successful creation
failure category
```

Avoid logging:

```text
session token
Authorization header
CLERK_SECRET_KEY
JWT payload wholesale
full Clerk Backend User payload
password data
```

Be especially careful not to log token values through exception context.

---

# 68. User Enumeration

Provisioning errors must not create an external oracle that reveals another user's account unnecessarily.

For example, an email collision should not expose:

```text
the other user's ID
role
orders
profile
Clerk ID
```

Return only the minimum safe error prescribed by the contract.

Keep detailed diagnostics in protected server logs.

---

# 69. Authorization Is Not Part of Provisioning

Provisioning proves:

```text
this Clerk identity maps to this local User
```

and assigns CUSTOMER when creating a new public account.

It does not automatically authorize:

```text
orders
admin APIs
staff operations
inventory
payments
```

Those remain governed by Laravel policies and later Group D phases.

---

# 70. Public Catalog Must Remain Anonymous

Do not add Clerk authentication/provisioning middleware to:

```text
public category endpoints
public product endpoints
search
public product detail
```

Public browsing must remain possible without Clerk.

Only routes explicitly requiring application authentication should invoke the authenticated-user resolution path.

---

# 71. Anonymous Request / Enquiry Must Remain Possible

Do not accidentally make:

```text
POST /requests
POST /enquiries
```

authentication-required.

They support:

```text
anonymous
OR
authenticated
```

If a valid Clerk identity is present later, Laravel may derive local ownership.

If no identity is present, they remain anonymous.

Invalid supplied authentication credentials must not be treated as a valid authenticated user.

---

# 72. Documentation

Update the relevant consolidated documentation only.

Likely files:

```text
docs/decisions.md
docs/api/api-conventions.md
docs/domain/business-rules.md
```

and any Phase 4.1-auth contract documents that require implementation status updates.

Document:

* local JIT provisioning algorithm;
* `clerk_user_id`;
* no email auto-linking;
* CUSTOMER-only public provisioning;
* transaction/idempotency behavior;
* Clerk Backend User fetch only for first provisioning/needed synchronization;
* no Laravel credential creation.

Do not create unnecessary permanent phase markdown files.

---

# 73. OpenAPI

Only update OpenAPI if Phase 4.1 retained or introduced an explicit Laravel-facing registration/bootstrap operation.

If JIT provisioning is internal middleware behavior, there may be **no new public endpoint** to document.

Do not invent an API endpoint solely so Phase 4.2 appears visible in OpenAPI.

Authentication behavior can be documented through the existing security scheme and relevant protected endpoint descriptions.

---

# 74. No Frontend Changes

Do not modify:

```text
frontend/web
frontend/app
frontend/design-system
```

during this phase.

Do not add:

```text
SignInButton
SignUpButton
UserButton
ClerkProvider
```

yet.

Those belong to the later client phases.

---

# 75. No Clerk CLI Initialization

Do not run:

```bash
clerk init
```

against:

```text
furniture-ecommerce/
backend/laravel/
```

unless current official Clerk tooling explicitly supports and is intentionally being used for this backend, and Phase 4.1 approved it.

The supplied CLI initialization process is primarily relevant when the actual frontend framework exists.

For Laravel, use the backend PHP integration deliberately.

---

# 76. Quality Requirements

Maintain existing project code-quality constraints:

* cognitive complexity ≤15;
* maximum 3 return statements per function where applicable;
* small cohesive services;
* centralized constants;
* explicit naming;
* no duplicated role strings;
* no scattered Clerk configuration access;
* no giant middleware;
* no magic strings;
* minimal comments.

Prefer:

```text
one class → one responsibility
```

over clever abstraction.

---

# 77. Static Analysis

Ensure all new PHP code passes the existing PHPStan/Larastan level.

Do not suppress new static-analysis failures broadly.

Avoid:

```php
@phpstan-ignore-next-line
```

unless there is a documented unavoidable SDK typing defect.

If Clerk SDK types require an adapter, isolate that adapter instead of weakening analysis across the project.

---

# 78. Commands / Verification

From:

```text
backend/laravel/
```

run the project's normal checks.

At minimum:

```bash
composer install
php artisan migrate:fresh --seed
php artisan test
vendor/bin/pint --test
vendor/bin/phpstan analyse
```

or the equivalent scripts already defined by the repository.

Also run:

```bash
composer audit
```

if consistent with current project tooling.

Do not replace existing CI commands with a new workflow.

---

# 79. Verify Fresh Rebuild

Because this phase changes the users schema, repeat the Group C rebuild invariant:

```text
empty database
    ↓
all migrations
    ↓
seed data
    ↓
tests
```

must succeed.

Ensure existing Group C seeders/factories still work after Clerk mapping is added.

---

# 80. Schema/API Change Report

At completion report:

## Schema

Expected:

```text
users.clerk_user_id
```

plus only the credential-column changes explicitly approved in Phase 4.1.

## API

Expected:

* no unrelated V1 changes;
* registration semantics follow Phase 4.1 Clerk migration decision;
* no new arbitrary endpoint.

## Authorization

Expected:

```text
public signup → CUSTOMER only
```

---

# 81. Files Changed Report

Explicitly list:

* Composer files;
* config files;
* new migration;
* User model;
* authentication integration classes;
* provisioning service;
* provider/container bindings;
* factories adjusted;
* tests;
* documentation.

Do not commit, stage, or push.

Leave version-control operations to the project owner.

---

# 82. Definition of Done

Phase 4.2 is complete when:

* official Clerk PHP backend integration is installed/configured as needed;
* Clerk secrets remain server-only;
* `users.clerk_user_id` exists with uniqueness protection;
* schema no longer requires creating a fake Laravel password for Clerk customers;
* verified Clerk `sub` is the only automatic external-account mapping key;
* email auto-linking is prohibited and tested;
* local customer provisioning exists;
* provisioning is idempotent;
* concurrent provisioning cannot create duplicate users;
* new public customers receive CUSTOMER only;
* Clerk metadata cannot elevate role;
* existing Staff/Admin mappings are not overwritten;
* customer profile creation is atomic where required;
* approved identity snapshot is stored correctly;
* no second Laravel authentication token/session is created;
* Clerk Backend API failure cannot result in fabricated identity;
* normal tests do not call external Clerk services;
* migration rebuild passes;
* PHPUnit passes;
* Pint passes;
* PHPStan passes;
* Composer/security checks pass as applicable;
* documentation reflects the implementation;
* no frontend work has leaked into this phase.

---

# 83. Out of Scope

Do not implement:

* Next.js Clerk initialization;
* Flutter Clerk initialization;
* actual frontend registration screen;
* Clerk UI components;
* login/logout UI;
* password recovery;
* account email-change UI;
* full email-verification flow;
* profile editing;
* guest-cart merge;
* Clerk webhooks;
* staff approval;
* admin bootstrap UI;
* Laravel authorization policies;
* rate limiting;
* MFA;
* Organizations;
* payment authentication.

---

# 84. STOP Condition

STOP when Clerk customer identities can be securely and deterministically projected into the Laravel application as local CUSTOMER users and all Phase 4.2 tests/checks pass.

Do not continue automatically into login/logout.

The next project-owner request should begin:

**Phase 4.3 — Clerk Login / Logout and Laravel Authenticated Request Resolution**
