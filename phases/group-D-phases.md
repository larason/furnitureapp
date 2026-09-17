# Phase 4.8 — Website Authentication Contract / Next.js Clerk Integration Boundary

## Purpose

Define and validate the authentication contract that the future Next.js website will use with Clerk and the Laravel backend.

This is **not a frontend implementation phase**.

The purpose is to ensure that when website development begins in the later frontend groups, the frontend agent already has a stable, secure authentication boundary to implement.

The future architecture is:

```text
Next.js website
    ↓
Clerk browser session
    ↓
Clerk session token
    ↓
Authorization: Bearer <token>
    ↓
Laravel API
    ↓
existing Clerk authentication middleware
    ↓
local Laravel User
    ↓
Laravel RBAC / policies / domain
```

Phase 4.8 defines this contract.

It must **not build the website implementation yet**.

---

# 1. Critical Phase Boundary

Do not modify:

```text
frontend/web/
frontend/app/
frontend/design-system/
```

during Phase 4.8.

Do not:

```text
install @clerk/nextjs
run clerk init
create ClerkProvider
create proxy.ts
create middleware.ts
create /sign-in
create /sign-up
create authentication layouts
create React hooks
create frontend API clients
create website auth state
```

Those belong to the later frontend phases.

This separation is intentional.

It ensures the eventual authentication pages are built with the same:

```text
design tokens
MUI theme
layout system
responsive rules
navigation shell
form primitives
spacing
typography
error patterns
loading states
accessibility patterns
```

as the rest of the website.

---

# 2. Why Frontend Work Is Deferred

The website frontend is developed later in Groups L–O.

Flutter implementation is developed later in Groups P–Q.

Authentication UI must therefore be created alongside those applications, not before them.

Building Clerk pages now risks:

* inconsistent layouts;
* duplicated UI primitives;
* design-token divergence;
* separate form conventions;
* inconsistent responsive behavior;
* premature routing decisions;
* unnecessary refactoring later.

Phase 4.8 must establish only the **integration contract**.

---

# 3. Dependencies

Required:

* Phase 4.1 complete;
* Phase 4.2 complete;
* Phase 4.3 complete;
* Phase 4.4 complete;
* Phase 4.5 complete;
* Phase 4.6 complete;
* Phase 4.7 mobile boundary complete;
* Laravel accepts verified Clerk session tokens;
* Clerk identity resolves to `users.clerk_user_id`;
* `/me` works;
* Clerk owns passwords, email verification, sessions, and recovery;
* Laravel owns RBAC and application authorization.

Do not compensate for incomplete backend authentication by designing frontend workarounds.

---

# 4. Authoritative Inputs

Review:

1. `AGENTS.md`
2. Phase 4.1 Clerk architecture decision
3. Phase 4.2 local provisioning
4. Phase 4.3 authenticated request resolution
5. Phase 4.4 recovery/security
6. Phase 4.5 email verification
7. Phase 4.6 profile operations
8. Phase 4.7 mobile API authentication boundary
9. `docs/api/api-contract.md`
10. `docs/api/api-resources.md`
11. `docs/api/api-conventions.md`
12. `docs/api/openapi.yaml`
13. `docs/domain/business-rules.md`
14. `docs/decisions.md`

Use current Clerk documentation/MCP/skills where provider behavior needs verification.

Do not implement frontend code.

---

# 5. Core Contract

The future website must authenticate customers using Clerk.

Laravel must authenticate API requests using the Clerk session token.

Canonical boundary:

```text
Clerk authenticated website session
        ↓
current Clerk session token
        ↓
Authorization: Bearer <token>
        ↓
Laravel Clerk verifier
        ↓
token.sub
        ↓
users.clerk_user_id
        ↓
local User
```

This is the same fundamental API authentication boundary defined for mobile.

---

# 6. No Second Website Authentication System

The future website must not introduce:

```text
Laravel Sanctum SPA auth
Laravel password login
NextAuth/Auth.js
custom JWT
custom refresh token
custom session database
parallel customer cookie auth
```

Clerk remains the sole customer authentication/session provider.

Laravel remains the application backend.

---

# 7. Future Next.js Responsibility

When the frontend phase eventually reaches website authentication, Next.js will be responsible for:

```text
Clerk frontend integration
signup UI
signin UI
email verification UI
password recovery UI
Clerk session state
obtaining current Clerk session token
passing token to Laravel
frontend routing/navigation
```

These are **future implementation responsibilities**, not Phase 4.8 tasks.

---

# 8. Future Laravel Responsibility

Laravel already owns:

```text
verify Clerk token
resolve local User
JIT provision CUSTOMER
check account state
apply role/permissions
apply ownership
apply policies
execute commerce logic
```

Do not move any of these responsibilities to Next.js later.

---

# 9. Authentication vs Authorization

Document this clearly:

```text
Clerk:
Who is this user?

Laravel:
What may this user do?
```

The frontend may hide or show UI based on Laravel-provided role/application state.

The frontend must never become the authorization authority.

---

# 10. Website Signup Contract

Future website customer signup:

```text
email
password
```

Required:

```text
email verification through Clerk
```

Not required:

```text
phone
```

Do not change this contract during frontend implementation unless a formal product decision changes it.

---

# 11. Website Sign-In Contract

Future customer sign-in:

```text
email
password
```

through Clerk.

Laravel must never receive the password.

There must be no future request such as:

```http
POST /api/v1/login
```

containing customer credentials unless the frozen contract explicitly retained such an endpoint, which the Clerk migration should have retired.

---

# 12. Email Verification Contract

Clerk owns:

```text
verification code/link generation
delivery
expiration
resend
verification state
```

Future frontend must complete the Clerk verification flow.

Laravel must not implement a competing verification mechanism.

---

# 13. Password Recovery Contract

Clerk owns:

```text
forgot password
reset password
credential security
recovery verification
```

Future Next.js pages/components must use Clerk.

Laravel must not accept password-reset credentials.

---

# 14. Future Session Transport

The future website must obtain the current Clerk session token using Clerk's supported Next.js integration.

It must then call Laravel with:

```http
Authorization: Bearer <Clerk session token>
```

Do not introduce another token exchange.

---

# 15. Future Server-Side Requests

Preferred website architecture for authenticated server-rendered content:

```text
Browser
    ↓
Next.js
    ↓
Clerk server auth context
    ↓
current Clerk token
    ↓
Laravel
```

This is an implementation guideline for the future frontend phase.

Do not implement it in Phase 4.8.

---

# 16. Future Client-Side Requests

If a future interactive Client Component needs to call Laravel directly:

```text
Client Component
    ↓
Clerk-supported current token retrieval
    ↓
Authorization: Bearer <token>
    ↓
Laravel
```

Do not persist the token manually.

Do not create a second frontend token store.

---

# 17. Public Website Contract

Public pages must remain usable without authentication.

Examples include:

```text
homepage
product listing
product detail
categories
search
public furniture browsing
```

The future authentication integration must not globally protect the website.

This preserves:

```text
SEO
SSR
public caching
anonymous browsing
```

---

# 18. Protected Website Contract

Future customer areas may require authentication:

```text
account
profile
orders
notifications
checkout
customer request history
customer enquiry history
```

Frontend routing may enforce sign-in for UX.

Laravel must still enforce backend authentication and authorization.

---

# 19. Checkout Boundary

Preserve:

```text
Browse → anonymous allowed
Cart → according to cart policy
Checkout → authentication required
```

Future frontend behavior:

```text
anonymous customer
    ↓
attempts checkout
    ↓
Clerk sign-in/signup
    ↓
return to checkout
```

Laravel still independently enforces authentication.

---

# 20. `/me` Contract

The future website should use:

```http
GET /api/v1/me
```

to obtain the application user.

This is the source of:

```text
Laravel user ID
application profile
name
phone
email snapshot
role
application account state
```

according to the finalized API representation.

Do not use the Clerk User object as the full application User.

---

# 21. Role Contract

Future website authorization-related UI must use Laravel-derived role/application state.

Do not use:

```text
Clerk publicMetadata.role
Clerk unsafeMetadata.role
```

as the application RBAC source.

Laravel remains authoritative.

---

# 22. Profile Contract

Future profile UI must use:

```http
GET /api/v1/me
PATCH /api/v1/me
```

for Laravel-owned fields.

Current ownership:

```text
name → Laravel
phone → Laravel, optional
email → Clerk
email verification → Clerk
password → Clerk
role → Laravel, server controlled
```

Frontend implementation must preserve this separation.

---

# 23. Future Email Change

The future UI must not implement:

```text
PATCH /me {email}
```

Email changes require Clerk security/reverification.

After successful Clerk email change, Laravel's email snapshot may be reconciled.

Implementation remains for the appropriate frontend/account phase.

---

# 24. Future Phone Editing

Phone is ordinary Laravel profile/contact data.

It is:

```text
optional
not an authentication identifier
not required at signup
```

Future profile UI may edit it through `/me`.

---

# 25. Token Storage Rule

Future frontend implementation must not manually persist Clerk session tokens in:

```text
localStorage
sessionStorage
IndexedDB
application Redux store
custom cookies
```

solely for Laravel API access.

Clerk owns browser session handling.

Retrieve a current token when required.

---

# 26. Server Token Isolation

Future Next.js server-side code must never place the Clerk session token in:

```text
rendered HTML
serialized page props
client component props
logs
public cache
```

Use the token only for authenticated server-to-Laravel requests.

---

# 27. Caching Contract

Public Laravel data:

```text
products
categories
public catalog
```

may use normal website/public caching strategy.

Private Laravel data:

```text
/me
orders
notifications
checkout state
```

must not use shared/public caching.

The frontend phases must preserve the backend private cache contract.

---

# 28. Error Handling Contract

Future website implementation must use Laravel's standard error envelope:

```text
HTTP status
code
field
details
meta.request_id
```

Do not parse English error strings for application logic.

---

# 29. 401 Contract

```text
401
```

means the Laravel API does not accept the current authentication state.

Future frontend should:

* check current Clerk session;
* obtain current token if appropriate;
* retry only where safe;
* route to sign-in if the session is no longer usable.

Do not implement infinite retry loops.

---

# 30. 403 Contract

```text
403
```

means:

```text
authenticated
but not authorized
```

Future frontend must not automatically sign out on 403.

---

# 31. 404 Contract

Private:

```text
404 RESOURCE_NOT_FOUND
```

may intentionally mask ownership.

Future frontend must not treat it as an authentication failure.

---

# 32. Mutation Retry Contract

Future frontend must not automatically replay unsafe mutations merely because authentication changed.

Especially:

```text
checkout
payment
order actions
inventory-sensitive operations
```

Retry only where the backend endpoint's idempotency contract makes it safe.

---

# 33. Logout Contract

Future website logout:

```text
Clerk sign out current session
    ↓
clear private frontend application state
    ↓
future Laravel protected calls unauthenticated
```

Do not:

```text
delete Laravel User
delete Orders
delete CustomerProfile
```

during logout.

---

# 34. Multi-Device Contract

A customer may be signed in simultaneously on:

```text
browser
Flutter app
another browser
```

All valid sessions for:

```text
same Clerk user
```

must resolve to:

```text
same Laravel User
```

Do not create client-specific local accounts.

---

# 35. Pending Clerk Session Contract

If Clerk authentication is incomplete because of a required security task:

```text
password reset
MFA setup
verification requirement
```

the future frontend must not treat the user as fully authenticated for protected application access.

Use Clerk's current supported session state semantics.

---

# 36. CORS Contract

If future browser code calls Laravel directly:

Laravel CORS must allow only approved website origins.

Do not use permissive:

```text
*
```

for production authenticated browser API access.

If Next.js server-side code calls Laravel, browser CORS does not apply to that request path.

---

# 37. CSRF Contract

Do not introduce Laravel customer cookie authentication merely for website convenience.

Customer API authentication remains:

```text
Bearer Clerk session token
```

This keeps the Laravel customer API boundary consistent across web and mobile.

---

# 38. Authorized Party Validation

Laravel's Clerk verifier should already validate approved token origins/authorized parties according to Phase 4.1–4.3.

Phase 4.8 must verify that the configuration model can later include:

```text
development website origin
staging website origin
production website origin
```

without frontend implementation today.

---

# 39. Environment Contract

Future Next.js implementation will require client-safe and server-only Clerk configuration.

Document expected categories:

```text
Clerk publishable configuration → browser-safe
Clerk secret configuration → server-only
Laravel API URL → environment-specific
```

Do not add actual frontend env files in Phase 4.8.

Do not store secrets in documentation.

---

# 40. Do Not Run Clerk CLI

Phase 4.8 must not run:

```bash
clerk init
clerk auth login
clerk doctor
```

for the frontend.

Those commands belong when:

```text
frontend/web/
```

becomes an active implementation target.

---

# 41. Do Not Install Packages

Do not install:

```text
@clerk/nextjs
@clerk/ui
```

during this phase.

The frontend package versions should be chosen when the Next.js project itself is active.

This avoids premature dependency/version coupling.

---

# 42. Do Not Create Frontend Routes

Do not create:

```text
/sign-in
/sign-up
/account
/profile
```

in Phase 4.8.

Those pages must be designed in the context of the final frontend architecture.

---

# 43. Do Not Create Frontend Providers

Do not create:

```text
ClerkProvider
AuthProvider
ApplicationUserProvider
```

during this phase.

Their placement depends on the future app shell/layout architecture.

---

# 44. Do Not Create Frontend Middleware

Do not create:

```text
proxy.ts
middleware.ts
```

during Phase 4.8.

Their eventual configuration depends on:

* installed Next.js version;
* actual routes;
* public/protected route structure;
* application shell.

Document requirements only.

---

# 45. Do Not Create Sign-In Components

Do not build:

```text
<SignIn />
<SignUp />
<SignInButton />
<SignUpButton />
<UserButton />
```

yet.

These are frontend implementation details.

---

# 46. Do Not Style Authentication Yet

Do not define:

```text
Clerk appearance config
auth page spacing
auth page typography
button styles
form card styles
```

during Group D.

Authentication UI must later use the same website design system.

---

# 47. Design-System Handoff

The future web authentication phase must inherit:

```text
MUI theme
Nike-inspired tokens
shared form primitives
shared buttons
shared cards/surfaces
shared error components
shared responsive layout
```

from the website foundation.

Do not create parallel authentication design tokens.

---

# 48. Future Web Implementation Sequence

When the frontend reaches the appropriate group, follow roughly:

```text
website foundation
        ↓
MUI theme
        ↓
design tokens
        ↓
reusable primitives
        ↓
navigation/layout shell
        ↓
Clerk integration
        ↓
signup/signin pages
        ↓
customer commerce pages
```

Do not reverse this order.

---

# 49. Future Sign-In Page

When eventually implemented, the sign-in page should use the same:

```text
container widths
form components
buttons
typography
responsive behavior
error messaging
```

as other website forms.

Do not implement it now.

---

# 50. Future Sign-Up Page

Likewise, future signup UI will collect:

```text
email
password
```

and complete Clerk email verification.

Phone remains outside signup.

Do not implement it now.

---

# 51. Future Recovery Page

Password recovery must use Clerk.

The visual implementation should follow the same frontend design system.

Do not implement it now.

---

# 52. Future Verification Page

Email verification must use Clerk.

Do not implement a custom Laravel verification form.

Do not implement its UI now.

---

# 53. Future Account Security UI

Password/security management remains Clerk-owned.

The website account UI may later expose Clerk account/security actions.

Do not build them during Group D.

---

# 54. Backend Review

Phase 4.8 should verify that Laravel already supports everything the future website requires:

```text
Bearer authentication
local User resolution
JIT provisioning
/me
401 behavior
403 behavior
private resource authorization
email/password Clerk model
email verification state
```

If a backend gap exists, fix it in the appropriate backend abstraction.

Do not create a web-specific backend pathway.

---

# 55. No Website-Specific Laravel Authentication

Do not create:

```text
AuthenticateNextJs
WebClerkController
WebsiteTokenExchange
WebLoginController
```

The Laravel authentication boundary must remain client-neutral.

---

# 56. Same Boundary as Mobile

Phase 4.7 established:

```text
Flutter
→ Clerk session token
→ Laravel
```

Phase 4.8 establishes:

```text
Next.js
→ Clerk session token
→ Laravel
```

Laravel should not care which client sent the valid token.

---

# 57. Client-Neutral Backend

Mandatory invariant:

```text
same Clerk user
from Flutter
or Next.js
        ↓
same users.clerk_user_id
        ↓
same Laravel User
```

No `client_type` should participate in identity resolution.

---

# 58. OpenAPI Review

Review protected routes.

Ensure the OpenAPI security scheme describes:

```text
Bearer authentication
using a Clerk-issued authenticated session token
```

Do not add frontend-specific auth endpoints.

---

# 59. No New Website Auth API

Do not introduce:

```text
/api/v1/web/login
/api/v1/web/signup
/api/v1/web/token
/api/v1/web/refresh
```

The website authenticates with Clerk directly.

---

# 60. Contract Documentation

Document the expected future website flow in existing consolidated documentation.

A concise sequence is enough:

```text
1. User signs in/up through Clerk.
2. Clerk establishes browser session.
3. Next.js obtains current Clerk session token.
4. Next.js sends token to Laravel as Bearer.
5. Laravel authenticates token.
6. Laravel resolves local User.
7. Laravel handles authorization/business logic.
```

Do not create excessive new documents.

---

# 61. Documentation Updates

Likely update only:

```text
docs/api/api-conventions.md
docs/decisions.md
AGENTS.md
```

and possibly:

```text
docs/api/api-contract.md
docs/api/openapi.yaml
```

where auth semantics need clarification.

Keep consolidated docs authoritative.

---

# 62. `AGENTS.md` Handoff

Clarify that:

```text
Group D
→ establishes web authentication contract

frontend Groups L–O
→ implement website authentication

Groups P–Q
→ implement Flutter authentication
```

This prevents future agents from prematurely modifying frontend applications.

---

# 63. Future Frontend Agent Instructions

Record the future website implementation requirements concisely:

```text
Use @clerk/nextjs.
Use current App Router integration.
Use ClerkProvider.
Use current Clerk middleware/proxy convention.
Use await auth() server-side.
Use getToken() for Laravel calls.
Do not introduce another auth provider.
Do not use Clerk metadata for Laravel role.
```

These are implementation constraints for the later phase.

Do not execute them today.

---

# 64. Next.js Version Deferred

Do not decide:

```text
proxy.ts
vs
middleware.ts
```

until the actual Next.js version in `frontend/web` is being implemented.

At that time:

```text
inspect installed Next.js version
follow current Clerk guidance
```

Do not create files based on assumptions now.

---

# 65. Clerk SDK Version Deferred

Do not lock:

```text
@clerk/nextjs version
```

during Group D.

Install the then-current compatible release when the frontend implementation phase begins.

This avoids stale dependency decisions.

---

# 66. Authentication UI Choice Deferred

Do not decide prematurely between:

```text
Clerk prebuilt components
custom Clerk flow
```

until:

* website design primitives exist;
* form conventions exist;
* page layouts exist;
* UX requirements are known.

Default recommendation for later remains:

```text
prefer standard Clerk components unless custom UI is necessary
```

but do not implement now.

---

# 67. MUI Integration Deferred

Do not solve Clerk/MUI styling in Group D.

When the website design system exists, auth pages can be integrated correctly.

This is one of the main reasons frontend work is deferred.

---

# 68. No Authentication State Management Decision Yet

Do not add:

```text
Redux
Zustand
React Context
```

for auth.

The future app should use Clerk for authentication state and only introduce application-user state if necessary.

The final decision belongs to the website architecture phase.

---

# 69. No BFF Decision Yet

Do not build or commit the website to a large Backend-for-Frontend layer.

Document both supported future patterns:

```text
Next.js server → Laravel
```

and where necessary:

```text
browser → Laravel with current Clerk token
```

Choose the simplest per feature when frontend implementation begins.

---

# 70. Backend Tests — Valid Web Token

Existing Clerk authentication tests should establish:

```text
valid Clerk session token
→ authenticated Laravel User
```

This is client-independent.

If not sufficiently covered, add backend tests.

Do not need actual Next.js code.

---

# 71. Backend Tests — Missing Token

Protected endpoint:

```text
no token
```

must return:

```text
401 AUTHENTICATION_REQUIRED
```

This guarantees the future website has a stable contract.

---

# 72. Backend Tests — Invalid Token

Invalid Clerk credential:

```text
401
```

without local provisioning.

No provider internals exposed.

---

# 73. Backend Tests — Valid CUSTOMER

Valid token for mapped CUSTOMER:

```text
GET /me
```

returns correct application profile.

---

# 74. Backend Tests — First Website Session

Simulate a valid Clerk user with no local Laravel user.

First protected request:

```text
→ Phase 4.2 provisioning
→ CUSTOMER
→ request succeeds
```

This represents both future website and mobile behavior.

---

# 75. Backend Tests — Role Authority

Simulate:

```text
Clerk metadata role = ADMIN
Laravel role = CUSTOMER
```

Verify Laravel remains CUSTOMER.

This protects future frontend implementation from accidental Clerk role dependence.

---

# 76. Backend Tests — Same User Across Clients

Conceptually simulate two valid Clerk sessions with the same:

```text
sub
```

Verify both resolve to the same local Laravel User.

No actual Next.js or Flutter code is required.

---

# 77. Backend Tests — Public Catalog

Ensure public catalog endpoints remain accessible without a token.

Authentication work must not globally protect API routes.

---

# 78. Backend Tests — `/me` Privacy

Verify:

```text
GET /me
```

uses private/no-store response semantics.

Future web caching can rely on this contract.

---

# 79. Backend Tests — 403

Authenticated customer attempting unauthorized operation must receive:

```text
403
```

or masking behavior according to the specific resource policy.

Do not convert authorization failures into login failures.

---

# 80. No Frontend Test Files

Do not create:

```text
React component tests
Playwright auth tests
Next.js tests
```

during this phase.

No frontend implementation exists yet.

Those tests belong with the future frontend features.

---

# 81. Clerk Live Testing

Do not require a real Next.js client or Clerk browser session in ordinary Group D tests.

Backend authentication should use fakes/test tokens/verifier abstractions.

Full browser authentication will be tested when frontend implementation exists.

---

# 82. No Playwright / Cypress

Do not install:

```text
Playwright
Cypress
```

for Phase 4.8.

Frontend E2E testing belongs to later frontend/QA phases.

---

# 83. No Frontend Package Changes

Expected changes under:

```text
frontend/
```

should be:

```text
NONE
```

during Phase 4.8.

If the agent believes frontend modification is necessary, stop and document why rather than proceeding.

---

# 84. Expected Backend Code Changes

Expected:

```text
none
or minimal
```

because Phase 4.3 should already provide the client-neutral Clerk bearer-token authentication boundary.

Phase 4.8 primarily verifies and documents readiness for the future website.

---

# 85. Expected Schema Changes

Expected:

```text
NONE
```

Do not add:

```text
web_session
website_token
nextjs_user_id
browser_session
```

to the database.

---

# 86. No User-Agent Authentication

Do not identify or authorize website users using:

```text
User-Agent
browser cookie created by Laravel
client_type
```

Only the verified Clerk credential establishes identity.

---

# 87. No Website-Specific Role

Do not add roles such as:

```text
WEB_CUSTOMER
MOBILE_CUSTOMER
```

The role remains:

```text
CUSTOMER
```

independent of client.

---

# 88. No Duplicate Profiles

Do not create:

```text
web_profile
mobile_profile
```

The same Laravel profile is shared across clients.

---

# 89. Shared Profile Invariant

Future:

```text
customer changes phone on website
```

then Flutter should later see the same value through:

```text
GET /me
```

because Laravel is the profile authority.

This must remain true.

---

# 90. Security Checklist

Verify architecture guarantees:

```text
Clerk authenticates website customers
Laravel verifies Clerk session tokens
Laravel owns authorization
password never reaches Laravel
email verification stays Clerk-owned
phone remains optional
no second auth system
no website-specific backend login
no frontend token persistence requirement
same user across web/mobile
public catalog remains public
```

---

# 91. Avoid Overengineering

Do not create:

```text
WebsiteAuthenticationService
FrontendAuthGateway
NextJsSessionBridge
TokenExchangeService
WebIdentityAdapter
BrowserAuthRepository
```

in Laravel.

They are unnecessary.

The backend already receives a standard Bearer token.

---

# 92. Maintain Client Neutrality

Laravel should conceptually see:

```text
authenticated Clerk request
```

not:

```text
Next.js request
Flutter request
```

This dramatically simplifies maintenance.

---

# 93. Quality Requirements

Any backend/documentation changes must maintain:

* cognitive complexity ≤15;
* maximum 3 returns where practical;
* strict validation;
* centralized configuration;
* provider-independent domain logic;
* minimal comments;
* no duplicated authentication logic.

---

# 94. Commands / Verification

If only documentation changes occur:

run any documentation/schema validation used by the repository.

Also run relevant backend tests:

```bash
cd backend/laravel

php artisan test
vendor/bin/pint --test
vendor/bin/phpstan analyse
composer audit
```

Use project-defined equivalent scripts where applicable.

Do not run frontend install/build commands because the frontend is not being implemented.

---

# 95. Files Changed Report

At completion report:

## Files changed

Exact paths.

## Frontend changes

Must state:

```text
NONE
```

## Backend changes

Expected:

```text
none or minimal
```

Explain any exception.

## Contract decisions

Confirm:

```text
future Next.js
→ Clerk
→ session token
→ Laravel bearer auth
```

## Tests

List backend tests added/run.

## Deferred frontend work

Clearly identify later website phases.

---

# 96. Frontend Handoff Checklist

Leave a concise handoff for the future website agent:

```text
[ ] initialize Clerk only inside frontend/web
[ ] install current compatible @clerk/nextjs
[ ] follow actual installed Next.js version
[ ] place ClerkProvider according to current Clerk guidance
[ ] configure Clerk middleware/proxy
[ ] keep catalog public
[ ] protect account/checkout/customer routes
[ ] use await auth() server-side
[ ] use getToken() for Laravel calls
[ ] send Authorization Bearer
[ ] use Laravel /me for role/profile
[ ] do not use Clerk metadata as RBAC
[ ] signup = email + password
[ ] phone not required
[ ] email verification through Clerk
[ ] use shared website design system/components
```

Do not execute this checklist in Phase 4.8.

---

# 97. Relationship to Group L–O

Group D:

```text
defines authentication architecture
```

Groups L onward:

```text
build website foundation
design system
layouts
components
pages
```

Then the relevant website authentication/customer-commerce phase:

```text
implements Clerk UI and routing
```

This order is mandatory.

---

# 98. Relationship to Groups P–Q

Likewise:

```text
Phase 4.7
→ defines Flutter authentication contract

Groups P–Q
→ implement Flutter authentication and customer UI
```

Do not allow Group D to become an early frontend implementation group.

---

# 99. Definition of Done

Phase 4.8 is complete when:

* future Next.js authentication architecture is explicitly defined;
* the website will use Clerk as its sole authentication provider;
* Laravel will receive Clerk session tokens through `Authorization: Bearer`;
* the existing client-neutral Laravel authentication boundary is sufficient;
* `/me` is confirmed as the future source of application profile/role;
* public and protected route expectations are documented;
* signup remains email + password;
* email verification remains Clerk-owned;
* phone remains optional;
* no website-specific Laravel login/token endpoints exist;
* no NextAuth/Auth.js or Sanctum SPA auth is planned;
* web and mobile identities resolve to the same local User;
* caching/security/error behavior is documented for the future frontend;
* backend regression tests pass;
* no frontend code is created;
* no frontend packages are installed;
* no Clerk frontend CLI initialization is performed;
* no frontend routing/layout/design decisions are prematurely implemented;
* future frontend agents have a clear implementation handoff.

---

# 100. Out of Scope

Do not implement:

* `frontend/web` Clerk setup;
* `@clerk/nextjs`;
* `ClerkProvider`;
* `proxy.ts`;
* `middleware.ts`;
* sign-in page;
* sign-up page;
* verification page;
* password recovery page;
* website auth layout;
* account navigation;
* frontend API client;
* React auth state;
* MUI authentication components;
* website styling;
* Flutter implementation;
* E2E browser tests;
* social login;
* phone login;
* MFA UI;
* Clerk Organizations;
* BFF architecture.

---

# 101. STOP Condition

STOP once the future website authentication contract is fully documented, the Laravel backend is confirmed ready to accept Clerk-authenticated website requests through the same client-neutral Bearer-token boundary used by mobile, and all relevant backend checks pass.

There must be:

```text
NO frontend implementation
NO frontend package installation
NO Clerk frontend initialization
```

during Phase 4.8.

Do not continue automatically.

The next backend roadmap phase is:

**Phase 4.9 — Roles / Laravel RBAC Integration**

Actual website Clerk implementation must wait until the corresponding frontend phase in Groups L–O is reached.
