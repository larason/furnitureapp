# Phase 1.32 — OpenAPI Contract Specification

## 1. Purpose

Create the formal, machine-readable **Version 1 OpenAPI specification** for the furniture ecommerce API.

The OpenAPI document becomes the canonical machine-readable representation of the API contract established in Phases 1.16–1.31.

It must describe:

* API metadata
* servers
* authentication
* public endpoints
* customer endpoints
* Staff endpoints
* Admin endpoints
* parameters
* request bodies
* response bodies
* reusable schemas
* enums
* validation constraints
* error responses
* security requirements
* pagination
* examples
* idempotency requirements
* documented state transitions
* authoritative money fields
* authorization expectations

The specification must be suitable for:

* API documentation generation
* API client generation
* contract testing
* schema validation
* backend implementation reference
* Next.js integration
* Flutter integration

OpenAPI 3.2.0 is the current published specification version as of this phase. OAS 3.1.x remains valid, but do not downgrade merely for familiarity; instead verify that the project's selected tooling supports the chosen version before freezing the file.

---

# 2. Dependencies

Treat the following as authoritative:

* Phase 1.16 — API Error Contract
* Phase 1.17 — Authentication Contract
* Phase 1.18 — Authorization and Permission Contract
* Phase 1.19 — Concrete API Endpoint Inventory
* Phase 1.20 — Catalog API Contract
* Phase 1.21 — Cart API Contract
* Phase 1.22 — Checkout API Contract
* Phase 1.23 — Order API Contract
* Phase 1.24 — Order Tracking and Fulfillment API Contract
* Phase 1.25 — Made-to-Order Request API Contract
* Phase 1.26 — General Enquiry API Contract
* Phase 1.27 — Notification API Contract
* Phase 1.28 — User/Profile API Contract
* Phase 1.29 — Staff/Admin Operational API Contract
* Phase 1.30 — Cross-Domain API Contract Review
* Phase 1.31 — Canonical API Examples
* Placeholder endpoint resolution from the previous contract correction

Also inspect:

```text
AGENTS.md
docs/VISION.md
docs/api/api-contract.md
docs/api/api-resources.md
docs/api/api-conventions.md
docs/domain/business-rules.md
docs/decisions.md
```

Do not invent API behavior while writing OpenAPI.

If the specification cannot express an approved contract cleanly, resolve the contract issue before completing this phase.

---

# 3. Authoritative Relationship

The contract hierarchy is:

```text
Business Rules
      ↓
API Contract
      ↓
Canonical API Examples
      ↓
OpenAPI Specification
      ↓
Implementation
```

OpenAPI does not redefine business rules.

It formalizes the contract already approved.

If OpenAPI and the written contract disagree, **do not silently choose the OpenAPI version**.

Resolve the contradiction in the authoritative documentation first.

---

# 4. Output File

Create:

```text
docs/api/openapi.yaml
```

Use YAML.

Do not create:

```text
docs/api/openapi.json
docs/api/swagger.yaml
docs/api/swagger.json
```

unless a tooling requirement is explicitly recorded.

`openapi.yaml` is the canonical machine-readable API specification.

---

# 5. OpenAPI Version

Set the document's `openapi` field to the selected OpenAPI version.

Preferred:

```yaml
openapi: 3.2.0
```

Before freezing, verify that the repository's intended validation/documentation/code-generation tooling supports this version.

If the project's tooling cannot reliably consume 3.2.0, document the compatibility constraint in `docs/decisions.md` and select the newest supported specification version rather than allowing an invalid or misleading document.

Do not use an undocumented mixture of OAS 3.0 and OAS 3.1/3.2 features.

---

# 6. API Metadata

Define an `info` object containing:

* API title
* meaningful API description
* API contract version
* project version where appropriate

Do not put implementation-specific details in `info`.

The description should make clear that:

* this is Version 1
* the API supports the website and Flutter clients
* public catalog browsing is available
* checkout requires authentication
* Staff/Admin operational APIs are protected

---

# 7. Server Definition

Use an example/documentation server:

```yaml
servers:
  - url: https://api.example.com/api/v1
```

Do not hard-code a production infrastructure URL unless one has explicitly been approved.

Keep:

```text
/api/v1
```

consistent throughout.

Do not repeat `/api/v1` in server URLs and then accidentally duplicate it in paths.

Correct:

```yaml
servers:
  - url: https://api.example.com/api/v1

paths:
  /products:
```

Incorrect:

```yaml
servers:
  - url: https://api.example.com/api/v1

paths:
  /api/v1/products:
```

---

# 8. Tags

Create stable domain tags.

Recommended tags:

```text
Authentication
Users
Catalog
Cart
Checkout
Orders
Tracking
Requests
Enquiries
Notifications
Inventory
Staff Operations
Admin
Audit
```

Keep tag names consistent with the API contract documentation.

Do not create dozens of endpoint-specific tags.

---

# 9. Security Schemes

Define the API authentication mechanism from Phase 1.17.

If the approved scheme is bearer authentication, define an appropriate HTTP bearer security scheme.

Conceptually:

```yaml
components:
  securitySchemes:
    bearerAuth:
      type: http
      scheme: bearer
```

Use the exact authentication mechanism approved by Phase 1.17.

Do not invent OAuth flows, refresh-token behavior, API keys, or cookie authentication if those are not part of the approved contract.

---

# 10. Global Security Rule

Do not put:

```yaml
security:
  - bearerAuth: []
```

on the root document if doing so would incorrectly make public catalog endpoints authenticated.

Instead apply security at the operation level where public and protected endpoints differ.

Public:

```yaml
security: []
```

Authenticated:

```yaml
security:
  - bearerAuth: []
```

This distinction is mandatory.

---

# 11. Path Inventory

Every canonical endpoint from Phases 1.19 and the placeholder-resolution update must appear exactly once.

At minimum reconcile the paths for:

```text
/auth/register
/auth/login
/auth/logout

/me
/me/cart
/me/cart/items
/me/cart/items/{item}

/checkout

/products
/products/{product}
/categories

/me/orders
/me/orders/{order}
/me/orders/{order}/cancel
/me/orders/{order}/tracking

/staff/orders
/staff/orders/{order}
/staff/orders/{order}/accept
/staff/orders/{order}/start-processing
/staff/orders/{order}/set-delivery-fee
/staff/orders/{order}/ready-for-pickup
/staff/orders/{order}/complete-pickup
/staff/orders/{order}/ship
/staff/orders/{order}/deliver
/staff/orders/{order}/complete

/staff/inventory
/staff/inventory/{inventory}
/staff/inventory/{inventory}/adjust

/staff/products
/staff/products/{product}

/requests
/me/requests
/me/requests/{request}
/staff/requests
/staff/requests/{request}

/enquiries
/me/enquiries
/me/enquiries/{enquiry}
/staff/enquiries
/staff/enquiries/{enquiry}

/me/notifications
/staff/notifications

/admin/staff
/admin/staff/{user}
/admin/staff/{user}/approve
/admin/staff/{user}/suspend
/admin/staff/{user}/reactivate

/admin/audit-logs
```

Only include a path when its corresponding endpoint has actually been approved.

Do not use placeholder paths.

---

# 12. Path Parameter Rule

Every `{parameter}` appearing in a path must have a corresponding OpenAPI path parameter definition.

For example:

```yaml
/orders/{order}
```

must declare:

```yaml
- name: order
  in: path
  required: true
```

OpenAPI requires path template expressions to correspond to path parameters.

Do not define a path parameter as optional.

---

# 13. Parameter Naming

Use one canonical parameter name for each resource concept:

```text
product
category
item
order
request
enquiry
notification
user
inventory
auditLog
```

Do not alternate between:

```text
orderId
order_id
id
order
```

for the same path concept unless the API contract explicitly requires it.

---

# 14. Schema Architecture

Define reusable schemas under:

```yaml
components:
  schemas:
```

Do not duplicate large schemas inline across dozens of endpoints.

Create reusable schemas for major resources and shared concepts.

At minimum consider:

```text
User
CustomerProfile
StaffProfile
Product
ProductSummary
Category
Variant
Cart
CartItem
CheckoutRequest
Order
OrderItem
Tracking
MadeToOrderRequest
Enquiry
Notification
Inventory
Error
ErrorItem
Pagination
Money
AddressSnapshot
```

Use separate request schemas where mutability differs from response schemas.

---

# 15. Request/Response Separation

Do not use one giant resource schema for every operation.

For example:

```text
UserResponse
UpdateMeRequest
OrderResponse
CheckoutRequest
SetDeliveryFeeRequest
InventoryAdjustmentRequest
```

This prevents server-controlled fields from accidentally becoming writable.

A field returned by the server does not automatically belong in the request schema.

---

# 16. Required and Read-Only Fields

Use OpenAPI schema constraints to reflect the contract.

Fields that are server-generated or immutable should be marked appropriately.

Where applicable use:

```yaml
readOnly: true
```

Examples include:

* IDs
* order reference
* timestamps
* calculated totals
* server-controlled status
* approval metadata
* historical order values

Do not rely on `readOnly` alone for security. Backend authorization and validation remain mandatory.

---

# 17. Closed Enums

Every Version 1 enum must be represented explicitly.

Examples:

```yaml
enum:
  - CUSTOMER
  - STAFF
  - ADMIN
```

and:

```yaml
enum:
  - PICKUP
  - DELIVERY
```

and:

```yaml
enum:
  - IN_STOCK
  - MADE_TO_ORDER
```

Do not add an unspecified fallback such as:

```text
OTHER
UNKNOWN
CUSTOM
```

unless the written contract explicitly includes it.

This OpenAPI file must reflect the project's CLOSED Version 1 enum policy.

---

# 18. Order Status Schema

Define the approved Order status enum exactly as established by the cross-domain review.

Do not create alternate terms merely because an OpenAPI example could appear easier to read.

The specification must describe the same state vocabulary used by:

* Order API
* Tracking API
* Staff operations
* Notifications
* examples
* future tests

---

# 19. Fulfillment Type Schema

Define:

```yaml
enum:
  - PICKUP
  - DELIVERY
```

Use this same schema anywhere the concept appears.

Do not duplicate the enum manually in multiple places with the risk of drift.

---

# 20. Product Type Schema

Define the approved product type enum:

```yaml
enum:
  - IN_STOCK
  - MADE_TO_ORDER
```

Use the shared schema wherever product type appears.

---

# 21. Money Schema

Create one reusable money representation.

It must agree with `docs/api/api-conventions.md`.

If the approved representation is integer minor units:

```yaml
Money:
  type: object
  required:
    - amount
    - currency
  properties:
    amount:
      type: integer
    currency:
      type: string
```

Use the project's actual approved constraints.

Do not mix decimal and integer monetary representation.

Do not use floating-point number types for financial amounts.

---

# 22. Order Financial Schemas

Define the financial fields exactly once conceptually:

```text
subtotal
delivery_fee
total
currency
```

Ensure the schema communicates:

```text
total = subtotal + delivery_fee
```

through documentation/description and examples.

Do not attempt to encode arbitrary financial business logic using a JSON Schema expression if the implementation must calculate it.

The API server remains authoritative.

---

# 23. Delivery Fee Representation

Reflect the approved delivery-fee workflow.

For delivery:

```text
delivery_fee
```

may initially be unresolved according to the approved order lifecycle.

The OpenAPI schema must correctly represent its nullability/availability state.

For pickup:

```text
delivery_fee = 0
```

must remain a documented business invariant.

Do not make the delivery fee customer-writable merely because it exists in an Order response.

---

# 24. Address Schema

Create the canonical delivery-address schema based on the approved Checkout/Order contract.

Distinguish:

```text
checkout delivery address input
```

from:

```text
historical order delivery-address snapshot
```

Do not make existing order address snapshots writable.

Saved customer address-book behavior remains deferred.

---

# 25. Error Schemas

Define:

```text
ErrorResponse
ErrorItem
```

based on Phase 1.16.

Canonical structure (wrapper name is ErrorResponse — use this name consistently in the schema list, definition, and all $ref values):

```yaml
ErrorResponse:
  type: object
  required:
    - errors
  properties:
    errors:
      type: array
      items:
        $ref: '#/components/schemas/ErrorItem'
```

The `ErrorItem` schema should represent the approved fields.

Optional fields such as:

* details
* field path
* request ID/meta

must only be included if they are part of the approved contract.

---

# 26. Error Responses

Reuse standard response components where practical.

For example:

```yaml
components:
  responses:
    Unauthorized:
    Forbidden:
    NotFound:
    Conflict:
    ValidationError:
    RateLimited:
    InternalServerError:
```

Ensure each response points to the standard error schema.

Do not define a unique error object for each endpoint unless the contract genuinely requires it.

---

# 27. HTTP Response Codes

Every operation must document its relevant response codes.

At minimum use the contract's approved categories:

```text
200
201
204
401
403
404
409
422
429
500
```

Only include a status code where it is actually meaningful for that operation.

Do not add every possible code to every endpoint.

---

# 28. Authentication Endpoints

Define the complete request/response schemas for:

```text
POST /auth/register
POST /auth/login
POST /auth/logout
```

Registration must not permit client-controlled roles or permissions.

Login must not expose passwords.

Logout must follow the authentication contract's actual token/session invalidation behavior without inventing client-visible internals.

---

# 29. User `/me` Endpoints

Define:

```text
GET /me
PATCH /me
```

The response schema must expose only approved user/profile fields.

The update request must not permit:

```text
role
permissions
account status
approval state
security internals
```

Do not make the OpenAPI schema appear to permit such fields merely because the response contains role information.

---

# 30. Public Catalog Endpoints

Define:

```text
GET /products
GET /products/{product}
GET /categories
```

using the exact endpoint inventory.

Document:

* public access
* pagination
* allowed filters
* allowed sorting
* public representation
* availability semantics

Do not expose internal Staff/Admin catalog fields through the public schema.

---

# 31. Cart Endpoints

Define:

```text
GET /me/cart
POST /me/cart/items
PATCH /me/cart/items/{item}
DELETE /me/cart/items/{item}
```

The OpenAPI request schemas must prevent:

* ownership input
* server-controlled pricing
* arbitrary stock changes
* unsupported product types

Where the cart endpoint rejects `MADE_TO_ORDER`, document that through the endpoint description/error behavior rather than weakening the product enum.

---

# 32. Checkout Endpoint

Define:

```text
POST /checkout
```

The request schema must represent:

* cart-driven checkout
* fulfillment type
* conditional delivery address
* approved optional/required fields
* idempotency mechanism

Do not permit:

```text
subtotal
delivery_fee
total
order_status
order_reference
```

as customer-controlled checkout fields.

---

# 33. Checkout Conditional Validation

OpenAPI should represent conditional requirements as accurately as practical.

For example:

```text
PICKUP
→ delivery address not required

DELIVERY
→ delivery address required
```

Use OpenAPI-supported schema composition such as `oneOf`/`anyOf` only when it improves correctness and remains understandable to tooling.

Do not create a complicated schema that is technically expressive but difficult for Laravel/Next.js/Flutter tooling to consume.

The written contract remains authoritative for business validation.

---

# 34. Idempotency Header

Where checkout and other writes use the approved idempotency mechanism, document the header as a parameter or header component.

For example:

```yaml
- name: Idempotency-Key
  in: header
  required: true
```

Only mark it required on endpoints where Phase 1.30 established that requirement.

Do not globally require it for every API operation without authorization from the contract.

---

# 35. Customer Order Endpoints

Define:

```text
GET /me/orders
GET /me/orders/{order}
POST /me/orders/{order}/cancel
GET /me/orders/{order}/tracking
```

The OpenAPI descriptions must communicate:

* own-resource authorization
* order reference
* financial fields
* fulfillment information
* cancellation constraints
* tracking semantics

Do not encode the 20-minute cancellation window as merely a client-side description.

The server is authoritative.

---

# 36. Staff Order Endpoints

Define:

```text
GET /staff/orders
GET /staff/orders/{order}
POST /staff/orders/{order}/accept
POST /staff/orders/{order}/start-processing
POST /staff/orders/{order}/set-delivery-fee
POST /staff/orders/{order}/ready-for-pickup
POST /staff/orders/{order}/complete-pickup
POST /staff/orders/{order}/ship
POST /staff/orders/{order}/deliver
POST /staff/orders/{order}/complete
```

Each operation must document:

* STAFF authorization
* ADMIN authorization where applicable
* valid current states
* fulfillment constraints
* request body
* response state
* conflicts
* audit side effect

Do not create one generic status-update operation.

---

# 37. Delivery Fee Operation

Define the dedicated delivery-fee operation.

Its request schema must contain only the approved delivery-fee input.

Do not include:

```text
subtotal
total
currency
order status
```

as writable input unless the contract explicitly says so.

Response should expose the recalculated authoritative financial representation.

---

# 38. Inventory Endpoints

Define the exact inventory paths approved by the endpoint inventory.

For:

```text
POST /staff/inventory/{inventory}/adjust
```

define:

```text
quantity_delta
reason
```

or the exact approved request fields.

The schema must not allow arbitrary replacement of current stock.

The response must clearly represent server-calculated current inventory.

---

# 39. Staff Product Endpoints

Define the exact Staff product endpoints approved by the catalog contract.

For example:

```text
GET /staff/products
POST /staff/products
GET /staff/products/{product}
PATCH /staff/products/{product}
```

Do not automatically add:

```text
DELETE /staff/products/{product}
```

unless deletion is part of the approved contract.

For business-state changes such as publication, use dedicated actions where required.

---

# 40. Request Endpoints

Define:

```text
POST /requests
GET /me/requests
GET /me/requests/{request}
GET /staff/requests
GET /staff/requests/{request}
```

and any explicitly approved operational mutation route.

Distinguish:

```text
anonymous submission
```

from:

```text
authenticated customer ownership
```

Do not permit a client to assign another customer as owner.

---

# 41. Enquiry Endpoints

Define:

```text
POST /enquiries
GET /me/enquiries
GET /me/enquiries/{enquiry}
GET /staff/enquiries
GET /staff/enquiries/{enquiry}
```

plus only the approved operational mutation.

Keep original customer-submitted message fields read-only after creation.

---

# 42. Attachment References

Where attachments are represented in API payloads, define reusable attachment schemas.

The schema must not imply that files are public.

Do not place unrestricted storage URLs in examples/schema descriptions.

Use the protected access mechanism defined by the API contract.

---

# 43. Notification Endpoints

Define the exact approved paths for:

```text
GET /me/notifications
GET /staff/notifications
```

and the canonical mark-read operation selected by the contract.

The notification schema should include only approved fields.

Do not make notification recipient or source writable.

---

# 44. Admin Staff Endpoints

Define:

```text
GET /admin/staff
GET /admin/staff/{user}
POST /admin/staff
POST /admin/staff/{user}/approve
POST /admin/staff/{user}/suspend
POST /admin/staff/{user}/reactivate
```

only where each operation has been approved.

The schemas must distinguish:

```text
Staff creation data
Staff response
Staff lifecycle action
```

Do not allow client control of approval actor/time.

---

# 45. Audit Endpoints

Only expose:

```text
GET /admin/audit-logs
```

if the project approved an Admin audit read API.

Do not define mutation operations for audit logs.

If audit logs are internal-only, exclude them from the public API specification and document that decision.

---

# 46. Authorization Documentation

OpenAPI is not a complete authorization engine.

Nevertheless, every protected operation must clearly document its authorization expectation.

Use:

* `security`
* operation descriptions
* tags
* structured extensions only when genuinely useful

Do not invent proprietary OpenAPI extensions such as:

```yaml
x-role: STAFF
```

unless the project deliberately adopts and documents them.

If role metadata is needed for tooling, define one consistent extension convention in `docs/api/api-conventions.md`.

---

# 47. Authorization Matrix Consistency

The OpenAPI document must match the finalized permission matrix from Phase 1.30.

At minimum verify:

```text
CUSTOMER
STAFF
ADMIN
```

against every protected operation.

Particular attention:

* Staff cannot use Admin-only Staff management endpoints.
* Customer cannot access Staff resources.
* Customer cannot set delivery fee.
* Staff cannot alter customer roles/passwords.
* Admin can perform explicitly approved operational operations.

---

# 48. API Examples in OpenAPI

Migrate the canonical examples from Phase 1.31 into OpenAPI.

Use examples for:

* successful requests
* successful responses
* major error responses
* important business states

Do not invent a second version of an example.

Prefer references to reusable schemas and exact canonical example values.

---

# 49. Example Integrity

Every OpenAPI example must obey:

```text
example path
=
canonical path

example request
=
approved request schema

example response
=
approved response schema

example enums
=
closed enum values

example money
=
canonical money representation

example timestamps
=
canonical timestamp format
```

No stale Phase 1.31 placeholder path may remain.

---

# 50. Pagination Schema

Define one reusable pagination model.

Use it consistently for:

* products
* orders
* requests
* enquiries
* notifications
* inventory
* Staff lists
* Admin lists

The exact fields must match the global API convention.

Do not invent domain-specific pagination response structures.

---

# 51. Filtering and Sorting

Document approved query parameters.

For example:

```text
status
fulfillment_type
date range
search
sort
page
per_page
```

Only include fields explicitly approved by the relevant endpoint contract.

Use reusable parameter components for truly shared parameters.

Do not allow arbitrary field names through OpenAPI patterns unless the API contract intentionally supports them.

---

# 52. Content Types

Use the approved API media type.

For normal JSON endpoints this will generally be:

```text
application/json
```

Document multipart/form-data only where the attachment/upload contract explicitly requires it.

Do not introduce file-upload mechanics into OpenAPI merely because an attachment exists conceptually.

---

# 53. Response Headers

Document standard response headers only where the global contract defines them.

Potential examples include:

```text
Location
X-Request-ID
Retry-After
```

Do not invent response headers.

Where a rate-limit contract exists, represent its approved headers consistently.

---

# 54. Request ID / Correlation

If the API contract defines a request ID or correlation mechanism, include it consistently.

Document it in the relevant shared response/header components.

Do not create a different naming convention in OpenAPI.

---

# 55. Idempotency Documentation

For all endpoints requiring idempotency, OpenAPI must show the requirement.

At minimum review:

```text
POST /checkout
inventory adjustments
order state actions
delivery-fee action
Staff lifecycle actions
```

Only mark an operation idempotent when the business contract defines its behavior.

Do not label every POST as idempotent simply because it is convenient for documentation.

---

# 56. State Constraints

OpenAPI operation descriptions must document important state preconditions.

Example:

```text
POST /staff/orders/{order}/ship
```

must explain that the operation applies only to:

```text
DELIVERY
```

orders in the approved predecessor state.

OpenAPI schema validation alone is insufficient for state-machine enforcement.

The Laravel implementation must enforce these rules later.

---

# 57. Business Rules That OpenAPI Must Not Fake

Do not attempt to encode complex server behavior solely as schema validation.

Examples:

```text
20-minute cancellation window
inventory concurrency
order transition validity
delivery-fee mutability
authorization ownership
payment readiness
audit creation
notification side effects
```

Document them clearly in operation descriptions and preserve them in the authoritative contract.

The actual backend enforces them.

---

# 58. Operation IDs

Every operation must have a unique stable `operationId`.

Prefer using the existing endpoint IDs or a deterministic operation naming scheme that maps clearly to them.

For example:

```yaml
operationId: CAT-001
```

only if the endpoint inventory intentionally defines operation IDs that way.

Otherwise use descriptive stable identifiers such as:

```text
listProducts
getProduct
getMyCart
createCheckout
listStaffOrders
setOrderDeliveryFee
approveStaff
```

Do not have duplicate `operationId` values.

Do not casually rename an operation after clients begin consuming generated SDKs.

---

# 59. Endpoint ID Mapping

Preserve traceability from OpenAPI to the endpoint inventory.

Every operation should be identifiable back to the stable endpoint ID from Phase 1.19.

If `operationId` cannot itself carry the endpoint ID cleanly, document the mapping in the operation description or approved metadata convention.

Do not lose traceability between:

```text
Endpoint Inventory
→ API Contract
→ OpenAPI
→ future tests
```

---

# 60. Reusable Parameters

Define shared parameters under:

```yaml
components:
  parameters:
```

where useful.

Potential reusable parameters:

```text
OrderPath
ProductPath
RequestPath
EnquiryPath
UserPath
NotificationPath
InventoryPath
Page
PerPage
Sort
```

Do not over-componentize trivial one-off parameters.

---

# 61. Reusable Responses

Define shared standard responses under:

```yaml
components:
  responses:
```

At minimum consider:

```text
Unauthorized
Forbidden
NotFound
Conflict
ValidationError
RateLimited
InternalServerError
```

Use them consistently.

---

# 62. Description Quality

Operation descriptions should answer:

* who can call it
* what it does
* important state preconditions
* major business restrictions
* important side effects

Do not write implementation-specific descriptions such as:

```text
Calls OrderService::accept()
```

OpenAPI describes externally observable behavior, not Laravel class names.

---

# 63. No Database Leakage

Never expose:

* table names
* database columns that are internal-only
* foreign-key implementation details
* ORM class names
* SQL
* migration terminology

unless the field is genuinely part of the public contract.

---

# 64. No Framework Leakage

Do not document Laravel-specific behavior as part of the API contract.

Avoid describing:

```text
FormRequest
Policy
Eloquent
Middleware
Model
Observer
Job
```

The API contract must remain implementation-independent.

---

# 65. Validation Constraints

Use OpenAPI validation constraints where they genuinely reflect the approved contract.

Examples:

```yaml
type: integer
minimum: 1
```

for positive quantities where applicable.

Use:

* `minLength`
* `maxLength`
* `pattern`
* `minimum`
* `maximum`
* `format`
* `enum`
* `required`

only when those constraints have been approved.

Do not invent arbitrary limits.

---

# 66. String Formats

Use standard schema formats where they accurately represent the contract, such as:

```text
email
date-time
uri
uuid
```

Do not mark a value `uuid` unless the contract actually uses UUIDs.

Do not use `format: password` as a substitute for security rules.

---

# 67. Nullable Fields

Represent nullability explicitly.

Especially review:

```text
delivery_fee
delivery_address
read_at
approved_at
payment-related fields
request product association
enquiry order reference
```

Do not use optionality and nullability interchangeably.

An absent field and a present `null` value must mean the same thing everywhere they occur, unless explicitly defined otherwise.

---

# 68. Discriminator / Polymorphism

Use OpenAPI polymorphism only where the domain actually requires it.

Do not create unnecessarily complex `oneOf` structures for normal resources.

Potential legitimate use:

```text
PICKUP checkout payload
DELIVERY checkout payload
```

but only if it makes the approved conditional contract clearer and tooling remains compatible.

---

# 69. Deprecation

Do not mark any Version 1 endpoint deprecated without an approved decision.

If an endpoint is deprecated later, preserve the old contract long enough to define a migration path.

Phase 1.32 is a Version 1 specification, not a future compatibility exercise.

---

# 70. Group H Payment Boundary

Do not create payment gateway endpoints in this OpenAPI specification merely because Orders contain payment-related information.

Group A should expose only the approved order/payment relationship needed for later work.

Do not add:

```text
/payments
/payment-methods
/payment-webhooks
/refunds
/captures
```

unless they are explicitly part of the current approved Group A contract.

Group H owns payment implementation.

---

# 71. Email/Push Boundary

Do not create OpenAPI endpoints for:

```text
email delivery
push notifications
SMS delivery
```

as part of this phase.

In-app notifications remain represented according to Phase 1.27.

Real outbound delivery remains deferred to Group R.

---

# 72. OpenAPI Validation

After creating the file, validate it using an OpenAPI-compliant validator that supports the selected specification version.

The validation must catch at minimum:

* invalid YAML
* malformed OpenAPI structure
* missing `paths`
* missing required operation fields
* unresolved `$ref`
* invalid schema references
* missing path parameters
* duplicate operation IDs
* invalid response definitions
* unsupported specification features
* inconsistent component references

Do not declare the phase complete if validation fails.

---

# 73. Reference Integrity

Perform a full `$ref` audit.

Every reference must resolve.

Avoid circular references that are not necessary.

If circular relationships are required for resource schemas, confirm that the chosen tooling handles them correctly.

Do not create reference chains that make the specification unnecessarily difficult to consume.

---

# 74. Schema Consistency Validation

Validate that examples conform to their schemas.

At minimum test:

* request examples
* response examples
* error examples
* money objects
* pagination objects
* order objects
* checkout objects
* notification objects

An example that contradicts the schema is a contract defect.

---

# 75. Endpoint Coverage Validation

Build a coverage comparison:

```text
Endpoint Inventory
        ↕
OpenAPI paths
```

Every approved endpoint must appear.

Every OpenAPI operation must correspond to an approved endpoint.

Report:

```text
missing endpoints
extra endpoints
wrong methods
wrong paths
wrong operation IDs
```

No unexplained differences are allowed.

---

# 76. Contract-to-OpenAPI Comparison

Compare:

```text
api-contract.md
api-resources.md
api-conventions.md
openapi.yaml
```

for:

* field names
* types
* required fields
* enums
* nullability
* status codes
* paths
* methods
* authorization
* examples

OpenAPI must not become a separate interpretation of the API.

---

# 77. Security Review of OpenAPI

Inspect the generated specification as if it were published publicly.

Ensure it does not disclose:

* secrets
* internal infrastructure
* database schema
* private customer data
* undocumented Admin capabilities
* unsupported endpoints
* internal service names
* authentication implementation secrets

Documented schemas may still reveal that a field exists; that is acceptable only where the field is legitimately part of the corresponding actor's response.

---

# 78. Customer Privacy Review

Check every customer-facing schema for accidental inclusion of Staff/Admin-only fields.

Particular attention:

```text
User
Order
Request
Enquiry
Notification
Product
```

A field's presence in an internal schema must not automatically make it appear in a public schema.

Use separate schemas where visibility differs.

---

# 79. Staff Privacy Review

Verify that Staff order/request/enquiry schemas expose only the customer information required for operational work.

Do not accidentally expose:

* customer authentication details
* unrelated order history
* unrelated requests
* unrelated enquiries
* hidden account-management fields

---

# 80. Admin Exposure Review

The OpenAPI document will make Admin capabilities discoverable.

Therefore do not document an Admin endpoint that has not been deliberately approved.

"Admin can technically do this" is not enough.

Every Admin endpoint must have an explicit business purpose and authorization rule.

---

# 81. Documentation Links

Where appropriate, connect OpenAPI operation descriptions to the relevant domain contract sections.

Avoid external links that point to private/local paths unavailable to API consumers.

OpenAPI should remain portable.

---

# 82. Formatting and YAML Quality

Keep the YAML human-readable.

Use:

* consistent indentation
* logical tag grouping
* stable ordering
* reusable components
* concise descriptions
* quoted strings only where YAML requires/disambiguates them

Do not generate unreadable machine-minified YAML.

---

# 83. Repository Validation Commands

Add or use the repository's approved validation command.

The command should validate:

```text
docs/api/openapi.yaml
```

against the selected OpenAPI version.

If the repository already has API validation tooling, use it rather than introducing a second competing validation stack.

Record the command in `docs/api/api-conventions.md` or `AGENTS.md` only if it is intended as a permanent developer workflow.

---

# 84. Generated Documentation Check

If a documentation renderer is available, render the OpenAPI specification and inspect:

* paths
* tags
* schemas
* examples
* authentication
* error responses
* request bodies
* role boundaries

Do not treat successful YAML parsing as proof that the API contract is correct.

---

# 85. Generated Client Check

If repository tooling supports SDK generation, perform a lightweight generation test if practical.

The purpose is to catch:

* invalid schemas
* unsupported constructs
* duplicate operation IDs
* confusing nullable types
* malformed path parameters

Do not start building the production client libraries in this phase.

---

# 86. Canonical Example Coverage

Ensure the OpenAPI document includes examples for the major scenarios from Phase 1.31:

```text
public catalog
customer registration/login
/me
cart
pickup checkout
delivery checkout
customer order
cancellation
tracking
Staff order operations
delivery fee
inventory adjustment
made-to-order request
enquiry
notifications
Staff approval
authorization failure
validation failure
conflict
```

Avoid duplicating identical examples across every response.

Use the canonical example once and reference/reuse it where appropriate.

---

# 87. Required OpenAPI Components

Before completion, review the `components` section for at least:

```text
schemas
securitySchemes
responses
parameters
examples
```

Use only sections that are actually needed.

Do not create empty component groups merely for visual symmetry.

---

# 88. Required OpenAPI Invariants

The resulting specification must preserve these invariants:

```text
1. Public catalog is publicly readable.
2. Checkout is authenticated.
3. Customer private resources are ownership-scoped.
4. Staff operational routes are protected.
5. Admin-only routes are protected.
6. Staff cannot administer customer accounts through ordinary Staff APIs.
7. Roles remain CUSTOMER, STAFF, ADMIN.
8. V1 enums remain CLOSED.
9. Order status is server-controlled.
10. Customer cannot set delivery fee.
11. Pickup delivery fee is zero.
12. Staff/Admin may set delivery fee when allowed.
13. Total is server-authoritative.
14. Historical order values are immutable.
15. Made-to-order requests do not automatically become orders.
16. Notifications are downstream from business state.
17. Group H payment remains outside Group A implementation scope.
18. Group R email/push delivery remains outside this specification.
```

---

# 89. Documentation Synchronization

After creating `openapi.yaml`, review:

```text
docs/api/api-contract.md
docs/api/api-resources.md
docs/api/api-conventions.md
docs/domain/business-rules.md
docs/decisions.md
```

against it.

The written documentation should now say:

```text
OpenAPI specification:
docs/api/openapi.yaml
```

and treat the OpenAPI file as the machine-readable representation of the same contract.

Do not create contradictory "simplified" API documentation.

---

# 90. Changes Requiring Return to Earlier Phases

If creating OpenAPI reveals that the contract lacks a required answer, return to the relevant phase.

Examples:

```text
Missing error code
→ Phase 1.16

Unclear authentication behavior
→ Phase 1.17

Unclear Staff/Admin authorization
→ Phase 1.18 / 1.29

Undefined endpoint
→ Phase 1.19

Missing resource shape
→ relevant domain phase

Contradictory state
→ Phase 1.30

Missing example
→ Phase 1.31
```

Do not "solve" a business-rule ambiguity inside OpenAPI.

---

# 91. Required Definition of Done

Phase 1.32 is complete only when:

* [ ] `docs/api/openapi.yaml` exists.
* [ ] The selected OpenAPI version is explicitly declared.
* [ ] The chosen version is supported by the project's validation/documentation tooling.
* [ ] API metadata is complete.
* [ ] Server configuration is consistent with `/api/v1`.
* [ ] Tags are defined consistently.
* [ ] Authentication security scheme is defined.
* [ ] Public endpoints correctly omit authentication.
* [ ] Protected endpoints correctly require authentication.
* [ ] All approved endpoint paths are represented.
* [ ] No placeholder endpoint paths remain.
* [ ] Every path parameter is formally declared.
* [ ] Every operation has a unique stable `operationId`.
* [ ] Endpoint-to-inventory traceability is preserved.
* [ ] Request schemas are separated from response schemas where required.
* [ ] Server-controlled fields are read-only/non-writable in request schemas.
* [ ] CLOSED Version 1 enums are represented exactly.
* [ ] Money representation is consistent.
* [ ] Date/time representation is consistent.
* [ ] Nullability is explicit.
* [ ] Pagination is consistent.
* [ ] Filtering/sorting parameters are consistent.
* [ ] Idempotency headers are documented where required.
* [ ] Error schema matches Phase 1.16.
* [ ] Standard HTTP error responses are represented consistently.
* [ ] Canonical examples from Phase 1.31 have been incorporated.
* [ ] Examples validate against their schemas.
* [ ] Customer privacy boundaries are preserved.
* [ ] Staff/Admin boundaries are preserved.
* [ ] Delivery-fee workflow is represented correctly.
* [ ] Order state constraints are documented.
* [ ] Payment remains outside Group A implementation scope.
* [ ] Email/push delivery remains outside Group A scope.
* [ ] All `$ref` references resolve.
* [ ] OpenAPI validation passes.
* [ ] No duplicate `operationId` exists.
* [ ] No extra undocumented endpoint exists.
* [ ] No approved endpoint is missing.
* [ ] Written API documentation agrees with OpenAPI.
* [ ] No implementation code has been started.

---

# 92. STOP Condition

**STOP after `docs/api/openapi.yaml` validates successfully and is synchronized with the complete Version 1 API contract.**

Do not begin:

* Laravel routing
* Laravel controllers
* Eloquent models
* migrations
* Form Requests
* policies
* frontend API clients
* Flutter API clients
* payment integration
* notification delivery
* production API deployment

The OpenAPI file is now the machine-readable contract that later implementation phases must consume.

**Next phase: 1.33 — API Contract Security Review.**
