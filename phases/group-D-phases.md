# Phase 4.7 — API Authentication for Mobile / Flutter Clerk Boundary

## Purpose

Define and implement the **mobile authentication boundary** between the future Flutter application, Clerk, and the existing Laravel API.

Keep this phase simple.

The required architecture is:

```text
Flutter
   ↓
Clerk authentication
   ↓
Clerk session token
   ↓
Authorization: Bearer <token>
   ↓
Laravel API
   ↓
existing Clerk authentication middleware
   ↓
users.clerk_user_id
   ↓
local Laravel User
   ↓
Laravel RBAC / policies / domain
```

Do not create another mobile authentication system.

Do not create Laravel-issued mobile tokens.

Do not duplicate authentication logic already implemented in Phases 4.2–4.6.

---

# 1. Dependencies

Required:

* Phase 4.1 complete;
* Phase 4.2 local-user provisioning complete;
* Phase 4.3 Clerk session authentication complete;
* Phase 4.4 recovery/security complete;
* Phase 4.5 email verification complete;
* Phase 4.6 profile operations complete;
* Laravel already accepts valid Clerk session tokens;
* authenticated Clerk `sub` resolves to local `users.clerk_user_id`;
* Laravel RBAC remains authoritative.

If Laravel authentication middleware is not already working, fix the earlier phase instead of creating mobile-specific authentication.

---

# 2. Scope

This phase should establish:

* how Flutter authenticates through Clerk;
* how Flutter obtains a valid Clerk session token;
* how that token is sent to Laravel;
* how the mobile networking layer attaches authentication;
* how 401/session expiration is handled;
* how logout works;
* minimal secure token/session persistence rules;
* mobile authentication interfaces for future Flutter implementation;
* tests for the mobile/API boundary;
* documentation.

Keep the implementation small.

---

# 3. Important Current Clerk Constraint

Current Clerk documentation provides official SDK quickstarts for:

```text
Android
iOS
Expo
web frameworks
```

but does not currently list Flutter as an official quickstart target.

Therefore:

> Do not tightly couple the entire Flutter application to an unofficial Clerk package.

If the project uses a community-maintained Flutter Clerk package, isolate it behind one small authentication adapter.

Do not spread package-specific Clerk classes throughout the app.

---

# 4. Standard Architecture

Use a conventional mobile architecture:

```text
UI
 ↓
AuthRepository
 ↓
ClerkAuthAdapter
 ↓
Clerk session
```

and:

```text
API Repository
 ↓
ApiClient
 ↓
Auth token provider
 ↓
Authorization: Bearer <Clerk session token>
 ↓
Laravel
```

That is enough.

Do not introduce:

* authentication microservices;
* custom OAuth server;
* Firebase Auth;
* Laravel Sanctum;
* custom JWT issuance;
* token-exchange server;
* multiple auth providers.

---

# 5. One Identity System

The same Clerk account must work across:

```text
Next.js
Flutter
```

and map to exactly one:

```text
Laravel users.id
```

Invariant:

```text
Clerk user_ABC
    ↓
users.clerk_user_id = user_ABC
```

regardless of client.

Do not create mobile-specific users.

---

# 6. Flutter Authentication Abstraction

The Flutter app should eventually expose a very small application-facing interface.

Conceptually:

```text
AuthRepository
```

Responsibilities:

```text
signIn(...)
signUp(...)
signOut()
getSessionToken()
isSignedIn
currentUser
```

Only include operations the app actually needs.

Do not design a massive generic authentication framework.

---

# 7. Clerk Adapter

Create or plan one implementation:

```text
ClerkAuthRepository
```

or:

```text
ClerkAuthAdapter
```

depending on existing Flutter conventions.

This adapter is the only place that should know about the selected Clerk Flutter/community/native integration.

Other application code should depend on:

```text
AuthRepository
```

not Clerk package classes.

---

# 8. Do Not Implement Flutter UI Yet

This phase is primarily about the API/authentication boundary.

Do not build:

* sign-in screen;
* sign-up screen;
* forgot-password UI;
* verification UI;
* profile UI.

Those belong to the later Flutter application phases.

If `frontend/app/` is not initialized yet, document the interface/design rather than scaffolding unrelated UI work.

---

# 9. Mobile Sign-Up Policy

The existing authentication policy is:

```text
required:
- email
- password

email verification:
- Clerk

phone:
- not required
```

Do not introduce mobile-specific signup requirements.

Flutter must use the same Clerk account configuration as web.

---

# 10. Sign-In Policy

Customer sign-in remains:

```text
email
+
password
```

through Clerk.

Flutter must never send the customer password to Laravel.

Flow:

```text
Flutter
   ↓
Clerk
   ↓
authenticated session
   ↓
session token
   ↓
Laravel
```

---

# 11. Laravel Must Never Receive Passwords

Do not create:

```http
POST /api/v1/mobile/login
```

with:

```json
{
  "email": "...",
  "password": "..."
}
```

Do not proxy passwords through Laravel.

Do not create a special mobile login controller.

---

# 12. Session Token

After successful Clerk authentication, obtain the **current Clerk session token** using the selected Clerk SDK/integration.

Use that token to authenticate Laravel requests.

The mobile app must not fabricate or decode its own authentication claims.

---

# 13. API Header

Use the standard header:

```http
Authorization: Bearer <Clerk session token>
```

No custom authentication header.

Do not use:

```text
X-User-Id
X-Clerk-Id
X-Mobile-Token
```

for authentication.

---

# 14. Token Provider

The networking layer should depend on a small abstraction such as:

```text
AuthTokenProvider
```

Conceptually:

```text
Future<String?> getToken();
```

The implementation obtains the current Clerk session token.

This keeps the API client independent from Clerk SDK details.

---

# 15. API Client

The Flutter API client should attach the token automatically for authenticated requests.

Conceptually:

```text
request
   ↓
get current token
   ↓
if present:
Authorization: Bearer ...
   ↓
Laravel
```

Do not manually add the token separately in every repository method.

---

# 16. Public API Calls

Public routes must remain usable without authentication.

Examples:

```text
products
categories
search
product detail
```

The API client should not require a token for public calls.

It is acceptable if a valid token is sometimes attached globally, but do not make public API functionality depend on having one.

Prefer predictable route/client behavior.

---

# 17. Protected API Calls

Protected calls include examples such as:

```text
/me
checkout
own orders
notifications
own requests
own enquiries
```

These require a valid Clerk session token.

Laravel remains responsible for returning:

```text
401 AUTHENTICATION_REQUIRED
```

when authentication is absent or unusable.

---

# 18. No Client-Side Authorization Authority

Flutter may use role/profile state for navigation UX.

It must not decide final authorization.

For example:

```text
if (user.role == ADMIN)
```

may influence what UI is shown.

It must never replace Laravel policy checks.

Laravel remains authoritative.

---

# 19. Token Lifetime

Clerk session tokens are short-lived.

Do not assume the token obtained at login remains valid indefinitely.

Do not cache one bearer token permanently.

Retrieve a current valid token through the Clerk session abstraction when required.

---

# 20. Do Not Build Custom Refresh Tokens

Do not create:

```text
mobile_refresh_token
Laravel refresh token
custom JWT refresh endpoint
```

Clerk owns session renewal.

The mobile adapter should ask Clerk for the current usable session token.

---

# 21. Secure Storage

Avoid unnecessary manual token storage.

Preferred model:

```text
Clerk SDK/integration owns session persistence
```

and Flutter requests the current token when needed.

Only use secure device storage if required by the selected Clerk integration.

If manual secure storage is required, use the platform's protected credential storage through a maintained Flutter package.

Never store auth credentials in:

```text
SharedPreferences
plain SQLite
plain file
logs
Hive without encryption
```

---

# 22. Do Not Store Passwords

Never persist:

```text
email + password
```

for automatic login.

Session persistence belongs to Clerk.

Do not build "remember me" by storing the password.

---

# 23. App Restart

Expected behavior:

```text
app launches
    ↓
initialize Clerk auth adapter
    ↓
restore existing Clerk session if valid
    ↓
signed in
```

or:

```text
no valid Clerk session
    ↓
signed out
```

Do not contact Laravel solely to determine whether Clerk has a session.

---

# 24. Current Application User

After Clerk session restoration:

```text
GET /api/v1/me
```

should be used to obtain current Laravel application profile/role state when needed.

This keeps:

```text
role
account state
profile
```

authoritative from Laravel.

Do not rely on stale locally cached role information for sensitive decisions.

---

# 25. Recommended App Startup Sequence

Keep it straightforward:

```text
initialize application
    ↓
initialize Clerk
    ↓
check Clerk session
    ↓
if authenticated:
    GET /me
    ↓
load application
```

Do not create a complicated authentication state machine unless the actual SDK requires it.

---

# 26. Authentication States

At application level, a small state model is enough:

```text
loading
authenticated
unauthenticated
error
```

If Clerk exposes required pending verification/security tasks, surface them through the adapter when necessary.

Do not copy every Clerk internal state into the app.

---

# 27. Email Verification

Email verification is already owned by Clerk.

Flutter should complete Clerk's verification flow when signing up.

Laravel should only receive a usable session after the approved Clerk authentication flow is complete.

Do not create mobile-specific Laravel verification endpoints.

---

# 28. Password Recovery

Password recovery is already owned by Clerk.

Flutter eventually invokes Clerk's recovery flow.

Do not create:

```text
/api/v1/mobile/forgot-password
```

or:

```text
/api/v1/mobile/reset-password
```

---

# 29. Logout

Standard mobile logout:

```text
Flutter
    ↓
Clerk signOut / terminate current session
    ↓
clear application auth/profile state
    ↓
return to unauthenticated state
```

Do not delete the Laravel User.

Do not delete customer orders/history.

---

# 30. Local Application Cache on Logout

On logout, clear private in-memory/local cached application state where appropriate:

```text
current profile
private orders cache
notifications cache
authenticated cart state
```

Do not delete unrelated public catalog cache unnecessarily.

Do not rely on cache clearing as the actual logout mechanism.

Clerk session termination is the security operation.

---

# 31. 401 Handling

The API client should have one centralized 401 strategy.

When Laravel returns:

```text
401
```

do not immediately assume every failure requires destructive logout.

First ask the Clerk authentication layer for the current session/token according to its supported semantics.

If the Clerk session is no longer valid:

```text
transition to unauthenticated
```

Keep this logic centralized.

---

# 32. Avoid Infinite Retry Loops

Never implement:

```text
401
→ retry
→ 401
→ retry
→ 401
→ ...
```

At most perform the SDK-supported token/session refresh/retrieval flow and retry according to a bounded strategy.

If authentication remains invalid, sign the application state out.

---

# 33. Request Retry

Do not blindly replay unsafe mutation requests after authentication refresh.

For example:

```text
checkout
payment initiation
order actions
```

may require idempotency rules.

The networking layer must not turn an authentication retry into duplicate business operations.

Respect existing Laravel idempotency semantics.

---

# 34. Simple Standard Rule for Retries

For V1:

```text
GET / safe request
→ may retry once after obtaining current auth token

mutation
→ retry only where existing API idempotency behavior makes it safe
```

Do not invent a generic automatic retry engine for every HTTP request.

---

# 35. Networking Package

Use the networking library already chosen for Flutter.

Do not introduce multiple HTTP clients solely for authentication.

A typical architecture is enough:

```text
Dio or existing HTTP client
+
one auth interceptor
```

Do not overabstract this.

---

# 36. Authentication Interceptor

If using Dio or equivalent, one interceptor is appropriate.

Responsibilities:

```text
obtain current token
attach Authorization
handle auth failure in bounded manner
```

It should not:

* perform navigation directly;
* contain user provisioning logic;
* inspect Laravel roles;
* store passwords;
* implement checkout retries.

Keep it small.

---

# 37. Repository Layer

Feature repositories should not know Clerk exists.

Example:

```text
OrderRepository
→ ApiClient
```

not:

```text
OrderRepository
→ Clerk SDK
→ token
→ HTTP
```

Only the API/network layer needs token access.

---

# 38. Laravel Needs No Mobile-Specific Middleware

Reuse the existing:

```text
Clerk authentication middleware
```

already implemented in Group D.

Do not create:

```text
AuthenticateFlutterUser
```

unless mobile genuinely uses a different credential type.

It should not.

---

# 39. Same Backend Authentication Path

Both future clients should reach:

```text
Authorization: Bearer Clerk session token
        ↓
same Laravel verifier
        ↓
same LocalUserResolver
```

Do not maintain one verifier for web and another for Flutter.

---

# 40. CORS Is Not a Flutter Concern

Native Flutter mobile HTTP calls are not subject to browser CORS in the same manner as web clients.

Do not add permissive Laravel CORS rules just to make Flutter work.

CORS configuration belongs to browser clients.

---

# 41. TLS

All production mobile API traffic must use:

```text
HTTPS
```

Do not permit plain HTTP in production.

Local development exceptions can use normal development configuration.

Do not disable TLS certificate validation in production code.

---

# 42. API Base URL

Use environment-specific configuration:

```text
development
staging
production
```

Do not hard-code the production Laravel API URL throughout repositories.

This belongs in the Flutter environment/network configuration phase.

---

# 43. Clerk Publishable Configuration

Flutter may contain only Clerk configuration intended for client use.

Never embed:

```text
CLERK_SECRET_KEY
```

in Flutter.

Secret/backend keys belong only to trusted server environments.

---

# 44. Do Not Hide Security in Obfuscation

Flutter compilation/obfuscation does not make server secrets safe.

Never put Clerk secret keys or Laravel secrets into app source code.

Anything shipped with the mobile app must be considered obtainable by the user.

---

# 45. Local User Provisioning

The first authenticated Flutter API call may trigger existing Phase 4.2 JIT provisioning.

Do not implement provisioning in Flutter.

Conceptually:

```text
Flutter authenticated Clerk user
    ↓
GET /me
    ↓
Laravel
    ↓
user missing?
    ↓
existing LocalUserProvisioner
```

---

# 46. Email Is Not Mapping Key

Flutter must not send:

```text
email
```

to identify the Laravel User.

Laravel continues mapping:

```text
verified token.sub
→ clerk_user_id
```

Do not duplicate identity rules on mobile.

---

# 47. Profile Data

Flutter obtains application profile information through:

```http
GET /api/v1/me
```

Do not use the Clerk User object as the complete application profile.

Clerk knows authentication identity.

Laravel knows application profile/RBAC.

---

# 48. Name and Phone

Recall:

```text
name → Laravel profile
phone → optional Laravel profile
```

Do not depend on Clerk for these fields in Flutter.

Profile editing will eventually call:

```http
PATCH /api/v1/me
```

---

# 49. Session State and Laravel Account State

A valid Clerk session does not guarantee Laravel business access.

Example:

```text
Clerk authenticated
+
Laravel account restricted
```

may still result in application denial.

Flutter should display backend error/state rather than treating Clerk login as the only permission decision.

---

# 50. Role Caching

Flutter may cache:

```text
role
profile
```

for UI convenience.

But on fresh app start/authentication, refresh from:

```text
GET /me
```

Do not persist an ADMIN role forever and trust it after server-side changes.

---

# 51. Guest Cart

Do not implement guest-cart merge in this phase.

Future flow remains:

```text
guest cart
+
authenticated local customer
↓
existing CART-005 merge operation
```

Do not implicitly merge carts just because Clerk login succeeds.

---

# 52. Deep Links

Do not build complex auth deep-link handling unless the chosen Clerk sign-up/recovery strategy requires it.

Current authentication choice is:

```text
email + password
email verification
```

If verification uses email code, no additional verification deep-link architecture is necessary.

Keep V1 simple.

---

# 53. Social Login

Do not add Google/Apple/etc. during Phase 4.7 unless already approved.

Mobile authentication should match the existing V1 Clerk configuration.

Do not broaden auth scope.

---

# 54. Biometrics

Do not add Face ID/fingerprint login in this phase.

Device biometrics may later protect locally stored credentials/session access, but Clerk remains authentication authority.

This is not required for the standard V1 flow.

---

# 55. Certificate Pinning

Do not add certificate pinning by default.

HTTPS with normal platform certificate validation is the standard secure approach.

Certificate pinning adds operational complexity and rotation risk and is not justified for this project at present.

---

# 56. Custom Device IDs

Do not invent:

```text
device_id
installation_id
trusted_device_id
```

for authentication unless Clerk or another actual requirement needs them.

Avoid device fingerprinting.

---

# 57. Background Token Refresh

Do not create a background timer that refreshes tokens continuously.

Use the Clerk SDK/session abstraction as intended.

Get a current token when API access requires it.

Keep lifecycle management simple.

---

# 58. App Resume

When the app resumes after being suspended:

* allow Clerk integration to restore/evaluate session;
* fetch a current token when an authenticated API call occurs;
* handle 401 centrally.

Do not build complex manual expiry timers unless required by the SDK.

---

# 59. Offline Mode

Do not interpret locally cached profile data as proof of current authentication.

If offline:

```text
cached content may be displayed
```

where product requirements allow.

But authenticated mutations requiring Laravel cannot be performed until connectivity/authentication is available.

Do not invent offline authentication authority.

---

# 60. Error Mapping

Keep two layers:

```text
Clerk authentication errors
```

for authentication UI, and:

```text
Laravel API errors
```

for application operations.

Do not combine all provider errors into domain errors.

---

# 61. UI-Friendly Auth Errors

The future Flutter UI may map Clerk errors to friendly messages.

Do not expose:

```text
stack traces
SDK class names
JWT validation details
```

to customers.

Keep provider-specific handling inside the authentication feature.

---

# 62. Laravel Error Handling

Laravel continues returning the project-standard error envelope.

Flutter API code should inspect:

```text
HTTP status
code
field
details
```

not parse human-readable messages.

This remains unchanged.

---

# 63. 401 vs 403

Mobile handling should preserve:

```text
401
→ authentication problem

403
→ authenticated but not authorized
```

Do not automatically sign out on every 403.

A forbidden order/admin action is not necessarily a broken Clerk session.

---

# 64. 404 Ownership Masking

Do not reinterpret:

```text
404 RESOURCE_NOT_FOUND
```

on private resources as an authentication failure.

The backend intentionally uses ownership masking.

Do not trigger logout.

---

# 65. Testing Strategy

Do not require real Clerk authentication in ordinary Flutter tests.

Use fake implementations of:

```text
AuthRepository
AuthTokenProvider
```

This keeps tests deterministic.

---

# 66. Unit Tests — Signed Out

Given:

```text
AuthTokenProvider → null
```

protected API client call should behave according to application design without fabricating authentication.

Do not attach an Authorization header.

---

# 67. Unit Tests — Signed In

Given:

```text
AuthTokenProvider → test token
```

verify request contains:

```http
Authorization: Bearer test-token
```

Do not test Clerk cryptography in Flutter.

Laravel owns token verification.

---

# 68. Unit Tests — Public Request

Public API requests must work with no token.

Do not force login for catalog calls.

---

# 69. Unit Tests — 401

Simulate Laravel:

```text
401 AUTHENTICATION_REQUIRED
```

Verify centralized auth handler is invoked.

Do not create infinite retries.

---

# 70. Unit Tests — 403

Simulate:

```text
403 FORBIDDEN
```

Verify app does not automatically sign out.

---

# 71. Unit Tests — Logout

Verify logout:

```text
calls Clerk auth adapter
clears authenticated app state
does not delete public cache unnecessarily
```

Do not test Laravel user deletion.

---

# 72. Unit Tests — Same User

Simulate multiple Clerk sessions belonging to the same Clerk User ID.

Laravel integration tests should already verify they resolve to the same local User.

Do not replicate all backend identity tests in Flutter.

---

# 73. Integration Smoke Test

When an actual Clerk-compatible mobile integration is available in the development environment, perform one manual/staging smoke path:

```text
sign up with email/password
verify email
sign in
GET /me
sign out
```

Do not make this live-provider flow part of normal CI.

---

# 74. No Real Credentials in Tests

Never commit:

```text
real customer email
real password
real Clerk session token
real Clerk secret
```

Use fakes/test environments.

---

# 75. Flutter Package Decision

Before adding a Flutter Clerk package:

* verify current maintenance;
* verify compatibility with the current Flutter/Dart versions;
* verify support for required email/password + verification + session token flow;
* review open issues/security posture;
* avoid abandoned packages.

Because Clerk does not currently list Flutter as an official quickstart, isolate whichever implementation is selected.

Do not allow package APIs to leak beyond the authentication adapter.

---

# 76. Avoid Native Platform Bridge Unless Necessary

Do not immediately build:

```text
Flutter ↔ MethodChannel ↔ Clerk Android SDK
Flutter ↔ MethodChannel ↔ Clerk iOS SDK
```

That creates substantial maintenance burden.

Only use native platform bridging if no adequately maintained Flutter-compatible Clerk implementation exists and mobile implementation cannot proceed otherwise.

Record such a decision explicitly before adding that complexity.

---

# 77. Preferred Simplicity Order

Choose in this order:

```text
1. maintained Clerk-compatible Flutter package
2. simple documented REST/provider integration if Clerk officially supports it securely
3. native Android/iOS bridge only if necessary
```

Do not start from option 3.

---

# 78. No Custom Clerk REST Reimplementation Without Need

Do not manually recreate all Clerk client/session behavior using raw HTTP if a maintained client implementation already handles it.

Authentication libraries exist to avoid mistakes around:

* session lifecycle;
* verification;
* token refresh;
* device state.

Use maintained integration where possible.

---

# 79. Folder Structure

When Flutter app implementation begins, a simple structure is enough:

```text
lib/
├── core/
│   └── network/
│       └── api_client.dart
└── features/
    └── auth/
        ├── data/
        │   └── clerk_auth_repository.dart
        └── domain/
            └── auth_repository.dart
```

Adjust to the project's actual feature structure.

Do not create ten authentication layers.

---

# 80. Avoid Overengineering

Do not create separate classes for:

```text
LoginUseCase
LogoutUseCase
GetTokenUseCase
RefreshTokenUseCase
AuthenticationCoordinator
SessionOrchestrator
TokenLifecycleManager
```

unless the application actually benefits from them.

For this app, a small repository + token provider + API interceptor is sufficient.

---

# 81. Dependency Injection

Use the project's existing dependency injection approach if one exists.

Do not introduce a heavyweight DI framework solely for authentication.

Manual/provider-based injection is acceptable if already consistent with Flutter architecture.

---

# 82. State Management

Use the app's chosen state-management package.

Do not introduce another state-management framework only for authentication.

Authentication state can expose:

```text
loading
signedIn
signedOut
error
```

through the existing application state pattern.

---

# 83. Logging

Mobile logs must never contain:

```text
session token
password
Authorization header
Clerk secret
```

Diagnostic logs may contain:

```text
auth state transition
API status code
request correlation ID
```

without secret data.

---

# 84. Crash Reports

Ensure crash-reporting breadcrumbs do not record Authorization headers.

If network logging is later enabled, redact:

```text
Authorization
Cookie
password
verification code
```

---

# 85. Documentation

Update the relevant documentation with the mobile boundary.

Record:

```text
Flutter authenticates with Clerk.
Flutter obtains Clerk session token.
Flutter sends token as Bearer to Laravel.
Laravel uses existing Clerk authentication middleware.
No mobile-specific Laravel tokens.
```

Do not create excessive documentation.

Use existing consolidated auth architecture/decision docs.

---

# 86. `AGENTS.md`

Update only if necessary to clarify:

```text
Flutter authentication:
Clerk session token → Laravel bearer auth
```

Do not rewrite unrelated frontend roadmap items.

---

# 87. OpenAPI

No new endpoint is expected.

Existing protected endpoints already use bearer authentication.

Do not introduce:

```text
/mobile/login
/mobile/token
/mobile/refresh
```

to OpenAPI.

---

# 88. Schema Changes

Expected:

```text
NONE
```

Do not add:

```text
mobile_token
device_token
refresh_token
last_mobile_login
```

to users.

Push-notification device tokens, if needed later, belong to notification phases, not authentication.

---

# 89. Laravel Changes

Expected:

```text
minimal or none
```

Laravel should already support Clerk bearer tokens from Phase 4.3.

Only make backend changes if a genuine compatibility gap is discovered.

Do not duplicate mobile authentication middleware.

---

# 90. Flutter Changes

If `frontend/app` is not yet actively implemented:

this phase may remain primarily architecture/documentation plus any shared contract tests.

Do not prematurely scaffold the whole Flutter application solely to satisfy the phase.

Respect dependency order.

---

# 91. Existing Flutter Roadmap

AGENTS.md later includes:

```text
Phase 16.1 Flutter project setup
Phase 16.3 environment configuration
Phase 16.4 networking layer
Phase 16.5 authentication storage/session
```

Do not steal all of that implementation into Group D.

Phase 4.7 defines the **contract and secure approach** those phases must follow.

---

# 92. Handoff to Flutter Phases

Record these future requirements:

### Phase 16.3

configure Clerk client-safe values and Laravel base URL.

### Phase 16.4

create API client/interceptor.

### Phase 16.5

implement Clerk session persistence/auth repository.

Do not implement unrelated Flutter application features now.

---

# 93. Security Checklist

Verify the architecture guarantees:

```text
password never reaches Laravel
Clerk secret never enters Flutter
session token sent only over HTTPS
Authorization bearer used
no custom mobile JWT
no custom refresh token
no token in SharedPreferences
Laravel remains authz authority
Clerk sub remains the Laravel identity mapping key
phone remains optional profile field
same Clerk identity → same Laravel User
```

---

# 94. Code Quality

Follow project rules:

* small cohesive classes;
* no unnecessary abstraction;
* minimal comments;
* explicit naming;
* one auth adapter;
* one token provider;
* one network auth interceptor;
* use existing state management/DI;
* no copy-pasted auth logic.

Do not engineer for hypothetical future identity providers.

---

# 95. Expected Tests

At minimum document/implement tests for:

```text
token attached to authenticated request
no token for signed-out state
public API works without authentication
401 handled centrally
403 does not force logout
logout clears authenticated state
no password/token logged
```

Do not reproduce the full backend Clerk verification suite in Flutter.

---

# 96. Commands / Checks

If no Flutter project code changes occur:

run existing backend checks to ensure documentation/config changes did not regress anything.

If Flutter code exists and is changed, run the project's standard Flutter checks, such as:

```bash
flutter analyze
flutter test
```

Use existing project commands where defined.

Do not add CI infrastructure in this phase.

---

# 97. Files Changed Report

At completion report:

## Files changed

Exact paths.

## Backend changes

Expected:

```text
none or minimal
```

## Flutter boundary

Document selected:

```text
AuthRepository
AuthTokenProvider
API auth interceptor
```

## Clerk integration

State the selected Flutter-compatible integration/package if one was actually chosen.

## Tests

List added tests.

## Commands

List commands and results.

## Known risks

Especially any reliance on a community-maintained Flutter Clerk integration.

---

# 98. Definition of Done

Phase 4.7 is complete when:

* mobile authentication architecture is documented;
* Flutter uses Clerk as the only authentication provider;
* email/password remains the signup/sign-in policy;
* email verification remains Clerk-owned;
* Flutter never sends passwords to Laravel;
* Flutter can obtain a current Clerk session token through a thin auth adapter;
* Laravel calls use `Authorization: Bearer <token>`;
* Laravel uses the existing Clerk verifier;
* no mobile-specific Laravel authentication endpoint exists;
* no custom mobile JWT exists;
* no custom refresh-token infrastructure exists;
* tokens are not stored insecurely;
* API authentication is centralized in the network layer;
* 401 and 403 have distinct handling;
* no infinite token-refresh/retry loop exists;
* public API access remains unauthenticated;
* one Clerk identity maps to the same Laravel User across web/mobile;
* Clerk-specific Flutter implementation is isolated behind an interface;
* no unnecessary native platform bridge was introduced;
* no unnecessary schema change was made;
* tests/checks pass;
* future Flutter phases have a clear implementation contract.

---

# 99. Out of Scope

Do not implement:

* complete Flutter application setup;
* final login/signup screens;
* profile screens;
* forgot-password screens;
* order screens;
* cart UI;
* social login;
* phone authentication;
* biometrics;
* certificate pinning;
* custom OAuth server;
* custom JWT service;
* Laravel mobile login endpoint;
* push-notification device registration;
* native Clerk Android/iOS bridges unless separately approved.

---

# 100. STOP Condition

STOP when the mobile authentication boundary is reduced to the standard secure flow:

```text
Flutter
→ Clerk
→ current Clerk session token
→ Authorization Bearer
→ existing Laravel authentication
→ local User
```

with a small isolated Flutter authentication adapter and no parallel auth system.

Do not continue automatically.

The next roadmap phase is:

**Phase 4.8 — SPA / Website Authentication with Clerk**
