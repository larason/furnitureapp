# Phase 4.1 — Clerk Authentication Architecture / Design Review

## Purpose

Redesign the authentication architecture around **Clerk as the external authentication and identity provider**, while preserving Laravel as the authoritative application/domain backend.

This phase is a **design, contract-impact, and architecture review**.

Do not implement customer registration, login UI, password recovery, Next.js authentication, Flutter authentication, authorization policies, or production Clerk middleware yet.

The outcome of this phase must make the ownership boundary unambiguous:

```text
Clerk
    │
    ├── credentials
    ├── sign-up/sign-in
    ├── sessions
    ├── password/security flows
    ├── authentication factors
    └── verified external identity
            │
            │ verified Clerk identity
            ▼
Laravel API
    │
    ├── local User mapping
    ├── CUSTOMER / STAFF / ADMIN
    ├── permissions
    ├── account business state
    ├── ownership
    ├── policies
    ├── carts/orders/payments
    ├── requests/enquiries
    └── all commerce/business rules
```

The key rule is:

> **Clerk authenticates the person. Laravel decides what that authenticated person is allowed to do in this application.**

---

# 1. Dependencies

Phase 4.1 starts only after Phase Group C has passed its exit condition.

Required:

* Phase 3.1–3.19 complete;
* local `users` model/table exists;
* RBAC schema exists;
* all commerce-domain FKs to local users exist;
* database can be rebuilt from migrations;
* V1 API contract exists and is frozen;
* existing authentication conventions have been documented.

The Group D roadmap remains:

* 4.1 Authentication design review
* 4.2 Customer registration
* 4.3 Login/logout
* 4.4 Password recovery
* 4.5 Email verification if required
* 4.6 Profile operations
* 4.7 API authentication for mobile
* 4.8 SPA authentication for website
* 4.9 Roles
* 4.10 Policies/permissions
* 4.11 Rate limiting
* 4.12 Authentication tests

Do not implement later phases during this review.

---

# 2. Authoritative Inputs

Review before making decisions:

1. `AGENTS.md`
2. `docs/VISION.md`
3. `docs/api/api-contract.md`
4. `docs/api/api-resources.md`
5. `docs/api/api-conventions.md`
6. `docs/api/openapi.yaml`
7. `docs/domain/business-rules.md`
8. `docs/decisions.md`
9. final Group C migrations/models/factories
10. current Clerk documentation
11. Clerk MCP/Skills if installed by the project owner.

The V1 API contract is frozen. Clerk adoption is an explicit architecture decision by the project owner, but any externally observable auth change must still follow the project's documented post-freeze contract-change process.

Do not silently rewrite authentication semantics.

---

# 3. Clerk Tooling Rules

The project owner has selected Clerk and will provide Clerk MCP/Skills.

Use those tools where available to:

* inspect current Clerk capabilities;
* inspect the configured Clerk application;
* verify current official documentation;
* verify supported PHP/backend integration;
* verify relevant session/token claims;
* confirm configured sign-in/sign-up methods.

Do not expose or print secrets.

Do not read or dump `.env` contents.

Do not copy:

* `CLERK_SECRET_KEY`;
* webhook signing secrets;
* session tokens;
* private credentials

into documentation, test output, logs, prompts, or source code.

The target Clerk application is the application already selected by the project owner.

Do not create an alternative Clerk application.

---

# 4. Do Not Run Frontend Clerk Initialization Yet

The repository structure is:

```text
furniture-ecommerce/
├── backend/
│   └── laravel/
├── frontend/
│   ├── app/
│   └── web/
└── ...
```

The active phase is:

```text
backend/laravel/
```

The frontend applications are explicitly future phases.

Therefore Phase 4.1 must **not**:

* run `clerk init` at repository root;
* scaffold Next.js;
* install `@clerk/nextjs`;
* modify `frontend/web`;
* modify Flutter;
* create Clerk UI components;
* create sign-in pages;
* create proxy/middleware files for Next.js.

The supplied Clerk CLI setup instructions become relevant when the appropriate frontend integration phase begins.

Phase 4.1 concerns the architecture Laravel will expect from all authenticated clients.

---

# 5. Core Architecture Decision

Record an ADR establishing:

> Clerk is the authentication/credential/session authority. Laravel remains the application identity projection, RBAC authority, ownership authority, and business authorization authority.

Do not create a dual authentication system.

Specifically, Laravel must not independently issue customer authentication credentials in parallel with Clerk.

Reject architectures such as:

```text
Clerk login
    ↓
exchange Clerk token
    ↓
Laravel Sanctum token
    ↓
second independent session
```

unless an unavoidable requirement is identified and explicitly approved.

Preferred model:

```text
Clerk session
    ↓
Clerk session token
    ↓
Laravel verifies token
    ↓
Laravel resolves local User
    ↓
Laravel authorizes request
```

One authenticated user session should not require maintaining a second customer credential system in Laravel.

---

# 6. External Identity vs Local User Identity

The existing local `users` table must remain.

Do **not** replace all local user foreign keys with Clerk user IDs.

Existing domain relationships must continue to use the local application user:

```text
users.id
    ↓
orders.customer_id
carts.user_id
notifications.recipient_user_id
furniture_requests.user_id
enquiries.user_id
profiles.user_id
RBAC assignments
...
```

Introduce a mapping concept:

```text
Clerk User ID
    ↓
users.clerk_user_id
    ↓
users.id
    ↓
application domain
```

The Clerk User ID is the external authenticated identity.

The Laravel `users.id` remains the internal domain identity.

---

# 7. `clerk_user_id` Design

Define the target schema change for a later implementation phase:

```text
users.clerk_user_id
```

Requirements:

* stores Clerk User ID;
* unique;
* indexed;
* server-controlled;
* never writable by ordinary clients;
* immutable after secure account linking;
* never accepted from `/me` profile requests;
* never used as a public authorization shortcut.

Do not implement this migration during Phase 4.1 unless a tiny proof is absolutely necessary.

Document the migration that Phase 4.2 will require.

Do not use email as the primary Clerk↔Laravel identity key.

---

# 8. Never Link Accounts by Email Alone

This is a critical security rule.

The canonical mapping must be:

```text
verified Clerk `sub`
    ↓
users.clerk_user_id
```

Never:

```text
Clerk email
    ↓
SELECT users WHERE email = ...
    ↓
assume same person
```

Email may change.

A deleted Clerk account may later be recreated using the same email.

Multiple identity providers may eventually be linked to one Clerk account.

Therefore:

> Clerk's stable user identifier is identity. Email is user/contact data.

If no `clerk_user_id` mapping exists, provisioning must follow the approved first-login/provisioning workflow rather than silently attaching to a local record merely because an email matches.

Any legacy-account linking strategy would require a separate secure migration process.

---

# 9. Local User Provisioning Strategy

Design **just-in-time local provisioning** for ordinary customer signup.

Target flow:

```text
Customer signs up with Clerk
        ↓
Clerk creates authenticated identity
        ↓
client sends first authenticated API request
        ↓
Laravel verifies Clerk session token
        ↓
extract Clerk user ID (`sub`)
        ↓
users.clerk_user_id exists?
   ┌───────────────┴───────────────┐
  YES                              NO
   │                                │
resolve local User          retrieve minimum trusted
   │                       Clerk user information
   │                                │
   │                         create local User
   │                                │
   │                         assign CUSTOMER only
   └───────────────┬────────────────┘
                   ↓
         Laravel request continues
```

This must be designed as an idempotent operation.

Concurrent first requests from the same Clerk identity must not create duplicate Laravel users.

The future implementation must rely on:

* unique `clerk_user_id`;
* transaction/upsert or equivalent safe creation;
* retry-safe resolution.

---

# 10. Webhooks Are Reconciliation, Not Login Dependency

Do not design:

```text
Clerk signup
    ↓
wait for webhook
    ↓
Laravel creates user
    ↓
only then API works
```

Clerk webhooks are asynchronous.

Therefore an authenticated customer's first API request must not depend on a `user.created` webhook having already arrived.

Use webhooks later for reconciliation events such as:

```text
user.created
user.updated
user.deleted
```

where useful.

The exact webhook implementation belongs to a later Group D micro-phase.

Document that webhook processing must eventually include:

* signature verification;
* replay/idempotency protection;
* safe retries;
* no client authority;
* no secrets in logs.

---

# 11. Identity Field Ownership Matrix

Phase 4.1 must explicitly produce a field-ownership matrix.

At minimum review:

| Field                     | Proposed authority |
| ------------------------- | ------------------ |
| Clerk user ID             | Clerk              |
| Laravel user ID           | Laravel            |
| Authentication credential | Clerk              |
| Session                   | Clerk              |
| Password                  | Clerk              |
| MFA/auth factors          | Clerk              |
| Primary verified email    | Clerk              |
| Email verification status | Clerk              |
| Application role          | Laravel            |
| Permissions               | Laravel            |
| Account business state    | Laravel            |
| Customer profile data     | decide explicitly  |
| Operational phone/contact | decide explicitly  |
| Order/customer ownership  | Laravel            |

Do not leave `name`, `phone`, or `email` with two competing writers.

For every locally duplicated Clerk field decide whether it is:

```text
AUTHORITATIVE
SNAPSHOT
INITIAL_BOOTSTRAP_ONLY
DERIVED
```

Document the decision.

---

# 12. Email Ownership

Existing API conventions treat email as identity/security data rather than an ordinary `/me` profile field.

Preserve that principle.

If Clerk owns authentication email:

* ordinary `PATCH /me` must not directly change authentication email;
* email changes must occur through the Clerk security/account workflow;
* Laravel may maintain a synchronized local email snapshot when needed;
* Laravel must never treat `{"email_verified": true}` from a client as authoritative.

Determine how the local email snapshot is refreshed:

* JIT reconciliation;
* verified webhook;
* explicit synchronization operation;
* or a bounded combination.

Do not allow arbitrary client writes to overwrite Clerk identity state.

---

# 13. Name and Phone Ownership

Review current Clerk application settings using the Clerk tooling.

Determine whether Clerk currently collects:

* name;
* phone;
* email;
* username;
* password;
* OAuth identities;
* other factors.

Then explicitly decide which fields belong to Clerk versus the Laravel application profile.

Do not enable additional authentication methods simply because Clerk supports them.

Avoid a situation where:

```text
Clerk profile edits phone
```

while simultaneously:

```text
PATCH /api/v1/me
```

independently edits the same canonical field without reconciliation rules.

Record one authoritative owner.

---

# 14. Credentials Must Leave Laravel

The current Group C user schema was designed before Clerk was selected.

Review existing columns such as:

```text
password
remember_token
```

The target architecture should not retain a second usable password credential inside Laravel when Clerk manages authentication.

Phase 4.1 must specify the required follow-up migration strategy.

Preferred target:

```text
Laravel users
    no application-auth password credential
    no Laravel remember-token session mechanism
    clerk_user_id mapping
```

Do not edit already-applied Group C migrations.

Any schema adaptation must occur through a **new Group D migration**.

Do not implement that migration during the design review.

---

# 15. Laravel Authentication Context

Define how a successfully verified Clerk identity becomes an authenticated Laravel principal.

Target architecture:

```text
HTTP request
    ↓
Clerk authentication middleware
    ↓
verify Clerk session token
    ↓
resolve local User
    ↓
attach local User to Laravel request/auth context
    ↓
authorization middleware/policy
    ↓
controller
```

Authentication middleware must not perform business authorization.

Keep separate:

```text
Authentication
    "Who is this?"

Authorization
    "May this user perform this action?"
```

Potential future location:

```text
backend/laravel/app/Http/Middleware/
```

Potential supporting abstractions:

```text
ClerkTokenVerifier
AuthenticatedIdentity
ClerkUserResolver
LocalUserProvisioner
```

Do not create abstractions without clear responsibility.

---

# 16. Token Transport Architecture

Use one consistent Laravel authentication boundary for web and mobile where practical.

Preferred Laravel-facing transport:

```http
Authorization: Bearer <Clerk session token>
```

Conceptually:

```text
Next.js Clerk session
    ↓
Clerk session token
    ↓
Authorization Bearer
    ↓
Laravel

Flutter Clerk session
    ↓
Clerk session token
    ↓
Authorization Bearer
    ↓
Laravel
```

The Next.js Clerk SDK may internally maintain its browser session using secure cookies.

That does **not** mean Laravel should create another browser session.

For server-rendered Next.js requests later:

```text
Next.js server
    ↓
retrieve current Clerk session token
    ↓
server-to-server Laravel call
    ↓
Authorization Bearer
```

For mobile:

```text
Flutter
    ↓
current Clerk session token
    ↓
Laravel API
```

Do not store session tokens in insecure persistent browser storage such as `localStorage`.

---

# 17. Token Verification Requirements

Phase 4.1 must define, but not yet implement, the verification requirements.

Laravel must never merely decode a JWT.

It must cryptographically verify it.

Verify as applicable:

* signature;
* expected algorithm;
* `exp`;
* `nbf`;
* `iss`;
* Clerk user identity in `sub`;
* authorized party / `azp`;
* expected audience when configured;
* other Clerk-required session semantics.

Reject malformed, expired, untrusted, or incorrectly issued credentials.

Do not trust arbitrary claims solely because they decode successfully.

---

# 18. Prefer Local Cryptographic Verification

Design normal API authentication so Laravel does not need a Clerk network request for every authenticated request.

Preferred architecture:

```text
Clerk public verification material
        ↓
Laravel token verification
        ↓
no Clerk network request required
for ordinary known-user API request
```

Where public-key/JWKS caching is used, document:

* retrieval;
* cache behavior;
* key rotation handling;
* failure behavior.

Do not build a permanent authentication dependency where every furniture API request requires a successful round trip to Clerk.

Backend API calls to Clerk should be reserved for cases that actually require remote Clerk user data or administrative operations.

---

# 19. Clerk PHP Integration Review

Inspect the current official Clerk PHP SDK and official Clerk backend guidance.

Determine:

* whether the PHP SDK provides the required request/JWT verification primitives;
* whether an additional standards-compliant JWT/JWKS library is required;
* how Clerk Backend API calls should be wrapped;
* how errors should be converted into project-level authentication errors.

Do not guess SDK method names.

Do not implement against JavaScript examples inside Laravel.

Use PHP-native supported primitives.

Record the selected dependency/design in `docs/decisions.md`.

Implementation belongs to Phase 4.2/4.3 as appropriate.

---

# 20. `CLERK_SECRET_KEY` Boundary

The Clerk secret key is server-only.

It may later exist only in secured backend/server environments needing Clerk Backend API access.

Never expose it to:

* Next.js client components;
* Flutter;
* browser JavaScript;
* API responses;
* logs;
* source control.

Laravel configuration must reference environment variables rather than embedding secrets.

The design may define:

```text
config/clerk.php
```

but Phase 4.1 does not need actual secret values.

---

# 21. Local Roles Remain Authoritative

Do not move application RBAC into Clerk metadata.

Canonical roles remain:

```text
CUSTOMER
STAFF
ADMIN
```

Laravel continues to own role assignment.

Never authorize an operation based solely on a frontend/Clerk claim such as:

```json
{
  "role": "ADMIN"
}
```

unless the project explicitly redesigns the RBAC trust model later.

Current design:

```text
verified Clerk identity
        ↓
local users row
        ↓
local RBAC
        ↓
Laravel policy
```

---

# 22. Do Not Use Clerk Organizations as Application Roles

Do not introduce Clerk Organizations merely to implement:

```text
CUSTOMER
STAFF
ADMIN
```

The existing RBAC model already handles these roles.

Organizations would introduce another authorization authority and unnecessary complexity.

Only revisit Clerk Organizations if a genuine future business requirement introduces multi-tenant organizations.

That is outside V1.

---

# 23. Customer Registration Authority

Public self-registration must never grant privileged application roles.

Target:

```text
Clerk public signup
        ↓
local provisioning
        ↓
CUSTOMER
```

Never:

```text
signup metadata role=ADMIN
```

Never derive STAFF/ADMIN from arbitrary Clerk user metadata.

Staff/Admin provisioning must be controlled by Laravel administrative workflows.

---

# 24. Staff Identity Model

Design the future staff flow conceptually.

A Staff user must have:

1. a valid Clerk identity;
2. a mapped local Laravel user;
3. approved local STAFF role/permissions.

Clerk authentication alone does not make someone Staff.

For example:

```text
valid Clerk session
+
local CUSTOMER role
=
Customer
```

not Staff.

Staff approval remains an application authorization decision.

---

# 25. Admin Bootstrap

Document how the first Admin identity will eventually be bootstrapped safely.

Requirements:

* must correspond to a real Clerk identity;
* local ADMIN assignment must be explicit;
* must not be available through public signup parameters;
* must not depend on client metadata;
* must be auditable once audit infrastructure is active;
* no committed admin password;
* no wildcard privilege shortcut.

Do not implement the bootstrap operation during Phase 4.1.

---

# 26. Authentication State vs Application Account State

Keep these separate.

Example:

```text
Clerk authenticated = true
```

does not necessarily mean:

```text
Laravel application access = unrestricted
```

Laravel may later determine:

```text
authenticated identity
+
local account state
+
role
+
permissions
+
resource ownership
+
business state
```

before allowing an operation.

Clerk answers identity/session questions.

Laravel answers business authorization questions.

---

# 27. Session Lifecycle

Document expected session semantics:

* session created by Clerk;
* session refreshed by Clerk;
* session expires according to Clerk policy;
* logout terminates/revokes Clerk session;
* Laravel does not create a parallel customer session;
* multi-device sessions remain possible;
* invalid/expired token produces project-standard authentication failure.

Review how immediate session/user revocation interacts with locally verified JWTs and token lifetime.

Document the expected security behavior instead of assuming instantaneous global invalidation.

---

# 28. Laravel Error Mapping

Clerk/vendor errors must not leak through the API.

Map authentication failures to existing project-level semantics such as:

```text
AUTHENTICATION_REQUIRED
INVALID_AUTHENTICATION
SESSION_EXPIRED
FORBIDDEN
```

as appropriate.

Do not return:

* Clerk stack traces;
* raw SDK exceptions;
* Clerk secret/key information;
* token contents;
* JWKS internals;
* internal account identifiers unnecessarily.

Protected Laravel endpoints continue using the project's standard API error envelope.

---

# 29. Clerk UI Errors vs Laravel API Errors

Distinguish:

```text
Clerk-owned authentication UI flow
```

from:

```text
Laravel protected API request
```

Clerk sign-up/sign-in UI may surface Clerk-managed validation/security feedback.

Laravel business APIs must continue returning the project's normal V1 error contract.

Do not attempt to proxy every Clerk signup/password validation failure through Laravel merely to preserve an obsolete custom authentication endpoint.

---

# 30. Frozen API Contract Impact Review

This is mandatory.

Existing V1 documentation assumes Laravel-owned AUTH endpoints such as:

* registration;
* login;
* logout;
* password recovery;
* password/security changes;
* email verification/security flows.

Adopting Clerk materially changes those responsibilities.

Create an explicit matrix:

| Existing AUTH operation | Clerk-owned? | Laravel endpoint retained? | Retired? | Replacement flow |
| ----------------------- | ------------ | -------------------------- | -------- | ---------------- |

Inspect the exact existing `AUTH-*` inventory rather than guessing it.

Do not recycle endpoint IDs.

If an endpoint is retired:

```text
AUTH-xxx → RETIRED
```

Its identifier remains retired permanently.

Do not reassign that ID to another operation.

---

# 31. Formal Post-Freeze Contract Change

Because auth behavior was frozen, document Clerk adoption as an approved post-freeze architecture change.

Update the appropriate authoritative documents so they no longer claim Laravel owns password/session issuance when Clerk now owns it.

Review/update as necessary:

```text
docs/api/api-contract.md
docs/api/api-resources.md
docs/api/api-conventions.md
docs/api/openapi.yaml
docs/domain/business-rules.md
docs/decisions.md
AGENTS.md
```

Only change authentication-related statements required by Clerk adoption.

Do not alter unrelated:

* catalog;
* cart;
* checkout;
* order;
* payment;
* delivery;
* notification

contracts.

---

# 32. OpenAPI Security Model

Review the existing `bearerAuth` security scheme.

The Laravel API may continue using:

```http
Authorization: Bearer ...
```

but the bearer credential semantics must now explicitly mean:

> valid Clerk-issued session token accepted by the Laravel authentication middleware.

Do not describe it as a Laravel-issued token if Laravel no longer issues customer credentials.

Protected endpoints remain protected.

Public catalog remains public.

Anonymous request/enquiry creation remains anonymous-capable.

---

# 33. `/me` Contract

Preserve:

```http
GET /api/v1/me
PATCH /api/v1/me
```

as the application self-profile boundary unless the formal contract review identifies an explicit reason to change them.

The user is selected from the authenticated Clerk principal:

```text
token.sub
    ↓
users.clerk_user_id
    ↓
local users.id
```

Clients must still never submit:

```text
user_id
clerk_user_id
role
permissions
account_state
```

to choose their identity.

`/me` means exactly:

> the local application user mapped to the authenticated Clerk identity.

---

# 34. User Synchronization Rules

Define synchronization explicitly.

A suitable baseline is:

### Synchronous path

Used when Laravel must resolve the current authenticated user immediately.

```text
verify Clerk token
→ lookup by clerk_user_id
→ provision if absent
→ continue
```

### Asynchronous reconciliation

Used for Clerk events that do not need to block a user request.

```text
Clerk webhook
→ verify signature
→ idempotent reconciliation
```

Do not make ordinary authentication dependent on webhook timing.

---

# 35. User Update Reconciliation

Determine what should happen when Clerk sends:

```text
user.updated
```

Only synchronize fields Clerk actually owns.

Do not overwrite Laravel-owned profile/business fields simply because Clerk sends similarly named properties.

Example:

```text
Clerk owns email
→ update local email snapshot

Laravel owns role
→ never update role from Clerk metadata
```

Make this field-specific.

Do not implement generic:

```php
$user->update($clerkUserPayload);
```

---

# 36. User Deletion / Account Retention

This requires an explicit design decision but not full implementation yet.

Current domain records may reference a user through:

* orders;
* payments indirectly;
* carts;
* requests;
* enquiries;
* notifications;
* operational history.

Therefore a Clerk:

```text
user.deleted
```

event must **not automatically hard-delete the Laravel user and historical commerce data**.

Design a preservation strategy.

Potential target semantics:

```text
Clerk identity deleted
    ↓
authentication no longer available
    ↓
local historical user/domain record retained
```

The final privacy/account-retention implementation belongs to Phase 4.6 / later customer-management work.

---

# 37. Carry Forward the Cart FK Risk

Phase 3.19 recorded:

```text
carts.user_id
MySQL  → RESTRICT
SQLite → SET NULL
```

Do not fix this incidentally in Phase 4.1.

Record that the final delete behavior must be resolved when the account-retention policy is established during:

```text
Phase 4.6 — Profile operations
```

and, if necessary, later customer-management work.

Preserve commerce history.

---

# 38. Guest Cart → Authenticated Customer

Do not let Clerk adoption change the existing guest-cart security model.

Future login/registration flow must support:

```text
guest cart credential
+
successful Clerk authentication
        ↓
authenticated local User
        ↓
explicit server-side guest cart merge
```

The guest cart bearer credential alone never authenticates a User.

The Clerk session alone does not authorize possession of an arbitrary guest cart.

The later merge operation must validate both independently.

---

# 39. Anonymous Request / Enquiry Semantics

Preserve existing behavior:

```text
anonymous user
→ may submit furniture request/enquiry
→ user_id = null
```

When a valid Clerk session is present:

```text
Clerk identity
→ local User
→ user_id server-derived
```

Never trust body:

```json
{
  "user_id": "..."
}
```

Do not auto-claim old anonymous requests/enquiries by matching email after login.

---

# 40. Authentication Middleware Failure Modes

Document behavior for:

```text
no token
malformed token
expired token
invalid signature
wrong issuer
untrusted authorized party
unknown Clerk user mapping
Clerk Backend API temporarily unavailable during first provisioning
local account conflict
disabled application account
```

Each must fail predictably and safely.

Do not silently downgrade an invalid authenticated request to anonymous when the endpoint requires authentication.

---

# 41. Availability / Failure Strategy

Separate two cases.

### Existing mapped user

Normal requests should preferably authenticate using cryptographic token verification without requiring a Clerk API network request.

### First-time provisioning / explicit synchronization

A Clerk Backend API call may be necessary.

If Clerk is temporarily unavailable during first-time provisioning:

* do not create an unverified local identity;
* do not infer identity from email/body data;
* return a safe temporary service/authentication failure according to the project's error model.

Do not weaken authentication because an external service is unavailable.

---

# 42. Testing Architecture

Design authentication code for testability.

Do not force every Laravel feature test to contact Clerk.

Create an abstraction boundary so tests can supply a verified identity deterministically.

Conceptual example:

```text
ClerkTokenVerifier interface
        │
        ├── production Clerk verifier
        └── test fake verifier
```

Feature tests should be able to represent:

```text
signed out
valid Customer
valid Staff
valid Admin
expired token
invalid token
unknown external identity
```

without real external network calls.

Real Clerk integration tests may be added separately in Phase 4.12.

---

# 43. Never Accept Test Backdoors in Production

Do not add mechanisms such as:

```http
X-Test-User-Id: ...
X-Fake-Role: ADMIN
```

to production middleware.

Test authentication substitution must exist through dependency injection/testing configuration only.

Production configuration must never permit an arbitrary client to bypass Clerk verification.

---

# 44. Clerk Metadata Policy

Document:

```text
Clerk metadata != Laravel authorization authority
```

Metadata may eventually contain convenience information, but do not authorize protected operations using client-editable or remotely stored metadata without an explicitly approved trust model.

Critical application roles stay in Laravel.

Avoid copying the full Laravel permission set into session-token metadata.

This prevents:

* stale permission claims;
* oversized tokens;
* duplicated authority;
* unclear invalidation semantics.

---

# 45. Authentication Logging

Design safe logs for:

* token verification failure category;
* local user provisioning failure;
* user-mapping conflicts;
* webhook signature failures later;
* unexpected Clerk SDK/API failures.

Never log:

* bearer tokens;
* secret keys;
* JWT contents wholesale;
* passwords;
* password reset secrets;
* webhook signing secrets.

Use the existing request ID for correlation.

---

# 46. Rate Limiting Ownership

Document responsibility:

### Clerk

Credential-facing abuse controls for Clerk-owned flows such as login/password mechanisms as supported/configured by Clerk.

### Laravel

Still responsible for application API abuse controls such as:

* anonymous request creation;
* anonymous enquiry creation;
* checkout;
* business state changes;
* sensitive application operations.

Do not assume adopting Clerk eliminates the need for Phase 4.11.

---

# 47. CSRF/CORS Review

Review the new cross-client architecture.

Because Laravel will accept authenticated browser/mobile API requests using a bearer token, determine:

* allowed browser origins;
* CORS policy;
* expected `azp`/authorized-party validation;
* credentials/cookie settings;
* whether any Laravel route still uses cookie authentication.

Do not enable wildcard CORS with credentials.

Do not weaken CSRF protections for unrelated cookie-authenticated routes.

Record the design; implementation belongs later.

---

# 48. Frontend-Web Future Boundary

Document what Phase 4.8 will eventually do.

Only then should the agent initialize Clerk inside:

```text
frontend/web/
```

not repository root.

When that phase arrives:

* use the official Clerk Next.js integration;
* follow the project-owner supplied Clerk CLI instructions;
* use `@clerk/nextjs`;
* configure the selected Clerk application;
* preserve Material UI/design-system architecture;
* integrate auth controls naturally;
* ensure Laravel calls receive the correct Clerk token.

Do not perform this now.

---

# 49. Flutter Future Boundary

Document Phase 4.7 separately.

The Flutter application must eventually:

```text
authenticate with Clerk
→ obtain current valid Clerk session token
→ call same Laravel API
→ Laravel resolves same local User
```

Do not create a mobile-only user/account database.

Do not duplicate application authorization logic in Flutter.

Use the community-maintained Clerk Flutter SDK public beta (`clerk_flutter: 0.0.18-beta` — exact pin, no `^`, per Clerk beta hard-pinning guidance; from `github.com/clerk-community/clerk-sdk-flutter`; verified publisher `clerk.com` on pub.dev does not imply official support; breaking changes expected until `1.0.0`). Re-confirm the exact reviewed version at Phase 4.7 implementation time — do not float the beta with `^`. Keep Flutter authentication behind a small repository/service abstraction so the rest of the app does not depend directly on provider-specific APIs while the SDK is beta.

Do not implement Flutter during Phase 4.1.

---

# 50. Same User Across Web and Mobile

The architecture must guarantee:

```text
Web Clerk login
Clerk user = user_ABC
        ↓
Laravel users.clerk_user_id = user_ABC
```

and:

```text
Flutter Clerk login
Clerk user = user_ABC
        ↓
same Laravel users row
```

There must not be separate:

```text
web customer
mobile customer
```

records for the same Clerk identity.

---

# 51. ReferenceGenerator Deferred Work

Carry forward the Group C recommendation without mixing it into this auth phase.

Outstanding:

```text
OrderFactory::generateReference()
PaymentFactory::generateReference()
FurnitureRequestFactory::generateReference()
```

must eventually migrate from Faker `bothify` to:

```text
App\Support\ReferenceGenerator
```

following `EnquiryFactory`.

Production Order/Payment/Request service paths must also use `ReferenceGenerator`.

This is unrelated to Clerk authentication.

Do **not** implement it in Phase 4.1 unless required by an unavoidable touched-file cleanup.

Keep it on the deferred implementation register.

---

# 52. Other Group C Deferred Risks

Preserve the previously recorded items.

Do not address in Phase 4.1:

### Product availability

`products.product_type` / `is_published`

→ Group E Phase 5.7.

### MySQL enum case behavior

Admin writes must validate CLOSED values

→ Group K.

### MySQL-specific test harness failures

Do not port them now.

→ Group U if MySQL-backed CI is introduced.

### Cart user FK delete policy

Review only as part of account-retention design.

→ Phase 4.6 / Group K.

---

# 53. Required ADR

Add/update `docs/decisions.md` with an ADR covering at minimum:

**Decision:** Clerk provides authentication and credential/session management; Laravel provides local domain identity, RBAC, ownership, and business authorization.

Record:

* context;
* decision;
* alternatives rejected;
* local user mapping;
* `clerk_user_id`;
* token transport;
* verification model;
* no dual Laravel credentials;
* local RBAC authority;
* user provisioning;
* webhook role;
* data synchronization;
* account deletion/retention implications;
* frontend/mobile implications;
* frozen API contract impact;
* security consequences.

Keep it concise but complete.

---

# 54. Required Architecture Diagram

Add a simple text/Mermaid architecture diagram to the relevant documentation.

It should communicate:

```text
             ┌───────────────┐
             │     Clerk     │
             │ Auth/Sessions │
             └───────┬───────┘
                     │
           verified session token
                     │
        ┌────────────┴────────────┐
        │                         │
   Next.js Web                Flutter
        │                         │
        └────────────┬────────────┘
                     │ Bearer token
                     ▼
              ┌─────────────┐
              │ Laravel API │
              └──────┬──────┘
                     │
             verify Clerk identity
                     │
                     ▼
              Local `users`
                     │
              Laravel RBAC
                     │
                     ▼
           Application Domain
```

Do not create an overly complex identity diagram.

---

# 55. Required Contract Impact Matrix

Produce a table covering every current authentication endpoint.

For each:

```text
endpoint ID
current path
current responsibility
Clerk responsibility?
Laravel retained?
retired?
replacement client flow
breaking/non-breaking
documentation requiring update
```

Do not proceed to Phase 4.2 while this matrix contains ambiguous ownership.

---

# 56. Required Data Ownership Matrix

Produce a second matrix:

```text
data field
authoritative system
local copy?
sync mechanism
client writable?
security notes
```

At minimum:

* Clerk user ID
* local user ID
* email
* email verified state
* name
* phone
* password
* sessions
* roles
* permissions
* account state
* profile information.

---

# 57. Required Trust-Boundary Matrix

Document what Laravel may trust after token verification.

Example:

| Input                         | Trust                           |
| ----------------------------- | ------------------------------- |
| verified Clerk `sub`          | authenticated external identity |
| request body `user_id`        | never trusted                   |
| request body `role`           | never trusted                   |
| Clerk public metadata role    | not application authority       |
| local RBAC role               | authoritative application role  |
| local ownership relation      | authoritative ownership         |
| client `email_verified`       | never trusted                   |
| Clerk verified identity state | authentication authority        |

This matrix should guide all future Group D implementation.

---

# 58. Documentation Updates

Phase 4.1 may modify documentation.

Expected files include some subset of:

```text
AGENTS.md
docs/decisions.md
docs/domain/business-rules.md
docs/api/api-contract.md
docs/api/api-resources.md
docs/api/api-conventions.md
docs/api/openapi.yaml
```

Do not create unnecessary standalone permanent phase documents.

Use existing consolidated documentation wherever possible.

---

# 59. Code Changes

Expected production implementation changes:

```text
NONE or minimal
```

Phase 4.1 is not the Clerk middleware implementation phase.

Acceptable code changes only if necessary to support an architecture spike that is then removed or clearly isolated.

Do not install unrelated packages.

Do not implement controllers.

Do not implement authentication routes.

Do not modify frontend applications.

---

# 60. Tests

This design phase does not require the complete Group D test suite.

However, run existing backend tests after documentation/configuration changes to ensure nothing was accidentally broken.

If any executable proof/spike is retained, test it appropriately.

Do not weaken existing tests merely because authentication architecture is changing.

Record future tests required for Phase 4.12.

---

# 61. Future Test Matrix

Document the cases Group D must eventually cover:

```text
missing token
valid token
expired token
malformed token
invalid signature
wrong issuer
wrong authorized party
unknown Clerk identity
first-request provisioning
concurrent provisioning
existing local mapping
Customer role
Staff role
Admin role
role tampering
body user_id tampering
disabled local account
logout/revoked session
profile ownership
cross-user IDOR
web/mobile same identity
Clerk outage during first provisioning
webhook duplicate/replay
```

Do not implement the whole matrix in Phase 4.1.

---

# 62. Maintainability Requirements

Follow existing project quality rules.

Authentication implementation planned here must eventually use:

* small cohesive classes;
* dependency injection;
* explicit names;
* centralized verification;
* centralized error mapping;
* no duplicated JWT parsing;
* no scattered environment access;
* no scattered Clerk SDK calls;
* no magic auth strings.

Maintain:

* cognitive complexity ≤15;
* maximum 3 return statements per function where practical under project rule;
* minimal comments;
* no giant middleware class.

A future controller must never contain the full Clerk verification/provisioning/RBAC process inline.

---

# 63. Files Changed Report

At phase completion, report:

### Files changed

Exact files.

### Schema changes

Expected:

```text
none implemented
```

but list planned Phase 4.2 migration(s).

### API contract changes

Explicitly identify Clerk-related post-freeze updates.

### Tests

List tests run/added.

### Commands/checks

List exact commands.

### Known risks

List unresolved Clerk/security/integration risks.

This report is mandatory per `AGENTS.md`.

---

# 64. Decisions That Must Be Resolved Before Phase 4.2

Phase 4.1 is not complete until these are explicit:

1. Clerk is authentication authority.
2. Laravel remains authorization authority.
3. Local `users` table remains.
4. Clerk identity maps through unique `clerk_user_id`.
5. Account linking does not use email alone.
6. Customer JIT provisioning strategy is defined.
7. Default public signup role is CUSTOMER.
8. STAFF/ADMIN cannot be self-assigned.
9. Password/session ownership belongs to Clerk.
10. Laravel customer password/remember-token strategy is defined for removal/deprecation.
11. Token transport to Laravel is defined.
12. Token verification requirements are defined.
13. Webhook role is reconciliation, not synchronous auth.
14. Field ownership/synchronization matrix exists.
15. User deletion/retention strategy is at least bounded and assigned to Phase 4.6.
16. Existing AUTH endpoints have Clerk migration/retirement decisions.
17. Frozen V1 contract-change documentation is updated.
18. Web and Flutter future integration boundaries are defined.
19. Testing abstraction strategy is defined.
20. No secret-handling ambiguity remains.

---

# 65. Definition of Done

Phase 4.1 is complete when:

* Clerk/Laravel responsibility boundary is documented;
* the project has one authentication authority, not two;
* Laravel's local User remains the application-domain principal;
* unique Clerk↔Laravel mapping design is finalized;
* provisioning strategy is finalized;
* token transport and verification architecture are finalized;
* JWT validation expectations are explicit;
* local RBAC remains authoritative;
* Clerk metadata is explicitly non-authoritative for roles;
* public signup cannot create Staff/Admin;
* webhook responsibilities are clearly bounded;
* field ownership/synchronization is documented;
* account-deletion retention implications are documented;
* the cart FK retention issue is assigned to the correct later phase;
* the existing frozen AUTH contract has been formally reviewed and updated through the project's post-freeze process;
* no unrelated V1 contract behavior changed;
* Next.js/Flutter integration is deferred to their correct phases;
* secrets are not committed or exposed;
* existing backend checks still pass;
* Phase 4.2 has an unambiguous implementation target.

---

# 66. Out of Scope

Do not implement:

* Clerk sign-up UI;
* Clerk sign-in UI;
* Clerk CLI initialization in `frontend/web`;
* Flutter Clerk SDK;
* Laravel Clerk middleware;
* actual JWT verifier;
* customer registration logic;
* login/logout implementation;
* password reset UI;
* email verification UI;
* profile synchronization implementation;
* role policies;
* authorization gates;
* rate limiting;
* webhooks;
* Admin UI;
* production Clerk deployment;
* MFA configuration;
* Organizations;
* billing;
* payment authentication;
* catalog APIs.

This phase establishes architecture only.

---

# 67. STOP Condition

STOP when the Clerk authentication architecture, local-user mapping, trust boundaries, field ownership, API contract impact, and future implementation responsibilities are fully documented and internally consistent.

Do not continue automatically to customer registration.

The next project-owner request should begin:

**Phase 4.2 — Customer Registration with Clerk / Local User Provisioning**

using the architecture approved in Phase 4.1.
