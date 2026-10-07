PHASE 15.1 — CUSTOMER REGISTRATION / LOGIN UI
Prompt ID: 31764

ENTRY STATE

Group L — Design System Foundation          CLOSED
Group M — Next.js Website Foundation        CLOSED
Group N — Website Catalog and SEO           CLOSED

Phase 14.11 — PASS
Group N — CLOSED

Group O — Website Customer Commerce         ACTIVE
Phase 15.1 — Registration / Login UI        ACTIVE

Do NOT start Phase 15.2 automatically.


1. OBJECTIVE

Implement production-grade CUSTOMER authentication for the existing Next.js
website using the authentication architecture already completed in Group D:
```
Clerk
    ↓
Next.js Clerk session
    ↓
current Clerk session token
    ↓
Authorization: Bearer <Clerk session token>
    ↓
Laravel
    ↓
cryptographic Clerk verification
    ↓
local User projection / JIT CUSTOMER provisioning
    ↓
Laravel-owned role/account state/authorization
```
This phase owns the WEBSITE implementation of the already-frozen Clerk/Laravel
authentication boundary.

It does NOT redesign authentication.

It must provide:

- customer sign-up UI;
- customer sign-in UI;
- Clerk-owned email verification;
- Clerk-owned password recovery;
- Clerk session restoration;
- logout;
- authenticated/signed-out website navigation state;
- server-side Clerk authentication context;
- a reusable server-only bridge for authenticated Laravel API requests;
- correct token forwarding to Laravel;
- verification that Laravel remains authoritative for local CUSTOMER role,
  account state, profile, permissions, and business authorization.

The implementation must be ready for Phase 15.2 and later protected customer
features without those phases having to reinvent authentication.


2. AUTHORITY

Before modifying code, read:
```
AGENTS.md
frontend/AGENTS.md
frontend/web/ROUTING.md
frontend/web/RESPONSIVE.md
frontend/web/production-caveats.md or its actual repository path

frontend/design-system/DESIGN.md
frontend/design-system/COMPONENTS.md
frontend/design-system/ACCESSIBILITY.md
frontend/design-system/USAGE.md
frontend/design-system/tokens.css

docs/clerk-authentication-architecture.md
docs/api/api-contract.md
docs/api/api-conventions.md
docs/api/api-resources.md
docs/api/openapi.yaml
docs/decisions.md
docs/domain/business-rules.md

phases/group-D-phases.md
phases/group-O-phases.md

Then inspect the actual current:

frontend/web/package.json
frontend/web/app/layout.tsx
frontend/web/proxy.ts
frontend/web/lib/api/client.ts
frontend/web/components/layout/**
frontend/web/theme/**
frontend/web/.gitignore
```
and all existing authentication-related files.

Repository reality wins over assumptions.


3. CURRENT CLERK DOCUMENTATION

Because Clerk and Next.js integration changes over time, verify the CURRENT
official Clerk Next.js documentation before implementation.

Use only official Clerk documentation for Clerk-specific implementation
decisions.

The repository currently uses Next.js 16.3.8.

Expected current architecture:

- @clerk/nextjs
- App Router
- proxy.ts for Next.js 16+
- ClerkProvider inside <body>
- await auth() server-side
- auth().getToken() / equivalent current supported session-token retrieval
- Clerk prebuilt SignIn / SignUp unless repository inspection demonstrates a
  real requirement for a custom auth flow
- resource-level auth checks for protected data

Do not copy an obsolete Clerk Core 2 tutorial.

In particular:

Do NOT introduce deprecated SignedIn/SignedOut patterns if the installed Clerk
version uses the current Show control.

Do NOT use createRouteMatcher merely because an old tutorial uses it if the
current SDK has deprecated that pattern.

Do NOT use middleware/proxy as the sole authorization boundary.


4. EXISTING CLERK APPLICATION

This project already selected the Clerk application:

app_3BXbzUtbWuYa9hmlKKEDxxvHVXC

Do NOT create another Clerk application.

Do NOT choose another application.

Do NOT initialize an unclaimed temporary Clerk application.

Do NOT enable Clerk Organizations.

The website, Flutter application, and backend architecture must use the same
customer identity system.


5. HUMAN-ACTION POLICY — IMPORTANT

Some Clerk steps require the project owner to authenticate, enter secrets,
approve configuration, receive verification email, or change Clerk Dashboard
settings.

The coding agent MUST NOT:

- fabricate success;
- invent keys;
- print secrets;
- ask the user to paste CLERK_SECRET_KEY into chat;
- commit secrets;
- bypass a required human Clerk action;
- create a second Clerk app because the intended app is inaccessible;
- silently weaken authentication to keep working.

Whenever such a step is reached, stop at a clean checkpoint and output:

HUMAN ACTION REQUIRED
```
Why:
<why this cannot safely be completed by the agent>

Action:
<exact command/dashboard action>

Expected result:
<what the user should see>

Reply with:
<minimal confirmation needed to continue>
```
After the user confirms, resume the SAME Phase 15.1.


6. CLERK CLI CHECKPOINT

Before running Clerk initialization, inspect whether Clerk is already correctly
installed/linked.

Do not read or print existing environment files merely to inspect secrets.

If Clerk initialization has NOT already been completed against the selected
application, require the human owner to authenticate first.

Human command:
```
npx -y clerk@latest auth login
```
After successful authentication, initialize/link ONLY the approved application:
```
npx -y clerk@latest init --app app_3BXbzUtbWuYa9hmlKKEDxxvHVXC
```
If the installed Clerk CLI uses an equivalent current syntax, verify it from
official Clerk documentation before changing the command.

Do NOT run create-next-app.

The existing Next.js application is authoritative.


7. CLI GENERATED CODE IS NOT AUTOMATICALLY AUTHORITATIVE

Clerk CLI may modify:

- package.json;
- environment configuration;
- app/layout.tsx;
- proxy.ts;
- auth routes;
- provider setup.

Treat generated changes as integration scaffolding.

Review every change.

Reconcile it with the existing architecture.

Do not blindly accept generated code that destroys existing behavior.


8. CRITICAL PROXY.TS CONSTRAINT

The website ALREADY has important proxy.ts behavior from Group N.

In particular, proxy.ts participates in the production hard-404 preflight for:
```
/products/[slug]
/categories/[slug]
```
Phase 15.1 MUST compose Clerk middleware with the existing proxy behavior.

DO NOT replace the existing proxy implementation with a trivial:

export default clerkMiddleware()

if doing so removes the catalog hard-404 logic.

Preserve:

- canonical product hard 404;
- canonical category hard 404;
- fixture-mode behavior;
- exclusions for /products and /search collections;
- exclusions for /sitemap.xml and /robots.txt;
- existing request-first behavior.

Add Clerk integration around/within the existing proxy architecture rather than
destroying it.


9. CLERK PROXY MATCHER

Verify the current official Clerk matcher requirements for Next.js 16.

The Clerk frontend API path:

/__clerk/(.*)

must be handled as required by the current SDK.

Preserve existing catalog matcher behavior.

Do not accidentally run catalog detail preflight against:

/__clerk/**
/sign-in/**
/sign-up/**
/_next/**
static assets
/sitemap.xml
/robots.txt

The final matcher must be tested.


10. PUBLIC-BY-DEFAULT WEBSITE

Do NOT globally require authentication.

The following remain public:

/
 /products
 /products/[slug]
 /categories/[slug]
 /search
 /sitemap.xml
 /robots.txt
 /sign-in/**
 /sign-up/**

and future anonymous request/enquiry routes according to their owning phases.

Clerk middleware/proxy provides auth context.

It does not turn the public catalog into a private site.


11. RESOURCE-LEVEL SECURITY

Future protected pages/actions must check authentication where protected data
is actually read or mutated.

Laravel independently verifies authentication and authorization.

Frontend route protection is UX, not backend security.

Never rely on:

"is signed in in Next.js"

as sufficient permission to access Laravel customer data.


12. CLOSED ROLE MODEL

The V1 roles remain exactly:

CUSTOMER
STAFF
ADMIN

Do NOT add:

USER
MEMBER
BUYER
MANAGER
DELIVERY_AGENT

or any other role.


13. PUBLIC CUSTOMER SIGN-UP

Public website self-registration creates customer identities only.

Flow:

Visitor
→ Clerk sign-up
→ email/password
→ Clerk email verification
→ active Clerk session
→ authenticated Laravel request
→ verified Clerk token
→ Laravel local-user resolution/JIT provisioning
→ local CUSTOMER

There is no Staff/Admin public registration.


14. NO CLIENT ROLE SELECTION

The sign-up UI MUST NOT contain:

role
account type
customer/staff selector
admin selector
permission selector

Do not send role metadata to Clerk.

Do not put CUSTOMER into unsafeMetadata/publicMetadata as authorization.

Laravel assigns the local CUSTOMER role according to the established backend
JIT provisioning policy.


15. SIGN-UP FIELDS

V1 customer sign-up credentials are:

email
password

Phone is NOT required at registration.

Do not add:

phone
address
company
username
role
date of birth

merely because Clerk can collect them.

Name/profile information belongs to the established profile/application flows,
not credential authority unless repository authority explicitly says otherwise.


16. EMAIL VERIFICATION

Email verification is REQUIRED.

Clerk is the sole verification authority.

Clerk:

- creates verification challenges;
- sends verification messages;
- validates verification;
- owns verified-email state.

Laravel must NOT:

- generate OTPs;
- send verification codes;
- expose verification endpoints;
- trust client-submitted email_verified=true.

Use the Clerk SignUp flow's supported verification UI.


17. UNVERIFIED USERS

An incomplete/unverified Clerk identity must not become a valid protected
Laravel CUSTOMER.

The established backend JIT gate already enforces verified primary email.

Do not weaken it.

If live verification demonstrates otherwise:

STOP.

Report a backend/auth contract regression.


18. PASSWORD RECOVERY

Password recovery is Clerk-owned.

Use the recovery capability provided by the current Clerk SignIn flow.

Do NOT implement:

/api/password/forgot
/api/password/reset
Laravel reset email
custom reset OTP
custom reset token storage
custom password hashing

Do not send passwords or recovery codes to Laravel.


19. SESSION TASKS

Current Clerk versions may place authenticated users into pending session-task
states such as required password reset or MFA setup.

Use Clerk's supported SignIn/SignUp behavior.

Do not treat a pending session as a fully authenticated application session.

Do not bypass Clerk session tasks to reach Laravel protected APIs.


20. NO CLERK ORGANIZATIONS

Do not enable Organizations.

Do not add:

OrganizationSwitcher
OrganizationList
organization role checks
organization membership
org metadata

Application roles remain Laravel-owned.


21. PREBUILT AUTH COMPONENTS FIRST

Prefer current Clerk:

<SignIn />
<SignUp />

for the credential flow.

Reason:

Clerk already owns:

- password handling;
- verification;
- recovery;
- session tasks;
- error behavior;
- bot/security behavior.

Do not rebuild those flows with useSignIn/useSignUp unless a concrete project
requirement cannot be satisfied by the supported prebuilt components.

"Make it look more custom" is NOT sufficient reason to rebuild security-sensitive
auth flows.


22. ROUTES

Implement dedicated public routes using the current Clerk-supported App Router
pattern.

Expected shape:

app/sign-in/[[...sign-in]]/page.tsx
app/sign-up/[[...sign-up]]/page.tsx

Verify exact routing requirements against the installed Clerk version.

Update ROUTING.md.

Do not create:

/login
/register
/auth/login
/auth/register

as competing aliases unless an existing frozen route contract requires them.


23. AUTH ROUTE SEO

Authentication pages are utility/private-intent pages, not organic landing pages.

Set appropriate metadata.

They should not compete with catalog pages for indexing.

Use a truthful noindex policy if consistent with the existing SEO architecture.

Do not add auth routes to sitemap.xml.

Do not add auth JSON-LD.

Do not add them to product/category structured data.


24. REDIRECT BEHAVIOR

Preserve safe Clerk return-to behavior.

If a customer starts sign-in from a public page, successful authentication may
return them to the originating page using Clerk's supported redirect behavior.

Do not blindly force every sign-in to:

/account

because no Phase 15.1 account dashboard exists.

Fallback destination may be `/`.

Do not create open redirects.

Use Clerk-supported redirect semantics.


25. CLERK ENVIRONMENT VARIABLES

Use current Clerk-supported variables.

Expected secret/public distinction:

NEXT_PUBLIC_CLERK_PUBLISHABLE_KEY
    client-safe Clerk publishable configuration

CLERK_SECRET_KEY
    SERVER SECRET

Never expose:

CLERK_SECRET_KEY
CLERK_WEBHOOK_SIGNING_SECRET
backend credentials

through NEXT_PUBLIC_*.


26. PRODUCTION-CAVEATS CORRECTION

The current production caveats say:

"All website variables are server-only."

That was correct for the catalog/SEO configuration before Clerk.

It is no longer literally correct once Clerk is integrated.

Update the document to distinguish:

SERVER-ONLY:
SITE_URL
API_BASE_URL
CATALOG_MEDIA_BASE_URL
CLERK_SECRET_KEY
other secrets

CLIENT-SAFE BY DESIGN:
NEXT_PUBLIC_CLERK_PUBLISHABLE_KEY
Clerk's documented public auth-route configuration where required

Do NOT generalize this into permission to expose arbitrary configuration.


27. ENVIRONMENT FILES

Never commit:

.env
.env.local
.env.production
secret-containing files

Verify .gitignore.

Do not print secret values in:

terminal report
tests
snapshots
logs
completion report
Git diff


28. CLERK PROVIDER

Integrate ClerkProvider according to the CURRENT Next.js SDK requirements.

For the current architecture, it belongs inside <body>.

Preserve the existing:

<html>
<body>
MUI/theme providers
SiteShell
font variables
metadata
accessibility structure

Do not wrap or restructure the whole application unnecessarily.


29. PROVIDER ORDER

Inspect the actual existing provider tree before changing it.

Choose the smallest correct ClerkProvider insertion.

Do not duplicate:

ThemeProvider
CssBaseline
AppRouterCacheProvider
existing providers
SiteShell


30. CLERK APPEARANCE

The auth UI must visually belong to SL Furnitures.

Use Clerk's supported `appearance` customization.

Map Clerk appearance to the existing design system.

Do NOT invent arbitrary:

colors
font sizes
spacing
radii
shadows

Use approved token values.

Inspect the actual token names before mapping them.


31. NO THIRD DESIGN SYSTEM

Do not install a Clerk theme package merely to make the auth form look like:

shadcn
Material clone
generic SaaS dashboard

The website uses MUI + the SL Furnitures design system.

Expected new auth dependency:

@clerk/nextjs

No additional Clerk UI/theme dependency unless inspection proves it is
strictly required.


32. CLERK COMPONENT STYLING

Prefer:

Clerk appearance variables
existing CSS variables/tokens
existing MUI/layout primitives around the Clerk component

Avoid brittle selectors into undocumented Clerk internals.

Do not fork Clerk's internal DOM.


33. AUTH PAGE VISUAL DIRECTION

The pages should remain:

architectural
warm
editorial
calm
crafted
accessible
restrained

Suggested composition:

desktop:
restrained two-region editorial composition where appropriate

mobile:
single-column auth-first composition

But inspect the current design system and available approved project imagery
before choosing composition.

Do not create generic AI-auth UI.


34. AUTH PAGE CONTENT

Keep copy concise.

Examples of content purpose:

Sign in:
"Welcome back"

Sign up:
"Create your account"

Do not add fake claims such as:

"Join 50,000 happy customers"
"Free delivery"
"30-day returns"
"Secure checkout"

unless authoritative project data supports them.


35. AUTH PAGE IMAGERY

If an image is used:

- reuse an approved project-owned furniture/editorial asset;
- use next/image;
- preserve responsive geometry;
- do not use competitor assets;
- do not add a random Unsplash dependency;
- do not preload decorative auth imagery over the actual functional UI without
  performance justification.

An image is optional.

Do not force one merely to fill space.


36. AUTH NAVIGATION

Now that /sign-in and /sign-up are implemented, activate appropriate website
navigation affordances.

Signed out:

Sign in
Create account

Signed in:

a restrained customer/session affordance

Do not add cart yet.

Do not add checkout yet.

Do not add orders yet.

Do not add wishlist.

Do not activate an unimplemented /account route.


37. HEADER ARCHITECTURE

Inspect the current SiteHeader before implementation.

Do not create a second header.

Do not move the whole SiteHeader client-side merely to show auth state.

Prefer Clerk's server-compatible/control composition and the smallest necessary
client boundary supported by the current SDK.


38. MOBILE NAVIGATION

The existing mobile drawer remains the canonical mobile navigation.

Integrate authentication affordances without creating a second drawer or
parallel mobile header.

Preserve:

Escape handling
focus restore
keyboard accessibility
touch targets
existing category behavior


39. SIGNED-IN AFFORDANCE

Do not expose internal:

Clerk user ID
Laravel user ID
role
permissions

in the ordinary header.

The header only needs to communicate session state appropriately.

If using Clerk UserButton, inspect its generated functionality carefully.

Do not enable links to unimplemented account/order routes.

Do not expose Clerk Organization controls.


40. LOGOUT

Logout is Clerk-owned.

Use Clerk's supported sign-out/session lifecycle.

Do NOT call a Laravel logout endpoint.

Do NOT delete the local Laravel User.

Do NOT clear historical application data.

Signing out one browser/device does not imply revoking every other session.


41. AUTHENTICATED LARAVEL BRIDGE

Implement a reusable SERVER-ONLY authentication adapter for future website
customer API calls.

Do not scatter:

await auth()
await getToken()
Authorization header construction

across Phase 15.2–15.10 pages.

Create one appropriately named auth/API integration boundary after inspecting
the existing lib/api architecture.


42. API CLIENT REUSE

All Laravel HTTP requests continue through:

frontend/web/lib/api/client.ts

Do not create:

axios client
authFetch
second ApiClient
/api proxy
BFF
Next route-handler proxy

unless the existing architecture explicitly requires it.

Extend/compose the existing domain-neutral client appropriately.


43. DOMAIN-NEUTRAL TRANSPORT

Do not put Clerk imports directly into the generic transport if doing so makes
the domain-neutral transport depend on request/session state.

Preferred layering:

Clerk server auth adapter
    ↓
obtains request-scoped token
    ↓
passes Authorization header/token to existing API client
    ↓
Laravel

The generic transport should remain independently testable.


44. TOKEN HANDLING

For each authenticated Laravel request:

obtain the current Clerk session token using the supported server-side SDK
mechanism.

Send:

Authorization: Bearer <token>

The token is request-scoped.


45. NEVER LEAK TOKENS

The Clerk session token MUST NOT enter:

React props
rendered HTML
JSON-LD
metadata
browser localStorage
sessionStorage
URL/query string
logs
error messages
analytics
shared cache
test snapshots
Git history


46. NO PARALLEL TOKEN SYSTEM

Do not introduce:

Laravel Sanctum token
NextAuth/Auth.js
custom JWT
refresh token
Laravel session cookie
frontend-issued token
localStorage auth token

Clerk remains the only credential/session authority.


47. NO JWT DECODING FOR AUTHORIZATION

Do not decode the Clerk JWT in Next.js and use its contents as Laravel
authorization truth.

Laravel verifies the token.

Laravel owns local role and application authorization.


48. LARAVEL /ME

Inspect the frozen USER-001 / GET /api/v1/me contract.

Create the website domain helper needed to call it with the Clerk token.

GET /me is authoritative for:

- local application user;
- local CUSTOMER/STAFF/ADMIN role;
- local account state;
- application profile;
- synchronized verification snapshot.

Do not derive those values from Clerk metadata.


49. /ME CACHE POLICY

GET /me is PRIVATE.

Use:

private/no-store semantics

Never ISR/cache it publicly.

Never put it in the public catalog cache.


50. JIT CUSTOMER PROVISIONING

Do not reproduce JIT provisioning in Next.js.

Laravel already owns it.

The first authenticated Laravel request may provision the local CUSTOMER.

Next.js only supplies a valid Clerk bearer token.


51. JIT CONCURRENCY

Do not add frontend "create local user" calls.

Do not POST a duplicate user record.

Do not use email to search/create a local user.

The backend's concurrency-safe Clerk-sub mapping is authoritative.


52. EMAIL IS NOT THE IDENTITY LINK

Never implement:

find local user by email
then attach Clerk account

The immutable server-controlled Clerk subject mapping is authoritative.

Email is a synchronized attribute, not the identity key.


53. ROLE AUTHORITY

Clerk metadata must NOT be used as application RBAC.

Do not put:

CUSTOMER
STAFF
ADMIN

into Clerk metadata for authorization.

Laravel local RBAC remains authoritative.


54. STAFF / ADMIN

Phase 15.1 is CUSTOMER WEBSITE authentication.

Do not build Staff/Admin registration UI.

Do not build admin dashboard login here.

A valid Clerk session belonging to a local STAFF/ADMIN does not magically turn
the customer website into an admin application.

If role-specific customer-site behavior becomes necessary later, obtain it
from Laravel `/me` and follow the owning phase.


55. 401 / 403 / 404

Preserve distinctions.

401:
not authenticated / expired / invalid session

403:
authenticated but not authorized

404:
resource unavailable or intentionally masked according to backend contract

Do not turn every auth failure into "sign in again."

Do not map 403 to 401.

Do not map auth failures to product/category 404.


56. BOUNDED TOKEN RETRY

Inspect the frozen auth convention.

For safe authenticated reads, a single supported token refresh/retrieval retry
may be permitted where the existing architecture specifies it.

Do NOT implement:

while (401) refresh forever

Do NOT blindly replay unsafe mutations.

Phase 15.1 should establish the reusable policy, not duplicate it per feature.


57. PUBLIC CATALOG REQUESTS

Do NOT attach Clerk bearer tokens to public catalog requests merely because a
visitor happens to be signed in.

Public catalog endpoints remain public and cache-safe by contract.

This is important.

Authenticated session presence must not accidentally make:

/
 /products
 /categories
 /search
 /sitemap.xml

private/customer-specific.


58. CACHE SEPARATION

Never share cached responses between:

public catalog
and
private /me/customer APIs

Authenticated requests must not enter public caches.


59. CORS

Do not change CORS preemptively.

Server-side Next.js → Laravel calls do not require browser CORS.

If Phase 15.1 introduces no browser-direct Laravel request, no CORS change
should be necessary.

If inspection proves browser-direct access is required:

STOP and verify the existing approved origin policy before modifying backend
CORS.


60. ROUTE PROTECTION BOUNDARY

Phase 15.1 itself does not need to invent future protected account routes.

Establish reusable helpers so later pages can do resource-level:

await auth()

and redirect unauthenticated users appropriately.

Do not prematurely protect:

/products
/search
/category pages
homepage


61. RETURN-TO BEHAVIOR

Test:

/products → Sign in → successful sign-in → appropriate return

and direct:

/sign-in → successful sign-in → fallback destination

Do not lose legitimate return URLs.

Do not allow external arbitrary redirect targets.


62. SIGN-UP → SIGN-IN TRANSFER

Clerk may allow users to transfer between sign-in and sign-up flows.

Ensure:

"Create account"
and
"Already have an account? Sign in"

resolve to the canonical project routes.

No duplicate auth route scheme.


63. ALREADY-SIGNED-IN BEHAVIOR

Test direct visits to:

/sign-in
/sign-up

while already authenticated.

Use Clerk's supported behavior.

Do not create redirect loops.


64. EMAIL ENUMERATION

Do not add custom UI logic that reveals whether an email belongs to an account.

Let Clerk's configured authentication flow handle enumeration protection.

Do not add:

"Email not registered"

based on a custom Laravel lookup.


65. ERRORS

Credential/verification/recovery errors belong to Clerk's auth UI.

Laravel integration failures are different.

Do not expose:

JWT
Clerk IDs
Laravel stack traces
request headers
API response bodies
secrets

in user-visible errors.


66. LARAVEL UNAVAILABLE AFTER CLERK SIGN-IN

A valid Clerk session and an unavailable Laravel backend are distinct states.

Do not sign the user out merely because Laravel returns 5xx/network failure.

Do not pretend local application provisioning succeeded.

Report a recoverable application-service failure when an owning protected page
needs Laravel state.


67. LOCAL ACCOUNT SUSPENSION

A valid Clerk identity does not override Laravel account business state.

If `/me` indicates an application account restriction according to the frozen
contract:

honor the Laravel state.

Do not infer active access merely from Clerk `isSignedIn`.


68. HUMAN CLERK DASHBOARD CONFIGURATION

Before live sign-up verification, inspect whether the following selected Clerk
application settings can be verified programmatically.

Required intended configuration:

- sign-up with email enabled;
- sign-in with email enabled;
- password authentication enabled;
- email verification required;
- phone NOT required;
- public customer registration allowed;
- Organizations NOT used for this application;
- no Staff/Admin self-registration semantics.

If any required setting must be changed in Clerk Dashboard:

STOP.

Output HUMAN ACTION REQUIRED with the exact Dashboard path/current Clerk label
and desired setting.

Do not guess that it is configured.


69. HUMAN EMAIL VERIFICATION

A genuine end-to-end sign-up requires access to the verification email/code.

When reached:

STOP.

Ask the human owner to complete the verification challenge in the browser.

Do not bypass verification.

Do not use a fake "verified" client state.


70. TEST USER POLICY

Do not create test Clerk users in the owner's existing Clerk application
without explicit approval.

If automated/live verification requires creating a test user:

output HUMAN ACTION REQUIRED and request approval first.

If approved:

- clearly identify it as a test account;
- do not use real personal credentials;
- report its creation;
- do not print its password into logs/completion reports.


71. LIVE LARAVEL AUTH SMOKE TEST

A complete Phase 15.1 must verify the Clerk → Laravel boundary if the required
human/session setup is available.

Test:

active verified Clerk customer
→ current session token
→ GET /api/v1/me
→ Laravel accepts token
→ local user resolves/JIT provisions
→ local role is CUSTOMER for new public signup
→ no password/token stored by Laravel
→ second GET /me resolves same local user

Do not create a special production route merely to expose tokens for testing.


72. IF LIVE /ME TEST REQUIRES HUMAN ACTION

Use the HUMAN ACTION REQUIRED protocol.

Do not weaken the PASS gate silently.

If environment limitations make a real Clerk/Laravel smoke test impossible,
report:

LIVE CLERK → LARAVEL SMOKE:
NOT AVAILABLE — <exact reason>

Then distinguish automated contract PASS from live-integration verification.


73. TEST SIGN-IN

Verify:

correct email/password → success
incorrect credentials → safe Clerk error
signed-in state survives ordinary navigation
public catalog remains usable
no token appears in HTML/URL/logs


74. TEST SIGN-UP

Verify:

email/password only
phone absent
role absent
verification required
unverified identity cannot access protected Laravel state
verified identity succeeds
new local role = CUSTOMER


75. TEST PASSWORD RECOVERY

Verify Clerk recovery is reachable from sign-in.

Confirm no Laravel password endpoint is called.

Do not need to complete an actual password reset against a real account if that
would be destructive; verify the configured Clerk flow safely.


76. TEST LOGOUT

Verify:

signed in
→ Clerk sign out
→ website becomes signed out
→ protected auth context unavailable

Laravel local User/history remains intact.

Do not expect other device sessions to be revoked.


77. TEST PUBLIC ACCESS

Signed out users must still access:

/
 /products
 /products/[slug]
 /categories/[slug]
 /search

No login wall.


78. TEST PROXY REGRESSION

This is mandatory.

Verify API mode:

existing product slug:
correct response

missing product slug:
HTTP 404

existing category slug:
correct response

missing category slug:
HTTP 404

/robots.txt:
works

/sitemap.xml:
works

/sign-in:
works

/sign-up:
works

/__clerk/...:
not broken by catalog preflight

Do not declare Phase 15.1 PASS if Clerk integration breaks Group N hard-404
behavior.


79. TEST SEO REGRESSION

Authentication must not alter public SEO.

Verify:

canonicals
metadata
JSON-LD
sitemap
robots
internal links

Public catalog HTML must not accidentally contain customer identity/session
data.


80. TEST HEADER

Signed out:
expected auth actions

Signed in:
expected session/customer affordance

No layout shift severe enough to destabilize the header.

No dead /account/cart/orders links.


81. TEST MOBILE DRAWER

Signed-out and signed-in states.

Verify:

keyboard
Escape
focus restoration
touch targets
no overflow
auth actions reachable


82. TEST RESPONSIVE

At minimum:

390
959
960
961
1440

and 200% zoom.

Auth pages must reflow.

No horizontal overflow.

No auth card wider than viewport.

No hidden verification/recovery controls.


83. ACCESSIBILITY

Verify:

one main landmark from SiteShell
logical heading structure
labels
error association
focus visibility
keyboard-only completion
password-manager compatibility
touch targets
200% zoom
reduced motion

Do not replace semantic inputs with decorative pseudo-controls.


84. PASSWORD MANAGERS

Do not disable:

autocomplete
paste
password managers

without a Clerk/security requirement.

Do not implement hostile password UX.


85. NO CUSTOM PASSWORD RULES

Do not duplicate Clerk password validation in Laravel or arbitrary frontend
regex.

Use Clerk's configured password policy.

If the project owner must choose/change the password policy:

HUMAN ACTION REQUIRED.


86. SECURITY HEADERS

Do not loosen CSP/security headers globally just to make Clerk work.

If Clerk requires additional origins under an existing CSP:

inspect the actual policy and use the narrow official Clerk origins.

Report the exact change.

Do not add `*`.


87. DEPENDENCIES

Expected new production dependency:

@clerk/nextjs

Do not add:

next-auth
auth.js
axios
jsonwebtoken
jose
custom auth libraries
form auth libraries
another Clerk UI theme package

unless a proven requirement exists.


88. EXISTING API CLIENT

Regression-test the Phase 13.5 API client.

Public unauthenticated requests must remain unchanged.

Authenticated helper composition must not change:

success envelopes
error envelopes
Retry-After
request IDs
FormData handling
timeouts
cancellation
API version prefix


89. TEST ARCHITECTURE

Add a focused Phase 15.1 test suite, for example:

npm run test:auth

following existing test conventions.

Do not introduce a new test framework merely for this phase.


90. AUTH CONTRACT TESTS

At minimum test:

- ClerkProvider integration exists in the correct provider tree;
- canonical sign-in route exists;
- canonical sign-up route exists;
- proxy composes Clerk with existing catalog behavior;
- /__clerk matcher is preserved as required;
- public catalog is not protected;
- server auth adapter uses current Clerk auth/getToken;
- bearer token is passed request-scoped to the existing API client;
- token is not persisted;
- public catalog calls do not automatically receive bearer tokens;
- /me is private/no-store;
- role is not sourced from Clerk metadata;
- no role field in registration;
- phone not required;
- no Laravel credential endpoint is called;
- no second auth provider exists;
- no localStorage/sessionStorage bearer-token persistence;
- no secret is referenced from client code.


91. DO NOT TEST SECRETS

Tests must not require real:

CLERK_SECRET_KEY
production Clerk user
production API key

for ordinary CI contract tests.

Mock at the auth adapter boundary where appropriate.

Live integration verification is separate.


92. EXISTING REGRESSION SUITE

Run all relevant existing frontend tests including:

npm run test:performance
npm run test:links
npm run test:crawl
npm run test:seo
npm run test:structured-data
npm run test:filters
npm run test:search
npm run test:product-detail
npm run test:products
npm run test:category
npm run test:homepage
npm run test:api
npm run test:theme
npm run test:layout
npm run test:responsive
npm run test:states

plus:

npm run test:auth


93. STATIC VALIDATION

Must pass:

npm run typecheck
npm run lint
npm run build
git diff --check


94. CLERK DIAGNOSTICS

After integration, run the current Clerk diagnostic command if supported by the
installed CLI, expected:

npx -y clerk@latest doctor

Do not paste secrets from diagnostic output into the report.

Report only actionable findings/status.


95. PRODUCTION BUILD WITHOUT SECRET LEAK

Inspect production output/config sufficiently to verify:

CLERK_SECRET_KEY is not client-bundled.

Do not grep/print the secret itself.

Use structural checks, variable-name/code-boundary checks, and framework
behavior.


96. DOCUMENTATION — ROUTING

Update frontend/web/ROUTING.md.

Record:

/sign-in/[[...sign-in]]
/sign-up/[[...sign-up]]

as implemented auth routes.

Keep:

cart
checkout
payment
orders

deferred until their owning phases.


97. DOCUMENTATION — FRONTEND AGENTS

Update frontend/AGENTS.md only with durable authentication rules.

Expected durable rules include:

- Clerk sole website identity/session provider;
- Laravel sole local role/authorization authority;
- server-side token forwarding;
- no token persistence;
- public catalog stays public;
- no Clerk metadata RBAC;
- no parallel auth system.


98. DOCUMENTATION — PRODUCTION CAVEATS

Update production-caveats.md.

Remove/replace the statement that customer auth is wholly "not implemented."

Document:

REQUIRED:
Clerk production application keys/config

REQUIRED:
production Clerk allowed/origin/domain configuration where applicable

REQUIRED:
email/password + verification configuration

REQUIRED:
CLERK_SECRET_KEY server-only

CLIENT-SAFE:
NEXT_PUBLIC_CLERK_PUBLISHABLE_KEY

ASSESS:
production redirect URLs/domain behavior

Do not claim production Clerk configuration is complete unless it was actually
verified.


99. ENVIRONMENT DOCUMENTATION

If the repository has:

.env.example

or an equivalent non-secret template, update it with variable NAMES and safe
placeholder values only.

Never place real keys there.


100. BACKEND

Expected backend code changes:

NONE

Group D already implemented Clerk/Laravel verification and local authorization.

If Phase 15.1 discovers a real backend defect:

STOP.

Report it separately rather than casually changing Group D.


101. CLERK WEBHOOKS

Do not build new Clerk webhook infrastructure in Phase 15.1.

Existing Group D reconciliation architecture remains authoritative.

If webhook production configuration requires a manual Dashboard endpoint or
signing secret and it is necessary for the already-implemented backend:

report it as HUMAN ACTION REQUIRED / production configuration.

Do not create a duplicate Next.js webhook endpoint.


102. FLUTTER

Changed:

NO

Flutter authentication belongs to Groups P/Q.


103. ADMIN

Changed:

NO

Admin authentication UI is separate.


104. CART

Changed:

NO

Do not start Phase 15.2.


105. CHECKOUT / PAYMENT

Changed:

NO


106. REQUEST-FIRST ACCURACY

Phase 15.1 introduces customer identity, not a purchase capability.

Do not suddenly add:

Add to cart
Checkout
Pay now
Orders

to public pages.

Those remain owned by later phases and the current release policy.


107. HUMAN VERIFICATION REPORT

Every manual action requested from the owner must be recorded as:

HUMAN ACTION

Action requested:
<...>

Completed:
YES / NO

Evidence:
<non-secret description>

Never record secret values.


108. COMPLETION REPORT

Return exactly this structure:

PHASE 15.1 — CUSTOMER REGISTRATION / LOGIN UI

Status:
PASS / BLOCKED


CLERK APPLICATION

Application ID:
app_3BXbzUtbWuYa9hmlKKEDxxvHVXC

Existing application reused:
YES / FAIL

New Clerk application created:
NO / FAIL

Organizations enabled:
NO / FAIL


HUMAN ACTIONS

Clerk CLI login required:
YES / NO

Clerk init/link required:
YES / NO

Dashboard changes required:
<list/NONE>

Email verification human step:
YES / NO

Test-user approval required:
YES / NO

Outstanding human actions:
<list/NONE>


SDK

Next.js:
<version>

@clerk/nextjs:
<version>

Clerk CLI:
<version>

proxy.ts convention:
PASS / FAIL

Clerk doctor:
PASS / FAIL / NOT AVAILABLE


ROUTES

/sign-in/[[...sign-in]]:
PASS / FAIL

/sign-up/[[...sign-up]]:
PASS / FAIL

Duplicate /login:
NONE / FAIL

Duplicate /register:
NONE / FAIL

Auth pages noindex:
PASS / FAIL

Sitemap exclusion:
PASS / FAIL


PROVIDER

ClerkProvider:
PASS / FAIL

Inside body:
YES / FAIL

Existing MUI provider preserved:
YES / FAIL

Existing SiteShell preserved:
YES / FAIL

Duplicate provider:
NONE / FAIL


PROXY

Existing catalog preflight preserved:
YES / FAIL

Clerk middleware composed:
YES / FAIL

/__clerk handling:
PASS / FAIL

Public catalog remains public:
YES / FAIL

Product hard 404:
PASS / FAIL

Category hard 404:
PASS / FAIL

Sitemap:
PASS / FAIL

Robots:
PASS / FAIL


REGISTRATION

Email:
YES

Password:
YES

Phone required:
NO / FAIL

Role selector:
NONE / FAIL

Staff registration:
NONE / FAIL

Admin registration:
NONE / FAIL

Email verification:
PASS / FAIL

Verification authority:
CLERK / FAIL


SIGN-IN

Email/password:
PASS / FAIL

Recovery:
PASS / FAIL

Session restoration:
PASS / FAIL

Already-signed-in behavior:
PASS / FAIL

Return-to behavior:
PASS / FAIL


LOGOUT

Clerk-owned:
YES / FAIL

Laravel logout endpoint called:
NO / FAIL

Local user deleted:
NO / FAIL

Other sessions incorrectly revoked:
NO / FAIL


LARAVEL AUTH BRIDGE

Server-only adapter:
<path>

Existing API client reused:
YES / FAIL

Current Clerk token:
PASS / FAIL

Authorization Bearer:
PASS / FAIL

Token persisted:
NO / FAIL

Token rendered:
NO / FAIL

Parallel auth system:
NONE / FAIL


/ME

Domain helper:
<path>

Private/no-store:
YES / FAIL

Laravel role authoritative:
YES / FAIL

Clerk metadata role authority:
NO / FAIL

JIT implemented in Next.js:
NO / FAIL


LIVE CLERK → LARAVEL

Status:
PASS / NOT AVAILABLE / FAIL

Verified Clerk customer:
PASS / NOT AVAILABLE

GET /api/v1/me:
PASS / NOT AVAILABLE

New local role:
CUSTOMER / NOT AVAILABLE / FAIL

Repeat /me same local user:
PASS / NOT AVAILABLE

Unverified customer rejected:
PASS / NOT AVAILABLE

Secrets exposed:
NO / FAIL


HEADER

Signed-out Sign in:
PASS / FAIL

Signed-out Create account:
PASS / FAIL

Signed-in affordance:
PASS / FAIL

Dead account link:
NONE / FAIL

Cart added:
NO / FAIL

Orders added:
NO / FAIL


DESIGN SYSTEM

Existing tokens:
PASS / FAIL

Existing components:
PASS / FAIL

New token authority:
NONE / FAIL

Arbitrary auth colors:
NONE / FAIL

Arbitrary spacing:
NONE / FAIL

Third design system:
NONE / FAIL


ACCESSIBILITY

Keyboard:
PASS / FAIL

Focus:
PASS / FAIL

Labels:
PASS / FAIL

Errors:
PASS / FAIL

Password manager:
PASS / FAIL

390px:
PASS / FAIL

959:
PASS / FAIL

960:
PASS / FAIL

961:
PASS / FAIL

1440:
PASS / FAIL

200% zoom:
PASS / FAIL


SECURITY

CLERK_SECRET_KEY client exposure:
NONE / FAIL

Bearer token HTML:
NONE / FAIL

Bearer token storage:
NONE / FAIL

Bearer token logs:
NONE / FAIL

Email identity linking:
NONE / FAIL

Client role assignment:
NONE / FAIL

Open redirect:
NONE / FAIL

Public catalog auth contamination:
NONE / FAIL


ENVIRONMENT

NEXT_PUBLIC_CLERK_PUBLISHABLE_KEY:
DOCUMENTED / FAIL

CLERK_SECRET_KEY:
DOCUMENTED SERVER-ONLY / FAIL

Real secrets committed:
NONE / FAIL

.env ignored:
PASS / FAIL

Production caveats corrected:
YES / FAIL


TESTS

test:auth:
PASS / FAIL

test:performance:
PASS / FAIL

test:links:
PASS / FAIL

test:crawl:
PASS / FAIL

test:seo:
PASS / FAIL

test:structured-data:
PASS / FAIL

test:filters:
PASS / FAIL

test:search:
PASS / FAIL

test:product-detail:
PASS / FAIL

test:products:
PASS / FAIL

test:category:
PASS / FAIL

test:homepage:
PASS / FAIL

test:api:
PASS / FAIL

test:theme:
PASS / FAIL

test:layout:
PASS / FAIL

test:responsive:
PASS / FAIL

test:states:
PASS / FAIL

TypeScript:
PASS / FAIL

ESLint:
PASS / FAIL

Production build:
PASS / FAIL

git diff --check:
PASS / FAIL


BOUNDARIES

Backend changed:
NO

Flutter changed:
NO

Admin changed:
NO

Cart implemented:
NO

Checkout implemented:
NO

Payment implemented:
NO

Orders implemented:
NO

New auth provider:
NONE

New HTTP client:
NONE

New BFF/proxy API:
NONE

New design system:
NONE


DOCUMENTATION

ROUTING.md:
UPDATED / FAIL

frontend/AGENTS.md:
UPDATED / UNCHANGED

production-caveats.md:
UPDATED / FAIL

Group O phase record:
UPDATED / FAIL

ADR:
NONE / <id>


GIT

git-workflow-and-versioning read:
YES / NO

Operations:
<exact>

Commit:
<hash/message>

Push:
<result/NONE>


RESULT

Phase 15.1:
PASS / BLOCKED

Group O:
ACTIVE

Next phase:
Phase 15.2 — Cart UI / BLOCKED


109. GIT

Before ANY Git command:

locate/read/follow root project skill:

git-workflow-and-versioning

Preserve unrelated owner changes.

Never commit:

.env
.env.local
Clerk secrets
session tokens

Commit Phase 15.1 atomically.

Report exact:

branch
files staged
commit hash
commit message
push result


110. STOP CONDITION

Phase 15.1 may be declared PASS only when:

- the approved existing Clerk application is reused;
- no second Clerk application exists;
- current official Clerk Next.js integration has been verified;
- @clerk/nextjs is integrated correctly;
- ClerkProvider is correctly composed with the existing provider tree;
- existing proxy.ts catalog behavior is preserved;
- clerkMiddleware is correctly composed rather than replacing hard-404 logic;
- /__clerk requirements are satisfied;
- public catalog remains public;
- canonical /sign-in route works;
- canonical /sign-up route works;
- registration is email + password only;
- phone is not required;
- no role selector exists;
- email verification is Clerk-owned and required;
- password recovery is Clerk-owned;
- logout is Clerk-owned;
- no Laravel credential endpoint is reintroduced;
- no parallel auth system exists;
- no Clerk Organizations architecture is introduced;
- no Clerk metadata is trusted for application roles;
- the reusable server-side Laravel auth adapter exists;
- current Clerk session tokens are forwarded request-scoped as Bearer tokens;
- the existing API client remains authoritative;
- bearer tokens are not persisted/rendered/logged/cached;
- public catalog requests do not gain bearer headers automatically;
- GET /me is treated as private/no-store;
- Laravel remains authoritative for local role/account state;
- Next.js does not reproduce JIT provisioning;
- email is not used to link local identities;
- signed-out and signed-in header states work;
- no dead /account/cart/orders links are added;
- auth pages follow the frozen SL Furnitures design system;
- accessibility and responsive checks pass;
- existing catalog hard 404 behavior passes;
- SEO/crawl/structured-data behavior remains unchanged;
- test:auth passes;
- all relevant frontend regressions pass;
- typecheck passes;
- lint passes;
- production build passes;
- git diff --check passes;
- production-caveats.md is reconciled with Clerk's public publishable key;
- no backend feature work is introduced;
- no Flutter work is introduced;
- no Admin UI work is introduced;
- no Cart work is introduced;
- no Phase 15.2 work is started;
- all required human Clerk actions are either completed or explicitly block PASS.

If a required Clerk Dashboard/CLI/email-verification action cannot be completed
by the coding agent, STOP and output HUMAN ACTION REQUIRED rather than
pretending the phase passed.

Only after every required gate is satisfied report:

Phase 15.1 — PASS
Phase 15.2 — READY

Do not start Phase 15.2 automatically.
