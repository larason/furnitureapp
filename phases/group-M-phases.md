# Phase 13.5 — Website API Client Foundation

## Objective

Implement the production-ready **Next.js → Laravel API client foundation** for `frontend/web`.

The architecture must establish one consistent path:

```text
Next.js website
      ↓
typed application API boundary
      ↓
HTTP transport
      ↓
Laravel /api/v1
```

The API client must support the public catalog immediately and provide a safe foundation for later authenticated customer functionality without prematurely implementing Clerk authentication.

This phase owns:

```text
base URL handling
request construction
response decoding
Laravel error decoding
timeouts
abort/cancellation
headers
query serialization
JSON handling
204 handling
typed request options
server/client execution compatibility
request identifiers/correlation metadata
safe error representation
cache-policy hooks/options
testability
```

It does NOT own domain endpoint implementations.

---

# 1. Scope

Implement only:

```text
Phase 13.5 — API Client
```

Do NOT begin:

```text
13.6 routing conventions
13.7 layout system
13.8 application error/loading/not-found UI
13.9 responsive foundation

Group N catalog pages
```

Also do not implement Clerk integration unless an existing repository contract explicitly makes a tiny transport hook necessary.

---

# 2. Read Authorities Before Coding

Inspect at minimum:

```text
AGENTS.md
frontend/AGENTS.md

docs/api/
├── api-contract.md
├── api-conventions.md
├── api-examples.md
├── api-resources.md
└── openapi.yaml

docs/clerk-authentication-architecture.md
docs/domain/business-rules.md
docs/decisions.md

frontend/design-system/ACCESSIBILITY.md

frontend/web/
├── package.json
├── tsconfig.json
├── next.config.*
├── app/
├── theme/
└── existing lib/services/API-related code

backend/laravel/routes/api.php

phases/group-M-phases.md
```

Inspect actual backend implementations where needed to confirm:

```text
response envelopes
error envelopes
headers
pagination
validation errors
status codes
rate limiting
```

The frozen V1 API contract is authoritative.

Do not invent frontend-friendly alternatives.

---

# 3. Git Workflow

Before any Git command, locate and read the root:

```text
git-workflow-and-versioning
```

skill.

Follow it exactly.

Preserve unrelated owner changes.

Never commit:

```text
.env
.env.local
API credentials
Clerk secret keys
database credentials
private keys
```

Git operations are authorized only through that skill.

---

# 4. Architecture Principle

The API client must be a **thin infrastructure boundary**.

Desired dependency direction:

```text
future page/component
       ↓
future domain/query layer
       ↓
API client
       ↓
fetch
       ↓
Laravel
```

Do NOT create:

```text
page
→ raw fetch()
→ Laravel
```

throughout the application.

But also do NOT make the transport know about:

```text
products
categories
cart
orders
requests
enquiries
payments
```

unless unavoidable for a generic contract type.

Transport should remain domain-neutral.

---

# 5. Use Native Fetch

Prefer the platform/Next.js native:

```ts
fetch()
```

Do NOT install:

```text
axios
ky
superagent
got
react-query
SWR
```

for the transport layer.

The existing platform is sufficient.

Expected new dependencies:

```text
NONE
```

---

# 6. Do Not Build React Data Fetching Yet

This phase must NOT introduce:

```text
useApi()
useFetch()
useProducts()
useCategories()
React Query
SWR
Context-based API state
```

The transport client is not a React abstraction.

It must be usable from:

```text
Server Components
server-side functions
future Client Components where appropriate
tests
```

without React dependency.

---

# 7. Server-First Compatibility

Public catalog architecture will rely heavily on server rendering.

Therefore the API client must work naturally from server-side Next.js code.

Do not make the API client dependent on:

```text
window
document
localStorage
sessionStorage
React hooks
browser-only globals
```

---

# 8. Client Compatibility

Some future interactive operations will need browser-side requests.

The core transport may therefore be runtime-neutral where safe.

However:

```text
server-only secrets
```

must NEVER become part of a shared client bundle.

Design the boundary so future authenticated/server-only behavior can be layered safely.

---

# 9. Recommended Structure

Inspect existing conventions first.

A reasonable minimal structure may resemble:

```text
frontend/web/
└── lib/
    └── api/
        ├── client.ts
        ├── errors.ts
        ├── types.ts
        └── __tests__/
```

Do not mechanically use this structure if the repository already establishes another appropriate convention.

Do NOT create dozens of tiny files.

Prefer cohesive responsibilities.

---

# 10. One Canonical API Client

There must be one canonical generic request mechanism.

Avoid:

```text
apiClient
httpClient
fetchClient
laravelClient
requestClient
```

all implementing overlapping behavior.

Choose one clear authority.

---

# 11. Base URL

The Laravel API base origin must come from configuration.

Do NOT hard-code:

```text
http://localhost:8000
https://api.example.com
```

inside request functions.

Use an environment-based configuration appropriate to the execution environment.

---

# 12. Environment Variable Naming

Inspect existing repository environment conventions first.

Do not invent multiple aliases such as:

```text
API_URL
BACKEND_URL
LARAVEL_URL
NEXT_PUBLIC_API_URL
API_BASE_URL
```

Choose one canonical variable based on existing conventions.

Document it in an example environment file if the repository uses one.

Never commit actual environment secrets.

---

# 13. Public vs Server-Only Base URL

Determine whether the same Laravel origin is intentionally reachable from both browser and server.

Do not automatically expose a server-only internal hostname through:

```text
NEXT_PUBLIC_*
```

If browser-side direct API calls are not yet required, prefer the least-exposed configuration compatible with current architecture.

Do not invent a proxy/BFF architecture in this phase.

Record the decision.

---

# 14. API Version

The frozen API namespace is:

```text
/api/v1
```

The client must have one deterministic convention for applying this prefix.

Avoid accidental URLs such as:

```text
/api/v1/api/v1/products
```

or:

```text
/products
```

against the wrong origin.

---

# 15. URL Construction

Use safe URL construction.

Do not concatenate arbitrary strings carelessly:

```ts
baseUrl + path + '?' + query
```

without handling:

```text
slashes
encoding
query parameters
arrays
optional values
```

---

# 16. Endpoint Input

Generic request paths should be constrained to application API paths.

Do not let arbitrary user-controlled absolute URLs turn the API client into an unintended generic fetch proxy.

---

# 17. Query Serialization

Implement deterministic query serialization.

Support only contract-required value shapes.

Handle appropriately:

```text
string
number
boolean
arrays if API contract uses them
undefined/null according to contract
```

Use:

```ts
URLSearchParams
```

where appropriate.

Do not invent nested query conventions absent from the API contract.

---

# 18. Request Methods

Support the HTTP methods actually needed by the API foundation:

```text
GET
POST
PUT/PATCH if contract uses them
DELETE
```

Do not invent semantic methods like:

```text
createProduct()
checkout()
approveRequest()
```

in the generic transport.

---

# 19. JSON Requests

For JSON bodies:

```text
Content-Type: application/json
```

must be applied correctly.

Do not send the header for requests without JSON bodies when unnecessary.

---

# 20. JSON Serialization

Only serialize defined JSON request bodies.

Do not blindly run:

```ts
JSON.stringify(undefined)
```

or transform FormData into JSON.

---

# 21. FormData Support

The API includes attachment-capable workflows later.

The generic transport must not make future `FormData` impossible.

If supporting `FormData` now is straightforward:

```text
allow BodyInit/FormData
and do not manually set multipart Content-Type
```

so the runtime can generate the boundary.

Do NOT implement furniture-request attachment endpoints now.

---

# 22. Default Accept Header

For API requests, use:

```http
Accept: application/json
```

unless a particular future endpoint requires another representation.

---

# 23. Header Merging

Allow callers to provide appropriate additional headers without accidentally deleting required defaults.

Header behavior must be deterministic.

Do not allow caller input to silently override security-sensitive future headers without deliberate policy.

---

# 24. Authentication Boundary

Do NOT implement Clerk authentication in this phase.

Do NOT:

```text
import Clerk hooks
call useAuth()
read browser Clerk state
implement sign-in
implement middleware
```

The generic client should nevertheless allow a future authorized layer to provide an:

```http
Authorization: Bearer <token>
```

header without redesigning the entire transport.

---

# 25. No Stored Bearer Tokens

Never design the client around:

```text
localStorage token
sessionStorage token
hard-coded bearer token
```

Clerk will remain the identity/token authority.

---

# 26. Credentials

Do not automatically set:

```ts
credentials: "include"
```

unless the actual Laravel/Clerk architecture requires cookie-based behavior.

The current architecture uses Clerk bearer identity.

Avoid unnecessary cross-origin credential semantics.

---

# 27. Request ID / Correlation Metadata

Inspect the API conventions for request/correlation identifiers.

If Laravel emits a request identifier header or error metadata:

```text
capture it
```

in the structured API error where useful.

Do not invent a conflicting identifier protocol.

---

# 28. Error Contract Is Authoritative

Inspect the frozen Laravel error contract.

The frontend must decode that contract exactly.

Do not replace backend errors with a frontend invention such as:

```ts
{
  message: string;
}
```

if the API defines richer fields.

---

# 29. Typed API Error

Create a typed application error representation appropriate to the frozen contract.

Conceptually it may contain:

```text
HTTP status
stable backend error code
safe message
validation details
request/correlation ID
retry metadata
```

ONLY if those fields actually exist in the contract.

Do not invent unsupported fields.

---

# 30. Error Class

A dedicated error class is appropriate if it materially improves handling.

Example conceptually:

```ts
class ApiError extends Error {
  ...
}
```

It should preserve:

```text
safe API metadata
HTTP status
stable error identity
```

without exposing raw backend internals.

---

# 31. Error Cause

Where supported and useful, preserve the original transport/parsing cause internally.

Do not expose:

```text
stack traces
internal URLs
framework exception details
```

to future user-facing messages.

---

# 32. Validation Errors

Inspect how Laravel represents validation errors.

Preserve the actual contract.

Do not flatten structured validation data into a single string if future forms will need field-level errors.

---

# 33. 401

Treat:

```text
401 Unauthorized
```

as an API result.

Do NOT automatically:

```text
redirect to login
clear auth
reload page
```

inside the generic transport.

Authentication UX belongs to a higher layer.

---

# 34. 403

Do not translate:

```text
403
```

into:

```text
404
```

on the frontend.

The backend already owns authorization masking semantics.

Trust the backend response contract.

---

# 35. 404

A generic API client should expose the structured 404.

Do not invoke Next.js:

```ts
notFound()
```

from the generic transport.

That is a routing/page concern for later phases.

---

# 36. 422

Preserve structured validation information from:

```text
422
```

responses.

Do not treat validation failures as network failures.

---

# 37. 429

The backend contract requires rate limiting with:

```http
Retry-After
```

Capture the contract-defined retry information.

Do not discard it.

Do not automatically retry mutation requests.

---

# 38. Retry-After

Parse `Retry-After` according to the API convention.

If the repository contract specifies seconds, honor that exact interpretation.

Do not invent milliseconds/absolute dates unless the backend supports them.

---

# 39. 5xx

Server failures should produce a safe typed API error.

Do not expose:

```text
HTML exception pages
stack traces
database errors
Laravel debug output
```

through the application error object.

---

# 40. Non-JSON Error Responses

Infrastructure failures may occasionally return:

```text
HTML
plain text
empty body
proxy error
```

even when JSON was expected.

The API client must fail safely.

Do not crash with an unrelated:

```text
Unexpected token '<'
```

parsing exception as the public application error.

Preserve the HTTP status and classify the malformed/unexpected response safely.

---

# 41. Success Responses

Decode the frozen success envelope exactly.

Do not invent a frontend envelope if the backend already defines one.

If the API has multiple valid response forms:

```text
single resource
collection
pagination
no-content
```

support those generically only as contractually required.

---

# 42. Explicit Response Typing

The request function should support typed successful responses.

Conceptually:

```ts
request<T>(...)
```

is appropriate.

But generics do NOT validate runtime data.

Do not claim:

```text
TypeScript generic = runtime schema validation
```

---

# 43. Runtime Validation

Do NOT add:

```text
Zod
Valibot
Yup
io-ts
```

solely for this phase.

If the project already has an approved runtime validation system, inspect it.

Otherwise preserve the frozen contract through types/tests and introduce runtime schemas only in a separately justified phase.

---

# 44. `unknown` Before Trust

When decoding untrusted response JSON, prefer:

```ts
unknown
```

during boundary parsing rather than immediately casting everything to:

```ts
T
```

Use narrow structural checks for the generic envelope/error fields that the transport must understand.

Avoid a fake runtime validator pretending to validate entire domain resources.

---

# 45. Empty Responses

Handle valid empty response bodies.

Especially:

```text
204 No Content
```

must not produce a JSON parsing failure.

---

# 46. HEAD Responses

If HEAD is used by the contract, it must not require JSON parsing.

Do not add HEAD support if no repository use requires it.

---

# 47. Timeout

Network requests must not hang indefinitely.

Implement a reasonable configurable/default timeout using:

```text
AbortController / AbortSignal
```

compatible with the supported runtime.

Do not add a timeout library.

---

# 48. Timeout Value

Do not choose an arbitrary value without documenting it.

Inspect repository/API conventions first.

If no timeout is specified, choose a conservative infrastructure default and document it as a frontend transport policy, not an API contract.

Keep it configurable.

---

# 49. Caller Cancellation

Allow future callers to provide an:

```ts
AbortSignal
```

where appropriate.

The internal timeout must compose correctly with caller cancellation.

Do not silently ignore the caller's signal.

---

# 50. Abort Classification

Distinguish where useful between:

```text
request timed out
caller intentionally aborted
HTTP error
network transport failure
```

Do not mislabel every abort as:

```text
server unavailable
```

---

# 51. Network Errors

Network failures have no HTTP status.

Represent them distinctly from:

```text
404
422
500
```

Do not invent:

```text
status = 0
```

unless that convention is explicitly documented and justified.

Prefer a typed transport failure classification.

---

# 52. Retry Policy

Do NOT add automatic retries by default.

Especially never automatically retry:

```text
POST
PATCH
PUT
DELETE
```

without idempotency guarantees.

Retries may be designed later at a domain/query layer.

---

# 53. GET Retry

Even GET requests should not receive hidden aggressive retry behavior in this foundational client.

Keep transport behavior predictable.

---

# 54. Next.js Cache Semantics

The API client must allow callers to pass legitimate Next.js fetch caching/revalidation options where server-side requests need them.

Do not hard-code:

```ts
cache: "no-store"
```

for every request.

That would undermine future public catalog caching.

---

# 55. Do Not Hard-Code `force-cache`

Likewise do not make every GET:

```ts
cache: "force-cache"
```

Public catalog freshness policies belong to endpoint/domain consumers.

---

# 56. Cache Policy Ownership

Use this dependency:

```text
generic transport
→ supports cache options

future endpoint/domain client
→ selects appropriate policy

page
→ uses domain client
```

The transport provides capability.

It does not decide catalog business freshness.

---

# 57. Next.js `next` Options

Where supported by the installed Next.js version, allow server callers to provide legitimate:

```text
revalidate
tags
```

options without leaking those Next-specific concepts into backend API contracts.

Keep this as transport configuration.

---

# 58. Client Runtime Compatibility

Do not send Next.js-only fetch options from browser code where they have no meaning.

Design types/implementation carefully so shared transport remains predictable.

---

# 59. Logging

Do NOT add verbose request/response logging containing:

```text
Authorization headers
personal information
request bodies
contact information
attachment data
```

A transport client should not become a data-leak source.

---

# 60. Development Diagnostics

If development diagnostics already exist, safe metadata may include:

```text
HTTP method
safe route template/path
status
request ID
```

Avoid secrets and personal data.

Do not add a logging framework.

---

# 61. Authorization Header Redaction

Any diagnostic/error serialization must never include the bearer token.

Add a regression test if the client includes diagnostic metadata that could accidentally serialize headers.

---

# 62. URL Privacy

Be cautious logging complete URLs because future query parameters may contain:

```text
search terms
email
phone
identifiers
```

Prefer no automatic URL logging in the transport.

---

# 63. Error Privacy

The accessibility baseline requires error semantics without leaking backend/private-resource information.

The API client should therefore preserve:

```text
safe backend message
stable error metadata
```

but not expose raw response internals indiscriminately.

Do not make the full raw `Response` object the expected UI error surface.

---

# 64. Backend Authority

Never infer business behavior from HTTP status alone when the frozen API supplies a stable error code.

Example principle:

```text
status
+
stable error code
+
contract metadata
```

may inform higher layers.

Do not embed business rules into the generic client.

---

# 65. Money

Do not convert API money amounts to JavaScript floating-point major currency units.

If generic API types touch money at all:

```text
preserve integer minor units
+
currency
```

exactly as supplied.

Formatting belongs later.

---

# 66. Dates

Do not globally convert all ISO strings into:

```ts
Date
```

inside the transport.

The transport should preserve API representations.

Domain mapping may decide later when a `Date` object is useful.

---

# 67. Enum Handling

Do not silently normalize unknown enum values.

V1 enums are CLOSED.

Domain endpoint types later should represent the contract exactly.

Generic transport should not know individual enums.

---

# 68. `additionalProperties: false`

Do not use the frontend client to append undocumented request fields.

Future endpoint DTOs must follow the frozen contract.

The generic transport should transmit exactly the body it receives.

---

# 69. No Client-Side Business Authority

The API client must never become authoritative for:

```text
price
inventory
roles
ownership
order status
delivery fee
payment status
request lifecycle
```

Laravel remains authoritative.

---

# 70. No Direct Database Access

No:

```text
MySQL
Firebase
Supabase
direct DB SDK
```

belongs in the web client.

All business data flows through Laravel.

---

# 71. CORS

Do not "solve" CORS by:

```text
mode: "no-cors"
```

This is prohibited.

If runtime verification reveals a CORS problem:

```text
report it
```

and fix the actual deployment/API configuration only if that work belongs within approved scope.

`no-cors` is not a solution.

---

# 72. Proxy Architecture

Do not create a Next.js catch-all API proxy such as:

```text
/app/api/[...path]/route.ts
```

without an explicit architectural decision.

This phase is not permission to turn Next.js into an undocumented BFF.

---

# 73. Server Actions

Do not implement the API client exclusively through:

```text
Server Actions
```

Server Actions are an application interaction mechanism, not the generic HTTP transport.

Future flows may use them where appropriate.

---

# 74. API Route Handlers

Do not create Next.js Route Handlers merely to wrap every Laravel endpoint.

Laravel remains the API.

---

# 75. OpenAPI

Use:

```text
docs/api/openapi.yaml
```

as a verification source.

Do NOT add an OpenAPI code generator in this phase unless one is already part of the approved repository architecture.

Expected:

```text
no generated API SDK
```

for Phase 13.5.

---

# 76. Generic API Types

Only create generic types needed by transport.

Examples may include, depending on actual contract:

```text
ApiSuccess<T>
ApiErrorPayload
ApiValidationErrors
PaginationMeta
RequestOptions
TransportFailure
```

Do not create all product/cart/order DTOs now.

---

# 77. Pagination

If the frozen contract defines generic pagination metadata:

implement its generic type exactly.

Do not invent:

```text
pageCount
hasMore
cursor
```

unless present in the contract.

---

# 78. Resource Types

Do NOT manually type every resource in:

```text
api-resources.md
```

during Phase 13.5.

Resource-specific types should be introduced alongside their endpoint/domain consumers.

---

# 79. Public API Surface

Keep the API module's exports intentional.

Avoid exposing internal helpers such as:

```text
parseRawBody
mergeInternalHeaders
normalizeUnknownError
```

unless callers genuinely need them.

---

# 80. Immutability

Do not mutate caller-provided:

```text
Headers
RequestInit
URLSearchParams
AbortSignal
```

objects unexpectedly.

Construct internal request state safely.

---

# 81. Request Body Safety

Do not accept an unconstrained generic:

```ts
body: any
```

Prefer a sensible body type compatible with supported request modes.

Do not over-engineer this into a complex serialization framework.

---

# 82. Method/Body Safety

Avoid sending request bodies with:

```text
GET
HEAD
```

unless the backend contract explicitly requires such behavior.

---

# 83. URL Encoding Tests

Test values containing characters such as:

```text
space
&
+
/
Unicode
```

where query/path encoding applies.

Do not hand-roll encoding.

---

# 84. Base URL Tests

Test normalization for:

```text
base URL with trailing slash
base URL without trailing slash
path with leading slash
```

according to the chosen API configuration convention.

Prevent duplicate/missing separators.

---

# 85. Missing Configuration

If required API base configuration is absent:

fail clearly.

Do not silently default production code to:

```text
localhost
```

A missing configuration should produce a deterministic developer-facing configuration error.

---

# 86. Configuration Validation

Validate at least:

```text
URL is present
URL is syntactically valid
protocol is appropriate
```

Do not add a general environment-schema dependency.

---

# 87. Browser Exposure Test

If using a server-only API URL:

verify it is not imported into a client component/bundle.

If a public API origin is intentionally required:

document why exposure is safe.

The API URL itself is not necessarily secret, but server-only infrastructure names may be.

---

# 88. Testing Strategy

Add focused tests for the API foundation.

Use the repository's existing frontend test infrastructure.

Do NOT install a second test runner.

If no suitable automated unit test infrastructure exists, inspect the project before deciding whether adding one belongs to this phase.

Do not invent a large testing stack casually.

---

# 89. Required Client Tests

At minimum cover, using the actual contract:

```text
base URL construction
query encoding
default Accept header
JSON Content-Type behavior
successful JSON response
success envelope decoding
204 response
structured API error
validation error preservation
404 preservation
429 + Retry-After
500 safe error handling
non-JSON error body
network failure
timeout
caller abort
header merging
```

Add tests for Next.js cache option forwarding if the architecture exposes them.

---

# 90. Auth Regression Test

If the generic client supports caller-provided authorization headers, verify:

```text
token can be supplied deliberately
token is not persisted
token is not logged
```

Do not use a real Clerk token.

---

# 91. No Real Backend Dependency in Unit Tests

Unit tests should not require:

```text
running Laravel
internet
production API
```

Mock/stub the fetch boundary appropriately using existing testing conventions.

---

# 92. Integration Smoke Check

Where practical, a local integration smoke check against the configured Laravel API may be performed if the backend is available.

Do not make Phase 13.5 PASS depend on external infrastructure that is unavailable if unit/contract verification is sufficient.

Report:

```text
RUN / NOT RUN
```

with reason.

---

# 93. Type Safety Tests

Ensure TypeScript correctly rejects obviously invalid client option usage where practical.

Do not weaken types to make tests convenient.

---

# 94. Error Narrowing

Provide a clean way for higher layers to determine whether an unknown caught value is the API client's typed error.

For example:

```text
instanceof
```

or an appropriate type guard.

Do not force every caller to inspect arbitrary object properties.

---

# 95. Error Message Discipline

`Error.message` should contain a safe developer/application-level description.

Do not stuff serialized backend responses into it.

Keep structured metadata structured.

---

# 96. Future 13.8 Boundary

Phase 13.8 will own presentation of:

```text
not found
loading
unexpected failure
route-level error
```

Therefore Phase 13.5 only produces machine-usable error information.

It does NOT choose:

```text
toast
dialog
error page
inline banner
redirect
```

---

# 97. Future Form Boundary

Future request/enquiry forms will decide how:

```text
422 field errors
```

map onto actual form controls.

Phase 13.5 preserves them.

It does not render them.

---

# 98. Future Auth Boundary

A later authenticated layer will acquire Clerk tokens and inject them into requests.

The API client must make this possible without coupling the generic transport to Clerk.

Desired future dependency:

```text
Clerk token acquisition
       ↓
authenticated API wrapper/caller
       ↓
generic API client
```

NOT:

```text
generic API client
       ↓
Clerk React hook
```

---

# 99. Future Catalog Boundary

Group N may eventually create domain clients such as conceptually:

```text
getProducts()
getProduct()
getCategories()
searchProducts()
```

Those should use Phase 13.5.

Do NOT implement them now.

---

# 100. Future Cart Boundary

Cart endpoints later use the same transport.

Do not special-case:

```text
guest token
cart ownership
cart persistence
```

in the generic client during this phase.

---

# 101. Request-First Boundary

The production request-first policy does not change generic transport behavior.

Do not add:

```text
checkout disabled
cart disabled
MTO routing
```

logic to the API client.

Those are application/domain concerns.

---

# 102. Documentation

Document the API-client architecture concisely.

Preferred location:

```text
frontend/web/lib/api/README.md
```

only if repository conventions support local architecture documentation.

Otherwise place the documentation in the existing Group M execution record.

Document:

```text
authority
base URL configuration
/api/v1 behavior
generic request usage
error model
timeout/cancellation
cache ownership
authentication boundary
security constraints
```

Do not create unnecessary documentation duplication.

---

# 103. Usage Examples

Documentation may show small transport examples such as:

```ts
const response = await apiRequest<MyResponse>({
  method: "GET",
  path: "/example",
});
```

Use neutral examples.

Do not implement fake production endpoints merely for documentation.

---

# 104. Environment Documentation

If an environment variable is introduced, update the repository's approved example/template environment file.

Never place a real deployment URL there unless project policy intentionally treats it as public configuration.

Explain expected format.

---

# 105. AGENTS Guidance

Update:

```text
frontend/AGENTS.md
```

only if durable API-client rules should constrain future agents.

Useful durable rules may include:

```text
all Laravel HTTP access goes through canonical API client
no Axios without architecture approval
no raw page-level fetch to Laravel
no business logic in transport
no token persistence
preserve backend error contract
```

Do not turn AGENTS.md into an implementation diary.

---

# 106. ADR Decision

Add an ADR only if this phase introduces a material architecture decision not already recorded.

A reasonable ADR, if repository convention warrants it, would be:

```text
WEB-001 — Native Fetch API Client Boundary
```

Possible decisions:

```text
native fetch
single generic transport
Laravel /api/v1
no Axios
no React dependency
server-first/runtime-neutral
typed errors
backend contract preserved
domain-specific clients layered later
auth injection external to transport
cache policy owned by consumers
```

Use the repository's actual ADR numbering convention.

Do not force an ADR if the project records these decisions elsewhere.

---

# 107. Cognitive Complexity

Keep implementation straightforward.

Repository rule remains:

```text
cognitive complexity ≤ 15
```

where enforced.

Do not create one giant request function handling every concern through deeply nested branching.

Extract cohesive helpers where justified.

---

# 108. Return Count

Preserve the project's preference for:

```text
≤ 3 returns per function
```

where practical.

Do not distort simple TypeScript merely to mechanically satisfy this preference if existing frontend standards clarify otherwise.

---

# 109. Naming

Prefer names that describe infrastructure responsibilities.

Good conceptual examples:

```text
apiRequest
ApiError
buildApiUrl
parseApiResponse
```

Avoid vague names:

```text
helper
utils
common
manager
serviceThing
```

Follow existing repository naming conventions first.

---

# 110. No Premature Repository Pattern

Do NOT create:

```text
ProductRepository
CategoryRepository
CartRepository
OrderRepository
```

during Phase 13.5.

The project does not need enterprise ceremony around a generic fetch wrapper.

---

# 111. No Global Singleton State

The API client should not maintain mutable global state such as:

```text
current user
current token
last response
retry queue
request cache
```

Next.js fetch/server caching and higher layers own relevant state.

---

# 112. Concurrency

The transport must be safe for concurrent server requests.

Never store per-request:

```text
Authorization header
AbortController
request ID
```

in shared mutable module state.

This is especially important under SSR.

---

# 113. Server Request Isolation

A future authenticated SSR request must never leak one user's bearer token into another request.

Design Phase 13.5 so authentication is passed per request/call.

No mutable global auth header.

---

# 114. Security Regression Tests

Where practical, test that:

```text
one request's headers
do not mutate
the next request's headers
```

This is a high-value SSR isolation invariant.

---

# 115. Request Header Authority

Future callers may add headers, but generic client defaults should remain predictable.

Do not accept unsafe headers from user-controlled values.

This is an internal application API, not an arbitrary proxy.

---

# 116. Content Negotiation

Do not add:

```text
XML
text/html
protobuf
```

support without an API requirement.

V1 API is JSON-oriented except valid no-content/file/multipart cases defined by contract.

---

# 117. File Downloads

Do not build blob/file-download infrastructure unless the frozen V1 frontend endpoints currently require it.

Attachments being uploaded later does not imply generic download support is needed now.

---

# 118. Upload Progress

Native `fetch` does not provide straightforward upload-progress semantics.

Do not replace the transport stack merely to obtain hypothetical future progress bars.

Solve that only when an actual UX requirement exists.

---

# 119. Response Headers

Preserve access to contractually meaningful response metadata such as:

```text
Retry-After
request identifier
pagination headers
```

if those are actually defined.

Do not expose every response header as application state unnecessarily.

---

# 120. HTTP Status

Successful typed responses may need status metadata only if actual consumers require it.

Do not wrap every successful resource in a huge transport object without reason.

Choose a minimal API that still supports contract requirements.

---

# 121. Generic Client Ergonomics

A future endpoint function should be concise.

Desired conceptual use:

```ts
return apiRequest<ProductCollection>({
  path: "/products",
  query,
  cache: ...
});
```

not:

```ts
return apiRequest({
  protocol: ...,
  parser: ...,
  serializer: ...,
  adapter: ...,
  middleware: ...,
  responseFactory: ...
});
```

Avoid building a mini networking framework.

---

# 122. No Middleware Pipeline Framework

Do not invent Axios-style:

```text
interceptors
middleware arrays
plugin system
hooks
```

during this phase.

Simple composition is preferable.

---

# 123. Public Error Shape Stability

Future application code should not depend directly on arbitrary Laravel implementation internals.

Depend on the **frozen API error contract**.

If backend currently emits undocumented fields:

```text
ignore them
```

unless the contract is updated through the proper process.

---

# 124. Contract Conflict Rule

If:

```text
OpenAPI
api-contract.md
api-conventions.md
actual Laravel implementation
```

disagree materially:

```text
STOP
```

Do not guess.

Report:

```text
documents involved
exact discrepancy
affected endpoint/field/status
recommended source-of-truth resolution
```

Do not silently teach the frontend whichever behavior happens to be easiest.

---

# 125. Frozen V1 Rule

Do not modify backend V1 merely to simplify the frontend.

The API contract is frozen.

Any genuine contract defect requires the established contract-change process.

---

# 126. Verification Commands

Run actual commands available in `frontend/web/package.json`.

At minimum, where available:

```text
TypeScript/typecheck
ESLint
API client tests
existing theme contract tests
production build
git diff --check
```

Do not invent script names.

---

# 127. Existing Foundation Non-Regression

Because 13.1–13.4 passed, re-run enough existing verification to prove 13.5 did not break:

```text
MUI SSR integration
theme contract
production build
```

Do not reopen those phases unnecessarily.

---

# 128. Dependency Check

Final expected dependency change:

```text
NONE
```

If a dependency is added:

```text
Phase 13.5 cannot be reported as clean PASS
without explaining why native platform capabilities were insufficient.
```

---

# 129. Files Expected to Change

Likely:

```text
frontend/web/lib/api/*
frontend/web/.env.example
    # only if repo convention uses it

frontend/AGENTS.md
    # only durable guidance

phases/group-M-phases.md

docs/decisions.md
    # only if ADR justified
```

Possibly:

```text
frontend/web/package.json
```

only for scripts if genuinely necessary.

Do not modify theme files unless a real regression is discovered.

---

# 130. Completion Report

Return:

```text
Phase 13.5 status:
PASS / BLOCKED


ARCHITECTURE

Canonical API client:
<path>

Transport:
native fetch / FAIL

React dependency:
NONE / FAIL

Axios/third-party HTTP dependency:
NONE / <explain>

Domain-specific endpoints implemented:
NONE / <list>

API namespace:
/api/v1 / FAIL

Base URL configuration:
<variable / strategy>

Hard-coded production API URL:
NO / FAIL

Server-compatible:
YES / NO

Browser-compatible where safe:
YES / NO

Mutable global request/auth state:
NONE / FAIL


REQUEST HANDLING

URL construction:
PASS / FAIL

Query serialization:
PASS / FAIL

JSON requests:
PASS / FAIL

FormData-compatible:
YES / NO / NOT REQUIRED <reason>

Accept header:
PASS / FAIL

Header merging:
PASS / FAIL

GET/HEAD body protection:
PASS / FAIL

Caller AbortSignal:
PASS / FAIL

Timeout:
<value / strategy>

Automatic retries:
NONE / FAIL


AUTH BOUNDARY

Clerk integrated:
NO

Authorization injection supported:
YES / NO

Bearer tokens persisted:
NO / FAIL

Global auth header:
NONE / FAIL

Cross-request token isolation:
PASS / FAIL


RESPONSE CONTRACT

Frozen success contract preserved:
PASS / FAIL

Frozen error contract preserved:
PASS / FAIL

Validation errors preserved:
PASS / FAIL

204 handled:
PASS / FAIL

404 preserved:
PASS / FAIL

422 preserved:
PASS / FAIL

429 Retry-After preserved:
PASS / FAIL

5xx safely represented:
PASS / FAIL

Non-JSON error handled:
PASS / FAIL

Network failure distinguished:
PASS / FAIL

Timeout distinguished:
PASS / FAIL

Caller abort distinguished:
PASS / FAIL

Request/correlation ID:
<strategy / NOT IN CONTRACT>


NEXT.JS

Cache policy hard-coded globally:
NO / FAIL

Caller cache options:
PASS / FAIL

Revalidation/tag capability:
PASS / FAIL / NOT APPLICABLE

Root layout changed:
NO / <reason>

Client boundary expanded:
NO / <reason>


SECURITY

Secrets in client bundle:
NONE / FAIL

Token logging:
NONE / FAIL

Request body logging:
NONE / FAIL

Raw backend internals exposed:
NO / FAIL

mode=no-cors:
NO / FAIL

Undocumented BFF/proxy created:
NO / FAIL

Direct DB access:
NO / FAIL


TESTS

Base URL:
PASS / FAIL

Query encoding:
PASS / FAIL

Headers:
PASS / FAIL

JSON success:
PASS / FAIL

204:
PASS / FAIL

Structured error:
PASS / FAIL

Validation error:
PASS / FAIL

404:
PASS / FAIL

429 + Retry-After:
PASS / FAIL

500:
PASS / FAIL

Non-JSON failure:
PASS / FAIL

Network failure:
PASS / FAIL

Timeout:
PASS / FAIL

Caller abort:
PASS / FAIL

Header/request isolation:
PASS / FAIL

Cache option forwarding:
PASS / FAIL / NOT APPLICABLE

Real backend required:
NO


VALIDATION

TypeScript:
PASS / FAIL

ESLint:
PASS / FAIL

API client tests:
PASS / FAIL

Theme regression:
PASS / FAIL

Production build:
PASS / FAIL

git diff --check:
PASS / FAIL


SCOPE

13.6 routing conventions started:
NO

13.7 layout system started:
NO

13.8 error/loading UI started:
NO

13.9 responsive foundation started:
NO

Group N started:
NO

Clerk UI/auth integration started:
NO

Flutter changed:
NO

Backend/API changed:
NO

Dependencies added:
NONE / <list>


DOCUMENTATION

Group M record:
PASS / FAIL

Environment documentation:
<path / NONE>

Frontend agent guidance:
<updated / unchanged>

ADR:
<id / NONE>


FILES CHANGED

<list>


GIT

git-workflow-and-versioning skill read:
YES / NO

Operations:
<list>

Commit:
<hash/message>

Push:
<result / NONE>


RESULT

Phase 13.5:
PASS / BLOCKED

Phase 13.6:
READY / BLOCKED
```

---

# 131. STOP Condition

Phase 13.5 may be declared PASS only when:

- one canonical generic API transport exists;
- it uses native `fetch`;
- no unnecessary HTTP/data-fetching dependency was added;
- it has no React dependency;
- it works naturally in server-side Next.js code;
- it does not depend on browser globals;
- configuration does not hard-code deployment URLs;
- `/api/v1` construction is deterministic;
- query parameters are encoded safely;
- JSON headers/body behavior is correct;
- future multipart/FormData use is not unnecessarily blocked;
- the frozen success/error contracts are preserved;
- structured validation errors remain available;
- 204 responses are handled;
- 401/403/404/422 remain backend-defined outcomes;
- `429 Retry-After` is preserved according to the API convention;
- malformed/non-JSON error responses fail safely;
- HTTP, network, timeout, and intentional-abort failures are distinguishable where needed;
- requests have bounded timeout behavior;
- caller cancellation is supported;
- automatic mutation retries do not exist;
- cache behavior is not globally hard-coded;
- future Next.js cache/revalidation behavior can be selected by consumers;
- Clerk is not coupled into generic transport;
- bearer tokens are never persisted;
- no mutable global authorization state exists;
- concurrent SSR requests cannot leak authorization headers;
- backend business authority is preserved;
- no direct database access exists;
- no undocumented Next.js API proxy/BFF was created;
- no Group N/domain endpoint implementation was started;
- tests cover the transport/error edge cases;
- TypeScript passes;
- ESLint passes;
- API client tests pass;
- existing theme foundation remains healthy;
- production build passes;
- `git diff --check` passes;
- Git operations follow `git-workflow-and-versioning`.

Then report:

```text
Phase 13.5 — PASS
Phase 13.6 — READY
```

Do not start Phase 13.6 automatically.

**Git operations are authorized only through the root `git-workflow-and-versioning` skill. Follow that skill exactly.**

---

# Phase 13.5 Execution Record

```text
Phase 13.5: PASS
Phase 13.6: READY
```

## Architecture

- Canonical transport: `frontend/web/lib/api/client.ts`; native `fetch`, no React dependency, no third-party HTTP client, no domain endpoints.
- All resource paths receive `/api/v1` exactly once. Base origin is server-only `API_BASE_URL`, HTTPS-only except localhost development; no deployment URL fallback is hard-coded. Browser clients require an explicitly supplied approved public origin.
- Client is concurrency-safe: only immutable fetch/config defaults live in the factory closure. Authorization and all request-specific headers, cancellation state, and timers are per call.
- No Clerk integration, token persistence, proxy/BFF, raw database access, or automatic retry was introduced.

## Request And Response Contract

- Deterministic URL/query construction; scalar/repeated-array query serialization uses `URLSearchParams`; absolute paths, `/api/v1`-prefixed paths, and path traversal segments are rejected.
- `Accept: application/json` is default. JSON bodies set `Content-Type`; FormData is passed without a multipart header. GET bodies are rejected. Caller headers are cloned/merged.
- Success envelopes and `meta.pagination` are preserved; 204 returns `undefined`. `ApiError` preserves HTTP status, structured `errors`, validation details, request ID, and numeric-seconds `Retry-After` on 429. Malformed/non-JSON responses expose no raw response body. 401/403/404/422 are not remapped.
- `ApiTransportError` separates network, timeout, and intentional abort. Default timeout is 10 seconds and configurable; per-request AbortSignal is honored. No mutable token/header state or logging exists.
- Cache policy is caller-selected. Native `cache` works in either runtime; Next.js `next.revalidate`/`next.tags` are forwarded only server-side.

## Configuration And Documentation

- `API_BASE_URL` is configured locally in ignored `frontend/web/.env.local` as `http://127.0.0.1:8000`; production still requires its own HTTPS deployment value. The format and server/browser exposure boundary are documented in `frontend/web/lib/api/README.md`. No frontend `.env.example` convention exists; no real deployment URL was added.
- Durable routing-through-client and backend-authority rules were added to `frontend/AGENTS.md`.
- ADR `WEB-001` records the native Fetch boundary. No dependencies were added.

## Tests And Verification

```text
API client tests: PASS (17 cases; Node built-in test runner, no new dependency)
TypeScript: PASS (`npx tsc --noEmit`)
ESLint: PASS (`npm run lint`)
Theme contract: PASS (`npm run test:theme`)
Production build: PASS (`npm run build`)
git diff --check: PASS
```

Coverage includes URL/version construction, query/path encoding, configuration, headers and per-request authorization isolation, JSON/FormData, GET-body rejection, success/pagination/204, structured API and validation errors, 401/403/404/422, 429 retry metadata, malformed 500 responses, network failure, timeout, caller abort, and cache option forwarding/runtime separation.

Local Laravel integration smoke: **NOT RUN** — the Laravel server was not running during verification. API unit/contract behavior is verified without requiring Laravel or external network access.

## Scope

```text
13.6 routing conventions: NOT STARTED
13.7 layout system: NOT STARTED
13.8 application error/loading UI: NOT STARTED
13.9 responsive foundation: NOT STARTED
Group N catalog/domain clients: NOT STARTED
Clerk UI/auth: NOT STARTED
Flutter/backend/API changes: NONE
```

Phase 13.6 is ready; it was not started automatically.
