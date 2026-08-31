# Phase 1.15 — Define API Validation and Domain-Validation Conventions

## 1. Purpose

Phase 1.15 establishes the project's standard approach to **input validation and business-rule validation**.

The objective is to clearly separate:

```text
HTTP/transport validation
        ↓
request/schema validation
        ↓
authentication
        ↓
authorization
        ↓
domain/business validation
        ↓
transaction/concurrency validation
        ↓
business operation
```

This prevents a common architectural problem where:

* Next.js validates one way;
* Flutter validates another way;
* Laravel controllers contain inconsistent rules;
* database constraints are expected to enforce business behavior;
* domain invariants are duplicated incorrectly;
* clients can bypass rules by calling the API directly.

This phase defines the **validation architecture and conventions**, not the validation implementation.

---

# 2. Documentation Strategy

Continue the consolidated-document strategy.

Do **not** create:

```text
validation.md
api-validation.md
domain-validation.md
validation-decisions.md
```

unless a later project decision genuinely requires them.

Update:

```text id="4gkqww"
docs/api/api-contract.md
docs/api/api-conventions.md
docs/api/api-resources.md
docs/domain/business-rules.md
docs/decisions.md
AGENTS.md
```

Only update `AGENTS.md` where the governing development rules need to reference the finalized validation architecture.

---

# 3. Authoritative Project Paths

Use:

```text id="u4w17x"
AGENTS.md
docs/VISION.md
```

Do not use the old names:

```text id="zojv5s"
agent.md
VISION.md
```

---

# 4. Payment Assignment

Payment-specific validation remains part of:

**Phase Group H**

not Group G.

This phase may define generic validation architecture applicable to Payment but must not define:

* provider-specific validation;
* payment provider fields;
* payment provider SDK behavior;
* webhook payload validation.

---

# 5. Version 1 Enum Policy

All Version 1 enums are:

> **CLOSED by default.**

Validation must enforce the approved enum values.

Examples:

```text id="xq2t3u"
IN_STOCK
MADE_TO_ORDER

PICKUP
DELIVERY

PENDING_PAYMENT
PAID
ACCEPTED
PROCESSING
READY_FOR_PICKUP
SHIPPED
DELIVERED
COMPLETED
```

Do not reintroduce the previous `OPEN/EXTENSIBLE` policy.

A new enum value must be treated as a compatibility decision.

---

# 6. Dependency Position

Current sequence:

```text id="epm9j4"
1.1 API Scope
       ↓
1.2 Domain Nouns
       ↓
1.3 Domain Invariants
       ↓
1.4 Logical Data Model
       ↓
1.5 Logical Model Review + Freeze
       ↓
1.6 API Resource Inventory
       ↓
1.7 API Resource Relationships
       ↓
1.8 API Versioning
       ↓
1.9 Endpoint Naming
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
1.16 ...
```

Do not redesign earlier phases here.

---

# 7. Authoritative Inputs

Read:

```text id="8v4n9p"
AGENTS.md
docs/VISION.md

docs/api/api-contract.md
docs/api/api-conventions.md
docs/api/api-resources.md
docs/domain/business-rules.md
docs/decisions.md
```

Also review the finalized outputs from Phases 1.1–1.14.

---

# 8. Core Validation Principle

There are different kinds of validation.

Do not treat all validation as one layer.

The project must distinguish at minimum:

```text id="cl8f5m"
Transport validation
Schema/input validation
Authentication
Authorization
Domain validation
Cross-field validation
State-dependent validation
Concurrency validation
External-system validation
```

Each layer has different responsibilities.

---

# 9. Transport Validation

Transport validation determines whether the request is structurally acceptable to the API infrastructure.

Examples:

```text id="7q8bqv"
correct Content-Type
valid JSON syntax
request size within limits
supported HTTP method
supported media type
```

Examples of failures:

```text id="5wxy02"
malformed JSON
unsupported content type
body too large
```

These are not business-rule violations.

---

# 10. Schema/Input Validation

Schema validation determines whether the supplied fields have the correct structure and basic data types.

Examples:

```text id="vbgwz9"
quantity must be an integer
email must be valid format
name must not exceed maximum length
fulfillment_type must be a valid enum
```

This layer should not decide complex business facts such as:

> Whether the product is currently in stock.

That belongs later.

---

# 11. Authentication Validation

Authentication determines:

> Who is making this request?

Examples:

```text id="0k2b9m"
valid authenticated session
valid bearer token
authenticated customer exists
```

Do not confuse:

```text id="qjq60z"
authentication
```

with:

```text id="x0l97j"
authorization
```

---

# 12. Authorization

Authorization determines:

> Is this authenticated actor allowed to perform this operation on this resource?

Examples:

```text id="cz9n7i"
Customer → own order
Staff → operational order
Admin → administrative management
```

A validly structured request may still fail authorization.

---

# 13. Domain Validation

Domain validation answers:

> Is this operation allowed according to the business rules?

Examples:

```text id="vksn8c"
IN_STOCK product can be purchased
MADE_TO_ORDER product cannot enter normal checkout
order is within cancellation window
fulfillment type matches required delivery information
order can transition from current state to requested state
```

This is the layer where Phase 1.3 invariants are enforced.

---

# 14. Never Move Domain Rules Entirely to the Client

Do not rely on:

```text id="0i1f1b"
Next.js validation
Flutter validation
```

for authoritative business decisions.

Clients may duplicate validation for UX.

The API must independently enforce all important business invariants.

---

# 15. Client Validation vs Server Validation

Document this rule:

```text id="z4dr9g"
Client validation
→ UX optimization

Server validation
→ authority
```

Example:

Flutter can disable:

```text id="lo0eju"
"Checkout"
```

when stock appears unavailable.

But Laravel must still verify stock.

---

# 16. Cross-Field Validation

Some rules require examining multiple request fields together.

Examples:

```text id="8idj40"
fulfillment_type = DELIVERY
→ delivery address required

fulfillment_type = PICKUP
→ delivery fee must be zero/not supplied as customer-controlled data
```

Another:

```text id="u02wqz"
product_type = MADE_TO_ORDER
→ normal checkout prohibited
```

These should not be reduced to isolated field validators.

---

# 17. Conditional Validation

Some fields are conditionally required.

Example:

```text id="lplx5f"
PICKUP
→ delivery address not required

DELIVERY
→ delivery address required
```

Define conditional validation rules explicitly.

Do not create confusing universal rules such as:

```text delivery_address is always required
```

when the business does not require it.

---

# 18. State-Dependent Validation

Some operations are valid only in certain states.

Example:

```text id="u7q7gs"
Order = PROCESSING
→ SHIP may be valid

Order = COMPLETED
→ SHIP should be invalid
```

The validator must consider current authoritative state.

Do not validate a requested state transition only from the submitted value.

---

# 19. Product Purchasability Validation

A purchase operation must validate at least conceptually:

```text id="mff94r"
Product exists
Product active
Product is IN_STOCK
Variant valid
Current stock sufficient
Requested quantity valid
```

Do not rely solely on:

```text id="8p5dzk"
product_type = IN_STOCK
```

Current availability still matters.

---

# 20. Inventory Validation

Inventory validation must be authoritative and concurrency-safe.

Conceptually:

```text id="z6a7yr"
read current stock
↓
validate quantity
↓
reserve/consume according to transaction
```

Later implementation must protect against races.

Do not implement stock validation as:

```text id="3t4n7v"
SELECT stock
if stock > quantity
then UPDATE stock
```

without transaction/concurrency considerations.

That specific implementation belongs later.

---

# 21. Pricing Validation

The API must validate the **request's inputs**, but calculate authoritative pricing itself.

Example:

Client sends:

```json id="v38p8b"
{
  "product_id": "123",
  "quantity": 2
}
```

Backend determines:

```text id="w9c4b5"
current price
subtotal
delivery fee
total
```

Do not validate a customer-submitted total as though it were authoritative.

---

# 22. Delivery Fee Validation

For delivery:

```text id="mqnmx8"
fulfillment_type = DELIVERY
```

the customer supplies necessary delivery information.

The authorized business operator determines the actual delivery fee.

Therefore customer input must not be allowed to override:

```text id="6dx6cs"
approved delivery fee
```

---

# 23. Checkout Validation

Checkout requires a layered process.

Conceptually:

```text id="0g1mwj"
1. Transport valid
       ↓
2. Input schema valid
       ↓
3. Customer authenticated
       ↓
4. Customer authorized for cart
       ↓
5. Cart valid
       ↓
6. Products purchasable
       ↓
7. Inventory available
       ↓
8. Fulfillment valid
       ↓
9. Price calculated server-side
       ↓
10. Order/payment workflow
```

Do not collapse all these steps into one generic validator.

---

# 24. Cart Validation

Cart validation must ensure:

```text id="3j1w1p"
product exists
variant belongs to product
product is active
product type is purchasable
quantity is valid
```

At checkout, inventory and current pricing must be checked again.

---

# 25. Request Validation

Made-to-order requests should validate:

```text id="bcbb2a"
contact information
product reference when supplied
quantity when supplied
dimensions
material
color
notes
attachments
```

The request is valid even when:

```text id="j6wspy"
user = null
```

because anonymous submission is approved.

---

# 26. Enquiry Validation

General enquiries should validate:

```text id="ii7eh7"
contact information
subject where required
message
attachments
```

An authenticated User may be associated, but it is not mandatory.

---

# 27. Profile Validation

Profile updates should validate each mutable field appropriately.

Do not treat:

```text id="8v4tx5"
email
password
role
account_status
```

as ordinary profile fields automatically.

Some identity/security operations require dedicated workflows.

---

# 28. Order Cancellation Validation

Cancellation must conceptually validate:

```text id="6r0w3x"
authenticated customer
        ↓
owns order
        ↓
order exists
        ↓
customer cancellation allowed
        ↓
20-minute window valid
        ↓
current order state eligible
```

Do not allow the client to submit:

```json id="f0m18z"
{
  "cancelled_at": "10:05"
}
```

and use that to establish eligibility.

The backend determines the authoritative time.

---

# 29. Order Transition Validation

Every controlled status transition should validate:

```text id="iozff8"
current state
requested transition
actor authorization
business preconditions
```

Example:

```text id="m0f16o"
PROCESSING
→ SHIPPED
```

may require appropriate staff authorization and delivery-related conditions.

Do not allow:

```text id="1x3vvi"
status = SHIPPED
```

to bypass the transition validator.

---

# 30. Enum Validation

Because Version 1 enums are closed:

```text id="9kjbr0"
input ∈ documented enum set
```

must be true.

An unsupported value is invalid.

Do not silently normalize unknown values into:

```text id="vnxjtb"
OTHER
UNKNOWN
CUSTOM
```

unless such values are explicitly part of the approved enum.

---

# 31. Enum Storage vs API Validation

The physical database may eventually represent enums differently.

This phase concerns API/business validation.

The API contract must remain authoritative about accepted values.

Do not let MySQL implementation determine public API enum semantics.

---

# 32. Validation Error Categories

Classify validation failures conceptually.

### Structural/Input

```text id="u9wgn9"
INVALID_TYPE
INVALID_FORMAT
INVALID_VALUE
MISSING_REQUIRED_FIELD
```

### Business

```text id="8hwuk3"
PRODUCT_NOT_PURCHASABLE
INSUFFICIENT_STOCK
ORDER_NOT_CANCELLABLE
INVALID_ORDER_TRANSITION
```

### Authorization

```text id="z7f6qi"
NOT_AUTHENTICATED
FORBIDDEN
RESOURCE_NOT_OWNED
```

### External

Later:

```text id="jz5a8m"
PAYMENT_PROVIDER_ERROR
```

Provider-specific errors belong to Group H.

---

# 33. Validation Error Ownership

The layer that detects an error should have clear responsibility.

Example:

```text id="yaq1r7"
quantity is string
→ request/schema validation

user not logged in
→ authentication

user doesn't own order
→ authorization

order outside cancellation window
→ domain validation

payment provider rejected payment
→ external/payment validation
```

Do not report all of these as generic:

```text id="2f4op5"
INVALID_REQUEST
```

unless the error contract deliberately groups them.

---

# 34. Validation Error Stability

Once error codes are part of the API contract, they are version-sensitive.

Do not casually rename:

```text id="3z2q8m"
INSUFFICIENT_STOCK
```

to:

```text id="7p9ssg"
OUT_OF_STOCK_ERROR
```

within Version 1.

Error-code design is finalized later, but stability must be recognized now.

---

# 35. Field-Level Validation Errors

The future error contract should be capable of identifying which input field failed.

Example conceptually:

```json id="5i4azt"
{
  "field": "fulfillment_type",
  "code": "INVALID_VALUE"
}
```

For:

```text id="rqgcrj"
delivery_address.city
```

nested field paths should be representable.

Do not finalize the exact JSON format yet.

---

# 36. Multiple Validation Errors

Determine whether the API should report:

```text id="jv8v5t"
first error only
```

or:

```text id="g6ujl9"
all safely detectable validation errors
```

For form-heavy operations, returning multiple independent input errors is generally more useful.

Recommended:

> Return all safely determinable schema/input validation errors for the same request.

Domain failures may still stop processing earlier when continuing would be unsafe.

---

# 37. Validation Order

Use the following conceptual ordering:

```text id="po9pmk"
Transport
   ↓
Schema/type validation
   ↓
Authentication
   ↓
Authorization
   ↓
Domain validation
   ↓
Concurrency/transaction checks
   ↓
External dependencies
   ↓
Persistence/workflow
```

Do not access business records unnecessarily before basic input validation.

Do not perform expensive external operations before authorization.

---

# 38. Do Not Leak Authorization Information

Validation order must not reveal private information.

Example:

An unauthenticated caller should not be able to learn:

```text id="w8n5vk"
whether Order OD-12345 exists
```

by deliberately sending invalid ownership queries.

Authorization and resource exposure must be designed to avoid enumeration/leakage.

---

# 39. Not Found vs Unauthorized

The exact API behavior will be defined later, but identify the security issue:

```text id="k6m9n0"
Does revealing "order exists but you don't own it"
leak information?
```

Customer-owned resources need deliberate handling.

Do not implement an endpoint-level response yet.

---

# 40. Database Validation vs Domain Validation

The database may enforce:

```text id="4vwf7m"
uniqueness
non-null constraints
foreign-key integrity
```

but database constraints do not replace domain validation.

Example:

```text id="qk96fx"
Database can enforce product_id exists.

Database cannot by itself determine:
MADE_TO_ORDER products cannot be checked out.
```

Keep those responsibilities separate.

---

# 41. Database Constraints as Last Line of Defense

Later physical schema design should enforce appropriate structural invariants.

Conceptually:

```text id="9jsh3c"
Domain validation
+
Database integrity
```

are complementary.

Do not rely solely on either one.

---

# 42. Validation and Transactions

Some domain validation must occur inside or immediately around a transaction.

Examples:

```text id="u3t4zh"
stock availability
order creation
reservation
critical order transitions
```

The final implementation must prevent:

```text validate
↓
state changes before operation
↓
validated assumption becomes stale
```

where concurrency could invalidate the decision.

---

# 43. Optimistic vs Pessimistic Validation

Identify where later implementation may need:

```text id="q1k2ar"
optimistic locking
pessimistic locking
atomic update
transaction isolation
```

This phase does not choose the mechanism.

The requirement is:

> Business validation must remain correct under concurrent operations.

---

# 44. Idempotency and Validation

Idempotency must happen at the correct boundary.

Example:

```text id="z6l6qk"
POST checkout
```

If the exact same idempotent request is retried, the API should not perform the business operation twice.

Validation and idempotency must work together.

Do not validate and create duplicate business records independently for each retry.

---

# 45. Validation of Client-Controlled Fields

Create an explicit conceptual distinction:

```text id="nb0nqh"
ACCEPT
REJECT
IGNORE
SERVER-GENERATE
```

Example:

```text id="q0f99w"
client sends quantity
→ ACCEPT after validation

client sends order status
→ REJECT/ignore according to endpoint contract

client sends order total
→ not authoritative; reject as client-controlled field

client sends created_at
→ reject/ignore
```

Do not allow arbitrary fields to flow through.

---

# 46. Unknown Fields

Use the Phase 1.14 policy.

Validation must enforce the chosen unknown-field behavior consistently.

Recommended baseline:

> Strict validation for create/update/action input, with explicitly documented exceptions.

Do not allow unknown fields to become arbitrary database properties.

---

# 47. Validation Messages

Separate:

```text id="z9b9ur"
machine-stable error code
```

from:

```text id="8cpgp0"
human-readable message
```

The frontend should be able to act on:

```text id="74v8ng"
INSUFFICIENT_STOCK
```

without parsing English prose.

Messages may change more safely than codes, subject to the API compatibility policy.

---

# 48. Localization

Do not require the backend to produce every frontend-localized message.

For example:

```text id="aoy4cp"
code = INSUFFICIENT_STOCK
```

Next.js/Flutter may display:

```text id="l75m3t"
"Only 2 units are available."
```

according to UI/localization strategy.

The API's machine error code remains authoritative.

---

# 49. Validation Message Security

Messages must not expose:

```text id="6u2f6k"
SQL details
database schema
stack traces
internal file paths
API secrets
payment provider credentials
private records
```

Do not return raw exception messages to clients.

---

# 50. File Validation

Attachments require additional validation.

Later implementation must consider:

```text id="n9k22o"
file size
content type
extension
actual file signature
storage permissions
malware/security scanning where appropriate
```

The client-provided MIME type is not itself trustworthy.

Storage provider remains deferred.

---

# 51. Authentication Input Validation

Authentication-specific validation may include:

```text id="4rmyk5"
email format
password requirements
verification tokens
reset tokens
```

The exact security rules belong to the authentication phase.

This phase establishes that authentication input must still pass the same layered validation model.

---

# 52. Password Validation Security

Never return validation details that help an attacker unnecessarily.

Do not reveal:

```text id="s8iwl7"
whether an email belongs to an account
```

through password-reset flows unless the later authentication contract deliberately allows it.

---

# 53. Validation and Rate Limiting

Some validation-sensitive operations should later have rate limits.

Examples:

```text id="xah3by"
login
password recovery
anonymous request submission
anonymous enquiry submission
checkout
payment
```

Do not implement rate limiting now.

Record the relationship between validation and abuse prevention.

---

# 54. Validation and Logging

Validation failures may be logged for diagnostics, but do not log sensitive input indiscriminately.

Never log:

```text id="mb0n5k"
password
authentication token
payment secrets
full private addresses
```

unless there is a specific approved secure logging requirement.

---

# 55. Validation and API Compatibility

Validation rules are part of the API behavior.

Within Version 1:

Changing:

```text id="csn8fv"
previously accepted input
```

to:

```text id="q5a2i3"
rejected input
```

can be a breaking change.

Similarly, making a field suddenly required can break existing clients.

Therefore validation changes must follow the API versioning policy.

---

# 56. Closed Enum Compatibility

Because Version 1 enums are closed:

Adding:

```text id="l3d39c"
NEW_STATUS
```

may affect existing clients.

Treat the addition as a formal API compatibility decision.

Do not silently add enum values to Laravel validation.

---

# 57. Business Rule Centralization

Define the architecture principle:

> A business rule must have one authoritative implementation boundary.

For example:

```text id="xk72o0"
20-minute cancellation rule
→ domain/application layer
```

not separately reimplemented in:

```text Next.js
Flutter
Laravel controller
Laravel model
```

Clients may mirror the rule for UX, but the backend remains authoritative.

---

# 58. Validation Reuse

Validation that is purely structural may be reusable between endpoints.

However, do not create an enormous generic validation system that hides domain meaning.

Prefer clear, named rules.

Example conceptually:

```text id="7f7i4z"
ValidateCheckoutInput
ValidateDeliverySelection
ValidateOrderCancellation
```

rather than:

```text id="w7g2va"
UniversalBusinessValidator
```

Implementation comes later.

---

# 59. Validation and Domain Layer Separation

The eventual architecture should conceptually resemble:

```text id="us9v2x"
HTTP Request
      ↓
Request Validator
      ↓
Application Command
      ↓
Domain Validation
      ↓
Domain/Application Service
      ↓
Repository/Transaction
```

Do not place every business rule inside Laravel Form Request validation.

---

# 60. Validation and API Resources

Validation input definitions must align with API resource definitions from Phase 1.6.

Do not allow:

```text id="s3k6f0"
resource says field is read-only
```

while:

```text id="0ibf7p"
validator accepts it as mutable input
```

The two contracts must remain consistent.

---

# 61. Validation and Request Types

Classify validation by request purpose:

```text id="f0yltp"
CREATE
UPDATE
ACTION
QUERY
AUTHENTICATION
UPLOAD
WEBHOOK
```

Each has different validation characteristics.

---

# 62. Query Validation

Query parameters from Phase 1.11 must also be validated.

Examples:

```text id="0u4n9a"
page must be positive integer
per_page within allowed limit
product_type in closed enum
sort in allow-list
min_price <= max_price
```

Do not treat query strings as inherently safe because they are not request bodies.

---

# 63. Pagination Validation

Pagination validation should enforce Phase 1.12 rules.

Conceptually:

```text id="k8om86"
page >= 1
1 <= per_page <= maximum
```

Do not duplicate these rules differently on every collection endpoint.

---

# 64. Sorting Validation

Only documented sort fields may be accepted.

Do not map arbitrary query input to arbitrary database columns.

This is both a correctness and security requirement.

---

# 65. Filtering Validation

Only documented filter fields/operators may be accepted.

Do not allow an arbitrary:

```text id="g38v5z"
field
operator
value
```

DSL to become a generic database query.

---

# 66. Validation of Relationships

When a request references another resource:

```text id="ro6v5m"
product_id
variant_id
category_id
```

validate not only:

```text id="f2n00d"
does it exist?
```

but when required:

```text id="lo9fl8"
does it belong to the expected parent?
is it active?
is it allowed in this operation?
```

---

# 67. Example — Variant Validation

Bad conceptual validation:

```text id="rm7gv9"
variant_id exists
```

Better:

```text id="xzzn6v"
variant exists
AND
variant belongs to product
AND
variant is active
AND
variant is purchasable
```

This illustrates the distinction between structural existence and business validity.

---

# 68. Example — Delivery Validation

Bad:

```text id="c8twc0"
delivery_address exists
```

Better:

```text id="6p2x4h"
fulfillment_type = DELIVERY
AND
delivery information is sufficient
AND
customer is authorized for checkout
AND
delivery fee will be determined by backend
```

---

# 69. Example — Cancellation Validation

Bad:

```text id="r5p9pz"
status != COMPLETED
```

Better:

```text id="86q7uf"
authenticated owner
AND
within 20-minute window
AND
current state permits customer cancellation
AND
operation has not already been completed
```

---

# 70. Validation and External Dependencies

Some validation requires external systems.

Payment is the major example.

Later:

```text id="vxy2ww"
Payment request
→ provider
→ provider response/webhook
→ backend verification
```

External provider failures should not be mistaken for normal input validation errors.

Payment-specific behavior belongs to Group H.

---

# 71. Validation Failure Atomicity

A failed business validation must not leave partial business state.

Example:

```text id="5xw5c6"
Order creation starts
↓
inventory reservation succeeds
↓
later validation fails
```

The final implementation must prevent the reservation from remaining incorrectly applied.

This is a later transaction design responsibility.

---

# 72. Validation and Auditability

Important validation-triggered state changes should eventually be auditable.

Examples:

```text id="7v1v0n"
order cancellation
order status transition
inventory adjustment
payment status change
```

Not every failed request needs a permanent business audit record.

Do not build audit infrastructure yet.

---

# 73. Validation Decision Hierarchy

Use this hierarchy:

```text id="xamnlx"
1. Is the request structurally valid?
        ↓
2. Is the caller authenticated when required?
        ↓
3. Is the caller authorized?
        ↓
4. Is the requested operation business-valid?
        ↓
5. Is the current state still valid under concurrency?
        ↓
6. Can the operation safely execute?
```

This hierarchy should appear in the central API architecture documentation.

---

# 74. Required Documentation Updates

Update:

### `docs/api/api-contract.md`

Add:

```text id="nmw1dk"
## Validation Conventions

### Validation Layers
### Request Validation
### Query Validation
### Cross-Field Validation
### Domain Validation
### State Validation
### Authorization
### Enum Validation
### Client vs Server Validation
### Unknown Fields
### Immutable Fields
### Validation Codes
### Validation Compatibility
### File Validation
```

### `docs/api/api-conventions.md`

Add the reusable validation rules.

### `docs/api/api-resources.md`

For each resource where applicable, reserve sections for:

```text id="ck83po"
input validation
mutable fields
immutable fields
conditional fields
business validation
```

### `docs/domain/business-rules.md`

Make sure domain rules identified in Phase 1.3 remain clearly authoritative.

### `docs/decisions.md`

Record the major validation architecture decisions.

### `AGENTS.md`

Only update if a permanent engineering rule should be added, such as:

> Backend/domain validation is authoritative; frontend validation is advisory.

---

# 75. Decision Record Examples

Record major decisions such as:

```text id="84tqja"
API-VAL-001
Validation is layered rather than implemented as one monolithic validator.

API-VAL-002
Frontend validation is advisory; backend validation is authoritative.

API-VAL-003
Version 1 enums are closed.

API-VAL-004
Business state transitions cannot be performed through uncontrolled generic field updates.

API-VAL-005
Client-supplied financial totals are never authoritative.

API-VAL-006
Critical inventory validation must remain concurrency-safe.
```

Use the project's existing decision numbering convention where appropriate.

---

# 76. Validation Matrix

Produce a conceptual matrix such as:

| Operation       | Transport | Schema |     Auth | Authorization | Domain | Concurrency |
| --------------- | --------: | -----: | -------: | ------------: | -----: | ----------: |
| Browse products |       Yes |    Yes |       No |            No |    Yes |         Low |
| Add cart item   |       Yes |    Yes |     Yes* |           Yes |    Yes |      Medium |
| Checkout        |       Yes |    Yes |      Yes |           Yes |    Yes |        High |
| Cancel order    |       Yes |    Yes |      Yes |           Yes |    Yes | Medium/High |
| Submit request  |       Yes |    Yes | Optional |    Contextual |    Yes |         Low |
| Submit enquiry  |       Yes |    Yes | Optional |    Contextual |    Yes |         Low |
| Update profile  |       Yes |    Yes |      Yes |           Yes |    Yes |         Low |
| Ship order      |       Yes |    Yes |      Yes |           Yes |    Yes |        High |

`*` depends on the final cart/authentication design; do not use this table to override earlier approved behavior.

---

# 77. Validation Testing Requirements

Each business validation rule must eventually have a test.

Examples:

```text id="o1b9rs"
invalid product type
insufficient stock
invalid variant
missing delivery information
invalid enum
unknown field
unauthorized order
expired cancellation window
invalid order transition
duplicate checkout
```

Tests should eventually cover both successful and failed cases.

---

# 78. Validation Test Independence

Do not test business rules only through the React or Flutter UI.

Important domain rules must be testable at the backend/domain level.

UI tests are useful but insufficient.

---

# 79. Validation and Integration Testing

Later API integration tests should confirm:

```text id="1xk5qf"
request
→ validation
→ authorization
→ business operation
```

works as a complete contract.

Do not rely entirely on unit tests that never execute the actual API boundary.

---

# 80. Validation and End-to-End Testing

Later end-to-end tests should verify important user flows:

```text id="d17vy0"
anonymous browse
authenticated checkout
request submission
enquiry submission
order cancellation
order tracking
```

The backend validation remains the source of truth.

---

# 81. Definition of Done

Phase 1.15 is complete when:

1. Validation layers are explicitly defined.
2. Transport validation is separated from business validation.
3. Schema validation is separated from domain validation.
4. Authentication is separated from authorization.
5. Cross-field validation is defined.
6. State-dependent validation is defined.
7. Inventory validation is identified as concurrency-sensitive.
8. Pricing validation rules are defined.
9. Delivery fee authority is defined.
10. Checkout validation flow is defined.
11. Cancellation validation includes the 20-minute rule.
12. Order-state validation is defined.
13. Anonymous request/enquiry validation is defined.
14. Unknown-field handling is aligned with Phase 1.14.
15. Server-controlled fields are explicitly protected.
16. Closed Version 1 enum validation is explicit.
17. Validation errors have conceptual categories.
18. Validation changes are recognized as API compatibility changes.
19. Frontend validation is explicitly advisory.
20. Backend/domain validation is authoritative.
21. Payment-specific validation remains assigned to **Phase Group H**.
22. Consolidated project documents are updated.
23. No endpoint-specific validation implementation has been written.

---

# 82. Explicitly Out of Scope

Do NOT:

```text id="vm75a2"
Create Laravel Form Request classes
Create Laravel validation rules
Create DTOs
Create domain validators
Create database constraints
Create migrations
Create OpenAPI request schemas
Define final error JSON
Implement error middleware
Implement authentication
Implement authorization
Implement payment validation
Build Next.js forms
Build Flutter forms
```

---

# 83. STOP CONDITION — Mandatory

After updating:

```text id="0izxkl"
docs/api/api-contract.md
docs/api/api-conventions.md
docs/api/api-resources.md
docs/domain/business-rules.md
docs/decisions.md
```

and completing the validation/consistency review:

**STOP.**

Do not automatically implement validation.

Do not create Laravel `FormRequest` classes.

Do not create controllers.

Do not create OpenAPI request schemas.

Do not create frontend validators.

The next phase must be explicitly requested.

Recommended next phase:

# Phase 1.16 — Define API Error Contract

That phase should establish the exact structure and semantics for API failures, including:

```text
validation errors
authentication errors
authorization errors
not-found behavior
business-rule errors
conflict/concurrency errors
external-service errors
error codes
messages
field-level errors
request IDs
HTTP status mapping
```

while continuing to maintain these rules in the consolidated API documentation rather than creating many small permanent markdown files.
