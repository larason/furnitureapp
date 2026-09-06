# Group B phases instructions

# Phase 2.7 — Exception/Error Handling Foundation

## Objective

Implement the Laravel backend's centralized exception and API error-handling foundation for Version 1.

The implementation must make Laravel consistently translate expected and unexpected failures into the frozen `/api/v1` error contract, without introducing business/domain rules that belong to later phases.

The result must provide a stable foundation for Form Requests, DTOs, authentication, authorization, domain services, persistence, and later logging.

---

## 1. Phase Context

Phases 2.1–2.5 have already been implemented as part of the project foundation, including:

* Laravel application initialization
* environment configuration
* database connection
* base application structure

Phase 2.6 established the API routing foundation.

Do **not** redo or refactor those phases unless a small change is strictly required for this phase.

Phase 2.7 is limited to exception/error handling infrastructure.

The next phases remain responsible for their own concerns:

* **2.8:** logging foundation
* **2.9:** health/status endpoint
* **2.10:** coding standards/static analysis
* **2.11:** test framework setup
* **Group C:** database/domain implementation
* **Group D:** authentication/authorization

---

## 2. Authoritative Sources

Before modifying code, read the current versions of:

1. `AGENTS.md`
2. `docs/api/api-contract.md`
3. `docs/api/api-resources.md`
4. `docs/api/api-conventions.md`
5. `docs/domain/business-rules.md`

Treat the frozen Version 1 API documentation as authoritative.

Do not introduce a second error specification such as:

* `docs/api/api-errors.md`
* `docs/api/validation-errors.md`

The existing consolidated API conventions explicitly define the reusable error architecture.

Do not silently alter the frozen contract.

---

# 3. Core Requirements

## 3.1 Centralized exception handling

Create a single authoritative API exception-handling mechanism.

Expected responsibilities include:

* determining whether the request is an API request covered by `/api/v1`
* converting known application/API exceptions into the correct contract response
* converting framework exceptions into the correct contract response
* converting unexpected exceptions into a safe `500` response
* preventing raw Laravel/framework exceptions from reaching clients
* preserving the request correlation ID
* ensuring errors use the frozen JSON structure

Do not distribute error formatting across individual controllers.

A controller must not independently invent its own error envelope.

---

# 4. Frozen Error Envelope

All API failures must follow the frozen structure:

```json
{
  "errors": [
    {
      "code": "INVALID_VALUE",
      "message": "The selected fulfillment type is invalid.",
      "field": "fulfillment_type"
    }
  ],
  "meta": {
    "request_id": "..."
  }
}
```

The implementation must enforce these rules:

* top-level member is `errors`
* `errors` is always an array
* no `error` singular member
* no `success: false`
* no `data` member in an error response
* each error has a required `code`
* each error has a required safe human-readable `message`
* `field` is optional
* `details` is optional
* `request_id` belongs under `meta.request_id`
* `request_id` must not be duplicated inside each error object

These are frozen Version 1 conventions.

---

# 5. Error Code Rules

Implement a centralized representation for application error codes rather than scattering arbitrary strings throughout the codebase.

The error code itself is part of the machine-readable contract.

Codes must:

* use `UPPER_SNAKE_CASE`
* remain stable
* not expose framework exception names
* not expose SQLSTATE values
* not expose database implementation details
* not use generic `ERROR` or `FAILED` codes
* not invent endpoint-specific variants unnecessarily

The currently frozen vocabulary includes, among others:

### Structural

```text
INVALID_TYPE
INVALID_FORMAT
INVALID_VALUE
MISSING_REQUIRED_FIELD
```

### Business

```text
PRODUCT_NOT_PURCHASABLE
INSUFFICIENT_STOCK
ORDER_NOT_CANCELLABLE
INVALID_ORDER_TRANSITION
```

### Authentication/authorization

```text
AUTHENTICATION_REQUIRED
INVALID_CREDENTIALS
SESSION_EXPIRED
INVALID_AUTHENTICATION
FORBIDDEN
```

### General HTTP/API failures

```text
RESOURCE_NOT_FOUND
CONFLICT
RATE_LIMITED
INVALID_JSON
REQUEST_TOO_LARGE
UNSUPPORTED_MEDIA_TYPE
METHOD_NOT_ALLOWED
INTERNAL_SERVER_ERROR
EXTERNAL_SERVICE_ERROR
```

Use the authoritative contract and error registry when deciding whether a particular code already exists.

Do not add new Version 1 error codes merely because a more specific message seems convenient.

## Error codes are a CLOSED contract; adding or changing them requires the established contract-change process.

# 6. HTTP Status Mapping

Centralize the mapping between API failure categories and HTTP status.

The foundation must support the frozen conventions:

| HTTP status   | Typical contract meaning                            |
| ------------- | --------------------------------------------------- |
| `400`         | Malformed request / invalid JSON                    |
| `401`         | Authentication required/invalid authentication      |
| `403`         | Authenticated but not authorized                    |
| `404`         | Resource not addressable / masked private resource  |
| `409`         | State/concurrency conflict                          |
| `413`         | Request too large                                   |
| `415`         | Unsupported media type                              |
| `422`         | Well-formed request but validation/business invalid |
| `429`         | Rate limited                                        |
| `500`         | Unexpected internal failure                         |
| `502/503/504` | Temporary external/upstream failure                 |

The HTTP status and error `code` are separate contract elements. Do not encode the status by inventing duplicate error codes.

Do not change these mappings casually.

---

# 7. Request ID Foundation

Every API error response must contain:

```json
"meta": {
  "request_id": "..."
}
```

The request ID must be:

* unique per request
* safe for clients to receive
* unrelated to `user_id`
* unrelated to `order_id`
* unrelated to `payment_id`
* free of secrets or sensitive information
* suitable for correlating the eventual server-side logs

The request ID should be created or propagated at the HTTP boundary so that errors generated at different layers use the same identifier.

The error handler must not generate a different request ID for each exception during the same request.

The frozen contract explicitly makes `meta.request_id` part of every error response.

Do not implement the full logging system in this phase. Phase 2.8 owns logging.

---

# 8. Exception Taxonomy

Establish a small, maintainable exception taxonomy sufficient for the API layer.

Separate at least these conceptual categories:

### Client/input failures

Used when the request itself is malformed or invalid.

Examples:

* malformed JSON
* invalid type
* invalid format
* invalid value
* missing required field
* unsupported media type
* request too large

### Authentication failures

Used for authentication-related failures.

Do not confuse:

* unauthenticated → `401`
* authenticated but forbidden → `403`

### Authorization failures

Represent authorization failures centrally.

Do not expose private resource existence when the frozen contract requires 404 masking.

### Resource-not-found failures

Provide a normalized mechanism for an addressable resource that cannot be returned.

The mechanism must support masked-not-found behavior for private customer-owned resources.

### Business/domain failures

Provide the foundation for later application/domain exceptions such as:

* `PRODUCT_NOT_PURCHASABLE`
* `INSUFFICIENT_STOCK`
* `ORDER_NOT_CANCELLABLE`
* `INVALID_ORDER_TRANSITION`

Do not implement these business rules yet.

### Conflict failures

Provide a normalized representation for later concurrency/state conflicts.

### External failures

Provide the generic infrastructure necessary for later upstream failures.

Do not implement payment-provider-specific exceptions in this phase.

### Unexpected failures

Anything not deliberately mapped must become a safe `500 INTERNAL_SERVER_ERROR` API response.

Never expose the underlying exception directly.

---

# 9. Framework Exception Mapping

Add centralized mappings for framework-level failures that can occur before application/domain code exists.

The implementation should handle, where applicable:

* route not found
* unsupported HTTP method
* malformed JSON
* HTTP authentication failures
* authorization failures
* validation failures where the framework currently produces them
* request size failures
* unsupported media type
* application-defined exceptions
* unexpected exceptions

Do not expose:

* `ModelNotFoundException`
* SQL exceptions
* SQLSTATE values
* stack traces
* framework class names
* filesystem paths
* server IP addresses
* internal configuration
* secrets
* authentication tokens
* payment provider secrets

The client receives only the safe contract representation.

The server-side exception object must remain available for later logging.

These disclosure restrictions are explicitly frozen in the API conventions.

---

# 10. Validation Error Foundation

Prepare the error infrastructure so later Form Requests can produce contract-compliant validation responses.

The handler must be capable of representing multiple validation errors in a single response.

Example:

```json
{
  "errors": [
    {
      "code": "MISSING_REQUIRED_FIELD",
      "message": "The field is required.",
      "field": "name"
    },
    {
      "code": "INVALID_VALUE",
      "message": "The selected fulfillment type is invalid.",
      "field": "fulfillment_type"
    }
  ],
  "meta": {
    "request_id": "..."
  }
}
```

Field paths must use canonical dot notation:

```text
delivery_address.city
items.0.quantity
```

Do not return Laravel's internal validation representation directly if it differs from the frozen contract.

The API layer must be prepared to return all safely determinable schema errors rather than only the first one.

Do **not** implement the actual endpoint Form Requests in Phase 2.7.

---

# 11. Critical Mass-Assignment Boundary

Preserve the requirement established for Phase 2.6 and the frozen contract:

Never implement code that does:

```php
$request->all()
```

and passes that collection directly into:

* models
* repositories
* services
* DTO constructors
* commands
* persistence operations

The future pipeline must remain:

```text
Request
→ FormRequest/schema validation
→ validated()
→ explicit allow-list
→ DTO/Command
→ application/domain logic
→ persistence
```

In particular, the exception-handling work must not create helper abstractions that accidentally encourage unrestricted request data propagation.

The frozen API contract explicitly prohibits `$request->all() → model fill` and requires validated input to flow through controlled DTO/command boundaries.

---

# 12. `additionalProperties: false` Compatibility

Do not weaken strict request handling while building the error layer.

Future strict create/update/action inputs must be able to surface unknown fields as contract-compliant validation errors rather than silently ignoring them.

For example:

```json
{
  "errors": [
    {
      "code": "INVALID_VALUE",
      "message": "The request contains an unsupported field.",
      "field": "unexpected_field"
    }
  ],
  "meta": {
    "request_id": "..."
  }
}
```

Do not create a generic "accept anything and let the model decide" request abstraction.

Unknown fields are explicitly rejected for strict V1 inputs.

---

# 13. 404 Masking / Enumeration Protection

The error infrastructure must support resource-not-found masking.

For private customer-owned resources:

```text
Customer A requests Customer B's order
→ do not return 403 exposing ownership existence
→ return the contractually appropriate 404
```

The error infrastructure must therefore not force every authorization failure into `403`.

The eventual authorization/resource layer must be able to deliberately raise or map to the masked not-found representation.

Do not implement customer ownership logic now.

Only establish the error-handling mechanism required to support it.

The frozen contract explicitly requires 404 masking to prevent identifier enumeration.

---

# 14. Rate-Limit Error Compatibility

The error layer must already support the frozen rate-limit contract.

When later rate limiting produces a `429`, the API response must use:

```text
HTTP 429
```

with:

```text
Retry-After: <value>
```

The response body must use:

```json
{
  "errors": [
    {
      "code": "RATE_LIMITED",
      "message": "Too many requests."
    }
  ],
  "meta": {
    "request_id": "..."
  }
}
```

Do **not** create:

```json
{
  "retry_after_seconds": 30
}
```

Do not put retry timing into the error body unless the frozen contract is later changed.

Do not disclose the internal rate-limit algorithm.

This is especially important because the project contract explicitly requires the standard `Retry-After` header.

Do not implement the complete rate-limiting policy matrix in this phase; later backend work will attach the documented limiters.

---

# 15. Error Message Rules

Messages are human-readable and not the machine contract.

Messages must:

* be safe for the client
* be understandable
* avoid implementation details
* avoid sensitive data
* avoid internal identifiers
* avoid exception class names
* avoid SQL
* avoid filesystem paths
* avoid provider secrets
* not require frontend parsing for business decisions

Frontend logic will branch on:

```text
code
field
details
HTTP status
```

not on message text.

The conventions explicitly separate stable machine codes from human-readable messages.

Do not attempt to implement localization in Laravel.

---

# 16. Safe `details`

Support an optional structured `details` object.

It must be possible for later domain code to safely return structured information such as:

```json
"details": {
  "requested_quantity": 5,
  "available_quantity": 2
}
```

Do not allow:

* internal IDs
* reservation IDs
* provider secrets
* database records
* stack information
* credentials
* private customer data

The error formatter should not automatically serialize arbitrary exception properties into `details`.

Only explicitly supplied safe details may be exposed.

---

# 17. Response Headers

Ensure the API error response foundation does not interfere with required headers established elsewhere.

In particular:

* preserve `Retry-After` when applicable
* preserve the request/correlation identifier mechanism
* do not replace standard HTTP semantics with JSON-only metadata
* do not leak internal response/debug headers

Do not implement the entire CORS, CSRF, cache, or security-header system here unless an already-established Phase 2.6 foundation requires a minimal compatibility change.

---

# 18. Architectural Requirements

Keep the implementation small.

Prefer a structure similar in responsibility to:

```text
HTTP boundary
    ↓
API exception handler
    ↓
exception-to-contract mapper
    ↓
error response factory/formatter
```

Exact class names and locations should follow the existing Laravel project structure.

Avoid:

* giant exception handlers containing business rules
* a universal exception class for every possible failure
* controller-specific error formatting
* duplicated error JSON construction
* domain logic inside the exception handler
* database queries inside error formatting
* hidden authorization logic inside generic error formatting
* speculative abstraction layers

The exception layer should translate failures, not decide business policy.

---

# 19. No Business Logic in Error Handling

The exception layer must not determine:

* whether a product is purchasable
* whether inventory is sufficient
* whether an order can be cancelled
* whether a transition is valid
* whether a user owns a resource
* whether delivery is permitted
* what delivery fee applies
* what an order total is
* whether payment succeeded

It only maps an already-determined failure into the API contract.

The frozen architecture requires business rules to have one authoritative boundary rather than being duplicated in infrastructure such as Form Requests or controllers.

---

# 20. API Scope

Only the Version 1 API namespace is in scope:

```text
/api/v1
```

Do not redesign existing route paths.

Do not recreate removed `/staff/...` aliases.

Do not add new public endpoints.

Do not add health/status behavior; Phase 2.9 owns that.

---

# 21. Testing

Add focused tests for the exception/error foundation.

Tests should verify at minimum:

### Envelope

* every mapped API error has `errors`
* `errors` is an array
* no `data` member appears in an error response
* `meta.request_id` exists
* `request_id` is not placed inside the error object

### Codes

* codes are returned in the canonical form
* unsupported/internal framework names are never exposed

### HTTP mapping

Verify representative mappings for:

* `400`
* `401`
* `403`
* `404`
* `409`
* `413`
* `415`
* `422`
* `429`
* `500`
* `502`
* `503`
* `504`

The `502`/`503`/`504` cases must verify the frozen external/upstream-failure mapping to `EXTERNAL_SERVICE_ERROR` (per §6 and `api-contract.md §15.12`), with a safe generic message and no provider/upstream detail leakage. Where the underlying failure carries retry guidance, it must use the standard `Retry-After` HTTP header (as with `429`), never a JSON retry field.

### Validation

Verify multiple field errors can be returned.

Verify canonical nested field paths are preserved.

### Security

Verify unexpected exceptions do not expose:

* stack traces
* SQL
* filesystem paths
* framework exception class names
* secrets
* private implementation details

### Request ID

Verify every API error contains a valid request ID.

Verify the same request uses one request ID consistently.

### 404 masking compatibility

Verify the error infrastructure can represent a masked `404` without exposing ownership information.

### Rate-limit compatibility

Verify the `429` representation supports the standard:

```text
Retry-After
```

header.

Do not implement the full business test matrix from later domain phases yet.

---

# 22. Minimal Code Comments

Keep comments in code to an absolute minimum.

Do not write comments that merely restate what the code does.

Avoid comments such as:

```php
// Return the response
return $response;
```

Prefer clear names and straightforward structure.

A comment is justified only when it explains a non-obvious constraint, compatibility requirement, security decision, or framework workaround that cannot reasonably be expressed through the code itself.

Do not add long explanatory block comments, implementation essays, or duplicated contract documentation inside source files.

The authoritative explanation belongs in the project documentation, not repetitive inline comments.

---

# 23. Validation Commands

After implementation, run the project's existing verification commands appropriate to its current state.

At minimum:

```bash
php artisan route:list
php artisan test
```

Also run the relevant focused test subset for the new exception/error-handling code.

Do not introduce new test-framework infrastructure in Phase 2.7 if Phase 2.11 has not yet established it.

Use the test facilities already available in the repository.

---

# 24. Review Checklist

Before marking the phase complete, verify:

* [ ] `/api/v1` routes continue to use the existing routing foundation
* [ ] one centralized API exception/error boundary exists
* [ ] error responses use `errors` only
* [ ] no `data` appears in error responses
* [ ] error codes are centralized/stable
* [ ] HTTP status mappings follow the frozen contract
* [ ] `meta.request_id` is present on API errors
* [ ] nested field paths use canonical dot notation
* [ ] multiple validation errors are representable
* [ ] private-resource 404 masking is supported
* [ ] unexpected exceptions are sanitized
* [ ] SQL/framework/stack/secret leakage is prevented
* [ ] `429` can emit standard `Retry-After`
* [ ] no custom retry JSON field has been introduced
* [ ] no `$request->all()` mass-assignment path has been introduced
* [ ] strict unknown-field errors remain compatible with `additionalProperties: false`
* [ ] no business/domain rules have been implemented
* [ ] no authentication system has been implemented
* [ ] no authorization system has been implemented
* [ ] no logging subsystem has been implemented
* [ ] no health endpoint has been implemented
* [ ] comments in new code are minimal and only explain non-obvious constraints
* [ ] focused tests pass
* [ ] existing tests still pass

---

# 25. Definition of Done

Phase 2.7 is complete only when:

1. Laravel has one consistent API exception/error handling foundation.
2. All handled API failures can be rendered using the frozen V1 error envelope.
3. The error contract includes the required `meta.request_id`.
4. HTTP statuses and machine-readable error codes follow the frozen conventions.
5. Validation errors can represent multiple field-level failures using canonical field paths.
6. Private-resource 404 masking is technically supported.
7. Unexpected exceptions are sanitized and never expose internal implementation details.
8. The rate-limit representation is compatible with `429` plus standard `Retry-After`.
9. The implementation does not weaken strict request validation or the validated-input → DTO/command boundary.
10. Tests cover the error-handling foundation.
11. No unrelated business functionality has been introduced.
12. New code contains only the minimum necessary comments.

---

# 26. Explicitly Out of Scope

Do **not** implement during Phase 2.7:

* database migrations
* domain models
* domain validators
* business-rule implementation
* Form Requests for actual business endpoints
* DTO implementations for actual business resources
* authentication
* Laravel Sanctum
* authorization policies/permissions
* customer/staff/admin role enforcement
* product/catalog behavior
* inventory behavior
* cart behavior
* checkout behavior
* order lifecycle behavior
* payment integration
* payment-provider errors
* webhook implementation
* delivery-fee logic
* request/enquiry domain behavior
* attachment processing
* notification behavior
* logging subsystem
* audit subsystem
* health/status endpoint
* OpenAPI implementation
* frontend error handling
* Flutter error handling
* Next.js error handling
* CI setup
* static-analysis setup
* new endpoint creation

The frozen API conventions defer Laravel endpoint handlers, domain validation, and endpoint-specific middleware to later backend phases. Phase 2.7 must implement the centralized API exception/error boundary described in this document.

---

# 27. STOP Condition

Stop immediately when the Phase 2.7 definition of done is satisfied.

Do not continue into Phase 2.8.

Do not begin logging implementation merely because exception objects are now available.

Do not begin authentication or authorization.

Do not begin Form Requests or DTOs for domain endpoints.

Do not redesign the frozen API contract.

If an existing implementation detail appears to conflict with the frozen API error contract, make the smallest necessary compatibility correction and document the discrepancy rather than expanding the scope of this phase.
