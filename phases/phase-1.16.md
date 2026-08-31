# Phase 1.16 — Define API Error Contract

## 1. Purpose

Phase 1.16 establishes the project's **formal API error contract**.

The goal is to ensure that all API consumers receive failures in a predictable, machine-readable, secure, and version-compatible format.

The contract must work consistently for:

```text
Next.js Website
Flutter Mobile App
Admin Application
Future API Clients
```

This phase defines:

* error envelope;
* error object structure;
* stable error codes;
* human-readable messages;
* validation errors;
* authentication errors;
* authorization errors;
* not-found behavior;
* business-rule failures;
* conflict errors;
* concurrency failures;
* external-service failures;
* field-level validation errors;
* request/correlation IDs;
* HTTP status mapping;
* retry guidance;
* security and information-disclosure rules;
* compatibility requirements.

This phase does **not** implement the error handling in Laravel.

---

# 2. Documentation Strategy

Continue using the consolidated documentation structure.

Do **not** create permanent files such as:

```text
api-errors.md
error-contract.md
validation-errors.md
http-errors.md
```

unless the project later demonstrates a genuine need for a separate document.

Update:

```text
docs/api/api-contract.md
docs/api/api-conventions.md
docs/api/api-resources.md
docs/domain/business-rules.md
docs/decisions.md
AGENTS.md
```

Only modify `AGENTS.md` if an error-handling rule belongs in the project's permanent development instructions.

---

# 3. Authoritative Project Paths

The authoritative project documents are:

```text
AGENTS.md
docs/VISION.md
```

Do not use the obsolete names:

```text
agent.md
VISION.md
```

---

# 4. Payment Assignment

Payment-specific error behavior remains assigned to:

**Phase Group H**

not Group G.

This phase may define generic external-service and payment error principles, but must not invent provider-specific payment error codes or workflows.

---

# 5. Version 1 Enum Policy

All Version 1 enums are:

> **CLOSED by default.**

This also applies if an error code or error category is represented using an enumeration.

Do not reintroduce the previous `OPEN/EXTENSIBLE` interpretation.

---

# 6. Dependency Position

The current sequence is:

```text
1.1  API Scope
       ↓
1.2  Domain Nouns
       ↓
1.3  Domain Invariants
       ↓
1.4  Logical Data Model
       ↓
1.5  Logical Model Review + Freeze
       ↓
1.6  API Resource Inventory
       ↓
1.7  API Resource Relationships
       ↓
1.8  API Versioning
       ↓
1.9  Endpoint Naming
       ↓
1.10 HTTP Methods
       ↓
1.11 Query Parameters
       ↓
1.12 Pagination
       ↓
1.13 Response Representation
       ↓
1.14 Request/Input Conventions
       ↓
1.15 Validation Conventions
       ↓
1.16 API Error Contract
       ↓
1.17 Authentication Contract
       ↓
...
```

Do not use this phase to redesign validation or domain invariants.

---

# 7. Authoritative Inputs

Read:

```text
AGENTS.md
docs/VISION.md

docs/api/api-contract.md
docs/api/api-conventions.md
docs/api/api-resources.md
docs/domain/business-rules.md
docs/decisions.md
```

Also review the completed Phase 1.1–1.15 work, especially:

```text
API scope
domain invariants
HTTP methods
query conventions
pagination
response envelope
request conventions
validation conventions
API versioning
```

---

# 8. Core Error Principle

An API error is part of the public API contract.

Clients must not need to parse human-readable messages to determine what happened.

Use:

```text
stable machine-readable error code
+
human-readable message
+
optional structured details
```

The machine-readable code is authoritative.

---

# 9. Standard Error Envelope

Choose one project-wide structure.

**Mandatory envelope (fixed, not baseline — §72 ADR-ERR-001):**

```json
{
  "errors": [
    {
      "code": "PRODUCT_NOT_PURCHASABLE",
      "message": "This product cannot be purchased."
    }
  ]
}
```

All API errors **MUST** use the same top-level (CLOSED, mandatory):

```text
errors
```

Do not alternate between:

```text
error
errors
message
errors.message
failure
```

---

# 10. Why Use an Error Array

Use an array even when there is only one error.

This supports:

```text
multiple validation errors
multiple field errors
future structured errors
```

Example:

```json
{
  "errors": [
    {
      "code": "VALIDATION_ERROR",
      "message": "The request contains invalid fields."
    },
    {
      "code": "INVALID_VALUE",
      "message": "The selected fulfillment type is invalid."
    }
  ]
}
```

However, do not return multiple unrelated business errors merely to make the array non-empty.

---

# 11. Standard Error Object

Define the conceptual fields (**request_id is NOT per-error — see §30, mandatory `meta.request_id`**):

```text
code
message
details
field
```

Only fields relevant to the particular error need to be present.

For example:

```json
{
  "errors": [
    {
      "code": "INVALID_VALUE",
      "message": "The selected fulfillment type is invalid.",
      "field": "fulfillment_type"
    }
  ]
}
```

Do not require every error to contain every field.

---

# 12. Machine-Readable Error Code

Every API error should have a stable `code`.

Examples:

```text
VALIDATION_ERROR
INVALID_VALUE
MISSING_REQUIRED_FIELD
AUTHENTICATION_REQUIRED
INVALID_CREDENTIALS
FORBIDDEN
RESOURCE_NOT_FOUND
PRODUCT_NOT_PURCHASABLE
INSUFFICIENT_STOCK
ORDER_NOT_CANCELLABLE
INVALID_ORDER_TRANSITION
CONFLICT
RATE_LIMITED
EXTERNAL_SERVICE_ERROR
INTERNAL_SERVER_ERROR
```

The list must remain controlled and documented.

Do not let Laravel exception class names become public error codes.

---

# 13. Error Code Naming Convention

Use:

```text
UPPER_SNAKE_CASE
```

Examples:

```text
AUTHENTICATION_REQUIRED
INSUFFICIENT_STOCK
ORDER_NOT_CANCELLABLE
INVALID_ORDER_TRANSITION
```

Avoid:

```text
authenticationRequired
insufficient-stock
InsufficientStock
```

---

# 14. Error Codes vs Messages

Do not use:

```text
message = "Not enough stock"
```

as the machine-readable contract.

Instead:

```text
code = INSUFFICIENT_STOCK
```

with:

```text
message = "The requested quantity is not available."
```

The message can evolve more safely than the code.

---

# 15. Error Message Requirements

Messages should be:

* clear;
* concise;
* safe;
* user/action oriented;
* free of internal implementation details.

Avoid:

```text
SQLSTATE[23000]...
```

or:

```text
Undefined array key...
```

or:

```text
Laravel Exception...
```

---

# 16. Error Message Localization

Do not make clients depend on an English message.

The stable contract is:

```text
code
```

The frontend may later map that code to localized UI copy.

Example:

```text
INSUFFICIENT_STOCK
```

may become:

```text
"Only 2 units are currently available."
```

in English or another supported language.

---

# 17. HTTP Status and Error Code Are Separate

Do not treat the error code as a replacement for HTTP status.

A response has:

```text
HTTP status
+
API error code
```

For example:

```text
409
CONFLICT
```

or:

```text
422
INVALID_VALUE
```

The exact mapping must be standardized in this phase.

---

# 18. Recommended HTTP Status Categories

Use standard HTTP semantics where practical.

### 400 — Bad Request

For malformed or otherwise invalid requests that cannot be processed as submitted.

Use sparingly when a more precise status is inappropriate.

### 401 — Unauthorized

For missing/invalid authentication.

Important:

> HTTP 401 means authentication is required/failed, not "permission denied."

### 403 — Forbidden

For authenticated clients lacking permission.

### 404 — Not Found

For a resource that is not available in the request context.

### 409 — Conflict

For a state/concurrency conflict.

### 422 — Unprocessable Content

For structurally valid input that fails validation/business processing where the project chooses 422.

### 429 — Too Many Requests

For rate limiting.

### 500 — Internal Server Error

For unexpected server-side failure.

### 502/503/504

For appropriate external/upstream service failures where the infrastructure/API contract requires exposing that distinction.

Do not assign every business error to 500.

---

# 19. Recommended Validation Status

Use:

```text
422
```

for well-formed requests that fail input/domain validation.

Examples:

```text
INVALID_VALUE
MISSING_REQUIRED_FIELD
INSUFFICIENT_STOCK
PRODUCT_NOT_PURCHASABLE
ORDER_NOT_CANCELLABLE
INVALID_ORDER_TRANSITION
```

However, distinguish validation from true resource-state conflicts where appropriate.

---

# 20. Recommended Authentication Status

Use:

```text
401
```

when authentication is missing or invalid.

Examples:

```text
AUTHENTICATION_REQUIRED
INVALID_AUTHENTICATION
```

Do not use 403 for a completely unauthenticated request where authentication is required.

---

# 21. Recommended Authorization Status

Use:

```text
403
```

when:

```text
authenticated
+
not authorized
```

Example:

```text
FORBIDDEN
```

Do not expose excessive details about why a private resource belongs to another customer.

---

# 22. Resource Not Found

Use:

```text
404
```

for resources that cannot be addressed in the current API context.

Example:

```text
RESOURCE_NOT_FOUND
```

The implementation may deliberately use a non-distinguishing response for ownership-sensitive resources to avoid information leakage.

---

# 23. Customer Ownership and 404/403 — Mandatory 404 Masking

For private customer-owned resources (Orders, customer carts, requests/enquiries owned by customer — per `api-contract.md §15.8`):

**MUST** return 404 masking, not 403, when the resource is not owned / not addressable in the caller’s context.

- `Customer A requests Customer B's order` → **MUST** return:

```text
404
RESOURCE_NOT_FOUND (or ORDER_NOT_FOUND)
```

from Customer A’s perspective — **never** `403 FORBIDDEN` which would reveal that the resource exists.

- `403 FORBIDDEN` remains only for authenticated-but-not-authorized **non-ownership** cases where existence is not sensitive (e.g., staff operation without permission), not for private ownership checks.

This is the **one mandatory rule** for private-resource existence: 404 masking prevents ownership enumeration. The behavior is fixed, not “may” or “consider”.

---

# 24. Resource Enumeration Protection

Do not allow an attacker to learn private identifiers simply by probing the API.

For example:

```text
GET /orders/1001
GET /orders/1002
GET /orders/1003
```

must not provide detailed differences that reveal another customer's orders.

Authorization and error behavior must work together.

---

# 25. Validation Error Structure

Field-level errors should be representable.

Example:

```json
{
  "errors": [
    {
      "code": "INVALID_VALUE",
      "message": "The selected fulfillment type is invalid.",
      "field": "fulfillment_type"
    }
  ]
}
```

For nested fields:

```text
delivery_address.city
```

must be representable.

Use one field-path convention across the API.

---

# 26. Multiple Field Errors

For ordinary schema validation, prefer returning all safely determinable errors.

Example:

```json
{
  "errors": [
    {
      "code": "MISSING_REQUIRED_FIELD",
      "message": "A name is required.",
      "field": "name"
    },
    {
      "code": "INVALID_VALUE",
      "message": "Quantity must be at least 1.",
      "field": "quantity"
    }
  ]
}
```

Do not stop at the first trivial field error if the remaining validation can safely be performed.

---

# 27. Business Error Details

Business errors may contain structured details when useful.

Example:

```json
{
  "errors": [
    {
      "code": "INSUFFICIENT_STOCK",
      "message": "The requested quantity is not available.",
      "details": {
        "requested_quantity": 5,
        "available_quantity": 2
      }
    }
  ]
}
```

Only expose information safe for the caller.

Do not expose internal stock-reservation IDs or staff data.

---

# 28. Sensitive Error Details

Never return:

```text
database SQL
stack traces
password information
authentication secrets
payment secrets
internal file paths
server IP details
framework exception names
```

in normal production API responses.

---

# 29. Internal Errors

Unexpected errors should produce a stable generic contract.

Example:

```json
{
  "errors": [
    {
      "code": "INTERNAL_SERVER_ERROR",
      "message": "An unexpected error occurred."
    }
  ]
}
```

Do not expose the underlying exception.

The detailed diagnostic should remain in secure server logs/monitoring.

---

# 30. Request ID — Mandatory Envelope Placement (`meta.request_id`)

Adopted contract (**normative, per `api-contract.md §15.13` / `api-conventions.md §17.4`):** The request/correlation identifier **MUST** be at envelope level **`meta.request_id`**, not per-error object.

Example (normative):

```json
{
  "errors": [
    {
      "code": "INTERNAL_SERVER_ERROR",
      "message": "An unexpected error occurred."
    }
  ],
  "meta": {
    "request_id": "..."
  }
}
```

**Not** per-error `request_id` inside `errors[]`. Placement is fixed as `meta.request_id` consistent with success `meta` (pagination, etc.).

---

# 31. Request ID Purpose

The request ID allows support staff to connect:

```text
customer-facing error
        ↓
server logs
        ↓
diagnostic event
```

Example customer support workflow:

> "Please provide your request ID."

The request ID must not contain sensitive information.

---

# 32. Request ID Stability

A request ID identifies one API request/processing context.

It must not be:

```text
user ID
order ID
payment ID
session token
```

unless explicitly designed as a separate identifier.

---

# 33. Retry Guidance

Some errors are retryable.

The API should conceptually distinguish:

```text
RETRYABLE
NOT_RETRYABLE
CONDITIONALLY_RETRYABLE
```

Do not necessarily put this field in every response.

The error contract should define which error classes are candidates for retry.

---

# 34. Examples of Retryability

Generally:

### Retryable

```text
temporary service unavailable
gateway timeout
transient upstream failure
```

### Not retryable without changing input/state

```text
INVALID_VALUE
MISSING_REQUIRED_FIELD
PRODUCT_NOT_PURCHASABLE
ORDER_NOT_CANCELLABLE
FORBIDDEN
```

### Requires re-read/reconciliation

```text
CONFLICT
INSUFFICIENT_STOCK
INVALID_ORDER_TRANSITION
```

The final client behavior remains application-specific.

---

# 35. Retry-After

For rate limiting:

```text
429
```

evaluate standard:

```text
Retry-After
```

The API should use standard HTTP mechanisms where appropriate rather than inventing:

```text
retry_after_seconds
wait
try_again
```

as arbitrary fields.

The exact representation can be documented here and implemented later.

---

# 36. Conflict Errors

Use `409 Conflict` when the request is structurally valid but cannot be completed because of current resource state or concurrency.

Potential examples:

```text
CONFLICT
ORDER_STATE_CONFLICT
RESOURCE_VERSION_CONFLICT
DUPLICATE_OPERATION
```

Do not use 409 for ordinary malformed field input.

---

# 37. Inventory Conflict

A stock race may produce a conflict.

Example:

```text
Customer believes:
stock = 1

Another customer purchases it

Customer submits checkout
```

The API may return:

```text
409
INSUFFICIENT_STOCK
```

or another approved business conflict status.

The exact mapping must be chosen consistently.

---

# 38. Idempotency Conflict

When an idempotent request key has already been used:

```text
duplicate request
```

the API must eventually define whether it:

* returns the original result;
* returns a conflict;
* returns an idempotency-specific error.

Do not implement the idempotency mechanism here.

Record that error semantics must be deterministic.

---

# 39. Unsupported Method

If a client uses an unsupported HTTP method:

```text
405 Method Not Allowed
```

should be used according to standard HTTP semantics.

The error body must still follow the project's error envelope.

Do not invent custom:

```text
METHOD_NOT_SUPPORTED_ERROR
```

unless useful as an additional API error code.

---

# 40. Unsupported Media Type

For an unsupported `Content-Type`:

```text
415 Unsupported Media Type
```

should be considered.

The error should still follow the standard envelope.

---

# 41. Malformed JSON

For invalid JSON syntax:

```text
400 Bad Request
```

is appropriate.

Use a stable error code such as:

```text
INVALID_JSON
```

Do not expose parser stack traces.

---

# 42. Request Size Exceeded

Where payload exceeds server-defined limits:

```text
413 Content Too Large
```

may be used.

Example API code:

```text
REQUEST_TOO_LARGE
```

Do not leak infrastructure-specific size configuration unless intentionally documented.

---

# 43. Rate Limiting

Use:

```text
429 Too Many Requests
```

where appropriate.

Potential API error:

```text
RATE_LIMITED
```

Do not expose internal rate-limit algorithms.

---

# 44. Business Errors Must Be Explicit

Do not collapse all business-rule failures into:

```text
VALIDATION_ERROR
```

when the client needs to respond differently.

Examples:

```text
PRODUCT_NOT_PURCHASABLE
INSUFFICIENT_STOCK
ORDER_NOT_CANCELLABLE
INVALID_ORDER_TRANSITION
```

This allows:

Next.js:

> Show availability warning.

Flutter:

> Refresh product/stock.

Admin:

> Investigate operational conflict.

---

# 45. Product Errors

Potential Version 1 codes:

```text
PRODUCT_NOT_FOUND
PRODUCT_NOT_PURCHASABLE
PRODUCT_UNAVAILABLE
INVALID_PRODUCT_VARIANT
```

Only create codes that correspond to real API behavior.

Do not create dozens of near-duplicate codes.

---

# 46. Cart Errors

Potential:

```text
CART_NOT_FOUND
CART_ITEM_NOT_FOUND
INVALID_CART_ITEM
CART_ITEM_UNAVAILABLE
```

Keep these distinct from Order errors.

---

# 47. Checkout Errors

Potential:

```text
CHECKOUT_REQUIRES_AUTHENTICATION
CHECKOUT_NOT_ALLOWED
CART_INVALID
INSUFFICIENT_STOCK
PRODUCT_NOT_PURCHASABLE
INVALID_FULFILLMENT
INVALID_DELIVERY_INFORMATION
```

The final code list must be aligned with actual endpoint contracts later.

---

# 48. Order Errors

Potential:

```text
ORDER_NOT_FOUND
ORDER_NOT_CANCELLABLE
INVALID_ORDER_TRANSITION
ORDER_STATE_CONFLICT
```

Do not invent codes for every imaginable order state.

---

# 49. Request Errors

Potential:

```text
REQUEST_NOT_FOUND
INVALID_REQUEST
REQUEST_NOT_ALLOWED
```

Only retain codes that are genuinely useful to clients.

---

# 50. Enquiry Errors

Potential:

```text
ENQUIRY_NOT_FOUND
INVALID_ENQUIRY
```

Again, keep the vocabulary concise.

---

# 51. Attachment Errors

Potential:

```text
INVALID_ATTACHMENT
ATTACHMENT_TOO_LARGE
UNSUPPORTED_ATTACHMENT_TYPE
ATTACHMENT_NOT_FOUND
```

Exact file-validation behavior is later.

---

# 52. Authentication Errors

Potential:

```text
AUTHENTICATION_REQUIRED
INVALID_CREDENTIALS
SESSION_EXPIRED
INVALID_AUTHENTICATION
```

Authentication contract details belong in Phase 1.17.

Do not finalize every authentication-specific code here if the next phase will refine them.

The important requirement is that authentication errors fit the common error contract.

---

# 53. Authorization Errors

The baseline code may be:

```text
FORBIDDEN
```

Avoid leaking whether a private resource exists.

---

# 54. Validation Errors

Use a small reusable vocabulary.

Potential:

```text
VALIDATION_ERROR
MISSING_REQUIRED_FIELD
INVALID_VALUE
INVALID_FORMAT
INVALID_TYPE
```

Field-level details identify the exact problem.

Do not create:

```text
NAME_MISSING
EMAIL_BAD
PHONE_BAD
QUANTITY_WRONG
```

unless there is a demonstrated reason.

---

# 55. Error Codes Should Be Stable

Once a Version 1 error code is public:

Do not casually rename:

```text
INSUFFICIENT_STOCK
```

to:

```text
NOT_ENOUGH_INVENTORY
```

because existing clients may depend on it.

Use the API versioning policy.

---

# 56. Error Codes Are Not Database Codes

Do not use:

```text
SQLSTATE_23000
MYSQL_DUPLICATE_ENTRY
```

as public API errors.

Database implementation can change without changing the API contract.

---

# 57. Error Codes Are Not Laravel Exceptions

Do not expose:

```text
ModelNotFoundException
ValidationException
AuthenticationException
```

as your public API vocabulary.

Map implementation exceptions into the stable API contract.

---

# 58. Error Code Namespaces

Consider whether the project needs namespaces.

For this application, likely no.

Prefer:

```text
INSUFFICIENT_STOCK
```

over:

```text
commerce.inventory.insufficient_stock
```

unless the number of error domains eventually becomes large enough to justify namespaces.

Keep Version 1 simple.

---

# 59. Error Details Schema

Define `details` as structured data only.

Good:

```json
"details": {
  "available_quantity": 2,
  "requested_quantity": 5
}
```

Bad:

```json
"details": "MySQL query failed at line 382."
```

Do not use `details` as a second message field.

---

# 60. Error Field Paths

Choose one field-path convention.

Recommended dot notation:

```text
delivery_address.city
delivery_address.phone
```

For arrays, evaluate a predictable notation such as:

```text
items.0.quantity
```

or another canonical representation.

Use one style across the API.

---

# 61. Error Details and Security

Even structured details can leak information.

Do not return:

```text
other_customer_id
internal_inventory_reservation_id
provider_secret
database_key
staff_only_note
```

unless explicitly authorized.

---

# 62. Error Response Metadata

Use `meta` only for API-level metadata.

Potential:

```json
{
  "errors": [],
  "meta": {
    "request_id": "..."
  }
}
```

Do not put business error objects inside `meta`.

---

# 63. Error vs Successful Response

The response contract must make it easy for clients to branch:

```text
HTTP success
→ data

HTTP error
→ errors
```

Do not return:

```json
{
  "success": false,
  "data": null,
  "message": "..."
}
```

as the project's primary error architecture unless explicitly chosen.

---

# 64. Partial Success

Evaluate whether Version 1 needs partial-success responses.

For core operations such as:

```text
checkout
order creation
payment
order cancellation
```

avoid ambiguous partial-success behavior.

The API should make the business result explicit.

For batch operations introduced later, a separate contract may be required.

Do not implement batch partial success now.

---

# 65. Error and Transactions

An error response must correspond to the actual business outcome.

For example:

```text
API returns:
INSUFFICIENT_STOCK
```

but inventory has actually been reserved.

That is unacceptable.

The error contract therefore depends on later transaction correctness.

Document the principle:

> Error reporting must accurately reflect whether the requested business operation committed or did not commit.

---

# 66. Error and Idempotency

If an idempotent request fails, repeated identical requests must produce consistent semantics.

Do not allow:

```text
first request → error
second identical request → creates order
```

simply because the implementation did not preserve idempotency state.

The exact mechanism is later.

---

# 67. Error and Concurrency

When concurrency causes an operation to fail:

```text
409
```

or the approved resource-specific status/code should communicate that the client may need to refresh and retry the business operation.

This is different from a permanently invalid request.

---

# 68. Error and Frontend UX

The API error contract should allow clients to decide whether to:

```text
show field error
show toast/snackbar
refresh resource
redirect to login
retry
contact support
```

without parsing human text.

Example:

```text
AUTHENTICATION_REQUIRED
→ redirect/login

INSUFFICIENT_STOCK
→ refresh stock UI

INVALID_VALUE
→ field-level error

INTERNAL_SERVER_ERROR
→ generic failure UI
```

---

# 69. Error Handling in Flutter

Flutter should be able to map:

```text
code
field
details
```

to appropriate UI behavior.

Do not make Flutter depend on Laravel exception names.

---

# 70. Error Handling in Next.js

Next.js server/client components should also use:

```text
code
```

rather than parsing:

```text
message
```

to determine behavior.

The website should remain compatible if human messages are updated.

---

# 71. Admin Error Handling

Admin UI may receive more operational information where authorized, but it must still use the same base error contract.

Do not create a completely separate admin error format.

---

# 72. Documentation Requirements

Update:

## `docs/api/api-contract.md`

Add:

```text
## Error Contract

### Error Envelope
### Error Object
### Error Codes
### HTTP Status Mapping
### Validation Errors
### Authentication Errors
### Authorization Errors
### Not Found
### Business Errors
### Conflict Errors
### Rate Limiting
### External Service Errors
### Request ID
### Retry Semantics
### Security Rules
### Compatibility
```

---

# 73. Update `docs/api/api-conventions.md`

Consolidate:

```text
error envelope
error code naming
messages
field paths
request IDs
status semantics
security
retry conventions
```

---

# 74. Update `docs/api/api-resources.md`

Where relevant, record resource-specific error categories.

Do not enumerate every possible error for every resource yet.

For example:

```text
Order
→ ownership/not-found
→ cancellation
→ state transition
```

---

# 75. Update `docs/domain/business-rules.md`

Ensure important business failures are represented as domain-level concepts.

For example:

```text
INSUFFICIENT_STOCK
ORDER_NOT_CANCELLABLE
INVALID_ORDER_TRANSITION
```

must correspond to real business rules already established.

Do not invent business behavior through error codes.

---

# 76. Update `docs/decisions.md`

Record major decisions such as:

```text
API-ERR-001
All errors use an `errors` array.

API-ERR-002
Every public API error has a stable machine-readable code.

API-ERR-003
HTTP status and API error code are separate concepts.

API-ERR-004
Production errors never expose implementation details.

API-ERR-005
Field-level validation errors use a canonical field-path format.

API-ERR-006
Version 1 error codes are stable and version-sensitive.
```

Use the project's established decision numbering scheme if different.

---

# 77. AGENTS.md Update

Only add a permanent rule if useful.

Recommended permanent engineering rule:

> Never expose raw framework, database, or infrastructure exceptions through API responses; map them to the documented API error contract.

---

# 78. Error Code Registry

Inside:

```text
docs/api/api-contract.md
```

create a controlled registry.

Example:

| Code                       | Meaning                     | Typical HTTP status | Client action         |
| -------------------------- | --------------------------- | ------------------: | --------------------- |
| `VALIDATION_ERROR`         | General validation failure  |                 422 | Correct input         |
| `INVALID_VALUE`            | Field value invalid         |                 422 | Correct input         |
| `AUTHENTICATION_REQUIRED`  | Authentication required     |                 401 | Authenticate          |
| `FORBIDDEN`                | Not permitted               |                 403 | Stop/adjust access    |
| `RESOURCE_NOT_FOUND`       | Resource unavailable        |                 404 | Reconcile             |
| `PRODUCT_NOT_PURCHASABLE`  | Product cannot be purchased |                 422 | Refresh/catalog       |
| `INSUFFICIENT_STOCK`       | Requested stock unavailable |            409/422* | Refresh/retry         |
| `ORDER_NOT_CANCELLABLE`    | Cancellation not allowed    |            422/409* | Stop                  |
| `INVALID_ORDER_TRANSITION` | State transition invalid    |            409/422* | Refresh/reconcile     |
| `RATE_LIMITED`             | Too many requests           |                 429 | Retry later           |
| `INTERNAL_SERVER_ERROR`    | Unexpected server failure   |                 500 | Retry/contact support |

`*` must be formally resolved for the individual business contract later.

Do not finalize resource-specific statuses prematurely if later endpoint work has not established the exact semantics.

---

# 79. Avoid Error-Code Explosion

Do not create hundreds of tiny codes.

Use a new code only when the client genuinely needs to behave differently.

For example:

```text
INSUFFICIENT_STOCK
```

may be valuable.

But:

```text
INSUFFICIENT_STOCK_FOR_SOFA
INSUFFICIENT_STOCK_FOR_TABLE
INSUFFICIENT_STOCK_FOR_CHAIR
```

is not.

---

# 80. Avoid Ambiguous Generic Codes

Do not use:

```text
ERROR
FAILED
BAD_REQUEST
SOMETHING_WENT_WRONG
```

as the only domain vocabulary.

The HTTP status may already communicate broad failure classification.

The API code should communicate useful machine-level meaning.

---

# 81. Error Status Matrix

Create a preliminary mapping:

| Category                        |                   HTTP status |
| ------------------------------- | ----------------------------: |
| Malformed JSON                  |                           400 |
| Unsupported method              |                           405 |
| Unsupported content type        |                           415 |
| Authentication required/invalid |                           401 |
| Forbidden                       |                           403 |
| Resource not found              |                           404 |
| Validation failure              |                           422 |
| Business validation failure     |  422 unless conflict-specific |
| State/concurrency conflict      |                           409 |
| Rate limited                    |                           429 |
| Internal unexpected error       |                           500 |
| Temporary upstream failure      | 502/503/504 where appropriate |

This is a project convention; final endpoint-specific exceptions must be documented rather than invented.

---

# 82. External Service Errors

When a future provider fails:

Do not directly expose:

```text
provider error string
provider internal code
provider credentials
```

Instead map it to a stable project-level API error.

Payment-specific mappings belong to Group H.

---

# 83. Payment Error Boundary

The generic error contract should permit Group H to define:

```text
payment-specific error codes
provider reconciliation errors
webhook failures
payment timeout behavior
```

without breaking the global error envelope.

---

# 84. Error Contract Compatibility

Within Version 1:

Potentially breaking:

```text
remove an error code
rename an error code
change error semantics
change field path format
remove a documented error detail
change HTTP status incompatibly
make an error suddenly success
```

Potentially non-breaking:

```text
improve human message
add optional error detail
add documentation
add a new error code for a newly introduced operation
```

However, new behavior must still be reviewed for client compatibility.

---

# 85. Closed Error Enumerations

If the API exposes a documented finite list of:

```text
codes
categories
types
```

the Version 1 contract should treat the documented values as closed unless explicitly stated otherwise.

This remains consistent with the project's global closed-enum policy.

---

# 86. Error Contract and Documentation

Every endpoint defined in later phases must reference the relevant existing error codes rather than inventing undocumented ones.

For example:

```text
POST checkout
→ AUTHENTICATION_REQUIRED
→ CART_INVALID
→ INSUFFICIENT_STOCK
```

not:

```text
CHECKOUT_FAIL
```

created locally by the endpoint designer.

---

# 87. Error Contract and Testing

Every documented error must eventually have automated coverage.

At minimum test:

```text
HTTP status
error code
field where applicable
safe message behavior
authorization behavior
state consistency
```

Do not test only the HTTP status.

---

# 88. Error Contract and Security Testing

Later security tests should verify that:

```text
unauthorized resource access
```

does not reveal:

```text resource existence
private data
internal identifiers
database details
```

through error responses.

---

# 89. Error Contract and Logging

The server may log richer diagnostic context than the client receives.

Example:

```text
Client:
INTERNAL_SERVER_ERROR
request_id = abc123

Server:
full exception + stack trace + deployment context
```

This separation is intentional.

---

# 90. Error Contract and Support

The project should be able to use:

```text request_id
order reference
customer reference
```

to diagnose failures.

Do not put private debugging information directly into the client response.

---

# 91. Required Deliverables

Update only the consolidated project documentation:

### `docs/api/api-contract.md`

Add the complete Error Contract section and initial error-code registry.

### `docs/api/api-conventions.md`

Add reusable error-handling conventions.

### `docs/api/api-resources.md`

Add resource-specific error considerations where useful.

### `docs/domain/business-rules.md`

Ensure business errors map correctly to established business rules.

### `docs/decisions.md`

Record the major error-contract decisions.

### `AGENTS.md`

Only update permanent engineering rules if necessary.

Do **not** create additional permanent phase-specific markdown files.

---

# 92. Validation Checklist

### Envelope

* [ ] All errors use one top-level `errors` structure.
* [ ] Error objects have stable codes.
* [ ] Messages are human-readable.
* [ ] Structured details are supported where appropriate.
* [ ] Errors cannot be confused with successful `data`.

### HTTP

* [ ] 400 semantics defined.
* [ ] 401 semantics defined.
* [ ] 403 semantics defined.
* [ ] 404 semantics defined.
* [ ] 409 semantics defined.
* [ ] 422 semantics defined.
* [ ] 429 semantics defined.
* [ ] 500 semantics defined.
* [ ] 405/415/413 considerations are defined.

### Validation

* [ ] Field-level errors are supported.
* [ ] Nested field paths are supported.
* [ ] Multiple validation errors are supported.
* [ ] Unknown-field errors fit the contract.
* [ ] Closed enum failures are supported.

### Business errors

* [ ] Product failures are represented.
* [ ] Inventory failures are represented.
* [ ] Checkout failures are represented.
* [ ] Order failures are represented.
* [ ] Cancellation failures are represented.
* [ ] State-transition failures are represented.
* [ ] Request/enquiry failures can be represented.

### Security

* [ ] Raw exceptions are never exposed.
* [ ] SQL/database information is never exposed.
* [ ] Authentication secrets are never exposed.
* [ ] Payment secrets are never exposed.
* [ ] Private resource existence is protected appropriately.
* [ ] Error details respect authorization.

### Compatibility

* [ ] Error codes are version-sensitive.
* [ ] HTTP status changes are treated as contract changes.
* [ ] Human messages are not used as machine identifiers.
* [ ] Version 1 enum policy remains CLOSED.

### Operations

* [ ] Request/correlation ID policy is defined.
* [ ] Retryability is considered.
* [ ] Rate limiting is represented.
* [ ] Concurrency errors are represented.

### Payment

* [ ] Payment-specific implementation remains in **Phase Group H**.
* [ ] No provider-specific payment errors have been hardcoded.
* [ ] The global error contract can accommodate future payment errors.

### Documentation

* [ ] Existing consolidated documents are updated.
* [ ] No unnecessary new permanent markdown files are created.

---

# 93. Explicitly Out of Scope

Do NOT:

```text
Implement Laravel exception handlers
Create Laravel API Resources
Create exception classes
Create middleware
Implement authentication
Implement authorization
Implement rate limiting
Implement payment provider errors
Implement webhook error handling
Create endpoint-specific error schemas
Create OpenAPI error schemas
Build Next.js error handling
Build Flutter error handling
```

---

# 94. Definition of Done

Phase 1.16 is complete when:

1. The global API error envelope is fixed.
2. Error objects have a stable structure.
3. Error-code naming is fixed.
4. HTTP status conventions are documented.
5. Validation error representation is defined.
6. Field-level error paths are defined.
7. Authentication errors are represented.
8. Authorization errors are represented.
9. Resource-not-found behavior is defined.
10. Business-rule errors are represented.
11. Conflict/concurrency errors are represented.
12. Rate-limit errors are represented.
13. Internal errors are safely represented.
14. Request/correlation IDs are addressed.
15. Retry semantics are documented conceptually.
16. Error information-disclosure rules are explicit.
17. Error codes are treated as versioned API contract elements.
18. Version 1 closed-enum policy remains intact.
19. Payment-specific errors remain extensible within the global envelope but implementation remains deferred to **Group H**.
20. Consolidated documentation has been updated.
21. No implementation code has been written.

---

# 95. STOP CONDITION — Mandatory

After completing Phase 1.16 and updating:

```text
docs/api/api-contract.md
docs/api/api-conventions.md
docs/api/api-resources.md
docs/domain/business-rules.md
docs/decisions.md
```

and passing the validation checklist:

**STOP.**

Do not implement the error handler.

Do not create Laravel exception classes.

Do not create OpenAPI error schemas yet.

Do not build frontend error-handling code.

The next phase must be explicitly requested.

Recommended next phase:

# Phase 1.17 — Define Authentication Contract

That phase will establish the API-level authentication model for the two different client types:

```text
Next.js website
Flutter mobile application
```

while preserving the approved rules that:

```text
anonymous users can browse;
anonymous users can submit made-to-order requests and enquiries;
checkout requires authentication;
customer-private resources require authentication;
```

It will define the authentication lifecycle, session/token boundaries, registration, login, logout, password recovery, verification, device/session behavior, credential handling, and security requirements—without yet implementing Laravel/Sanctum.
