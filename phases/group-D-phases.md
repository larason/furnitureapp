# Phase 4.11 — Authentication / Authorization Rate Limiting

## Purpose

Implement practical, moderate Laravel API rate limiting that protects the application from abuse without unnecessarily blocking legitimate customers, Staff, or Admin users.

The design principle is:

```text
security against abuse
+
reasonable burst tolerance
+
minimal impact on normal usage
```

Do not treat rate limiting as the primary authentication or authorization mechanism.

The security stack remains:

```text
Clerk
→ credential/session security

Laravel authentication
→ identify local User

Laravel authorization
→ permissions / ownership

Laravel rate limiting
→ abuse control
```

---

# 1. Read Current Project Docs First

Before changing code, read the latest repository versions of:

```text
AGENTS.md
docs/api/api-contract.md
docs/api/api-conventions.md
docs/api/api-resources.md
docs/domain/business-rules.md
docs/decisions.md
docs/api/openapi.yaml
```

These files have been updated.

Treat their current values and decisions as authoritative.

If they already define:

```text
rate limits
rate-limit groups
Retry-After behavior
endpoint-specific exceptions
```

use those values.

Do not overwrite documented decisions with the fallback recommendations in this phase.

---

# 2. Dependencies

Required:

* Phase 4.1–4.8 authentication architecture complete;
* Phase 4.9 Laravel RBAC complete;
* Phase 4.10 authorization policies complete;
* Clerk authenticates users;
* Laravel resolves authenticated local Users;
* CUSTOMER / STAFF / ADMIN are working;
* authorization failures already distinguish 401 / 403 / masked 404;
* existing Laravel API routing/rate-limit foundation exists.

Do not use rate limiting to compensate for incomplete authorization.

---

# 3. Core Authority Split

Rate-limiting responsibility must be separated.

## Clerk owns

Credential/security flow protection for:

```text
sign-up
sign-in
password recovery
email verification
session/security operations
MFA if later enabled
```

Laravel no longer owns these credential flows.

Do not recreate separate Laravel login throttling for endpoints that no longer exist.

---

## Laravel owns

Application API abuse protection for:

```text
protected API traffic
anonymous submissions
checkout
cart mutations
profile mutations
order actions
Staff/Admin operational mutations
other abuse-prone Laravel endpoints
```

Do not duplicate Clerk credential-security logic.

---

# 4. Main Principle — Do Not Over-Throttle

Normal application behavior must not easily trigger rate limits.

A normal customer should be able to:

```text
browse
open product pages
use search
manage cart
open account
refresh orders
checkout
```

without encountering 429 responses.

Likewise Staff should be able to process normal operational workloads without constantly hitting limits.

Rate limits exist to stop:

```text
automation abuse
request floods
brute-force-like behavior against Laravel APIs
scraping at abusive rates
spam submissions
accidental runaway clients
```

not to punish ordinary usage.

---

# 5. Avoid One Global Tiny Limit

Do not apply something like:

```text
60 requests / minute
```

to the entire API indiscriminately.

Modern web/mobile clients can legitimately generate many requests through:

```text
page hydration
parallel data loading
cart refresh
notifications
order polling
multiple tabs
mobile resume
```

Use endpoint categories.

---

# 6. Recommended Limiter Categories

Use a small number of clear rate-limit groups.

Prefer roughly:

```text
public-read
authenticated-read
authenticated-write
anonymous-submit
sensitive-action
operational-write
```

Do not create dozens of unique limiters.

Keep the model understandable.

---

# 7. Use Existing Laravel RateLimiter

Use Laravel's standard:

```text
RateLimiter
Limit
throttle middleware
```

Do not install another throttling package unless the existing Laravel mechanism is genuinely insufficient.

Do not build a custom Redis algorithm.

---

# 8. Preserve Existing `Retry-After`

The project already requires rate-limited responses to use:

```http
429 Too Many Requests
Retry-After: <seconds>
```

Ensure every Laravel limiter follows this contract.

Do not return only:

```json
{
  "retry_after_seconds": 30
}
```

instead of the standard header.

The standard header is authoritative.

---

# 9. Standard Error Envelope

A Laravel rate-limited response must use the project's standard API error envelope.

Expected code:

```text
RATE_LIMITED
```

if that is the current documented canonical code.

Do not expose Laravel's default HTML throttle page.

Do not invent another error shape.

---

# 10. Do Not Reveal Internal Algorithm

429 responses should not expose:

```text
Redis keys
user IDs used in limiter keys
hashing strategy
internal bucket names
server topology
exact anti-abuse heuristics
```

Expose only information the client needs:

```text
HTTP 429
RATE_LIMITED
Retry-After
request_id
```

according to the API contract.

---

# 11. Authenticated Limit Key

For authenticated traffic, prefer:

```text
local Laravel User ID
```

as the primary limiter identity.

Conceptually:

```text
user:{users.id}
```

not:

```text
Clerk email
phone
raw Clerk user ID
bearer token
```

Do not place the raw Clerk session token in rate-limit keys.

---

# 12. Why User-Based Limits Matter

Authenticated users should generally not be throttled primarily by IP.

Several legitimate users may share:

```text
university Wi-Fi
office Wi-Fi
mobile carrier NAT
family router
corporate proxy
```

An IP-only limiter could block many innocent users because one client is noisy.

Therefore:

```text
authenticated
→ user-keyed limits
```

should be the normal approach.

---

# 13. Anonymous Limit Key

For anonymous Laravel requests, use:

```text
trusted client IP
```

where no stronger application identity exists.

Examples:

```text
anonymous enquiries
anonymous furniture requests
public search abuse
```

Do not trust arbitrary:

```http
X-Forwarded-For
```

unless Laravel's trusted-proxy configuration is correct.

---

# 14. Trusted Proxy Configuration

Before relying on:

```text
$request->ip()
```

confirm trusted proxy configuration is appropriate for deployment.

If the app later sits behind:

```text
Cloudflare
load balancer
reverse proxy
```

Laravel must derive the real client IP safely.

Do not trust client-supplied forwarded headers from arbitrary sources.

---

# 15. Guest Cart Identity

Do not automatically rate-limit every guest-cart request solely by IP if a valid guest cart bearer token provides a better stable application key.

Where appropriate, limiter identity may combine:

```text
guest cart credential digest
+
IP fallback
```

without storing/logging the raw guest token.

Keep implementation simple.

Do not redesign guest-cart authentication in this phase.

---

# 16. Public Catalog

Public catalog reads should receive generous limits.

Examples:

```text
GET /products
GET /products/{slug}
GET /categories
GET /categories/{slug}
search
```

These routes are expected to support:

```text
browsing
SSR
SEO
mobile usage
```

Do not aggressively throttle ordinary browsing.

---

# 17. Public Read Fallback

If current docs do not define a value, use a **generous** starting point rather than a strict one.

For example, a reasonable initial development baseline could be approximately:

```text
300 requests / minute / IP
```

for ordinary public API reads.

This is a fallback recommendation only.

If current repository docs define another value, use the documented value.

---

# 18. Search

Search can be more abuse-prone than static product reads.

Use a moderate search limit if needed.

Do not make it so low that normal:

```text
typing
filters
pagination
sorting
```

trigger 429 responses.

Future frontend should also debounce search appropriately, but backend protection must stand independently.

---

# 19. Authenticated Reads

Authenticated read endpoints may include:

```text
GET /me
GET own orders
GET notifications
GET own requests
GET own enquiries
```

Use generous user-based limits.

A normal app may make several parallel requests on startup.

Do not rate-limit `/me` to a tiny number.

---

# 20. Authenticated Read Fallback

If docs are silent, a reasonable fallback starting point is approximately:

```text
180 requests / minute / authenticated user
```

for routine authenticated reads.

This is not a contractual number.

Prefer documented project values when available.

---

# 21. Authenticated Writes

General customer mutations include:

```text
PATCH /me
cart add/update/remove
notification state updates
```

Use more moderate limits than reads.

These operations should tolerate normal clicking/retries but block automated floods.

---

# 22. Authenticated Write Fallback

If no project-specific value exists, a practical initial baseline is roughly:

```text
60 writes / minute / user
```

for ordinary authenticated mutations.

Do not apply this to every sensitive endpoint automatically.

Specific high-impact actions may have their own limiter.

---

# 23. Checkout

Checkout is high impact because it can affect:

```text
inventory
orders
payments later
```

It already relies on:

```text
authentication
validation
transactions
idempotency
```

Rate limiting is an additional abuse layer.

Do not make checkout excessively restrictive.

---

# 24. Checkout Fallback

Where docs provide no explicit threshold, use a moderate user-based limit such as approximately:

```text
10 checkout attempts / minute / user
```

while preserving idempotency.

The exact project-defined limit wins if present.

A legitimate customer retrying after validation failure should not immediately become locked out.

---

# 25. Idempotency Still Required

Rate limiting does not replace:

```text
Idempotency-Key
database uniqueness
transactions
```

for checkout/payment-sensitive operations.

Even below the rate limit, duplicate requests must remain safe.

---

# 26. Anonymous Furniture Requests

Anonymous furniture-request submission can be spammed.

Apply a moderate anonymous limiter.

Do not throttle browsing merely because request submission is abuse-prone.

---

# 27. Anonymous Enquiries

Likewise:

```text
POST /enquiries
```

should have spam protection.

A normal customer should still be able to submit a few legitimate enquiries without friction.

---

# 28. Anonymous Submission Fallback

If current docs have no explicit value, a reasonable starting range is:

```text
5–10 submissions / 10 minutes / IP
```

depending on endpoint.

Prefer the less restrictive end unless actual abuse justifies stronger limits.

Do not use aggressive lockouts lasting hours.

---

# 29. CUSTOMER Order Actions

Examples:

```text
cancel order
```

should use moderate user-based throttling.

The domain rules already determine whether the action is allowed.

Do not rely on rate limiting to enforce:

```text
20-minute cancellation window
order ownership
order status
```

---

# 30. STAFF Operational Actions

Staff may perform legitimate bursts such as:

```text
accept several orders
update preparation/shipping states
adjust inventory
```

Do not impose customer-like tiny limits on operational workflows.

Use user-based operational limits with sufficient burst capacity.

---

# 31. Operational Write Fallback

If docs are silent, start generously, for example approximately:

```text
120 operational writes / minute / Staff/Admin user
```

where operational workflows justify it.

This is a fallback, not a fixed project contract.

Tune based on actual usage.

---

# 32. ADMIN Actions

Admin security-sensitive actions may be less frequent.

Examples:

```text
approve Staff
change Staff state
manage permissions
```

They can have a smaller dedicated limiter if needed.

But authorization and audit remain the primary protections.

Do not rate-limit Admin so aggressively that legitimate recovery/admin operations become unusable.

---

# 33. Sensitive Action Fallback

If no documented value exists:

```text
20–30 actions / minute / authenticated user
```

is generally ample for human administrative actions.

Do not introduce long punitive lockouts.

---

# 34. Clerk Login / Signup

Do not create Laravel rate limiters for:

```text
Clerk sign-in
Clerk sign-up
Clerk password reset
Clerk email verification
```

when those operations go directly through Clerk.

Clerk owns credential abuse protection.

---

# 35. No Duplicate Password Brute-Force Limiter

Laravel does not receive customer passwords.

Therefore do not build:

```text
login:{email}:{ip}
failed_password_counter
credential_lockout table
```

for customer authentication.

That would duplicate Clerk.

---

# 36. Future Frontend Authentication

Future Next.js and Flutter applications should handle Clerk's own authentication throttling/error responses according to Clerk APIs.

Do not proxy those errors through Laravel merely to normalize them.

---

# 37. Authorization Failures and Rate Limiting

Rate limiting should happen at a layer appropriate to the route.

For authenticated private routes:

```text
authenticate
→ derive User
→ user-based limiter
→ authorization
→ domain
```

This allows fair per-user limits.

Do not authorize expensive business operations before basic abuse controls if the limiter can safely run earlier.

---

# 38. Never Use Authorization Result as Limiter Key

Do not create different keys such as:

```text
allowed:user
forbidden:user
```

That adds unnecessary complexity.

Use stable actor/endpoint categories.

---

# 39. Failed Authorization Floods

Repeated authenticated requests producing:

```text
403
404 masked ownership failure
```

can still consume resources.

They should count toward the applicable route/user limiter.

Do not provide unlimited probing simply because authorization fails.

---

# 40. 401 Floods

Requests with:

```text
missing
malformed
invalid
```

authentication can also be abused.

A moderate IP-level fallback limiter may be appropriate around protected API traffic before/around expensive authentication verification.

Do not set it low enough to block legitimate users behind NAT.

---

# 41. Token Verification DoS Protection

Clerk token verification should be efficient and mostly local/JWKS cached.

Do not solve expensive authentication by imposing tiny request limits.

Maintain:

```text
local JWT verification
cached JWKS
```

according to previous phases.

Rate limiting is supplemental.

---

# 42. No Per-Token Limiter

Do not key by:

```text
Authorization header
raw JWT
session token
```

Tokens rotate.

This would:

* fragment counters;
* leak sensitive material;
* make abuse controls ineffective.

Use local user identity after authentication.

---

# 43. Role-Based Limits

Avoid dramatically different limits based solely on:

```text
CUSTOMER
STAFF
ADMIN
```

except where workloads genuinely differ.

Rate limiting should generally reflect endpoint behavior, not privilege hierarchy.

A Staff operational endpoint can have a higher operational-write capacity because its expected workload is different.

---

# 44. Do Not Give ADMIN Unlimited Requests

ADMIN must not automatically bypass all rate limits.

Compromised Admin credentials should not gain unlimited request capacity.

Use sensible generous limits.

---

# 45. No Permanent Blocking

A Laravel rate limiter should normally create temporary throttling.

Do not implement:

```text
automatic account suspension
permanent ban
blacklist forever
```

from ordinary rate-limit violations.

Those require explicit security/abuse policy.

---

# 46. No Escalating Lockout System

Avoid complex:

```text
first violation → 1 minute
second → 30 minutes
third → 24 hours
```

unless actual threat data later proves it necessary.

For V1, fixed-window/sliding Laravel rate limiting is sufficient.

---

# 47. No CAPTCHA in Backend Phase

Do not add CAPTCHA automatically.

If anonymous spam later becomes a real problem, CAPTCHA can be evaluated at the frontend/edge layer.

Do not overcomplicate V1 preemptively.

---

# 48. Use Burst-Friendly Limits

Legitimate applications often generate short bursts.

Prefer:

```text
reasonable minute-scale budgets
```

over tiny second-by-second limits.

Avoid limits that punish:

```text
page load
mobile reconnect
multiple parallel queries
```

---

# 49. Avoid Global Account Lockout

Do not block an authenticated User from the entire application because they exceeded one endpoint's limit.

Prefer endpoint/category-specific limits.

Example:

```text
enquiry submission limited
```

should not prevent:

```text
view products
view own orders
logout
```

---

# 50. Always Permit Logout

Do not make a user unable to sign out because an application API limiter was exceeded.

Clerk logout itself is outside Laravel application throttling.

Security actions needed to terminate a session should remain accessible.

---

# 51. Recovery and Security Actions

Clerk owns recovery/security throttling.

Do not wrap them in restrictive Laravel counters.

---

# 52. Health Endpoint

Do not apply normal customer API throttling to:

```text
health/status
```

in a way that breaks monitoring.

Use infrastructure-level protection if required.

The health endpoint should remain lightweight.

---

# 53. Webhooks

Future webhook endpoints should have:

```text
signature verification
idempotency
```

as primary controls.

Do not use ordinary IP rate limiting that might reject legitimate provider retries.

Provider-specific webhook treatment belongs to the relevant integration phase.

---

# 54. Payment Webhooks

Payment webhooks belong to Group H.

Do not preemptively apply generic customer rate limits to them.

---

# 55. Public Static Assets

Laravel rate limiting does not belong on frontend static assets.

Do not involve application limiter logic in CDN/static delivery.

---

# 56. Edge / CDN Protection

Future infrastructure may add:

```text
Cloudflare rate limiting
WAF
DDoS protection
```

in production.

Do not duplicate an elaborate WAF inside Laravel.

Laravel limiters protect application-level workloads.

Edge protection belongs to production/security phases.

---

# 57. Layered Security

Future production architecture may be:

```text
CDN / WAF
    ↓
Laravel rate limiting
    ↓
authentication
    ↓
authorization
    ↓
domain
```

Each layer has a different purpose.

Do not make Laravel solve volumetric DDoS.

---

# 58. Limiter Naming

Use clear names such as:

```text
public-read
authenticated-read
authenticated-write
anonymous-submit
checkout
operational-write
sensitive-action
```

or the names already documented.

Avoid names like:

```text
limiter1
strict
super-strict
```

---

# 59. Centralize Rate Definitions

Keep limiter definitions in one standard Laravel location.

Do not scatter numeric constants through:

```text
controllers
routes
services
policies
```

Centralization makes later tuning safe.

---

# 60. Configuration

Where practical, keep meaningful rate values configurable per environment.

Do not require code changes to tune production limits.

Use normal Laravel configuration/environment patterns.

Do not expose internal limits to clients beyond standard headers.

---

# 61. Safe Defaults

Production should never accidentally have:

```text
0 requests allowed
```

because an environment variable is missing.

Validate configuration and provide sensible application defaults.

---

# 62. Environment Differences

Local/test environments may use different limits only when needed for deterministic testing.

Do not disable rate limiting completely in production.

Do not make production significantly more restrictive without justification.

---

# 63. Testing Environment

Tests may use:

```text
clear limiter state
controlled test keys
small test-only limits
```

where necessary.

Do not alter production limiter values merely to make tests faster.

---

# 64. Distributed Deployment

Use a cache backend that works correctly across multiple Laravel instances in production.

Do not depend on per-process memory if the application may scale horizontally.

Use existing Laravel cache infrastructure.

Redis may be appropriate if already part of production architecture.

Do not add Redis solely because of this phase unless the project has already selected it.

---

# 65. Database Rate Limiting

Do not create custom database tables recording every API request.

Laravel's standard limiter/cache infrastructure is sufficient.

Avoid unnecessary write amplification.

---

# 66. Limiter Failure Behavior

Do not silently disable all security if rate-limit storage temporarily fails without considering the project's existing cache behavior.

Follow Laravel's normal infrastructure behavior and existing production strategy.

Do not engineer a complex distributed fallback in this phase.

---

# 67. Retry Behavior

Clients should respect:

```http
Retry-After
```

when receiving 429.

Do not encourage hammering the endpoint immediately.

Future frontend should display a reasonable temporary message where relevant.

No frontend implementation in this phase.

---

# 68. Avoid Revealing Exact User Quotas in UI Contract

The frontend generally needs:

```text
temporarily rate limited
retry after N seconds
```

not an internal explanation of the rate algorithm.

Do not make UI logic depend on hard-coded server thresholds.

---

# 69. Rate Limit vs Validation

Invalid requests may count against the limiter.

Do not allow unlimited malformed submissions.

Rate limiting is transport/abuse control.

Validation remains separate.

---

# 70. Rate Limit vs Authentication

A 429 means:

```text
too many requests
```

not:

```text
authentication invalid
```

Do not sign users out because Laravel returned 429.

---

# 71. Rate Limit vs Authorization

A 429 means neither:

```text
FORBIDDEN
```

nor:

```text
RESOURCE_NOT_FOUND
```

Keep these semantics separate.

---

# 72. Error Ordering

For a route where throttling occurs after authentication:

```text
invalid/missing auth
→ normal authentication error
```

unless an outer abuse limiter already stops an extreme flood.

Do not accidentally turn every invalid token into 429 and hide useful normal authentication behavior.

---

# 73. Public Enumeration

Rate limiting can slow resource enumeration, but it does not replace:

```text
404 ownership masking
non-sequential public identifiers where applicable
authorization-aware queries
```

Keep Phase 4.10 protections intact.

---

# 74. Anonymous Spam

Rate limiting helps with anonymous:

```text
enquiries
furniture requests
```

but validation and future anti-spam measures remain separate.

Do not reject legitimate repeat customers too aggressively.

---

# 75. File Upload Endpoints

If anonymous/request attachments exist later, uploads may require tighter throughput controls because they consume more resources.

Do not implement full upload-specific security here unless those endpoints already exist.

Group T includes broader upload/security review.

---

# 76. Expensive Queries

If a particular search/filter operation is materially expensive, it may receive a specialized limit.

Do not preemptively rate-limit every query parameter differently.

Optimize query/index performance first.

---

# 77. Customer Multiple Tabs

Design limits so one customer using:

```text
two browser tabs
mobile app
```

simultaneously does not instantly hit their user budget.

Remember the same Clerk identity maps to the same Laravel User.

Use sufficiently generous user limits.

---

# 78. Multi-Device Sessions

Because multiple sessions are valid:

```text
website
phone
tablet
```

may share the same authenticated user limiter.

That is acceptable if the user limit is generous.

Do not create complicated per-device counters.

---

# 79. STAFF Shared Workstation / NAT

Staff authenticated limits must key by local User, not office IP, wherever possible.

Do not let one busy Staff member throttle the whole office.

---

# 80. Anonymous Shared NAT

Anonymous IP-based limits necessarily affect shared NAT users.

Therefore anonymous limits must be conservative and moderate.

This is another reason not to use very small anonymous budgets.

---

# 81. No Email Limiter Keys

Do not key application API rate limits by:

```text
email
```

Email can change and may expose personal information in cache keys/logging.

Use internal User ID.

---

# 82. No Phone Limiter Keys

Do not use phone as a limiter identity.

Phone is optional profile/contact data.

---

# 83. Hashing Anonymous Keys

If the cache backend/logging would expose raw identifying data unnecessarily, use an appropriate normalized/hashed limiter key.

Do not overengineer cryptographic identities where Laravel's standard behavior already avoids exposure.

---

# 84. Logging Rate Limit Events

Log only useful signals.

Potential fields:

```text
request_id
limiter category
route/action
authenticated local user ID when applicable
hashed/normalized anonymous key if appropriate
```

Do not log:

```text
Authorization token
password
Clerk secret
full request payload
raw guest cart token
```

---

# 85. Avoid Log Flooding

Do not generate massive error logs for every repeated 429 under an attack.

Use existing logging/monitoring conventions.

Rate-limit/aggregate logs where infrastructure supports it later.

---

# 86. Metrics

If existing metrics infrastructure exists, useful counters include:

```text
rate_limit.hit
rate_limit.allowed
limiter category
route
```

Do not add a new observability stack just for this phase.

---

# 87. Tune From Evidence

Initial limits are operational defaults.

Future tuning should use:

```text
real traffic
429 frequency
normal request patterns
abuse patterns
```

not guesses alone.

Document that limits can be adjusted without breaking the V1 response contract.

---

# 88. Raising Limits

Increasing a rate threshold later is normally operational tuning.

It should not require a new API version.

---

# 89. Lowering Limits

Significantly lowering limits can affect clients operationally.

Treat major reductions carefully and document them.

Do not silently change a usable API into an aggressively throttled one.

---

# 90. OpenAPI

Document:

```text
429 RATE_LIMITED
Retry-After
```

where applicable according to existing project conventions.

Do not duplicate exact thresholds in every endpoint schema unless the current contract requires it.

Thresholds are generally operational configuration rather than core response schema.

---

# 91. API Conventions

Ensure `api-conventions.md` clearly states:

```text
429
RATE_LIMITED
Retry-After
```

and the moderate/non-punitive rate-limit philosophy where appropriate.

Use current repository wording/structure.

---

# 92. Decisions Documentation

Record important decisions such as:

```text
Clerk owns credential throttling.
Laravel owns application API throttling.
Authenticated limits are user-based.
Anonymous limits are IP-based where necessary.
Limits are temporary, not account bans.
```

Do not create a separate permanent phase document unless project conventions require it.

---

# 93. `AGENTS.md`

Update only if the latest AGENTS instructions need the implementation decision recorded.

Do not rewrite unrelated phases.

---

# 94. Middleware Placement

Apply named Laravel limiters through route middleware.

Do not manually call rate-limiter checks throughout controller methods.

Keep routing declarative.

---

# 95. Example Route Grouping

Conceptually:

```text
public catalog
→ public-read

anonymous POST request/enquiry
→ anonymous-submit

protected customer reads
→ auth
→ authenticated-read

ordinary mutations
→ auth
→ authenticated-write

checkout
→ auth
→ checkout

staff/admin operational writes
→ auth
→ operational-write
```

Adapt to actual current routes.

---

# 96. Authentication Before User-Keyed Limit

A limiter that uses:

```text
$user->id
```

must run after authentication has established the local User.

Do not attempt to read authenticated User before the Clerk middleware.

---

# 97. Outer Anonymous Abuse Guard

If needed, an additional generous IP-based API guard may sit outside authentication to protect infrastructure from extreme request floods.

If implemented:

* keep it very generous;
* do not make it the primary normal limit;
* do not block legitimate shared NAT users easily.

Do not add it without clear need.

---

# 98. Authorization Still Runs

Passing a rate limit does not imply authorization.

Pipeline remains:

```text
rate allowance
≠
permission
```

Policies still execute.

---

# 99. CUSTOMER Limits

Do not rate CUSTOMER differently merely because their role is lower privilege.

Use endpoint behavior.

Examples:

```text
customer reads
customer writes
checkout
```

---

# 100. STAFF Limits

Operational Staff endpoints may have larger burst allowances.

Do not make Staff unlimited.

---

# 101. ADMIN Limits

Admin endpoints may use the same operational limiter or a sensitive-action limiter depending on action.

Do not create extremely restrictive Admin rates without evidence.

---

# 102. No Frontend Changes

Do not modify:

```text
frontend/web/
frontend/app/
frontend/design-system/
```

during Phase 4.11.

No 429 UI.

No retry components.

No frontend throttling libraries.

Frontend handling comes later.

---

# 103. Client-Side Debounce Is Not Security

Future search UI may debounce requests.

That improves UX/performance.

It does not replace Laravel throttling.

Do not defer backend security to frontend behavior.

---

# 104. Schema Changes

Expected:

```text
NONE
```

Do not add:

```text
rate_limit_events
failed_attempts
ip_bans
user_lockouts
```

tables for standard V1 API throttling.

---

# 105. New Dependencies

Expected:

```text
NONE
```

Use Laravel's built-in rate limiter.

Do not add a third-party package without necessity.

---

# 106. Tests — Normal Usage Below Limit

Verify normal request patterns remain successful.

Do not only test the 429 path.

For example:

```text
several consecutive reads
→ 200
```

This protects against accidentally tiny limits.

---

# 107. Tests — Limit Exceeded

Exceed a test limiter.

Verify:

```text
HTTP 429
canonical RATE_LIMITED error
Retry-After present
```

Do not assert implementation-internal cache details.

---

# 108. Tests — Retry-After

Mandatory regression test:

```http
Retry-After
```

must be present and valid on 429 responses.

This was already established as an API convention.

---

# 109. Tests — Authenticated Users Separated

Customer A exhausting their user-keyed limit must not exhaust Customer B's limit.

Mandatory fairness test.

---

# 110. Tests — Shared IP Authenticated Users

Two authenticated users with the same test IP must have independent user-based quotas where that limiter is intended to be user-keyed.

This prevents NAT-related collateral blocking.

---

# 111. Tests — Anonymous IP

Anonymous submissions from the same IP should share the appropriate anonymous limiter.

Different IPs should not share the same counter.

---

# 112. Tests — Role Does Not Bypass

ADMIN exceeding a configured endpoint limit should still receive 429.

No universal role bypass.

---

# 113. Tests — Authorization Still Works

Below the rate limit:

```text
unauthorized user
→ 403/404
```

according to Phase 4.10.

Do not let rate-limit middleware break authorization semantics.

---

# 114. Tests — Authentication Still Works

Below the limiter:

```text
missing token
→ 401
invalid token
→ authentication error
```

according to existing auth contract.

---

# 115. Tests — Public Catalog

Normal public catalog browsing must remain comfortably under the configured limit.

Use repeated requests in tests if practical to verify it is not accidentally using a strict write limiter.

---

# 116. Tests — Anonymous Submission

Verify spam-like repeated anonymous submissions eventually receive 429.

Verify the first legitimate requests succeed.

---

# 117. Tests — Checkout

Verify normal checkout attempt succeeds below the limiter.

Excessive repeated attempts eventually receive 429.

Do not test domain idempotency as a substitute for rate limiting.

---

# 118. Tests — Operational Staff

Verify normal burst of Staff operational actions remains accepted below the configured operational limit.

Do not set a test threshold that implies production must be tiny.

---

# 119. Tests — Limiter State Isolation

Clear Laravel rate limiter state between tests.

Avoid flaky test ordering.

---

# 120. Tests — No Token Leakage

Ensure rate-limit cache/log keys do not include raw bearer tokens.

If the implementation makes key construction inspectable, add a focused unit test.

---

# 121. Tests — No Email Key

Likewise ensure authenticated application limiter is keyed by internal user identity, not email.

---

# 122. Test Configuration

Tests may define smaller thresholds to exercise 429 efficiently.

Production/default values remain independently configured.

Do not issue hundreds of requests just to test one limiter if configuration can safely be overridden in test environment.

---

# 123. Cache Driver Tests

Do not over-test Laravel's RateLimiter internals.

Test project configuration and behavior.

Laravel itself owns its limiter algorithm.

---

# 124. Quality Requirements

Maintain:

* cognitive complexity ≤15;
* max 3 returns where practical;
* centralized limiter definitions;
* no duplicated numbers;
* named constants/configuration where meaningful;
* no giant rate-limiter service;
* minimal comments.

---

# 125. Avoid Overengineering

Do not introduce:

```text
adaptive machine-learning throttling
behavior scoring
device fingerprinting
per-user risk scores
automatic blacklists
CAPTCHA orchestration
distributed custom token buckets
```

for V1.

Standard Laravel throttling is enough.

---

# 126. Security Review

Before completing the phase verify:

```text
Clerk still owns credential abuse protection
Laravel application APIs have appropriate abuse controls
authenticated limits use User identity
anonymous limits use trusted IP where needed
normal usage does not hit limits easily
Retry-After always works
429 uses standard error contract
no role has unlimited bypass
no permanent bans exist
```

---

# 127. Existing API Rate Limiter

Inspect the existing Group B API limiter implementation.

Phase 2.6 already established API routing/rate-limit foundation and documented `Retry-After`.

Do not replace functioning infrastructure unnecessarily.

Extend/refine it.

---

# 128. Avoid Duplicate Limiter Layers

If an existing general API limiter is already active:

review it before adding endpoint-specific limiters.

Do not accidentally stack:

```text
global 60/min
+
route 60/min
+
group 60/min
```

and effectively make the API much stricter than intended.

This is a critical review item.

---

# 129. Effective Limit Calculation

For every protected route, inspect the complete middleware stack.

Document which limiter actually applies.

Avoid accidental hidden compounding.

---

# 130. Route Inventory

Review all `/api/v1` routes and classify each into one limiter category.

Produce an internal implementation matrix similar to:

| Endpoint category           | Identity key | Limiter             |
| --------------------------- | ------------ | ------------------- |
| Public catalog read         | IP           | public-read         |
| Authenticated customer read | User ID      | authenticated-read  |
| Customer mutation           | User ID      | authenticated-write |
| Checkout                    | User ID      | checkout            |
| Anonymous request/enquiry   | IP           | anonymous-submit    |
| Staff/Admin operation       | User ID      | operational-write   |

Use actual current routes.

---

# 131. Do Not Publish Sensitive Threshold Matrix Unnecessarily

The implementation/docs may record values for maintainers.

Do not expose every internal threshold in API responses.

---

# 132. Monitoring Handoff

Carry to Group T:

```text
review real 429 metrics
adjust limits if legitimate users hit them
review edge/WAF protection
review suspicious abuse patterns
```

Phase 4.11 establishes safe initial application limits.

Production tuning happens from real evidence.

---

# 133. Commands

Run:

```bash
php artisan test
vendor/bin/pint --test
vendor/bin/phpstan analyse
composer audit
```

If route/cache configuration requires it, run relevant Laravel configuration/route inspection commands.

No schema migration should be necessary.

---

# 134. Files Changed Report

At completion report:

## Docs reviewed

List exact latest authoritative files read.

## Limiters

List:

```text
name
endpoint category
identity key
configured threshold
```

## Existing limiter changes

Explain whether the Group B global API limiter was retained, relaxed, replaced, or scoped.

## Retry-After

Confirm 429 behavior.

## Tests

List all added/updated tests.

## Schema

Must normally state:

```text
NONE
```

## Frontend

Must state:

```text
NONE
```

## Dependencies

Expected:

```text
NONE
```

unless justified.

---

# 135. Definition of Done

Phase 4.11 is complete when:

* current `AGENTS.md` and API/decision docs were reviewed first;
* any existing documented thresholds were preserved;
* Clerk remains responsible for credential-flow throttling;
* Laravel rate limits only application API abuse;
* limiter groups are small and understandable;
* authenticated traffic is primarily user-keyed;
* anonymous abuse-prone traffic is appropriately IP-keyed;
* shared-NAT authenticated users do not share normal user quotas;
* public catalog limits are generous;
* authenticated reads are generous;
* writes have moderate limits;
* checkout has reasonable anti-abuse protection;
* anonymous requests/enquiries have spam protection;
* Staff/Admin operational workflows tolerate normal bursts;
* no role receives unlimited bypass;
* no permanent bans or aggressive escalating lockouts were introduced;
* existing global and route limiters do not accidentally compound into overly strict behavior;
* all 429 responses include `Retry-After`;
* 429 responses follow the canonical error envelope;
* 401/403/404 semantics remain intact;
* rate limiting does not replace idempotency, authorization, or validation;
* no frontend changes were made;
* no schema changes were needed;
* no unnecessary dependency was introduced;
* normal-use tests pass;
* rate-limit tests pass;
* full backend tests pass;
* Pint passes;
* PHPStan passes;
* Composer audit passes.

---

# 136. Out of Scope

Do not implement:

* CAPTCHA;
* permanent IP bans;
* user bans;
* account lockouts;
* ML/risk scoring;
* device fingerprinting;
* WAF rules;
* Cloudflare configuration;
* frontend 429 UI;
* frontend search debounce;
* custom JWT login throttling;
* Clerk credential throttling;
* webhook throttling strategy;
* payment-provider throttling;
* full production monitoring.

---

# 137. STOP Condition

STOP when Laravel has **moderate, endpoint-appropriate abuse protection** without creating friction for normal CUSTOMER / STAFF / ADMIN usage.

The desired result is:

```text
normal user
→ does not notice rate limiting

buggy or abusive client
→ temporarily receives 429 + Retry-After

credential attacker
→ handled primarily by Clerk

high-volume network attack
→ handled later by edge/WAF infrastructure

authorized business operation
→ still governed by Laravel policies/domain rules
```

Do not continue automatically.

The next backend roadmap phase is:

**Phase 4.12 — Authentication / Authorization Test Completion and Group D Exit Review**

DO NOT COMMIT, STAGE OR PUSH.

The project owner handles Git operations.
