# Group B phases instructions

# Phase 2.6 — API Routing Foundation

## 1. Purpose

Implement the Laravel **API routing foundation** for the already-frozen Version 1 API contract.

Phases 2.1–2.5 have already established:

* repository/project setup
* Laravel application initialization
* environment configuration
* database connection
* base application structure

Do **not** redo those phases.

This phase establishes the route architecture that later Laravel implementation phases will populate with:

* authentication
* catalog
* cart
* checkout
* orders
* requests
* enquiries
* notifications
* inventory
* Staff/Admin operations

The API contract is frozen. The routing implementation must conform to it rather than redefining it. The repository's roadmap explicitly places **Phase 2.6 — API routing foundation** here, followed by exception/error handling in 2.7.

---

# 2. Mandatory First Actions

Before modifying routing code:

### Read the current repository instructions

Read:

```text
AGENTS.md
```

and any more-specific applicable `AGENTS.md` files.

Do not rely only on an earlier copy of the instructions.

### Read the frozen API contract

At minimum inspect:

```text
docs/api/api-contract.md
docs/api/api-resources.md
docs/api/api-conventions.md
docs/api/openapi.yaml
docs/decisions.md
```

The uploaded API conventions explicitly identify themselves as the frozen Version 1 baseline and state that they govern `/api/v1`.

---

# 3. Scope

This phase covers:

```text
Laravel route registration
API version grouping
route naming conventions
route/controller organization
middleware attachment points
route parameter constraints
public/private route separation
canonical path verification
route-list verification
rate-limiter plumbing
route-level API security boundaries
```

This phase does **not** implement the business domain.

---

# 4. Explicit Out-of-Scope

Do not implement in Phase 2.6:

* database migrations
* domain models
* repositories
* business services
* FormRequest classes for domain operations
* DTO implementations for domain operations
* authentication workflows
* authorization policies
* payment processing
* order business logic
* catalog business logic
* inventory business logic
* notification business logic
* attachment storage implementation
* health endpoint business logic
* full exception/error handler
* logging architecture
* CI configuration

Those belong to subsequent phases.

---

# 5. Frozen API Version

All Version 1 routes must live under:

```text
/api/v1
```

No unversioned Version 1 API routes are allowed.

The frozen convention explicitly requires `/api/v1` and states that removed endpoint IDs remain retired rather than recycled.

Do not introduce:

```text
/api
/v1
/api/v2
```

as alternate Version 1 route surfaces.

---

# 6. Canonical Route Vocabulary

Use the **current canonical routes**, not the earlier conceptual `/staff/...` paths.

The frozen conventions explicitly removed the former Staff-prefixed aliases and retained canonical routes without the redundant Staff prefix:

```text
/orders
/inventory/{product}/adjust
/products
/requests
/enquiries
```

This was done specifically to reduce attack surface and prevent divergent authorization behavior.

Therefore do **not** implement:

```text
/api/v1/staff/orders
/api/v1/staff/orders/{order}
/api/v1/staff/inventory
/api/v1/staff/products
/api/v1/staff/requests
/api/v1/staff/enquiries
```

as V1 canonical routes.

Do not add aliases "for convenience."

---

# 7. Route Namespace Architecture

Use route groups to separate:

```text
public
authenticated customer/self
operational
administrative
```

The exact Laravel grouping mechanism must follow the installed Laravel version and existing application structure.

Conceptually:

```text
/api/v1
    ├── public
    ├── auth
    ├── me
    ├── orders
    ├── requests
    ├── enquiries
    ├── notifications
    ├── products
    ├── inventory
    └── admin
```

Do not encode authorization merely through URL naming.

A route such as:

```text
/api/v1/orders
```

is operational only because its middleware/policy layer later establishes the approved authorization, not because the path itself says `staff`.

---

# 8. Public Route Group

Public routes must remain genuinely public where the contract requires it.

At minimum:

```text
GET /api/v1/products
GET /api/v1/products/{product}
GET /api/v1/categories
GET /api/v1/categories/{category}
POST /api/v1/auth/register
POST /api/v1/auth/login
POST /api/v1/auth/forgot-password
POST /api/v1/auth/reset-password
POST /api/v1/requests
POST /api/v1/enquiries
```

The exact final endpoint inventory must determine which of these are present.

The frozen conventions explicitly require public catalog routes to remain unauthenticated for SSR/SEO and define anonymous request/enquiry submission.

Do not attach the authenticated-user middleware to public catalog routes.

---

# 9. Authentication Route Separation

Keep authentication routes distinct from authenticated self-service routes.

Conceptually:

```text
/api/v1/auth/...
```

versus:

```text
/api/v1/me/...
```

Do not put registration/login under `/me`.

Do not attach authenticated middleware to registration/login.

Password security operations already defined by the frozen contract must retain their documented route paths and security behavior.

For example, the frozen conventions define:

```text
POST /auth/change-password
POST /auth/forgot-password
POST /auth/reset-password
```

with strict request schemas.

---

# 10. Authenticated `/me` Route Group

Self-service routes should be grouped under:

```text
/api/v1/me
```

Examples include:

```text
GET   /api/v1/me
PATCH /api/v1/me

GET   /api/v1/me/cart
POST  /api/v1/me/cart/items
PATCH /api/v1/me/cart/items/{item}
DELETE /api/v1/me/cart/items/{item}

GET   /api/v1/me/orders
GET   /api/v1/me/orders/{order}
POST  /api/v1/me/orders/{order}/cancel
GET   /api/v1/me/orders/{order}/tracking

GET   /api/v1/me/requests
GET   /api/v1/me/requests/{request}

GET   /api/v1/me/enquiries
GET   /api/v1/me/enquiries/{enquiry}

GET   /api/v1/me/notifications
```

Use one authenticated-user middleware boundary for the group where practical.

Do not make clients send `user_id` to determine whose `/me` data is retrieved.

---

# 11. Customer Ownership at Route Level

Route grouping alone does not establish resource ownership.

The routing foundation must leave a clear middleware/policy attachment point for:

```text
authenticated principal
+
owner/resource authorization
```

The frozen contract requires private-resource 404 masking rather than exposing whether another customer's resource exists.

Therefore do not build route middleware that turns every unauthorized private-resource access into a generic exposed `403`.

The detailed policy/error implementation comes later.

---

# 12. Guest Cart Routing

The frozen API contains guest-cart behavior.

Do not remove or redesign it during route setup.

The guest-cart identifier is an opaque UUIDv4 bearer credential scoped only to its cart.

The route foundation must therefore preserve support for the appropriate cart endpoints and their required header/cookie transport:

```text
X-Guest-Cart-Id
```

The server must treat this as a scoped guest-cart credential, not as user identity.

---

# 13. Guest Cart Security

Do not implement guest-cart authorization by:

```text
$request->input('user_id')
```

or:

```text
$request->input('guest_cart_id')
```

as identity proof.

The actual guest-cart ownership resolution must later be server-side.

The frozen convention explicitly states that the guest token does not authorize account operations and cannot itself authorize a merge.

---

# 14. Operational Order Routes

Register the canonical order operations according to the frozen endpoint inventory.

Do not prefix them with `/staff`.

Conceptually:

```text
GET  /api/v1/orders
GET  /api/v1/orders/{order}

POST /api/v1/orders/{order}/accept
POST /api/v1/orders/{order}/process
POST /api/v1/orders/{order}/ready-for-pickup
POST /api/v1/orders/{order}/ship
POST /api/v1/orders/{order}/deliver
POST /api/v1/orders/{order}/complete
POST /api/v1/orders/{order}/delivery-fee
```

**Important:** use the exact action names and endpoint IDs in the frozen `api-contract.md`/OpenAPI rather than blindly copying these conceptual names if they differ.

The routing foundation must never introduce a generic:

```text
PATCH /api/v1/orders/{order}
```

for status mutation.

The contract explicitly uses controlled POST actions for order transitions.

---

# 15. Customer Order Routes vs Operational Order Routes

Do not confuse:

```text
/api/v1/me/orders/{order}
```

with:

```text
/api/v1/orders/{order}
```

They represent different authorization contexts.

Customer:

```text
AUTHENTICATED_OWNER
```

Operational:

```text
STAFF/ADMIN operational permission
```

The controller/resource implementation comes later, but the route structure must preserve this distinction.

---

# 16. Inventory Routing

Use the frozen canonical inventory path.

The convention states:

```text
POST /api/v1/inventory/{product}/adjust
```

for the inventory adjustment operation.

Do not create:

```text
POST /api/v1/inventory/{inventory}
POST /api/v1/staff/inventory/{inventory}/adjust
```

as alternative V1 endpoints.

The parameter is the canonical product resource identifier where the frozen endpoint contract specifies `{product}`.

---

# 17. Product Operational Routes

Use the canonical product-management route family:

```text
GET   /api/v1/products
POST  /api/v1/products
GET   /api/v1/products/{product}
PATCH /api/v1/products/{product}
```

where the endpoint inventory distinguishes public GET operations from Staff/Admin mutation access.

This means authorization cannot be inferred only from the path because the same product resource has different audience/operation semantics.

The frozen contract explicitly separates catalog management request schemas from response schemas and requires strict allow-lists.

---

# 18. Category Routes

Use the canonical category paths defined by the frozen contract:

```text
GET   /api/v1/categories
GET   /api/v1/categories/{category}
POST  /api/v1/categories
PATCH /api/v1/categories/{category}
```

The category route family has the same public/protected split as products (`CAT-003/004` vs `CAT-011/012`):

```text
PUBLIC    GET   /api/v1/categories
PUBLIC    GET   /api/v1/categories/{category}
PROTECTED POST  /api/v1/categories
PROTECTED PATCH /api/v1/categories/{category}
```

The `GET` operations are `PUBLIC` reads (`CAT-003/004`): Anonymous, Customer, Staff and Admin may call them without authentication.

The `POST`/`PATCH` operations are `ADMINISTRATIVE` catalog mutations (`CAT-011/012`): they require authenticated `STAFF`/`ADMIN` identity **and** the `products.manage` permission where approved, enforced by backend authorization middleware/policies — never by frontend-only checks or path inference. These routes must never be registered in a public, unauthenticated route group.

Authorization must therefore be attached at route-registration time (e.g. middleware `auth` + permission policy) per operation, not inferred from the shared `/api/v1/categories` path.

Only register methods that actually exist in the endpoint inventory.

Do not add:

```text
/api/v1/categories/{category}/products
```

The frozen convention explicitly rejects that duplicate route in favor of:

```text
GET /api/v1/products?category={category}
```

for product filtering.

---

# 19. Product Variant Routing

Where the frozen contract exposes variants, preserve shallow nested routing:

```text
/api/v1/products/{product}/variants
/api/v1/products/{product}/variants/{variant}
```

Use the exact methods from the endpoint inventory.

Variant resolution must later confirm that:

```text
variant belongs to product
```

Do not treat `variant` as globally interchangeable with a product.

The API conventions explicitly require parent validation for product/variant relationships.

---

# 20. No Product Image Endpoint

Do not add:

```text
GET /api/v1/products/{product}/images
```

The frozen contract embeds product images inside the product detail representation instead of exposing that duplicate endpoint.

If image-upload or image-management routes exist elsewhere in the frozen Admin contract, implement only those exact approved endpoints.

---

# 21. Made-to-Order Request Routes

Use:

```text
POST /api/v1/requests
```

for submission.

Authenticated customer retrieval:

```text
GET /api/v1/me/requests
GET /api/v1/me/requests/{request}
```

Operational retrieval:

```text
GET /api/v1/requests
GET /api/v1/requests/{request}
```

or the exact paths specified in the frozen endpoint inventory.

Do not restore the removed:

```text
/api/v1/staff/requests
```

aliases.

---

# 22. General Enquiry Routes

Use:

```text
POST /api/v1/enquiries
```

for public submission.

Authenticated customer retrieval:

```text
GET /api/v1/me/enquiries
GET /api/v1/me/enquiries/{enquiry}
```

Operational retrieval:

```text
GET /api/v1/enquiries
GET /api/v1/enquiries/{enquiry}
```

or the exact frozen inventory paths.

Again, do not recreate `/staff/enquiries`.

---

# 23. Attachment Routes

Where the frozen contract defines request/enquiry attachment uploads, register:

```text
POST /api/v1/requests/{request}/attachments
POST /api/v1/enquiries/{enquiry}/attachments
```

The frozen security contract defines the `X-Upload-Token` security scheme for anonymous/scoped attachment uploads and requires the token to be bound to the parent resource.

Do not create public unrestricted attachment routes.

Do not place upload implementation/storage logic into this routing phase.

---

# 24. Notification Routes

Register the approved notification routes from the frozen endpoint inventory.

At minimum the authenticated customer path is conceptually:

```text
GET /api/v1/me/notifications
```

Use the exact approved read-state operation for marking notifications read.

Do not create arbitrary notification creation routes for clients.

Notification creation remains a server-side business consequence.

---

# 25. Admin Staff Routes

Admin-only Staff lifecycle operations belong under:

```text
/api/v1/admin/staff
```

Examples:

```text
GET  /api/v1/admin/staff
GET  /api/v1/admin/staff/{user}
POST /api/v1/admin/staff
POST /api/v1/admin/staff/{user}/approve
POST /api/v1/admin/staff/{user}/suspend
POST /api/v1/admin/staff/{user}/reactivate
```

Use only the methods/routes actually present in the frozen inventory.

Attach clear Admin-only middleware/policy placeholders.

Do not let these routes be callable merely because a request reaches `/admin`.

---

# 26. Administrative Authorization Attachment Points

The route foundation must make it straightforward for later phases to attach:

```text
authentication middleware
role/permission middleware or policies
rate limiting
request validation
request IDs
authorization
```

Do not implement authorization logic in closures inside route definitions.

Avoid:

```php
Route::post(..., function () {
    if (auth()->user()->role === 'ADMIN') {
        ...
    }
});
```

Use controllers and later policy/middleware layers.

---

# 27. Middleware Ordering

Preserve the documented validation pipeline:

```text
Transport
    ↓
Schema/type
    ↓
Authentication
    ↓
Authorization
    ↓
Domain
    ↓
Concurrency/transaction
    ↓
External
    ↓
Persistence/workflow
```

The frozen conventions explicitly require this ordering and state that business records should not be accessed before schema validation or external operations before authorization.

Phase 2.6 only establishes routing/middleware attachment points.

Do not implement all later layers prematurely.

---

# 28. Route Model Binding

Use Laravel route model binding only where it aligns with the frozen resource identifier semantics and later authorization requirements.

Do not allow implicit binding to bypass object-level authorization.

For private resources, the eventual behavior must support the frozen 404 masking policy.

Do not expose whether a resource exists merely because Laravel successfully resolved a model before authorization.

---

# 29. Explicit Parameter Constraints

Add route parameter constraints only where they reflect the frozen contract.

Examples:

```text
{product}
{category}
{order}
{request}
{enquiry}
{notification}
{user}
```

If a resource uses opaque machine IDs, use the project's approved identifier constraint.

Do not impose UUID constraints on identifiers that are not defined as UUIDs.

Do not change identifier design during this phase.

---

# 30. Route Name Convention

Give routes stable Laravel route names where useful.

Prefer a predictable structure such as:

```text
api.products.index
api.products.show
api.checkout.store
api.orders.index
api.orders.show
api.orders.accept
```

The exact naming convention should be consistent across the backend.

Do not use route names as the external API contract.

External clients consume:

```text
HTTP method + path
```

not Laravel route names.

---

# 31. Controller Organization

Route declarations should reference controllers rather than embedding business logic.

Conceptual organization:

```text
app/Http/Controllers/Api/V1/
```

with resource/domain groupings as appropriate.

Do not build large controllers merely because the entire endpoint set is being registered.

Do not implement domain actions inside route files.

---

# 32. Route File Organization

Use the routing structure provided by the installed Laravel version.

Do not force the application into an older Laravel `routes/api.php` pattern if the current application uses a different bootstrap configuration.

Route registration should remain easy to inspect.

Prefer grouping by API concern rather than scattering individual route declarations across unrelated files.

---

# 33. Rate Limiting Requirement

The routing foundation must support the project's rate-limiting architecture.

The frozen API conventions require security-sensitive rate-limited responses to return:

```http
429 Too Many Requests
Retry-After: <value>
```

and explicitly prohibit replacing this with a custom JSON `retry_after_seconds` field.

This requirement is mandatory for Laravel implementation.

---

# 34. Rate Limiter Response Contract

When Laravel's rate limiter rejects a request, the API response must eventually conform to the frozen error contract:

```json
{
  "errors": [
    {
      "code": "RATE_LIMITED",
      "message": "..."
    }
  ],
  "meta": {
    "request_id": "..."
  }
}
```

with:

```http
Retry-After: <seconds>
```

Use the actual approved error envelope/code from the frozen contract.

Do not invent an alternative throttling response.

The conventions explicitly define `429 RATE_LIMITED` plus the standard `Retry-After` header.

---

# 35. Rate-Limiter Middleware Foundation

Implement the routing/middleware attachment mechanism so that rate limiting can be applied per route/group without redesigning routing later.

Use Laravel's supported rate-limiting infrastructure.

Do not build a custom distributed rate-limiter in Phase 2.6.

The routing layer must provide a clean place to apply named rate limiters later.

---

# 36. Rate-Limit Threshold Source of Truth

Do not invent new thresholds in Phase 2.6.

The frozen convention already records the approved security-sensitive thresholds, including:

```text
/auth/register
/auth/login
/auth/password/*
/requests
/enquiries
/checkout
/me/cart/items
/me/orders/{order}/cancel
attachment uploads
/inventory/{product}/adjust
/admin/staff/*
/products
```

with their approved rate categories.

The detailed authentication-specific rate-limiting implementation remains Phase 4.11.

Phase 2.6 establishes the routing architecture and contract-compliant throttling response behavior needed later.

---

# 37. Strict Request Validation Requirement

This is mandatory for all later Laravel implementation and must be established as a routing/foundation rule now:

```text
FormRequest::validated()
        ↓
DTO / Command Mapper
        ↓
Domain/Application logic
```

Never:

```text
$request->all()
        ↓
$model->fill(...)
```

The frozen conventions explicitly require unknown fields to be rejected and explicitly prohibit `$request->all()` mass assignment.

---

# 38. `additionalProperties: false` Enforcement

The OpenAPI schemas use strict request objects with `additionalProperties: false`.

Laravel must honor the same behavior.

Therefore future FormRequests must:

* validate only documented fields
* reject undocumented fields
* use `validated()`
* pass only validated fields to DTOs/commands
* never pass `$request->all()` into models

The frozen catalog-management convention explicitly requires:

```text
validated()->only(allowList) → DTO
```

for the catalog write endpoints.

---

# 39. DTO Mapper Requirement

When DTOs are introduced, their constructors/mappers must consume validated data only.

Preferred conceptual flow:

```text
HTTP request
   ↓
FormRequest validation
   ↓
validated()
   ↓
DTO::fromValidated(...)
   ↓
application command/service
```

Do not create:

```text
DTO::fromRequest($request)
```

that internally calls:

```php
$request->all()
```

This would violate the frozen input-security contract.

---

# 40. Unknown Field Security

Do not silently ignore unknown privileged request fields.

For example:

```json
{
  "name": "Modern Sofa",
  "role": "ADMIN"
}
```

must not become:

```text
name accepted
role silently ignored
```

where the contract specifies strict unknown-field rejection.

The frozen convention explicitly says unknown fields are rejected for strict create/update/action inputs.

The full rejection mechanism belongs to the request-validation phase, but the routing foundation must not create a generic request pipeline that inherently encourages permissive payload handling.

---

# 41. Public vs Protected Middleware

Do not attach authentication globally to the entire `/api/v1` group if public routes exist.

Instead structure routes so public operations explicitly remain public while protected groups receive the relevant middleware.

The frozen contract requires public catalog routes to remain unauthenticated.

---

# 42. Do Not Use Frontend Role Checks

The route foundation must not depend on:

```text
Next.js route protection
Flutter route guards
Admin UI hiding buttons
```

for authorization.

Laravel must eventually enforce all protected routes.

The uploaded `AGENTS.md` explicitly states that frontend validation/auth state is advisory and backend/domain validation and authorization are authoritative.

---

# 43. CSRF/Cookie Route Considerations

The frozen contract distinguishes browser cookie-authenticated mutations from bearer-only mobile requests.

The API conventions require CSRF protection for cookie-authenticated mutations and exempt bearer-only Flutter traffic.

Phase 2.6 should ensure the middleware architecture leaves a clean attachment point for this behavior.

Do not disable CSRF globally merely to make API requests easier during development.

The complete authentication implementation belongs to Group D.

---

# 44. CORS Considerations

Do not use CORS as authorization.

If CORS configuration is touched during this phase, preserve the frozen allow-list architecture:

```text
https://www.example.com
https://admin.example.com
```

and never use:

```text
*
```

with credentialed browser traffic.

The frozen conventions explicitly define the allowed origins and headers.

Full CORS tuning can remain with the appropriate infrastructure/authentication phase.

---

# 45. Cache-Sensitive Route Grouping

Route organization must preserve the distinction between:

```text
PUBLIC
PRIVATE
INTERNAL/OPERATIONAL
```

The frozen contract specifies public caching for catalog endpoints and private/no-store behavior for customer/operational data.

Do not attach a public caching policy to:

```text
/orders
/me/*
/inventory
/admin/*
```

Route grouping should make it easy for later middleware to apply the correct cache policy.

---

# 46. Route Discovery Verification

After implementation, inspect the Laravel route table using the installed framework's route-list command.

Verify:

* all intended routes appear
* no removed alias appears
* no duplicate route appears
* no unintended public privileged route exists
* methods are correct
* prefixes are correct
* middleware assignment is visible
* route names are unique

---

# 47. Canonical Route Security Check

Explicitly verify that these **must not exist**:

```text
/api/v1/staff/orders
/api/v1/staff/inventory
/api/v1/staff/products
/api/v1/staff/requests
/api/v1/staff/enquiries
```

as legacy duplicates.

The frozen security review removed these aliases specifically to reduce the attack surface.

---

# 48. Route Collision Review

Check for collisions such as:

```text
/products/{product}
/products/{action}
```

or:

```text
/orders/{order}
/orders/{fixed-action}
```

Ensure fixed literal routes are registered/resolved correctly relative to parameter routes as appropriate for the Laravel version.

Do not use broad wildcards that can capture unrelated routes.

---

# 49. API Route Naming and Versioning Test

The following must be distinguishable:

```text
GET /api/v1/products
GET /api/v1/products/{product}
```

and:

```text
GET /api/v1/categories
GET /api/v1/categories/{category}
```

Likewise:

```text
GET /api/v1/orders
GET /api/v1/orders/{order}
```

Do not accidentally create a dynamic route that captures a fixed action incorrectly.

---

# 50. Route-Model Identifier Semantics

Respect the frozen catalog rule:

* Product/Category details may resolve by slug or opaque machine ID.
* Variant detail resolves by variant ID under its product parent.

The conventions explicitly document this dual-resolution behavior.

Do not hard-code a route constraint that permits only numeric IDs if the frozen contract permits slug resolution.

---

# 51. No Business Logic in Routes

Do not place:

* database queries
* pricing calculations
* order transitions
* inventory adjustments
* permission decisions
* DTO mapping
* notification creation

inside route declarations.

For Phase 2.6, route declarations should establish **transport and middleware topology**, not business behavior.

---

# 52. Route Documentation

Where the routing structure differs from an obvious Laravel default, add a concise comment or documentation note.

Document:

* API version boundary
* route grouping strategy
* canonical route aliases policy
* middleware attachment points
* rate-limit attachment point

Do not produce a large routing manual.

---

# 53. Required Validation

Run the appropriate project checks after routing changes.

At minimum:

```bash
php artisan route:list
php artisan test
```

and any already-configured repository checks from `AGENTS.md`.

Do not claim tests pass unless they were actually executed.

---

# 54. Route Smoke Tests

Add or prepare lightweight routing tests where the existing test infrastructure supports them.

At minimum verify:

### Public

```text
GET /api/v1/products
GET /api/v1/categories
```

do not require authentication.

### Protected

```text
GET /api/v1/me
GET /api/v1/me/orders
```

do require authentication.

### Operational

```text
GET /api/v1/orders
GET /api/v1/inventory
```

do not become public.

### Admin

```text
GET /api/v1/admin/staff
```

does not become publicly accessible.

Do not implement full business responses yet merely to satisfy these routing tests.

---

# 55. Rate-Limit Smoke Test

Where the rate-limiter response is wired in this phase, verify that an intentionally throttled request produces:

```http
429 Too Many Requests
Retry-After: <seconds>
```

and the canonical error response structure.

Do not expose:

* internal limiter algorithm
* bucket state
* infrastructure keys
* internal counters

The documented contract requires `Retry-After`; it specifically rejects a custom JSON retry field.

---

# 56. Strict Input Pipeline Smoke Test

Where a minimal validation test is practical, verify the future contract direction with a representative request object:

```json
{
  "name": "Example",
  "unexpected_field": "should_not_be_accepted"
}
```

The test should document the expected strict rejection behavior.

Do not use `$request->all()` in the test implementation or production implementation.

The actual domain FormRequest work remains in later phases.

---

# 57. OpenAPI Route Coverage Check

Compare the Laravel route list against:

```text
docs/api/openapi.yaml
```

for the routes that Phase 2.6 registers.

Every implemented route must correspond to an approved OpenAPI operation.

Do not add "temporary" API routes that are absent from the frozen specification.

If a routing-only infrastructure endpoint is necessary, verify that the frozen contract explicitly allows it or defer it to Phase 2.9.

---

# 58. Health Endpoint Boundary

Do **not** implement the health endpoint as part of 2.6.

The roadmap explicitly assigns:

```text
Phase 2.9 — Health/status endpoint
```

to a later phase.

Phase 2.6 may establish route-grouping conventions that 2.9 will use.

Do not pull Phase 2.9 forward.

---

# 59. Exception Handling Boundary

Likewise, do not implement the complete API exception/error layer here.

The roadmap assigns:

```text
Phase 2.7 — Exception/error handling foundation
```

after routing.

Phase 2.6 may establish middleware/route structure needed by 2.7.

Do not duplicate error handling in every route.

---

# 60. Route Inventory Reconciliation

Before completion, compare the route set with the frozen endpoint inventory.

Explicitly detect:

```text
missing route
extra route
alias route
wrong method
wrong prefix
wrong authentication boundary
wrong middleware group
```

No unexplained differences are allowed.

The frozen conventions state that the V1 endpoint catalogue is authoritative and removed endpoint IDs are retired rather than reused.

---

# 61. Security Review

Before completion verify:

* no public privileged route
* no Admin route accessible without the appropriate future authorization middleware
* no Customer ownership route accepts arbitrary user IDs as authorization
* no legacy `/staff/...` aliases
* no arbitrary route wildcard
* no route exposes internal infrastructure
* guest-cart credentials remain scoped
* upload-token routes remain protected
* rate-limited routes have the `Retry-After` response architecture
* route groups do not accidentally bypass CSRF/auth middleware
* no client role controls authorization

---

# 62. Definition of Done

Phase 2.6 is complete only when:

* [ ] Current `AGENTS.md` and applicable nested instructions were read.
* [ ] Frozen Group A API contract was reviewed.
* [ ] `docs/api/openapi.yaml` was reviewed.
* [ ] `/api/v1` is the only Version 1 API prefix.
* [ ] Existing Laravel routing structure for the installed version is respected.
* [ ] Public routes are separated from authenticated/protected routes.
* [ ] `/me` routes have a clean authenticated middleware boundary.
* [ ] Operational routes use the frozen canonical paths.
* [ ] Removed `/staff/...` aliases are not implemented.
* [ ] Admin routes have a clear Admin-only middleware/policy attachment point.
* [ ] Product/category routes match the frozen endpoint inventory.
* [ ] Variant route structure matches the frozen contract.
* [ ] Cart routes preserve guest-cart support where required.
* [ ] Guest-cart token transport remains compatible with `X-Guest-Cart-Id`.
* [ ] Order customer routes are distinct from operational routes.
* [ ] Order state actions are not represented as generic status PATCH routes.
* [ ] Inventory uses the canonical `/inventory/{product}/adjust` route.
* [ ] Request/enquiry routes use the canonical non-Staff-prefixed routes.
* [ ] Attachment routes preserve the scoped upload-token boundary.
* [ ] Notification route structure matches the frozen contract.
* [ ] Route model binding does not bypass later ownership authorization.
* [ ] Public catalog routes are not forced through authentication.
* [ ] Route names are unique and consistent.
* [ ] No business logic has been placed in route declarations.
* [ ] Middleware attachment points exist for authentication, authorization, rate limiting, CSRF where applicable, and later request validation.
* [ ] Rate-limiter infrastructure is capable of returning `429` with standard `Retry-After`.
* [ ] Rate limiting does not expose internal limiter details.
* [ ] Future FormRequest/DTO implementation is explicitly required to use `validated()` and not `$request->all()`.
* [ ] Strict unknown-field rejection is preserved as the implementation requirement corresponding to `additionalProperties: false`.
* [ ] `php artisan route:list` passes/works.
* [ ] Existing tests pass, or any baseline failure is accurately documented.
* [ ] Route smoke tests cover public/protected/operational/Admin boundaries where the test framework is available.
* [ ] Laravel route coverage has been compared with OpenAPI.
* [ ] No Phase 2.7 exception system has been prematurely implemented.
* [ ] No Phase 2.9 health endpoint has been prematurely implemented.
* [ ] No domain/database implementation has been started.

---

# 63. STOP Condition

**STOP after the Laravel Version 1 routing topology is implemented and verified.**

Do not continue into:

* exception/error handling
* custom API error middleware
* logging architecture
* health/status implementation
* authentication implementation
* FormRequests
* DTOs
* models
* migrations
* policies
* business services
* inventory logic
* order logic
* payment logic

The next implementation phase is:

**Phase 2.7 — Exception/Error Handling Foundation.**

Phase 2.6 must leave Laravel with a clean, canonical, security-aware routing foundation that the subsequent phases can build upon without changing the frozen API surface.
